<?php

declare(strict_types=1);

require __DIR__ . '/includes/notes.php';

send_common_headers();

switch ($_SERVER['REQUEST_METHOD'] ?? '') {
    case 'OPTIONS':
        http_response_code(204);
        exit;
    case 'GET':
        handle_get();
        break;
    case 'POST':
        handle_post();
        break;
    case 'PATCH':
        handle_patch();
        break;
    case 'DELETE':
        handle_delete();
        break;
    default:
        header('Allow: GET, POST, PATCH, DELETE, OPTIONS');
        fail(405, 'Unsupported method');
}

function handle_get(): void {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
    $id = require_valid_id($query['id'] ?? null);

    if (!note_exists($id)) {
        fail(404, 'Not found');
    }

    $content = file_get_contents(markdown_path($id));
    if ($content === false) {
        fail(500, 'Internal Server Error');
    }

    // Always served as plain text so a note's own raw markdown can never be
    // interpreted as HTML by a browser (stored XSS). Rendering happens
    // client-side, through markdown-it into a DOMPurify-sanitized fragment.
    header('Content-Type: text/plain; charset=utf-8');
    echo $content;
}

function handle_post(): void {
    $content = require_note_content(read_json_body(MAX_CONTENT_BYTES));

    try {
        $id = generate_unique_id();
        $password = bin2hex(random_bytes(16));
    } catch (\Exception $e) {
        fail(500, 'Internal Server Error');
    }

    // The plaintext password is only ever transmitted once, right here.
    // Only a salted hash of it is persisted (see check_password()), so a
    // filesystem read of meta.json alone can't be used to hijack a note.
    $meta = json_encode([
        'id' => $id,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ], JSON_THROW_ON_ERROR);

    if (file_put_contents(markdown_path($id), $content) === false
        || file_put_contents(meta_path($id), $meta) === false) {
        fail(500, 'Internal Server Error');
    }

    header('Content-Type: application/json');
    echo json_encode(['id' => $id, 'password' => $password], JSON_THROW_ON_ERROR);
}

function handle_patch(): void {
    [$id, $password] = require_id_and_password();
    check_password($id, $password);

    $content = require_note_content(read_json_body(MAX_CONTENT_BYTES));
    if (file_put_contents(markdown_path($id), $content) === false) {
        fail(500, 'Internal Server Error');
    }
}

function handle_delete(): void {
    [$id, $password] = require_id_and_password();
    check_password($id, $password);

    $mdPath = markdown_path($id);
    $metaPath = meta_path($id);
    if (is_file($mdPath)) {
        unlink($mdPath);
    }
    if (is_file($metaPath)) {
        unlink($metaPath);
    }
}

function check_password(string $id, string $password): void {
    $path = meta_path($id);
    if (!is_file($path)) {
        fail(404, 'Not found');
    }

    $raw = file_get_contents($path);
    $meta = $raw === false ? null : json_decode($raw, true);

    $hash = is_array($meta) ? ($meta['password_hash'] ?? null) : null;
    if (!is_string($hash) || !password_verify($password, $hash)) {
        fail(401, 'Unauthorized');
    }
}

function require_id_and_password(): array {
    parse_str($_SERVER['QUERY_STRING'] ?? '', $query);
    $id = require_valid_id($query['id'] ?? null);

    $password = $_SERVER['HTTP_PASSWORD'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!is_string($password) || $password === '') {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strcasecmp((string)$k, 'Password') === 0 && is_string($v) && $v !== '') {
                    $password = $v;
                    break;
                }
            }
        }
    }

    if (!is_string($password) || $password === '') {
        fail(400, 'No valid id or password provided');
    }

    return [$id, $password];
}

function require_note_content(array $body): string {
    $content = $body['content'] ?? null;
    if (!is_string($content)) {
        fail(400, 'Invalid JSON or missing content');
    }
    if (strlen($content) > MAX_CONTENT_BYTES) {
        fail(413, 'Content exceeds maximum length');
    }
    return $content;
}

function generate_unique_id(): string {
    do {
        $id = bin2hex(random_bytes(16));
    } while (note_exists($id));
    return $id;
}
