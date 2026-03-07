<?php

$mode = $argv[1] ?? 'html';
$server = [];

switch ($mode) {
    case 'xrw':
        $server = ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        break;
    case 'accept':
        $server = ['HTTP_ACCEPT' => 'text/html,application/json;q=0.9'];
        break;
    case 'html':
    default:
        $server = ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml,*/*'];
        break;
}

http_response_code(200);
ob_start();

register_shutdown_function(static function (): void {
    $output = ob_get_clean();
    $decoded = json_decode($output, true);

    echo json_encode([
        'status' => http_response_code(),
        'body' => $decoded ?? $output,
    ]);
});

require_once __DIR__ . '/../../../admin/includes/auth_guard.php';

adminRequireAjaxJsonRequest($server);
