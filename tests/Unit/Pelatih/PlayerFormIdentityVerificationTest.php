<?php

use PHPUnit\Framework\TestCase;

final class PlayerFormIdentityVerificationTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        $source = file_get_contents(__DIR__ . '/../../../pelatih/player_form.php');
        $this->assertNotFalse($source);

        $this->source = $source;
    }

    public function testSharedIdentityVerificationHelperAddsCsrfAndAjaxHeaders(): void
    {
        $this->assertStringContainsString('function submitIdentityVerification(formData)', $this->source);
        $this->assertStringContainsString("formData.append('csrf_token', window.ADMIN_CSRF_TOKEN || '');", $this->source);
        $this->assertStringContainsString("'Accept': 'application/json'", $this->source);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $this->source);
    }

    public function testNIKAndNISNVerificationReuseSharedHelperInsteadOfRawFetch(): void
    {
        $matches = [];
        preg_match_all('/submitIdentityVerification\(formData\)\s*\.then/', $this->source, $matches);

        $this->assertCount(2, $matches[0]);
        $this->assertSame(1, substr_count($this->source, "fetch('../api/verify_identity.php'"));
    }
}
