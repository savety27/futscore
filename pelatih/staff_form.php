<?php
$page_title = isset($_GET['id']) ? 'Edit Staf Tim' : 'Tambah Staf Tim';
$current_page = 'team_staff';
require_once 'config/database.php';
require_once 'includes/header.php';

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
            header("Location: team_staff.php");
            exit;
        }
        
        // Fetch certificates
        $stmt = $conn->prepare("SELECT * FROM staff_certificates WHERE staff_id = ? ORDER BY created_at DESC");
        $stmt->execute([$staff_id]);
        $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Error: ' .  $e->getMessage();
        header("Location: team_staff.php");
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
    <style>
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-section:last-of-type {
            border-bottom: none;
        }
        
        .section-subtitle {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-grid.full-width {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
            font-size: 14px;
        }
        
        .required {
            color: var(--danger);
        }
        
        .form-input, .form-select, .form-textarea {
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .file-upload-container {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-container:hover {
            border-color: var(--primary);
            background: rgba(10, 36, 99, 0.02);
        }
        
        .file-upload-input {
            display: none;
        }
        
        .file-upload-icon {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .file-upload-text {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .file-upload-subtext {
            font-size: 13px;
            color: var(--gray);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            cursor: pointer;
            margin: 0;
            font-weight: 500;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(10, 36, 99, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            background: #5a6268;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .error-message {
            background: rgba(211, 47, 47, 0.1);
            border-left: 4px solid var(--danger);
            color: var(--danger);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .current-photo {
            max-width: 200px;
            border-radius: 10px;
            margin-top: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .certificate-list {
            margin-top: 20px;
        }
        
        .certificate-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .certificate-info {
            flex: 1;
        }
        
        .certificate-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .certificate-meta {
            font-size: 13px;
            color: var(--gray);
        }
        
        .add-certificate-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
        }
        
        .photo-preview-container {
            margin-top: 15px;
            display: none;
        }
        
        .photo-preview-container.active {
            display: block;
        }
        
        .new-photo-preview {
            max-width: 200px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            border: 3px solid var(--success);
        }
        
        .preview-label {
            font-size: 14px;
            color: var(--success);
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .file-upload-small {
            padding: 0 16px;
            height: 48px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
        }
        
        .file-upload-small .file-upload-icon {
            font-size: 20px;
            margin-bottom: 0;
        }
        
        .file-upload-small .file-upload-text {
            font-size: 14px;
            margin-bottom: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .file-upload-small .file-upload-subtext {
            display: none;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas <?php echo $is_edit ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
            <?php echo $is_edit ? 'Edit Staf Tim' : 'Tambah Staf Tim Baru'; ?>
        </h2>
        <a href="team_staff.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <?php 
        echo $_SESSION['error_message']; 
        unset($_SESSION['error_message']);
        ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="staff_actions.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
        <?php if ($is_edit): ?>
        <input type="hidden" name="id" value="<?php echo $staff_id; ?>">
        <?php endif; ?>
        
        <!-- Basic Information -->
        <div class="form-section">
            <div class="section-subtitle">
                <i class="fas fa-info-circle"></i>
                Informasi Dasar
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">
                        Nama Lengkap <span class="required">*</span>
                    </label>
                    <input type="text" name="name" class="form-input" 
                           value="<?php echo htmlspecialchars($staff_data['name']); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Jabatan <span class="required">*</span>
                    </label>
                    <select name="position" class="form-select" required>
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
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($staff_data['email']); ?>" 
                           placeholder="email@example.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label">No. Telepon</label>
                    <input type="tel" name="phone" class="form-input" 
                           value="<?php echo htmlspecialchars($staff_data['phone']); ?>" 
                           placeholder="08123456789">
                </div>
            </div>
            
            <!-- Photo Upload -->
            <div class="form-group">
                <label class="form-label">Foto Profil</label>
                <?php if ($is_edit && !empty($staff_data['photo'])): ?>
                    <img src="../<?php echo htmlspecialchars($staff_data['photo']); ?>" 
                         class="current-photo" alt="Current Photo">
                    <div class="checkbox-group" style="margin-top: 10px;">
                        <input type="checkbox" name="delete_photo" id="delete_photo" value="1">
                        <label for="delete_photo">Hapus foto saat ini</label>
                    </div>
                <?php endif; ?>
                <div class="file-upload-container" onclick="document.getElementById('photo').click()">
                    <input type="file" id="photo" name="photo" class="file-upload-input" 
                           accept="image/jpeg,image/png,image/gif">
                    <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                    <div class="file-upload-text">Klik untuk upload foto<?php echo $is_edit ? ' baru' : ''; ?></div>
                    <div class="file-upload-subtext">Format: JPG, PNG, GIF | Maks: 5MB</div>
                </div>
                
                <!-- Preview for newly selected photo -->
                <div id="photoPreviewContainer" class="photo-preview-container">
                    <div class="preview-label">
                        <i class="fas fa-image"></i>
                        Preview Foto Baru:
                    </div>
                    <img id="photoPreview" class="new-photo-preview" alt="Preview">
                </div>
            </div>
        </div>
        
        <!-- Personal Information -->
        <div class="form-section">
            <div class="section-subtitle">
                <i class="fas fa-address-card"></i>
                Data Pribadi
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Tempat Lahir</label>
                    <input type="text" name="birth_place" class="form-input" 
                           value="<?php echo htmlspecialchars($staff_data['birth_place']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="birth_date" class="form-input" 
                           value="<?php echo htmlspecialchars($staff_data['birth_date']); ?>">
                </div>
            </div>
            
            <div class="form-grid full-width">
                <div class="form-group">
                    <label class="form-label">Alamat</label>
                    <textarea name="address" class="form-textarea"><?php echo htmlspecialchars($staff_data['address']); ?></textarea>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Kota</label>
                    <input type="text" name="city" class="form-input" 
                           value="<?php echo htmlspecialchars($staff_data['city']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Provinsi</label>
                    <input type="text" name="province" class="form-input" 
                           value="<?php echo htmlspecialchars($staff_data['province']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Kode Pos</label>
                    <input type="text" name="postal_code" class="form-input" 
                           value="<?php echo htmlspecialchars($staff_data['postal_code']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Negara</label>
                    <input type="text" name="country" class="form-input" 
                           value="<?php echo htmlspecialchars($staff_data['country']); ?>">
                </div>
            </div>
        </div>
        
        <!-- Certificates Section -->
        <?php if ($is_edit && !empty($certificates)): ?>
        <div class="form-section">
            <div class="section-subtitle">
                <i class="fas fa-certificate"></i>
                Sertifikat yang Ada
            </div>
            
            <div class="certificate-list">
                <?php foreach ($certificates as $cert): ?>
                <div class="certificate-item">
                    <div class="certificate-info">
                        <div class="certificate-name"><?php echo htmlspecialchars($cert['certificate_name']); ?></div>
                        <div class="certificate-meta">
                            <?php if (!empty($cert['issuing_authority'])): ?>
                                Penerbit: <?php echo htmlspecialchars($cert['issuing_authority']); ?>
                            <?php endif; ?>
                            <?php if (!empty($cert['issue_date'])): ?>
                                | Tanggal: <?php echo date('d/m/Y', strtotime($cert['issue_date'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="delete_certificates[]" 
                               value="<?php echo $cert['id']; ?>" 
                               id="cert_<?php echo $cert['id']; ?>">
                        <label for="cert_<?php echo $cert['id']; ?>">Hapus</label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Add New Certificates -->
        <div class="form-section">
            <div class="section-subtitle">
                <i class="fas fa-certificate"></i>
                <?php echo $is_edit ? 'Tambah Sertifikat Baru' : 'Sertifikat'; ?>
            </div>
            
            <div id="certificateContainer">
                <div class="certificate-upload-item">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Nama Sertifikat</label>
                            <input type="text" name="<?php echo $is_edit ? 'new_certificate_name' : 'certificate_name'; ?>[]" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Penerbit</label>
                            <input type="text" name="<?php echo $is_edit ? 'new_certificate_authority' : 'certificate_authority'; ?>[]" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tanggal Terbit</label>
                            <input type="date" name="<?php echo $is_edit ? 'new_certificate_date' : 'certificate_date'; ?>[]" class="form-input">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">File Sertifikat</label>
                            <div class="file-upload-container file-upload-small" onclick="this.querySelector('input[type=file]').click()">
                                <input type="file" name="<?php echo $is_edit ? 'new_certificates' : 'certificates'; ?>[]" class="file-upload-input" 
                                       accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" onclick="event.stopPropagation()">
                                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                <div class="file-upload-text">Klik untuk upload sertifikat</div>
                                <div class="file-upload-subtext">JPG, PNG, PDF, DOC, DOCX | Maks: 10MB</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="button" class="add-certificate-btn" onclick="addCertificateField()">
                <i class="fas fa-plus"></i> Tambah Sertifikat Lainnya
            </button>
        </div>
        
        <!-- Status -->
        <div class="form-section">
            <div class="checkbox-group">
                <input type="checkbox" name="is_active" id="is_active" value="1" 
                       <?php echo ($staff_data['is_active'] ?? 1) ? 'checked' : ''; ?>>
                <label for="is_active">Staff Aktif</label>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <a href="team_staff.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Batal
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?php echo $is_edit ? 'Update Staff' : 'Tambah Staff'; ?>
            </button>
        </div>
    </form>
</div>

<script>
// Photo preview handler
document.getElementById('photo').addEventListener('change', function(e) {
    const file = this.files[0];
    const previewContainer = document.getElementById('photoPreviewContainer');
    const preview = document.getElementById('photoPreview');
    
    if (file) {
        // Check file size (5MB max)
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('Ukuran file terlalu besar! Maksimal 5MB');
            this.value = '';
            previewContainer.classList.remove('active');
            return;
        }
        
        // Check file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            alert('Format file tidak valid! Gunakan JPG, PNG, atau GIF');
            this.value = '';
            previewContainer.classList.remove('active');
            return;
        }
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.classList.add('active');
        };
        reader.readAsDataURL(file);
    } else {
        previewContainer.classList.remove('active');
    }
});

// Prevent file input click from bubbling to container
document.getElementById('photo').addEventListener('click', function(e) {
    e.stopPropagation();
});

function addCertificateField() {
    const container = document.getElementById('certificateContainer');
    const isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    const namePrefix = isEdit ? 'new_certificate' : 'certificate';
    const certPrefix = isEdit ? 'new_certificates' : 'certificates';
    
    const newField = document.createElement('div');
    newField.className = 'certificate-upload-item';
    newField.style.marginTop = '20px';
    newField.style.paddingTop = '20px';
    newField.style.borderTop = '2px dashed #ddd';
    newField.innerHTML = `
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Nama Sertifikat</label>
                <input type="text" name="${namePrefix}_name[]" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">Penerbit</label>
                <input type="text" name="${namePrefix}_authority[]" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">Tanggal Terbit</label>
                <input type="date" name="${namePrefix}_date[]" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label">File Sertifikat</label>
                <div class="file-upload-container file-upload-small" onclick="this.querySelector('input[type=file]').click()">
                    <input type="file" name="${certPrefix}[]" class="file-upload-input" 
                           accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" onclick="event.stopPropagation()">
                    <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                    <div class="file-upload-text">Klik untuk upload sertifikat</div>
                    <div class="file-upload-subtext">JPG, PNG, PDF, DOC, DOCX | Maks: 10MB</div>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()" style="margin-top: 10px;">
            <i class="fas fa-trash"></i> Hapus Form Ini
        </button>
    `;
    
    container.appendChild(newField);
}
</script>

<?php require_once 'includes/footer.php'; ?>
