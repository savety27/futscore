<?php
/**
 * API Endpoint: Verify NIK & NISN
 * POST /api/verify_identity.php
 * 
 * Parameters:
 *   - type: 'nik' or 'nisn'
 *   - value: the number to verify
 *   - exclude_player_id: (optional) player ID to exclude from duplicate check (for edit mode)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['verified' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../admin/config/database.php';

$type = trim($_POST['type'] ?? '');
$value = trim($_POST['value'] ?? '');
$exclude_player_id = (int)($_POST['exclude_player_id'] ?? 0);

if (empty($type) || empty($value)) {
    echo json_encode(['verified' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

// ============================================================
// DAFTAR KODE PROVINSI INDONESIA (Kemendagri)
// ============================================================
$provinsi_codes = [
    '11' => 'Aceh',
    '12' => 'Sumatera Utara',
    '13' => 'Sumatera Barat',
    '14' => 'Riau',
    '15' => 'Jambi',
    '16' => 'Sumatera Selatan',
    '17' => 'Bengkulu',
    '18' => 'Lampung',
    '19' => 'Kep. Bangka Belitung',
    '21' => 'Kep. Riau',
    '31' => 'DKI Jakarta',
    '32' => 'Jawa Barat',
    '33' => 'Jawa Tengah',
    '34' => 'DI Yogyakarta',
    '35' => 'Jawa Timur',
    '36' => 'Banten',
    '51' => 'Bali',
    '52' => 'Nusa Tenggara Barat',
    '53' => 'Nusa Tenggara Timur',
    '61' => 'Kalimantan Barat',
    '62' => 'Kalimantan Tengah',
    '63' => 'Kalimantan Selatan',
    '64' => 'Kalimantan Timur',
    '65' => 'Kalimantan Utara',
    '71' => 'Sulawesi Utara',
    '72' => 'Sulawesi Tengah',
    '73' => 'Sulawesi Selatan',
    '74' => 'Sulawesi Tenggara',
    '75' => 'Gorontalo',
    '76' => 'Sulawesi Barat',
    '81' => 'Maluku',
    '82' => 'Maluku Utara',
    '91' => 'Papua',
    '92' => 'Papua Barat',
    '93' => 'Papua Selatan',
    '94' => 'Papua Tengah',
    '95' => 'Papua Pegunungan',
    '96' => 'Papua Barat Daya'
];

// ============================================================
// VERIFICATION LOGIC
// ============================================================

if ($type === 'nik') {
    $result = verifyNIK($value, $provinsi_codes, $conn, $exclude_player_id);
} elseif ($type === 'nisn') {
    $result = verifyNISN($value, $conn, $exclude_player_id);
} else {
    $result = ['verified' => false, 'message' => 'Tipe verifikasi tidak valid'];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;

// ============================================================
// FUNCTIONS
// ============================================================

function verifyNIK($nik, $provinsi_codes, $conn, $exclude_player_id) {
    // 1. Cek panjang (harus 16 digit)
    if (!preg_match('/^[0-9]{16}$/', $nik)) {
        return [
            'verified' => false,
            'message' => 'NIK harus terdiri dari tepat 16 digit angka',
            'details' => ['step' => 'format']
        ];
    }

    // 2. Parse struktur NIK
    $kode_provinsi = substr($nik, 0, 2);
    $kode_kab_kota = substr($nik, 2, 2);
    $kode_kecamatan = substr($nik, 4, 2);
    $tanggal = substr($nik, 6, 2);
    $bulan = substr($nik, 8, 2);
    $tahun = substr($nik, 10, 2);
    $urutan = substr($nik, 12, 4);

    // 3. Validasi kode provinsi
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

    // 4. Validasi kode kab/kota (01-99)
    $kab_kota_int = (int)$kode_kab_kota;
    if ($kab_kota_int < 1 || $kab_kota_int > 99) {
        return [
            'verified' => false,
            'message' => "Kode kabupaten/kota '$kode_kab_kota' tidak valid",
            'details' => ['step' => 'kab_kota']
        ];
    }

    // 5. Validasi kode kecamatan (01-99)
    $kec_int = (int)$kode_kecamatan;
    if ($kec_int < 1 || $kec_int > 99) {
        return [
            'verified' => false,
            'message' => "Kode kecamatan '$kode_kecamatan' tidak valid",
            'details' => ['step' => 'kecamatan']
        ];
    }

    // 6. Validasi tanggal lahir yang ter-encode di NIK
    // Untuk perempuan, tanggal ditambah 40
    $tgl_int = (int)$tanggal;
    $is_female = false;
    if ($tgl_int > 40) {
        $tgl_int -= 40;
        $is_female = true;
    }

    $bulan_int = (int)$bulan;

    // Validasi tanggal (1-31) dan bulan (1-12)
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

    // Validasi tanggal sesuai bulan
    $tahun_full = ((int)$tahun > 30) ? 1900 + (int)$tahun : 2000 + (int)$tahun;
    if (!checkdate($bulan_int, $tgl_int, $tahun_full)) {
        return [
            'verified' => false,
            'message' => 'Tanggal lahir yang ter-encode di NIK tidak valid (tanggal tidak sesuai bulan/tahun)',
            'details' => ['step' => 'checkdate']
        ];
    }

    // 7. Validasi urutan (0001-9999)
    $urutan_int = (int)$urutan;
    if ($urutan_int < 1) {
        return [
            'verified' => false,
            'message' => 'Nomor urut registrasi di NIK tidak valid',
            'details' => ['step' => 'urutan']
        ];
    }

    // 8. Cek duplikasi di database
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
        // Jika error database, tetap lanjut verifikasi tanpa cek duplikasi
        error_log("DB Error in NIK verification: " . $e->getMessage());
    }

    // 9. Semua validasi lolos → verified!
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

function verifyNISN($nisn, $conn, $exclude_player_id) {
    // 1. Cek panjang (harus 10 digit)
    if (!preg_match('/^[0-9]{10}$/', $nisn)) {
        $length = strlen(preg_replace('/[^0-9]/', '', $nisn));
        return [
            'verified' => false,
            'message' => "NISN harus terdiri dari tepat 10 digit angka (saat ini: $length digit)",
            'details' => ['step' => 'format']
        ];
    }

    // 2. NISN tidak boleh semua angka sama (e.g. 0000000000)
    if (preg_match('/^(\d)\1{9}$/', $nisn)) {
        return [
            'verified' => false,
            'message' => 'NISN tidak valid — tidak boleh semua digit sama',
            'details' => ['step' => 'pattern']
        ];
    }

    // 3. Cek duplikasi di database
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

    // 4. Semua validasi lolos
    return [
        'verified' => true,
        'message' => 'NISN terverifikasi ✓',
        'details' => [
            'nisn' => $nisn
        ]
    ];
}
?>
