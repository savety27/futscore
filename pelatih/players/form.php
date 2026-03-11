<?php
require_once '../config/database.php';
$page_title = 'Pemain Saya';
$current_page = 'players';
require_once '../includes/header.php';

$event_helper_path = __DIR__ . '/../../admin/includes/event_helpers.php';
if (file_exists($event_helper_path)) {
    require_once $event_helper_path;
}

$action = 'add';
$player_id = 0;
$event_options = function_exists('getDynamicEventOptions') ? getDynamicEventOptions($conn) : [];
$player = [
    'name' => '',
    'jersey_number' => '',
    'position' => '',
    'position_detail' => '',
    'birth_date' => '',
    'birth_place' => '',
    'gender' => 'L',
    'height' => '',
    'weight' => '',
    'photo' => '',
    'dominant_foot' => '',
    'nisn' => '',
    'nik' => '',
    'sport_type' => '',
    'email' => '',
    'phone' => '',
    'nationality' => 'Indonesia',
    'street' => '',
    'city' => '',
    'province' => '',
    'postal_code' => '',
    'country' => 'Indonesia',
    'dribbling' => 5,
    'technique' => 5,
    'speed' => 5,
    'juggling' => 5,
    'shooting' => 5,
    'setplay_position' => 5,
    'passing' => 5,
    'control' => 5,
    'ktp_image' => '',
    'kk_image' => '',
    'birth_cert_image' => '',
    'diploma_image' => '',
    'status' => 'active'
];

