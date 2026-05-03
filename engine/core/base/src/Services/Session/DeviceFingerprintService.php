<?php

declare(strict_types=1);

namespace Core\Base\Services\Session;

use Core\Base\Services\Session\Contracts\DeviceFingerprintServiceInterface;
use DeviceDetector\Cache\LaravelCache;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * DeviceFingerprintService — สร้าง device fingerprint และวิเคราะห์ความเสี่ยง
 *
 * คุณสมบัติหลัก:
 *  - Server-side fingerprint จาก stable signals (HMAC-SHA256 signed)
 *  - รองรับ Client Hints API (Chromium 90+) + User-Agent fallback
 *  - ตรวจจับ bot, headless browser, HTTP library, outdated browser
 *  - Risk scoring แบบ config-driven (ปรับ weights/thresholds ผ่าน config)
 *
 * หมายเหตุการลงทะเบียน:
 *  - ต้องลงทะเบียนเป็น bind() (transient) ไม่ใช่ singleton() เพราะ class มี mutable state
 *    ต่อ request — ใช้ singleton จะทำให้ state รั่วข้าม request ใน Octane/long-running process
 *
 * การใช้งาน:
 *  - $service->fingerprint($request) — สร้าง fingerprint hash
 *  - $service->analyze($request)     — วิเคราะห์ครบทุกมิติ + risk score
 *  - $service->fromRequest($request) — parse แล้วใช้ accessors ทีละตัว
 *
 * Config keys (services.fingerprint.*):
 *  - browser_thresholds: กำหนด minimum version ต่อ browser
 *  - risk_weights: กำหนดคะแนนต่อระดับ (high/medium/low)
 *  - max_risk_score: คะแนนสูงสุด (default: 100)
 *  - max_proxy_hops: จำนวน proxy hops สูงสุดที่ยอมรับ (default: 3)
 */
final class DeviceFingerprintService implements DeviceFingerprintServiceInterface
{
    private DeviceDetector $detector;

    private bool $parsed = false;

    /** @var array<string, mixed> ข้อมูล client ที่ parse แล้ว */
    private array $parsedClient = [];

    /** @var array<string, mixed> ข้อมูล OS ที่ parse แล้ว */
    private array $parsedOs = [];

    /** @var array<string, mixed> ข้อมูล identity เพิ่มเติมจาก ClientHints + request */
    private array $parsedIdentity = [];

    private ?string $currentRequestId = null;

