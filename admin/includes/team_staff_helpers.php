<?php

function teamStaffPositionOptions(): array
{
    return [
        'manager' => 'Manager',
        'headcoach' => 'Head Coach',
        'coach' => 'Coach',
        'goalkeeper_coach' => 'Goalkeeper Coach',
        'medic' => 'Medic',
        'official' => 'Official',
    ];
}

function teamStaffPositionBadgeClass(?string $position): string
{
    $positionClasses = [
        'manager' => 'badge-primary',
        'headcoach' => 'badge-success',
        'coach' => 'badge-secondary',
        'goalkeeper_coach' => 'badge-warning',
        'medic' => 'badge-danger',
        'official' => 'badge-info',
    ];

    $position = trim((string)$position);

    return $positionClasses[$position] ?? 'badge-secondary';
}

function teamStaffPositionIsValid(?string $position): bool
{
    $position = trim((string)$position);

    return $position !== '' && array_key_exists($position, teamStaffPositionOptions());
}

function teamStaffPositionValidationError(?string $position): ?string
{
    $position = trim((string)$position);

    if ($position === '') {
        return 'Jabatan harus dipilih';
    }

    if (!teamStaffPositionIsValid($position)) {
        return 'Jabatan tidak valid';
    }

    return null;
}

function teamStaffPositionLabel(?string $position): string
{
    $position = trim((string)$position);
    $positionOptions = teamStaffPositionOptions();

    return $positionOptions[$position] ?? 'Unknown';
}

function teamStaffExportShouldNeutralizeFormula(string $value): bool
{
    return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1;
}

function teamStaffExportEscapeText($value, string $placeholder = '-'): string
{
    $text = (string)$value;

    if (trim($text) === '') {
        $text = $placeholder;
    } elseif (teamStaffExportShouldNeutralizeFormula($text)) {
        $text = "'" . $text;
    }

    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function teamStaffExportTextCell($value, string $placeholder = '-'): string
{
    return "<td style=\"mso-number-format:'\\@';\">" . teamStaffExportEscapeText($value, $placeholder) . '</td>';
}
