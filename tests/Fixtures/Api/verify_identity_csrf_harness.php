<?php

ob_start();

register_shutdown_function(static function (): void {
    $body = ob_get_clean();
    $decodedBody = json_decode($body, true);

    echo json_encode([
        'status' => http_response_code(),
        'body' => is_array($decodedBody) ? $decodedBody : $body,
    ], JSON_UNESCAPED_UNICODE);
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [
    'admin_logged_in' => true,
    'csrf_token' => 'known-token',
];

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

$_POST = [
    'type' => 'nik',
    'value' => '3101011501100001',
];

if (($argv[1] ?? 'missing') === 'invalid') {
    $_POST['csrf_token'] = 'wrong-token';
}

require __DIR__ . '/../../../api/verify_identity.php';
