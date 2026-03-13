<?php
session_start();

// Load database config
$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'operator') {
    header("Location: ../login.php");
    exit;
}

if (!function_exists('adminHasColumn')) {
    function adminHasColumn(PDO $conn, $tableName, $columnName) {
        try {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
            if ($safeTable === '') {
                return false;
            }
            $quotedColumn = $conn->quote((string) $columnName);
            $stmt = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';
$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$current_page = 'berita';
$operator_event_name = 'Event Operator';
$operator_event_image = '';
$operator_event_is_active = true;

if ($operator_id > 0) {
    try {
        $stmtOperator = $conn->prepare("
            SELECT e.name AS event_name, e.image AS event_image,
                   COALESCE(e.is_active, 1) AS event_is_active
            FROM admin_users au
            LEFT JOIN events e ON e.id = au.event_id
            WHERE au.id = ?
            LIMIT 1
        ");
        $stmtOperator->execute([$operator_id]);
        $operator_row = $stmtOperator->fetch(PDO::FETCH_ASSOC);
        $operator_event_name = trim((string)($operator_row['event_name'] ?? '')) !== '' ? (string)$operator_row['event_name'] : 'Event Operator';
        $operator_event_image = trim((string)($operator_row['event_image'] ?? ''));
        $operator_event_is_active = ((int)($operator_row['event_is_active'] ?? 1) === 1);
    } catch (PDOException $e) {
        // keep defaults
    }
}

if (!$operator_event_is_active) {
    $_SESSION['error_message'] = 'Event operator sedang non-aktif. Mode hanya lihat data.';
    header("Location: berita.php");
    exit;
}


// Initialize variables
$errors = [];
$form_data = [
    'judul' => '',
    'slug' => '',
    'konten' => '',
    'penulis' => $admin_name,
    'status' => 'draft',
    'tag' => ''
];

// Fungsi untuk generate slug
function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data = [
        'judul' => trim($_POST['judul'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'konten' => trim($_POST['konten'] ?? ''),
        'penulis' => $admin_name,
        'status' => $_POST['status'] ?? 'draft',
        'tag' => trim($_POST['tag'] ?? '')
    ];
    
    // Validation
    if (empty($form_data['judul'])) {
        $errors['judul'] = "Judul berita harus diisi";
    } elseif (strlen($form_data['judul']) < 5) {
        $errors['judul'] = "Judul minimal 5 karakter";
    } elseif (strlen($form_data['judul']) > 200) {
        $errors['judul'] = "Judul maksimal 200 karakter";
    }
    
    if (empty($form_data['slug'])) {
        // Generate slug from judul
        $form_data['slug'] = generateSlug($form_data['judul']);
    } else {
        $form_data['slug'] = generateSlug($form_data['slug']);
    }
    
    // Check if slug already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM berita WHERE slug = ?");
            $stmt->execute([$form_data['slug']]);
            if ($stmt->rowCount() > 0) {
                // Add timestamp to make it unique
                $form_data['slug'] .= '-' . time();
            }
        } catch (PDOException $e) {
            $errors['database'] = "Gagal memeriksa slug: " . $e->getMessage();
        }
    }
    
    if (empty($form_data['konten'])) {
        $errors['konten'] = "Konten berita harus diisi";
    }
    
    // Handle file upload
    $gambar_path = null;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['gambar'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Check file type
        if (!in_array($file['type'], $allowed_types)) {
            $errors['gambar'] = "Format file harus JPG, PNG, GIF, atau WebP";
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors['gambar'] = "Ukuran file maksimal 5MB";
        }
        
        if (!isset($errors['gambar'])) {
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'berita_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = '../images/berita/';
            
            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $gambar_path = $filename;
            } else {
                $errors['gambar'] = "Gagal mengupload gambar";
            }
        }
    }
    
    // If no errors, insert to database
    if (empty($errors)) {
        try {
            $berita_has_created_by = adminHasColumn($conn, 'berita', 'created_by');
            if ($berita_has_created_by && $operator_id > 0) {
                $stmt = $conn->prepare("
                    INSERT INTO berita (judul, slug, konten, gambar, penulis, status, tag, created_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $form_data['judul'],
                    $form_data['slug'],
                    $form_data['konten'],
                    $gambar_path,
                    $form_data['penulis'],
                    $form_data['status'],
                    $form_data['tag'],
                    $operator_id
                ]);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO berita (judul, slug, konten, gambar, penulis, status, tag, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $form_data['judul'],
                    $form_data['slug'],
                    $form_data['konten'],
                    $gambar_path,
                    $form_data['penulis'],
                    $form_data['status'],
                    $form_data['tag']
                ]);
            }
            
            $_SESSION['success_message'] = "Berita berhasil ditambahkan!";
            header("Location: berita.php");
            exit;
            
        } catch (PDOException $e) {
            $errors['database'] = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Buat Berita Baru</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../pelatih/css/style.css'); ?>">
<link rel="stylesheet" href="css/challenge_form.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/challenge_form.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<!-- Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<style>
    /* Specific overrides for Berita Heritage Design */
    .status-options {
        display: flex;
        gap: 16px;
        margin-top: 8px;
    }
    .status-option {
        flex: 1;
    }
    .status-option input[type="radio"] {
        display: none;
    }
    .status-option label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding: 24px 16px;
        background: #fff;
        border: 2px solid var(--heritage-border);
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-family: var(--font-display);
        font-weight: 800;
        color: var(--heritage-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-size: 0.85rem;
    }
    .status-option input[type="radio"]:checked + label {
        border-color: var(--heritage-text);
        background: var(--heritage-bg);
        color: var(--heritage-text);
        box-shadow: 0 12px 24px rgba(30, 27, 75, 0.1);
        transform: translateY(-2px);
    }
    .status-option label i {
        font-size: 1.75rem;
        margin-bottom: 4px;
    }

    /* File upload Heritage Design */
    .file-upload-container {
        background: #fff;
        border: 2px dashed var(--heritage-border);
        border-radius: 28px;
        padding: 48px 24px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        box-shadow: var(--soft-shadow);
    }
    .file-upload-container:hover {
        border-color: var(--heritage-text);
        background: #fdfcfb;
    }
    .file-upload-icon {
        font-size: 3.5rem;
        color: var(--heritage-text);
        margin-bottom: 20px;
    }
    .file-preview {
        margin-top: 32px;
        text-align: center;
        animation: revealUp 0.6s ease-out;
    }
    .file-preview img {
        max-width: 100%;
        max-height: 350px;
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        border: 4px solid #fff;
    }
    .file-info {
        margin-top: 16px;
        font-family: var(--font-display);
        font-weight: 700;
        color: var(--heritage-text);
        font-size: 0.95rem;
    }

    /* Character count enhancement */
    .char-count {
        font-family: var(--font-display);
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--heritage-text-muted);
        text-align: right;
        margin-top: 10px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Summernote Heritage Style */
    .note-editor {
        border-radius: 20px !important;
        overflow: hidden;
        border: 2px solid var(--heritage-border) !important;
        background: #fff !important;
        box-shadow: var(--soft-shadow);
    }
    .note-editor.note-frame:focus-within {
        border-color: var(--heritage-text) !important;
    }
    .note-toolbar {
        background: #f8f7f4 !important;
        border-bottom: 1px solid var(--heritage-border) !important;
        padding: 10px 15px !important;
    }
    .note-btn {
        background: #fff !important;
        border: 1px solid var(--heritage-border) !important;
        border-radius: 8px !important;
        padding: 5px 10px !important;
    }
    .note-editable {
        padding: 24px !important;
        font-family: var(--font-body) !important;
        font-size: 1rem !important;
        line-height: 1.6 !important;
    }
</style>
</head>
<body>

<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar reveal">
            <div class="greeting">
                <h1>Buat Berita Baru 📰</h1>
                <p>Publikasikan informasi dan artikel terkini Anda</p>
            </div>
            
            <div class="user-actions">
                <a href="../operator/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </a>
            </div>
        </div>

        <div class="challenge-container">
            <!-- Heritage Header -->
            <header class="header reveal d-1">
                <h1>
                    <i class="fas fa-plus-circle"></i>
                    Tulis Berita
                </h1>
                <a href="berita.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
            </header>

            <!-- ERROR MESSAGES -->
            <?php if (isset($errors['database'])): ?>
            <div class="alert alert-danger reveal">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $errors['database']; ?>
            </div>
            <?php endif; ?>

            <!-- CREATE BERITA FORM -->
            <div class="form-container">
                <form method="POST" action="" enctype="multipart/form-data" id="beritaForm">
                    
                    <!-- Section 1: Core Information -->
                    <div class="form-section reveal d-1">
                        <h2 class="section-title">
                            <i class="fas fa-newspaper"></i>
                            Informasi Utama
                        </h2>
                        
                        <div class="form-grid">
                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label">
                                    Judul Berita <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="judul" 
                                       name="judul" 
                                       class="form-control <?php echo isset($errors['judul']) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($form_data['judul'] ?? ''); ?>"
                                       placeholder="Tulis judul berita yang menarik perhatian"
                                       maxlength="200"
                                       required>
                                <div class="char-count">
                                    <span id="judulCount">0</span>/200 Karakter
                                </div>
                                <?php if (isset($errors['judul'])): ?>
                                    <span class="error"><?php echo $errors['judul']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Slug URL (SEO)
                                </label>
                                <input type="text" 
                                       id="slug" 
                                       name="slug" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($form_data['slug'] ?? ''); ?>"
                                       placeholder="judul-berita-anda">
                                <small style="color: var(--heritage-text-muted); display: block; margin-top: 8px; font-weight: 600; font-size: 0.75rem;">
                                    Generate otomatis dari judul jika dikosongkan.
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Identitas Penulis
                                </label>
                                <input type="text" 
                                       id="penulis" 
                                       name="penulis" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($form_data['penulis'] ?? ''); ?>"
                                       readonly>
                                <small style="color: var(--heritage-text-muted); display: block; margin-top: 8px; font-weight: 600; font-size: 0.75rem;">
                                    Terkunci sesuai akun operator yang masuk.
                                </small>
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label">
                                    Tags & Label
                                </label>
                                <input type="text" 
                                       id="tag" 
                                       name="tag" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($form_data['tag'] ?? ''); ?>"
                                       placeholder="Misal: futsal, turnamen, prestasi">
                                <small style="color: var(--heritage-text-muted); display: block; margin-top: 8px; font-weight: 600; font-size: 0.75rem;">
                                    Pisahkan setiap tag menggunakan tanda koma (,).
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Featured Image -->
                    <div class="form-section reveal d-2">
                        <h2 class="section-title">
                            <i class="fas fa-camera"></i>
                            Gambar Utama
                        </h2>
                        
                        <div class="form-group">
                            <div class="file-upload-container" id="gambarUpload">
                                <input type="file" 
                                       id="gambar" 
                                       name="gambar" 
                                       class="file-upload-input"
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                <div style="font-family: var(--font-display); font-weight: 800; color: var(--heritage-text); font-size: 1.25rem; margin-bottom: 8px; letter-spacing: -0.01em;">Unggah Visual Berita</div>
                                <div style="color: var(--heritage-text-muted); font-size: 0.95rem; font-weight: 500;">Klik atau tarik file ke sini (Format: JPG/PNG/WebP, Maks: 5MB)</div>
                                
                                <div class="file-preview" id="gambarPreview" style="display: none;">
                                    <img id="gambarPreviewImg" src="" alt="Pratinjau Gambar">
                                    <div class="file-info" id="gambarFileInfo"></div>
                                </div>
                            </div>
                            <?php if (isset($errors['gambar'])): ?>
                                <span class="error"><?php echo $errors['gambar']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section 3: Article Content -->
                    <div class="form-section reveal d-2">
                        <h2 class="section-title">
                            <i class="fas fa-edit"></i>
                            Isi Artikel
                        </h2>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Konten Narasi <span class="required">*</span>
                            </label>
                            <textarea id="konten" 
                                      name="konten" 
                                      class="<?php echo isset($errors['konten']) ? 'is-invalid' : ''; ?>"
                                      required><?php echo htmlspecialchars($form_data['konten'] ?? ''); ?></textarea>
                            <div class="char-count">
                                <span id="kontenCount">0</span> Karakter Terinput
                            </div>
                            <?php if (isset($errors['konten'])): ?>
                                <span class="error"><?php echo $errors['konten']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section 4: Publishing Settings -->
                    <div class="form-section reveal d-2">
                        <h2 class="section-title">
                            <i class="fas fa-paper-plane"></i>
                            Opsi Publikasi
                        </h2>
                        
                        <div class="form-group">
                            <label class="form-label">Tentukan Status Berita</label>
                            <div class="status-options">
                                <div class="status-option">
                                    <input type="radio" id="status_draft" name="status" value="draft" <?php echo $form_data['status'] == 'draft' ? 'checked' : ''; ?>>
                                    <label for="status_draft">
                                        <i class="fas fa-pencil-ruler"></i>
                                        Draft
                                    </label>
                                </div>
                                
                                <div class="status-option">
                                    <input type="radio" id="status_published" name="status" value="published" <?php echo $form_data['status'] == 'published' ? 'checked' : ''; ?>>
                                    <label for="status_published">
                                        <i class="fas fa-broadcast-tower"></i>
                                        Publish
                                    </label>
                                </div>
                                
                                <div class="status-option">
                                    <input type="radio" id="status_archived" name="status" value="archived" <?php echo $form_data['status'] == 'archived' ? 'checked' : ''; ?>>
                                    <label for="status_archived">
                                        <i class="fas fa-box-archive"></i>
                                        Arsipkan
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="form-actions reveal d-3">
                        <button type="button" class="btn btn-secondary" id="customResetBtn">
                            <i class="fas fa-undo"></i>
                            Batal & Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Artikel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<!-- Summernote JS -->
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-id-ID.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const judulInput = document.getElementById('judul');
    const slugInput = document.getElementById('slug');
    const gambarInput = document.getElementById('gambar');
    const gambarPreview = document.getElementById('gambarPreview');
    const gambarPreviewImg = document.getElementById('gambarPreviewImg');
    const gambarFileInfo = document.getElementById('gambarFileInfo');
    let slugManuallyEdited = false;

    // Summernote Heritage Initialization
    if (window.jQuery && $('#konten').length) {
        $('#konten').summernote({
            placeholder: 'Tuangkan narasi berita Anda secara detail di sini...',
            tabsize: 2,
            height: 400,
            lang: 'id-ID',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['fontname', ['fontname']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture', 'video', 'table']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ],
            callbacks: {
                onChange: function() {
                    const html = $('#konten').summernote('code') || '';
                    const plainText = $('<div>').html(html).text().trim();
                    document.getElementById('kontenCount').textContent = plainText.length;
                }
            }
        });
    }

    // Heritage Slug System
    judulInput.addEventListener('input', function() {
        document.getElementById('judulCount').textContent = this.value.length;
        if (!slugManuallyEdited) {
            const slug = this.value
                .toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
            slugInput.value = slug;
        }
    });

    slugInput.addEventListener('input', function() {
        slugManuallyEdited = this.value.trim() !== '';
    });

    // Visual Media Handler
    gambarInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                gambarPreviewImg.src = e.target.result;
                gambarFileInfo.textContent = `${file.name} | ${(file.size / 1024 / 1024).toFixed(2)} MB`;
                gambarPreview.style.display = 'block';
            };
            
            reader.readAsDataURL(file);
        }
    });

    // Reset with Heritage Confirmation
    document.getElementById('customResetBtn').addEventListener('click', function(e) {
        if (!confirm('Apakah Anda yakin ingin membatalkan penulisan dan meriset seluruh formulir?')) {
            return;
        }
        document.getElementById('beritaForm').reset();
        $('#konten').summernote('code', '');
        gambarPreview.style.display = 'none';
        slugManuallyEdited = false;
        document.getElementById('judulCount').textContent = '0';
        document.getElementById('kontenCount').textContent = '0';
        toastr.info('Formulir telah dibersihkan.');
    });

    // Validation Shield
    const form = document.getElementById('beritaForm');
    form.addEventListener('submit', function(e) {
        const judul = judulInput.value.trim();
        const kontenHtml = $('#konten').summernote('code');
        const kontenText = $('<div>').html(kontenHtml).text().trim();

        if (judul.length < 5) {
            e.preventDefault();
            toastr.warning('Judul artikel terlalu pendek (Min. 5 karakter)');
            judulInput.focus();
            return;
        }

        if (!kontenText || kontenHtml === '<p><br></p>') {
            e.preventDefault();
            toastr.warning('Harap isi narasi berita sebelum menyimpan');
            $('#konten').summernote('focus');
            return;
        }
    });

    // Initial State
    document.getElementById('judulCount').textContent = judulInput.value.length;
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
