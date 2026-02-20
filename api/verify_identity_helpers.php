<?php

function verifyNIK($nik, $provinsi_codes, $conn, $exclude_player_id)
{
    if (!preg_match('/^[0-9]{16}$/', $nik)) {
        return [
            'verified' => false,
            'message' => 'NIK harus terdiri dari tepat 16 digit angka',
            'details' => ['step' => 'format']
        ];
    }

    $kode_provinsi = substr($nik, 0, 2);
    $kode_kab_kota = substr($nik, 2, 2);
    $kode_kecamatan = substr($nik, 4, 2);
    $tanggal = substr($nik, 6, 2);
    $bulan = substr($nik, 8, 2);
    $tahun = substr($nik, 10, 2);
    $urutan = substr($nik, 12, 4);

    if (!isset($provinsi_codes[$kode_provinsi])) {
        return [
            'verified' => false,
            'message' => "Kode provinsi '$kode_provinsi' tidak terdaftar di Kemendagri",
            'details' => [
                'step' => 'provinsi',
                'kode_provinsi' => $kode_provinsi
            ]
        ];
    }

    $nama_provinsi = $provinsi_codes[$kode_provinsi];

    $kab_kota_int = (int)$kode_kab_kota;
    if ($kab_kota_int < 1 || $kab_kota_int > 99) {
        return [
            'verified' => false,
            'message' => "Kode kabupaten/kota '$kode_kab_kota' tidak valid",
            'details' => ['step' => 'kab_kota']
        ];
    }

    $kec_int = (int)$kode_kecamatan;
    if ($kec_int < 1 || $kec_int > 99) {
        return [
            'verified' => false,
            'message' => "Kode kecamatan '$kode_kecamatan' tidak valid",
            'details' => ['step' => 'kecamatan']
        ];
    }

    $tgl_int = (int)$tanggal;
    $is_female = false;
    if ($tgl_int > 40) {
        $tgl_int -= 40;
        $is_female = true;
    }

    $bulan_int = (int)$bulan;

    if ($tgl_int < 1 || $tgl_int > 31) {
        return [
            'verified' => false,
            'message' => 'Tanggal lahir ter-encode di NIK tidak valid',
            'details' => ['step' => 'tanggal']
        ];
    }

    if ($bulan_int < 1 || $bulan_int > 12) {
        return [
            'verified' => false,
            'message' => 'Bulan lahir ter-encode di NIK tidak valid',
            'details' => ['step' => 'bulan']
        ];
    }

    $tahun_full = ((int)$tahun > 30) ? 1900 + (int)$tahun : 2000 + (int)$tahun;
    if (!checkdate($bulan_int, $tgl_int, $tahun_full)) {
        return [
            'verified' => false,
            'message' => 'Tanggal lahir yang ter-encode di NIK tidak valid (tanggal tidak sesuai bulan/tahun)',
            'details' => ['step' => 'checkdate']
        ];
    }

    $urutan_int = (int)$urutan;
    if ($urutan_int < 1) {
        return [
            'verified' => false,
            'message' => 'Nomor urut registrasi di NIK tidak valid',
            'details' => ['step' => 'urutan']
        ];
    }

    try {
        $sql = "SELECT id, name FROM players WHERE nik = ?";
        $params = [$nik];
        if ($exclude_player_id > 0) {
            $sql .= " AND id != ?";
            $params[] = $exclude_player_id;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return [
                'verified' => false,
                'message' => 'NIK sudah terdaftar atas nama "' . $existing['name'] . '"',
                'details' => ['step' => 'duplicate', 'existing_player' => $existing['name']]
            ];
        }
    } catch (PDOException $e) {
        error_log("DB Error in NIK verification: " . $e->getMessage());
    }

    $gender_str = $is_female ? 'Perempuan' : 'Laki-laki';
    $tgl_lahir_str = sprintf('%02d-%02d-%04d', $tgl_int, $bulan_int, $tahun_full);

    return [
        'verified' => true,
        'message' => 'NIK terverifikasi ✓',
        'details' => [
            'provinsi' => $nama_provinsi,
            'kode_kab_kota' => $kode_kab_kota,
            'kode_kecamatan' => $kode_kecamatan,
            'tanggal_lahir' => $tgl_lahir_str,
            'jenis_kelamin' => $gender_str,
            'urutan' => $urutan
        ]
    ];
}

