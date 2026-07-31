<?php

declare(strict_types=1);

require __DIR__ . '/includes/notes.php';

send_common_headers();

function load_config(): array {
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
        fail(500, 'Server is not configured');
    }
    return require $path;
}

$config = load_config();

$id = $_REQUEST['id'] ?? null;
$token = $_REQUEST['token'] ?? null;

function render_page(string $title, string $contentHtml): void {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - Just Share Please Admin</title>
    <style>
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #1e1e1e;
            color: #dadada;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
        }
        .card {
            background: #2a2a2a;
            border-radius: 12px;
            padding: 32px;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
            box-sizing: border-box;
        }
        h2 {
            margin-top: 0;
            color: #ffffff;
            font-size: 1.5rem;
        }
        p {
            line-height: 1.6;
            color: #b0b0b0;
            margin: 16px 0;
        }
        code {
            font-family: "JetBrains Mono", monospace;
            background: #1e1e1e;
            padding: 2px 6px;
            border-radius: 4px;
            color: #a48cf2;
        }
        .actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn {
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 500;
            padding: 10px 18px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: #ffffff;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .btn-secondary {
            background-color: #444444;
            color: #ffffff;
        }
        .btn-secondary:hover {
            background-color: #555555;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            background: rgba(146, 117, 240, 0.2);
            color: #a48cf2;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="card">
        {$contentHtml}
    </div>
</body>
</html>
HTML;
    exit;
}

if (!is_string($id) || !preg_match(ID_PATTERN, $id)) {
    http_response_code(400);
    render_page('Invalid Request', '<h2>Invalid Note ID</h2><p>The provided note ID is missing or invalid.</p><a href="./" class="btn btn-secondary">Go to Home</a>');
}

if (!verify_admin_delete_token($config, $id, $token)) {
    http_response_code(403);
    render_page('Access Denied', '<h2>Access Denied</h2><p>Invalid or expired admin token provided.</p><a href="./" class="btn btn-secondary">Go to Home</a>');
}

if (!note_exists($id)) {
    render_page('Note Not Found', '<h2>Note Not Found</h2><p>The note <code>' . htmlspecialchars($id) . '</code> does not exist or has already been deleted.</p><a href="./" class="btn btn-secondary">Go to Home</a>');
}

$safeId = htmlspecialchars($id);
$safeToken = htmlspecialchars((string) $token);
$siteUrl = rtrim((string) ($config['site_url'] ?? './'), '/');
$noteUrl = $siteUrl . '/#' . $safeId;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
    $mdPath = markdown_path($id);
    $metaPath = meta_path($id);

    if (is_file($mdPath)) {
        @unlink($mdPath);
    }
    if (is_file($metaPath)) {
        @unlink($metaPath);
    }

    render_page('Note Deleted', '
        <div class="badge">Admin Action</div>
        <h2>Note Deleted Successfully</h2>
        <p>Note <code>' . $safeId . '</code> and its metadata have been permanently removed from the server.</p>
        <div class="actions">
            <a href="./" class="btn btn-secondary">Return to Home</a>
        </div>
    ');
}

// Render Confirmation Form (GET)
render_page('Confirm Note Deletion', '
    <div class="badge">Admin Confirmation</div>
    <h2>Delete Note?</h2>
    <p>Are you sure you want to permanently delete note <code>' . $safeId . '</code>?</p>
    <p>This action will immediately remove the file and metadata from the server and cannot be undone.</p>
    <form method="POST" action="delete">
        <input type="hidden" name="id" value="' . $safeId . '">
        <input type="hidden" name="token" value="' . $safeToken . '">
        <input type="hidden" name="confirm" value="1">
        <div class="actions">
            <button type="submit" class="btn btn-danger">Confirm Delete</button>
            <a href="' . htmlspecialchars($noteUrl) . '" target="_blank" class="btn btn-secondary">View Note First</a>
        </div>
    </form>
');
