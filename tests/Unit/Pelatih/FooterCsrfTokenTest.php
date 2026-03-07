<?php

use PHPUnit\Framework\TestCase;

final class FooterCsrfTokenTest extends TestCase
{
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION['csrf_token'] = 'known-token';
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
    }

    public function testFooterExposesSessionCsrfTokenToJavascript(): void
    {
        ob_start();
        require __DIR__ . '/../../../pelatih/includes/footer.php';
        $output = ob_get_clean();

        $this->assertStringContainsString('window.ADMIN_CSRF_TOKEN', $output);
        $this->assertStringContainsString(json_encode('known-token'), $output);
    }
}
