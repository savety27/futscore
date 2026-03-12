<?php
$page_title = 'Tambah Staf Team';
$current_page = 'team_staff';
require_once '../config/database.php';
require_once '../includes/header.php';

// Initialize variables
$errors = [];
$staff_data = null;
$certificates = [];
$is_edit = isset($_GET['id']);
$staff_id = $is_edit ? (int)$_GET['id'] : 0;



// Handle edit mode - fetch staff data
if ($is_edit) {
    try {
        // Verify staff belongs to coach's team_id
        $stmt = $conn->prepare("
            SELECT * FROM team_staff 
            WHERE id = ? AND team_id = ?
        ");
        $stmt->execute([$staff_id, $team_id]);
        $staff_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff_data) {
            $_SESSION['error_message'] = 'Staff tidak ditemukan atau akses ditolak.';
            header("Location: index.php");
            exit;
        }
        
        // Fetch certificates
        $stmt = $conn->prepare("SELECT * FROM staff_certificates WHERE staff_id = ? ORDER BY created_at DESC");
        $stmt->execute([$staff_id]);
        $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error: ' .  $e->getMessage();
        header("Location: index.php");
        exit;
    }
} else {
    // Default data for add mode
    $staff_data = [
        'name' => '',
        'position' => '',
        'email' => '',
        'phone' => '',
        'birth_place' => '',
        'birth_date' => '',
        'address' => '',
        'city' => '',
        'province' => '',
        'postal_code' => '',
        'country' => 'Indonesia',
        'is_active' => 1
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Area Pelatih</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../players/css/player_form.css?v=<?php echo (int)@filemtime(__DIR__ . '/../players/css/player_form.css'); ?>">
</head>
<body>

<div class="container">
    <header class="header reveal">
        <h1>
            <i class="fas <?php echo $is_edit ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
            <?php echo $is_edit ? 'Edit Staf Team' : 'Tambah Staf Team Baru'; ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </header>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger reveal">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
    </div>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="POST" action="actions.php" enctype="multipart/form-data" id="staffForm">
            <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
            <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo $staff_id; ?>">
            <?php endif; ?>
            
            <!-- Basic Information -->
            <div class="form-section reveal d-1">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Dasar
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">Nama Lengkap</span>
                            <span class="note">Wajib</span>
                        </label>
                        <input type="text" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($staff_data['name']); ?>" 
                               placeholder="Masukkan nama lengkap" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">Jabatan</span>
                            <span class="note">Wajib</span>
                        </label>
                        <select name="position" class="form-control" required>
                            <option value="">Pilih Jabatan</option>
                            <option value="manager" <?php echo $staff_data['position'] == 'manager' ? 'selected' : ''; ?>>Manager</option>
                            <option value="headcoach" <?php echo $staff_data['position'] == 'headcoach' ? 'selected' : ''; ?>>Head Coach</option>
                            <option value="coach" <?php echo $staff_data['position'] == 'coach' ? 'selected' : ''; ?>>Assistant Coach</option>
                            <option value="goalkeeper_coach" <?php echo $staff_data['position'] == 'goalkeeper_coach' ? 'selected' : ''; ?>>Goalkeeper Coach</option>
                            <option value="medic" <?php echo $staff_data['position'] == 'medic' ? 'selected' : ''; ?>>Medic</option>
                            <option value="official" <?php echo $staff_data['position'] == 'official' ? 'selected' : ''; ?>>Official</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span>Email</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($staff_data['email']); ?>" 
                               placeholder="email@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span>No. Telepon</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($staff_data['phone']); ?>" 
                               placeholder="08123456789">
                    </div>
                </div>
            </div>

            <!-- Personal Information -->
            <div class="form-section reveal d-2">
                <h2 class="section-title">
                    <i class="fas fa-address-card"></i>
                    Data Pribadi
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <span>Tempat Lahir</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="birth_place" class="form-control" 
                               value="<?php echo htmlspecialchars($staff_data['birth_place']); ?>"
                               placeholder="Masukkan tempat lahir">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span>Tanggal Lahir</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="date" name="birth_date" class="form-control" 
                               value="<?php echo htmlspecialchars($staff_data['birth_date']); ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span>Alamat Lengkap</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="address" class="form-control" 
                               value="<?php echo htmlspecialchars($staff_data['address']); ?>"
                               placeholder="Masukkan alamat lengkap">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Kota</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="city" class="form-control" 
                               value="<?php echo htmlspecialchars($staff_data['city']); ?>"
                               placeholder="Masukkan kota">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span>Provinsi</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="province" class="form-control" 
                               value="<?php echo htmlspecialchars($staff_data['province']); ?>"
                               placeholder="Masukkan provinsi">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span>Kode Pos</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="postal_code" class="form-control" 
                               value="<?php echo htmlspecialchars($staff_data['postal_code']); ?>"
                               placeholder="Masukkan kode pos">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span>Negara</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="country" class="form-control" 
                               value="<?php echo htmlspecialchars($staff_data['country']); ?>"
                               placeholder="Masukkan negara">
                    </div>
                </div>
            </div>

            <!-- Photo Section -->
            <div class="form-section reveal d-3">
                <h2 class="section-title">
                    <i class="fas fa-camera"></i>
                    Foto Profil
                </h2>
                
                <div class="document-upload-grid" style="grid-template-columns: 1fr; max-width: 500px; margin: 0 auto;">
                    <div class="form-group hero-upload">
                        <?php if ($is_edit && !empty($staff_data['photo'])): ?>
                            <div class="file-item">
                                <img src="../../<?php echo htmlspecialchars($staff_data['photo']); ?>" alt="Current Photo">
                                <div style="flex: 1;">
                                    <div style="font-weight: 700; font-size: 0.85rem;">Foto Tersimpan</div>
                                    <div style="font-size: 0.7rem; color: var(--heritage-text-muted); line-height: 1.2;">Klik upload di bawah untuk mengganti</div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 10px; margin-bottom: 20px;">
                                <input type="checkbox" name="delete_photo" id="delete_photo" value="1" style="width: auto; height: auto;">
                                <label for="delete_photo" style="font-size: 0.9rem; color: var(--heritage-text-muted); cursor: pointer; margin: 0;">Hapus foto saat ini</label>
                            </div>
                        <?php endif; ?>
                        
                        <div class="file-upload" id="photoUpload">
                            <div class="upload-icon-wrapper">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="upload-text">
                                <span class="upload-title">Pilih Foto Profil Baru</span>
                                <span class="upload-hint">JPG, PNG (Maksimal 5MB)</span>
                            </div>
                            <div class="btn-select">Pilih File</div>
                            <input type="file" name="photo" id="photoFile" accept="image/*">
                        </div>
                        <div class="file-preview" id="photoPreview"></div>
                    </div>
                </div>
            </div>

            <!-- Certificates Section -->
            <div class="form-section reveal d-4">
                 <h2 class="section-title">
                    <i class="fas fa-certificate"></i>
                    Dokumen Sertifikat Kepelatihan
                </h2>

                <?php if ($is_edit && !empty($certificates)): ?>
                <div style="margin-bottom: 24px;">
                    <span class="form-label" style="display:block; margin-bottom:12px;">Sertifikat yang Ada</span>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
                        <?php foreach ($certificates as $cert): ?>
                        <div style="background: white; border: 1px solid var(--heritage-border); border-radius: 16px; padding: 16px; display: flex; flex-direction: column; gap: 12px;">
                            <div>
                                <div style="font-weight: 700; color: var(--heritage-text);"><?php echo htmlspecialchars($cert['certificate_name']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--heritage-text-muted); margin-top:4px;">
                                    <?php if (!empty($cert['issuing_authority'])): ?>
                                        Penerbit: <?php echo htmlspecialchars($cert['issuing_authority']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($cert['issue_date'])): ?>
                                        <br>Tanggal: <?php echo date('d M Y', strtotime($cert['issue_date'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: auto; padding-top: 12px; border-top: 1px solid var(--heritage-border);">
                                <input type="checkbox" name="delete_certificates[]" 
                                       value="<?php echo $cert['id']; ?>" 
                                       id="cert_<?php echo $cert['id']; ?>"
                                       style="width:auto; height:auto; cursor:pointer;">
                                <label for="cert_<?php echo $cert['id']; ?>" style="color:var(--heritage-crimson); font-size:0.85rem; font-weight:700; cursor:pointer; margin:0;">Hapus Sertifikat</label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <span class="form-label" style="display:block; margin-bottom:12px;"><?php echo $is_edit ? 'Tambah Sertifikat Baru' : 'Sertifikat (Opsional)'; ?></span>
                
                <div id="certificateContainer" style="display: flex; flex-direction: column; gap: 24px;">
                    <div class="certificate-upload-item" style="background: white; border: 1px solid var(--heritage-border); border-radius: 16px; padding: 24px; box-shadow: var(--soft-shadow);">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <span>Nama Sertifikat</span>
                                </label>
                                <input type="text" name="<?php echo $is_edit ? 'new_certificate_name' : 'certificate_name'; ?>[]" class="form-control" placeholder="Contoh: Lisensi C AFC">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <span>Penerbit</span>
                                </label>
                                <input type="text" name="<?php echo $is_edit ? 'new_certificate_authority' : 'certificate_authority'; ?>[]" class="form-control" placeholder="Contoh: PSSI">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <span>Tanggal Terbit</span>
                                </label>
                                <input type="date" name="<?php echo $is_edit ? 'new_certificate_date' : 'certificate_date'; ?>[]" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <span>File Bukti</span>
                                    <span class="note">Maks 10MB</span>
                                </label>
                                <div class="file-upload-container file-upload-small" onclick="this.querySelector('input[type=file]').click()">
                                    <input type="file" name="<?php echo $is_edit ? 'new_certificates' : 'certificates'; ?>[]" class="file-upload-input certificate-file-input" 
                                           accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" onclick="event.stopPropagation()">
                                    <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                    <div class="file-upload-text">Klik untuk upload sertifikat</div>
                                    <div class="file-upload-subtext" style="display:none;">JPG, PNG, PDF, DOCX | Maks: 10MB</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-secondary" onclick="addCertificateField()" style="margin-top:20px; width:100%; justify-content:center;">
                    <i class="fas fa-plus"></i> Form Sertifikat Lainnya
                </button>
            </div>
            
            <!-- Status Section -->
            <div class="form-section reveal d-5">
                <h2 class="section-title">
                    <i class="fas fa-check-circle"></i>
                    Status Keanggotaan
                </h2>
                <div class="form-group">
                    <div class="status-active-container">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($staff_data['is_active'] ?? 1) ? 'checked' : ''; ?> class="status-checkbox">
                        <div class="status-info">
                            <label for="is_active" class="status-label">Staff Aktif</label>
                            <div class="status-description">Staff aktif akan tercantum dalam kepengurusan klub resmi.</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions reveal d-6">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batalkan
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $is_edit ? 'Perbarui Data Staff' : 'Simpan Staff'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Basic file upload handling for Profile Photo
    const photoUpload = document.getElementById('photoUpload');
    const photoFile = document.getElementById('photoFile');
    const photoPreview = document.getElementById('photoPreview');

    if (photoUpload && photoFile) {
        photoUpload.addEventListener('click', () => photoFile.click());
        photoUpload.addEventListener('dragover', (e) => { e.preventDefault(); photoUpload.classList.add('dragover'); });
        photoUpload.addEventListener('dragleave', () => photoUpload.classList.remove('dragover'));
        photoUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            photoUpload.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                photoFile.files = e.dataTransfer.files;
                handlePhotoPreview(photoFile.files[0]);
            }
        });

        photoFile.addEventListener('change', (e) => {
            if (e.target.files.length) handlePhotoPreview(e.target.files[0]);
        });
    }

    function handlePhotoPreview(file) {
        if (!file.type.startsWith('image/')) {
            alert('Harap pilih file gambar untuk profil!');
            photoFile.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran file terlalu besar! Maksimal 5MB.');
            photoFile.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            photoPreview.innerHTML = `
                <div class="file-item">
                    <img src="${e.target.result}" alt="Preview">
                    <div>
                        <div style="font-weight:700; font-size: 0.85rem">${file.name}</div>
                        <div style="font-size: 0.75rem; color: var(--heritage-text-muted);">${formatFileSize(file.size)}</div>
                    </div>
                </div>`;
        };
        reader.readAsDataURL(file);
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});

function addCertificateField() {
    const container = document.getElementById('certificateContainer');
    const isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    const namePrefix = isEdit ? 'new_certificate_name' : 'certificate_name';
    const authPrefix = isEdit ? 'new_certificate_authority' : 'certificate_authority';
    const datePrefix = isEdit ? 'new_certificate_date' : 'certificate_date';
    const certPrefix = isEdit ? 'new_certificates' : 'certificates';
    
    const newField = document.createElement('div');
    newField.className = 'certificate-upload-item';
    newField.style.background = 'white';
    newField.style.border = '1px solid var(--heritage-border)';
    newField.style.borderRadius = '16px';
    newField.style.padding = '24px';
    newField.style.boxShadow = 'var(--soft-shadow)';
    
    newField.innerHTML = `
        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
            <button type="button" onclick="this.closest('.certificate-upload-item').remove()" style="background:none; border:none; color:var(--heritage-crimson); cursor:pointer; font-weight:700; font-size:0.85rem;">
                <i class="fas fa-trash"></i> Hapus Form
            </button>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label"><span>Nama Sertifikat</span></label>
                <input type="text" name="\${namePrefix}[]" class="form-control" placeholder="Contoh: Lisensi C AFC">
            </div>
            <div class="form-group">
                <label class="form-label"><span>Penerbit</span></label>
                <input type="text" name="\${authPrefix}[]" class="form-control" placeholder="Contoh: PSSI">
            </div>
            <div class="form-group">
                <label class="form-label"><span>Tanggal Terbit</span></label>
                <input type="date" name="\${datePrefix}[]" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">
                    <span>File Bukti</span>
                    <span class="note">Maks 10MB</span>
                </label>
                <div class="file-upload-container file-upload-small" onclick="this.querySelector('input[type=file]').click()">
                    <input type="file" name="\${certPrefix}[]" class="file-upload-input certificate-file-input" 
                           accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" onclick="event.stopPropagation()">
                    <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                    <div class="file-upload-text">Klik untuk upload sertifikat</div>
                    <div class="file-upload-subtext" style="display:none;">JPG, PNG, PDF, DOCX | Maks: 10MB</div>
                </div>
            </div>
        </div>
    `;
    container.appendChild(newField);
    const newInput = newField.querySelector('.certificate-file-input');
    if (newInput) {
        initCertificateUpload(newInput);
    }
}

function initCertificateUpload(fileInput) {
    const uploadContainer = fileInput.closest('.file-upload-container');
    if (!uploadContainer) return;

    const uploadText = uploadContainer.querySelector('.file-upload-text');
    const defaultText = 'Klik untuk upload sertifikat';

    function clearIndicator() {
        const indicator = uploadContainer.querySelector('.file-selected-indicator');
        if (indicator) indicator.remove();
    }

    function markSelected(fileName) {
        clearIndicator();
        uploadContainer.classList.add('has-file');
        if (uploadText) {
            uploadText.textContent = `File dipilih: ${fileName}`;
        }

        const indicator = document.createElement('div');
        indicator.className = 'file-selected-indicator';
        indicator.innerHTML = '<i class="fas fa-check"></i>';
        indicator.title = `File dipilih: ${fileName}`;
        uploadContainer.appendChild(indicator);
    }

    function resetState() {
        clearIndicator();
        uploadContainer.classList.remove('has-file');
        if (uploadText) {
            uploadText.textContent = defaultText;
        }
    }

    fileInput.addEventListener('change', function() {
        const file = this.files && this.files[0] ? this.files[0] : null;
        if (!file) {
            resetState();
            return;
        }
        markSelected(file.name);
    });
}

document.querySelectorAll('.certificate-file-input').forEach(initCertificateUpload);
</script>

<?php require_once '../includes/footer.php'; ?>
