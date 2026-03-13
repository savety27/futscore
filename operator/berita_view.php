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

if ($operator_id > 0) {
    try {
        $stmtOperator = $conn->prepare("
            SELECT e.name AS event_name, e.image AS event_image
            FROM admin_users au
            LEFT JOIN events e ON e.id = au.event_id
            WHERE au.id = ?
            LIMIT 1
        ");
        $stmtOperator->execute([$operator_id]);
        $operator_row = $stmtOperator->fetch(PDO::FETCH_ASSOC);
        $operator_event_name = trim((string)($operator_row['event_name'] ?? '')) !== '' ? (string)$operator_row['event_name'] : 'Event Operator';
        $operator_event_image = trim((string)($operator_row['event_image'] ?? ''));
    } catch (PDOException $e) {
        // keep defaults
    }
}


// Fetch berita data
try {
    $ownership_where_sql = '';
    $ownership_params = [];
    $berita_has_created_by = adminHasColumn($conn, 'berita', 'created_by');
    if ($berita_has_created_by && $operator_id > 0) {
        $ownership_where_sql = " AND created_by = ?";
        $ownership_params[] = $operator_id;
    } else {
        $ownership_where_sql = " AND (penulis = ? OR penulis = ?)";
        $ownership_params[] = $admin_name;
        $ownership_params[] = $admin_username;
    }

    $stmt = $conn->prepare("SELECT * FROM berita WHERE id = ?" . $ownership_where_sql);
    $stmt->execute(array_merge([$berita_id], $ownership_params));
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
$status_info = $status_badges[$berita['status']] ?? ['class' => 'status-draft', 'text' => ucfirst((string)($berita['status'] ?? 'draft'))];

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
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../pelatih/css/style.css'); ?>">
<link rel="stylesheet" href="css/challenge.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/challenge.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
/* Page-specific unique styles for Berita View */
:root {
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Specific UI for View Details */
.info-card {
    background: var(--heritage-card);
    border: 1px solid var(--heritage-border);
    border-radius: 28px;
    padding: 40px;
    margin-bottom: 40px;
    box-shadow: var(--soft-shadow);
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 32px;
}

.info-item {
    padding-bottom: 20px;
    border-bottom: 1px solid var(--heritage-border);
}

.info-label {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--heritage-text-muted);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-label i { color: var(--heritage-gold); font-size: 0.9rem; }

.info-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--heritage-text);
    line-height: 1.4;
}

.badge-premium {
    display: inline-flex;
    padding: 4px 16px;
    border-radius: 100px;
    font-size: 0.85rem;
    font-weight: 700;
    font-family: var(--font-display);
}

.badge-published { background: #ecfdf5; color: #047857; }
.badge-draft { background: #f3f4f6; color: #374151; }
.badge-archived { background: #fef2f2; color: #b91c1c; }

/* Berita Content Styles */
.berita-content-container {
    font-size: 1.15rem;
    line-height: 1.8;
    color: var(--heritage-text);
}

.berita-content-container h1, 
.berita-content-container h2, 
.berita-content-container h3 {
    font-family: var(--font-display);
    color: var(--heritage-text);
    margin-top: 2rem;
    margin-bottom: 1rem;
    font-weight: 800;
}

.berita-content-container p {
    margin-bottom: 1.5rem;
}

.berita-image-wrap {
    width: 100%;
    max-height: 600px;
    overflow: hidden;
    border-radius: 24px;
    margin-bottom: 32px;
    border: 1px solid var(--heritage-border);
    box-shadow: var(--soft-shadow);
}

.berita-image-full {
    width: 100%;
    height: auto;
    display: block;
    object-fit: cover;
}

.image-placeholder-premium {
    width: 100%;
    height: 400px;
    background: var(--heritage-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    color: var(--heritage-text-muted);
}

/* Tags Premium */
.tags-premium-container {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

.tag-premium {
    background: white;
    color: var(--heritage-text);
    padding: 8px 18px;
    border-radius: 14px;
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 0.9rem;
    border: 1px solid var(--heritage-border);
    transition: var(--transition);
}

.tag-premium:hover {
    background: var(--heritage-text);
    color: white;
    transform: translateY(-2px);
}

/* Mobile Adjustments */
@media (max-width: 768px) {
    .main { padding: 20px !important; }
    
    .dashboard-hero { 
        flex-direction: column; 
        align-items: flex-start; 
        text-align: left;
    }
    
    .hero-title { font-size: 2.5rem; }
    
    .info-card { padding: 24px; border-radius: 20px; }
    
    .info-grid { grid-template-columns: 1fr; }
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
                <h1>Berita Details 📰</h1>
                <p>Lihat detail lengkap berita</p>
            </div>
            
            <div class="user-actions">
                <a href="../operator/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </a>
            </div>
        </div>

        <div class="challenge-container">
            <!-- EDITORIAL HEADER -->
            <header class="dashboard-hero reveal">
                <div class="hero-content">
                    <span class="hero-label">Berita Overview #<?php echo (int)$berita['id']; ?></span>
                    <h1 class="hero-title"><?php echo htmlspecialchars($berita['judul'] ?? ''); ?></h1>
                    <p class="hero-description">
                        Ditulis oleh <strong><?php echo htmlspecialchars($berita['penulis'] ?? ''); ?></strong> 
                        pada <?php echo $created_at; ?>.
                    </p>
                </div>
                <div class="hero-actions">
                    <a href="berita.php" class="btn-premium btn-export">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </header>

            <!-- STATS & METADATA -->
            <div class="reveal d-1">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <h2 class="section-title">Informasi & Statistik</h2>
                        <div class="section-line"></div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-info-circle"></i> Status</div>
                            <div class="info-value">
                                <span class="badge-premium badge-<?php echo strtolower($berita['status']); ?>">
                                    <?php echo strtoupper($berita['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-eye"></i> Total Views</div>
                            <div class="info-value"><?php echo number_format($berita['views']); ?> x dilihat</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-calendar-alt"></i> Terakhir Update</div>
                            <div class="info-value"><?php echo $updated_at; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-link"></i> URL Slug</div>
                            <div class="info-value" style="font-family: monospace; font-size: 0.9rem;">
                                <?php echo htmlspecialchars($berita['slug'] ?? ''); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MEDIA & TAGS -->
            <div class="reveal d-2">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <h2 class="section-title">Media Berita</h2>
                        <div class="section-line"></div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="berita-image-wrap">
                        <?php if (!empty($berita['gambar'])): ?>
                            <img src="../images/berita/<?php echo htmlspecialchars($berita['gambar'] ?? ''); ?>" 
                                 alt="<?php echo htmlspecialchars($berita['judul'] ?? ''); ?>" 
                                 class="berita-image-full">
                        <?php else: ?>
                            <div class="image-placeholder-premium">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($tags)): ?>
                    <div class="info-label"><i class="fas fa-tags"></i> Tags Berita</div>
                    <div class="tags-premium-container">
                        <?php foreach ($tags as $tag): ?>
                            <?php $tag = trim($tag); ?>
                            <?php if (!empty($tag)): ?>
                            <span class="tag-premium"><?php echo htmlspecialchars($tag ?? ''); ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONTENT SECTION -->
            <div class="reveal d-3">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <h2 class="section-title">Isi Konten</h2>
                        <div class="section-line"></div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="berita-content-container">
                        <?php 
                        $content = trim($berita['konten']);
                        $hasHtmlTags = preg_match('/<[^>]+>/', $content);
                        
                        if ($hasHtmlTags) {
                            $sanitized_content = sanitizeHtml($content);
                            $sanitized_content = preg_replace('/(\s*\n\s*){3,}/', "\n\n", $sanitized_content);
                            echo $sanitized_content;
                        } else {
                            $cleaned_content = cleanTextContent($content);
                            echo '<div class="plain-text-content">' . nl2br(htmlspecialchars($cleaned_content ?? '')) . '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- FOOTER ACTIONS -->
            <div class="reveal d-3" style="text-align: center; padding-bottom: 40px;">
                <a href="berita.php" class="btn-premium btn-export" style="background: var(--heritage-text); color: white;">
                    <i class="fas fa-list"></i> Kembali ke Daftar Berita
                </a>
            </div>
        </div>
    </div>
    </div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Clean up content formatting
    const contentWrapper = document.querySelector('.berita-content-container');
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
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
