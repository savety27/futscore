<?php

use PHPUnit\Framework\TestCase;

final class HtmlFormCsrfStatusTest extends TestCase
{
    private const HTML_FORM_HANDLERS = [
        'admin/berita_create.php',
        'admin/berita_edit.php',
        'admin/challenge_create.php',
        'admin/challenge_edit.php',
        'admin/challenge_result.php',
        'admin/event_create.php',
        'admin/event_edit.php',
        'admin/pelatih_create.php',
        'admin/pelatih_edit.php',
        'admin/perangkat_create.php',
        'admin/perangkat_edit.php',
        'admin/player/add.php',
        'admin/player/edit.php',
        'admin/team_create.php',
        'admin/team_edit.php',
        'admin/team_staff_create.php',
        'admin/team_staff_edit.php',
        'admin/transfer.php',
        'admin/venue_create.php',
        'admin/venue_edit.php',
    ];

    private const JSON_CSRF_ENDPOINTS = [
        'admin/berita_delete.php',
        'admin/challenge_delete.php',
        'admin/event_delete.php',
        'admin/pelatih_delete.php',
        'admin/perangkat_delete.php',
        'admin/player/delete.php',
        'admin/team_delete.php',
        'admin/team_staff_delete.php',
        'admin/venue_delete.php',
    ];

    public function testHtmlFormHandlersDoNotReturnForbiddenStatusOnInvalidCsrf(): void
    {
        foreach (self::HTML_FORM_HANDLERS as $relativePath) {
            $source = $this->readSource($relativePath);

            $this->assertStringContainsString('$has_valid_csrf = false;', $source, $relativePath);
            $this->assertStringNotContainsString('http_response_code(403);', $source, $relativePath);
        }
    }

    public function testJsonDeleteEndpointsStillReturnForbiddenStatusOnInvalidCsrf(): void
    {
        foreach (self::JSON_CSRF_ENDPOINTS as $relativePath) {
            $source = $this->readSource($relativePath);

            $this->assertStringContainsString('http_response_code(403);', $source, $relativePath);
            $this->assertStringContainsString('json_encode', $source, $relativePath);
        }
    }

    public function testJsonDeleteEndpointsRequireAjaxJsonRequest(): void
    {
        foreach (self::JSON_CSRF_ENDPOINTS as $relativePath) {
            $source = $this->readSource($relativePath);

            $this->assertStringContainsString('adminRequireAjaxJsonRequest($_SERVER);', $source, $relativePath);
        }
    }

    private function readSource(string $relativePath): string
    {
        $source = file_get_contents(__DIR__ . '/../../../' . $relativePath);
        $this->assertNotFalse($source, $relativePath);

        return $source;
    }
}
