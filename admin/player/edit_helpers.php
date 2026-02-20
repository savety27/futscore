<?php

function playerEditCollectInput(array $post): array
{
    return [
        'name' => trim((string)($post['name'] ?? '')),
        'birth_place' => trim((string)($post['birth_place'] ?? '')),
        'birth_date' => trim((string)($post['birth_date'] ?? '')),
        'sport_type' => trim((string)($post['sport_type'] ?? '')),
        'gender' => trim((string)($post['gender'] ?? '')),
        'nik' => trim((string)($post['nik'] ?? '')),
        'nisn' => trim((string)($post['nisn'] ?? '')),
        'height' => trim((string)($post['height'] ?? '')),
        'weight' => trim((string)($post['weight'] ?? '')),
        'email' => trim((string)($post['email'] ?? '')),
        'phone' => trim((string)($post['phone'] ?? '')),
        'nationality' => trim((string)($post['nationality'] ?? 'Indonesia')),
        'street' => trim((string)($post['street'] ?? '')),
        'city' => trim((string)($post['city'] ?? '')),
        'province' => trim((string)($post['province'] ?? '')),
        'postal_code' => trim((string)($post['postal_code'] ?? '')),
        'country' => trim((string)($post['country'] ?? 'Indonesia')),
        'team_id' => ($post['team_id'] ?? '') !== '' ? (string)$post['team_id'] : null,
        'jersey_number' => ($post['jersey_number'] ?? '') !== '' ? (string)$post['jersey_number'] : null,
        'dominant_foot' => trim((string)($post['dominant_foot'] ?? '')),
        'position' => trim((string)($post['position'] ?? '')),
        'position_detail' => trim((string)($post['position_detail'] ?? '')),
        'status' => isset($post['status']) ? 'active' : 'inactive',
        'dribbling' => isset($post['dribbling']) ? (int)$post['dribbling'] : 5,
        'technique' => isset($post['technique']) ? (int)$post['technique'] : 5,
        'speed' => isset($post['speed']) ? (int)$post['speed'] : 5,
        'juggling' => isset($post['juggling']) ? (int)$post['juggling'] : 5,
        'shooting' => isset($post['shooting']) ? (int)$post['shooting'] : 5,
        'setplay_position' => isset($post['setplay_position']) ? (int)$post['setplay_position'] : 5,
        'passing' => isset($post['passing']) ? (int)$post['passing'] : 5,
        'control' => isset($post['control']) ? (int)$post['control'] : 5,
    ];
}

function playerEditValidateInput(array $input): ?string
{
    $nikError = playerEditValidateNik((string)($input['nik'] ?? ''));
    if ($nikError !== null) {
        return $nikError;
    }

    if ((function_exists('mb_strlen') ? mb_strlen($input['position_detail'], 'UTF-8') : strlen($input['position_detail'])) > 100) {
        return 'Detail posisi maksimal 100 karakter!';
    }

    return null;
}

function playerEditUpdateSql(): string
{
    return "UPDATE players SET
        name = :name,
        birth_place = :birth_place,
        birth_date = :birth_date,
        sport_type = :sport_type,
        gender = :gender,
        nik = :nik,
        nisn = :nisn,
        height = :height,
        weight = :weight,
        email = :email,
        phone = :phone,
        nationality = :nationality,
        street = :street,
        city = :city,
        province = :province,
        postal_code = :postal_code,
        country = :country,
        team_id = :team_id,
        jersey_number = :jersey_number,
        dominant_foot = :dominant_foot,
        position = :position,
        position_detail = :position_detail,
        dribbling = :dribbling,
        technique = :technique,
        speed = :speed,
        juggling = :juggling,
        shooting = :shooting,
        setplay_position = :setplay_position,
        passing = :passing,
        control = :control,
        photo = :photo,
        ktp_image = :ktp_image,
        kk_image = :kk_image,
        birth_cert_image = :birth_cert_image,
        diploma_image = :diploma_image,
        status = :status,
        updated_at = NOW()
    WHERE id = :id";
}

function playerEditBuildUpdateParams(array $input, array $uploadedFiles, int $id): array
{
    return [
        ':id' => $id,
        ':name' => $input['name'],
        ':birth_place' => $input['birth_place'],
        ':birth_date' => $input['birth_date'],
        ':sport_type' => $input['sport_type'],
        ':gender' => playerEditMapGenderForDb($input['gender']),
        ':nik' => $input['nik'],
        ':nisn' => $input['nisn'] !== '' ? $input['nisn'] : null,
        ':height' => $input['height'] !== '' ? $input['height'] : 0,
        ':weight' => $input['weight'] !== '' ? $input['weight'] : 0,
        ':email' => $input['email'] !== '' ? $input['email'] : null,
        ':phone' => $input['phone'] !== '' ? $input['phone'] : null,
        ':nationality' => $input['nationality'] !== '' ? $input['nationality'] : 'Indonesia',
        ':street' => $input['street'] !== '' ? $input['street'] : null,
        ':city' => $input['city'] !== '' ? $input['city'] : null,
        ':province' => $input['province'] !== '' ? $input['province'] : null,
        ':postal_code' => $input['postal_code'] !== '' ? $input['postal_code'] : null,
        ':country' => $input['country'] !== '' ? $input['country'] : 'Indonesia',
        ':team_id' => $input['team_id'],
        ':jersey_number' => $input['jersey_number'],
        ':dominant_foot' => $input['dominant_foot'],
        ':position' => $input['position'],
        ':position_detail' => $input['position_detail'] !== '' ? $input['position_detail'] : null,
        ':dribbling' => $input['dribbling'],
        ':technique' => $input['technique'],
        ':speed' => $input['speed'],
        ':juggling' => $input['juggling'],
        ':shooting' => $input['shooting'],
        ':setplay_position' => $input['setplay_position'],
        ':passing' => $input['passing'],
        ':control' => $input['control'],
        ':status' => $input['status'],
        ':photo' => $uploadedFiles['photo'] ?? null,
        ':ktp_image' => $uploadedFiles['ktp_image'] ?? null,
        ':kk_image' => $uploadedFiles['kk_image'] ?? null,
        ':birth_cert_image' => $uploadedFiles['birth_cert_image'] ?? null,
        ':diploma_image' => $uploadedFiles['diploma_image'] ?? null,
    ];
}

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
