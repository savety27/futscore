<?php
$page_title = 'Lihat Detail Staf Tim';
$current_page = 'team_staff';
require_once 'config/database.php';
require_once 'includes/header.php';

// Get staff ID
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($staff_id <= 0) {
    header("Location: team_staff.php");
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
        header("Location: team_staff.php");
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
    header("Location: team_staff.php");
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

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Team Staff - Area Pelatih</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 28px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-size: 15px;
            text-decoration: none;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
        }
        
        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(10, 36, 99, 0.3);
        }
        
        .info-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        }
        
        .info-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .staff-main-info {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        .staff-photo-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .staff-basic-info h2 {
            font-size: 28px;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .staff-basic-info .position {
            font-size: 18px;
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .staff-basic-info .team {
            font-size: 15px;
            color: var(--gray);
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
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
            font-size: 22px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            margin-bottom: 25px;
        }
        
        .info-title i {
            color: var(--secondary);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .info-item {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            transition: all 0.3s;
        }
        
        .info-item:hover {
            background: #f0f7ff;
            transform: translateY(-2px);
        }
        
        .info-label {
            display: block;
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 16px;
            color: var(--dark);
            font-weight: 600;
        }
        
        .certificate-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .certificate-card {
            border: 1px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            background: white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        
        .certificate-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .certificate-name {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .certificate-meta {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .certificate-file {
            margin-top: 15px;
        }
        
        .view-certificate-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--accent), #00B4D8);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .view-certificate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 201, 240, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .staff-main-info {
                flex-direction: column;
                text-align: center;
            }
            
            .action-buttons {
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

<!-- Page Header -->
<div class="page-header">
    <h2 class="page-title">
        <i class="fas fa-user-tie"></i>
        <span>Detail Staf Tim</span>
    </h2>
    <div class="action-buttons">
        <a href="team_staff.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<!-- Main Info Card -->
<div class="info-card">
    <div class="info-header">
        <div class="staff-main-info">
            <?php if (!empty($staff_data['photo'])): ?>
                <img src="../<?php echo htmlspecialchars($staff_data['photo']); ?>" 
                     alt="<?php echo htmlspecialchars($staff_data['name']); ?>" 
                     class="staff-photo-large"
                     onerror="this.src='../images/staff/default-staff.png'">
            <?php else: ?>
                <div class="staff-photo-large" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-user-tie" style="font-size: 60px; color: #999;"></i>
                </div>
            <?php endif; ?>
            
            <div class="staff-basic-info">
                <h2><?php echo htmlspecialchars($staff_data['name']); ?></h2>
                <div class="position"><?php echo $position_labels[$staff_data['position']] ?? htmlspecialchars($staff_data['position']); ?></div>
                <div class="team"><?php echo htmlspecialchars($staff_data['team_name']); ?> (<?php echo htmlspecialchars($staff_data['team_alias']); ?>)</div>
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
                    $file_path = '../uploads/certificates/' . $cert['certificate_file'];
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

<?php require_once 'includes/footer.php'; ?>
