<?php
/**
 * API Auth Guard
 *
 * For shared API endpoints callable by any authenticated user
 * (admin, superadmin, pelatih, etc.).
 *
 * Requires only that a valid session exists (admin_logged_in = true).
 * Does NOT check admin_role; use admin/includes/auth_guard.php for admin-only endpoints.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns true if any authenticated user session is active.
 *
 * @param array $session  Typically $_SESSION
 */
function apiAuthIsValid(array $session): bool
{
    return !empty($session['admin_logged_in']);
}

/**
 * Returns true when the current HTTP request was made via AJAX/fetch.
 *
 * @param array $server  Typically $_SERVER
 */
function apiIsAjaxRequest(array $server): bool
{
    $xrw = strtolower($server['HTTP_X_REQUESTED_WITH'] ?? '');
    if ($xrw === 'xmlhttprequest') {
        return true;
    }
    return str_contains($server['HTTP_ACCEPT'] ?? '', 'application/json');
}

if (!apiAuthIsValid($_SESSION) && PHP_SAPI !== 'cli') {
    if (apiIsAjaxRequest($_SERVER)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Sesi telah habis. Silakan login kembali.',
        ]);
        exit;
    }

    // Non-AJAX fallback (direct browser navigation to the API URL)
    http_response_code(403);
    exit;
}
