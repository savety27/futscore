<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$event_helper_path = __DIR__ . '/../admin/includes/event_helpers.php';
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
    'position_detail' => '', // Tetap ada di array tapi tidak diisi manual
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
            require_once 'includes/footer.php';
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

<div class="container">
    <div class="header">
        <h1><i class="fas fa-user-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i> 
            <?php echo $action === 'add' ? 'Tambah' : 'Ubah'; ?> Pemain
        </h1>
        <a href="players.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
        </a>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars(urldecode($_GET['error'])); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✓</span>
            <span>Pemain berhasil <?php echo $_GET['msg'] === 'added' ? 'ditambahkan' : 'diperbarui'; ?>!</span>
        </div>
    <?php endif; ?>

    <div class="form-container">
        <form action="player_actions.php" method="POST" enctype="multipart/form-data" id="playerForm">
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            <input type="hidden" name="id" value="<?php echo $player_id; ?>">
            <input type="hidden" id="hasExistingKk" value="<?php echo !empty($player['kk_image']) ? '1' : '0'; ?>">
            
            <!-- Basic Information Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-user-circle"></i>
                   Profil 
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
                            <span class="required-field">Event</span>
                            <span class="note">Wajib</span>
                        </label>
                        <select name="sport_type" class="form-control" required>
                            <option value="">Pilih Event</option>
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
                        <select name="position" class="form-control" required>
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
                        <select name="dominant_foot" class="form-control">
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
            <div class="form-section">
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
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-id-card"></i>
                    Identitas & Verifikasi
                </h2>
                <p class="note" style="margin-bottom: 20px; color: var(--gray);">
                    NIK dan NISN wajib diverifikasi sebelum data dapat disimpan.
                </p>
                
                <div class="form-grid">
                    <!-- NIK -->
                    <div class="form-group">
                        <label class="form-label">
                            <span class="required-field">NIK</span>
                            <span class="note">Wajib (16 digit)</span>
                        </label>
                        <div class="verify-input-wrapper">
                            <input type="text" name="nik" class="form-control verify-input" 
                                   id="nikInput"
                                   placeholder="Masukkan 16-digit NIK" required
                                   maxlength="16"
                                   inputmode="numeric"
                                   pattern="[0-9]{16}"
                                   title="NIK harus terdiri dari tepat 16 digit angka"
                                   value="<?php echo htmlspecialchars($player['nik'] ?? ''); ?>">
                            <button type="button" class="verify-btn" id="nikVerifyBtn" onclick="verifyNIK()" disabled>
                                <i class="fas fa-shield-alt"></i> Verifikasi
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
                            <span class="note">Wajib (10 digit)</span>
                        </label>
                        <div class="verify-input-wrapper">
                            <input type="text" name="nisn" class="form-control verify-input" 
                                   id="nisnInput"
                                   placeholder="Masukkan 10-digit NISN" required
                                   maxlength="10"
                                   inputmode="numeric"
                                   pattern="[0-9]{10}"
                                   title="NISN harus terdiri dari tepat 10 digit angka"
                                   value="<?php echo htmlspecialchars($player['nisn'] ?? ''); ?>">
                            <button type="button" class="verify-btn" id="nisnVerifyBtn" onclick="verifyNISN()" disabled>
                                <i class="fas fa-shield-alt"></i> Verifikasi
                            </button>
                        </div>
                        <input type="hidden" name="nisn_verified" id="nisnVerified" value="0">
                        <div class="verify-feedback" id="nisnFeedback"></div>
                        <div class="verify-details" id="nisnDetails" style="display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- Photo Upload Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-camera"></i>
                    Foto Profil 
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">
                            <span>Foto Profil</span>
                            <span class="note">Maks 5MB (JPG, PNG, GIF)</span>
                        </label>
                        
                        <?php if (!empty($player['photo'])): ?>
                        <div class="current-photo" style="margin-bottom: 20px;">
                            <p style="font-size: 14px; color: var(--gray); margin-bottom: 10px;">
                                <strong>Foto :</strong>
                            </p>
                            <div class="file-item">
                                <img src="../images/players/<?php echo htmlspecialchars($player['photo'] ?? ''); ?>" 
                                     alt="Current Photo" style="width: 60px; height: 60px;">
                                <div>
                                    <div><strong><?php echo htmlspecialchars($player['photo'] ?? ''); ?></strong></div>
                                    <div style="font-size: 12px; color: var(--gray);">Klik untuk mengganti foto</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="file-upload" id="photoUpload">
                            <div>
                                <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: var(--primary); margin-bottom: 10px;"></i>
                                <p style="margin: 0; color: var(--gray);">Klik untuk unggah atau seret & lepas</p>
                                <p style="margin: 5px 0 0 0; font-size: 12px; color: var(--gray);">Maksimal 5MB</p>
                            </div>
                            <input type="file" name="photo" id="photoFile" accept="image/*">
                        </div>
                        <div class="file-preview" id="photoPreview"></div>
                    </div>
                </div>
            </div>

            <!-- Document Upload Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-file-alt"></i>
                    Dokumen
                </h2>
                
                <p class="note" style="margin-bottom: 20px; color: var(--gray);">
                    Unggah dokumen pendukung. Kartu Keluarga wajib, dokumen lain opsional. Maksimal 5MB per file.
                </p>
                
                <div class="form-grid">
                    <?php
                    $documents = [
                        'ktp_image' => ['label' => 'KTP / KIA / Kartu Pelajar / Kartu Identitas', 'current' => $player['ktp_image']],
                        'kk_image' => ['label' => 'Kartu Keluarga', 'current' => $player['kk_image']],
                        'birth_cert_image' => ['label' => 'Akta Lahir / Surat Ket. Lahir', 'current' => $player['birth_cert_image']],
                        'diploma_image' => ['label' => 'Ijazah / Biodata Raport / Kartu NISN', 'current' => $player['diploma_image']]
                    ];
                    
                    foreach ($documents as $key => $doc):
                        $is_kk = ($key === 'kk_image');
                    ?>
                    <div class="form-group">
                        <label class="form-label">
                            <span class="<?php echo $is_kk ? 'required-field' : ''; ?>"><?php echo $doc['label']; ?></span>
                            <span class="note"><?php echo $is_kk ? 'Wajib' : 'Opsional'; ?></span>
                        </label>
                        <?php if ($is_kk): ?>
                            <p class="kk-warning">* FILE KARTU KELUARGA WAJIB DIUPLOAD!</p>
                        <?php endif; ?>
                        
                        <?php if (!empty($doc['current'])): ?>
                        <div class="current-photo" style="margin-bottom: 10px;">
                            <p style="font-size: 12px; color: var(--gray); margin-bottom: 5px;">
                                <strong>Foto :</strong> <?php echo htmlspecialchars($doc['current'] ?? ''); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="file-upload" id="<?php echo $key; ?>Upload">
                            <div>
                                <i class="fas fa-cloud-upload-alt" style="font-size: 20px; color: var(--primary); margin-bottom: 8px;"></i>
                                <p style="margin: 0; color: var(--gray); font-size: 14px;">Unggah <?php echo $doc['label']; ?></p>
                            </div>
                            <input type="file" name="<?php echo $key; ?>" id="<?php echo $key; ?>File" accept="image/*">
                        </div>
                        <div class="file-preview" id="<?php echo $key; ?>Preview"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Skills Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-chart-line"></i>
                    Keahlian (Rentang 0-10)
                </h2>
                
                <p class="note" style="margin-bottom: 20px; color: var(--gray);">
                    Nilai Default: 5
                </p>
                
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
                        <div class="slider-container">
                            <input type="range" name="<?php echo $key; ?>" class="slider" min="0" max="10" 
                                   value="<?php echo $value; ?>" id="<?php echo $key; ?>Slider"
                                   oninput="document.getElementById('<?php echo $key; ?>Value').textContent = this.value">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Status Section -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-check-circle"></i>
                    Status Pemain
                </h2>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="status_active" name="status" value="active" <?php echo ($player['status'] ?? 'active') === 'active' ? 'checked' : ''; ?>>
                        <label for="status_active" style="font-weight: normal;">Pemain Aktif</label>
                    </div>
                    <small style="color: #666;">Pemain aktif akan tampil dalam sistem</small>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <a href="players.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $action === 'add' ? 'Simpan Pemain' : 'Perbarui Pemain'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Additional styles for the form */
