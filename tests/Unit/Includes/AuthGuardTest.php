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

    public function testAcceptHeaderContainingJsonIsAjax(): void
    {
        $server = ['HTTP_ACCEPT' => 'text/html,application/json;q=0.9'];
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

    public function testAdminRequireAjaxJsonRequestAllowsXRequestedWith(): void
    {
        $result = $this->runAjaxGuardHarness('xrw');

        $this->assertSame(200, $result['status']);
        $this->assertSame('', $result['body']);
    }

    public function testAdminRequireAjaxJsonRequestAllowsAcceptJson(): void
    {
        $result = $this->runAjaxGuardHarness('accept');

        $this->assertSame(200, $result['status']);
        $this->assertSame('', $result['body']);
    }

    public function testAdminRequireAjaxJsonRequestRejectsNonAjaxRequest(): void
    {
        $result = $this->runAjaxGuardHarness('html');

        $this->assertSame(400, $result['status']);
        $this->assertSame([
            'success' => false,
            'message' => 'AJAX/JSON request required',
        ], $result['body']);
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

    private function runAjaxGuardHarness(string $mode): array
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(__DIR__ . '/../../Fixtures/Admin/admin_ajax_guard_harness.php')
            . ' '
            . escapeshellarg($mode);

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));

        $decoded = json_decode(implode("\n", $output), true);
        $this->assertIsArray($decoded, implode("\n", $output));

        return $decoded;
    }
}
