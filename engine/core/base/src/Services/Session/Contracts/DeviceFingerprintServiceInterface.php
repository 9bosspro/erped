<?php

declare(strict_types=1);

namespace Core\Base\Services\Session\Contracts;

use Illuminate\Http\Request;

/**
 * DeviceFingerprintServiceInterface — สัญญาสำหรับ Device Fingerprint Service
 *
 * ครอบคลุม:
 *  - Parse    (fromRequest)
 *  - Hash     (fingerprint)
 *  - Analysis (analyze, detectRiskSignals, calculateScore)
 *  - Accessors (browser, os, device, brand, model, ...)
 */
interface DeviceFingerprintServiceInterface
{
    // ─── Parse ──────────────────────────────────────────────────

    /**
     * Parse request และเก็บข้อมูลใน instance (lazy, cache ต่อ UA + Client Hints)
     *
     * @param  Request  $request  HTTP request ปัจจุบัน
     * @return static instance ที่ parse แล้ว (fluent)
     */
    public function fromRequest(Request $request): static;

    // ─── Hash ───────────────────────────────────────────────────

    /**
     * สร้าง server-side fingerprint hash (HMAC-SHA256)
     *
     * @param  Request  $request  HTTP request ปัจจุบัน
     * @return string HMAC-SHA256 fingerprint hash
     *
     * @throws \RuntimeException ถ้า app.key ว่าง
     */
    public function fingerprint(Request $request): string;

    // ─── Analysis ───────────────────────────────────────────────

    /**
     * วิเคราะห์ device ครบทุกมิติ + risk scoring
     *
     * @param  Request  $request  HTTP request ปัจจุบัน
     * @return array<string, mixed> ผลวิเคราะห์รวม fingerprint + risk_score
     */
    public function analyze(Request $request): array;

    // ─── Bot / Type ──────────────────────────────────────────────

    /** ตรวจว่าเป็น bot หรือไม่ */
    public function isBot(): bool;

    /** @return array<string, mixed>|null ข้อมูล bot หรือ null ถ้าไม่ใช่ bot */
    public function botInfo(): ?array;

    /** ดึงชื่อประเภทอุปกรณ์ เช่น 'desktop', 'smartphone', 'tablet' */
    public function device(): string;

    /** ดึงประเภท client เช่น 'browser', 'library', 'feed reader' */
    public function clientType(): ?string;

    // ─── Browser ────────────────────────────────────────────────

    /** ดึงชื่อ browser เช่น 'Chrome', 'Firefox', 'Safari' */
    public function browser(): ?string;

    /** ดึง version ของ browser เช่น '120.0.6099.109' */
    public function browserVersion(): ?string;

    /** @return array<string, string> brand list จาก Sec-CH-UA-Full-Version-List */
    public function brandList(): array;

    /** ดึง Android app id จาก X-Requested-With header */
    public function appId(): string;

    // ─── OS ─────────────────────────────────────────────────────

    /** ดึงชื่อ OS เช่น 'Windows', 'Android', 'iOS' */
    public function os(): ?string;

    /** ดึง version ของ OS เช่น '11', '14.2' */
    public function osVersion(): ?string;

    /**
     * ดึง OS ที่แม่นที่สุด — Client Hints ก่อน fallback UA
     *
     * @return array{source: string, name: string|null, version: string|null}
     */
    public function osResolved(): array;

    // ─── Device ─────────────────────────────────────────────────

    /** ดึงชื่อแบรนด์อุปกรณ์ เช่น 'Samsung', 'Apple' */
    public function brand(): ?string;

    /** ดึงชื่อรุ่นอุปกรณ์ เช่น 'Galaxy S24', 'iPhone 15' */
    public function model(): ?string;

    /** ตรวจว่าเป็นอุปกรณ์มือถือหรือไม่ */
    public function isMobile(): bool;

    /** ตรวจว่าเป็นอุปกรณ์ desktop หรือไม่ */
    public function isDesktop(): bool;

    /** ตรวจว่ารองรับ touch screen หรือไม่ */
    public function isTouchEnabled(): bool;

    /**
     * ดึง form factors จาก Sec-CH-UA-Form-Factors เช่น ['desktop'], ['phone']
     *
     * @return string[]
     */
    public function formFactors(): array;

    /** ดึง bitness จาก Sec-CH-UA-Bitness เช่น "64", "32" */
    public function bitness(): string;

    // ─── Raw identity ───────────────────────────────────────────

    /**
     * ดึงข้อมูล identity ดิบทั้งหมด
     *
     * @return array<string, mixed>
     */
    public function identity(): array;
}
