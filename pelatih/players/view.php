<?php
$page_title = 'Detail Player';
$current_page = 'players';
require_once '../config/database.php';
require_once '../includes/header.php';

$team_id = $_SESSION['team_id'] ?? 0;

if (!isset($_GET['id']) || empty($_GET['id']) || !$team_id) {
    header('Location: ./');
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
        header('Location: ./');
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
    require_once '../includes/footer.php';
    exit;
}
?>

<link rel="stylesheet" href="css/player_view.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/player_view.css'); ?>">

<div class="player-view">
    <div class="container">
            <!-- Header -->
            <div class="header">
                <a href="./" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Players
                </a>
                <div class="page-title">
                    <i class="fas fa-user"></i>
                    <span>Player Profile</span>
                </div>
                <div class="action-buttons">
                    <a href="form.php?id=<?php echo $player['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i>
                        Edit Player
                    </a>
                    <button type="button" class="btn btn-success" id="printPlayerBtn">
                        <i class="fas fa-camera"></i>
                        Print Player
                    </button>
                    <a href="./" class="btn btn-primary">
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
                        $photo_path = '../../images/players/' . $player['photo'];
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
                                Kategori
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
                            $kk_path = '../../images/players/' . $player['kk_image'];
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
                            $akte_path = '../../images/players/' . $player['birth_cert_image'];
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
                            $ijazah_path = '../../images/players/' . $player['diploma_image'];
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

<?php require_once '../includes/footer.php'; ?>
