<?php

declare(strict_types=1);

require __DIR__ . '/includes/notes.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/notify.php';

send_common_headers();
require_method('POST');

const REASON_PRIORITY = [
    'spam' => 'low',
    'broken' => 'low',
    'inappropriate' => 'medium',
    'copyright' => 'medium',
    'other' => 'medium',
    'illegal' => 'high',
];

function load_config(): array {
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
        // Fail closed rather than silently skipping notifications forever.
        fail(500, 'Server is not configured');
    }
    return require $path;
}

function handle_report(): void {
    $config = load_config();
    $db = get_reports_db();

    $body = read_json_body(MAX_REPORT_BODY_BYTES);

    $noteId = require_valid_id($body['id'] ?? null);
    if (!note_exists($noteId)) {
        fail(404, 'Note not found');
    }

    $reason = $body['reason'] ?? null;
    if (!is_string($reason) || !array_key_exists($reason, REASON_PRIORITY)) {
        fail(400, 'Invalid reason');
    }
    $priority = REASON_PRIORITY[$reason];

    $message = $body['message'] ?? '';
    if (!is_string($message)) {
        fail(400, 'Invalid message');
    }
    // Strip control characters and cap length (defense in depth - the
    // read already caps the whole request body size).
    $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', trim($message)) ?? '';
    if (strlen($message) > MAX_REPORT_MESSAGE_BYTES) {
        $message = substr($message, 0, MAX_REPORT_MESSAGE_BYTES);
    }

    $ip = client_ip();
    $now = time();

    $hourAgo = $now - 3600;
    $stmt = $db->prepare('SELECT COUNT(*) FROM reports WHERE reporter_ip = ? AND created_at > ?');
    $stmt->execute([$ip, $hourAgo]);
    if ((int) $stmt->fetchColumn() >= (int) $config['max_reports_per_ip_per_hour']) {
        fail(429, 'Too many reports - please try again later');
    }

    $dayAgo = $now - 86400;
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM reports WHERE reporter_ip = ? AND note_id = ? AND created_at > ?'
    );
    $stmt->execute([$ip, $noteId, $dayAgo]);
    if ((int) $stmt->fetchColumn() >= (int) $config['max_reports_per_ip_per_note_per_day']) {
        fail(429, "You've already reported this note recently");
    }

    $stmt = $db->prepare(
        'INSERT INTO reports (note_id, reason, priority, message, reporter_ip, created_at)
         VALUES (:note_id, :reason, :priority, :message, :ip, :created_at)'
    );
    $stmt->execute([
        'note_id' => $noteId,
        'reason' => $reason,
        'priority' => $priority,
        'message' => $message,
        'ip' => $ip,
        'created_at' => $now,
    ]);

    if (notification_cooldown_allows($db, $config, $noteId, $priority)) {
        notify_admin($config, $noteId, $reason, $priority, $message, $ip);
    }

    header('Content-Type: application/json');
    echo json_encode(['reported' => true], JSON_THROW_ON_ERROR);
}

handle_report();
