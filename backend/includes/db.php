<?php

declare(strict_types=1);

function get_reports_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0770, true);
    }

    $pdo = new PDO('sqlite:' . DATA_DIR . '/reports.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode = WAL');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            note_id TEXT NOT NULL,
            reason TEXT NOT NULL,
            priority TEXT NOT NULL,
            message TEXT NOT NULL DEFAULT \'\',
            reporter_ip TEXT NOT NULL,
            created_at INTEGER NOT NULL
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_ip ON reports (reporter_ip, created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_note ON reports (note_id, created_at)');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notification_cooldowns (
            cooldown_key TEXT PRIMARY KEY,
            last_sent_at INTEGER NOT NULL
        )'
    );

    return $pdo;
}
