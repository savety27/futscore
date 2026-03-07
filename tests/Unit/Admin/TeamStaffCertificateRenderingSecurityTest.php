<?php

use PHPUnit\Framework\TestCase;

final class TeamStaffCertificateRenderingSecurityTest extends TestCase
{
    public function testTeamStaffListBuildsCertificateModalWithDomNodes(): void
    {
        $source = $this->readSource('admin/team_staff.php');

        $this->assertStringNotContainsString('content.innerHTML = html;', $source);
        $this->assertStringNotContainsString('modal.innerHTML = `', $source);
        $this->assertStringNotContainsString('onclick="viewCertificates(', $source);
        $this->assertStringNotContainsString("onclick=\"viewCertificateImage(", $source);
        $this->assertStringContainsString('content.replaceChildren(createCertificateGrid(certificates));', $source);
        $this->assertStringContainsString('encodeURIComponent(filename)', $source);
    }

    public function testTeamStaffDetailViewUsesDatasetBackedPreviewTriggers(): void
    {
        $source = $this->readSource('admin/team_staff_view.php');

        $this->assertStringNotContainsString("onclick=\"viewCertificateImage(", $source);
        $this->assertStringNotContainsString('modal.innerHTML = `', $source);
        $this->assertStringContainsString('certificate-preview-trigger', $source);
        $this->assertStringContainsString('data-certificate-file=', $source);
        $this->assertStringContainsString('encodeURIComponent(filename)', $source);
    }

    public function testCertificateEndpointSelectsExplicitFieldsOnly(): void
    {
        $source = $this->readSource('admin/team_staff_certificates.php');

        $this->assertStringNotContainsString('SELECT * FROM staff_certificates', $source);
        $this->assertStringContainsString('SELECT certificate_name, certificate_file, issuing_authority, issue_date', $source);
    }

    private function readSource(string $relativePath): string
    {
        $source = file_get_contents(__DIR__ . '/../../../' . $relativePath);
        $this->assertNotFalse($source, $relativePath);

        return $source;
    }
}
