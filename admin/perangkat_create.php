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

$temp_upload_session_key = 'perangkat_create_temp_uploads';
$temp_uploads = $_SESSION[$temp_upload_session_key] ?? [
    'photo' => null,
    'ktp_photo' => null
];
if (!is_array($temp_uploads)) {
    $temp_uploads = ['photo' => null, 'ktp_photo' => null];
}

$errors = [];
$form_data = [
    'name' => '',
    'no_ktp' => '',
    'birth_place' => '',
    'date_of_birth' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'province' => '',
    'postal_code' => '',
    'country' => 'Indonesia',
    'no_ktp_verified' => '0',
    'is_active' => 1
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'no_ktp' => trim($_POST['no_ktp'] ?? ''),
        'birth_place' => trim($_POST['birth_place'] ?? ''),
        'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'province' => trim($_POST['province'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'country' => trim($_POST['country'] ?? 'Indonesia'),
        'no_ktp_verified' => ($_POST['no_ktp_verified'] ?? '0') === '1' ? '1' : '0',
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];

    if (empty($form_data['name'])) {
        $errors['name'] = "Nama staff harus diisi";
    }

    if (empty($form_data['no_ktp'])) {
        $errors['no_ktp'] = "No. KTP harus diisi";
    } elseif (!preg_match('/^[0-9]{16}$/', $form_data['no_ktp'])) {
        $errors['no_ktp'] = "No. KTP harus 16 digit angka";
    } elseif ($form_data['no_ktp_verified'] !== '1') {
        $errors['no_ktp'] = "No. KTP harus diverifikasi terlebih dahulu";
    } else {
        $stmt = $conn->prepare("SELECT id FROM perangkat WHERE no_ktp = ? LIMIT 1");
        $stmt->execute([$form_data['no_ktp']]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $errors['no_ktp'] = "No. KTP sudah terdaftar";
        }
    }

    if ($form_data['email'] !== '' && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Format email tidak valid";
    }
    if ($form_data['phone'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $form_data['phone'])) {
        $errors['phone'] = "No. telepon harus 8-20 karakter (angka/+/-/spasi)";
    }
    if ($form_data['postal_code'] !== '' && !preg_match('/^[0-9]{5}$/', $form_data['postal_code'])) {
        $errors['postal_code'] = "Kode pos harus 5 digit angka";
    }

    $calculated_age = null;
    if (empty($form_data['date_of_birth'])) {
        $errors['date_of_birth'] = "Tanggal lahir harus diisi";
    } else {
        $dob = DateTimeImmutable::createFromFormat('Y-m-d', $form_data['date_of_birth']);
        $today = new DateTimeImmutable('today');
        if (!$dob || $dob->format('Y-m-d') !== $form_data['date_of_birth']) {
            $errors['date_of_birth'] = "Format tanggal lahir tidak valid";
        } elseif ($dob > $today) {
            $errors['date_of_birth'] = "Tanggal lahir tidak boleh melebihi hari ini";
        } else {
            $calculated_age = $dob->diff($today)->y;
            if ($calculated_age < 1 || $calculated_age > 120) {
                $errors['date_of_birth'] = "Usia dari tanggal lahir harus 1-120 tahun";
            }
        }
    }

    $photo_path = $temp_uploads['photo']['path'] ?? null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types, true)) {
            $errors['photo'] = "Format file harus JPG, PNG, atau GIF";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors['photo'] = "Ukuran file maksimal 5MB";
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'perangkat_' . time() . '_' . uniqid('', true) . '.' . $ext;
            $upload_dir = '../uploads/perangkat/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $old_photo_path = $temp_uploads['photo']['path'] ?? null;
                if (!empty($old_photo_path) && $old_photo_path !== ('uploads/perangkat/' . $filename) && file_exists('../' . $old_photo_path)) {
                    @unlink('../' . $old_photo_path);
                }
                $photo_path = 'uploads/perangkat/' . $filename;
                $temp_uploads['photo'] = [
                    'path' => $photo_path,
                    'name' => $file['name'],
                    'size' => (int) $file['size']
                ];
            } else {
                $errors['photo'] = "Gagal mengupload foto";
            }
        }
    } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $errors['photo'] = "Gagal mengupload foto";
    }

    $ktp_photo_path = $temp_uploads['ktp_photo']['path'] ?? null;
    if (!isset($_FILES['ktp_photo']) || $_FILES['ktp_photo']['error'] === UPLOAD_ERR_NO_FILE) {
        if (empty($ktp_photo_path)) {
            $errors['ktp_photo'] = "Foto KTP wajib diupload";
        }
    } elseif ($_FILES['ktp_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['ktp_photo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types, true)) {
            $errors['ktp_photo'] = "Format file harus JPG, PNG, atau GIF";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors['ktp_photo'] = "Ukuran file maksimal 5MB";
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'ktp_' . time() . '_' . uniqid('', true) . '.' . $ext;
            $upload_dir = '../uploads/perangkat/ktp/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $old_ktp_path = $temp_uploads['ktp_photo']['path'] ?? null;
                if (!empty($old_ktp_path) && $old_ktp_path !== ('uploads/perangkat/ktp/' . $filename) && file_exists('../' . $old_ktp_path)) {
                    @unlink('../' . $old_ktp_path);
                }
                $ktp_photo_path = 'uploads/perangkat/ktp/' . $filename;
                $temp_uploads['ktp_photo'] = [
                    'path' => $ktp_photo_path,
                    'name' => $file['name'],
                    'size' => (int) $file['size']
                ];
            } else {
                $errors['ktp_photo'] = "Gagal mengupload foto";
            }
        }
    } else {
        $errors['ktp_photo'] = "Gagal mengupload foto";
    }

    $licenses = [];
    if (isset($_FILES['licenses']) && is_array($_FILES['licenses']['name'])) {
        $license_names = $_POST['license_name'] ?? [];
        $authorities = $_POST['license_authority'] ?? [];
        $dates = $_POST['license_date'] ?? [];

        for ($i = 0; $i < count($_FILES['licenses']['name']); $i++) {
            if ($_FILES['licenses']['error'][$i] === UPLOAD_ERR_OK) {
                $name = $_FILES['licenses']['name'][$i];
                $size = $_FILES['licenses']['size'][$i];
                $tmp = $_FILES['licenses']['tmp_name'][$i];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];

                if (!in_array($ext, $allowed_ext, true)) {
                    $errors['licenses'] = "Format file sertifikat harus JPG, PNG, GIF, PDF, atau DOC";
                    break;
                }
                if ($size > 10 * 1024 * 1024) {
                    $errors['licenses'] = "Ukuran file sertifikat maksimal 10MB per file";
                    break;
                }

                $filename = 'license_' . time() . '_' . uniqid('', true) . '.' . $ext;
                $upload_dir = '../uploads/perangkat/licenses/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                if (move_uploaded_file($tmp, $upload_dir . $filename)) {
                    $licenses[] = [
                        'name' => trim($license_names[$i] ?? $name),
                        'file' => $filename,
                        'authority' => trim($authorities[$i] ?? ''),
                        'date' => trim($dates[$i] ?? '')
                    ];
                } else {
                    $errors['licenses'] = "Gagal mengupload file sertifikat";
                    break;
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("INSERT INTO perangkat (
                                        name, no_ktp, birth_place, age, email, phone,
                                        address, city, province, postal_code, country, photo, ktp_photo, is_active, created_at, updated_at
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $form_data['name'],
                $form_data['no_ktp'],
                $form_data['birth_place'] !== '' ? $form_data['birth_place'] : null,
                $form_data['date_of_birth'],
                $form_data['email'] !== '' ? $form_data['email'] : null,
                $form_data['phone'] !== '' ? $form_data['phone'] : null,
                $form_data['address'] !== '' ? $form_data['address'] : null,
                $form_data['city'] !== '' ? $form_data['city'] : null,
                $form_data['province'] !== '' ? $form_data['province'] : null,
                $form_data['postal_code'] !== '' ? $form_data['postal_code'] : null,
                $form_data['country'] !== '' ? $form_data['country'] : null,
                $photo_path,
                $ktp_photo_path,
                $form_data['is_active']
            ]);

            $perangkat_id = $conn->lastInsertId();
            if (!empty($licenses)) {
                $stmt = $conn->prepare("INSERT INTO perangkat_licenses (perangkat_id, license_name, license_file, issuing_authority, issue_date, created_at)
                                        VALUES (?, ?, ?, ?, ?, NOW())");
                foreach ($licenses as $license) {
                    $stmt->execute([
                        $perangkat_id,
                        $license['name'] !== '' ? $license['name'] : 'Lisensi',
                        $license['file'],
                        $license['authority'],
                        $license['date'] !== '' ? $license['date'] : null
                    ]);
                }
            }

            $conn->commit();
            unset($_SESSION[$temp_upload_session_key]);
            $_SESSION['success_message'] = "Staff berhasil ditambahkan!";
            header("Location: perangkat.php");
            exit;
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION[$temp_upload_session_key] = $temp_uploads;
            error_log('perangkat_create DB error: ' . $e->getMessage());
            if ($e->getCode() === '23000') {
                $errors['no_ktp'] = "No. KTP sudah terdaftar";
            } else {
                $errors['database'] = "Data gagal disimpan. Silakan periksa input lalu coba lagi.";
            }
        }
    } else {
        $_SESSION[$temp_upload_session_key] = $temp_uploads;
    }
}

