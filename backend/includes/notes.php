<?php

declare(strict_types=1);

// data/ lives at the webroot, one level up from includes/, and MUST stay
// unreachable over HTTP - see data/.htaccess. Using __DIR__ instead of the
// old getcwd() so the path doesn't depend on how/where the PHP SAPI was
// invoked from.
define('DATA_DIR', dirname(__DIR__) . '/data');

// bin2hex(random_bytes(16)) is always exactly 32 lowercase hex characters -
// anything else is rejected outright, before it ever touches the filesystem.
const ID_PATTERN = '/^[a-f0-9]{32}$/';

const MAX_CONTENT_BYTES = 262144; // 256 KiB
const MAX_REPORT_MESSAGE_BYTES = 1000;
const MAX_REPORT_BODY_BYTES = 8192; // headroom above the message cap for id/reason/JSON overhead

function send_common_headers(): void {
    // Defense in depth: nothing this API returns should ever be sniffed
    // into being rendered as HTML by an old/misbehaving browser.
    header('X-Content-Type-Options: nosniff');
}

function fail(int $status, string $message): never {
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function require_method(string ...$allowed): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        fail(405, 'Unsupported method');
    }
}

/**
 * Validates a note id from user input. Returns the id, or fails the
 * request with a 400 response.
 */
function require_valid_id(mixed $id): string {
    if (!is_string($id) || !preg_match(ID_PATTERN, $id)) {
        fail(400, 'Invalid or missing ID');
    }
    return $id;
}

/**
 * $id is validated against ID_PATTERN by require_valid_id() before it
 * reaches here everywhere it's used; basename() is kept as defense in
 * depth in case that ever changes.
 */
function markdown_path(string $id): string {
    return DATA_DIR . '/' . basename($id) . '.md';
}

function meta_path(string $id): string {
    return DATA_DIR . '/' . basename($id) . '.json';
}

function note_exists(string $id): bool {
    return is_file(markdown_path($id));
}

/**
 * Reads the raw JSON request body, enforcing a hard byte cap on the read
 * itself (not just on the decoded value) so a caller can't exhaust memory
 * by sending a huge body regardless of what Content-Length claims.
 */
function read_json_body(int $maxBytes): array {
    $declared = $_SERVER['CONTENT_LENGTH'] ?? null;
    if ($declared !== null && (int) $declared > $maxBytes + 4096) {
        fail(413, 'Content exceeds maximum length');
    }

    $stream = fopen('php://input', 'r');
    if ($stream === false) {
        fail(500, 'Internal Server Error');
    }
    $raw = stream_get_contents($stream, $maxBytes + 4096 + 1);
    fclose($stream);

    if ($raw === false || strlen($raw) > $maxBytes + 4096) {
        fail(413, 'Content exceeds maximum length');
    }

    $body = json_decode($raw, true);
    if (!is_array($body)) {
        fail(400, 'Invalid JSON or missing content');
    }
    return $body;
}

function client_ip(): string {
    // Deliberately NOT trusting X-Forwarded-For unless you configure your
    // reverse proxy to strip/overwrite it - trusting it blindly lets
    // anyone spoof their rate-limit identity via a request header.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
