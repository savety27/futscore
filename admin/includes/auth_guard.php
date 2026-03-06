<?php
/**
 * Admin Area Auth Guard
 *
 * Require this file at the very top of every admin PHP page.
 * It enforces two conditions:
 *   1. The user has an active session (admin_logged_in = true)
 *   2. The user's role is exactly 'admin'
 *
 * If either condition fails, the user is redirected to login.php and
 * execution stops immediately via exit.
 *
 * Works correctly whether the file is in /admin/ or /admin/player/ (one
 * extra directory level deep) by computing the redirect path at runtime.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns true if the current session belongs to an admin user.
 * Extracted as a function so it can be unit-tested without side effects.
 *
 * @param array $session  Typically $_SESSION
 */
function adminAuthIsValid(array $session): bool
{
    // The admin_users table uses 'superadmin' as the role value for admin
    // accounts (not 'admin'). Accept both to be safe.
    $role = $session['admin_role'] ?? '';
    return !empty($session['admin_logged_in'])
        && in_array($role, ['admin', 'superadmin'], true);
}

/**
 * Returns true when the current HTTP request was made via AJAX/fetch and
 * therefore expects a JSON response rather than an HTML redirect.
 *
 * Detection covers two standard signals:
 *   1. X-Requested-With: XMLHttpRequest  (jQuery / Axios)
 *   2. Accept header containing application/json  (native fetch with explicit header)
 *
 * @param array $server  Typically $_SERVER
 */
function adminIsAjaxRequest(array $server): bool
{
    $xrw = strtolower($server['HTTP_X_REQUESTED_WITH'] ?? '');
    if ($xrw === 'xmlhttprequest') {
        return true;
    }
    $accept = $server['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json');
}

if (!adminAuthIsValid($_SESSION) && PHP_SAPI !== 'cli') {
    // AJAX / fetch requests expect JSON — return 401 instead of redirecting.
    // A redirect would cause response.json() to throw a SyntaxError on the
    // HTML login page that the browser silently follows the redirect to.
    if (adminIsAjaxRequest($_SERVER)) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Sesi telah habis. Silakan login kembali.',
        ]);
        exit;
    }

    // __DIR__ is always admin/includes/.
    // The executing script is either in admin/ (one level up) or admin/player/
    // (a sibling subdirectory). Detect which case we're in and use the same
    // simple relative-path approach that pelatih/operator headers already use.
    //
    // admin/berita.php         → ../login.php   (browser: /futscore/login.php) ✓
    // admin/player/add.php     → ../../login.php (browser: /futscore/login.php) ✓
    $adminRoot  = dirname(__DIR__);                                    // .../admin/
    $scriptDir  = dirname(realpath($_SERVER['SCRIPT_FILENAME'] ?? __FILE__));
    $isInSubdir = (strpos($scriptDir . DIRECTORY_SEPARATOR, $adminRoot . DIRECTORY_SEPARATOR) === 0)
                  && ($scriptDir !== $adminRoot);

    $prefix = $isInSubdir ? '../../' : '../';

    header('Location: ' . $prefix . 'login.php');
    exit;
}