// Check if editing
if (isset($_GET['id'])) {
    $action = 'edit';
    $player_id = (int)$_GET['id'];
    $team_id = $_SESSION['team_id'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM players WHERE id = ? AND team_id = ?");
        $stmt->execute([$player_id, $team_id]);
        $fetched_player = $stmt->fetch();
        
        if ($fetched_player) {
            $player = $fetched_player;
            // Format birth date for HTML input
            if ($player['birth_date']) {
                $player['birth_date'] = date('Y-m-d', strtotime($player['birth_date']));
            }
            
            // Set default values if empty
            if (empty($player['nationality'])) $player['nationality'] = 'Indonesia';
            if (empty($player['country'])) $player['country'] = 'Indonesia';
            if (empty($player['sport_type'])) $player['sport_type'] = 'Futsal';
            if (empty($player['dominant_foot'])) $player['dominant_foot'] = 'kanan';
            if (empty($player['status'])) $player['status'] = 'active';
        } else {
            echo "<div class='alert alert-danger'>Player not found or unauthorized.</div>";
            require_once '../includes/footer.php';
            exit;
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

$selected_sport_type = trim((string)($player['sport_type'] ?? ''));
if ($selected_sport_type !== '' && !in_array($selected_sport_type, $event_options, true)) {
    $event_options[] = $selected_sport_type;
    natcasesort($event_options);
    $event_options = array_values($event_options);
}
?>

<link rel="stylesheet" href="css/player_form.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/player_form.css'); ?>">

<div class="container">
    <header class="header reveal">
        <h1>
            <i class="fas fa-user-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i> 
            <?php echo $action === 'add' ? 'Tambah' : 'Ubah'; ?> Pemain
        </h1>
        <a href="./" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
        </a>
    </header>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger reveal">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars(urldecode($_GET['error'])); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success reveal">
            <span class="alert-icon">✓</span>
            <span>Pemain berhasil <?php echo $_GET['msg'] === 'added' ? 'ditambahkan' : 'diperbarui'; ?>!</span>
        </div>
    <?php endif; ?>

    <div class="alert alert-danger" id="clientAlertBox" style="display: none;">
        <i class="fas fa-exclamation-circle"></i>
        <span id="clientAlertText"></span>
    </div>

    <div class="form-container">
        <form action="actions.php" method="POST" enctype="multipart/form-data" id="playerForm">
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            <input type="hidden" name="id" value="<?php echo $player_id; ?>">
            <input type="hidden" id="hasExistingKk" value="<?php echo !empty($player['kk_image']) ? '1' : '0'; ?>">
            
            <!-- Basic Information Section -->
            <div class="form-section reveal d-1">
                <h2 class="section-title">
                    <i class="fas fa-user-circle"></i>
                   Profil Dasar
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">Nama</span>
                            <span class="note">Wajib</span>
                        </label>
                        <input type="text" name="name" class="form-control" 
                                placeholder="Masukkan nama lengkap" required
                                value="<?php echo htmlspecialchars($player['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">No Punggung</span>
                            <span class="note">Wajib</span>
                        </label>
                        <input type="number" name="jersey_number" class="form-control" 
                                placeholder="Masukkan nomor punggung" required
                                value="<?php echo htmlspecialchars($player['jersey_number'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">Kategori</span>
                            <span class="note">Wajib</span>
                        </label>
                        <select name="sport_type" class="form-control custom-select" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($event_options as $sport): ?>
                                <?php $selected = ($player['sport_type'] == $sport) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($sport); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($sport); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">Posisi</span>
                            <span class="note">Wajib</span>
                        </label>
                        <select name="position" class="form-control custom-select" required>
                            <option value="">Pilih Posisi</option>
                            <?php 
                            $positions = [
                                'GK' => 'Goalkeeper (GK)',
                                'DF' => 'Defender (DF)', 
                                'MF' => 'Midfielder (MF)',
                                'FW' => 'Forward (FW)'
                            ];
                            foreach ($positions as $key => $label) {
                                $selected = ($player['position'] == $key) ? 'selected' : '';
                                echo "<option value='$key' $selected>$label</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Detail Posisi</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input
                            type="text"
                            name="position_detail"
                            class="form-control"
                            maxlength="100"
                            placeholder="Contoh: Winger Kiri"
                            value="<?php echo htmlspecialchars($player['position_detail'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">Tanggal Lahir</span>
                            <span class="note">Wajib</span>
                        </label>
                        <input type="date" name="birth_date" class="form-control" 
                               value="<?php echo htmlspecialchars($player['birth_date'] ?? ''); ?>"
                               max="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">Tempat Lahir</span>
                            <span class="note">Wajib</span>
                        </label>
                        <input type="text" name="birth_place" class="form-control" 
                               placeholder="Masukkan tempat lahir" required
                               value="<?php echo htmlspecialchars($player['birth_place'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">Jenis Kelamin</span>
                            <span class="note">Wajib</span>
                        </label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="gender" value="L" required
                                    <?php echo ($player['gender'] == 'L') ? 'checked' : ''; ?>>
                                <span>Laki-laki</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="gender" value="P" required
                                    <?php echo ($player['gender'] == 'P') ? 'checked' : ''; ?>>
                                <span>Perempuan</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Kaki Dominan</span>
                            <span class="note">Opsional</span>
                        </label>
                        <select name="dominant_foot" class="form-control custom-select">
                            <option value="">Pilih Kaki Dominan</option>
                            <option value="kanan" <?php echo ($player['dominant_foot'] == 'kanan') ? 'selected' : ''; ?>>Kanan</option>
                            <option value="kiri" <?php echo ($player['dominant_foot'] == 'kiri') ? 'selected' : ''; ?>>Kiri</option>
                            <option value="kedua" <?php echo ($player['dominant_foot'] == 'kedua') ? 'selected' : ''; ?>>Kedua</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Tinggi & Berat</span>
                            <span class="note">Opsional</span>
                        </label>
                        <div class="date-input">
                            <input type="number" name="height" class="form-control" 
                                   placeholder="Tinggi (cm)"
                                   value="<?php echo htmlspecialchars($player['height'] ?? ''); ?>">
                            <input type="number" name="weight" class="form-control" 
                                   placeholder="Berat (kg)"
                                   value="<?php echo htmlspecialchars($player['weight'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Information Section -->
            <div class="form-section reveal d-2">
                <h2 class="section-title">
                    <i class="fas fa-address-card"></i>
                    Informasi Kontak
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <span>Email</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="Masukkan email"
                               value="<?php echo htmlspecialchars($player['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Telpon</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="tel" name="phone" class="form-control" 
                               placeholder="Masukkan nomor telepon"
                               value="<?php echo htmlspecialchars($player['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Alamat</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="street" class="form-control" 
                               placeholder="Masukkan alamat lengkap"
                               value="<?php echo htmlspecialchars($player['street'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Kota</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="city" class="form-control" 
                               placeholder="Masukkan kota"
                               value="<?php echo htmlspecialchars($player['city'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Provinsi</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="province" class="form-control" 
                               placeholder="Masukkan provinsi"
                               value="<?php echo htmlspecialchars($player['province'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Kode Pos</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="postal_code" class="form-control" 
                               placeholder="Masukkan kode pos"
                               value="<?php echo htmlspecialchars($player['postal_code'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Kewarganegaraan</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="nationality" class="form-control" 
                               placeholder="Masukkan kewarganegaraan"
                               value="<?php echo htmlspecialchars($player['nationality'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <span>Negara</span>
                            <span class="note">Opsional</span>
                        </label>
                        <input type="text" name="country" class="form-control" 
                               placeholder="Masukkan negara"
                               value="<?php echo htmlspecialchars($player['country'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Identification Section -->
            <div class="form-section reveal d-3">
                <h2 class="section-title">
                    <i class="fas fa-id-card"></i>
                    Identitas & Verifikasi
                </h2>
                <p class="note" style="margin-bottom: 24px; color: var(--heritage-text-muted); font-family: var(--font-body);">
                    NIK dan NISN wajib diverifikasi sebelum data dapat disimpan untuk menjamin integritas atlet.
                </p>
                
                <div class="form-grid">
                    <!-- NIK -->
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">NIK</span>
                            <span class="note">16 digit</span>
                        </label>
                        <div class="verify-input-wrapper">
                            <input type="text" name="nik" class="form-control verify-input" 
                                   id="nikInput"
                                   placeholder="Masukkan 16-digit NIK" required
                                   maxlength="16"
                                   inputmode="numeric"
                                   pattern="[0-9]{16}"
                                   title="NIK harus terdiri dari tepat 16 digit angka"
                                   data-original="<?php echo htmlspecialchars($player['nik'] ?? ''); ?>"
                                   value="<?php echo htmlspecialchars($player['nik'] ?? ''); ?>">
                            <button type="button" class="verify-btn" id="nikVerifyBtn" onclick="verifyNIK()" disabled>
                                Verifikasi
                            </button>
                        </div>
                        <input type="hidden" name="nik_verified" id="nikVerified" value="0">
                        <div class="verify-feedback" id="nikFeedback"></div>
                        <div class="verify-details" id="nikDetails" style="display:none;"></div>
                    </div>

                    <!-- NISN -->
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">NISN</span>
                            <span class="note">10 digit</span>
                        </label>
                        <div class="verify-input-wrapper">
                            <input type="text" name="nisn" class="form-control verify-input" 
                                   id="nisnInput"
                                   placeholder="Masukkan 10-digit NISN" required
                                   maxlength="10"
                                   inputmode="numeric"
                                   pattern="[0-9]{10}"
                                   title="NISN harus terdiri dari tepat 10 digit angka"
                                   data-original="<?php echo htmlspecialchars($player['nisn'] ?? ''); ?>"
                                   value="<?php echo htmlspecialchars($player['nisn'] ?? ''); ?>">
                            <button type="button" class="verify-btn" id="nisnVerifyBtn" onclick="verifyNISN()" disabled>
                                Verifikasi
                            </button>
                        </div>
                        <input type="hidden" name="nisn_verified" id="nisnVerified" value="0">
                        <div class="verify-feedback" id="nisnFeedback"></div>
                        <div class="verify-details" id="nisnDetails" style="display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- Photo & Document Upload Section -->
            <div class="form-section reveal d-4">
                <h2 class="section-title">
                    <i class="fas fa-camera"></i>
                    Foto & Dokumen
                </h2>
                
                <div class="form-grid">
                    <!-- Profile Photo -->
                    <div class="form-group">
                        <label class="form-label">
                            <span>Foto Profil</span>
                            <span class="note">Maks 5MB</span>
                        </label>
                        
                        <?php if (!empty($player['photo'])): ?>
                        <div class="file-item">
                            <img src="../../images/players/<?php echo htmlspecialchars($player['photo'] ?? ''); ?>" 
                                 alt="Current Photo">
                            <div>
                                <div style="font-weight: 700;">Foto Saat Ini</div>
                                <div style="font-size: 0.75rem; color: var(--heritage-text-muted);">Klik upload untuk mengganti</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="file-upload" id="photoUpload">
                            <div>
                                <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--heritage-text); margin-bottom: 12px;"></i>
                                <p style="margin: 0; font-weight: 600;">Unggah Foto Profil</p>
                                <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--heritage-text-muted);">Maksimal 5MB (JPG, PNG)</p>
                            </div>
                            <input type="file" name="photo" id="photoFile" accept="image/*">
                        </div>
                        <div class="file-preview" id="photoPreview"></div>
                    </div>

                    <?php
                    $documents = [
                        'kk_image' => ['label' => 'Kartu Keluarga', 'current' => $player['kk_image'], 'req' => true],
                        'ktp_image' => ['label' => 'KTP / Kartu Identitas', 'current' => $player['ktp_image'], 'req' => false],
                        'birth_cert_image' => ['label' => 'Akta Lahir', 'current' => $player['birth_cert_image'], 'req' => false],
                        'diploma_image' => ['label' => 'Ijazah / Raport', 'current' => $player['diploma_image'], 'req' => false]
                    ];
                    
                    foreach ($documents as $key => $doc):
                    ?>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="<?php echo $doc['req'] ? 'required-field' : ''; ?>"><?php echo $doc['label']; ?></span>
                            <span class="note"><?php echo $doc['req'] ? 'Wajib' : 'Opsional'; ?></span>
                        </label>
                        
                        <?php if (!empty($doc['current'])): ?>
                        <div class="file-item">
                            <div style="font-size: 0.85rem; font-weight: 600;">Tersimpan: <?php echo htmlspecialchars($doc['current']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="file-upload" id="<?php echo $key; ?>Upload">
                            <div>
                                <i class="fas fa-file-upload" style="font-size: 1.5rem; color: var(--heritage-text); margin-bottom: 8px;"></i>
                                <p style="margin: 0; font-weight: 600; font-size: 0.9rem;">Unggah <?php echo $doc['label']; ?></p>
                            </div>
                            <input type="file" name="<?php echo $key; ?>" id="<?php echo $key; ?>File" accept="image/*">
                        </div>
                        <div class="file-preview" id="<?php echo $key; ?>Preview"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Skills Section -->
            <div class="form-section reveal d-1">
                <h2 class="section-title">
                    <i class="fas fa-chart-line"></i>
                    Atribut Teknis (0-10)
                </h2>
                
                <div class="skill-grid">
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
                        $value = isset($player[$key]) ? (int)$player[$key] : 5;
                    ?>
                    <div class="skill-item">
                        <div class="skill-header">
                            <span class="skill-name"><?php echo $label; ?></span>
                            <span class="skill-value" id="<?php echo $key; ?>Value"><?php echo $value; ?></span>
                        </div>
                        <input type="range" name="<?php echo $key; ?>" class="slider" min="0" max="10" 
                               value="<?php echo $value; ?>" id="<?php echo $key; ?>Slider"
                               oninput="document.getElementById('<?php echo $key; ?>Value').textContent = this.value">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Status Section -->
            <div class="form-section reveal d-2">
                <h2 class="section-title">
                    <i class="fas fa-check-circle"></i>
                    Status Keanggotaan
                </h2>
                <div class="form-group">
                    <div style="display: flex; align-items: center; gap: 15px; background: #f8fafc; padding: 20px; border-radius: 16px;">
                        <input type="checkbox" id="status_active" name="status" value="active" <?php echo ($player['status'] ?? 'active') === 'active' ? 'checked' : ''; ?> style="width: 24px; height: 24px; cursor: pointer;">
                        <div>
                            <label for="status_active" style="font-weight: 700; cursor: pointer; color: var(--heritage-text);">Pemain Aktif</label>
                            <div style="font-size: 0.85rem; color: var(--heritage-text-muted);">Pemain aktif akan tersedia untuk seleksi pertandingan.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions reveal d-3">
                <a href="./" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Batalkan
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $action === 'add' ? 'Simpan Pemain' : 'Perbarui Data'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // CUSTOM SELECT DROPDOWN
    // ============================================================
    function initCustomSelects() {
        const customSelects = document.querySelectorAll('.custom-select');
        
        customSelects.forEach(select => {
            // Hide native select
            select.style.display = 'none';
            
            const wrapper = document.createElement('div');
            wrapper.className = 'custom-select-wrapper';
            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(select);
            
            const trigger = document.createElement('div');
            trigger.className = 'custom-select-trigger';
            const selectedOption = select.options[select.selectedIndex];
            trigger.textContent = selectedOption ? selectedOption.textContent : 'Pilih...';
            wrapper.appendChild(trigger);
            
            const optionsContainer = document.createElement('div');
            optionsContainer.className = 'custom-options';
            wrapper.appendChild(optionsContainer);
            
            Array.from(select.options).forEach(option => {
                const opt = document.createElement('div');
                opt.className = 'custom-option' + (option.selected ? ' selected' : '');
                opt.textContent = option.textContent;
                opt.dataset.value = option.value;
                
                opt.addEventListener('click', () => {
                    select.value = option.value;
                    trigger.textContent = option.textContent;
                    
                    // Update classes
                    optionsContainer.querySelectorAll('.custom-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    
                    wrapper.classList.remove('open');
                    
                    // Trigger native change event
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                });
                
                optionsContainer.appendChild(opt);
            });
            
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                // Close other open selects
                document.querySelectorAll('.custom-select-wrapper').forEach(w => {
                    if (w !== wrapper) w.classList.remove('open');
                });
                wrapper.classList.toggle('open');
            });
        });
        
        // Close when clicking outside
        document.addEventListener('click', () => {
            document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open'));
        });
    }

    initCustomSelects();

    // ============================================================
    // FILE UPLOAD FUNCTIONALITY
    // ============================================================
    function setupFileUpload(uploadElement, fileInput, previewElement) {
        const uploadArea = uploadElement;
        const fileInputField = fileInput;
        const previewContainer = previewElement;

        uploadArea.addEventListener('click', () => fileInputField.click());
        uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('dragover'); });
        uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                fileInputField.files = e.dataTransfer.files;
                handleFileSelect(e.dataTransfer.files[0], previewContainer);
            }
        });
        fileInputField.addEventListener('change', (e) => {
            if (e.target.files.length) handleFileSelect(e.target.files[0], previewContainer);
        });
    }

    function handleFileSelect(file, previewContainer) {
        if (!file.type.startsWith('image/')) { showClientAlert('Harap pilih file gambar!'); return; }
        if (file.size > 5 * 1024 * 1024) { showClientAlert('Ukuran file terlalu besar! Maksimal 5MB.'); return; }
        const reader = new FileReader();
        reader.onload = function(e) {
            previewContainer.innerHTML = `
                <div class="file-item">
                    <img src="${e.target.result}" alt="Preview">
                    <div>
                        <div><strong>${file.name}</strong></div>
                        <div style="font-size: 12px; color: var(--heritage-text-muted);">${formatFileSize(file.size)}</div>
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

    function showClientAlert(message) {
        const alertBox = document.getElementById('clientAlertBox');
        const alertText = document.getElementById('clientAlertText');
        if (!alertBox || !alertText) {
            return;
        }
        alertText.textContent = message;
        alertBox.style.display = 'flex';
        alertBox.className = 'alert alert-danger';
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideClientAlert() {
        const alertBox = document.getElementById('clientAlertBox');
        if (alertBox) {
            alertBox.style.display = 'none';
        }
    }

    // Initialize file uploads
    [['photoUpload','photoFile','photoPreview'],['ktp_imageUpload','ktp_imageFile','ktp_imagePreview'],
     ['kk_imageUpload','kk_imageFile','kk_imagePreview'],['birth_cert_imageUpload','birth_cert_imageFile','birth_cert_imagePreview'],
     ['diploma_imageUpload','diploma_imageFile','diploma_imagePreview']
    ].forEach(([u,f,p]) => {
        const ue=document.getElementById(u), fe=document.getElementById(f), pe=document.getElementById(p);
        if(ue&&fe&&pe) setupFileUpload(ue,fe,pe);
    });

    // ============================================================
    // NIK INPUT & VERIFICATION
    // ============================================================
    const nikInput = document.getElementById('nikInput');
    const nikFeedback = document.getElementById('nikFeedback');
    const nikVerifyBtn = document.getElementById('nikVerifyBtn');
    const nikVerified = document.getElementById('nikVerified');
    const nikDetails = document.getElementById('nikDetails');
    const playerId = <?php echo (int)$player_id; ?>;
    const provinceInput = document.querySelector('input[name="province"]');
    const birthDateInput = document.querySelector('input[name="birth_date"]');
    const genderRadios = document.querySelectorAll('input[name="gender"]');

    function convertNikDateToInputValue(nikDateValue) {
        if (!nikDateValue || typeof nikDateValue !== 'string') {
            return '';
        }

        const dateParts = nikDateValue.split('-');
        if (dateParts.length !== 3) {
            return '';
        }

        const day = dateParts[0];
        const month = dateParts[1];
        const year = dateParts[2];

        if (!/^\d{2}$/.test(day) || !/^\d{2}$/.test(month) || !/^\d{4}$/.test(year)) {
            return '';
        }

        return `${year}-${month}-${day}`;
    }

    function mapNikGenderToPlayerGender(nikGenderValue) {
        if (nikGenderValue === 'Laki-laki') {
            return 'L';
        }

        if (nikGenderValue === 'Perempuan') {
            return 'P';
        }

        return '';
    }

    function autofillFieldsFromNikDetails(details) {
        if (!details || typeof details !== 'object') {
            return;
        }

        if (provinceInput && details.provinsi) {
            provinceInput.value = details.provinsi;
        }

        if (birthDateInput && details.tanggal_lahir) {
            const formattedDate = convertNikDateToInputValue(details.tanggal_lahir);
            if (formattedDate) {
                birthDateInput.value = formattedDate;
            }
        }

        if (genderRadios && details.jenis_kelamin) {
            const mappedGender = mapNikGenderToPlayerGender(details.jenis_kelamin);
            if (mappedGender) {
                genderRadios.forEach((radio) => {
                    radio.checked = radio.value === mappedGender;
                });
            }
        }
    }

    if (nikInput) {
        nikInput.addEventListener('input', function(e) {
            const numericValue = e.target.value.replace(/[^0-9]/g, '').slice(0, 16);
            const originalValue = String(nikInput.dataset.original || '').replace(/[^0-9]/g, '').slice(0, 16);
            const unchangedValidOriginal = playerId > 0 && originalValue.length === 16 && numericValue === originalValue;
            nikInput.value = numericValue;

            if (unchangedValidOriginal) {
                nikVerified.value = '1';
                nikDetails.style.display = 'none';
                nikVerifyBtn.classList.remove('loading');
                nikVerifyBtn.classList.add('verified');
                nikVerifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Tersimpan';
                nikVerifyBtn.disabled = true;
                nikInput.style.borderColor = 'var(--heritage-accent)';
                nikFeedback.textContent = 'NIK tidak diubah - verifikasi tetap valid';
                nikFeedback.className = 'verify-feedback success';
                return;
            }
            // Reset verification when value changes
            nikVerified.value = '0';
            nikDetails.style.display = 'none';
            nikVerifyBtn.classList.remove('verified');
            nikVerifyBtn.innerHTML = 'Verifikasi';
            nikInput.style.borderColor = 'var(--heritage-border)';

            if (numericValue.length === 16) {
                nikFeedback.textContent = 'Klik "Verifikasi" untuk memvalidasi NIK';
                nikFeedback.className = 'verify-feedback warning';
                nikVerifyBtn.disabled = false;
            } else if (numericValue.length > 0) {
                nikFeedback.textContent = `Kurang ${16 - numericValue.length} digit`;
                nikFeedback.className = 'verify-feedback warning';
                nikVerifyBtn.disabled = true;
            } else {
                nikFeedback.textContent = '16 digit angka';
                nikFeedback.className = 'verify-feedback';
                nikVerifyBtn.disabled = true;
            }
        });

        nikInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(String.fromCharCode(e.which || e.keyCode))) e.preventDefault();
        });
        nikInput.addEventListener('paste', function(e) {
            if (!/^[0-9]+$/.test((e.clipboardData || window.clipboardData).getData('text'))) e.preventDefault();
        });

        // Auto-trigger on page load for edit mode
        nikInput.dispatchEvent(new Event('input'));
    }

    function submitIdentityVerification(formData) {
        formData.append('csrf_token', window.ADMIN_CSRF_TOKEN || '');

        return fetch('../../api/verify_identity.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(response =>
            response.json().catch(() => null).then(data => {
                if (response.ok) {
                    return data || {};
                }

                const error = new Error((data && data.message) ? data.message : 'Gagal memverifikasi identitas');
                error.status = response.status;
                error.data = data;
                throw error;
            })
        );
    }

    // NIK Verify via AJAX
    window.verifyNIK = function() {
        const value = nikInput.value.trim();
        if (value.length !== 16) return;

        nikVerifyBtn.disabled = true;
        nikVerifyBtn.classList.add('loading');
        nikVerifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        nikFeedback.textContent = 'Sedang memverifikasi...';

        const formData = new FormData();
        formData.append('type', 'nik');
        formData.append('value', value);
        if (playerId > 0) formData.append('exclude_player_id', playerId);

        submitIdentityVerification(formData)
            .then(data => {
                if (data.verified) {
                    nikVerified.value = '1';
                    nikFeedback.textContent = '✓ ' + data.message;
                    nikFeedback.className = 'verify-feedback success';
                    nikInput.style.borderColor = 'var(--heritage-accent)';
                    nikVerifyBtn.innerHTML = '<i class="fas fa-check-circle"></i>';
                    nikVerifyBtn.classList.remove('loading');
                    nikVerifyBtn.classList.add('verified');

                    // Show details
                    if (data.details) {
                        autofillFieldsFromNikDetails(data.details);
                        let html = '<strong>📋 Data NIK:</strong><br>';
                        if (data.details.provinsi) html += `Provinsi: ${data.details.provinsi}<br>`;
                        if (data.details.tanggal_lahir) html += `Tgl Lahir: ${data.details.tanggal_lahir}<br>`;
                        if (data.details.jenis_kelamin) html += `Gender: ${data.details.jenis_kelamin}`;
                        nikDetails.innerHTML = html;
                        nikDetails.style.display = 'block';
                    }
                } else {
                    nikVerified.value = '0';
                    nikFeedback.textContent = '✗ ' + data.message;
                    nikFeedback.className = 'verify-feedback error';
                    nikInput.style.borderColor = 'var(--heritage-crimson)';
                    nikVerifyBtn.innerHTML = 'Gagal';
                    nikVerifyBtn.classList.remove('loading');
                    nikVerifyBtn.disabled = false;
                }
            })
            .catch(err => {
                nikFeedback.textContent = '⚠ Gagal menghubungi server';
                nikFeedback.className = 'verify-feedback error';
                nikVerifyBtn.innerHTML = 'Verifikasi';
                nikVerifyBtn.classList.remove('loading');
                nikVerifyBtn.disabled = false;
            });
    };

    // ============================================================
    // NISN INPUT & VERIFICATION
    // ============================================================
    const nisnInput = document.getElementById('nisnInput');
    const nisnFeedback = document.getElementById('nisnFeedback');
    const nisnVerifyBtn = document.getElementById('nisnVerifyBtn');
    const nisnVerified = document.getElementById('nisnVerified');

    if (nisnInput) {
        nisnInput.addEventListener('input', function(e) {
            const numericValue = e.target.value.replace(/[^0-9]/g, '').slice(0, 10);
            const originalValue = String(nisnInput.dataset.original || '').replace(/[^0-9]/g, '').slice(0, 10);
            const unchangedValidOriginal = playerId > 0 && originalValue.length === 10 && numericValue === originalValue;
            nisnInput.value = numericValue;

            if (unchangedValidOriginal) {
                nisnVerified.value = '1';
                nisnVerifyBtn.classList.remove('loading');
                nisnVerifyBtn.classList.add('verified');
                nisnVerifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Tersimpan';
                nisnVerifyBtn.disabled = true;
                nisnInput.style.borderColor = 'var(--heritage-accent)';
                nisnFeedback.textContent = 'NISN tidak diubah';
                nisnFeedback.className = 'verify-feedback success';
                return;
            }
            // Reset verification
            nisnVerified.value = '0';
            nisnVerifyBtn.classList.remove('verified');
            nisnVerifyBtn.innerHTML = 'Verifikasi';
            nisnInput.style.borderColor = 'var(--heritage-border)';

            if (numericValue.length === 10) {
                nisnFeedback.textContent = 'Klik "Verifikasi" untuk NISN';
                nisnFeedback.className = 'verify-feedback warning';
                nisnVerifyBtn.disabled = false;
            } else if (numericValue.length > 0) {
                nisnFeedback.textContent = `Kurang ${10 - numericValue.length} digit`;
                nisnFeedback.className = 'verify-feedback warning';
                nisnVerifyBtn.disabled = true;
            } else {
                nisnFeedback.textContent = '10 digit angka';
                nisnFeedback.className = 'verify-feedback';
                nisnVerifyBtn.disabled = true;
            }
        });

        nisnInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(String.fromCharCode(e.which || e.keyCode))) e.preventDefault();
        });
        nisnInput.addEventListener('paste', function(e) {
            if (!/^[0-9]+$/.test((e.clipboardData || window.clipboardData).getData('text'))) e.preventDefault();
        });

        // Auto-trigger on page load for edit mode
        nisnInput.dispatchEvent(new Event('input'));
    }

    // NISN Verify via AJAX
    window.verifyNISN = function() {
        const value = nisnInput.value.trim();
        if (value.length !== 10) return;

        nisnVerifyBtn.disabled = true;
        nisnVerifyBtn.classList.add('loading');
        nisnVerifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        nisnFeedback.textContent = 'Sedang memverifikasi...';

        const formData = new FormData();
        formData.append('type', 'nisn');
        formData.append('value', value);
        if (playerId > 0) formData.append('exclude_player_id', playerId);

        submitIdentityVerification(formData)
            .then(data => {
                if (data.verified) {
                    nisnVerified.value = '1';
                    nisnFeedback.textContent = '✓ ' + data.message;
                    nisnFeedback.className = 'verify-feedback success';
                    nisnInput.style.borderColor = 'var(--heritage-accent)';
                    nisnVerifyBtn.innerHTML = '<i class="fas fa-check-circle"></i>';
                    nisnVerifyBtn.classList.remove('loading');
                    nisnVerifyBtn.classList.add('verified');

                    // Show NISN details
                    const nisnDetailsEl = document.getElementById('nisnDetails');
                    if (data.details && nisnDetailsEl) {
                        let html = '<strong>📋 Data NISN:</strong><br>';
                        if (data.details.usia) html += `Usia: ${data.details.usia}<br>`;
                        if (data.details.perkiraan_jenjang) html += `Jenjang: ${data.details.perkiraan_jenjang}`;
                        nisnDetailsEl.innerHTML = html;
                        nisnDetailsEl.style.display = 'block';
                    }
                } else {
                    nisnVerified.value = '0';
                    nisnFeedback.textContent = '✗ ' + data.message;
                    nisnFeedback.className = 'verify-feedback error';
                    nisnInput.style.borderColor = 'var(--heritage-crimson)';
                    nisnVerifyBtn.innerHTML = 'Gagal';
                    nisnVerifyBtn.classList.remove('loading');
                    nisnVerifyBtn.disabled = false;
                }
            })
            .catch(err => {
                nisnFeedback.textContent = '⚠ Gagal menghubungi server';
                nisnFeedback.className = 'verify-feedback error';
                nisnVerifyBtn.innerHTML = 'Verifikasi';
                nisnVerifyBtn.classList.remove('loading');
                nisnVerifyBtn.disabled = false;
            });
    };

    // ============================================================
    // FORM VALIDATION (SUBMIT HANDLER)
    // ============================================================
    const playerForm = document.getElementById('playerForm');
    if (playerForm) {
        playerForm.addEventListener('submit', function(e) {
            hideClientAlert();
            const name = document.querySelector('input[name="name"]').value.trim();
            const jerseyNumber = document.querySelector('input[name="jersey_number"]').value.trim();
            const position = document.querySelector('select[name="position"]').value;
            const sportType = document.querySelector('select[name="sport_type"]').value;
            const birthDate = document.querySelector('input[name="birth_date"]').value;
            const birthPlace = document.querySelector('input[name="birth_place"]').value.trim();
            const gender = document.querySelector('input[name="gender"]:checked');
            const nik = document.querySelector('input[name="nik"]').value.trim();
            const nisn = document.querySelector('input[name="nisn"]').value.trim();

            if (!name || !jerseyNumber || !position || !sportType || !birthDate || !birthPlace || !gender || !nik || !nisn) {
                e.preventDefault();
                showClientAlert('Harap lengkapi semua field yang wajib diisi!');
                return false;
            }

            // Validate NIK format
            if (!/^[0-9]{16}$/.test(nik)) {
                e.preventDefault();
                showClientAlert('NIK harus terdiri dari tepat 16 digit angka!');
                return false;
            }

            // Validate NISN format
            if (!/^[0-9]{10}$/.test(nisn)) {
                e.preventDefault();
                showClientAlert('NISN harus terdiri dari tepat 10 digit angka!');
                return false;
            }

            // CHECK VERIFICATION STATUS
            if (nikVerified.value !== '1') {
                e.preventDefault();
                showClientAlert('NIK belum terverifikasi.');
                nikInput.focus();
                return false;
            }

            if (nisnVerified.value !== '1') {
                e.preventDefault();
                showClientAlert('NISN belum terverifikasi.');
                nisnInput.focus();
                return false;
            }

            // Validate KK upload requirement
            const kkInput = document.getElementById('kk_imageFile');
            const hasExistingKkEl = document.getElementById('hasExistingKk');
            const hasExistingKk = hasExistingKkEl ? hasExistingKkEl.value === '1' : false;
            const kkSelected = kkInput && kkInput.files && kkInput.files.length > 0;
            if (!hasExistingKk && !kkSelected) {
                e.preventDefault();
                showClientAlert('File Kartu Keluarga (KK) wajib diupload!');
                return false;
            }

            // Validate birth date
            const birth = new Date(birthDate);
            const today = new Date();
            if (birth > today) {
                e.preventDefault();
                showClientAlert('Tanggal lahir tidak boleh di masa depan!');
                return false;
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
