<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Module\Contracts;

use Nwidart\Modules\Laravel\Module as ModuleInstance;

/**
 * ModuleHelperInterface — สัญญาสำหรับ Module Helper
 *
 * ครอบคลุม:
 *  - Discovery    (find, findOrFail, all, allEnabled, allDisabled, has, count, names, enabledNames)
 *  - Status       (isEnabled, isDisabled, enable, disable)
 *  - Path         (path, appPath, configPath, viewsPath, langPath, routesPath,
 *                  databasePath, assetPath, testsPath)
 *  - Context      (current, currentName, fromRoute, fromClass, fromNamespace, fromPath)
 *  - Info         (namespace, version, description, composerAttr)
 *  - Collection   (filter, map, each, only)
 *  - Validation   (assertExists, assertEnabled)
 *  - Utility      (flushCache)
 */
interface ModuleHelperInterface
{
    // ─── Discovery ──────────────────────────────────────────────

    public function find(string $name): ?ModuleInstance;

    public function findOrFail(string $name): ModuleInstance;

    /** @return array<string, ModuleInstance> */
    public function all(): array;

    /** @return array<string, ModuleInstance> */
    public function allEnabled(): array;

    /** @return array<string, ModuleInstance> */
    public function allDisabled(): array;

    public function has(string $name): bool;

    public function count(): int;

    /** @return string[] */
    public function names(): array;

    /** @return string[] */
    public function enabledNames(): array;

    // ─── Status ─────────────────────────────────────────────────

    public function isEnabled(string $name): bool;

    public function isDisabled(string $name): bool;

    public function enable(string $name): void;

    public function disable(string $name): void;

    // ─── Path Utilities ─────────────────────────────────────────

    public function path(string $name, string $subPath = ''): string;

    public function appPath(string $name, string $subPath = ''): string;

    public function configPath(string $name, string $subPath = ''): string;

    public function viewsPath(string $name, string $subPath = ''): string;

    public function langPath(string $name, string $subPath = ''): string;

    public function routesPath(string $name, string $subPath = ''): string;

    public function databasePath(string $name, string $subPath = ''): string;

    public function assetPath(string $name, string $subPath = ''): string;

    public function testsPath(string $name, string $subPath = ''): string;

    // ─── Context Detection ──────────────────────────────────────

    public function current(): ?ModuleInstance;

    public function currentName(): ?string;

    public function fromRoute(): ?ModuleInstance;

    public function fromClass(string $className): ?ModuleInstance;

    public function fromNamespace(string $namespace): ?ModuleInstance;

    public function fromPath(string $filePath): ?ModuleInstance;

    // ─── Module Info ────────────────────────────────────────────

    public function namespace(string $name): string;

    public function version(string $name): ?string;

    public function description(string $name): ?string;

    public function composerAttr(string $name, string $key, mixed $default = null): mixed;

    // ─── Collection Helpers ─────────────────────────────────────

    /** @return array<string, ModuleInstance> */
    public function filter(callable $callback): array;

    public function map(callable $callback): array;

    public function each(callable $callback): void;

    /** @param string[] $names @return array<string, ModuleInstance> */
    public function only(array $names): array;

    // ─── Validation ─────────────────────────────────────────────

    public function assertExists(string $name): void;

    public function assertEnabled(string $name): void;

    // ─── Utility ────────────────────────────────────────────────

    public function flushCache(): void;
}
