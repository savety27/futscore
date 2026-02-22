<?php
session_start();

$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$perangkat_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($perangkat_id <= 0) {
    $_SESSION['error_message'] = "ID perangkat tidak valid.";
    header("Location: perangkat.php");
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT p.*,
               (SELECT COUNT(*) FROM perangkat_licenses pl WHERE pl.perangkat_id = p.id) AS license_count
        FROM perangkat p
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$perangkat_id]);
    $perangkat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$perangkat) {
        $_SESSION['error_message'] = "Data perangkat tidak ditemukan.";
        header("Location: perangkat.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM perangkat_licenses WHERE perangkat_id = ? ORDER BY created_at DESC, id DESC");
    $stmt->execute([$perangkat_id]);
    $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching perangkat data: " . $e->getMessage());
}

$today = new DateTimeImmutable('today');
$date_of_birth = trim((string) ($perangkat['age'] ?? ''));
$age_value = '-';
if ($date_of_birth !== '') {
    $dob = DateTimeImmutable::createFromFormat('Y-m-d', $date_of_birth);
    if ($dob && $dob->format('Y-m-d') === $date_of_birth && $dob <= $today) {
        $age_value = (string) $dob->diff($today)->y;
    } elseif (is_numeric($date_of_birth)) {
        $age_value = (string) max(0, (int) $date_of_birth);
    }
}

