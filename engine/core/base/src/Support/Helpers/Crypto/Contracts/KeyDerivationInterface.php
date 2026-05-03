<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Contracts;

/**
 * KeyDerivationInterface — สัญญาสำหรับ Key Derivation Function (KDF)
 *
 * แยกออกจาก HashHelperInterface เพราะ key derivation เป็นคนละ concern กับ hashing
 *
 * ครอบคลุม:
 *  - BLAKE2b KDF     (deriveKey)              — subkey จาก master key
 *  - Argon2id KDF    (deriveKeyFromPassword)  — key จาก password + salt
 *  - KDF Verify      (verifyDerivedKey, verifyDerivedKeyFromPassword)
 */
interface KeyDerivationInterface
{
    //
}
