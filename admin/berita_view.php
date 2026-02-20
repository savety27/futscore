<?php
session_start();

// Load database config
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Get berita ID
$berita_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($berita_id <= 0) {
    header("Location: berita.php");
    exit;
}


// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';


// Fetch berita data
try {
    $stmt = $conn->prepare("SELECT * FROM berita WHERE id = ?");
    $stmt->execute([$berita_id]);
    $berita = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$berita) {
        header("Location: berita.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error fetching berita data: " . $e->getMessage());
}

// Format date
$created_at = date('d F Y H:i', strtotime($berita['created_at']));
$updated_at = date('d F Y H:i', strtotime($berita['updated_at']));

// Format status badge
$status_badges = [
    'published' => ['class' => 'status-published', 'text' => 'Published'],
    'draft' => ['class' => 'status-draft', 'text' => 'Draft'],
    'archived' => ['class' => 'status-archived', 'text' => 'Archived']
];
$status_info = $status_badges[$berita['status']];

// Split tags
$tags = !empty($berita['tag']) ? explode(',', $berita['tag']) : [];

// Fungsi untuk sanitasi HTML yang aman
function sanitizeHtml($html) {
    // Basic sanitization - allow common HTML tags for content
    $allowed_tags = '<h1><h2><h3><h4><h5><h6><p><br><b><strong><i><em><u><ul><ol><li><a><img><table><tr><td><th><thead><tbody><tfoot><div><span><blockquote><code><pre><hr>';
    $html = strip_tags($html, $allowed_tags);
    
    // Remove any dangerous attributes
    $html = preg_replace('/ on\w+="[^"]*"/', '', $html);
    $html = preg_replace('/javascript:/i', '', $html);
    $html = preg_replace('/data:/i', '', $html);
    
    // Decode HTML entities
    $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
    
    return $html;
}

// Fungsi untuk membersihkan teks dari multiple newlines
function cleanTextContent($text) {
    // Remove multiple newlines and spaces
    $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    return $text;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Berita - <?php echo htmlspecialchars($berita['judul'] ?? ''); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
:root {
    --primary: #0f2744;
    --secondary: #f59e0b;
    --accent: #3b82f6;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --light: #F8F9FA;
    --dark: #1e293b;
    --gray: #64748b;
    --glass-white: rgba(255, 255, 255, 0.85);
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
    --premium-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --transition: cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%);
    color: var(--dark);
    min-height: 100vh;
    overflow-x: hidden;
}

.wrapper {
    display: flex;
    min-height: 100vh;
}

/* ===== MAIN CONTENT ===== */
.main {
    flex: 1;
    padding: 30px;
    margin-left: 280px;
    width: calc(100% - 280px);
    max-width: calc(100vw - 280px);
    overflow-x: hidden;
    transition: var(--transition);
}

/* Topbar */
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    padding: 20px 25px;
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    animation: slideDown 0.5s ease-out;
}

.greeting h1 {
    font-size: 28px;
    color: var(--primary);
    margin-bottom: 5px;
}

.greeting p {
    color: var(--gray);
    font-size: 14px;
}

.user-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

.logout-btn {
    background: linear-gradient(135deg, var(--danger) 0%, #B71C1C 100%);
    color: white;
    padding: 12px 28px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    box-shadow: 0 5px 15px rgba(211, 47, 47, 0.2);
}

.logout-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(211, 47, 47, 0.3);
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
}

