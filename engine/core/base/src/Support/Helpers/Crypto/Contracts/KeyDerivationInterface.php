<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Contracts;

/**
 * KeyDerivationInterface — สัญญาสำหรับ Key Derivation (HKDF)
 *
 * แยกออกจาก HashHelperInterface เพราะ key derivation เป็นคนละ concern กับ hashing
 */
interface KeyDerivationInterface
{
    public function deriveKey(
        string $context = 'default',
        string $saltb64 = '',
        string $inputKeyMaterial = '',
    ): string;

    public function deriveKeyFromPassword(string $inputPassword, string $saltb64): string;

    public function verifyDerivedKey(string $providedDerivedKey, string $purpose, string $saltb64 = '', string $masterKey = ''): bool;

    public function verifyDerivedKeyFromPassword(string $inputPassword, string $storedSaltb64, string $storedKeyb64): bool;
}
