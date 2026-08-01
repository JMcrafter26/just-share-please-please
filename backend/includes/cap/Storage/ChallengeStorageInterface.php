<?php
declare(strict_types=1);

namespace TrilbyMedia\Cap\Storage;

interface ChallengeStorageInterface
{
    /** @param array{c:int,s:int,d:int,expires:int} $challenge */
    public function storeChallenge(string $token, array $challenge): void;

    /** @return array{c:int,s:int,d:int,expires:int}|null */
    public function getChallenge(string $token): ?array;

    public function deleteChallenge(string $token): void;

    public function cleanupExpiredChallenges(int $nowMs): void;
}
