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
}
