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
 * Works correctly at any nesting depth inside /admin/ by computing an
 * absolute root-relative redirect URL from DOCUMENT_ROOT at runtime.
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

/**
 * Returns a root-relative URL to login.php that works at any nesting depth.
 *
 * Strategy: __DIR__ is always .../admin/includes/, so two dirname() calls
 * give the site root on the filesystem. We subtract DOCUMENT_ROOT to get
 * the web-root prefix (e.g. "/futscore") and append "/login.php".
 *
 * Falls back to a dynamic relative path if DOCUMENT_ROOT is unavailable (e.g. CLI).
 *
 * @param array $server  Typically $_SERVER
 */
function adminLoginUrl(array $server): string
{
    $siteRoot = dirname(dirname(__DIR__));   // two levels up from admin/includes/
    $docRoot  = realpath($server['DOCUMENT_ROOT'] ?? '');

    if ($docRoot !== false && str_starts_with($siteRoot, $docRoot)) {
        // Produces "" for a root install or "/futscore" for a sub-folder install.
        $webPrefix = substr($siteRoot, strlen($docRoot));
        return $webPrefix . '/login.php';
    }

    // Fallback: walk up from the executing script's directory to the site root,
    // counting levels, so the relative prefix is always correct regardless of depth.
    $rawScript = $server['SCRIPT_FILENAME'] ?? __FILE__;
    $scriptDir = dirname(realpath($rawScript) ?: $rawScript);
    $rel = '';
    $dir = $scriptDir;
    while ($dir !== $siteRoot && $dir !== dirname($dir)) {
        $rel .= '../';
        $dir  = dirname($dir);
    }
    return $rel . 'login.php';
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

    header('Location: ' . adminLoginUrl($_SERVER));
    exit;
}
