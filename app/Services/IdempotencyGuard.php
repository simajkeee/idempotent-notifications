<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\IdempotencyKeyException;

class IdempotencyGuard
{
    /**
     * @param string $storedHash
     * @param string $incomingHash
     * @return void
     *
     * @throws IdempotencyKeyException
     */
    public function assertMatchingHash(
        string $storedHash,
        string $incomingHash,
    ): void {
        if (! hash_equals($storedHash, $incomingHash)) {
            throw new IdempotencyKeyException;
        }
    }
}
