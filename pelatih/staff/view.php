<?php
$page_title = 'Lihat Detail Staf Team';
$current_page = 'team_staff';
require_once '../config/database.php';
require_once '../includes/header.php';

// Get staff ID
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($staff_id <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch staff data with team verification
try {
    $stmt = $conn->prepare("
        SELECT ts.*, 
               t.name as team_name,
               t.alias as team_alias,
               (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) as certificate_count
        FROM team_staff ts
        LEFT JOIN teams t ON ts.team_id = t.id
        WHERE ts.id = ? AND ts.team_id = ?
    ");
    $stmt->execute([$staff_id, $team_id]);
    $staff_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff_data) {
        $_SESSION['error_message'] = 'Staff tidak ditemukan atau akses ditolak.';
        header("Location: index.php");
        exit;
    }
    
    // Calculate age
    if (!empty($staff_data['birth_date'])) {
        $birthDate = new DateTime($staff_data['birth_date']);
        $today = new DateTime();
        $staff_data['age'] = $today->diff($birthDate)->y;
    } else {
        $staff_data['age'] = '-';
    }
    
    // Fetch certificates
    $stmt = $conn->prepare("SELECT * FROM staff_certificates WHERE staff_id = ? ORDER BY created_at DESC");
    $stmt->execute([$staff_id]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Position labels
$position_labels = [
    'manager' => 'Manager',
    'headcoach' => 'Head Coach',
    'coach' => 'Assistant Coach',
    'goalkeeper_coach' => 'Goalkeeper Coach',
    'medic' => 'Medic',
    'official' => 'Official'
];
?>

<style>
    .main {
        background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
    }

    .header-card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        padding: 30px;
        margin-bottom: 30px;
    }

    .header-card .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .header-card .section-title {
        font-size: 24px;
        font-weight: 600;
        color: var(--primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-helper {
        margin-top: -6px;
        color: var(--gray);
        font-size: 14px;
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
        text-decoration: none;
        transition: all 0.3s;
        font-size: 15px;
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

    /* Match back-button style with player_form.php */
    .btn-back-model {
        padding: 14px 28px;
        border-radius: 12px;
        font-size: 16px;
        background: #f8f9fa;
        color: var(--dark);
        border: 2px solid #e1e5eb;
        box-shadow: none;
    }

    .btn-back-model:hover {
        transform: none;
        background: #e9ecef;
        border-color: #ced4da;
    }

    .info-card {
        background: white;
        border-radius: 20px;
        padding: 26px;
        margin-bottom: 24px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
        border: 1px solid #eef2f7;
    }

    .info-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 24px;
        padding-bottom: 18px;
        border-bottom: 1px solid #edf2f7;
        gap: 16px;
        flex-wrap: wrap;
    }

    .staff-main-info {
        display: flex;
        gap: 20px;
        align-items: center;
    }

    .staff-photo-large {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #ffffff;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    }

    .staff-basic-info h2 {
        font-size: 28px;
        color: var(--primary);
        margin-bottom: 8px;
        line-height: 1.2;
    }

    .staff-basic-info .position {
        font-size: 17px;
        color: var(--accent);
        font-weight: 700;
        margin-bottom: 6px;
    }

    .staff-basic-info .team {
        font-size: 14px;
        color: var(--gray);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .badge-success {
        background: rgba(46, 125, 50, 0.1);
        color: var(--success);
        border: 1px solid rgba(46, 125, 50, 0.2);
    }

    .badge-danger {
        background: rgba(211, 47, 47, 0.1);
        color: var(--danger);
        border: 1px solid rgba(211, 47, 47, 0.2);
    }

    .info-title {
        font-size: 20px;
        color: var(--primary);
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        margin-bottom: 18px;
    }

    .info-title i {
        color: var(--secondary);
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .info-item {
        padding: 16px 18px;
        background: #f8fafd;
        border: 1px solid #e8eef6;
        border-radius: 12px;
        transition: all 0.2s ease;
    }

    .info-item:hover {
        background: #eef5ff;
        transform: translateY(-1px);
    }

    .info-label {
        display: block;
        font-size: 12px;
        color: #64748b;
        margin-bottom: 6px;
        font-weight: 700;
        letter-spacing: 0.2px;
        text-transform: uppercase;
    }

    .info-value {
        font-size: 15px;
        color: #0f172a;
        font-weight: 600;
        line-height: 1.4;
        word-break: break-word;
    }

    .certificate-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 14px;
        margin-top: 14px;
    }

    .certificate-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        background: #ffffff;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
        transition: all 0.2s ease;
    }

    .certificate-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }

    .certificate-name {
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 8px;
        font-size: 15px;
        line-height: 1.35;
    }

    .certificate-meta {
        font-size: 13px;
        color: var(--gray);
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .certificate-file {
        margin-top: 12px;
    }

    .view-certificate-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        background: linear-gradient(135deg, var(--accent), #00b4d8);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        transition: all 0.2s ease;
    }

    .view-certificate-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(76, 201, 240, 0.25);
    }

    .empty-state {
        text-align: center;
        padding: 52px 20px;
        color: var(--gray);
    }

    .empty-state i {
        font-size: 54px;
        margin-bottom: 14px;
        opacity: 0.3;
    }

    @media (max-width: 768px) {
        .header-card .section-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .header-card .btn {
            width: 100%;
            justify-content: center;
        }

        .info-card {
            border-radius: 16px;
            padding: 18px;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }

        .staff-main-info {
            flex-direction: column;
            text-align: center;
            width: 100%;
        }

        .staff-photo-large {
            width: 112px;
            height: 112px;
        }

        .info-header {
            justify-content: center;
            text-align: center;
        }
    }
</style>

<div class="card header-card">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-user-tie"></i>
            <span>Detail Staff Team</span>
        </h2>
        <a href="index.php" class="btn btn-secondary btn-back-model">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
    <div class="section-helper">Profil lengkap staf, kontak, dan dokumen sertifikat.</div>
</div>

<!-- Main Info Card -->
<div class="info-card">
    <div class="info-header">
        <div class="staff-main-info">
            <?php if (!empty($staff_data['photo'])): ?>
                <img src="../../<?php echo htmlspecialchars($staff_data['photo']); ?>" 
                     alt="<?php echo htmlspecialchars($staff_data['name']); ?>" 
                     class="staff-photo-large"
                     onerror="this.src='../../images/staff/default-staff.png'">
            <?php else: ?>
                <div class="staff-photo-large" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user-tie" style="font-size: 60px; color: #999;"></i>
                </div>
            <?php endif; ?>
            
            <div class="staff-basic-info">
                <h2><?php echo htmlspecialchars($staff_data['name']); ?></h2>
                <div class="position"><?php echo $position_labels[$staff_data['position']] ?? htmlspecialchars($staff_data['position']); ?></div>
                <div class="team">
                    <?php
                    $team_alias = trim((string)($staff_data['team_alias'] ?? ''));
                    echo htmlspecialchars($staff_data['team_name'] ?? '');
                    if ($team_alias !== '') {
                        echo ' (' . htmlspecialchars($team_alias) . ')';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div>
            <?php if ($staff_data['is_active']): ?>
                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Aktif</span>
            <?php else: ?>
                <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Tidak Aktif</span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Personal Information -->
    <div class="info-title">
        <i class="fas fa-id-card"></i>
        Informasi Pribadi
    </div>
    
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Email</span>
            <div class="info-value"><?php echo !empty($staff_data['email']) ? htmlspecialchars($staff_data['email']) : '-'; ?></div>
        </div>
        
        <div class="info-item">
            <span class="info-label">No. Telepon</span>
            <div class="info-value"><?php echo !empty($staff_data['phone']) ? htmlspecialchars($staff_data['phone']) : '-'; ?></div>
        </div>
        
        <div class="info-item">
            <span class="info-label">Tempat Lahir</span>
            <div class="info-value"><?php echo !empty($staff_data['birth_place']) ? htmlspecialchars($staff_data['birth_place']) : '-'; ?></div>
        </div>
        
        <div class="info-item">
            <span class="info-label">Tanggal Lahir</span>
            <div class="info-value">
                <?php 
                if (!empty($staff_data['birth_date'])) {
                    echo date('d/m/Y', strtotime($staff_data['birth_date'])) . ' (' . $staff_data['age'] . ' tahun)';
                } else {
                    echo '-';
                }
                ?>
            </div>
        </div>
        
        <div class="info-item" style="grid-column: 1 / -1;">
            <span class="info-label">Alamat</span>
            <div class="info-value"><?php echo !empty($staff_data['address']) ? htmlspecialchars($staff_data['address']) : '-'; ?></div>
        </div>
        
        <div class="info-item">
            <span class="info-label">Kota</span>
            <div class="info-value"><?php echo !empty($staff_data['city']) ? htmlspecialchars($staff_data['city']) : '-'; ?></div>
        </div>
        
        <div class="info-item">
            <span class="info-label">Provinsi</span>
            <div class="info-value"><?php echo !empty($staff_data['province']) ? htmlspecialchars($staff_data['province']) : '-'; ?></div>
        </div>
        
        <div class="info-item">
            <span class="info-label">Kode Pos</span>
            <div class="info-value"><?php echo !empty($staff_data['postal_code']) ? htmlspecialchars($staff_data['postal_code']) : '-'; ?></div>
        </div>
        
        <div class="info-item">
            <span class="info-label">Negara</span>
            <div class="info-value"><?php echo !empty($staff_data['country']) ? htmlspecialchars($staff_data['country']) : '-'; ?></div>
        </div>
    </div>
</div>

<!-- Certificates Card -->
<div class="info-card">
    <div class="info-title">
        <i class="fas fa-certificate"></i>
        Sertifikat (<?php echo count($certificates); ?>)
    </div>
    
    <?php if (empty($certificates)): ?>
        <div class="empty-state">
            <i class="fas fa-certificate"></i>
            <h4>Belum ada sertifikat</h4>
            <p>Staff ini belum memiliki sertifikat yang terdaftar</p>
        </div>
    <?php else: ?>
        <div class="certificate-grid">
            <?php foreach ($certificates as $cert): ?>
            <div class="certificate-card">
                <div class="certificate-name">
                    <?php echo htmlspecialchars($cert['certificate_name']); ?>
                </div>
                
                <?php if (!empty($cert['issuing_authority'])): ?>
                <div class="certificate-meta">
                    <i class="fas fa-building"></i>
                    Penerbit: <?php echo htmlspecialchars($cert['issuing_authority']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($cert['issue_date'])): ?>
                <div class="certificate-meta">
                    <i class="fas fa-calendar"></i>
                    Tanggal: <?php echo date('d/m/Y', strtotime($cert['issue_date'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($cert['certificate_file'])): ?>
                <div class="certificate-file">
                    <?php 
                    $file_path = '../../uploads/certificates/' . $cert['certificate_file'];
                    $file_ext = strtolower(pathinfo($cert['certificate_file'], PATHINFO_EXTENSION));
                    ?>
                    
                    <?php if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']) && file_exists($file_path)): ?>
                        <img src="<?php echo htmlspecialchars($file_path); ?>" 
                             alt="<?php echo htmlspecialchars($cert['certificate_name']); ?>"
                             style="max-width: 100%; border-radius: 8px; margin-top: 10px;">
                    <?php endif; ?>
                    
                    <a href="<?php echo htmlspecialchars($file_path); ?>" 
                       target="_blank" 
                       class="view-certificate-btn">
                        <i class="fas fa-download"></i>
                        Lihat Sertifikat
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
