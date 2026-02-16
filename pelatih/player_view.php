<?php
$page_title = 'Detail Player';
$current_page = 'players';
require_once 'config/database.php';
require_once 'includes/header.php';

$team_id = $_SESSION['team_id'] ?? 0;

if (!isset($_GET['id']) || empty($_GET['id']) || !$team_id) {
    header('Location: players.php');
    exit;
}

$player_id = (int)$_GET['id'];

try {
    $stmt = $conn->prepare("
        SELECT p.*, t.name as team_name, t.logo as team_logo, 
               t.coach, t.basecamp
        FROM players p
        LEFT JOIN teams t ON p.team_id = t.id
        WHERE p.id = ? AND p.team_id = ?
    ");
    $stmt->execute([$player_id, $team_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        header('Location: players.php');
        exit;
    }

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
    
    function formatGenderView($gender) {
        if (empty($gender)) return '-';
        if ($gender == 'L') return 'Laki-laki';
        if ($gender == 'P') return 'Perempuan';
        $gender_lower = strtolower($gender);
        if (strpos($gender_lower, 'perempuan') !== false || $gender_lower == 'p') return 'Perempuan';
        if (strpos($gender_lower, 'laki') !== false || $gender_lower == 'l') return 'Laki-laki';
        return ucfirst($gender);
    }

} catch (Exception $e) {
    echo "<div class='card'><div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    require_once 'includes/footer.php';
    exit;
}
?>

<style>
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

.main {
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
}

.player-view {
    margin-top: 20px;
}

/* Container */
.player-view .container {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

/* Header */
.player-view .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    background: linear-gradient(135deg, var(--primary), #1a365d);
    color: white;
}

.player-view .back-btn {
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

.player-view .back-btn:hover {
    background: var(--secondary);
    color: var(--primary);
    border-color: var(--secondary);
}

.player-view .page-title {
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.player-view .page-title i {
    color: var(--secondary);
}

.player-view .action-buttons {
    display: flex;
    gap: 10px;
}

.player-view .btn {
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

.player-view .btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
}

.player-view .btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
}

.player-view .btn-warning {
    background: linear-gradient(135deg, var(--warning), #FF9800);
    color: white;
}

.player-view .btn-warning:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(249, 168, 38, 0.2);
}

.player-view .btn-success {
    background: linear-gradient(135deg, var(--success), #1B5E20);
    color: white;
}

.player-view .btn-success:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.2);
}

/* Player Profile */
.player-view .player-profile {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 30px;
    padding: 30px;
}

.player-view .player-photo-section {
    text-align: center;
}

.player-view .player-photo {
    width: 250px;
    height: 250px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid white;
    box-shadow: var(--card-shadow);
    margin-bottom: 20px;
}

.player-view .default-photo {
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

.player-view .default-photo i {
    font-size: 80px;
    color: var(--primary);
}

.player-view .player-name {
    font-size: 28px;
    color: var(--dark);
    margin-bottom: 10px;
}

.player-view .player-team {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.player-view .player-number {
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
.player-view .player-details {
    padding-right: 20px;
}

.player-view .details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.player-view .detail-group {
    margin-bottom: 15px;
}

.player-view .detail-label {
    font-weight: 600;
    color: var(--gray);
    font-size: 13px;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.player-view .detail-value {
    font-size: 15px;
    color: var(--dark);
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

/* Skills Section */
.player-view .skills-section {
    background: #f8f9fa;
    padding: 30px;
    margin: 30px;
    border-radius: 15px;
}

.player-view .section-title {
    font-size: 20px;
    color: var(--primary);
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.player-view .skills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.player-view .skill-item {
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.player-view .skill-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
}

.player-view .skill-bar {
    height: 10px;
    background: #e0e0e0;
    border-radius: 5px;
    overflow: hidden;
    margin-bottom: 5px;
}

.player-view .skill-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    border-radius: 5px;
    transition: width 1s ease-in-out;
}

.player-view .skill-value {
    text-align: right;
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
}

/* Documents Section */
.player-view .documents-section {
    padding: 30px;
}

.player-view .documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.player-view .document-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    border: 2px solid transparent;
}

.player-view .document-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

.player-view .document-icon {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 15px;
    opacity: 0.8;
}

.player-view .document-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
}

.player-view .document-status {
    display: inline-block;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 15px;
}

.player-view .status-available {
    background: rgba(46, 125, 50, 0.1);
    color: var(--success);
}

.player-view .status-unavailable {
    background: rgba(158, 158, 158, 0.1);
    color: #9E9E9E;
}

.player-view .document-preview {
    width: 100%;
    height: 150px;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 15px;
    border: 2px solid #e0e0e0;
}

.player-view .document-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
    transition: var(--transition);
}

.player-view .document-preview img:hover {
    transform: scale(1.05);
}

/* ===== MOBILE RESPONSIVE DESIGN ===== */
@media screen and (max-width: 768px) {
    /* Main Content: Full width on mobile */
    .player-view .main {
        margin-left: 0;
        padding: 20px 15px;
        width: 100%;
        max-width: 100vw;
    }

    /* Header */
    .player-view .header {
        flex-direction: column;
        gap: 15px;
        align-items: center;
        text-align: center;
        padding: 20px;
    }
    
    .player-view .page-title {
        order: -1; /* Title first */
    }
    
    .player-view .action-buttons {
        flex-wrap: wrap;
        justify-content: center;
        width: 100%;
    }
    
    .player-view .btn {
        flex: 1;
        justify-content: center;
        min-width: 140px;
    }

    /* Player Profile Layout */
    .player-view .player-profile {
        grid-template-columns: 1fr;
        padding: 20px;
        gap: 30px;
    }
    
    .player-view .details-grid {
        grid-template-columns: 1fr;
    }
    
    .player-view .skills-grid {
        grid-template-columns: 1fr;
    }
    
    .player-view .documents-grid {
        grid-template-columns: 1fr;
    }
}

@media screen and (max-width: 480px) {
    .player-view .section-title {
        font-size: 18px;
    }
    
    .player-view .player-photo,
    .player-view .default-photo {
        width: 180px;
        height: 180px;
    }
    
    .player-view .player-name {
        font-size: 22px;
    }
}
</style>

<div class="player-view">
    <div class="container">
            <!-- Header -->
            <div class="header">
                <a href="players.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Players
                </a>
                <div class="page-title">
                    <i class="fas fa-user"></i>
                    <span>Player Profile</span>
                </div>
                <div class="action-buttons">
                    <a href="player_form.php?id=<?php echo $player['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i>
                        Edit Player
                    </a>
                    <button type="button" class="btn btn-success" id="printPlayerBtn">
                        <i class="fas fa-camera"></i>
                        Print Player
                    </button>
                    <a href="players.php" class="btn btn-primary">
                        <i class="fas fa-list"></i>
                        All Players
                    </a>
                </div>
            </div>

            <!-- Player Profile -->
            <div class="player-profile">
                <div class="player-photo-section">
                    <?php 
                    $photo_displayed = false;
                    if (!empty($player['photo'])): 
                        $photo_path = 'images/players/' . $player['photo'];
                        $possible_paths = [
                            $photo_path,
                            '../' . $photo_path,
                            '../../' . $photo_path,
                            '../../../' . $photo_path
                        ];
                        
                        $found_photo = false;
                        foreach ($possible_paths as $path) {
                            if (file_exists($path)) {
                                $found_photo = $path;
                                break;
                            }
                        }
                        
                        if ($found_photo):
                            $photo_displayed = true;
                    ?>
                        <img src="<?php echo $found_photo; ?>" 
                             alt="<?php echo htmlspecialchars($player['name'] ?? ''); ?>" 
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
                    
                    <h2 class="player-name"><?php echo htmlspecialchars($player['name'] ?? ''); ?></h2>
                    
                    <?php if (!empty($player['team_name'])): ?>
                        <div class="player-team">
                            <i class="fas fa-users"></i>
                            <?php echo htmlspecialchars($player['team_name'] ?? ''); ?>
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
                                Event
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
                            $ktp_path = 'images/players/' . $player['ktp_image'];
                            $possible_paths = [
                                $ktp_path,
                                '../' . $ktp_path,
                                '../../' . $ktp_path,
                                '../../../' . $ktp_path
                            ];
                            
                            $found_ktp = false;
                            foreach ($possible_paths as $path) {
                                if (file_exists($path)) {
                                    $found_ktp = $path;
                                    break;
                                }
                            }
                            
                            if ($found_ktp): ?>
                            <div class="document-preview">
                                <img src="<?php echo $found_ktp; ?>" 
                                     alt="KTP" onclick="viewDocument('<?php echo $found_ktp; ?>')">
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
                            $kk_path = 'images/players/' . $player['kk_image'];
                            $possible_paths = [
                                $kk_path,
                                '../' . $kk_path,
                                '../../' . $kk_path,
                                '../../../' . $kk_path
                            ];
                            
                            $found_kk = false;
                            foreach ($possible_paths as $path) {
                                if (file_exists($path)) {
                                    $found_kk = $path;
                                    break;
                                }
                            }
                            
                            if ($found_kk): ?>
                            <div class="document-preview">
                                <img src="<?php echo $found_kk; ?>" 
                                     alt="KK" onclick="viewDocument('<?php echo $found_kk; ?>')">
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
                            $akte_path = 'images/players/' . $player['birth_cert_image'];
                            $possible_paths = [
                                $akte_path,
                                '../' . $akte_path,
                                '../../' . $akte_path,
                                '../../../' . $akte_path
                            ];
                            
                            $found_akte = false;
                            foreach ($possible_paths as $path) {
                                if (file_exists($path)) {
                                    $found_akte = $path;
                                    break;
                                }
                            }
                            
                            if ($found_akte): ?>
                            <div class="document-preview">
                                <img src="<?php echo $found_akte; ?>" 
                                     alt="Akta Lahir" onclick="viewDocument('<?php echo $found_akte; ?>')">
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
                            $ijazah_path = 'images/players/' . $player['diploma_image'];
                            $possible_paths = [
                                $ijazah_path,
                                '../' . $ijazah_path,
                                '../../' . $ijazah_path,
                                '../../../' . $ijazah_path
                            ];
                            
                            $found_ijazah = false;
                            foreach ($possible_paths as $path) {
                                if (file_exists($path)) {
                                    $found_ijazah = $path;
                                    break;
                                }
                            }
                            
                            if ($found_ijazah): ?>
                            <div class="document-preview">
                                <img src="<?php echo $found_ijazah; ?>" 
                                     alt="Ijazah" onclick="viewDocument('<?php echo $found_ijazah; ?>')">
                            </div>
                        <?php endif; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate skill bars on load
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

    const printBtn = document.getElementById('printPlayerBtn');
    if (printBtn) {
        printBtn.addEventListener('click', async function() {
            await downloadPlayerScreenshot();
        });
    }
});

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

async function downloadPlayerScreenshot() {
    if (typeof html2canvas === 'undefined') {
        alert('Screenshot library not loaded. Please refresh the page.');
        return;
    }

    const target = document.querySelector('.main');
    if (!target) return;

    const printBtn = document.getElementById('printPlayerBtn');
    const originalText = printBtn ? printBtn.innerHTML : '';
    if (printBtn) {
        printBtn.disabled = true;
        printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    }

    try {
        const canvas = await html2canvas(target, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
            windowWidth: target.scrollWidth,
            windowHeight: target.scrollHeight
        });

        const link = document.createElement('a');
        link.download = 'player-<?php echo (int)$player['id']; ?>-profile.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (error) {
        alert('Failed to generate screenshot.');
    } finally {
        if (printBtn) {
            printBtn.disabled = false;
            printBtn.innerHTML = originalText;
        }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