$address_parts = [];
if (!empty($perangkat['address'])) $address_parts[] = (string) $perangkat['address'];
if (!empty($perangkat['city'])) $address_parts[] = (string) $perangkat['city'];
if (!empty($perangkat['province'])) $address_parts[] = (string) $perangkat['province'];
if (!empty($perangkat['postal_code'])) $address_parts[] = (string) $perangkat['postal_code'];
if (!empty($perangkat['country'])) $address_parts[] = (string) $perangkat['country'];
$full_address = !empty($address_parts) ? implode(', ', $address_parts) : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Perangkat </title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<style>
:root { --primary:#0f2744; --secondary:#f59e0b; --accent:#3b82f6; --success:#10b981; --danger:#ef4444; --dark:#1e293b; --gray:#64748b; --card-shadow:0 10px 15px -3px rgba(0,0,0,.05),0 4px 6px -2px rgba(0,0,0,.03); --premium-shadow:0 20px 25px -5px rgba(0,0,0,.08),0 10px 10px -5px rgba(0,0,0,.04); --transition:cubic-bezier(.4,0,.2,1) .3s; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Plus Jakarta Sans','Segoe UI',system-ui,-apple-system,sans-serif; background:linear-gradient(180deg,#eaf6ff 0%,#dff1ff 45%,#f4fbff 100%); color:var(--dark); min-height:100vh; overflow-x:hidden; }
.wrapper { display:flex; min-height:100vh; }
.main { flex:1; padding:30px; margin-left:280px; transition:var(--transition); }
.topbar,.page-header,.info-card,.stat-card { background:#fff; border-radius:20px; box-shadow:var(--card-shadow); }
.topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; padding:20px 25px; }
.greeting h1 { font-size:28px; color:var(--primary); margin-bottom:4px; }
.greeting p { color:var(--gray); font-size:14px; }
.logout-btn { background:linear-gradient(135deg,var(--danger) 0%,#B71C1C 100%); color:#fff; padding:12px 28px; border-radius:12px; text-decoration:none; font-weight:600; display:flex; align-items:center; gap:10px; }
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding:24px; }
.page-title { font-size:28px; color:var(--primary); display:flex; align-items:center; gap:12px; }
.page-title i { color:var(--secondary); }
.action-buttons { display:flex; gap:12px; }
.btn { padding:12px 22px; border-radius:12px; border:none; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:10px; cursor:pointer; }
.btn-primary { background:linear-gradient(135deg,var(--primary),var(--accent)); color:#fff; }
.btn-secondary { background:#6c757d; color:#fff; }
.stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
.stat-card { padding:22px; text-align:center; }
.stat-icon { font-size:34px; margin-bottom:8px; color:var(--primary); }
.stat-number { font-size:30px; font-weight:700; color:var(--dark); }
.stat-label { font-size:13px; color:var(--gray); margin-top:4px; }
.info-card { padding:26px; margin-bottom:24px; }
.info-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:14px; border-bottom:2px solid #f0f0f0; }
.info-title { font-size:20px; font-weight:700; color:var(--primary); display:flex; align-items:center; gap:10px; }
.profile-wrap { display:flex; gap:24px; align-items:center; flex-wrap:wrap; margin-bottom:20px; }
.photo-box { width:150px; height:150px; border-radius:50%; overflow:hidden; border:5px solid #fff; box-shadow:0 5px 20px rgba(0,0,0,.1); background:#f0f0f0; display:flex; align-items:center; justify-content:center; }
.photo-box img { width:100%; height:100%; object-fit:cover; }
.profile-meta h2 { font-size:28px; color:var(--dark); margin-bottom:8px; }
.profile-meta p { color:#666; margin-bottom:8px; }
.ktp-box { width:210px; border-radius:14px; overflow:hidden; border:1px solid #e0e0e0; background:#fff; }
.ktp-box img { width:100%; height:130px; object-fit:cover; display:block; }
.ktp-label { padding:10px 12px; font-size:12px; color:var(--gray); border-top:1px solid #f0f0f0; }
.info-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
.info-item { padding:16px; background:#f8f9fa; border-radius:14px; }
.info-label { display:block; font-size:13px; color:var(--gray); margin-bottom:6px; }
.info-value { font-size:15px; color:var(--dark); font-weight:600; }
.badge { display:inline-block; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700; }
.badge-success { background:rgba(46,125,50,.1); color:var(--success); border:1px solid rgba(46,125,50,.25); }
.badge-danger { background:rgba(211,47,47,.1); color:var(--danger); border:1px solid rgba(211,47,47,.25); }
.license-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.license-card { border:1px solid #e0e0e0; border-radius:12px; padding:16px; background:#fff; box-shadow:0 3px 10px rgba(0,0,0,.05); }
.license-card h4 { color:var(--primary); margin-bottom:8px; font-size:16px; }
.license-card p { color:#666; margin-bottom:4px; font-size:13px; }
.license-preview { margin-top:12px; text-align:center; background:#f8f9fa; border-radius:10px; padding:10px; }
.license-preview img { width:100%; max-height:170px; object-fit:contain; border-radius:8px; cursor:pointer; }
.file-box { padding:20px 10px; }
.file-box i { font-size:44px; color:#666; margin-bottom:8px; }
.file-box .name { font-size:12px; color:#666; word-break:break-word; margin-bottom:10px; }
.empty-state { text-align:center; padding:40px 20px; color:#999; }
.empty-state i { font-size:52px; color:#ddd; margin-bottom:12px; }
.modal { position:fixed; inset:0; background:rgba(0,0,0,.9); z-index:2000; display:none; align-items:center; justify-content:center; }
.modal.active { display:flex; }
.modal-content { position:relative; text-align:center; max-width:90vw; max-height:90vh; }
.modal-close { position:absolute; top:-40px; right:0; background:none; border:none; color:#fff; font-size:24px; cursor:pointer; }
.modal-image { max-width:90vw; max-height:90vh; border-radius:6px; }
.modal-caption { color:#fff; margin-top:10px; }
@media (max-width:1024px) { .main { margin-left:240px; } .stats-grid { grid-template-columns:1fr 1fr; } }
@media (max-width:768px) { .main { margin-left:0; padding:20px 15px; } .topbar,.page-header { flex-direction:column; align-items:flex-start; gap:12px; } .action-buttons { width:100%; flex-direction:column; } .btn { width:100%; justify-content:center; } .stats-grid,.info-grid { grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar">
            <div class="greeting">
                <h1>Detail Perangkat 📣</h1>
                <p>Informasi lengkap perangkat: <?php echo htmlspecialchars((string) $perangkat['name']); ?></p>
            </div>
            <div class="user-actions">
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>

        <div class="page-header">
            <div class="page-title"><i class="fas fa-user-tie"></i><span>Profil Perangkat</span></div>
            <div class="action-buttons">
                <a href="perangkat_edit.php?id=<?php echo (int) $perangkat_id; ?>" class="btn btn-primary"><i class="fas fa-edit"></i>Edit Perangkat</a>
                <a href="perangkat.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i>Kembali</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-certificate"></i></div>
                <div class="stat-number"><?php echo (int) ($perangkat['license_count'] ?? 0); ?></div>
                <div class="stat-label">Total Lisensi</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-clock"></i></div>
                <div class="stat-number"><?php echo htmlspecialchars($age_value); ?></div>
                <div class="stat-label">Usia</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-toggle-on"></i></div>
                <div class="stat-number"><?php echo (int) ($perangkat['is_active'] ?? 0) === 1 ? 'Aktif' : 'Non'; ?></div>
                <div class="stat-label">Status</div>
            </div>
        </div>

        <div class="info-card">
            <div class="info-header">
                <div class="info-title"><i class="fas fa-info-circle"></i>Informasi Perangkat</div>
                <?php if ((int) ($perangkat['is_active'] ?? 0) === 1): ?>
                    <span class="badge badge-success">AKTIF</span>
                <?php else: ?>
                    <span class="badge badge-danger">NON-AKTIF</span>
                <?php endif; ?>
            </div>

            <div class="profile-wrap">
                <div class="photo-box">
                    <?php if (!empty($perangkat['photo'])): ?>
                        <img src="../<?php echo htmlspecialchars((string) $perangkat['photo']); ?>" alt="<?php echo htmlspecialchars((string) $perangkat['name']); ?>">
                    <?php else: ?>
                        <i class="fas fa-user-tie" style="font-size:48px;color:#999;"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-meta" style="flex:1;min-width:260px;">
                    <h2><?php echo htmlspecialchars((string) $perangkat['name']); ?></h2>
                    <p><i class="fas fa-id-card"></i> No. KTP: <strong><?php echo htmlspecialchars((string) ($perangkat['no_ktp'] ?? '-')); ?></strong></p>
                    <p><i class="fas fa-envelope"></i> Email: <?php echo !empty($perangkat['email']) ? htmlspecialchars((string) $perangkat['email']) : '-'; ?></p>
                    <p><i class="fas fa-phone"></i> Telepon: <?php echo !empty($perangkat['phone']) ? htmlspecialchars((string) $perangkat['phone']) : '-'; ?></p>
                </div>
                <div class="ktp-box">
                    <?php if (!empty($perangkat['ktp_photo'])): ?>
                        <img src="../<?php echo htmlspecialchars((string) $perangkat['ktp_photo']); ?>" alt="Foto KTP" style="cursor:pointer;" onclick="viewLicenseImage('../<?php echo htmlspecialchars((string) $perangkat['ktp_photo'], ENT_QUOTES, 'UTF-8'); ?>', 'Foto KTP - <?php echo htmlspecialchars((string) $perangkat['name'], ENT_QUOTES, 'UTF-8'); ?>')">
                    <?php else: ?>
                        <div style="height:130px;display:flex;align-items:center;justify-content:center;color:#999;background:#f3f4f6;">
                            <i class="fas fa-id-card" style="font-size:32px;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="ktp-label">Foto KTP</div>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Tempat Lahir</span>
                    <div class="info-value"><?php echo !empty($perangkat['birth_place']) ? htmlspecialchars((string) $perangkat['birth_place']) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <span class="info-label">Tanggal Lahir</span>
                    <div class="info-value">
                        <?php
                        if ($date_of_birth !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_of_birth)) {
                            echo htmlspecialchars(date('d/m/Y', strtotime($date_of_birth)));
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <span class="info-label">Usia</span>
                    <div class="info-value"><?php echo htmlspecialchars($age_value); ?> tahun</div>
                </div>
                <div class="info-item">
                    <span class="info-label">Alamat</span>
                    <div class="info-value"><?php echo htmlspecialchars($full_address); ?></div>
                </div>
                <div class="info-item">
                    <span class="info-label">Dibuat Pada</span>
                    <div class="info-value"><?php echo !empty($perangkat['created_at']) ? htmlspecialchars(date('d F Y, H:i', strtotime((string) $perangkat['created_at']))) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <span class="info-label">Terakhir Diupdate</span>
                    <div class="info-value"><?php echo !empty($perangkat['updated_at']) ? htmlspecialchars(date('d F Y, H:i', strtotime((string) $perangkat['updated_at']))) : '-'; ?></div>
                </div>
            </div>
        </div>

        <div class="info-card">
            <div class="info-header">
                <div class="info-title"><i class="fas fa-certificate"></i>Sertifikat & Lisensi (<?php echo count($licenses); ?>)</div>
            </div>

            <?php if (!empty($licenses)): ?>
                <div class="license-grid">
                    <?php foreach ($licenses as $license): ?>
                        <?php
                        $license_file = basename((string) ($license['license_file'] ?? ''));
                        $license_file_url = '../uploads/perangkat/licenses/' . rawurlencode($license_file);
                        $ext = strtolower(pathinfo($license_file, PATHINFO_EXTENSION));
                        $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true);
                        ?>
                        <div class="license-card">
                            <h4><?php echo htmlspecialchars((string) ($license['license_name'] ?? 'Lisensi')); ?></h4>
                            <p><strong>Penerbit:</strong> <?php echo !empty($license['issuing_authority']) ? htmlspecialchars((string) $license['issuing_authority']) : '-'; ?></p>
                            <p><strong>Tanggal Terbit:</strong> <?php echo !empty($license['issue_date']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $license['issue_date']))) : '-'; ?></p>
                            <div class="license-preview">
                                <?php if ($is_image): ?>
                                    <img src="<?php echo htmlspecialchars($license_file_url); ?>" alt="<?php echo htmlspecialchars((string) ($license['license_name'] ?? 'Lisensi')); ?>" onclick="viewLicenseImage('<?php echo htmlspecialchars($license_file_url, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string) ($license['license_name'] ?? 'Lisensi'), ENT_QUOTES, 'UTF-8'); ?>')">
                                <?php else: ?>
                                    <div class="file-box">
                                        <i class="fas fa-file-alt"></i>
                                        <div class="name"><?php echo htmlspecialchars($license_file); ?></div>
                                        <a href="<?php echo htmlspecialchars($license_file_url); ?>" target="_blank" class="btn btn-primary" style="padding:8px 14px;font-size:13px;"><i class="fas fa-external-link-alt"></i>Buka File</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-certificate"></i>
                    <h4>Belum ada lisensi</h4>
                    <p>Perangkat ini belum memiliki sertifikat atau lisensi.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal" id="imageModal">
    <div class="modal-content">
        <button class="modal-close" type="button" onclick="closeImageModal()">&times;</button>
        <img src="" alt="Preview lisensi" class="modal-image" id="modalImage">
        <div class="modal-caption" id="modalCaption"></div>
    </div>
</div>

<script>
function viewLicenseImage(src, title) {
    const modal = document.getElementById('imageModal');
    const image = document.getElementById('modalImage');
    const caption = document.getElementById('modalCaption');
    image.src = src;
    caption.textContent = title || 'Preview Lisensi';
    modal.classList.add('active');
}

function closeImageModal() {
    const modal = document.getElementById('imageModal');
    const image = document.getElementById('modalImage');
    modal.classList.remove('active');
    image.src = '';
}

document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
