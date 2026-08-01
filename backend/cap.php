<?php
declare(strict_types=1);

require __DIR__ . '/includes/notes.php';
require __DIR__ . '/includes/capito/autoload.php';

use Capito\CapPhpServer\Cap;
use Capito\CapPhpServer\Storage\FileStorage;
use Capito\CapPhpServer\Exceptions\CapException;

send_common_headers();

ensure_data_dir();

$storage = new FileStorage(['path' => DATA_DIR . '/cap_storage.json']);
$capServer = new Cap([
    'challengeCount' => 2,
    'challengeSize' => 16,
    'challengeDifficulty' => 2,
    'tokenVerifyOnce' => true,
    'challengeExpires' => 300,
    'tokenExpires' => 600,
    'bruteForcePenalty' => 0, // Rate limiting handled at app layer if needed
    'storage' => $storage
]);

header('Content-Type: application/json');

$route = $_GET['route'] ?? '';
$clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

switch ($_SERVER['REQUEST_METHOD'] ?? '') {
    case 'OPTIONS':
        http_response_code(200);
        exit;

    case 'POST':
        if ($route === 'challenge') {
            try {
                $challenge = $capServer->createChallenge($clientIP);
                echo json_encode($challenge);
            } catch (CapException $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        if ($route === 'redeem') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
                exit;
            }

            try {
                $result = $capServer->redeemChallenge($input, $clientIP);

                if (!empty($result['success'])) {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['cap_verified'] = true;
                    $_SESSION['cap_verified_at'] = time();
                }

                echo json_encode($result);
            } catch (CapException $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Server error']);
            }
            exit;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        exit;

    default:
        http_response_code(405);
        header('Allow: POST, OPTIONS');
        echo json_encode(['error' => 'Method Not Allowed']);
        exit;
}
