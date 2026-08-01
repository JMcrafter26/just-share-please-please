<?php
declare(strict_types=1);

namespace TrilbyMedia\Cap;

/**
 * Deterministic PRNG that must be bit-exact with the upstream cap.js
 * implementation. Uses FNV-1a seeded with the challenge token to
 * regenerate salt/target pairs for each sub-challenge.
 *
 * JS uses 32-bit unsigned/int32 semantics. In PHP (64-bit) we emulate
 * that with explicit `& 0xFFFFFFFF` masks after every arithmetic op.
 */
final class Prng
{
    private const UINT32_MASK = 0xFFFFFFFF;
    private const FNV_OFFSET  = 0x811C9DC5; // 2166136261
    private const FNV_PRIME   = 0x01000193; // 16777619

    public static function generate(string $seed, int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $state  = self::fnv1a($seed);
        $result = '';

        while (strlen($result) < $length) {
            // xorshift32
            $state ^= ($state << 13) & self::UINT32_MASK;
            $state &= self::UINT32_MASK;
            $state ^= ($state >> 17) & self::UINT32_MASK;
            $state &= self::UINT32_MASK;
            $state ^= ($state << 5) & self::UINT32_MASK;
            $state &= self::UINT32_MASK;

            $result .= sprintf('%08x', $state);
        }

        return substr($result, 0, $length);
    }

    private static function fnv1a(string $data): int
    {
        $hash = self::FNV_OFFSET;
        $len  = strlen($data);

        for ($i = 0; $i < $len; $i++) {
            $hash ^= ord($data[$i]);
            $hash  = ($hash * self::FNV_PRIME) & self::UINT32_MASK;
        }

        return $hash;
    }
}