    /**
     * สร้าง server-side fingerprint จาก stable signals
     *
     * Signals ที่ใช้:
     *  - Browser name & type (ไม่มี version)
     *  - OS name + major version
     *  - Browser major version
     *  - Device type / brand / model
     *  - Client Hints (brand names, platform, arch, bitness, model, form-factors)
     *  - Accept-Language + Accept-Encoding (normalized)
     *  - App identity (native Android)
     *
     * ใช้ HMAC-SHA256 + app.key เพื่อป้องกัน rainbow table attack
     *
     * @param  Request|null  $request  HTTP request ปัจจุบัน (ถ้า null จะใช้ current request)
     * @return string HMAC-SHA256 fingerprint hash (hex, 64 chars)
     *
     * @throws RuntimeException ถ้า APP_KEY ยังไม่ได้ตั้งค่า (ป้องกัน HMAC key ว่าง)
     */
    public function fingerprint(?Request $request = null): string
    {
        if (! $this->parsed) {
            $this->fromRequest($request);
        }

        // หาก $request ยังคงเป็น null (กรณี fromRequest ถูกเรียกก่อนหน้าแล้ว)
        // ให้ใช้ helper request() เพื่อดึง instance ปัจจุบัน
        $request = $request ?? request();

        $appKeyRaw = config('app.key', '');
        $appKey = \is_string($appKeyRaw) ? $appKeyRaw : '';
        if ($appKey === '') {
            throw new RuntimeException(
                'APP_KEY is not set. Cannot generate a secure fingerprint without an HMAC key.',
            );
        }

        $id = $this->parsedIdentity;

        // Brand names จาก Sec-CH-UA — ตัด version ทิ้ง, sort เพื่อ stability
        $brandNames = array_keys((array) ($id['ch_brand_list'] ?? []));
        sort($brandNames);

        $components = [
            // ── Browser (parsed) ─────────────────────────────────────────
            'browser' => $this->browser() ?? '',
            'browser_type' => $this->clientType() ?? '',
            'browser_ver_maj' => explode('.', (string) ($this->browserVersion() ?? ''))[0],
            // ── OS / Device ───────────────────────────────────────────────
            'os' => $this->os() ?? '',
            'os_ver_major' => explode('.', (string) ($this->osVersion() ?? ''))[0],
            'device' => $this->device(),
            'brand' => $this->brand() ?? '',
            'model' => $this->model() ?? '',
            // ── Language / Encoding (normalized) ─────────────────────────
            'lang' => $this->normalizeLang((string) $request->header('Accept-Language', '')),
            'accept_enc' => (string) $request->header('Accept-Encoding', ''),
            // ── Client Hints — stable signals ────────────────────────────
            'ch_brands' => implode(',', $brandNames),
            'ch_platform' => (static function (mixed $v): string {
                return is_scalar($v) ? (string) $v : '';
            })($id['ch_platform'] ?? ''),
            'ch_mobile' => ($id['ch_mobile'] ?? false) ? '1' : '0',
            'ch_arch' => (static function (mixed $v): string {
                return is_scalar($v) ? (string) $v : '';
            })($id['ch_arch'] ?? ''),
            'ch_bitness' => (static function (mixed $v): string {
                return is_scalar($v) ? (string) $v : '';
            })($id['ch_bitness'] ?? ''),
            'ch_model' => (static function (mixed $v): string {
                return is_scalar($v) ? (string) $v : '';
            })($id['ch_model'] ?? ''),
            'ch_wow64' => ($id['ch_wow64'] ?? false) ? '1' : '0',
            'ch_form' => implode(',', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', (array) ($id['ch_form_factors'] ?? []))),
            // ── Native App identity ───────────────────────────────────────
            'app_id' => (static function (mixed $v): string {
                return is_scalar($v) ? (string) $v : '';
            })($id['ch_app'] ?? ''),
        ];

        $payload = json_encode($components, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        // HMAC-SHA256 ป้องกัน offline rainbow table / preimage attack
        return hash_hmac('sha256', $payload, $appKey);
    }

    /**
     * สร้าง instance จาก Request — parse ครั้งเดียวต่อ request
     *
     * Cache key ใช้ทั้ง User-Agent + Client Hints fingerprint เพื่อป้องกัน
     * สถานการณ์ที่ UA เดิมแต่ Client Hints ต่าง (เช่น browser update ระหว่าง session)
     *
     * รองรับ identity signals ครบทุกมิติ:
     *  - User-Agent (legacy)
     *  - Client Hints (Sec-CH-UA-*) ทุกตัวที่ library รองรับ
     *  - Mobile App identity (X-Requested-With)
     *  - Form Factor (Sec-CH-UA-Form-Factors)
     *  - isMobile / isDesktop / isTouchEnabled จาก DeviceDetector
     *
     * @param  Request|null  $request  HTTP request ปัจจุบัน (ถ้า null จะใช้ current request)
     * @return static instance ที่ parse แล้ว (fluent)
     */
    public function fromRequest(?Request $request = null): static
    {
        $request = $request ?? request();
        $ua = $request->userAgent() ?? '';
        $chPlatform = $request->header('Sec-CH-UA-Platform', '');
        $chBrands = $request->header('Sec-CH-UA', '');

        // Cache key ครอบคลุม UA + Client Hints ป้องกัน false cache hit
        $cacheKey = $ua.'|'.$chPlatform.'|'.$chBrands;

        if ($this->parsed && isset($this->detector) && $this->buildCacheKey() === $cacheKey) {
            return $this;
        }

        AbstractDeviceParser::setVersionTruncation(
            AbstractDeviceParser::VERSION_TRUNCATION_NONE,
        );

        $clientHints = ClientHints::factory($request->server->all());

        $this->detector = new DeviceDetector($ua, $clientHints);
        $this->detector->setCache(new LaravelCache);
        $this->detector->parse();

        $clientData = $this->detector->getClient();
        $this->parsedClient = is_array($clientData) ? $clientData : [];
        $osData = $this->detector->getOs();
        $this->parsedOs = is_array($osData) ? $osData : [];

        $this->parsedIdentity = [
            // ── Cache key (ใช้ตรวจ invalidation) ───────────────────
            '_cache_key' => $cacheKey,
            // ── Device ──────────────────────────────────────────────
            'is_mobile' => $this->detector->isMobile(),
            'is_desktop' => $this->detector->isDesktop(),
            'is_touch' => $this->detector->isTouchEnabled(),
            'is_bot' => $this->detector->isBot(),
            // ── Client Hints (high-entropy) ─────────────────────────
            'ch_model' => $clientHints->getModel(),
            'ch_platform' => $clientHints->getOperatingSystem(),
            'ch_platform_ver' => $clientHints->getOperatingSystemVersion(),
            'ch_arch' => $clientHints->getArchitecture(),
            'ch_bitness' => $clientHints->getBitness(),
            'ch_mobile' => $clientHints->isMobile(),
            'ch_brand_list' => $clientHints->getBrandList(),
            'ch_browser_ver' => $clientHints->getBrandVersion(),
            'ch_form_factors' => $clientHints->getFormFactors(),
            'ch_app' => $clientHints->getApp(),
            'ch_wow64' => $request->header('Sec-CH-UA-WoW64') === '?1',
            // ── Network identity ────────────────────────────────────
            'ip' => $request->ip(),
            'via' => $request->header('Via', ''),
            'dnt' => $request->header('DNT', ''),
            'connection' => $request->header('Connection', ''),
        ];

        $this->parsed = true;

        return $this;
    }

    public function getRequestId(?Request $request = null): string
    {
        $request = $request ?? request();
        if ($this->currentRequestId === null) {
            $fingerprint = $this->fingerprint($request);
            $random = Uuid::uuid7()->getHex()->toString();
            $ip = $request->ip() ?? '';
            $appKey = \is_string(config('app.key')) ? (string) config('app.key') : '';
            $sessionid = hash_hmac('sha256', $fingerprint.$ip.microtime(true).$random, $appKey);
            $this->currentRequestId = $sessionid;
        }

        return $this->currentRequestId;
    }

    // ─── Quick accessors ────────────────────────────────────────

    /**
     * ตรวจว่าเป็น bot หรือไม่
     */
    public function isBot(): bool
    {
        $this->ensureParsed();

        return (bool) ($this->parsedIdentity['is_bot'] ?? false);
    }

    /**
     * ดึงข้อมูล bot (ถ้าเป็น bot)
     *
     * @return array<string, mixed>|null ข้อมูล bot หรือ null ถ้าไม่ใช่ bot
     */
    public function botInfo(): ?array
    {
        $this->ensureParsed();

        // ใช้ cached is_bot แทนการเรียก detector->isBot() ซ้ำ
        if (! (bool) ($this->parsedIdentity['is_bot'] ?? false)) {
            return null;
        }

        $botData = $this->detector->getBot();

        return is_array($botData) ? $botData : null;
    }

    /**
     * ดึงชื่อประเภทอุปกรณ์ เช่น 'desktop', 'smartphone', 'tablet'
     */
    public function device(): string
    {
        $this->ensureParsed();

        return $this->detector->getDeviceName();
    }

    /**
     * ดึงชื่อ browser เช่น 'Chrome', 'Firefox', 'Safari'
     */
    public function browser(): ?string
    {
        $this->ensureParsed();

        return $this->parsedClient['name'] ?? null;
    }

    /**
     * ดึง version ของ browser เช่น '120.0.6099.109'
     */
    public function browserVersion(): ?string
    {
        $this->ensureParsed();

        return $this->parsedClient['version'] ?? null;
    }

    /**
     * ดึงประเภท client เช่น 'browser', 'library', 'feed reader'
     */
    public function clientType(): ?string
    {
        $this->ensureParsed();

        return $this->parsedClient['type'] ?? null;
    }

    /**
     * ดึงชื่อ OS เช่น 'Windows', 'Android', 'iOS'
     */
    public function os(): ?string
    {
        $this->ensureParsed();

        return $this->parsedOs['name'] ?? null;
    }

    /**
     * ดึง version ของ OS เช่น '11', '14.2'
     */
    public function osVersion(): ?string
    {
        $this->ensureParsed();

        return $this->parsedOs['version'] ?? null;
    }

    /**
     * ดึง OS ที่แม่นที่สุด — ใช้ Client Hints ก่อน (Chromium 90+) fallback ไป UA
     *
     * Client Hints (Sec-CH-UA-Platform) แม่นกว่า User-Agent เพราะ browser declare ตรงๆ
     * ไม่มีปัญหา UA spoofing
     *
     * @return array{source: string, name: string|null, version: string|null}
     *                                                                        source: 'client_hints' | 'user_agent' | 'unknown'
     */
    public function osResolved(): array
    {
        $this->ensureParsed();

        $chPlatform = $this->parsedIdentity['ch_platform'] ?? '';
        $chVersion = $this->parsedIdentity['ch_platform_ver'] ?? '';

        if ($chPlatform !== '') {
            return [
                'source' => 'client_hints',
                'name' => $chPlatform,
                'version' => $chVersion !== '' ? $chVersion : ($this->parsedOs['version'] ?? null),
            ];
        }

        $uaOs = $this->parsedOs['name'] ?? null;
        if (! empty($uaOs)) {
            return [
                'source' => 'user_agent',
                'name' => $uaOs,
                'version' => $this->parsedOs['version'] ?? null,
            ];
        }

        return ['source' => 'unknown', 'name' => null, 'version' => null];
    }

    /**
     * ดึงชื่อแบรนด์อุปกรณ์ เช่น 'Samsung', 'Apple'
     */
    public function brand(): ?string
    {
        $this->ensureParsed();

        return $this->detector->getBrandName() ?: null;
    }

    /**
     * ดึงชื่อรุ่นอุปกรณ์ เช่น 'Galaxy S24', 'iPhone 15'
     */
    public function model(): ?string
    {
        $this->ensureParsed();

        return $this->detector->getModel() ?: null;
    }

    /**
     * ตรวจว่าเป็นอุปกรณ์มือถือหรือไม่
     */
    public function isMobile(): bool
    {
        $this->ensureParsed();

        return (bool) ($this->parsedIdentity['is_mobile'] ?? false);
    }

    /**
     * ตรวจว่าเป็นอุปกรณ์ desktop หรือไม่
     */
    public function isDesktop(): bool
    {
        $this->ensureParsed();

        return (bool) ($this->parsedIdentity['is_desktop'] ?? false);
    }

    /**
     * ตรวจว่ารองรับ touch screen หรือไม่
     */
    public function isTouchEnabled(): bool
    {
        $this->ensureParsed();

        return (bool) ($this->parsedIdentity['is_touch'] ?? false);
    }

    /**
     * ดึง Android app id จาก X-Requested-With header
     */
    public function appId(): string
    {
        $this->ensureParsed();

        return $this->parsedIdentity['ch_app'] ?? '';
    }

    /**
     * ดึง form factors จาก Sec-CH-UA-Form-Factors เช่น ['desktop'], ['phone']
     *
     * @return string[]
     */
    public function formFactors(): array
    {
        $this->ensureParsed();

        return $this->parsedIdentity['ch_form_factors'] ?? [];
    }

    /**
     * ดึง bitness จาก Sec-CH-UA-Bitness เช่น "64", "32"
     */
    public function bitness(): string
    {
        $this->ensureParsed();

        return $this->parsedIdentity['ch_bitness'] ?? '';
    }

    /**
     * ดึง brand list จาก Sec-CH-UA-Full-Version-List เช่น ['Chrome' => '120.0.0.0']
     *
     * @return array<string, string>
     */
    public function brandList(): array
    {
        $this->ensureParsed();

        return $this->parsedIdentity['ch_brand_list'] ?? [];
    }

    /**
     * ดึงข้อมูล identity ดิบทั้งหมดที่เก็บไว้ (ไม่รวม _cache_key)
     *
     * @return array<string, mixed>
     */
    public function identity(): array
    {
        $this->ensureParsed();

        return array_filter(
            $this->parsedIdentity,
            fn (string $key) => ! str_starts_with($key, '_'),
            ARRAY_FILTER_USE_KEY,
        );
    }

    // ─── Fraud analysis ─────────────────────────────────────────

    /**
     * วิเคราะห์ device ครบทุกมิติ + risk scoring
     *
     * รวม fingerprint + identity + risk signals ใน 1 pass (ไม่ parse ซ้ำ)
     *
     * @param  Request|null  $request  HTTP request ปัจจุบัน (ถ้า null จะใช้ current request)
     * @return array<string, mixed> ผลวิเคราะห์ครบทุกมิติ รวม risk_score
     */
    public function analyze(?Request $request = null): array
    {
        $request = $request ?? request();
        $fp = $this->fingerprint($request);
        $riskSignals = $this->detectRiskSignals($request);
        $id = $this->parsedIdentity;

        return [
            'fingerprint' => $fp,
            // ── Bot ─────────────────────────────────────────────────
            'is_bot' => $id['is_bot'],
            'bot_info' => $id['is_bot'] ? $this->detector->getBot() : null,
            // ── Browser / Client ────────────────────────────────────
            'browser' => $this->parsedClient['name'] ?? null,
            'browser_ver' => $this->parsedClient['version'] ?? null,
            'browser_type' => $this->parsedClient['type'] ?? null,
            'brand_list' => $id['ch_brand_list'] ?? [],
            'app_id' => $id['ch_app'] ?? '',
            // ── OS ──────────────────────────────────────────────────
            'os' => $this->parsedOs['name'] ?? null,
            'os_ver' => $this->parsedOs['version'] ?? null,
            'os_resolved' => $this->osResolved(),
            'ch_platform' => $id['ch_platform'] ?? null,
            'ch_platform_ver' => $id['ch_platform_ver'] ?? null,
            // ── Device ──────────────────────────────────────────────
            'device_type' => $this->detector->getDeviceName(),
            'device_brand' => $this->detector->getBrandName() ?: null,
            'device_model' => $this->detector->getModel() ?: null,
            'ch_model' => $id['ch_model'] ?? null,
            'is_mobile' => $id['is_mobile'],
            'is_desktop' => $id['is_desktop'],
            'is_touch' => $id['is_touch'],
            'form_factors' => $id['ch_form_factors'] ?? [],
            // ── Hardware ────────────────────────────────────────────
            'ch_arch' => $id['ch_arch'] ?? null,
            'ch_bitness' => $id['ch_bitness'] ?? null,
            // ── Network ─────────────────────────────────────────────
            'ip' => $request->ip(),
            'via' => $id['via'] ?? null,
            'dnt' => $id['dnt'] ?? null,
            // ── Risk ────────────────────────────────────────────────
            'risk_signals' => $riskSignals,
            'risk_score' => $this->calculateScore($riskSignals),
        ];
    }

    // ─── Risk detection ─────────────────────────────────────────

    /**
     * ตรวจจับสัญญาณความเสี่ยงจาก request
     *
     * ตรวจสอบ: bot, missing UA, HTTP library, unusual client type,
     * outdated browser, excessive proxy hops, headless browser, missing Accept header
     *
     * หมายเหตุ: ไม่ตรวจ 'chrome headless' ใน isOutdatedBrowser() เพราะ headless
     * ถูกตรวจจับด้วย level=high ที่นี่อยู่แล้ว ป้องกัน double-counting
     *
     * @param  Request  $request  HTTP request ปัจจุบัน
     * @return array<int, array{type: string, level: string, detail?: string}> รายการ risk signals
     */
    protected function detectRiskSignals(Request $request): array
    {
        $signals = [];

        // ใช้ cached is_bot ป้องกัน double detection call
        $isBot = (bool) ($this->parsedIdentity['is_bot'] ?? false);

        if ($isBot) {
            $botData = $this->detector->getBot();
            $botName = is_array($botData) ? ($botData['name'] ?? 'unknown bot') : 'unknown bot';
            $signals[] = ['type' => 'bot_detected', 'level' => 'high', 'detail' => $botName];
        }

        // ไม่มี User-Agent
        $ua = $request->userAgent() ?? '';
        if ($ua === '') {
            $signals[] = ['type' => 'missing_user_agent', 'level' => 'high'];
        }

        // HTTP library แทน browser จริง
        if (($this->clientType() ?? '') === 'library') {
            $signals[] = [
                'type' => 'http_library',
                'level' => 'high',
                'detail' => $this->parsedClient['name'] ?? 'unknown',
            ];
        }

        // Feed reader / media player แทน browser
        if (in_array($this->parsedClient['type'] ?? null, ['feed reader', 'mediaplayer', 'pim'], true)) {
            $signals[] = [
                'type' => 'unusual_client_type',
                'level' => 'medium',
                'detail' => $this->parsedClient['type'],
            ];
        }

        // Browser เก่ามาก (ไม่รวม headless — ตรวจแยกด้วย level=high ด้านล่าง)
        if (isset($this->parsedClient['name']) && $this->isOutdatedBrowser()) {
            $signals[] = [
                'type' => 'outdated_browser',
                'level' => 'medium',
                'detail' => (static fn (mixed $v): string => is_scalar($v) ? (string) $v : '')($this->parsedClient['name'] ?? '')
                    .' '.(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '')($this->parsedClient['version'] ?? ''),
            ];
        }

        // Proxy hops มากผิดปกติ
        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor !== null) {
            $hopCount = count(array_filter(array_map('trim', explode(',', $forwardedFor))));
            $maxHopsRaw = config('services.fingerprint.max_proxy_hops', 3);
            $maxHops = is_int($maxHopsRaw) ? $maxHopsRaw : (is_numeric($maxHopsRaw) ? (int) $maxHopsRaw : 3);

            if ($hopCount > $maxHops) {
                $signals[] = ['type' => 'excessive_proxy_hops', 'level' => 'medium', 'detail' => "hops: {$hopCount}"];
            }
        }

        // Headless browser (Playwright / Puppeteer / Selenium)
        // ตรวจก่อน outdated_browser เพื่อให้ risk score สะท้อนความจริง (high ไม่ใช่ medium)
        if ($ua !== '' && preg_match('/HeadlessChrome|Headless|PhantomJS|Selenium|WebDriver|playwright/i', $ua)) {
            // ตัด UA ให้ไม่เกิน 200 chars ป้องกัน log injection / oversized payload
            $signals[] = [
                'type' => 'headless_browser',
                'level' => 'high',
                'detail' => mb_substr($ua, 0, 200),
            ];
        }

        // Accept header ไม่สอดคล้องกับ browser ที่ declare
        $clientType = $this->parsedClient['type'] ?? null;
        $accept = $request->header('Accept', '');
        if (in_array($clientType, ['browser', null], true) && $ua !== '' && $accept === '') {
            $signals[] = ['type' => 'missing_accept_header', 'level' => 'medium'];
        }

        return $signals;
    }

    /**
     * ตรวจว่า browser version ต่ำกว่า threshold ที่กำหนดหรือไม่
     *
     * อ่าน threshold จาก config('services.fingerprint.browser_thresholds')
     * ค่าเริ่มต้น: Chrome 120, Firefox 115, Safari 17, Edge 120
     *
     * หมายเหตุ: 'chrome headless' ถูกตัดออกจากที่นี่ — detectRiskSignals() จัดการด้วย
     *           level=high ผ่าน regex pattern แทน เพื่อป้องกัน double-counting score
     */
    protected function isOutdatedBrowser(): bool
    {
        $versionRaw = $this->parsedClient['version'] ?? '0';
        $version = is_scalar($versionRaw) ? (string) $versionRaw : '0';
        $majorVersion = (int) explode('.', $version)[0];

        $thresholdsRaw = config('services.fingerprint.browser_thresholds', [
            'chrome' => 120,
            'firefox' => 115,
            'safari' => 17,
            'edge' => 120,
        ]);
        $thresholds = is_array($thresholdsRaw) ? $thresholdsRaw : [];

        $nameRaw = $this->parsedClient['name'] ?? '';
        $browserName = strtolower(is_scalar($nameRaw) ? (string) $nameRaw : '');

        $minVersionRaw = $thresholds[$browserName] ?? null;
        $minVersion = is_int($minVersionRaw) ? $minVersionRaw : (is_numeric($minVersionRaw) ? (int) $minVersionRaw : null);

        return $minVersion !== null && $majorVersion > 0 && $majorVersion < $minVersion;
    }

    /**
     * คำนวณ risk score จาก signals ที่ตรวจพบ
     *
     * อ่าน weights จาก config('services.fingerprint.risk_weights')
     * ค่าเริ่มต้น: high=30, medium=15, low=5, สูงสุด 100
     *
     * @param  array<int, array{type: string, level: string}>  $signals  risk signals
     * @return int คะแนนความเสี่ยง (0-max_risk_score)
     */
    protected function calculateScore(array $signals): int
    {
        $weightsRaw = config('services.fingerprint.risk_weights', ['high' => 30, 'medium' => 15, 'low' => 5]);
        $weights = is_array($weightsRaw) ? $weightsRaw : ['high' => 30, 'medium' => 15, 'low' => 5];
        $maxScoreRaw = config('services.fingerprint.max_risk_score', 100);
        $maxScore = is_int($maxScoreRaw) ? $maxScoreRaw : (is_numeric($maxScoreRaw) ? (int) $maxScoreRaw : 100);

        $score = 0;
        foreach ($signals as $signal) {
            $weightValue = $weights[$signal['level']] ?? 0;
            $score += is_int($weightValue) ? $weightValue : (is_numeric($weightValue) ? (int) $weightValue : 0);
        }

        return min($score, $maxScore);
    }

    /**
     * Normalize Accept-Language header เป็น primary language tags
     *
     * ตัวอย่าง: "th-TH,th;q=0.9,en-US;q=0.8" → "th,en"
     * เก็บสูงสุด 3 ภาษาแรก, ตัด locale และ q-value ออก
     */
    private function normalizeLang(string $raw): string
    {
        preg_match_all('/([a-zA-Z]{2,3})(?:-[a-zA-Z0-9]+)*(?:;q=[\d.]+)?/', $raw, $matches);
        $langs = array_unique(array_map('strtolower', $matches[1]));

        return implode(',', array_slice($langs, 0, 3));
    }

    /**
     * คืน cache key ที่ใช้ตอน fromRequest() สำหรับตรวจ invalidation
     */
    private function buildCacheKey(): string
    {
        return $this->parsedIdentity['_cache_key'] ?? '';
    }

    /**
     * ตรวจว่า parse แล้วหรือยัง — throw ถ้ายังไม่ได้เรียก fromRequest()
     *
     * @throws RuntimeException ถ้ายังไม่ได้ parse
     */
    private function ensureParsed(): void
    {
        if (! $this->parsed) {
            throw new RuntimeException(
                'DeviceDetector not initialized. Call fromRequest($request) before using accessors.',
            );
        }
    }
}
