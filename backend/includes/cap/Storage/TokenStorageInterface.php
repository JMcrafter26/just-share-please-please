<?php
declare(strict_types=1);

namespace TrilbyMedia\Cap\Storage;

interface TokenStorageInterface
{
    /** Store a token key with its expiry timestamp (ms). */
    public function storeToken(string $key, int $expiresMs): void;

    /** Consume a token — returns true if it existed and was not expired, false otherwise. Deletes on read. */
    public function consumeToken(string $key, int $nowMs): bool;

    public function cleanupExpiredTokens(int $nowMs): void;
}
