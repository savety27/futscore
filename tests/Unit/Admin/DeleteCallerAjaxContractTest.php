<?php

use PHPUnit\Framework\TestCase;

final class DeleteCallerAjaxContractTest extends TestCase
{
    private const DELETE_CALLERS = [
        'admin/berita.php' => "fetch('berita_delete.php', {",
        'admin/berita_view.php' => "fetch('berita_delete.php', {",
        'admin/challenge.php' => "fetch('challenge_delete.php', {",
        'admin/event.php' => "fetch('event_delete.php', {",
        'admin/pelatih.php' => "fetch('pelatih_delete.php', {",
        'admin/perangkat.php' => "fetch('perangkat_delete.php', {",
        'admin/player.php' => "fetch('player/delete.php', {",
        'admin/team.php' => "fetch('team_delete.php', {",
        'admin/team_staff.php' => "fetch('team_staff_delete.php', {",
        'admin/venue.php' => "fetch('venue_delete.php', {",
    ];

    public function testDeleteCallersSendCsrfTokenAndAjaxHeaders(): void
    {
        foreach (self::DELETE_CALLERS as $relativePath => $deleteFetch) {
            $source = $this->readSource($relativePath);

            $this->assertStringContainsString($deleteFetch, $source, $relativePath);
            $this->assertStringContainsString("append('csrf_token'", $source, $relativePath);
            $this->assertStringContainsString("'Accept': 'application/json'", $source, $relativePath);
            $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $source, $relativePath);
        }
    }

    private function readSource(string $relativePath): string
    {
        $source = file_get_contents(__DIR__ . '/../../../' . $relativePath);
        $this->assertNotFalse($source, $relativePath);

        return $source;
    }
}
