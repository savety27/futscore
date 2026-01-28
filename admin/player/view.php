<?php
session_start();
// TAMBAHKIN INI - Deklarasi variabel sebelum digunakan
$photo_displayed = false;
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../player.php");
    exit;
}

$player_id = (int)$_GET['id'];

try {
    // Get player data with team info
    $stmt = $conn->prepare("
        SELECT p.*, t.name as team_name, t.logo as team_logo, 
               t.coach, t.basecamp
        FROM players p 
        LEFT JOIN teams t ON p.team_id = t.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
        header("Location: ../player.php");
        exit;
    }
    
    // Calculate age
    if (!empty($player['birth_date']) && $player['birth_date'] != '0000-00-00') {
        $birth_date = new DateTime($player['birth_date']);
        $today = new DateTime();
        $age = $today->diff($birth_date);
        $player['age_years'] = $age->y;
        $player['age_months'] = $age->m;
    } else {
        $player['age_years'] = '-';
        $player['age_months'] = '-';
    }
    
    // Format gender untuk display
    function formatGenderView($gender) {
        if (empty($gender)) return '-';
        
        // Handle enum values 'L' and 'P'
        if ($gender == 'L') {
            return 'Laki-laki';
        } elseif ($gender == 'P') {
            return 'Perempuan';
        }
        
        $gender_lower = strtolower($gender);
        
        if (strpos($gender_lower, 'perempuan') !== false || $gender_lower == 'p' || $gender_lower == 'perempuan') {
            return 'Perempuan';
        } elseif (strpos($gender_lower, 'laki') !== false || $gender_lower == 'l' || $gender_lower == 'laki-laki') {
            return 'Laki-laki';
        } else {
            return ucfirst($gender);
        }
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Player - FutScore</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* CSS styles remain the same as in your original file */
:root {
    --primary: #0A2463;
    --secondary: #FFD700;
    --accent: #4CC9F0;
    --success: #2E7D32;
    --warning: #F9A826;
    --danger: #D32F2F;
    --light: #F8F9FA;
    --dark: #1A1A2E;
    --gray: #6C757D;
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
    color: var(--dark);
    min-height: 100vh;
    padding: 20px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

/* Header */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    background: linear-gradient(135deg, var(--primary), #1a365d);
    color: white;
}

.back-btn {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
}

.back-btn:hover {
    background: var(--secondary);
    color: var(--primary);
    border-color: var(--secondary);
}

.page-title {
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title i {
    color: var(--secondary);
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    font-size: 14px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #FF9800);
    color: white;
}

.btn-warning:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(249, 168, 38, 0.2);
}

/* Player Profile */
.player-profile {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 30px;
    padding: 30px;
}

.player-photo-section {
    text-align: center;
}

.player-photo {
    width: 250px;
    height: 250px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid white;
    box-shadow: var(--card-shadow);
    margin-bottom: 20px;
}

.default-photo {
    width: 250px;
    height: 250px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--secondary), #FFEC8B);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    border: 5px solid white;
    box-shadow: var(--card-shadow);
}

.default-photo i {
    font-size: 80px;
    color: var(--primary);
}

.player-name {
    font-size: 28px;
    color: var(--dark);
    margin-bottom: 10px;
}

.player-team {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.player-number {
    font-size: 24px;
    color: var(--secondary);
    font-weight: 700;
    background: var(--primary);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 20px auto;
}

/* Player Details */
.player-details {
    padding-right: 20px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.detail-group {
    margin-bottom: 15px;
}

.detail-label {
    font-weight: 600;
    color: var(--gray);
    font-size: 13px;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-value {
    font-size: 15px;
    color: var(--dark);
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

/* Skills Section */
.skills-section {
    background: #f8f9fa;
    padding: 30px;
    margin: 30px;
    border-radius: 15px;
}

.section-title {
    font-size: 20px;
    color: var(--primary);
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.skills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.skill-item {
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.skill-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
}

.skill-bar {
    height: 10px;
    background: #e0e0e0;
    border-radius: 5px;
    overflow: hidden;
    margin-bottom: 5px;
}

.skill-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    border-radius: 5px;
}

.skill-value {
    text-align: right;
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
}

/* Documents Section */
.documents-section {
    padding: 30px;
}

.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.document-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    border: 2px solid transparent;
}

.document-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

.document-icon {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 15px;
    opacity: 0.8;
}

.document-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
}

.document-status {
    display: inline-block;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 15px;
}

.status-available {
    background: rgba(46, 125, 50, 0.1);
    color: var(--success);
}

.status-unavailable {
    background: rgba(158, 158, 158, 0.1);
    color: #9E9E9E;
}

.document-preview {
    width: 100%;
    height: 150px;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 15px;
    border: 2px solid #e0e0e0;
}

.document-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
    transition: var(--transition);
}

.document-preview img:hover {
    transform: scale(1.05);
}

/* Responsive */
@media (max-width: 992px) {
    .player-profile {
        grid-template-columns: 1fr;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .action-buttons {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <a href="../player.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Kembali ke Players
        </a>
        <div class="page-title">
            <i class="fas fa-user"></i>
            <span>Player Profile</span>
        </div>
        <div class="action-buttons">
            <a href="edit.php?id=<?php echo $player['id']; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i>
                Edit Player
            </a>
            <a href="../player.php" class="btn btn-primary">
                <i class="fas fa-list"></i>
                All Players
            </a>
        </div>
    </div>

    <!-- Player Profile -->
    <div class="player-profile">
        <div class="player-photo-section">
            <?php if (!empty($player['photo'])): 
                $photo_path = '../../images/players/' . $player['photo'];
                if (file_exists($photo_path)):
                    $photo_displayed = true;
            ?>
                <img src="<?php echo $photo_path; ?>" 
                     alt="<?php echo htmlspecialchars($player['name']); ?>" 
                     class="player-photo">
            <?php 
                endif;
            endif; 
            ?>
            
            <?php if (!$photo_displayed): ?>
                <div class="default-photo">
                    <i class="fas fa-user"></i>
                </div>
            <?php endif; ?>
            
            <h2 class="player-name"><?php echo htmlspecialchars($player['name']); ?></h2>
            
            <?php if (!empty($player['team_name'])): ?>
                <div class="player-team">
                    <i class="fas fa-users"></i>
                    <?php echo htmlspecialchars($player['team_name']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($player['jersey_number'])): ?>
                <div class="player-number">#<?php echo $player['jersey_number']; ?></div>
            <?php endif; ?>
        </div>
        
        <div class="player-details">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                Personal Information
            </h3>
            
            <div class="details-grid">
                <div class="detail-group">
                    <div class="detail-label">
                        <i class="fas fa-id-card"></i>
                        NIK
                    </div>
                    <div class="detail-value">
                        <?php 
                        $nik = $player['nik'];
                        if (!empty($nik)) {
                            $masked_nik = substr($nik, 0, 3) . '*********' . substr($nik, -4);
                            echo $masked_nik;
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">
                        <i class="fas fa-id-card"></i>
                        NISN
                    </div>
                    <div class="detail-value">
                        <?php echo !empty($player['nisn']) ? htmlspecialchars($player['nisn']) : '-'; ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">
                        <i class="fas fa-birthday-cake"></i>
                        Tempat/Tanggal Lahir
                    </div>
                    <div class="detail-value">
                        <?php 
                        echo !empty($player['birth_place']) ? htmlspecialchars($player['birth_place']) : '-';
                        echo ' / ';
                        echo !empty($player['birth_date']) && $player['birth_date'] != '0000-00-00' ? 
                             date('d M Y', strtotime($player['birth_date'])) : '-';
                        ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">
                        <i class="fas fa-user-clock"></i>
                        Usia
                    </div>
                    <div class="detail-value">
                        <?php echo $player['age_years']; ?> tahun <?php echo $player['age_months']; ?> bulan
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">
                        <i class="fas fa-venus-mars"></i>
                        Jenis Kelamin
                    </div>
                    <div class="detail-value">
                        <?php echo formatGenderView($player['gender']); ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">
                        <i class="fas fa-running"></i>
                        Cabor
                    </div>
                    <div class="detail-value">
                        <?php echo !empty($player['sport_type']) ? htmlspecialchars($player['sport_type']) : '-'; ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">
                        <i class="fas fa-ruler-vertical"></i>
                        Tinggi/Berat
                    </div>
                    <div class="detail-value">
                        <?php 
                        $height = !empty($player['height']) ? $player['height'] . ' cm' : '-';
                        $weight = !empty($player['weight']) ? $player['weight'] . ' kg' : '-';
                        echo $height . ' / ' . $weight;
                        ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">
                        <i class="fas fa-envelope"></i>
                        Email
                    </div>
                    <div class="detail-value">
                        <?php echo !empty($player['email']) ? htmlspecialchars($player['email']) : '-'; ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">
                        <i class="fas fa-phone"></i>
                        Telpon
                    </div>
                    <div class="detail-value">
                        <?php echo !empty($player['phone']) ? htmlspecialchars($player['phone']) : '-'; ?>
                    </div>
                </div>
                
                <div class="detail-group">
                    <div class="detail-label">
                        <i class="fas fa-flag"></i>
                        Kewarganegaraan
                    </div>
                    <div class="detail-value">
                        <?php echo !empty($player['nationality']) ? htmlspecialchars($player['nationality']) : '-'; ?>
                    </div>
                </div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">
                    <i class="fas fa-map-marker-alt"></i>
                    Alamat
                </div>
                <div class="detail-value">
                    <?php 
                    $address_parts = [];
                    if (!empty($player['street'])) $address_parts[] = $player['street'];
                    if (!empty($player['city'])) $address_parts[] = $player['city'];
                    if (!empty($player['province'])) $address_parts[] = $player['province'];
                    if (!empty($player['postal_code'])) $address_parts[] = $player['postal_code'];
                    if (!empty($player['country'])) $address_parts[] = $player['country'];
                    
                    echo !empty($address_parts) ? htmlspecialchars(implode(', ', $address_parts)) : '-';
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Football Info -->
    <div class="skills-section">
        <h3 class="section-title">
            <i class="fas fa-futbol"></i>
            Football Information & Skills
        </h3>
        
        <div class="details-grid" style="margin-bottom: 30px;">
            <div class="detail-group">
                <div class="detail-label">
                    <i class="fas fa-football-ball"></i>
                    Kaki Dominan
                </div>
                <div class="detail-value">
                    <?php echo !empty($player['dominant_foot']) ? htmlspecialchars($player['dominant_foot']) : '-'; ?>
                </div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">
                    <i class="fas fa-crosshairs"></i>
                    Posisi
                </div>
                <div class="detail-value">
                    <?php echo !empty($player['position']) ? htmlspecialchars($player['position']) : '-'; ?>
                </div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">
                    <i class="fas fa-calendar-plus"></i>
                    Created At
                </div>
                <div class="detail-value">
                    <?php echo date('d M Y, H:i', strtotime($player['created_at'])); ?>
                </div>
            </div>
            
            <div class="detail-group">
                <div class="detail-label">
                    <i class="fas fa-calendar-check"></i>
                    Last Updated
                </div>
                <div class="detail-value">
                    <?php echo date('d M Y, H:i', strtotime($player['updated_at'])); ?>
                </div>
            </div>
        </div>
        
        <!-- Skills Display -->
        <h4 style="font-size: 18px; color: var(--primary); margin-bottom: 20px;">
            Player Skills
        </h4>
        
        <div class="skills-grid">
            <?php 
            $skills = [
                'dribbling' => 'Dribbling',
                'technique' => 'Technique',
                'speed' => 'Speed',
                'juggling' => 'Juggling',
                'shooting' => 'Shooting',
                'setplay_position' => 'Setplay Position',
                'passing' => 'Passing',
                'control' => 'Control'
            ];
            
            foreach ($skills as $key => $label): 
                $value = $player[$key] ?? 5;
                $percentage = ($value / 10) * 100;
            ?>
            <div class="skill-item">
                <div class="skill-name"><?php echo $label; ?></div>
                <div class="skill-bar">
                    <div class="skill-fill" style="width: <?php echo $percentage; ?>%"></div>
                </div>
                <div class="skill-value"><?php echo $value; ?>/10</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Documents -->
    <div class="documents-section">
        <h3 class="section-title">
            <i class="fas fa-file-alt"></i>
            Documents
        </h3>
        
        <div class="documents-grid">
            <!-- KTP -->
            <div class="document-card">
                <div class="document-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="document-name">KTP / Kartu Identitas</div>
                <div class="document-status <?php echo !empty($player['ktp_image']) ? 'status-available' : 'status-unavailable'; ?>">
                    <?php echo !empty($player['ktp_image']) ? 'Tersedia' : 'Tidak Tersedia'; ?>
                </div>
                <?php if (!empty($player['ktp_image'])): 
                    $ktp_path = '../../images/players/' . $player['ktp_image'];
                    if (file_exists($ktp_path)): ?>
                    <div class="document-preview">
                        <img src="<?php echo $ktp_path; ?>" 
                             alt="KTP" onclick="viewDocument('<?php echo $ktp_path; ?>')">
                    </div>
                <?php endif; endif; ?>
            </div>
            
            <!-- KK -->
            <div class="document-card">
                <div class="document-icon">
                    <i class="fas fa-home"></i>
                </div>
                <div class="document-name">Kartu Keluarga</div>
                <div class="document-status <?php echo !empty($player['kk_image']) ? 'status-available' : 'status-unavailable'; ?>">
                    <?php echo !empty($player['kk_image']) ? 'Tersedia' : 'Tidak Tersedia'; ?>
                </div>
                <?php if (!empty($player['kk_image'])): 
                    $kk_path = '../../images/players/' . $player['kk_image'];
                    if (file_exists($kk_path)): ?>
                    <div class="document-preview">
                        <img src="<?php echo $kk_path; ?>" 
                             alt="KK" onclick="viewDocument('<?php echo $kk_path; ?>')">
                    </div>
                <?php endif; endif; ?>
            </div>
            
            <!-- Akta Lahir -->
            <div class="document-card">
                <div class="document-icon">
                    <i class="fas fa-baby"></i>
                </div>
                <div class="document-name">Akta Lahir</div>
                <div class="document-status <?php echo !empty($player['birth_cert_image']) ? 'status-available' : 'status-unavailable'; ?>">
                    <?php echo !empty($player['birth_cert_image']) ? 'Tersedia' : 'Tidak Tersedia'; ?>
                </div>
                <?php if (!empty($player['birth_cert_image'])): 
                    $akte_path = '../../images/players/' . $player['birth_cert_image'];
                    if (file_exists($akte_path)): ?>
                    <div class="document-preview">
                        <img src="<?php echo $akte_path; ?>" 
                             alt="Akta Lahir" onclick="viewDocument('<?php echo $akte_path; ?>')">
                    </div>
                <?php endif; endif; ?>
            </div>
            
            <!-- Ijazah -->
            <div class="document-card">
                <div class="document-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="document-name">Ijazah / Raport</div>
                <div class="document-status <?php echo !empty($player['diploma_image']) ? 'status-available' : 'status-unavailable'; ?>">
                    <?php echo !empty($player['diploma_image']) ? 'Tersedia' : 'Tidak Tersedia'; ?>
                </div>
                <?php if (!empty($player['diploma_image'])): 
                    $ijazah_path = '../../images/players/' . $player['diploma_image'];
                    if (file_exists($ijazah_path)): ?>
                    <div class="document-preview">
                        <img src="<?php echo $ijazah_path; ?>" 
                             alt="Ijazah" onclick="viewDocument('<?php echo $ijazah_path; ?>')">
                    </div>
                <?php endif; endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function viewDocument(imagePath) {
    window.open(imagePath, '_blank');
}

function showDefaultPhoto(imgElement) {
    imgElement.style.display = 'none';
    let defaultPhoto = imgElement.nextElementSibling;
    if (defaultPhoto && defaultPhoto.classList.contains('default-photo')) {
        defaultPhoto.style.display = 'flex';
    }
}

// Animate skill bars on load
document.addEventListener('DOMContentLoaded', function() {
    const skillBars = document.querySelectorAll('.skill-fill');
    skillBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
    
    // Handle image errors
    document.querySelectorAll('.player-photo, .document-preview img').forEach(img => {
        img.addEventListener('error', function() {
            if (this.classList.contains('player-photo')) {
                showDefaultPhoto(this);
            }
        });
    });
});
</script>
</body>
</html>