<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function api_input(): array
{
    $body = json_decode(file_get_contents('php://input'), true);
    return is_array($body) ? $body : $_POST;
}

function api_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_secret(): string
{
    $secret = getenv('AKADIMI_API_SECRET');
    if (!$secret) {
        api_response(['error' => 'API secret is not configured on the server.'], 500);
    }
    return $secret;
}

function issue_token(string $role, int $id, ?string $deviceHash = null): string
{
    $payload = ['role' => $role, 'id' => $id, 'exp' => time() + 86400];
    if ($deviceHash !== null) {
        $payload['device_hash'] = $deviceHash;
    }
    $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $signature = hash_hmac('sha256', $encoded, api_secret());
    return $encoded . '.' . $signature;
}

function authenticated(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
        api_response(['error' => 'Authentication required.'], 401);
    }

    [$encoded, $signature] = array_pad(explode('.', $matches[1], 2), 2, '');
    $expected = hash_hmac('sha256', $encoded, api_secret());
    if (!$encoded || !$signature || !hash_equals($expected, $signature)) {
        api_response(['error' => 'Invalid authentication token.'], 401);
    }

    $payload = json_decode(base64_decode(strtr($encoded, '-_', '+/')), true);
    if (!is_array($payload) || empty($payload['id']) || empty($payload['role']) || ($payload['exp'] ?? 0) < time()) {
        api_response(['error' => 'Expired authentication token.'], 401);
    }

    if ($payload['role'] === 'student') {
        $deviceHash = $payload['device_hash'] ?? '';
        if (!is_string($deviceHash) || !preg_match('/^[a-f0-9]{64}$/', $deviceHash)) {
            api_response(['error' => 'Student device session is invalid.'], 401);
        }

        $stmt = db()->prepare('SELECT active, device_token FROM students WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$payload['id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentDeviceToken = (string)($student['device_token'] ?? '');

        if (
            !$student ||
            (int)$student['active'] !== 1 ||
            $currentDeviceToken === '' ||
            !hash_equals(hash('sha256', $currentDeviceToken), $deviceHash)
        ) {
            api_response(['error' => 'تم إلغاء ربط هذا الجهاز. تواصل مع إدارة المنصة.'], 401);
        }
    }

    return $payload;
}