$persisted_photo = $temp_uploads['photo'] ?? null;
$persisted_ktp_photo = $temp_uploads['ktp_photo'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tambah Perangkat 📣</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
:root { --primary:#0f2744; --secondary:#f59e0b; --accent:#3b82f6; --success:#10b981; --danger:#ef4444; --dark:#1e293b; --gray:#64748b; --card-shadow:0 10px 15px -3px rgba(0,0,0,.05),0 4px 6px -2px rgba(0,0,0,.03); }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Plus Jakarta Sans','Segoe UI',system-ui,-apple-system,sans-serif; background:linear-gradient(180deg,#eaf6ff 0%,#dff1ff 45%,#f4fbff 100%); color:var(--dark); min-height:100vh; overflow-x:hidden; }
.wrapper { display:flex; min-height:100vh; }
.main { flex:1; padding:30px; margin-left:280px; }
.topbar,.page-header,.form-container { background:#fff; border-radius:20px; box-shadow:var(--card-shadow); }
.topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; padding:20px 25px; }
.greeting h1 { font-size:28px; color:var(--primary); }
.greeting p { color:var(--gray); font-size:14px; }
.logout-btn { background:linear-gradient(135deg,var(--danger) 0%,#B71C1C 100%); color:#fff; padding:12px 28px; border-radius:12px; text-decoration:none; font-weight:600; display:flex; align-items:center; gap:10px; }
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; padding:25px; }
.page-title { font-size:28px; color:var(--primary); display:flex; align-items:center; gap:12px; }
.page-title i { color:var(--secondary); }
.btn { padding:12px 25px; border-radius:12px; border:none; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:10px; text-decoration:none; }
.btn-primary { background:linear-gradient(135deg,var(--primary),var(--accent)); color:#fff; }
.btn-secondary { background:#6c757d; color:#fff; }
.form-container { padding:30px; }
.form-section { margin-bottom:30px; padding-bottom:20px; border-bottom:2px solid #f0f0f0; }
.form-section:last-child { border-bottom:none; margin-bottom:0; padding-bottom:0; }
.section-title { font-size:20px; color:var(--primary); margin-bottom:20px; font-weight:600; display:flex; align-items:center; gap:10px; }
.form-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:20px; }
.form-group { margin-bottom:20px; }
.form-label { display:block; margin-bottom:8px; font-weight:600; color:var(--dark); font-size:14px; }
.required { color:var(--danger); }
.form-input { width:100%; padding:12px 16px; border:2px solid #e0e0e0; border-radius:12px; font-size:16px; background:#f8f9fa; }
.form-input:focus { outline:none; border-color:var(--primary); background:#fff; }
.form-textarea { width:100%; min-height:100px; resize:vertical; padding:12px 16px; border:2px solid #e0e0e0; border-radius:12px; font-size:16px; background:#f8f9fa; font-family:inherit; }
.form-textarea:focus { outline:none; border-color:var(--primary); background:#fff; }
.verify-input-wrapper { display:flex; gap:10px; align-items:stretch; }
.verify-input-wrapper .form-input { flex:1; }
.verify-btn { border:none; padding:0 14px; border-radius:12px; background:var(--primary); color:#fff; font-weight:600; cursor:pointer; white-space:nowrap; }
.verify-btn:disabled { opacity:.5; cursor:not-allowed; }
.verify-btn.verified { background:var(--success); }
.verify-feedback { font-size:12px; margin-top:6px; color:var(--gray); }
.verify-feedback.warning { color:#b45309; }
.verify-feedback.success { color:#047857; }
.verify-feedback.error { color:#b91c1c; }
.verify-details { margin-top:8px; padding:8px 10px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; font-size:12px; color:#1e40af; display:none; }
.file-upload-container { border:2px dashed #e0e0e0; border-radius:16px; padding:24px; text-align:center; background:#f8f9fa; position:relative; }
.file-upload-input { position:absolute; width:100%; height:100%; top:0; left:0; opacity:0; cursor:pointer; }
.file-upload-icon { font-size:42px; color:var(--primary); margin-bottom:12px; }
.file-upload-text { font-size:15px; color:var(--gray); }
.file-upload-subtext { font-size:13px; color:var(--gray); opacity:.8; margin-top:6px; }
.file-preview { display:none; margin-top:15px; text-align:center; }
.file-preview img { max-width:220px; max-height:180px; border-radius:10px; border:1px solid #ddd; }
.file-preview .file-info { margin-top:8px; font-size:13px; color:var(--gray); }
.digit-feedback { font-size:12px; margin-top:6px; color:var(--gray); }
.digit-feedback.warning { color:#b45309; }
.digit-feedback.success { color:#047857; }
.file-upload-container.required-missing { border-color:var(--danger); background:#fff1f2; }
.file-upload-container.uploaded { border-color:var(--success); background:#ecfdf5; border-style:solid; }
.required-file-alert { display:none; margin-top:10px; padding:10px 12px; border-radius:10px; background:#fee2e2; color:#b91c1c; font-size:13px; border-left:4px solid #ef4444; }
.required-file-alert.show { display:block; }
.error { color:var(--danger); font-size:13px; display:block; margin-top:6px; }
.alert { padding:15px 20px; border-radius:12px; margin-bottom:20px; }
.alert-danger { background:rgba(211,47,47,.1); border-left:4px solid var(--danger); color:var(--danger); }
.checkbox-group { display:flex; align-items:center; gap:10px; }
.form-actions { display:flex; justify-content:flex-end; gap:15px; margin-top:20px; padding-top:20px; border-top:2px solid #f0f0f0; }
.license-block { border:2px dashed #e0e0e0; border-radius:12px; padding:15px; margin-bottom:12px; position:relative; background:#f8f9fa; }
.remove-license { position:absolute; top:8px; right:8px; background:var(--danger); color:#fff; border:none; width:30px; height:30px; border-radius:50%; cursor:pointer; }
.license-file-preview { display:none; margin-top:12px; }
.license-file-preview.show { display:block; }
.license-file-preview img { max-width:220px; max-height:160px; border-radius:10px; border:1px solid #ddd; background:#fff; }
.license-file-meta { margin-top:8px; display:flex; align-items:center; justify-content:center; gap:8px; font-size:13px; color:var(--gray); }
.license-file-meta i { color:var(--primary); }
@media (max-width:768px) { .main{margin-left:0;padding:20px 15px;} .form-grid{grid-template-columns:1fr;} .topbar,.page-header{flex-direction:column;align-items:flex-start;gap:12px;} .verify-input-wrapper{flex-direction:column;} }
</style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar">
            <div class="greeting">
                <h1>Tambah Perangkat 📣</h1>
                <p>Tambah data perangkat baru</p>
            </div>
            <div class="user-actions">
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>

        <div class="page-header">
            <div class="page-title"><i class="fas fa-user-tie"></i><span>Tambah Perangkat</span></div>
            <a href="perangkat.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i>Kembali</a>
        </div>

        <?php if (isset($errors['database'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errors['database']); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" id="perangkatForm">
                <div class="form-section">
                    <div class="section-title"><i class="fas fa-id-card"></i>Informasi Utama</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="name">Nama <span class="required">*</span></label>
                            <input type="text" id="name" name="name" class="form-input" value="<?php echo htmlspecialchars($form_data['name']); ?>" placeholder="Masukkan nama perangkat">
                            <?php if (isset($errors['name'])): ?><span class="error"><?php echo htmlspecialchars($errors['name']); ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="no_ktp">No. KTP <span class="required">*</span></label>
                            <div class="verify-input-wrapper">
                                <input type="text" id="no_ktp" name="no_ktp" class="form-input" value="<?php echo htmlspecialchars($form_data['no_ktp']); ?>" placeholder="Masukkan nomor KTP (16 digit)" maxlength="16" pattern="[0-9]{16}" inputmode="numeric" title="No. KTP harus 16 digit angka">
                                <button type="button" class="verify-btn" id="noKtpVerifyBtn" disabled>Verifikasi</button>
                            </div>
                            <input type="hidden" name="no_ktp_verified" id="noKtpVerified" value="<?php echo htmlspecialchars($form_data['no_ktp_verified']); ?>">
                            <div class="verify-feedback" id="noKtpFeedback">No. KTP harus 16 digit angka</div>
                            <div class="verify-details" id="noKtpDetails"></div>
                            <?php if (isset($errors['no_ktp'])): ?><span class="error"><?php echo htmlspecialchars($errors['no_ktp']); ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="birth_place">Tempat Lahir</label>
                            <input type="text" id="birth_place" name="birth_place" class="form-input" value="<?php echo htmlspecialchars($form_data['birth_place']); ?>" placeholder="Kota tempat lahir">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="date_of_birth">Tanggal Lahir <span class="required">*</span></label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-input" value="<?php echo htmlspecialchars($form_data['date_of_birth']); ?>" max="<?php echo date('Y-m-d'); ?>">
                            <?php if (isset($errors['date_of_birth'])): ?><span class="error"><?php echo htmlspecialchars($errors['date_of_birth']); ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Foto Profil</label>
                            <div class="file-upload-container">
                                <input type="file" id="photo" name="photo" class="file-upload-input" accept="image/jpeg,image/png,image/gif">
                                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                <div class="file-upload-text">Klik untuk upload foto</div>
                                <div class="file-upload-subtext">Format: JPG, PNG, GIF | Maks: 5MB</div>
                            </div>
                            <?php if (isset($errors['photo'])): ?><span class="error"><?php echo htmlspecialchars($errors['photo']); ?></span><?php endif; ?>
                            <div class="file-preview" id="photoPreview" style="<?php echo !empty($persisted_photo['path']) ? 'display:block;' : ''; ?>">
                                <img id="photoPreviewImg" src="<?php echo !empty($persisted_photo['path']) ? '../' . htmlspecialchars($persisted_photo['path']) : '#'; ?>" alt="Preview Foto">
                                <div class="file-info" id="photoFileInfo">
                                    <?php
                                        if (!empty($persisted_photo['name'])) {
                                            $persisted_photo_kb = isset($persisted_photo['size']) ? (int) round(((int) $persisted_photo['size']) / 1024) : 0;
                                            echo htmlspecialchars($persisted_photo['name']) . ' (' . $persisted_photo_kb . ' KB)';
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label class="form-label">Foto KTP <span class="required">*</span></label>
                            <div class="file-upload-container <?php echo !empty($persisted_ktp_photo['path']) ? 'uploaded' : ''; ?>" id="ktpUploadContainer">
                                <input type="file" id="ktp_photo" name="ktp_photo" class="file-upload-input" accept="image/jpeg,image/png,image/gif">
                                <i class="fas fa-id-card file-upload-icon"></i>
                                <div class="file-upload-text">Klik untuk upload atau drag & drop</div>
                                <div class="file-upload-subtext">Format: JPG, PNG, GIF | Maks: 5MB</div>
                            </div>
                            <div class="required-file-alert" id="ktpPhotoAlert">
                                Foto KTP wajib diupload sebelum simpan data.
                            </div>
                            <?php if (isset($errors['ktp_photo'])): ?><span class="error"><?php echo htmlspecialchars($errors['ktp_photo']); ?></span><?php endif; ?>
                            <div class="file-preview" id="ktpPreview" style="<?php echo !empty($persisted_ktp_photo['path']) ? 'display:block;' : ''; ?>">
                                <img id="ktpPreviewImg" src="<?php echo !empty($persisted_ktp_photo['path']) ? '../' . htmlspecialchars($persisted_ktp_photo['path']) : '#'; ?>" alt="Preview KTP">
                                <div class="file-info" id="ktpFileInfo">
                                    <?php
                                        if (!empty($persisted_ktp_photo['name'])) {
                                            $persisted_ktp_kb = isset($persisted_ktp_photo['size']) ? (int) round(((int) $persisted_ktp_photo['size']) / 1024) : 0;
                                            echo htmlspecialchars($persisted_ktp_photo['name']) . ' (' . $persisted_ktp_kb . ' KB)';
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-address-card"></i>Kontak</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($form_data['email']); ?>" placeholder="email@example.com">
                            <?php if (isset($errors['email'])): ?><span class="error"><?php echo htmlspecialchars($errors['email']); ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="phone">No. Telepon</label>
                            <input type="tel" id="phone" name="phone" class="form-input" value="<?php echo htmlspecialchars($form_data['phone']); ?>" placeholder="08xxxxxxxxxx / +62xxxxxxxxxx">
                            <?php if (isset($errors['phone'])): ?><span class="error"><?php echo htmlspecialchars($errors['phone']); ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-map-marker-alt"></i>Alamat Lengkap</div>
                    <div class="form-grid">
                        <div class="form-group" style="grid-column:1/-1;">
                            <label class="form-label" for="address">Alamat</label>
                            <textarea id="address" name="address" class="form-textarea" placeholder="Alamat lengkap"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="city">Kota</label>
                            <input type="text" id="city" name="city" class="form-input" value="<?php echo htmlspecialchars($form_data['city']); ?>" placeholder="Nama kota">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="province">Provinsi</label>
                            <input type="text" id="province" name="province" class="form-input" value="<?php echo htmlspecialchars($form_data['province']); ?>" placeholder="Nama provinsi">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="postal_code">Kode Pos</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input" value="<?php echo htmlspecialchars($form_data['postal_code']); ?>" placeholder="12345" maxlength="5" inputmode="numeric">
                            <?php if (isset($errors['postal_code'])): ?><span class="error"><?php echo htmlspecialchars($errors['postal_code']); ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="country">Negara</label>
                            <input type="text" id="country" name="country" class="form-input" value="<?php echo htmlspecialchars($form_data['country']); ?>" placeholder="Indonesia">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-certificate"></i>Sertifikat & Lisensi</div>
                    <div id="licensesContainer"></div>
                    <?php if (isset($errors['licenses'])): ?><span class="error"><?php echo htmlspecialchars($errors['licenses']); ?></span><?php endif; ?>
                    <button type="button" class="btn btn-primary" id="addLicenseBtn"><i class="fas fa-plus"></i>Tambah Lisensi</button>
                </div>

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-toggle-on"></i>Status Staff</div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $form_data['is_active'] ? 'checked' : ''; ?>>
                        <label for="is_active">Staff Aktif</label>
                    </div>
                    <small style="color:#666;">Staff aktif akan tampil dalam sistem</small>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary"><i class="fas fa-redo"></i>Reset</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i>Simpan Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    function bindPreview(inputId, previewId, imgId, infoId) {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        const img = document.getElementById(imgId);
        const info = document.getElementById(infoId);

        input.addEventListener('change', function() {
            if (!this.files.length) {
                return;
            }
            const file = this.files[0];
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                toastr.error('Format file harus berupa gambar (JPEG, PNG, atau GIF)');
                this.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                toastr.error('Ukuran file maksimal 5MB');
                this.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                info.textContent = `${file.name} (${Math.round(file.size / 1024)} KB)`;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
    }

    bindPreview('photo', 'photoPreview', 'photoPreviewImg', 'photoFileInfo');
    bindPreview('ktp_photo', 'ktpPreview', 'ktpPreviewImg', 'ktpFileInfo');

    const ktpPhotoInput = document.getElementById('ktp_photo');
    const ktpUploadContainer = document.getElementById('ktpUploadContainer');
    const ktpPhotoAlert = document.getElementById('ktpPhotoAlert');
    const hasPersistedKtpPhoto = <?php echo !empty($persisted_ktp_photo['path']) ? 'true' : 'false'; ?>;
    if (ktpPhotoInput && ktpUploadContainer && ktpPhotoAlert) {
        ktpPhotoInput.addEventListener('change', function() {
            if (this.files.length) {
                ktpUploadContainer.classList.remove('required-missing');
                ktpUploadContainer.classList.add('uploaded');
                ktpPhotoAlert.classList.remove('show');
            } else if (!hasPersistedKtpPhoto) {
                ktpUploadContainer.classList.remove('uploaded');
            }
        });
    }

    const noKtpInput = document.getElementById('no_ktp');
    const noKtpFeedback = document.getElementById('noKtpFeedback');
    const noKtpVerifyBtn = document.getElementById('noKtpVerifyBtn');
    const noKtpVerified = document.getElementById('noKtpVerified');
    const noKtpDetails = document.getElementById('noKtpDetails');
    const isNoKtpPreVerified = <?php echo ($form_data['no_ktp_verified'] ?? '0') === '1' ? 'true' : 'false'; ?>;
    if (noKtpInput) {
        noKtpInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 16);
            const len = e.target.value.length;

            if (!noKtpFeedback || !noKtpVerifyBtn || !noKtpVerified) {
                return;
            }

            noKtpVerified.value = '0';
            noKtpVerifyBtn.classList.remove('verified');
            if (noKtpDetails) {
                noKtpDetails.style.display = 'none';
                noKtpDetails.innerHTML = '';
            }

            if (len === 16) {
                noKtpFeedback.textContent = '16 digit terisi (16/16) - klik verifikasi';
                noKtpFeedback.className = 'verify-feedback warning';
                noKtpVerifyBtn.disabled = false;
            } else if (len > 0) {
                noKtpFeedback.textContent = `Kurang ${16 - len} digit (${len}/16)`;
                noKtpFeedback.className = 'verify-feedback warning';
                noKtpVerifyBtn.disabled = true;
            } else {
                noKtpFeedback.textContent = 'No. KTP harus 16 digit angka';
                noKtpFeedback.className = 'verify-feedback';
                noKtpVerifyBtn.disabled = true;
            }
        });

        noKtpInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(String.fromCharCode(e.which || e.keyCode))) {
                e.preventDefault();
            }
        });
        noKtpInput.addEventListener('paste', function(e) {
            if (!/^[0-9]+$/.test((e.clipboardData || window.clipboardData).getData('text'))) {
                e.preventDefault();
            }
        });

        noKtpInput.dispatchEvent(new Event('input'));
        if (isNoKtpPreVerified && noKtpInput.value.trim().length === 16 && noKtpVerifyBtn && noKtpFeedback && noKtpVerified) {
            noKtpVerified.value = '1';
            noKtpFeedback.textContent = 'No. KTP sudah terverifikasi';
            noKtpFeedback.className = 'verify-feedback success';
            noKtpVerifyBtn.disabled = true;
            noKtpVerifyBtn.classList.add('verified');
            noKtpVerifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Terverifikasi';
        }
    }

    function verifyNoKtp() {
        if (!noKtpInput || !noKtpVerifyBtn || !noKtpFeedback || !noKtpVerified) {
            return;
        }

        const value = noKtpInput.value.trim();
        if (!/^[0-9]{16}$/.test(value)) {
            return;
        }

        noKtpVerifyBtn.disabled = true;
        noKtpVerifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memverifikasi...';
        noKtpFeedback.textContent = 'Sedang memverifikasi No. KTP...';
        noKtpFeedback.className = 'verify-feedback';

        const payload = new FormData();
        payload.append('type', 'nik');
        payload.append('value', value);

        fetch('../api/verify_identity.php', { method: 'POST', body: payload })
            .then(response => response.json())
            .then(data => {
                if (data && data.verified) {
                    noKtpVerified.value = '1';
                    noKtpFeedback.textContent = 'No. KTP terverifikasi';
                    noKtpFeedback.className = 'verify-feedback success';
                    noKtpVerifyBtn.classList.add('verified');
                    noKtpVerifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Terverifikasi';

                    if (noKtpDetails && data.details) {
                        const details = [];
                        if (data.details.provinsi) details.push(`Provinsi: ${data.details.provinsi}`);
                        if (data.details.tanggal_lahir) details.push(`Tgl Lahir: ${data.details.tanggal_lahir}`);
                        if (data.details.jenis_kelamin) details.push(`Jenis Kelamin: ${data.details.jenis_kelamin}`);
                        if (details.length) {
                            noKtpDetails.innerHTML = details.join('<br>');
                            noKtpDetails.style.display = 'block';
                        }
                    }
                } else {
                    noKtpVerified.value = '0';
                    noKtpFeedback.textContent = (data && data.message) ? data.message : 'No. KTP tidak valid';
                    noKtpFeedback.className = 'verify-feedback error';
                    noKtpVerifyBtn.classList.remove('verified');
                    noKtpVerifyBtn.disabled = false;
                    noKtpVerifyBtn.textContent = 'Verifikasi';
                }
            })
            .catch(() => {
                noKtpVerified.value = '0';
                noKtpFeedback.textContent = 'Gagal menghubungi server verifikasi';
                noKtpFeedback.className = 'verify-feedback error';
                noKtpVerifyBtn.classList.remove('verified');
                noKtpVerifyBtn.disabled = false;
                noKtpVerifyBtn.textContent = 'Verifikasi';
            });
    }

    if (noKtpVerifyBtn) {
        noKtpVerifyBtn.addEventListener('click', verifyNoKtp);
    }

    const licensesContainer = document.getElementById('licensesContainer');
    const addLicenseBtn = document.getElementById('addLicenseBtn');

    function bindLicenseUpload(block) {
        const container = block.querySelector('.license-upload-container');
        const input = block.querySelector('.license-file-input');
        const label = block.querySelector('.license-file-label');
        const preview = block.querySelector('.license-file-preview');
        const previewImage = block.querySelector('.license-preview-image');
        const previewMeta = block.querySelector('.license-file-meta');
        if (!container || !input || !label || !preview || !previewImage || !previewMeta) {
            return;
        }
        const defaultLabel = 'Format: JPG, PNG, GIF, PDF, DOC | Maks: 10MB';

        function resetPreview() {
            preview.classList.remove('show');
            previewImage.style.display = 'none';
            previewImage.src = '';
            previewMeta.innerHTML = '';
        }

        container.addEventListener('dragover', function(e) {
            e.preventDefault();
            container.style.borderColor = 'var(--primary)';
        });

        container.addEventListener('dragleave', function() {
            container.style.borderColor = '#e0e0e0';
        });

        container.addEventListener('drop', function(e) {
            e.preventDefault();
            container.style.borderColor = '#e0e0e0';
            if (!e.dataTransfer.files.length) {
                return;
            }
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change'));
        });

        input.addEventListener('change', function() {
            if (!this.files.length) {
                label.textContent = defaultLabel;
                container.classList.remove('uploaded');
                resetPreview();
                return;
            }

            const file = this.files[0];
            const ext = file.name.split('.').pop().toLowerCase();
            const sizeLabel = `${Math.round(file.size / 1024)} KB`;
            const allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
            if (!allowedExt.includes(ext)) {
                toastr.error('Format file sertifikat harus JPG, PNG, GIF, PDF, atau DOC');
                this.value = '';
                label.textContent = defaultLabel;
                container.classList.remove('uploaded');
                resetPreview();
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                toastr.error('Ukuran file sertifikat maksimal 10MB');
                this.value = '';
                label.textContent = defaultLabel;
                container.classList.remove('uploaded');
                resetPreview();
                return;
            }

            label.textContent = `${file.name} (${sizeLabel})`;
            container.classList.add('uploaded');

            if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'inline-block';
                    previewMeta.innerHTML = `<i class="fas fa-image"></i><span>${file.name} (${sizeLabel})</span>`;
                    preview.classList.add('show');
                };
                reader.readAsDataURL(file);
            } else {
                const iconClass = ext === 'pdf' ? 'fa-file-pdf' : 'fa-file-word';
                previewImage.style.display = 'none';
                previewMeta.innerHTML = `<i class="fas ${iconClass}"></i><span>${file.name} (${sizeLabel})</span>`;
                preview.classList.add('show');
            }
        });
    }

    function addLicenseBlock() {
        const block = document.createElement('div');
        block.className = 'license-block';
        block.innerHTML = `
            <button type="button" class="remove-license"><i class="fas fa-times"></i></button>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nama Sertifikat/Lisensi</label>
                    <input type="text" name="license_name[]" class="form-input" placeholder="Contoh: Lisensi Kepelatihan C AFC">
                </div>
                <div class="form-group">
                    <label class="form-label">Penerbit/Lembaga</label>
                    <input type="text" name="license_authority[]" class="form-input" placeholder="Contoh: AFC (Asian Football Confederation)">
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Terbit</label>
                    <input type="date" name="license_date[]" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">File Sertifikat <span class="required"> </span></label>
                    <div class="file-upload-container license-upload-container">
                        <input type="file" name="licenses[]" class="file-upload-input license-file-input" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                        <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                        <div class="file-upload-text">Klik untuk upload atau drag & drop</div>
                        <div class="file-upload-subtext license-file-label">Format: JPG, PNG, GIF, PDF, DOC | Maks: 10MB</div>
                    </div>
                    <div class="license-file-preview">
                        <img src="" alt="Preview sertifikat" class="license-preview-image">
                        <div class="license-file-meta"></div>
                    </div>
                </div>
            </div>
        `;
        block.querySelector('.remove-license').addEventListener('click', function() {
            block.remove();
        });
        bindLicenseUpload(block);
        licensesContainer.appendChild(block);
    }

    addLicenseBtn.addEventListener('click', addLicenseBlock);
    addLicenseBlock();

    document.getElementById('perangkatForm').addEventListener('submit', function(e) {
        let valid = true;
        const errorMessages = [];

        const name = document.getElementById('name').value.trim();
        const noKtp = document.getElementById('no_ktp').value.trim();
        const dateOfBirth = document.getElementById('date_of_birth').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const postalCode = document.getElementById('postal_code').value.trim();
        const ktpPhoto = document.getElementById('ktp_photo');

        if (!name) {
            errorMessages.push('Nama staff harus diisi');
            valid = false;
        }
        if (!noKtp) {
            errorMessages.push('No. KTP harus diisi');
            valid = false;
        } else if (!/^[0-9]{16}$/.test(noKtp)) {
            errorMessages.push('No. KTP harus 16 digit angka');
            valid = false;
        } else if (!noKtpVerified || noKtpVerified.value !== '1') {
            errorMessages.push('No. KTP belum diverifikasi');
            valid = false;
        }
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errorMessages.push('Format email tidak valid');
            valid = false;
        }
        if (phone && !/^[0-9+\-\s]{8,20}$/.test(phone)) {
            errorMessages.push('No. telepon harus 8-20 karakter');
            valid = false;
        }
        if (postalCode && !/^[0-9]{5}$/.test(postalCode)) {
            errorMessages.push('Kode pos harus 5 digit angka');
            valid = false;
        }
        if (!dateOfBirth) {
            errorMessages.push('Tanggal lahir harus diisi');
            valid = false;
        }
        if (!ktpPhoto.files.length && !hasPersistedKtpPhoto) {
            errorMessages.push('Foto KTP wajib diupload');
            valid = false;
            if (ktpUploadContainer && ktpPhotoAlert) {
                ktpUploadContainer.classList.add('required-missing');
                ktpUploadContainer.classList.remove('uploaded');
                ktpPhotoAlert.classList.add('show');
                ktpUploadContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else if (ktpUploadContainer && ktpPhotoAlert) {
            ktpUploadContainer.classList.remove('required-missing');
            ktpPhotoAlert.classList.remove('show');
        }

        document.querySelectorAll('.license-block').forEach(function(block) {
            const title = block.querySelector('input[name="license_name[]"]').value.trim();
            const file = block.querySelector('input[name="licenses[]"]');
            if (title && !file.files.length) {
                errorMessages.push(`Sertifikat "${title}" harus memiliki file`);
                valid = false;
            }
        });

        if (!valid) {
            e.preventDefault();
            toastr.error(errorMessages.join('<br>'));
        }
    });
});
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