.main {
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0;
}

.header {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header h1 {
    font-size: 28px;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 15px;
}

.form-container {
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    padding: 30px;
    margin-bottom: 30px;
}

.form-section {
    margin-bottom: 40px;
    border-bottom: 2px solid #eee;
    padding-bottom: 30px;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 20px;
    color: var(--primary);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e1e5eb;
    border-radius: 12px;
    font-size: 15px;
    transition: var(--transition);
    background-color: #fff;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

.date-input {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.radio-group {
    display: flex;
    gap: 10px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 8px 12px;
    border: 2px solid #e1e5eb;
    border-radius: 8px;
    transition: var(--transition);
}

.radio-option:hover {
    border-color: var(--primary);
    background: #f8f9ff;
}

.radio-option input[type="radio"]:checked + span {
    color: var(--primary);
    font-weight: 600;
}

.file-upload {
    border: 2px dashed #e1e5eb;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: var(--transition);
    cursor: pointer;
    background: #fafbff;
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.file-upload:hover {
    border-color: var(--primary);
    background: #f0f4ff;
}

.file-upload.dragover {
    border-color: var(--secondary);
    background: #fff9e6;
}

.file-upload input[type="file"] {
    display: none;
}

.file-preview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.file-item {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.file-item img {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    object-fit: cover;
}

.skill-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.skill-item {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.skill-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.skill-name {
    font-weight: 600;
    color: var(--dark);
}

.skill-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary);
}

.slider-container {
    display: flex;
    align-items: center;
    gap: 15px;
}

.slider {
    flex: 1;
    -webkit-appearance: none;
    appearance: none;
    height: 6px;
    background: #ddd;
    border-radius: 3px;
    outline: none;
}

.slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--primary);
    cursor: pointer;
    transition: var(--transition);
}

.slider::-webkit-slider-thumb:hover {
    transform: scale(1.2);
}

.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 30px;
    border-top: 2px solid #eee;
}

.btn {
    padding: 14px 28px;
    border-radius: 12px;
    border: none;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary) 0%, #1a365d 100%);
    color: white;
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(10, 36, 99, 0.3);
}

