<?php

declare(strict_types=1);

// Copy this file to config.php (next to it) and fill in your own values.
// config.php is gitignored - never commit real webhook URLs or addresses.
return [
    'admin_email' => 'admin@example.com',
    'mail_from' => 'no-reply@example.com',

    // Leave empty to disable Discord notifications.
    'discord_webhook_url' => '',

    // Used to build a clickable link to the reported note in notifications.
    'site_url' => 'https://example.com',

    // How long to wait before sending ANOTHER notification about the SAME
    // note, per priority tier (seconds). Reports are always recorded in
    // the database regardless of this cooldown - it only throttles pings.
    'cooldown_per_note' => [
        'high' => 60,        // 1 minute
        'medium' => 900,     // 15 minutes
        'low' => 3600,       // 1 hour
    ],

    // Minimum gap between ANY two notifications, to smooth out bursts
    // across many different notes reported at once (seconds).
    'cooldown_global' => 15,

    // Abuse limits on the report endpoint itself, independent of whether
    // a notification actually gets sent.
    'max_reports_per_ip_per_hour' => 10,
    'max_reports_per_ip_per_note_per_day' => 1,
];
