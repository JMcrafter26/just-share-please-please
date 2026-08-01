<?php
declare(strict_types=1);

namespace TrilbyMedia\Cap\Storage;

/**
 * File-backed storage for Cap challenges and tokens.
 * Each type gets its own JSON file in the given directory.
 * Suitable for small/single-server deployments.
 */
final class FilesystemStorage implements ChallengeStorageInterface, TokenStorageInterface
{
    private string $challengesFile;
    private string $tokensFile;

    public function __construct(string $directory)
    {
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create Cap storage directory: {$directory}");
        }
        $this->challengesFile = rtrim($directory, '/\\') . '/challenges.json';
        $this->tokensFile     = rtrim($directory, '/\\') . '/tokens.json';
    }

    // ---- Challenge storage ----

    public function storeChallenge(string $token, array $challenge): void
    {
        $all = $this->read($this->challengesFile);
        $all[$token] = $challenge;
        $this->write($this->challengesFile, $all);
    }

    public function getChallenge(string $token): ?array
    {
        $all = $this->read($this->challengesFile);
        return $all[$token] ?? null;
    }

    public function deleteChallenge(string $token): void
    {
        $all = $this->read($this->challengesFile);
        unset($all[$token]);
        $this->write($this->challengesFile, $all);
    }

    public function cleanupExpiredChallenges(int $nowMs): void
    {
        $all = $this->read($this->challengesFile);
        $filtered = array_filter($all, fn($c) => ($c['expires'] ?? 0) > $nowMs);
        $this->write($this->challengesFile, $filtered);
    }

    // ---- Token storage ----

    public function storeToken(string $key, int $expiresMs): void
    {
        $all = $this->read($this->tokensFile);
        $all[$key] = $expiresMs;
        $this->write($this->tokensFile, $all);
    }

    public function consumeToken(string $key, int $nowMs): bool
    {
        $all = $this->read($this->tokensFile);
        if (!isset($all[$key])) {
            return false;
        }
        $expires = (int) $all[$key];
        unset($all[$key]);
        $this->write($this->tokensFile, $all);
        return $expires > $nowMs;
    }

    public function cleanupExpiredTokens(int $nowMs): void
    {
        $all = $this->read($this->tokensFile);
        $filtered = array_filter($all, fn($exp) => (int) $exp > $nowMs);
        $this->write($this->tokensFile, $filtered);
    }

    // ---- Helpers ----

    private function read(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function write(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
