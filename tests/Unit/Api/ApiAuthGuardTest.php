<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for apiAuthIsValid() and apiIsAjaxRequest() from api/api_auth_guard.php.
 */
final class ApiAuthGuardTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../api/api_auth_guard.php';
    }

    // ─── apiAuthIsValid() ─────────────────────────────────────────────────────

    public function testAnyLoggedInRoleReturnsTrue(): void
    {
        $session = [
            'admin_logged_in' => true,
            'admin_role'      => 'pelatih',
        ];

        $this->assertTrue(apiAuthIsValid($session));
    }

    public function testAdminRoleAlsoReturnsTrue(): void
    {
        $session = [
            'admin_logged_in' => true,
            'admin_role'      => 'admin',
        ];

        $this->assertTrue(apiAuthIsValid($session));
    }

    public function testSuperadminRoleAlsoReturnsTrue(): void
    {
        $session = [
            'admin_logged_in' => true,
            'admin_role'      => 'superadmin',
        ];

        $this->assertTrue(apiAuthIsValid($session));
    }

    public function testLoggedInWithNoRoleKeyReturnsTrue(): void
    {
        // Role is irrelevant for this guard — just needs admin_logged_in
        $session = ['admin_logged_in' => true];

        $this->assertTrue(apiAuthIsValid($session));
    }

    public function testNotLoggedInReturnsFalse(): void
    {
        $session = ['admin_role' => 'admin'];

        $this->assertFalse(apiAuthIsValid($session));
    }

    public function testFalseLoggedInFlagReturnsFalse(): void
    {
        $session = [
            'admin_logged_in' => false,
            'admin_role'      => 'admin',
        ];

        $this->assertFalse(apiAuthIsValid($session));
    }

    public function testNullLoggedInFlagReturnsFalse(): void
    {
        $session = [
            'admin_logged_in' => null,
            'admin_role'      => 'pelatih',
        ];

        $this->assertFalse(apiAuthIsValid($session));
    }

    public function testEmptySessionReturnsFalse(): void
    {
        $this->assertFalse(apiAuthIsValid([]));
    }

    // ─── apiIsAjaxRequest() ───────────────────────────────────────────────────

    public function testXRequestedWithXmlHttpRequestIsAjax(): void
    {
        $server = ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
        $this->assertTrue(apiIsAjaxRequest($server));
    }

    public function testXRequestedWithCaseInsensitiveIsAjax(): void
    {
        $server = ['HTTP_X_REQUESTED_WITH' => 'XMLHTTPREQUEST'];
        $this->assertTrue(apiIsAjaxRequest($server));
    }

    public function testAcceptApplicationJsonIsAjax(): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        $this->assertTrue(apiIsAjaxRequest($server));
    }

    public function testAcceptHtmlIsNotAjax(): void
    {
        $server = ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml,*/*'];
        $this->assertFalse(apiIsAjaxRequest($server));
    }

    public function testEmptyServerIsNotAjax(): void
    {
        $this->assertFalse(apiIsAjaxRequest([]));
    }
}
