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

<div class="players-container">
    <!-- Editorial Header -->
    <header class="dashboard-hero reveal">
        <div class="hero-content">
            <span class="hero-label">Profil Atlet</span>
            <h1 class="hero-title"><?php echo htmlspecialchars($player['name'] ?? ''); ?></h1>
            <p class="hero-description">Lihat statistik kemampuan, informasi personal, dan kelengkapan dokumen pemain secara mendalam.</p>
        </div>
        <div class="hero-actions">
            <a href="./" class="btn-premium btn-outline">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <a href="form.php?id=<?php echo $player['id']; ?>" class="btn-premium btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button type="button" class="btn-premium btn-success" id="printPlayerBtn">
                <i class="fas fa-camera"></i> Screenshot
            </button>
        </div>
    </header>

    <!-- Profile Section -->
    <div class="reveal d-1">
        <div class="section-header">
            <div class="section-title-wrap">
                <h2 class="section-title">Informasi Personal</h2>
                <div class="section-line"></div>
            </div>
        </div>

        <div class="profile-card">
            <div class="player-photo-section">
                <div class="player-photo-wrapper">
                    <?php 
                    $photo_displayed = false;
                    if (!empty($player['photo'])): 
                        $photo_path = '../../images/players/' . $player['photo'];
                        $possible_paths = [$photo_path, '../' . $photo_path, '../../' . $photo_path, '../../../' . $photo_path];
                        
                        $found_photo = false;
                        foreach ($possible_paths as $path) {
                            if (file_exists($path)) { $found_photo = $path; break; }
                        }
                        
                        if ($found_photo): $photo_displayed = true;
                    ?>
                        <img src="<?php echo $found_photo; ?>" alt="<?php echo htmlspecialchars($player['name'] ?? ''); ?>" class="player-photo">
                    <?php endif; endif; ?>
                    
                    <?php if (!$photo_displayed): ?>
                        <div class="default-photo"><i class="fas fa-user"></i></div>
                    <?php endif; ?>

                    <?php if (!empty($player['jersey_number'])): ?>
                        <div class="player-jersey-badge">#<?php echo $player['jersey_number']; ?></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($player['team_name'])): ?>
                    <div class="player-team-info">
                        <i class="fas fa-users"></i> <?php echo htmlspecialchars($player['team_name'] ?? ''); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-id-card"></i> NIK</div>
                    <div class="info-value">
                        <?php 
                        $nik = $player['nik'];
                        echo !empty($nik) ? substr($nik, 0, 3) . '*********' . substr($nik, -4) : '-';
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-id-card"></i> NISN</div>
                    <div class="info-value"><?php echo !empty($player['nisn']) ? htmlspecialchars($player['nisn']) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-birthday-cake"></i> Tempat/Tanggal Lahir</div>
                    <div class="info-value">
                        <?php 
                        echo !empty($player['birth_place']) ? htmlspecialchars($player['birth_place']) : '-';
                        echo ' / ';
                        echo !empty($player['birth_date']) && $player['birth_date'] != '0000-00-00' ? date('d M Y', strtotime($player['birth_date'])) : '-';
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user-clock"></i> Usia</div>
                    <div class="info-value"><?php echo $player['age_years']; ?> tahun <?php echo $player['age_months']; ?> bulan</div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-venus-mars"></i> Jenis Kelamin</div>
                    <div class="info-value"><?php echo formatGenderView($player['gender']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-running"></i> Kategori</div>
                    <div class="info-value"><?php echo !empty($player['sport_type']) ? htmlspecialchars($player['sport_type']) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-ruler-vertical"></i> Tinggi/Berat</div>
                    <div class="info-value"><?php echo (!empty($player['height']) ? $player['height'] . ' cm' : '-') . ' / ' . (!empty($player['weight']) ? $player['weight'] . ' kg' : '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-phone"></i> Kontak</div>
                    <div class="info-value"><?php echo !empty($player['phone']) ? htmlspecialchars($player['phone']) : '-'; ?></div>
                </div>
                <div class="info-item info-item-full">
                    <div class="info-label"><i class="fas fa-map-marker-alt"></i> Alamat</div>
                    <div class="info-value">
                        <?php 
                        $address_parts = array_filter([$player['street'] ?? '', $player['city'] ?? '', $player['province'] ?? '', $player['postal_code'] ?? '']);
                        echo !empty($address_parts) ? htmlspecialchars(implode(', ', $address_parts)) : '-';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Skills Section -->
    <div class="reveal d-2">
        <div class="section-header">
            <div class="section-title-wrap">
                <h2 class="section-title">Football Skills & Attributes</h2>
                <div class="section-line"></div>
            </div>
        </div>

        <div class="skills-container">
            <div class="info-grid skills-meta">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-football-ball"></i> Kaki Dominan</div>
                    <div class="info-value"><?php echo !empty($player['dominant_foot']) ? htmlspecialchars($player['dominant_foot']) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-crosshairs"></i> Posisi Utama</div>
                    <div class="info-value"><?php echo !empty($player['position']) ? htmlspecialchars($player['position']) : '-'; ?></div>
                </div>
            </div>

            <div class="skills-grid">
                <?php 
                $skills = ['dribbling' => 'Dribbling', 'technique' => 'Technique', 'speed' => 'Speed', 'juggling' => 'Juggling', 'shooting' => 'Shooting', 'setplay_position' => 'Setplay Position', 'passing' => 'Passing', 'control' => 'Control'];
                foreach ($skills as $key => $label): 
                    $value = $player[$key] ?? 5;
                    $percentage = ($value / 10) * 100;
                ?>
                <div class="skill-item">
                    <div class="skill-header">
                        <span class="skill-name"><?php echo $label; ?></span>
                        <span class="skill-val-text"><?php echo $value; ?>/10</span>
                    </div>
                    <div class="skill-track">
                        <div class="skill-fill" style="width: <?php echo $percentage; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Documents Section -->
    <div class="reveal d-3">
        <div class="section-header">
            <div class="section-title-wrap">
                <h2 class="section-title">Dokumen Pemain</h2>
                <div class="section-line"></div>
            </div>
        </div>

        <div class="documents-grid">
            <?php 
            $docs = [
                ['label' => 'KTP / Kartu Identitas', 'icon' => 'fa-id-card', 'field' => 'ktp_image'],
                ['label' => 'Kartu Keluarga', 'icon' => 'fa-home', 'field' => 'kk_image'],
                ['label' => 'Akta Lahir', 'icon' => 'fa-baby', 'field' => 'birth_cert_image'],
                ['label' => 'Ijazah / Raport', 'icon' => 'fa-graduation-cap', 'field' => 'diploma_image']
            ];
            foreach ($docs as $doc):
                $has_doc = !empty($player[$doc['field']]);
            ?>
            <div class="doc-card">
                <div class="doc-header">
                    <div class="doc-icon"><i class="fas <?php echo $doc['icon']; ?>"></i></div>
                    <div class="doc-title"><?php echo $doc['label']; ?></div>
                </div>
                <div class="doc-status <?php echo $has_doc ? 'status-available' : 'status-unavailable'; ?>">
                    <?php echo $has_doc ? 'Tersedia' : 'Tidak Tersedia'; ?>
                </div>
                <?php if ($has_doc): 
                    $img_path = '../../images/players/' . $player[$doc['field']];
                    $found_img = false;
                    foreach ([$img_path, '../' . $img_path, '../../' . $img_path, '../../../' . $img_path] as $p) {
                        if (file_exists($p)) { $found_img = $p; break; }
                    }
                    if ($found_img):
                ?>
                    <div class="doc-preview">
                        <img src="<?php echo $found_img; ?>" alt="<?php echo $doc['label']; ?>" onclick="viewDocument('<?php echo $found_img; ?>')">
                    </div>
                <?php endif; endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const skillBars = document.querySelectorAll('.skill-fill');
    skillBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => { bar.style.width = width; }, 100);
    });
    
    document.querySelectorAll('.player-photo, .doc-preview img').forEach(img => {
        img.addEventListener('error', function() {
            if (this.classList.contains('player-photo')) showDefaultPhoto(this);
        });
    });

    const printBtn = document.getElementById('printPlayerBtn');
    if (printBtn) printBtn.addEventListener('click', () => downloadPlayerScreenshot());
});

function viewDocument(imagePath) { window.open(imagePath, '_blank'); }

function showDefaultPhoto(imgElement) {
    imgElement.style.display = 'none';
    let defaultPhoto = imgElement.nextElementSibling;
    if (defaultPhoto && defaultPhoto.classList.contains('default-photo')) defaultPhoto.style.display = 'flex';
}

async function downloadPlayerScreenshot() {
    if (typeof html2canvas === 'undefined') { alert('Screenshot library not loaded.'); return; }
    const target = document.querySelector('.main');
    if (!target) return;
    const printBtn = document.getElementById('printPlayerBtn');
    const originalText = printBtn.innerHTML;
    printBtn.disabled = true;
    printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

    try {
        const canvas = await html2canvas(target, { backgroundColor: '#f8f7f4', scale: 2, useCORS: true });
        const link = document.createElement('a');
        link.download = 'player-<?php echo (int)$player['id']; ?>-profile.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (error) { alert('Failed to generate screenshot.'); } 
    finally { printBtn.disabled = false; printBtn.innerHTML = originalText; }
}
</script>

<?php require_once '../includes/footer.php'; ?>
