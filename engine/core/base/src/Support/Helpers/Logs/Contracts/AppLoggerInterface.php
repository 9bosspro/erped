<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Logs\Contracts;

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * AppLoggerInterface — สัญญาสำหรับ Application Logger Helper
 *
 * ครอบคลุม:
 *  - PSR-3 Log Levels    (emergency → debug)
 *  - Exception Logging   (exception, logError)
 *  - Security Logging    (security, loginAttempt, unauthorized, suspiciousActivity)
 *  - Audit Logging       (audit, logUserAccess)
 *  - Performance Logging (slowQuery, slowRequest)
 *  - Context & Channel   (withContext, channel)
 *  - Utility             (mask)
 */
interface AppLoggerInterface
{
    // ─── PSR-3 Log Levels ───────────────────────────────────────

    public function emergency(string $message, array $context = []): void;

    public function alert(string $message, array $context = []): void;

    public function critical(string $message, array $context = []): void;

    public function error(string $message, array $context = []): void;

    public function warning(string $message, array $context = []): void;

    public function notice(string $message, array $context = []): void;

    public function info(string $message, array $context = []): void;

    public function debug(string $message, array $context = []): void;

    // ─── Exception Logging ──────────────────────────────────────

    public function exception(Throwable $e, string $message = '', array $context = []): void;

    public function logError(Throwable $throwable, array $context = []): void;

    // ─── Security Logging ───────────────────────────────────────

    public function security(string $event, array $context = []): void;

    public function loginAttempt(string $identifier, bool $success, array $context = []): void;

    public function unauthorized(string $action = '', array $context = []): void;

    public function suspiciousActivity(string $description, array $context = []): void;

    // ─── Audit Logging ──────────────────────────────────────────

    public function audit(
        string $action,
        string $subject = '',
        array $changes = [],
        array $context = [],
    ): void;

    public function logUserAccess(string $text = '', array $context = []): void;

    // ─── Performance Logging ────────────────────────────────────

    public function slowQuery(string $sql, float $durationMs, array $context = []): void;

    public function slowRequest(
        string $url,
        string $method,
        float $durationMs,
        array $context = [],
    ): void;

    // ─── Context & Channel ──────────────────────────────────────

    public function withContext(array $context): static;

    public function channel(string $name): LoggerInterface;

    // ─── Utility ────────────────────────────────────────────────

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function mask(array $data): array;
}
