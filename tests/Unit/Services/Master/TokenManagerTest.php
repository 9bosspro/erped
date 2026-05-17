<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Master;

use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Slave\Contracts\Master\TokenFlow;
use Slave\Services\Master\TokenManager;

function createTestTokenManager(): TokenManager
{
    return new TokenManager(
        masterUrl: 'https://master.example.com',
        clientId: 'test-client',
        clientSecret: 'test-secret',
        sodium: app(SodiumHelper::class),
        signatureSeed: 'test-seed',
        publicBox: 'test-box',
        tokenStoreName: null, // use default cache (array in tests)
    );
}

describe('TokenManager', function () {
    describe('caching behavior', function () {
        it('returns access_token from rich array payload in cache', function () {
            $tokenManager = createTestTokenManager();

            $payload = [
                'access_token' => 'cached-token-123',
                'token_type' => 'Bearer',
                'expires_at' => now()->addHours(1)->toIso8601String(),
                'refresh_token' => 'refresh-123',
            ];

            $cacheKey = TokenFlow::OAuth->cachePrefix().':test-client:'.md5('default');
            Cache::put($cacheKey, $payload, 3600);

            $token = $tokenManager->getToken(TokenFlow::OAuth, 'default');

            expect($token)->toBe('cached-token-123');
        });

        it('returns null when cache is empty and no token can be fetched', function () {
            $tokenManager = createTestTokenManager();

            Cache::flush();

            $cachedData = $tokenManager->getTokenFromTokensStore(TokenFlow::OAuth, 'nonexistent');

            expect($cachedData)->toBeNull();
        });
    });

    describe('getRefreshToken', function () {
        it('returns refresh_token when present in cached payload', function () {
            $tokenManager = createTestTokenManager();

            $payload = [
                'access_token' => 'token-123',
                'refresh_token' => 'refresh-456',
                'expires_at' => now()->addHours(1)->toIso8601String(),
            ];

            $cacheKey = TokenFlow::OAuth->cachePrefix().':test-client:'.md5('test-scope');
            Cache::put($cacheKey, $payload, 3600);

            $refreshToken = $tokenManager->getRefreshToken(TokenFlow::OAuth, 'test-scope');

            expect($refreshToken)->toBe('refresh-456');
        });

        it('returns null when no cached data', function () {
            $tokenManager = createTestTokenManager();

            Cache::flush();

            $refreshToken = $tokenManager->getRefreshToken(TokenFlow::OAuth, 'missing');

            expect($refreshToken)->toBeNull();
        });
    });

    describe('store', function () {
        it('saves payload and returns access_token', function () {
            $tokenManager = createTestTokenManager();

            Cache::flush();

            $data = [
                'access_token' => 'stored-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'refresh_token' => 'refresh-token',
            ];

            $token = $tokenManager->store($data, TokenFlow::OAuth, 'scope');

            expect($token)->toBe('stored-token');
        });

        it('throws RuntimeException when access_token missing', function () {
            $tokenManager = createTestTokenManager();

            $data = [
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ];

            expect(fn () => $tokenManager->store($data, TokenFlow::OAuth, 'scope'))
                ->toThrow(RuntimeException::class);
        });

        it('uses DEFAULT_EXPIRES_IN when expires_in not provided', function () {
            $tokenManager = createTestTokenManager();

            Cache::flush();

            $data = [
                'access_token' => 'token-xyz',
                'token_type' => 'Bearer',
            ];

            $token = $tokenManager->store($data, TokenFlow::OAuth, 'scope');

            expect($token)->toBe('token-xyz');
        });
    });

    describe('expiry checking', function () {
        it('returns true when token is expired', function () {
            $tokenManager = createTestTokenManager();

            $payload = [
                'access_token' => 'token-123',
                'expires_at' => now()->subHours(1)->toIso8601String(),
            ];

            $cacheKey = TokenFlow::OAuth->cachePrefix().':test-client:'.md5('expired');
            Cache::put($cacheKey, $payload, 3600);

            $isExpired = $tokenManager->isExpired(TokenFlow::OAuth, 'expired');

            expect($isExpired)->toBeTrue();
        });

        it('returns false when token is still valid', function () {
            $tokenManager = createTestTokenManager();

            $payload = [
                'access_token' => 'token-123',
                'expires_at' => now()->addHours(2)->toIso8601String(),
            ];

            $cacheKey = TokenFlow::OAuth->cachePrefix().':test-client:'.md5('valid');
            Cache::put($cacheKey, $payload, 3600);

            $isExpired = $tokenManager->isExpired(TokenFlow::OAuth, 'valid');

            expect($isExpired)->toBeFalse();
        });

        it('returns true when no cached data', function () {
            $tokenManager = createTestTokenManager();

            Cache::flush();

            $isExpired = $tokenManager->isExpired(TokenFlow::OAuth, 'missing');

            expect($isExpired)->toBeTrue();
        });
    });

    describe('fluent builders return new instances', function () {
        it('withCacheSuffix returns new instance', function () {
            $tokenManager = createTestTokenManager();
            $cloned = $tokenManager->withCacheSuffix('user-123');

            expect($tokenManager)->not->toBe($cloned);
            expect($cloned)->toBeInstanceOf(TokenManager::class);
        });

        it('withTokenStore returns new instance', function () {
            $tokenManager = createTestTokenManager();
            $cloned = $tokenManager->withTokenStore('redis');

            expect($tokenManager)->not->toBe($cloned);
            expect($cloned)->toBeInstanceOf(TokenManager::class);
        });

        it('withCredentials returns new instance', function () {
            $tokenManager = createTestTokenManager();
            $cloned = $tokenManager->withCredentials('new-id', 'new-secret');

            expect($tokenManager)->not->toBe($cloned);
            expect($cloned)->toBeInstanceOf(TokenManager::class);
        });

        it('withUserPassword returns new instance', function () {
            $tokenManager = createTestTokenManager();
            $cloned = $tokenManager->withUserPassword('user@test.com', 'password');

            expect($tokenManager)->not->toBe($cloned);
            expect($cloned)->toBeInstanceOf(TokenManager::class);
        });
    });

    describe('manifest management', function () {
        it('getManifestKeys returns all tracked keys', function () {
            $tokenManager = createTestTokenManager();

            Cache::flush();

            $tokenManager->store(['access_token' => 'token1'], TokenFlow::OAuth, 'scope1');
            $tokenManager->store(['access_token' => 'token2'], TokenFlow::Jwt, 'scope2');

            $keys = $tokenManager->getManifestKeys();

            expect($keys)->toHaveCount(2);
        });

        it('clear removes specific token', function () {
            $tokenManager = createTestTokenManager();

            Cache::flush();

            $tokenManager->store(['access_token' => 'token1'], TokenFlow::OAuth, 'scope1');
            $tokenManager->store(['access_token' => 'token2'], TokenFlow::OAuth, 'scope2');

            $tokenManager->clear(TokenFlow::OAuth, 'scope1');

            $keys = $tokenManager->getManifestKeys();

            expect($keys)->toHaveCount(1);
        });

        it('clearAll removes all tokens', function () {
            $tokenManager = createTestTokenManager();

            Cache::flush();

            $tokenManager->store(['access_token' => 'token1'], TokenFlow::OAuth, 'scope1');
            $tokenManager->store(['access_token' => 'token2'], TokenFlow::Jwt, 'scope2');

            $tokenManager->clearAll();

            $keys = $tokenManager->getManifestKeys();

            expect($keys)->toHaveCount(0);
        });

        it('clearByKey removes specific key', function () {
            $tokenManager = createTestTokenManager();

            Cache::flush();

            $cacheKey = 'master_token:'.md5(TokenFlow::OAuth->cachePrefix().':test');
            Cache::put($cacheKey, ['access_token' => 'token'], 3600);

            $tokenManager->clearByKey($cacheKey);

            $cached = Cache::get($cacheKey);

            expect($cached)->toBeNull();
        });
    });

    describe('debugAllTokens', function () {
        it('returns all stored tokens', function () {
            $tokenManager = createTestTokenManager();

            Cache::flush();

            $tokenManager->store(['access_token' => 'token1'], TokenFlow::OAuth, 'scope1');
            $tokenManager->store(['access_token' => 'token2'], TokenFlow::Jwt, 'scope2');

            $debug = $tokenManager->debugAllTokens();

            expect($debug)->not->toBeEmpty();
            expect(count($debug))->toBe(2);
        });
    });

    describe('storage retrieval without expiry check', function () {
        it('getTokenFromTokensStore returns expired token without checking expiry', function () {
            $tokenManager = createTestTokenManager();

            $payload = [
                'access_token' => 'expired-token',
                'expires_at' => now()->subHours(1)->toIso8601String(),
            ];

            $cacheKey = TokenFlow::OAuth->cachePrefix().':test-client:'.md5('scope');
            Cache::put($cacheKey, $payload, 3600);

            $result = $tokenManager->getTokenFromTokensStore(TokenFlow::OAuth, 'scope');

            expect($result['access_token'])->toBe('expired-token');
        });
    });
});
