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

// Get berita ID
$berita_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($berita_id <= 0) {
    header("Location: berita.php");
    exit;
}


// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_username = trim((string)($_SESSION['admin_username'] ?? ''));
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
$berita_data = null;
$berita_has_created_by = adminHasColumn($conn, 'berita', 'created_by');
$ownership_where_sql = '';
$ownership_params = [];
if ($berita_has_created_by && $operator_id > 0) {
    $ownership_where_sql = " AND created_by = ?";
    $ownership_params[] = $operator_id;
} else {
    $ownership_where_sql = " AND (penulis = ? OR penulis = ?)";
    $ownership_params[] = $admin_name;
    $ownership_params[] = $admin_username;
}

// Fetch berita data
try {
    $stmt = $conn->prepare("SELECT * FROM berita WHERE id = ?" . $ownership_where_sql);
    $stmt->execute(array_merge([$berita_id], $ownership_params));
    $berita_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$berita_data) {
        header("Location: berita.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error fetching berita data: " . $e->getMessage());
}

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
        'tag' => trim($_POST['tag'] ?? ''),
        'delete_gambar' => isset($_POST['delete_gambar']) ? 1 : 0
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
    
    // Check if slug already exists (excluding current berita)
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM berita WHERE slug = ? AND id != ?");
            $stmt->execute([$form_data['slug'], $berita_id]);
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
    
    // Handle file upload and deletion
    $gambar_path = $berita_data['gambar'];
    
    // If delete gambar is checked
    if ($form_data['delete_gambar'] && $gambar_path) {
        if (file_exists('../images/berita/' . $gambar_path)) {
            @unlink('../images/berita/' . $gambar_path);
        }
        $gambar_path = null;
    }
    
    // If new gambar is uploaded
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
                // Delete old gambar if exists
                if ($gambar_path && file_exists('../images/berita/' . $gambar_path)) {
                    @unlink('../images/berita/' . $gambar_path);
                }
                $gambar_path = $filename;
            } else {
                $errors['gambar'] = "Gagal mengupload gambar";
            }
        }
    }
    
    // If no errors, update database
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                UPDATE berita SET 
                    judul = ?, 
                    slug = ?, 
                    konten = ?, 
                    gambar = ?, 
                    penulis = ?, 
                    status = ?, 
                    tag = ?, 
                    updated_at = NOW()
                WHERE id = ?" . $ownership_where_sql . "
            ");
            
            $stmt->execute(array_merge([
                $form_data['judul'],
                $form_data['slug'],
                $form_data['konten'],
                $gambar_path,
                $form_data['penulis'],
                $form_data['status'],
                $form_data['tag'],
                $berita_id
            ], $ownership_params));
            
            $_SESSION['success_message'] = "Berita berhasil diperbarui!";
            header("Location: berita.php");
            exit;
            
        } catch (PDOException $e) {
            $errors['database'] = "Gagal memperbarui data: " . $e->getMessage();
        }
    } else {
        // Update berita_data with new form data for display
        $berita_data = array_merge($berita_data, $form_data);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Berita</title>
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

    /* PREMIUM MEDIA ATTACHMENT REDESIGN */
    .media-upload-wrapper {
        position: relative;
        width: 100%;
    }

    .media-dropzone {
        background: #fff;
        border: 2px dashed var(--heritage-border);
        border-radius: 32px;
        padding: 60px 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 20px;
    }

    .media-dropzone::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2003/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
    }

    .media-dropzone:hover {
        border-color: var(--heritage-text);
        background: var(--heritage-bg);
        transform: translateY(-2px);
    }

    .media-dropzone.drag-over {
        border-color: var(--heritage-gold);
        background: #fdfcfb;
        box-shadow: 0 20px 40px rgba(180, 83, 9, 0.05);
    }

    .media-dropzone.drag-over::before {
        opacity: 0.03;
    }

    .upload-icon-circle {
        width: 80px;
        height: 80px;
        background: var(--heritage-bg);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--heritage-text);
        font-size: 2rem;
        border: 1px solid var(--heritage-border);
        transition: all 0.3s ease;
    }

    .media-dropzone:hover .upload-icon-circle {
        transform: scale(1.1) rotate(5deg);
        background: #fff;
        box-shadow: 0 8px 16px rgba(0,0,0,0.05);
    }

    .upload-prompt h3 {
        font-family: var(--font-display);
        font-weight: 800;
        font-size: 1.5rem;
        color: var(--heritage-text);
        margin-bottom: 8px;
        letter-spacing: -0.02em;
    }

    .upload-prompt p {
        color: var(--heritage-text-muted);
        font-size: 0.95rem;
        font-weight: 500;
    }

    /* Premium Preview Style */
    .premium-preview-container {
        width: 100%;
        max-width: 600px;
        margin: 0 auto;
        position: relative;
        animation: revealUp 0.6s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .polaroid-frame {
        background: #fff;
        padding: 16px 16px 60px 16px;
        border-radius: 4px;
        box-shadow: 0 30px 60px rgba(0,0,0,0.12), 0 10px 20px rgba(0,0,0,0.05);
        border: 1px solid var(--heritage-border);
        transform: rotate(-1deg);
        transition: transform 0.3s ease;
    }

    .polaroid-frame:hover {
        transform: rotate(0deg) scale(1.02);
    }

    .preview-image-wrapper {
        width: 100%;
        aspect-ratio: 4/3;
        overflow: hidden;
        border-radius: 2px;
        background: #f1f5f9;
        position: relative;
    }

    .preview-image-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .media-toolbar {
        position: absolute;
        top: -20px;
        right: -20px;
        display: flex;
        gap: 10px;
        z-index: 10;
    }

    .toolbar-btn {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
        font-size: 1.1rem;
    }

    .btn-replace { background: var(--heritage-text); color: #fff; }
    .btn-remove { background: var(--heritage-crimson); color: #fff; }

    .toolbar-btn:hover {
        transform: scale(1.1);
        filter: brightness(1.2);
    }

    .media-meta {
        position: absolute;
        bottom: 15px;
        left: 20px;
        right: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-family: var(--font-display);
        font-weight: 700;
        font-size: 0.85rem;
        color: var(--heritage-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .file-upload-input {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        opacity: 0; cursor: pointer;
        z-index: 5;
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

    /* Stats Box for Edit */
    .edit-stats-banner {
        display: flex;
        gap: 20px;
        background: var(--heritage-bg);
        padding: 20px;
        border-radius: 20px;
        margin-bottom: 30px;
        border: 1px solid var(--heritage-border);
    }
    .stat-pill {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .stat-pill-label {
        font-family: var(--font-display);
        font-weight: 800;
        font-size: 0.7rem;
        color: var(--heritage-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .stat-pill-value {
        font-family: var(--font-display);
        font-weight: 800;
        font-size: 1.1rem;
        color: var(--heritage-text);
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
                <h1>Edit Berita 📰</h1>
                <p>Kelola konten: <?php echo htmlspecialchars($berita_data['judul'] ?? ''); ?></p>
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
                    <i class="fas fa-edit"></i>
                    Revisi Artikel
                </h1>
                <a href="berita.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Batal
                </a>
            </header>

            <!-- ERROR MESSAGES -->
            <?php if (isset($errors['database'])): ?>
            <div class="alert alert-danger reveal">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $errors['database']; ?>
            </div>
            <?php endif; ?>

            <!-- EDIT BERITA FORM -->
            <div class="form-container">
                <form method="POST" action="" enctype="multipart/form-data" id="beritaForm">
                    <input type="hidden" name="id" value="<?php echo $berita_id; ?>">
                    <input type="hidden" name="delete_gambar" id="deleteGambarInput" value="0">
                    
                    <!-- Analytics Banner -->
                    <div class="edit-stats-banner reveal d-1">
                        <div class="stat-pill">
                            <span class="stat-pill-label">Waktu Dibuat</span>
                            <span class="stat-pill-value"><?php echo date('d M Y H:i', strtotime($berita_data['created_at'])); ?></span>
                        </div>
                        <div class="stat-pill">
                            <span class="stat-pill-label">Update Terakhir</span>
                            <span class="stat-pill-value"><?php echo date('d M Y H:i', strtotime($berita_data['updated_at'])); ?></span>
                        </div>
                        <div class="stat-pill">
                            <span class="stat-pill-label">Total Views</span>
                            <span class="stat-pill-value"><?php echo number_format($berita_data['views']); ?> Kali</span>
                        </div>
                    </div>

                    <!-- Section 1: Core Information -->
                    <div class="form-section reveal d-1">
                        <h2 class="section-title">
                            <i class="fas fa-newspaper"></i>
                            Identitas Berita
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
                                       value="<?php echo htmlspecialchars($berita_data['judul'] ?? ''); ?>"
                                       placeholder="Revisi judul berita..."
                                       maxlength="200"
                                       required>
                                <div class="char-count">
                                    <span id="judulCount"><?php echo strlen($berita_data['judul']); ?></span>/200 Karakter
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
                                       value="<?php echo htmlspecialchars($berita_data['slug'] ?? ''); ?>"
                                       placeholder="judul-berita-anda">
                                <small style="color: var(--heritage-text-muted); display: block; margin-top: 8px; font-weight: 600; font-size: 0.75rem;">
                                    Dibuat otomatis dari judul jika dikosongkan.
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Penulis
                                </label>
                                <input type="text" 
                                       id="penulis" 
                                       name="penulis" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($berita_data['penulis'] ?? ''); ?>"
                                       readonly>
                            </div>

                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label">
                                    Tags & Label
                                </label>
                                <input type="text" 
                                       id="tag" 
                                       name="tag" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($berita_data['tag'] ?? ''); ?>"
                                       placeholder="Misal: futsal, turnamen, prestasi">
                                <small style="color: var(--heritage-text-muted); display: block; margin-top: 8px; font-weight: 600; font-size: 0.75rem;">
                                    Gunakan koma (,) sebagai pemisah tag.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Featured Image REDESIGNED -->
                    <div class="form-section reveal d-2">
                        <h2 class="section-title">
                            <i class="fas fa-camera"></i>
                            Media Visual
                        </h2>
                        
                        <div class="form-group">
                            <div class="media-upload-wrapper">
                                <!-- Upload Prompt Area -->
                                <div class="media-dropzone" id="mediaDropzone" style="<?php echo !empty($berita_data['gambar']) ? 'display:none;' : ''; ?>">
                                    <input type="file" 
                                           id="gambar" 
                                           name="gambar" 
                                           class="file-upload-input"
                                           accept="image/jpeg,image/png,image/gif,image/webp">
                                    
                                    <div class="upload-icon-circle">
                                        <i class="fas fa-camera-retro"></i>
                                    </div>
                                    
                                    <div class="upload-prompt">
                                        <h3>Unggah Gambar Baru</h3>
                                        <p>Klik atau seret file ke area ini (Maks. 5MB)</p>
                                    </div>
                                </div>

                                <!-- Premium Preview Area -->
                                <div class="premium-preview-container" id="premiumPreview" style="<?php echo !empty($berita_data['gambar']) ? 'display:block;' : 'display:none;'; ?>">
                                    <div class="media-toolbar">
                                        <button type="button" class="toolbar-btn btn-replace" onclick="document.getElementById('gambar').click()" title="Ganti Gambar">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                        <button type="button" class="toolbar-btn btn-remove" id="btnRemoveMedia" title="Hapus Gambar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                    
                                    <div class="polaroid-frame">
                                        <div class="preview-image-wrapper">
                                            <?php 
                                            $img_src = !empty($berita_data['gambar']) ? '../images/berita/' . $berita_data['gambar'] : '';
                                            ?>
                                            <img id="premiumPreviewImg" src="<?php echo htmlspecialchars($img_src); ?>" alt="Pratinjau Gambar">
                                        </div>
                                        <div class="media-meta">
                                            <span id="metaFileName"><?php echo !empty($berita_data['gambar']) ? htmlspecialchars($berita_data['gambar']) : 'filename.jpg'; ?></span>
                                            <span id="metaFileSize">Existing Media</span>
                                        </div>
                                    </div>
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
                            Narasi Artikel
                        </h2>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Konten Berita <span class="required">*</span>
                            </label>
                            <textarea id="konten" 
                                      name="konten" 
                                      class="<?php echo isset($errors['konten']) ? 'is-invalid' : ''; ?>"
                                      required><?php echo htmlspecialchars($berita_data['konten'] ?? ''); ?></textarea>
                            <div class="char-count">
                                <span id="kontenCount"><?php echo strlen($berita_data['konten']); ?></span> Karakter Terinput
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
                            Konfigurasi Publikasi
                        </h2>
                        
                        <div class="form-group">
                            <label class="form-label">Ubah Status Publikasi</label>
                            <div class="status-options">
                                <div class="status-option">
                                    <input type="radio" id="status_draft" name="status" value="draft" <?php echo $berita_data['status'] == 'draft' ? 'checked' : ''; ?>>
                                    <label for="status_draft">
                                        <i class="fas fa-pencil-ruler"></i>
                                        Draft
                                    </label>
                                </div>
                                
                                <div class="status-option">
                                    <input type="radio" id="status_published" name="status" value="published" <?php echo $berita_data['status'] == 'published' ? 'checked' : ''; ?>>
                                    <label for="status_published">
                                        <i class="fas fa-broadcast-tower"></i>
                                        Publish
                                    </label>
                                </div>
                                
                                <div class="status-option">
                                    <input type="radio" id="status_archived" name="status" value="archived" <?php echo $berita_data['status'] == 'archived' ? 'checked' : ''; ?>>
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
                        <a href="berita.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Batalkan Revisi
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Simpan Perubahan
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
    const dropzone = document.getElementById('mediaDropzone');
    const premiumPreview = document.getElementById('premiumPreview');
    const premiumPreviewImg = document.getElementById('premiumPreviewImg');
    const metaFileName = document.getElementById('metaFileName');
    const metaFileSize = document.getElementById('metaFileSize');
    const btnRemoveMedia = document.getElementById('btnRemoveMedia');
    const deleteGambarInput = document.getElementById('deleteGambarInput');
    let slugManuallyEdited = slugInput.value.trim() !== '';

    // Summernote Heritage Initialization
    if (window.jQuery && $('#konten').length) {
        $('#konten').summernote({
            placeholder: 'Perbarui narasi berita Anda secara detail...',
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

    // Visual Media Premium Handler
    function handleFiles(files) {
        if (files && files[0]) {
            const file = files[0];
            
            // Check type and size
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                toastr.error('Format file tidak didukung. Gunakan JPG, PNG, atau WebP.');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                toastr.error('Ukuran file terlalu besar. Maksimal 5MB.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                premiumPreviewImg.src = e.target.result;
                metaFileName.textContent = file.name.length > 25 ? file.name.substring(0, 22) + '...' : file.name;
                metaFileSize.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                
                dropzone.style.display = 'none';
                premiumPreview.style.display = 'block';
                deleteGambarInput.value = "0"; // Cancel pending deletion
                toastr.success('Gambar baru dilampirkan.');
            };
            reader.readAsDataURL(file);
        }
    }

    gambarInput.addEventListener('change', function() {
        handleFiles(this.files);
    });

    // Drag & Drop interactions
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('drag-over');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('drag-over');
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        gambarInput.files = files;
        handleFiles(files);
    }, false);

    // Remove media
    btnRemoveMedia.addEventListener('click', function() {
        gambarInput.value = '';
        premiumPreview.style.display = 'none';
        dropzone.style.display = 'flex';
        deleteGambarInput.value = "1"; // Mark for deletion in DB
        toastr.warning('Gambar ditandai untuk dihapus.');
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
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
