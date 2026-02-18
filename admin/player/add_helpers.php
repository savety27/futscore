<?php

function playerAddCollectInput(array $post): array
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
        'place_of_birth',
        'date_of_birth',
        'sport',
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

    if (strlen($input['nik']) !== 16 || !is_numeric($input['nik'])) {
        return 'NIK harus terdiri dari tepat 16 digit angka!';
    }

    if ($input['nisn'] !== '' && (strlen($input['nisn']) !== 10 || !is_numeric($input['nisn']))) {
        return 'NISN harus terdiri dari tepat 10 digit angka!';
    }

    return null;
}

function playerAddMapGenderForDb(string $gender): string
{
    return $gender === 'Laki-laki' ? 'L' : 'P';
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

function playerAddBuildInsertParams(array $input, array $uploadedFiles, string $slug): array
{
    return [
        ':team_id' => $input['team_id'],
        ':name' => $input['name'],
        ':slug' => $slug,
        ':position' => $input['position'],
        ':jersey_number' => $input['jersey_number'],
        ':birth_date' => $input['date_of_birth'],
        ':height' => $input['height'] !== '' ? $input['height'] : null,
        ':weight' => $input['weight'] !== '' ? $input['weight'] : null,
        ':birth_place' => $input['place_of_birth'],
        ':gender' => playerAddMapGenderForDb($input['gender']),
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
        ':position_detail' => $input['position'],
        ':dribbling' => $input['dribbling'],
        ':technique' => $input['technique'],
        ':speed' => $input['speed'],
        ':juggling' => $input['juggling'],
        ':shooting' => $input['shooting'],
        ':setplay_position' => $input['setplay_position'],
        ':passing' => $input['passing'],
        ':control' => $input['control'],
        ':status' => $input['status'],
        ':photo' => $uploadedFiles['photo_file'] ?? '',
        ':ktp_image' => $uploadedFiles['ktp_file'] ?? '',
        ':kk_image' => $uploadedFiles['kk_file'] ?? '',
        ':birth_cert_image' => $uploadedFiles['akte_file'] ?? '',
        ':diploma_image' => $uploadedFiles['ijazah_file'] ?? '',
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
