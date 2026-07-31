<?php

declare(strict_types=1);

/**
 * Returns true (and records the send) if a notification is allowed right
 * now under both the per-note-per-priority cooldown and the global
 * cross-note cooldown; false if it should be suppressed. The report itself
 * is always stored in the DB regardless of what this returns - this only
 * gates the email/Discord ping so a burst of reports can't spam the admin.
 */
function notification_cooldown_allows(PDO $db, array $config, string $noteId, string $priority): bool {
    $now = time();

    $perNoteCooldown = $config['cooldown_per_note'][$priority] ?? 3600;
    $globalCooldown = (int) ($config['cooldown_global'] ?? 15);

    $noteKey = "note:{$noteId}:{$priority}";
    $globalKey = 'global';

    $lastForNote = get_cooldown_timestamp($db, $noteKey);
    if ($lastForNote !== null && $now - $lastForNote < $perNoteCooldown) {
        return false;
    }

    $lastGlobal = get_cooldown_timestamp($db, $globalKey);
    if ($lastGlobal !== null && $now - $lastGlobal < $globalCooldown) {
        return false;
    }

    set_cooldown_timestamp($db, $noteKey, $now);
    set_cooldown_timestamp($db, $globalKey, $now);
    return true;
}

function get_cooldown_timestamp(PDO $db, string $key): ?int {
    $stmt = $db->prepare('SELECT last_sent_at FROM notification_cooldowns WHERE cooldown_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (int) $value;
}

function set_cooldown_timestamp(PDO $db, string $key, int $timestamp): void {
    $stmt = $db->prepare(
        'INSERT INTO notification_cooldowns (cooldown_key, last_sent_at) VALUES (:key, :ts)
         ON CONFLICT(cooldown_key) DO UPDATE SET last_sent_at = :ts'
    );
    $stmt->execute(['key' => $key, 'ts' => $timestamp]);
}

/**
 * Sends the actual notification. Failures are logged and swallowed - a
 * broken mail server or webhook must never fail the report submission
 * itself, since the report is already safely stored in the database.
 */
function notify_admin(array $config, string $noteId, string $reason, string $priority, string $message, string $reporterIp = ''): void {
    $baseUrl = rtrim((string) ($config['site_url'] ?? ''), '/');
    $noteLink = $baseUrl . '/#' . $noteId;

    $token = generate_admin_delete_token($config, $noteId);
    $deleteLink = $baseUrl . '/delete?id=' . urlencode($noteId) . '&token=' . urlencode($token);

    $dateTime = date('Y-m-d H:i:s T');

    $detailsBlock = $message !== '' ? "\nDetails / Message:\n" . $message . "\n" : '';

    $summary = sprintf(
        "A note was reported on Just Share Please.\n\n" .
        "Report Details:\n" .
        "----------------------------------------\n" .
        "Date & Time: %s\n" .
        "Note ID:     %s\n" .
        "Priority:    %s\n" .
        "Reason:      %s\n" .
        "Reporter IP: %s\n" .
        "%s\n" .
        "Action Links:\n" .
        "----------------------------------------\n" .
        "View Note:   %s\n" .
        "Delete Note: %s\n",
        $dateTime,
        $noteId,
        strtoupper($priority),
        $reason,
        $reporterIp !== '' ? $reporterIp : 'Unknown',
        $detailsBlock,
        $noteLink,
        $deleteLink
    );

    try {
        send_admin_email($config, "[Just Share Please] Note reported ({$priority}): {$reason}", $summary);
    } catch (\Throwable $e) {
        error_log('notify_admin: email failed: ' . $e->getMessage());
    }

    try {
        send_discord_webhook($config, $summary);
    } catch (\Throwable $e) {
        error_log('notify_admin: discord webhook failed: ' . $e->getMessage());
    }
}

function send_admin_email(array $config, string $subject, string $body): void {
    $to = $config['admin_email'] ?? '';
    if ($to === '') {
        return;
    }
    $from = $config['mail_from'] ?? $to;
    $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8";
    mail($to, $subject, $body, $headers);
}

function send_discord_webhook(array $config, string $content): void {
    $url = $config['discord_webhook_url'] ?? '';
    if ($url === '') {
        return;
    }

    // Discord messages are capped at 2000 characters.
    if (strlen($content) > 1900) {
        $content = substr($content, 0, 1900) . '…';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['content' => $content], JSON_THROW_ON_ERROR),
    ]);
    curl_exec($ch);
    curl_close($ch);
}
