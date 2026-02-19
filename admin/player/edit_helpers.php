<?php

function playerEditValidateNik(string $nik): ?string
{
    if (strlen($nik) !== 16 || !is_numeric($nik)) {
        return 'NIK harus terdiri dari tepat 16 digit angka!';
    }

    return null;
}

function playerEditValidateKkImage(bool $hasExistingFile, bool $newFileUploaded, bool $deleteChecked): ?string
{
    if (!$newFileUploaded && (!$hasExistingFile || $deleteChecked)) {
        return 'File Kartu Keluarga (KK) wajib diupload!';
    }

    return null;
}

function playerEditMapGenderForDb(string $gender): string
{
    if ($gender === 'Laki-laki') {
        return 'L';
    }

    if ($gender === 'Perempuan') {
        return 'P';
    }

    return '';
}

function playerEditMapUpdateError(PDOException $e): string
{
    $driverCode = (int)($e->errorInfo[1] ?? 0);
    $messageLc = strtolower($e->getMessage());

    if ($driverCode === 1062) {
        if (strpos($messageLc, 'uq_players_name') !== false || strpos($messageLc, 'name') !== false) {
            return 'Error: Nama pemain sudah terdaftar. Gunakan nama yang berbeda.';
        }

        if (strpos($messageLc, 'nik') !== false) {
            return 'Error: NIK sudah terdaftar. Gunakan NIK yang berbeda.';
        }

        return 'Error: Data duplikat terdeteksi. Periksa kembali input Anda.';
    }

    return 'Error: ' . $e->getMessage();
}
