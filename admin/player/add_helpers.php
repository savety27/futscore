<?php

function playerAddCollectInput(array $post): array
{
    $birthPlace = $post['birth_place'] ?? $post['place_of_birth'] ?? '';
    $birthDate = $post['birth_date'] ?? $post['date_of_birth'] ?? '';
    $sportType = $post['sport_type'] ?? $post['sport'] ?? '';
    $street = $post['street'] ?? $post['address'] ?? '';

    return [
        'name' => trim((string)($post['name'] ?? '')),
        'birth_place' => trim((string)$birthPlace),
        'birth_date' => trim((string)$birthDate),
        'sport_type' => trim((string)$sportType),
        'gender' => trim((string)($post['gender'] ?? '')),
        'nik' => trim((string)($post['nik'] ?? '')),
        'nisn' => trim((string)($post['nisn'] ?? '')),
        'height' => trim((string)($post['height'] ?? '')),
        'weight' => trim((string)($post['weight'] ?? '')),
        'email' => trim((string)($post['email'] ?? '')),
        'phone' => trim((string)($post['phone'] ?? '')),
        'nationality' => trim((string)($post['nationality'] ?? 'Indonesia')),
        'street' => trim((string)$street),
        'city' => trim((string)($post['city'] ?? '')),
        'province' => trim((string)($post['province'] ?? '')),
        'postal_code' => trim((string)($post['postal_code'] ?? '')),
        'country' => trim((string)($post['country'] ?? 'Indonesia')),
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

function playerAddValidateInput(array $input): ?string
{
    $requiredFields = [
        'name',
        'birth_place',
        'birth_date',
        'sport_type',
        'gender',
        'nik',
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

    if (!in_array($input['gender'], ['Laki-laki', 'Perempuan'], true)) {
        return 'Jenis kelamin tidak valid!';
    }

    if (strlen($input['nik']) !== 16 || !is_numeric($input['nik'])) {
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

function playerAddMapGenderForDb(string $gender): string
{
    if ($gender === 'Laki-laki') {
        return 'L';
    }

    if ($gender === 'Perempuan') {
        return 'P';
    }

    return '';
}

function playerAddGenerateSlug(string $name, ?int $timestamp = null): string
{
    $slug = strtolower($name);
    $slug = str_replace(' ', '-', $slug);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    if ($timestamp === null) {
        $timestamp = time();
    }

    return $slug . '-' . $timestamp;
}

function playerAddInsertSql(): string
{
    return "INSERT INTO players (
        team_id, name, slug, position, jersey_number, birth_date, height, weight,
        birth_place, gender, nisn, nik, sport_type, email, phone, nationality,
        street, city, province, postal_code, country, dominant_foot, position_detail,
        dribbling, technique, speed, juggling, shooting, setplay_position, passing, control,
        photo, ktp_image, kk_image, birth_cert_image, diploma_image,
        created_at, updated_at, status
    ) VALUES (
        :team_id, :name, :slug, :position, :jersey_number, :birth_date, :height, :weight,
        :birth_place, :gender, :nisn, :nik, :sport_type, :email, :phone, :nationality,
        :street, :city, :province, :postal_code, :country, :dominant_foot, :position_detail,
        :dribbling, :technique, :speed, :juggling, :shooting, :setplay_position, :passing, :control,
        :photo, :ktp_image, :kk_image, :birth_cert_image, :diploma_image,
        NOW(), NOW(), :status
    )";
}

function playerAddBuildInsertParams(array $input, array $uploadedFiles, string $slug): array
{
    return [
        ':team_id' => $input['team_id'],
        ':name' => $input['name'],
        ':slug' => $slug,
        ':position' => $input['position'],
        ':jersey_number' => $input['jersey_number'],
        ':birth_date' => $input['birth_date'],
        ':height' => $input['height'] !== '' ? $input['height'] : null,
        ':weight' => $input['weight'] !== '' ? $input['weight'] : null,
        ':birth_place' => $input['birth_place'],
        ':gender' => playerAddMapGenderForDb($input['gender']),
        ':nisn' => $input['nisn'] !== '' ? $input['nisn'] : null,
        ':nik' => $input['nik'],
        ':sport_type' => $input['sport_type'],
        ':email' => $input['email'] !== '' ? $input['email'] : null,
        ':phone' => $input['phone'] !== '' ? $input['phone'] : null,
        ':nationality' => $input['nationality'] !== '' ? $input['nationality'] : 'Indonesia',
        ':street' => $input['street'] !== '' ? $input['street'] : null,
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
        ':photo' => $uploadedFiles['photo'] ?? $uploadedFiles['photo_file'] ?? '',
        ':ktp_image' => $uploadedFiles['ktp_image'] ?? $uploadedFiles['ktp_file'] ?? '',
        ':kk_image' => $uploadedFiles['kk_image'] ?? $uploadedFiles['kk_file'] ?? '',
        ':birth_cert_image' => $uploadedFiles['birth_cert_image'] ?? $uploadedFiles['akte_file'] ?? '',
        ':diploma_image' => $uploadedFiles['diploma_image'] ?? $uploadedFiles['ijazah_file'] ?? '',
    ];
}

function playerAddMapInsertError(PDOException $e): string
{
    $driverCode = (int)($e->errorInfo[1] ?? 0);
    $messageLc = strtolower($e->getMessage());

    if ($driverCode === 1062) {
        if (strpos($messageLc, 'uq_players_name') !== false || strpos($messageLc, 'name') !== false) {
            return 'Nama pemain sudah terdaftar. Gunakan nama yang berbeda.';
        }

        if (strpos($messageLc, 'nik') !== false) {
            return 'NIK sudah terdaftar. Gunakan NIK yang berbeda.';
        }

        return 'Data duplikat terdeteksi. Periksa kembali input Anda.';
    }

    return 'Terjadi kesalahan: ' . $e->getMessage();
}
