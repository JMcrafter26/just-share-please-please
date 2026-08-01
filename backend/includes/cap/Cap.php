<?php
declare(strict_types=1);

namespace TrilbyMedia\Cap;

/**
 * Cap proof-of-work captcha server — self-contained PHP port.
 * Wire-compatible with the official @cap.js/widget.
 *
 * Exposes two endpoints:
 *   POST /cap/challenge  → createChallenge() JSON response
 *   POST /cap/redeem     → redeemChallenge($token, $solutions) JSON response
 *
 * Token validation is done server-side via validateToken().
 */
final class Cap
{
    private const CLEANUP_INTERVAL_MS = 300_000; // 5 min
    private int $lastCleanupMs = 0;

    public function __construct(private readonly Config $config) {}

    /**
     * Generate a new challenge for the widget.
     *
     * @return array{challenge: array{c:int,s:int,d:int}, token: string, expires: int}
     */
    public function createChallenge(): array
    {
        $this->lazyCleanup();

        $challenge = [
            'c' => $this->config->challengeCount,
            's' => $this->config->challengeSize,
            'd' => $this->config->challengeDifficulty,
        ];
        $expiresMs = $this->config->expiresMs;
        $expires   = $this->nowMs() + $expiresMs;
        $token     = $this->randomHex(25);

        $this->config->challengeStorage->storeChallenge($token, $challenge + ['expires' => $expires]);

        return ['challenge' => $challenge, 'token' => $token, 'expires' => $expires];
    }

    /**
     * Validate a widget's redeem request.
     *
     * @param  string   $challengeToken  The token returned by createChallenge()
     * @param  array    $solutions       Array of hex-string solutions
     * @return array{success: bool, token?: string, expires?: int, error?: string}
     */
    public function redeemChallenge(string $challengeToken, array $solutions): array
    {
        $this->lazyCleanup();
        $nowMs = $this->nowMs();

        $challenge = $this->config->challengeStorage->getChallenge($challengeToken);
        if ($challenge === null) {
            return ['success' => false, 'error' => 'challenge_not_found'];
        }
        if (($challenge['expires'] ?? 0) <= $nowMs) {
            $this->config->challengeStorage->deleteChallenge($challengeToken);
            return ['success' => false, 'error' => 'challenge_expired'];
        }

        $count      = (int) ($challenge['c'] ?? 1);
        $size       = (int) ($challenge['s'] ?? 32);
        $difficulty = (int) ($challenge['d'] ?? 4);

        if (count($solutions) !== $count) {
            return ['success' => false, 'error' => 'invalid_solutions_count'];
        }

        // Verify each sub-challenge solution
        for ($i = 0; $i < $count; $i++) {
            $salt   = Prng::generate($challengeToken . $i, $size);
            $target = Prng::generate($challengeToken . $i . 'target', $difficulty);
            $sol    = $solutions[$i] ?? null;

            if (is_array($sol)) {
                $sol = $sol['nonce'] ?? $sol[0] ?? null;
            }

            if (is_int($sol) || is_float($sol)) {
                $sol = (string) $sol;
            }

            if (!is_string($sol) || $sol === '') {
                return ['success' => false, 'error' => 'invalid_solution'];
            }

            $hash = hash('sha256', $salt . $sol);
            if (!str_starts_with($hash, $target)) {
                return ['success' => false, 'error' => 'wrong_solution'];
            }
        }

        // Challenge is consumed
        $this->config->challengeStorage->deleteChallenge($challengeToken);

        // Issue a verification token
        $verToken  = $this->randomHex(32);
        $tokenKey  = $this->deriveTokenKey($verToken);
        $expiresMs = $nowMs + $this->config->expiresMs;

        $this->config->tokenStorage->storeToken($tokenKey, $expiresMs);

        return ['success' => true, 'token' => $verToken, 'expires' => $expiresMs];
    }

    /**
     * Validate and consume a verification token previously returned by redeemChallenge().
     * Single-use: deletes the token on first successful validation.
     */
    public function validateToken(string $verToken): bool
    {
        $key = $this->deriveTokenKey($verToken);
        return $this->config->tokenStorage->consumeToken($key, $this->nowMs());
    }

    // ---- Private helpers ----

    private function deriveTokenKey(string $verToken): string
    {
        return hash('sha256', $verToken);
    }

    private function randomHex(int $bytes): string
    {
        return bin2hex(random_bytes($bytes));
    }

    private function nowMs(): int
    {
        return (int) (microtime(true) * 1000);
    }

    private function lazyCleanup(): void
    {
        $now = $this->nowMs();
        if ($now - $this->lastCleanupMs < self::CLEANUP_INTERVAL_MS) {
            return;
        }
        $this->lastCleanupMs = $now;
        $this->config->challengeStorage->cleanupExpiredChallenges($now);
        $this->config->tokenStorage->cleanupExpiredTokens($now);
    }
}
