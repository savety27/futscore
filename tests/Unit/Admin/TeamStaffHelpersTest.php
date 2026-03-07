<?php

require_once __DIR__ . '/../../../admin/includes/team_staff_helpers.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TeamStaffHelpersTest extends TestCase
{
    public function testPositionOptionsExposeTheAllowedValues(): void
    {
        $this->assertSame([
            'manager' => 'Manager',
            'headcoach' => 'Head Coach',
            'coach' => 'Coach',
            'goalkeeper_coach' => 'Goalkeeper Coach',
            'medic' => 'Medic',
            'official' => 'Official',
        ], teamStaffPositionOptions());
    }

    #[DataProvider('validPositionProvider')]
    public function testPositionValidationAcceptsAllowedValues(string $position): void
    {
        $this->assertTrue(teamStaffPositionIsValid($position));
        $this->assertNull(teamStaffPositionValidationError($position));
        $this->assertNotSame('Unknown', teamStaffPositionLabel($position));
    }

    public static function validPositionProvider(): array
    {
        return [
            ['manager'],
            ['headcoach'],
            ['coach'],
            ['goalkeeper_coach'],
            ['medic'],
            ['official'],
        ];
    }

    #[DataProvider('invalidPositionProvider')]
    public function testPositionValidationRejectsUnexpectedValues(?string $position, string $expectedError): void
    {
        $this->assertFalse(teamStaffPositionIsValid($position));
        $this->assertSame($expectedError, teamStaffPositionValidationError($position));
    }

    public static function invalidPositionProvider(): array
    {
        return [
            [null, 'Jabatan harus dipilih'],
            ['', 'Jabatan harus dipilih'],
            ['assistant', 'Jabatan tidak valid'],
            ['<script>alert(1)</script>', 'Jabatan tidak valid'],
        ];
    }

    public function testPositionLabelFallsBackToUnknownForUnexpectedValues(): void
    {
        $this->assertSame('Unknown', teamStaffPositionLabel('assistant'));
    }

    public function testBadgeClassFallsBackToSecondaryForUnexpectedValues(): void
    {
        $this->assertSame('badge-secondary', teamStaffPositionBadgeClass('assistant'));
    }

    public function testExportEscapeTextEscapesHtml(): void
    {
        $escaped = teamStaffExportEscapeText('<script>alert("x")</script>');

        $this->assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $escaped);
    }

    public function testExportEscapeTextNeutralizesFormulaLikeValues(): void
    {
        $escaped = teamStaffExportEscapeText(" =SUM(A1:A2)");

        $this->assertSame('&#039; =SUM(A1:A2)', $escaped);
    }

    public function testExportEscapeTextUsesTheConfiguredPlaceholderForBlankValues(): void
    {
        $this->assertSame('-', teamStaffExportEscapeText(''));
        $this->assertSame('Indonesia', teamStaffExportEscapeText('', 'Indonesia'));
    }

    public function testExportTextCellFormatsTextCellsForExcel(): void
    {
        $cell = teamStaffExportTextCell('@admin');

        $this->assertStringContainsString('mso-number-format', $cell);
        $this->assertStringContainsString('&#039;@admin', $cell);
        $this->assertStringEndsWith('</td>', $cell);
    }
}