.page-title {
    font-size: 28px;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title i {
    color: var(--secondary);
    font-size: 32px;
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
    transition: var(--transition);
    font-size: 15px;
    text-decoration: none;
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

.btn-success {
    background: linear-gradient(135deg, var(--success), #4CAF50);
    color: white;
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.2);
}

.btn-success:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(46, 125, 50, 0.3);
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

/* Detail Container */
.detail-container {
    background: white;
    border-radius: 20px;
    padding: 40px;
    box-shadow: var(--card-shadow);
    margin-bottom: 30px;
}

.berita-header {
    margin-bottom: 40px;
    text-align: center;
    position: relative;
}

.berita-title {
    font-size: 36px;
    color: var(--primary);
    margin-bottom: 20px;
    line-height: 1.3;
    font-weight: 700;
}

.berita-meta {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--gray);
    font-size: 14px;
}

.meta-item i {
    color: var(--primary);
}

.status-badge {
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: inline-block;
}

.status-published {
    background: rgba(46, 125, 50, 0.15);
    color: var(--success);
    border: 1px solid rgba(46, 125, 50, 0.3);
}

.status-draft {
    background: rgba(108, 117, 125, 0.15);
    color: var(--gray);
    border: 1px solid rgba(108, 117, 125, 0.3);
}

.status-archived {
    background: rgba(211, 47, 47, 0.15);
    color: var(--danger);
    border: 1px solid rgba(211, 47, 47, 0.3);
}

/* Berita Image */
.berita-image-container {
    margin-bottom: 40px;
    text-align: center;
}

.berita-image {
    max-width: 100%;
    max-height: 500px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    object-fit: cover;
}

.image-placeholder {
    width: 100%;
    height: 300px;
    background: linear-gradient(135deg, #f5f7fa, #e4edf5);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
    color: var(--gray);
}

/* Berita Content */
.berita-content {
    font-size: 18px;
    line-height: 1.8;
    color: var(--dark);
    margin-bottom: 40px;
}

.berita-content h1, .berita-content h2, .berita-content h3, .berita-content h4 {
    color: var(--primary);
    margin-top: 30px;
    margin-bottom: 15px;
    font-weight: 600;
}

.berita-content p {
    margin-bottom: 20px;
    line-height: 1.6;
    text-align: justify;
}

.berita-content ul, .berita-content ol {
    margin-left: 20px;
    margin-bottom: 20px;
}

.berita-content li {
    margin-bottom: 8px;
    line-height: 1.6;
}

.berita-content img {
    max-width: 100%;
    height: auto;
    border-radius: 10px;
    margin: 20px 0;
    display: block;
}

.berita-content a {
    color: var(--primary);
    text-decoration: none;
    border-bottom: 2px solid var(--secondary);
    transition: var(--transition);
}

.berita-content a:hover {
    color: var(--accent);
    border-bottom-color: var(--accent);
}

/* Konten tanpa format - untuk teks biasa */
.plain-text-content {
    white-space: pre-wrap;
    word-wrap: break-word;
    font-size: 18px;
    line-height: 1.8;
    margin-bottom: 40px;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

/* Tags Section */
.tags-section {
    margin-bottom: 40px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
}

.section-title {
    font-size: 20px;
    color: var(--primary);
    margin-bottom: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.tags-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.tag-item {
    background: white;
    color: var(--primary);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
    border: 2px solid #e0e0e0;
    transition: var(--transition);
}

.tag-item:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
}

/* Stats Section */
.stats-section {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.stat-box {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 15px;
    text-align: center;
    transition: var(--transition);
}

.stat-box:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-shadow);
}

.stat-icon {
    font-size: 32px;
    color: var(--primary);
    margin-bottom: 15px;
    display: block;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: var(--gray);
}

/* Action Buttons */
.action-buttons-container {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 40px;
    padding-top: 30px;
    border-top: 2px solid #f0f0f0;
}

.action-btn {
    padding: 12px 25px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    font-size: 15px;
    text-decoration: none;
}

/* Slug Info */
.slug-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    font-family: monospace;
    font-size: 14px;
    color: var(--gray);
}

/* Content Formatting */
.content-wrapper {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
}

.content-wrapper p {
    margin-bottom: 1.2em;
    line-height: 1.8;
}

.content-wrapper br {
    display: none;
}

.content-wrapper p:empty {
    display: none;
}

/* =========================================
   MOBILE RESPONSIVE DESIGN
   ========================================= */

/* Default: Hide mobile-only elements on desktop */


/* ===== TABLET (max-width: 1024px) ===== */
@media screen and (max-width: 1024px) {

    .main {
        margin-left: 240px;
        width: calc(100% - 240px);
        max-width: calc(100vw - 240px);
    }
}

/* ===== MOBILE LANDSCAPE (max-width: 768px) ===== */
@media screen and (max-width: 768px) {
    


    /* Main Content: Full width on mobile */
    .main {
        margin-left: 0;
        padding: 20px 15px;
        width: 100%;
        max-width: 100vw;
    }

    /* Topbar: Stack vertically */
    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
        padding: 20px;
    }

    .greeting h1 {
        font-size: 24px;
    }

    .user-actions {
        width: 100%;
        display: flex;
        justify-content: flex-end;
    }

    /* Page Header: Stack vertically */
    .page-header {
        flex-direction: column;
        gap: 20px;
        align-items: center;
        text-align: center;
    }
    
    .page-title {
        width: 100%;
        justify-content: center;
    }

    .action-buttons {
        width: 100%;
        justify-content: center;
    }

    .btn {
        flex: 1;
        justify-content: center;
    }

    /* Detail View Mobile Adjustments */
    .berita-title {
        font-size: 24px;
    }
    
    .berita-meta {
        flex-direction: column;
        gap: 15px;
    }
    
    .stats-section {
        grid-template-columns: 1fr;
    }
    
    .action-buttons-container {
        flex-direction: column;
        gap: 15px;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
    
    .berita-content, .plain-text-content {
        font-size: 16px;
        line-height: 1.6;
    }
}

/* ===== MOBILE PORTRAIT (max-width: 480px) ===== */
@media screen and (max-width: 480px) {
    
    /* Reduce font sizes */
    .greeting h1 {
        font-size: 20px;
    }
    
    .greeting p {
        font-size: 13px;
    }

    .page-title {
        font-size: 20px;
    }

    .page-title i {
        font-size: 24px;
    }


    .logo,
    .logo img {
        max-width: 120px;
    }


}
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1>Berita Details 📰</h1>
                <p>Lihat detail lengkap berita</p>
            </div>
            
            <div class="user-actions">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-eye"></i>
                <span>Detail Berita</span>
            </div>
            <div class="action-buttons">
                <a href="berita.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Daftar
                </a>
                <a href="berita_edit.php?id=<?php echo $berita_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Berita
                </a>
            </div>
        </div>

        <!-- DETAIL BERITA -->
        <div class="detail-container">
            <!-- Berita Header -->
            <div class="berita-header">
                <h1 class="berita-title"><?php echo htmlspecialchars($berita['judul'] ?? ''); ?></h1>
                
                <div class="berita-meta">
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span>Penulis: <?php echo htmlspecialchars($berita['penulis'] ?? ''); ?></span>
                    </div>
                    
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span>Dibuat: <?php echo $created_at; ?></span>
                    </div>
                    
                    <div class="meta-item">
                        <i class="fas fa-sync-alt"></i>
                        <span>Diupdate: <?php echo $updated_at; ?></span>
                    </div>
                    
                    <span class="status-badge <?php echo $status_info['class']; ?>">
                        <?php echo $status_info['text']; ?>
                    </span>
                </div>
                
                <div class="slug-info">
                    <i class="fas fa-link"></i> Slug: <?php echo htmlspecialchars($berita['slug'] ?? ''); ?>
                </div>
            </div>

            <!-- Stats Section -->
            <div class="stats-section">
                <div class="stat-box">
                    <i class="fas fa-eye stat-icon"></i>
                    <div class="stat-value"><?php echo $berita['views']; ?></div>
                    <div class="stat-label">Total Views</div>
                </div>
                
                <div class="stat-box">
                    <i class="fas fa-file-alt stat-icon"></i>
                    <div class="stat-value"><?php echo strlen($berita['konten']); ?></div>
                    <div class="stat-label">Karakter Konten</div>
                </div>
                
                <div class="stat-box">
                    <i class="fas fa-hashtag stat-icon"></i>
                    <div class="stat-value"><?php echo count($tags); ?></div>
                    <div class="stat-label">Total Tags</div>
                </div>
                
                <div class="stat-box">
                    <i class="fas fa-id-badge stat-icon"></i>
                    <div class="stat-value">#<?php echo $berita['id']; ?></div>
                    <div class="stat-label">ID Berita</div>
                </div>
            </div>

            <!-- Berita Image -->
            <div class="berita-image-container">
                <?php if (!empty($berita['gambar'])): ?>
                    <img src="../images/berita/<?php echo htmlspecialchars($berita['gambar'] ?? ''); ?>" 
                         alt="<?php echo htmlspecialchars($berita['judul'] ?? ''); ?>" 
                         class="berita-image">
                <?php else: ?>
                    <div class="image-placeholder">
                        <i class="fas fa-image"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tags Section -->
            <?php if (!empty($tags)): ?>
            <div class="tags-section">
                <div class="section-title">
                    <i class="fas fa-tags"></i>
                    Tags Berita
                </div>
                <div class="tags-container">
                    <?php foreach ($tags as $tag): ?>
                        <?php $tag = trim($tag); ?>
                        <?php if (!empty($tag)): ?>
                        <span class="tag-item"><?php echo htmlspecialchars($tag ?? ''); ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Berita Content -->
            <div class="content-wrapper">
                <?php 
                // Deteksi apakah konten mengandung HTML tags
                $content = trim($berita['konten']);
                $hasHtmlTags = preg_match('/<[^>]+>/', $content);
                
                if ($hasHtmlTags) {
                    // Konten mengandung HTML - tampilkan dengan sanitasi
                    $sanitized_content = sanitizeHtml($content);
                    // Hapus multiple newlines dan whitespace berlebihan
                    $sanitized_content = preg_replace('/(\s*\n\s*){3,}/', "\n\n", $sanitized_content);
                    echo $sanitized_content;
                } else {
                    // Konten plain text - tampilkan dengan formatting yang baik
                    $cleaned_content = cleanTextContent($content);
                    echo '<div class="plain-text-content">' . nl2br(htmlspecialchars($cleaned_content ?? '')) . '</div>';
                }
                ?>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons-container">
                <a href="berita_edit.php?id=<?php echo $berita_id; ?>" class="action-btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Berita
                </a>
                
                <button onclick="deleteBerita(<?php echo $berita_id; ?>, '<?php echo htmlspecialchars(addslashes($berita['judul'] ?? '')); ?>')" 
                        class="action-btn" style="background: var(--danger); color: white;">
                    <i class="fas fa-trash"></i>
                    Hapus Berita
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Close menu when clicking overlay
    menuOverlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        document.body.classList.remove('menu-open');
        menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    });
    
    // Auto close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target) && !menuOverlay.contains(e.target)) {
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('menu-open');
                    menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                }
            }
        }
    });
    
    // Menu toggle functionality (untuk Submenu)
    document.querySelectorAll('.menu-link').forEach(link => {
        if (link.querySelector('.menu-arrow')) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const submenu = this.nextElementSibling;
                const arrow = this.querySelector('.menu-arrow');
                
                if (submenu) {
                    submenu.classList.toggle('open');
                    arrow.classList.toggle('rotate');
                }
            });
        }
    });
    
    // Clean up content formatting
    const contentWrapper = document.querySelector('.content-wrapper');
    if (contentWrapper) {
        // Hapus tag <br> berlebihan
        const content = contentWrapper.innerHTML;
        let cleanedContent = content;
        
        // Hapus multiple <br> tags
        cleanedContent = cleanedContent.replace(/(<br\s*\/?>\s*){3,}/gi, '<br><br>');
        
        // Hapus spasi berlebihan
        cleanedContent = cleanedContent.replace(/\s+/g, ' ');
        
        // Hapus paragraf kosong
        cleanedContent = cleanedContent.replace(/<p>\s*<\/p>/gi, '');
        
        contentWrapper.innerHTML = cleanedContent;
    }

    // Responsive adjustments
    function adjustLayout() {
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            document.querySelector('.main').style.marginLeft = '0';
        } else if (window.innerWidth > 1200) {
            document.querySelector('.main').style.marginLeft = '280px';
        }
    }
    
    adjustLayout();
    window.addEventListener('resize', adjustLayout);
});

function deleteBerita(beritaId, judul) {
    if (confirm(`Apakah Anda yakin ingin menghapus berita "${judul}"?`)) {
        fetch(`berita_delete.php?id=${beritaId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toastr.success('Berita berhasil dihapus!');
                setTimeout(() => {
                    window.location.href = 'berita.php';
                }, 1000);
            } else {
                toastr.error('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastr.error('Terjadi kesalahan saat menghapus berita.');
        });
    }
}
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>