.btn-secondary {
    background: #f8f9fa;
    color: var(--dark);
    border: 2px solid #e1e5eb;
}

.btn-secondary:hover {
    background: #e9ecef;
    border-color: #ced4da;
}

.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    animation: slideDown 0.3s ease-out;
}

.alert-success {
    background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
    color: var(--success);
    border-left: 4px solid var(--success);
}

.alert-danger {
    background: linear-gradient(135deg, #FFE5E5 0%, #FFCCCC 100%);
    color: var(--danger);
    border-left: 4px solid var(--danger);
}

  .note {
      font-size: 12px;
      color: var(--gray);
      margin-top: 5px;
      font-style: italic;
  }

  .kk-warning {
      margin: 6px 0 0;
      color: var(--danger);
      font-weight: bold;
      font-size: 12px;
  }

.required-field::after {
    content: " *";
    color: var(--danger);
    font-weight: bold;
}

.verify-input-wrapper {
    display: flex;
    gap: 10px;
    align-items: stretch;
}

.verify-input-wrapper .verify-input {
    flex: 1;
}

.verify-btn {
    padding: 10px 18px;
    border: none;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, var(--primary) 0%, #1a365d 100%);
    color: white;
    box-shadow: 0 3px 10px rgba(10, 36, 99, 0.2);
}

.verify-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.3);
}

.verify-btn:disabled {
    background: #ccc;
    color: #888;
    cursor: not-allowed;
    box-shadow: none;
}

