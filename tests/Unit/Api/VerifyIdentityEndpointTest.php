<?php

use PHPUnit\Framework\TestCase;

final class VerifyIdentityEndpointTest extends TestCase
{
    public function testMissingCsrfTokenReturns403Json(): void
    {
        $result = $this->runHarness('missing');

        $this->assertSame(403, $result['status']);
        $this->assertSame([
            'verified' => false,
            'message' => 'Invalid CSRF token',
        ], $result['body']);
    }

    public function testInvalidCsrfTokenReturns403Json(): void
    {
        $result = $this->runHarness('invalid');

        $this->assertSame(403, $result['status']);
        $this->assertSame([
            'verified' => false,
            'message' => 'Invalid CSRF token',
        ], $result['body']);
    }

    private function runHarness(string $mode): array
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(__DIR__ . '/../../Fixtures/Api/verify_identity_csrf_harness.php')
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
