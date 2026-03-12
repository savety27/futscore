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

<link rel="stylesheet" href="../players/css/player_view.css?v=<?php echo (int)@filemtime(__DIR__ . '/../players/css/player_view.css'); ?>">

<div class="players-container">
    <!-- Editorial Header -->
    <header class="dashboard-hero reveal">
        <div class="hero-content">
            <span class="hero-label">Profil Staff</span>
            <h1 class="hero-title"><?php echo htmlspecialchars($staff_data['name'] ?? ''); ?></h1>
            <p class="hero-description">Profil lengkap staf, informasi personal, kontak, dan dokumen sertifikat kepelatihan.</p>
        </div>
        <div class="hero-actions">
            <a href="index.php" class="btn-premium btn-outline">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </header>

    <!-- Profile Section -->
    <div class="reveal d-1">
        <div class="section-header">
            <div class="section-title-wrap">
                <h2 class="section-title">Informasi Pribadi</h2>
                <div class="section-line"></div>
            </div>
            
            <div class="status-badge">
                <?php if ($staff_data['is_active']): ?>
                    <span class="btn-premium btn-success" style="cursor:default;"><i class="fas fa-check-circle"></i> Aktif</span>
                <?php else: ?>
                    <span class="btn-premium" style="background:#dc3545; color:white; border:none; cursor:default;"><i class="fas fa-times-circle"></i> Tidak Aktif</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-card">
            <div class="player-photo-section">
                <div class="player-photo-wrapper">
                    <?php if (!empty($staff_data['photo'])): ?>
                        <img src="../../<?php echo htmlspecialchars($staff_data['photo']); ?>" 
                             alt="<?php echo htmlspecialchars($staff_data['name']); ?>" 
                             class="player-photo"
                             onerror="showDefaultPhoto(this)">
                        <div class="default-photo" style="display:none;"><i class="fas fa-user-tie"></i></div>
                    <?php else: ?>
                        <div class="default-photo"><i class="fas fa-user-tie"></i></div>
                    <?php endif; ?>
                </div>

                <div class="player-team-info">
                    <i class="fas fa-users"></i> 
                    <?php
                    $team_alias = trim((string)($staff_data['team_alias'] ?? ''));
                    echo htmlspecialchars($staff_data['team_name'] ?? '');
                    if ($team_alias !== '') {
                        echo ' (' . htmlspecialchars($team_alias) . ')';
                    }
                    ?>
                </div>
                <div class="player-team-info" style="color: var(--heritage-text); opacity: 0.8; font-size: 0.9em; margin-top: 4px;">
                    <?php echo $position_labels[$staff_data['position']] ?? htmlspecialchars($staff_data['position']); ?>
                </div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="info-value"><?php echo !empty($staff_data['email']) ? htmlspecialchars($staff_data['email']) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-phone"></i> No. Telepon</div>
                    <div class="info-value"><?php echo !empty($staff_data['phone']) ? htmlspecialchars($staff_data['phone']) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-birthday-cake"></i> Tempat/Tanggal Lahir</div>
                    <div class="info-value">
                        <?php 
                        echo !empty($staff_data['birth_place']) ? htmlspecialchars($staff_data['birth_place']) : '-';
                        echo ' / ';
                        echo !empty($staff_data['birth_date']) ? date('d M Y', strtotime($staff_data['birth_date'])) : '-';
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user-clock"></i> Usia</div>
                    <div class="info-value"><?php echo $staff_data['age'] !== '-' ? $staff_data['age'] . ' tahun' : '-'; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-map"></i> Kota</div>
                    <div class="info-value"><?php echo !empty($staff_data['city']) ? htmlspecialchars($staff_data['city']) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-map-signs"></i> Provinsi</div>
                    <div class="info-value"><?php echo !empty($staff_data['province']) ? htmlspecialchars($staff_data['province']) : '-'; ?></div>
                </div>
                
                <div class="info-item info-item-full">
                    <div class="info-label"><i class="fas fa-map-marker-alt"></i> Alamat Lengkap</div>
                    <div class="info-value">
                        <?php 
                        $address_parts = array_filter([$staff_data['address'] ?? '', $staff_data['postal_code'] ?? '', $staff_data['country'] ?? '']);
                        echo !empty($address_parts) ? htmlspecialchars(implode(', ', $address_parts)) : '-';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Certificates Section -->
    <div class="reveal d-2">
        <div class="section-header">
            <div class="section-title-wrap">
                <h2 class="section-title">Dokumen Sertifikat (<?php echo count($certificates); ?>)</h2>
                <div class="section-line"></div>
            </div>
        </div>

        <?php if (empty($certificates)): ?>
            <div class="info-card" style="text-align: center; padding: 40px; color: var(--heritage-text-muted); background: white; border-radius: 20px; border: 1px solid var(--heritage-border);">
                <i class="fas fa-certificate" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px; display:block;"></i>
                <h4 style="font-family: var(--font-display); font-weight: 700;">Belum ada sertifikat</h4>
                <p>Staff ini belum memiliki sertifikat yang terdaftar</p>
            </div>
        <?php else: ?>
            <div class="documents-grid">
                <?php foreach ($certificates as $cert): ?>
                <div class="doc-card">
                    <div class="doc-header">
                        <div class="doc-icon"><i class="fas fa-certificate"></i></div>
                        <div class="doc-title"><?php echo htmlspecialchars($cert['certificate_name']); ?></div>
                    </div>
                    
                    <div class="info-item" style="border:none; padding-bottom:12px;">
                        <span class="info-label"><i class="fas fa-building"></i> Penerbit</span>
                        <div class="info-value" style="font-size: 0.95rem;"><?php echo !empty($cert['issuing_authority']) ? htmlspecialchars($cert['issuing_authority']) : '-'; ?></div>
                    </div>
                    
                    <div class="info-item" style="border:none; padding-bottom:12px;">
                        <span class="info-label"><i class="fas fa-calendar"></i> Tanggal Terbit</span>
                        <div class="info-value" style="font-size: 0.95rem;"><?php echo !empty($cert['issue_date']) ? date('d M Y', strtotime($cert['issue_date'])) : '-'; ?></div>
                    </div>
                    
                    <?php if (!empty($cert['certificate_file'])): ?>
                        <?php 
                        $file_path = '../../uploads/certificates/' . $cert['certificate_file'];
                        $file_ext = strtolower(pathinfo($cert['certificate_file'], PATHINFO_EXTENSION));
                        $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif']);
                        ?>
                        
                        <?php if ($is_image && file_exists($file_path)): ?>
                            <div class="doc-preview" style="margin-top: 12px; margin-bottom: 12px;">
                                <img src="<?php echo htmlspecialchars($file_path); ?>" 
                                     alt="<?php echo htmlspecialchars($cert['certificate_name']); ?>"
                                     onclick="window.open('<?php echo htmlspecialchars($file_path); ?>', '_blank')">
                            </div>
                        <?php endif; ?>
                        
                        <a href="<?php echo htmlspecialchars($file_path); ?>" 
                           target="_blank" 
                           class="btn-premium btn-outline" style="width: 100%; justify-content: center; font-size: 0.85rem; padding: 8px; margin-top: 8px;">
                            <i class="fas fa-download"></i> Lihat Berkas
                        </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showDefaultPhoto(imgElement) {
    imgElement.style.display = 'none';
    let defaultPhoto = imgElement.nextElementSibling;
    if (defaultPhoto && defaultPhoto.classList.contains('default-photo')) defaultPhoto.style.display = 'flex';
}
</script>

<?php require_once '../includes/footer.php'; ?>