function verifyNISN($nisn, $conn, $exclude_player_id)
{
    if (!preg_match('/^[0-9]{10}$/', $nisn)) {
        $length = strlen(preg_replace('/[^0-9]/', '', $nisn));
        return [
            'verified' => false,
            'message' => "NISN harus terdiri dari tepat 10 digit angka (saat ini: $length digit)",
            'details' => ['step' => 'format']
        ];
    }

    if (preg_match('/^(\d)\1{9}$/', $nisn)) {
        return [
            'verified' => false,
            'message' => 'NISN tidak valid — tidak boleh semua digit sama',
            'details' => ['step' => 'pattern_same']
        ];
    }

    $sequential_asc = true;
    $sequential_desc = true;
    for ($i = 1; $i < 10; $i++) {
        if (((int)$nisn[$i] - (int)$nisn[$i - 1] + 10) % 10 !== 1) {
            $sequential_asc = false;
        }
        if (((int)$nisn[$i - 1] - (int)$nisn[$i] + 10) % 10 !== 1) {
            $sequential_desc = false;
        }
    }
    if ($sequential_asc || $sequential_desc) {
        return [
            'verified' => false,
            'message' => 'NISN tidak valid — tidak boleh angka berurutan',
            'details' => ['step' => 'pattern_sequential']
        ];
    }

    for ($len = 1; $len <= 3; $len++) {
        $chunk = substr($nisn, 0, $len);
        if ($chunk === str_repeat('0', $len)) {
            continue;
        }
        $repeated = str_repeat($chunk, (int)ceil(10 / $len));
        if (substr($repeated, 0, 10) === $nisn) {
            return [
                'verified' => false,
                'message' => 'NISN tidak valid — pola angka berulang terdeteksi',
                'details' => ['step' => 'pattern_repeated']
            ];
        }
    }

    $kode_tahun_raw = substr($nisn, 0, 3);
    $kode_tengah = substr($nisn, 3, 3);
    $nomor_urut = substr($nisn, 6, 4);

    $kode_thn_int = (int)$kode_tahun_raw;
    if ($kode_thn_int >= 900) {
        $tahun_lahir = 1000 + $kode_thn_int;
    } else {
        $tahun_lahir = 2000 + $kode_thn_int;
    }

    $tahun_sekarang = (int)date('Y');

    if ($tahun_lahir < 1990 || $tahun_lahir > $tahun_sekarang + 1) {
        return [
            'verified' => false,
            'message' => "Kode tahun lahir '$kode_tahun_raw' tidak valid (terdeteksi tahun $tahun_lahir)",
            'details' => ['step' => 'tahun_lahir']
        ];
    }

    if ($kode_tengah === '000') {
        return [
            'verified' => false,
            'message' => "Kode tengah NISN '000' tidak valid",
            'details' => ['step' => 'kode_tengah']
        ];
    }

    if ($nomor_urut === '0000') {
        return [
            'verified' => false,
            'message' => "Nomor urut siswa '0000' tidak valid",
            'details' => ['step' => 'nomor_urut']
        ];
    }

    try {
        $sql = "SELECT id, name FROM players WHERE nisn = ?";
        $params = [$nisn];
        if ($exclude_player_id > 0) {
            $sql .= " AND id != ?";
            $params[] = $exclude_player_id;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return [
                'verified' => false,
                'message' => 'NISN sudah terdaftar atas nama "' . $existing['name'] . '"',
                'details' => ['step' => 'duplicate', 'existing_player' => $existing['name']]
            ];
        }
    } catch (PDOException $e) {
        error_log("DB Error in NISN verification: " . $e->getMessage());
    }

    $usia = $tahun_sekarang - $tahun_lahir;

    if ($usia < 6) {
        $jenjang = 'PAUD/TK (Belum Masuk SD)';
    } elseif ($usia <= 12) {
        $jenjang = 'SD/MI (Sekolah Dasar)';
    } elseif ($usia <= 15) {
        $jenjang = 'SMP/MTs (Sekolah Menengah Pertama)';
    } elseif ($usia <= 18) {
        $jenjang = 'SMA/SMK/MA (Sekolah Menengah Atas)';
    } elseif ($usia <= 22) {
        $jenjang = 'Mahasiswa / Kuliah';
    } else {
        $jenjang = 'Alumni / Umum';
    }

    return [
        'verified' => true,
        'message' => 'NISN terverifikasi ✓',
        'details' => [
            'nisn' => $nisn,
            'tahun_lahir' => (string)$tahun_lahir,
            'usia' => (string)$usia . ' Tahun',
            'perkiraan_jenjang' => $jenjang,
            'kode_tengah' => $kode_tengah,
            'nomor_urut' => $nomor_urut
        ]
    ];
}
