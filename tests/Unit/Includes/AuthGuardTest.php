<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for adminAuthIsValid() extracted from admin/includes/auth_guard.php.
 */
final class AuthGuardTest extends TestCase
{
    protected function setUp(): void
    {
        // Load the guard file so adminAuthIsValid() is available.
        // The guard also calls session_start() at file scope, which is fine in CLI.
        require_once __DIR__ . '/../../../admin/includes/auth_guard.php';
    }

    // ─── adminAuthIsValid() unit tests ────────────────────────────────────────

    public function testValidAdminSessionReturnsTrue(): void
    {
        $session = [
            'admin_logged_in' => true,
            'admin_role'      => 'admin',
        ];

        $this->assertTrue(adminAuthIsValid($session));
    }

    public function testSuperadminRoleAlsoReturnsTrue(): void
    {
        // The DB uses 'superadmin' as the actual role value for admin accounts.
        $session = [
            'admin_logged_in' => true,
            'admin_role'      => 'superadmin',
        ];

        $this->assertTrue(adminAuthIsValid($session));
    }

    public function testPelatihRoleReturnsFalse(): void
    {
        $session = [
            'admin_logged_in' => true,
            'admin_role'      => 'pelatih',
        ];

        $this->assertFalse(adminAuthIsValid($session));
    }

    public function testOperatorRoleReturnsFalse(): void
    {
        $session = [
            'admin_logged_in' => true,
            'admin_role'      => 'operator',
        ];

        $this->assertFalse(adminAuthIsValid($session));
    }

    public function testEmptyRoleReturnsFalse(): void
    {
        $session = [
            'admin_logged_in' => true,
            'admin_role'      => '',
        ];

        $this->assertFalse(adminAuthIsValid($session));
    }

    public function testMissingRoleKeyReturnsFalse(): void
    {
        $session = [
            'admin_logged_in' => true,
            // admin_role key absent
        ];

        $this->assertFalse(adminAuthIsValid($session));
    }

    public function testNotLoggedInWithAdminRoleReturnsFalse(): void
    {
        $session = [
            // admin_logged_in absent
            'admin_role' => 'admin',
        ];

        $this->assertFalse(adminAuthIsValid($session));
    }

    public function testEmptyLoggedInFlagReturnsFalse(): void
    {
        $session = [
            'admin_logged_in' => false,
            'admin_role'      => 'admin',
        ];

        $this->assertFalse(adminAuthIsValid($session));
    }

    public function testCompletelyEmptySessionReturnsFalse(): void
    {
        $this->assertFalse(adminAuthIsValid([]));
    }

    public function testRoleIsCaseSensitive(): void
    {
        // 'Admin' (capital A) must NOT grant access — only 'admin' (lowercase).
        $session = [
            'admin_logged_in' => true,
            'admin_role'      => 'Admin',
        ];

        $this->assertFalse(adminAuthIsValid($session));
    }

    public function testNullLoggedInFlagReturnsFalse(): void
    {
        $session = [
            'admin_logged_in' => null,
            'admin_role'      => 'admin',
        ];

        $this->assertFalse(adminAuthIsValid($session));
    }

    // ─── isAjaxRequest() unit tests ───────────────────────────────────────────

    public function testXRequestedWithXmlHttpRequestIsAjax(): void
    {
        $server = ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        $this->assertTrue(adminIsAjaxRequest($server));
    }

    public function testXRequestedWithCaseInsensitive(): void
    {
        $server = ['HTTP_X_REQUESTED_WITH' => 'XMLHTTPREQUEST'];
        $this->assertTrue(adminIsAjaxRequest($server));
    }

    public function testAcceptJsonIsAjax(): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        $this->assertTrue(adminIsAjaxRequest($server));
    }

    public function testAcceptHtmlIsNotAjax(): void
    {
        $server = ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml,*/*'];
        $this->assertFalse(adminIsAjaxRequest($server));
    }

    public function testEmptyServerIsNotAjax(): void
    {
        $this->assertFalse(adminIsAjaxRequest([]));
    }

    // ─── adminLoginUrl() unit tests ───────────────────────────────────────────

    public function testLoginUrlSubFolderInstall(): void
    {
        // Simulates: DOCUMENT_ROOT=/var/www/html, site lives at /var/www/html/futscore
        // Must use realpath() so dirname() resolves symlinks the same way adminLoginUrl() does.
        $siteRoot = dirname(dirname(realpath(__DIR__ . '/../../../admin/includes')));
        $docRoot  = dirname($siteRoot);   // /var/www/html — one level above the site root

        $server = [
            'DOCUMENT_ROOT'   => $docRoot,
            'SCRIPT_FILENAME' => $siteRoot . '/admin/dashboard.php',
        ];

        $url = adminLoginUrl($server);

        // Should be /<folder>/login.php — an absolute path starting with /
        $this->assertStringStartsWith('/', $url);
        $this->assertStringEndsWith('/login.php', $url);
    }

    public function testLoginUrlRootInstall(): void
    {
        // Simulates: site is installed directly at the document root
        // (DOCUMENT_ROOT === siteRoot), so the web prefix is empty.
        $siteRoot = dirname(dirname(realpath(__DIR__ . '/../../../admin/includes')));

        $server = [
            'DOCUMENT_ROOT'   => $siteRoot,
            'SCRIPT_FILENAME' => $siteRoot . '/admin/dashboard.php',
        ];

        $this->assertSame('/login.php', adminLoginUrl($server));
    }

    public function testLoginUrlFallbackOneLevelDeep(): void
    {
        // DOCUMENT_ROOT points to a nonexistent path to force the relative fallback branch.
        // Script is one level inside admin/ (e.g. admin/dashboard.php).
        $siteRoot = dirname(dirname(realpath(__DIR__ . '/../../../admin/includes')));

        $server = [
            'DOCUMENT_ROOT'   => '/nonexistent',
            'SCRIPT_FILENAME' => $siteRoot . '/admin/dashboard.php',
        ];

        $url = adminLoginUrl($server);

        // One level deep → should produce "../login.php"
        $this->assertSame('../login.php', $url);
    }

    public function testLoginUrlFallbackTwoLevelsDeep(): void
    {
        // DOCUMENT_ROOT points to a nonexistent path to force the relative fallback branch.
        // Script is two levels inside admin/ (e.g. admin/player/add.php).
        $siteRoot = dirname(dirname(realpath(__DIR__ . '/../../../admin/includes')));

        $server = [
            'DOCUMENT_ROOT'   => '/nonexistent',
            'SCRIPT_FILENAME' => $siteRoot . '/admin/player/add.php',
        ];

        $url = adminLoginUrl($server);

        // Two levels deep → should produce "../../login.php"
        $this->assertSame('../../login.php', $url);
    }

    public function testLoginUrlFallbackThreeLevelsDeep(): void
    {
        // DOCUMENT_ROOT points to a nonexistent path to force the relative fallback branch.
        // Script is three levels inside admin/ — the new scenario the coworker flagged.
        $siteRoot = dirname(dirname(realpath(__DIR__ . '/../../../admin/includes')));

        $server = [
            'DOCUMENT_ROOT'   => '/nonexistent',
            'SCRIPT_FILENAME' => $siteRoot . '/admin/reports/monthly/view.php',
        ];

        $url = adminLoginUrl($server);

        // Three levels deep → should produce "../../../login.php"
        $this->assertSame('../../../login.php', $url);
    }
}
