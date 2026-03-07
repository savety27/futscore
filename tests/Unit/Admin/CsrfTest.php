<?php

use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        require_once __DIR__ . '/../../../admin/includes/csrf.php';
        unset($_SESSION['csrf_token']);
    }

    public function testTokenIsGeneratedAndReusedWithinSession(): void
    {
        $token = admin_csrf_token();

        $this->assertSame($token, admin_csrf_token());
        $this->assertSame($token, $_SESSION['csrf_token']);
        $this->assertSame(64, strlen($token));
    }

    public function testFieldRendersHiddenInputWithEscapedToken(): void
    {
        $_SESSION['csrf_token'] = 'unsafe"token<value>';

        $field = admin_csrf_field();

        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('unsafe&quot;token&lt;value&gt;', $field);
    }

    public function testValidTokenReturnsTrue(): void
    {
        $_SESSION['csrf_token'] = 'known-token';

        $this->assertTrue(admin_csrf_is_valid('known-token'));
    }

    public function testInvalidOrMissingTokenReturnsFalse(): void
    {
        $_SESSION['csrf_token'] = 'known-token';

        $this->assertFalse(admin_csrf_is_valid('other-token'));
        $this->assertFalse(admin_csrf_is_valid(''));

        unset($_SESSION['csrf_token']);
        $this->assertFalse(admin_csrf_is_valid('known-token'));
    }
}