.verify-btn.loading {
    background: linear-gradient(135deg, #6C757D 0%, #495057 100%);
    pointer-events: none;
}

.verify-btn.verified {
    background: linear-gradient(135deg, var(--success) 0%, #1B5E20 100%);
}

.verify-feedback {
    margin-top: 8px;
    font-size: 12px;
    color: var(--gray);
    min-height: 18px;
}

.verify-feedback.warning {
    color: var(--warning);
}

.verify-feedback.error {
    color: var(--danger);
    font-weight: 600;
}

.verify-feedback.success {
    color: var(--success);
    font-weight: 600;
}

.verify-details {
    margin-top: 10px;
    padding: 12px 15px;
    background: linear-gradient(135deg, #E8F5E9, #C8E6C9);
    border-radius: 10px;
    border-left: 4px solid var(--success);
    font-size: 13px;
    line-height: 1.6;
    animation: slideDown 0.3s ease-out;
}

.verify-details .detail-row {
    display: flex;
    justify-content: space-between;
    padding: 2px 0;
}

.verify-details .detail-label {
    color: var(--gray);
    font-weight: 500;
}

.verify-details .detail-value {
    color: var(--dark);
    font-weight: 600;
}

@keyframes verifyPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile Responsive */
@media screen and (max-width: 768px) {
    .header {
        flex-direction: column;
        gap: 15px;
        align-items: center;
        text-align: center;
        padding: 20px;
    }
    
    .header h1 {
        font-size: 24px;
        justify-content: center;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .date-input {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 15px;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .skill-grid {
        grid-template-columns: 1fr;
    }
    
    .radio-group {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media screen and (max-width: 480px) {
    .form-container {
        padding: 20px 15px;
    }
    
    .section-title {
        font-size: 18px;
    }
    
    .file-upload {
        padding: 15px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
        if (!file.type.startsWith('image/')) { alert('Harap pilih file gambar!'); return; }
        if (file.size > 5 * 1024 * 1024) { alert('Ukuran file terlalu besar! Maksimal 5MB.'); return; }
        const reader = new FileReader();
        reader.onload = function(e) {
            previewContainer.innerHTML = `
                <div class="file-item">
                    <img src="${e.target.result}" alt="Preview">
                    <div>
                        <div><strong>${file.name}</strong></div>
                        <div style="font-size: 12px; color: var(--gray);">${formatFileSize(file.size)}</div>
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

    if (nikInput) {
        nikInput.addEventListener('input', function(e) {
            const numericValue = e.target.value.replace(/[^0-9]/g, '').slice(0, 16);
            nikInput.value = numericValue;
            // Reset verification when value changes
            nikVerified.value = '0';
            nikDetails.style.display = 'none';
            nikVerifyBtn.classList.remove('verified');
            nikInput.style.borderColor = '#e1e5eb';

            if (numericValue.length === 16) {
                nikFeedback.textContent = '16 digit — Klik "Verifikasi" untuk memvalidasi';
                nikFeedback.className = 'verify-feedback warning';
                nikVerifyBtn.disabled = false;
                nikInput.style.borderColor = 'var(--warning)';
            } else if (numericValue.length > 0) {
                nikFeedback.textContent = `Kurang ${16 - numericValue.length} digit (${numericValue.length}/16)`;
                nikFeedback.className = 'verify-feedback warning';
                nikVerifyBtn.disabled = true;
            } else {
                nikFeedback.textContent = 'NIK harus diisi — 16 digit angka';
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
        if (nikInput.value && nikInput.value.length === 16) {
            nikFeedback.textContent = '16 digit — Klik "Verifikasi" untuk memvalidasi';
            nikFeedback.className = 'verify-feedback warning';
            nikVerifyBtn.disabled = false;
        }
    }

    // NIK Verify via AJAX
    window.verifyNIK = function() {
        const value = nikInput.value.trim();
        if (value.length !== 16) return;

        nikVerifyBtn.disabled = true;
        nikVerifyBtn.classList.add('loading');
        nikVerifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memverifikasi...';
        nikFeedback.textContent = 'Sedang memverifikasi NIK...';
        nikFeedback.className = 'verify-feedback';
        nikFeedback.style.animation = 'verifyPulse 1s infinite';

        const formData = new FormData();
        formData.append('type', 'nik');
        formData.append('value', value);
        if (playerId > 0) formData.append('exclude_player_id', playerId);

        fetch('../api/verify_identity.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                nikFeedback.style.animation = '';
                if (data.verified) {
                    nikVerified.value = '1';
                    nikFeedback.textContent = '✓ ' + data.message;
                    nikFeedback.className = 'verify-feedback success';
                    nikInput.style.borderColor = 'var(--success)';
                    nikVerifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Terverifikasi';
                    nikVerifyBtn.classList.remove('loading');
                    nikVerifyBtn.classList.add('verified');

                    // Show details
                    if (data.details) {
                        let html = '<strong>📋 Data NIK:</strong><br>';
                        if (data.details.provinsi) html += `<div class="detail-row"><span class="detail-label">Provinsi</span><span class="detail-value">${data.details.provinsi}</span></div>`;
                        if (data.details.tanggal_lahir) html += `<div class="detail-row"><span class="detail-label">Tgl Lahir</span><span class="detail-value">${data.details.tanggal_lahir}</span></div>`;
                        if (data.details.jenis_kelamin) html += `<div class="detail-row"><span class="detail-label">Jenis Kelamin</span><span class="detail-value">${data.details.jenis_kelamin}</span></div>`;
                        nikDetails.innerHTML = html;
                        nikDetails.style.display = 'block';
                    }
                } else {
                    nikVerified.value = '0';
                    nikFeedback.textContent = '✗ ' + data.message;
                    nikFeedback.className = 'verify-feedback error';
                    nikInput.style.borderColor = 'var(--danger)';
                    nikVerifyBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Verifikasi';
                    nikVerifyBtn.classList.remove('loading');
                    nikVerifyBtn.disabled = false;
                    nikDetails.style.display = 'none';
                }
            })
            .catch(err => {
                nikFeedback.style.animation = '';
                nikFeedback.textContent = '⚠ Gagal menghubungi server verifikasi';
                nikFeedback.className = 'verify-feedback error';
                nikVerifyBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Verifikasi';
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
            nisnInput.value = numericValue;
            // Reset verification
            nisnVerified.value = '0';
            nisnVerifyBtn.classList.remove('verified');
            nisnInput.style.borderColor = '#e1e5eb';
            const nisnDetailsEl = document.getElementById('nisnDetails');
            if (nisnDetailsEl) nisnDetailsEl.style.display = 'none';

            if (numericValue.length === 10) {
                nisnFeedback.textContent = '10 digit — Klik "Verifikasi" untuk memvalidasi';
                nisnFeedback.className = 'verify-feedback warning';
                nisnVerifyBtn.disabled = false;
                nisnInput.style.borderColor = 'var(--warning)';
            } else if (numericValue.length > 0) {
                nisnFeedback.textContent = `Kurang ${10 - numericValue.length} digit (${numericValue.length}/10)`;
                nisnFeedback.className = 'verify-feedback warning';
                nisnVerifyBtn.disabled = true;
            } else {
                nisnFeedback.textContent = 'NISN harus diisi — 10 digit angka';
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
        if (nisnInput.value && nisnInput.value.length === 10) {
            nisnFeedback.textContent = '10 digit — Klik "Verifikasi" untuk memvalidasi';
            nisnFeedback.className = 'verify-feedback warning';
            nisnVerifyBtn.disabled = false;
        }
    }

    // NISN Verify via AJAX
    window.verifyNISN = function() {
        const value = nisnInput.value.trim();
        if (value.length !== 10) return;

        nisnVerifyBtn.disabled = true;
        nisnVerifyBtn.classList.add('loading');
        nisnVerifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memverifikasi...';
        nisnFeedback.textContent = 'Sedang memverifikasi NISN...';
        nisnFeedback.className = 'verify-feedback';
        nisnFeedback.style.animation = 'verifyPulse 1s infinite';

        const formData = new FormData();
        formData.append('type', 'nisn');
        formData.append('value', value);
        if (playerId > 0) formData.append('exclude_player_id', playerId);

        fetch('../api/verify_identity.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                nisnFeedback.style.animation = '';
                if (data.verified) {
                    nisnVerified.value = '1';
                    nisnFeedback.textContent = '✓ ' + data.message;
                    nisnFeedback.className = 'verify-feedback success';
                    nisnInput.style.borderColor = 'var(--success)';
                    nisnVerifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Terverifikasi';
                    nisnVerifyBtn.classList.remove('loading');
                    nisnVerifyBtn.classList.add('verified');

                    // Show NISN details
                    const nisnDetailsEl = document.getElementById('nisnDetails');
                    if (data.details && nisnDetailsEl) {
                        let html = '<strong>📋 Data NISN:</strong><br>';
                        if (data.details.tahun_lahir) html += `<div class="detail-row"><span class="detail-label">Tahun Lahir</span><span class="detail-value">${data.details.tahun_lahir}</span></div>`;
                        if (data.details.usia) html += `<div class="detail-row"><span class="detail-label">Usia</span><span class="detail-value">${data.details.usia}</span></div>`;
                        if (data.details.perkiraan_jenjang) html += `<div class="detail-row"><span class="detail-label">Jenjang</span><span class="detail-value">${data.details.perkiraan_jenjang}</span></div>`;
                        if (data.details.kode_tengah) html += `<div class="detail-row"><span class="detail-label">Kode Tengah</span><span class="detail-value">${data.details.kode_tengah}</span></div>`;
                        if (data.details.nomor_urut) html += `<div class="detail-row"><span class="detail-label">No. Urut</span><span class="detail-value">${data.details.nomor_urut}</span></div>`;
                        nisnDetailsEl.innerHTML = html;
                        nisnDetailsEl.style.display = 'block';
                    }
                } else {
                    nisnVerified.value = '0';
                    nisnFeedback.textContent = '✗ ' + data.message;
                    nisnFeedback.className = 'verify-feedback error';
                    nisnInput.style.borderColor = 'var(--danger)';
                    nisnVerifyBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Verifikasi';
                    nisnVerifyBtn.classList.remove('loading');
                    nisnVerifyBtn.disabled = false;
                }
            })
            .catch(err => {
                nisnFeedback.style.animation = '';
                nisnFeedback.textContent = '⚠ Gagal menghubungi server verifikasi';
                nisnFeedback.className = 'verify-feedback error';
                nisnVerifyBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Verifikasi';
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
                alert('Harap lengkapi semua field yang wajib diisi!');
                return false;
            }

            // Validate NIK format
            if (!/^[0-9]{16}$/.test(nik)) {
                e.preventDefault();
                alert('NIK harus terdiri dari tepat 16 digit angka!');
                return false;
            }

            // Validate NISN format
            if (!/^[0-9]{10}$/.test(nisn)) {
                e.preventDefault();
                alert('NISN harus terdiri dari tepat 10 digit angka!');
                return false;
            }

            // CHECK VERIFICATION STATUS
            if (nikVerified.value !== '1') {
                e.preventDefault();
                alert('❌ NIK belum terverifikasi!\n\nSilakan klik tombol "Verifikasi" pada kolom NIK terlebih dahulu.');
                nikInput.focus();
                return false;
            }

            if (nisnVerified.value !== '1') {
                e.preventDefault();
                alert('❌ NISN belum terverifikasi!\n\nSilakan klik tombol "Verifikasi" pada kolom NISN terlebih dahulu.');
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
                alert('File Kartu Keluarga (KK) wajib diupload!');
                return false;
            }

            // Validate birth date
            const birth = new Date(birthDate);
            const today = new Date();
            if (birth > today) {
                e.preventDefault();
                alert('Tanggal lahir tidak boleh di masa depan!');
                return false;
            }
        });
    }

    // Set default birth date to 18 years ago
    const birthDateInput = document.querySelector('input[name="birth_date"]');
    if (birthDateInput && !birthDateInput.value) {
        const defaultDate = new Date();
        defaultDate.setFullYear(defaultDate.getFullYear() - 18);
        birthDateInput.value = defaultDate.toISOString().split('T')[0];
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
