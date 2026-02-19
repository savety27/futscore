<?php

function playerEditCollectInput(array $post): array
{
    return [
        'name' => trim((string)($post['name'] ?? '')),
        'place_of_birth' => trim((string)($post['place_of_birth'] ?? '')),
        'date_of_birth' => trim((string)($post['date_of_birth'] ?? '')),
        'sport' => trim((string)($post['sport'] ?? '')),
        'gender' => trim((string)($post['gender'] ?? '')),
        'nik' => trim((string)($post['nik'] ?? '')),
        'nisn' => trim((string)($post['nisn'] ?? '')),
        'height' => trim((string)($post['height'] ?? '')),
        'weight' => trim((string)($post['weight'] ?? '')),
        'email' => trim((string)($post['email'] ?? '')),
        'phone' => trim((string)($post['phone'] ?? '')),
        'nationality' => trim((string)($post['nationality'] ?? '')),
        'address' => trim((string)($post['address'] ?? '')),
        'city' => trim((string)($post['city'] ?? '')),
        'province' => trim((string)($post['province'] ?? '')),
        'postal_code' => trim((string)($post['postal_code'] ?? '')),
        'country' => trim((string)($post['country'] ?? '')),
        'team_id' => trim((string)($post['team_id'] ?? '')),
        'jersey_number' => trim((string)($post['jersey_number'] ?? '')),
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
    $requiredFields = [
        'name',
        'place_of_birth',
        'date_of_birth',
        'sport',
        'gender',
        'team_id',
        'jersey_number',
        'dominant_foot',
        'position',
    ];

    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            return 'Semua field yang wajib harus diisi!';
        }
    }

    if (empty($input['nik']) || strlen($input['nik']) !== 16 || !is_numeric($input['nik'])) {
        return 'NIK harus terdiri dari tepat 16 digit angka!';
    }

    if ($input['nisn'] !== '' && (strlen($input['nisn']) !== 10 || !is_numeric($input['nisn']))) {
        return 'NISN harus terdiri dari tepat 10 digit angka!';
    }

    if ((function_exists('mb_strlen') ? mb_strlen($input['position_detail'], 'UTF-8') : strlen($input['position_detail'])) > 100) {
        return 'Detail posisi maksimal 100 karakter!';
    }

    return null;
}

function playerEditUpdateSql(): string
{
    return "UPDATE players SET
        team_id = :team_id,
        name = :name,
        position = :position,
        jersey_number = :jersey_number,
        birth_date = :birth_date,
        height = :height,
        weight = :weight,
        birth_place = :birth_place,
        gender = :gender,
        nisn = :nisn,
        nik = :nik,
        sport_type = :sport_type,
        email = :email,
        phone = :phone,
        nationality = :nationality,
        street = :street,
        city = :city,
        province = :province,
        postal_code = :postal_code,
        country = :country,
        dominant_foot = :dominant_foot,
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
        ':team_id' => $input['team_id'],
        ':name' => $input['name'],
        ':position' => $input['position'],
        ':jersey_number' => $input['jersey_number'],
        ':birth_date' => $input['date_of_birth'],
        ':height' => $input['height'] !== '' ? $input['height'] : null,
        ':weight' => $input['weight'] !== '' ? $input['weight'] : null,
        ':birth_place' => $input['place_of_birth'],
        ':gender' => playerEditMapGenderForDb($input['gender']),
        ':nisn' => $input['nisn'] !== '' ? $input['nisn'] : null,
        ':nik' => $input['nik'],
        ':sport_type' => $input['sport'],
        ':email' => $input['email'] !== '' ? $input['email'] : null,
        ':phone' => $input['phone'] !== '' ? $input['phone'] : null,
        ':nationality' => $input['nationality'] !== '' ? $input['nationality'] : 'Indonesia',
        ':street' => $input['address'] !== '' ? $input['address'] : null,
        ':city' => $input['city'] !== '' ? $input['city'] : null,
        ':province' => $input['province'] !== '' ? $input['province'] : null,
        ':postal_code' => $input['postal_code'] !== '' ? $input['postal_code'] : null,
        ':country' => $input['country'] !== '' ? $input['country'] : 'Indonesia',
        ':dominant_foot' => $input['dominant_foot'],
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
        ':photo' => $uploadedFiles['photo_file'] ?? null,
        ':ktp_image' => $uploadedFiles['ktp_file'] ?? null,
        ':kk_image' => $uploadedFiles['kk_file'] ?? null,
        ':birth_cert_image' => $uploadedFiles['akte_file'] ?? null,
        ':diploma_image' => $uploadedFiles['ijazah_file'] ?? null,
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
