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
require_once __DIR__ . '/verify_identity_helpers.php';

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
?>
