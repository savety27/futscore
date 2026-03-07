<?php

use PHPUnit\Framework\TestCase;

final class TeamStaffOutputHardeningTest extends TestCase
{
    public function testCreateAndEditUseSharedPositionValidationAndOptions(): void
    {
        $createSource = $this->readSource('admin/team_staff_create.php');
        $editSource = $this->readSource('admin/team_staff_edit.php');

        $this->assertStringContainsString("require_once __DIR__ . '/includes/team_staff_helpers.php';", $createSource);
        $this->assertStringContainsString("require_once __DIR__ . '/includes/team_staff_helpers.php';", $editSource);
        $this->assertStringContainsString('teamStaffPositionValidationError(', $createSource);
        $this->assertStringContainsString('teamStaffPositionValidationError(', $editSource);
        $this->assertStringContainsString('foreach (teamStaffPositionOptions() as $positionValue => $positionLabel)', $createSource);
        $this->assertStringContainsString('foreach (teamStaffPositionOptions() as $positionValue => $positionLabel)', $editSource);
    }

    public function testListAndDetailUseSharedSafePositionLabels(): void
    {
        $listSource = $this->readSource('admin/team_staff.php');
        $viewSource = $this->readSource('admin/team_staff_view.php');

        $this->assertStringContainsString('teamStaffPositionBadgeClass(', $listSource);
        $this->assertStringContainsString('teamStaffPositionLabel(', $listSource);
        $this->assertStringNotContainsString("ucfirst(\$staff['position'] ?? '')", $listSource);
        $this->assertStringContainsString('teamStaffPositionBadgeClass(', $viewSource);
        $this->assertStringContainsString('teamStaffPositionLabel(', $viewSource);
        $this->assertStringNotContainsString('background: #FFD700', $viewSource);
        $this->assertStringNotContainsString("ucfirst(\$staff_data['position'] ?? '')", $viewSource);
    }

    public function testExportUsesFormulaSafeTextCellsAndSharedPositionLabels(): void
    {
        $exportSource = $this->readSource('admin/team_staff_export.php');

        $this->assertStringContainsString("require_once __DIR__ . '/includes/team_staff_helpers.php';", $exportSource);
        $this->assertStringContainsString('teamStaffExportTextCell(', $exportSource);
        $this->assertStringContainsString('teamStaffPositionLabel(', $exportSource);
        $this->assertStringNotContainsString('position_formatted', $exportSource);
    }

    private function readSource(string $relativePath): string
    {
        $source = file_get_contents(__DIR__ . '/../../../' . $relativePath);
        $this->assertNotFalse($source, $relativePath);

        return $source;
    }
}
