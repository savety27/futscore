<?php
$hideNavbars = true;
require_once 'includes/header.php';
require_once 'includes/functions.php';

// Gunakan koneksi database dari functions.php
global $db;
$conn = $db->getConnection();

// Cek apakah ini single news atau news list
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$isSingleNews = !empty($slug);

$news = null;
$relatedNews = [];
$newsList = [];
$popularNews = [];
$allTags = [];
$totalNews = 0;
$totalPages = 1;
$currentPage = 1;
$perPage = 12;
$offset = 0;
$searchKeyword = '';
$tagFilter = '';
$sortBy = 'newest';

if ($isSingleNews) {
    // ============================
    // DETAIL BERITA TUNGGAL
    // ============================
    
    // Ambil berita dari tabel berita
    $sql = "SELECT * FROM berita WHERE slug = ? AND status = 'published'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: news.php');
        exit;
    }
    
    $news = $result->fetch_assoc();

    // Tambah jumlah view
    $update_sql = "UPDATE berita SET views = views + 1 WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $news['id']);
    $update_stmt->execute();
    
    // Ambil data terbaru setelah ditambah view
    $sql = "SELECT * FROM berita WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $news['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $news = $result->fetch_assoc();

    $pageTitle = $news['judul'];

    // Ambil berita terkait (tag sama atau yang terbaru)
    if (!empty($news['tag'])) {
        $tags = explode(',', $news['tag']);
        $firstTag = trim($tags[0]);
        
        $sql = "SELECT * FROM berita 
               WHERE id != ? 
               AND status = 'published' 
               AND tag LIKE ? 
               ORDER BY created_at DESC 
               LIMIT 3";
        $stmt = $conn->prepare($sql);
        $searchTag = "%{$firstTag}%";
        $stmt->bind_param("is", $news['id'], $searchTag);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $relatedNews[] = $row;
        }
    }
    
    // Jika tidak ada berita terkait, ambil berita terbaru
    if (empty($relatedNews)) {
        $sql = "SELECT * FROM berita 
               WHERE id != ? 
               AND status = 'published' 
               ORDER BY created_at DESC 
               LIMIT 3";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $news['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $relatedNews[] = $row;
        }
    }
}
else {
    // ============================
    // LIST BERITA (SEMUA BERITA)
    // ============================
    
    // Setup pagination
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($currentPage < 1) {
        $currentPage = 1;
    }
    
    $perPage = 12;
    $offset = ($currentPage - 1) * $perPage;

    // Search dan filter
    $searchKeyword = isset($_GET['search']) ? trim($_GET['search']) : '';
    $tagFilter = isset($_GET['tag']) ? trim($_GET['tag']) : '';
    $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

    // Build query
    $whereConditions = ["status = 'published'"];
    $params = [];
    $paramTypes = '';
    
    if (!empty($searchKeyword)) {
        $whereConditions[] = "(judul LIKE ? OR konten LIKE ? OR penulis LIKE ? OR tag LIKE ?)";
        $searchTerm = "%{$searchKeyword}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $paramTypes .= 'ssss';
    }

    if (!empty($tagFilter)) {
        $whereConditions[] = "tag LIKE ?";
        $params[] = "%{$tagFilter}%";
        $paramTypes .= 's';
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Order by
    $orderBy = '';
    switch ($sortBy) {
        case 'popular':
            $orderBy = 'views DESC, created_at DESC';
            break;
        case 'oldest':
            $orderBy = 'created_at ASC';
            break;
        default: // 'newest'
            $orderBy = 'created_at DESC';
            break;
    }

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM berita {$whereClause}";
    $countStmt = $conn->prepare($countSql);
    
    if (!empty($params)) {
        $countStmt->bind_param($paramTypes, ...$params);
    }
    
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalRow = $countResult->fetch_assoc();
    $totalNews = $totalRow['total'];
    
    $totalPages = ceil($totalNews / $perPage);
    if ($totalPages < 1) {
        $totalPages = 1;
    }
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }

    // Get news for current page
    $listSql = "SELECT * FROM berita {$whereClause} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
    $listStmt = $conn->prepare($listSql);
    
    // Add LIMIT and OFFSET to params
    $params[] = $perPage;
    $params[] = $offset;
    $paramTypes .= 'ii';
    
    $listStmt->bind_param($paramTypes, ...$params);
    $listStmt->execute();
    $listResult = $listStmt->get_result();
    
    while ($row = $listResult->fetch_assoc()) {
        $newsList[] = $row;
    }

    // Get popular news for sidebar
    $popularSql = "SELECT * FROM berita WHERE status = 'published' ORDER BY views DESC LIMIT 5";
    $popularStmt = $conn->prepare($popularSql);
    $popularStmt->execute();
    $popularResult = $popularStmt->get_result();
    
    while ($row = $popularResult->fetch_assoc()) {
        $popularNews[] = $row;
    }

    // Get all unique tags
    $tagsSql = "SELECT DISTINCT tag FROM berita WHERE status = 'published' AND tag IS NOT NULL AND tag != ''";
    $tagsStmt = $conn->prepare($tagsSql);
    $tagsStmt->execute();
    $tagsResult = $tagsStmt->get_result();
    
    while ($row = $tagsResult->fetch_assoc()) {
        if (!empty($row['tag'])) {
            $tagArray = explode(',', $row['tag']);
            foreach ($tagArray as $tag) {
                $tag = trim($tag);
                if (!empty($tag) && !in_array($tag, $allTags)) {
                    $allTags[] = $tag;
                }
            }
        }
    }
    sort($allTags);

    $pageTitle = "Berita";
}

// Helper function for query string
function getQueryString($exclude = []) {
    $query = [];
    foreach ($_GET as $key => $value) {
        if (!in_array($key, $exclude) && !empty($value)) {
            $query[] = $key . '=' . urlencode($value);
        }
    }
    return $query ? '&' . implode('&', $query) : '';
}
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/news_redesign.css?v=<?php echo time(); ?>">
<div class="dashboard-wrapper">
    <!-- Mobile Header -->
    <header class="mobile-dashboard-header">
        <div class="mobile-logo">
            <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Buka/Tutup Sidebar" aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar" aria-hidden="true">
        <div class="sidebar-logo">
            <a href="<?php echo SITE_URL; ?>">
                <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> <span>BERANDA</span></a>
            <a href="event.php"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="team.php"><i class="fas fa-users"></i> <span>TIM</span></a>
            <div class="nav-item-dropdown">
                <a href="#" class="nav-has-dropdown" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                    <div class="nav-link-content">
                        <i class="fas fa-users"></i> <span>PEMAIN</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <div id="playerDropdown" class="sidebar-dropdown">
                    <a href="player.php">Pemain</a>
                    <a href="staff.php">Staf Tim</a>
                </div>
            </div>
            <a href="news.php" class="active"><i class="fas fa-newspaper"></i> <span>BERITA</span></a>
            <a href="bpjs.php"><i class="fas fa-shield-alt"></i> <span>BPJSTK</span></a>
            <a href="contact.php"><i class="fas fa-envelope"></i> <span>KONTAK</span></a>
            
            <div class="sidebar-divider" style="margin: 15px 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                <a href="<?php echo ($_SESSION['admin_role'] === 'pelatih' ? SITE_URL.'/pelatih/dashboard.php' : SITE_URL.'/admin/dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i> <span>DASHBOARD</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/logout.php" style="color: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i> <span>KELUAR</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-login-sidebar">
                    <i class="fas fa-sign-in-alt"></i> <span>MASUK</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-news">
            <div class="dashboard-header-inner">
                <div class="header-eyebrow">ALVETRIX</div>
                <h1><?php echo $isSingleNews ? 'Detail Berita' : 'Berita Terbaru'; ?></h1>
                <p class="header-subtitle">
                    <?php if ($isSingleNews): ?>
                        Dibaca <?php echo number_format($news['views']); ?> kali pada <?php echo date('d F Y', strtotime($news['created_at'])); ?>
                    <?php else: ?>
                        Ikuti berita terbaru futsal, laporan pertandingan, dan update pemain.
                    <?php endif; ?>
                </p>
            </div>
        </header>
        <div class="dashboard-body">
            <div class="container news-page-shell">
                <?php if ($isSingleNews): ?>
                    <div class="news-detail section-container section-elevated">
                        <div class="news-breadcrumb">
                            <a href="index.php">Beranda</a>
                            <span class="breadcrumb-separator">&rsaquo;</span>
                            <a href="news.php">Berita</a>
                            <span class="breadcrumb-separator">&rsaquo;</span>
                            <span><?php echo htmlspecialchars($news['judul'] ?? ''); ?></span>
                        </div>
                        <div class="news-header-detail">
                            <h1><?php echo htmlspecialchars($news['judul'] ?? ''); ?></h1>
                            <div class="news-meta-detail">
                                <span class="news-date">
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('d F Y', strtotime($news['created_at'])); ?>
                                </span>
                                <span class="news-views">
                                    <i class="fas fa-eye"></i> 
                                    <span id="news-detail-views"><?php echo number_format($news['views']); ?></span> dilihat
                                </span>
                                <?php if (!empty($news['penulis'])): ?>
                                <span class="news-author">
                                    <i class="fas fa-user"></i> 
                                    <?php echo htmlspecialchars($news['penulis'] ?? ''); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="news-image-detail-container">
                            <?php
                            $image = !empty($news['gambar']) ? $news['gambar'] : 'default-news.jpg';
                            $imagePath = SITE_URL . '/images/berita/' . $image;
                            $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                            ?>
                            <img src="<?php echo $imagePath; ?>" 
                                 alt="<?php echo htmlspecialchars($news['judul'] ?? ''); ?>"
                                 class="news-image-detail-main"
                                 onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                        </div>
                        
                        <div class="news-content-detail-wrapper">
                            <?php 
                            // Tampilkan konten - handle HTML dan plain text
                            $content = $news['konten'];
                            
                            // Jika konten punya tag HTML, tampilkan apa adanya
                            if (strip_tags($content) != $content) {
                                echo $content;
                            } else {
                                // Plain text - format dengan paragraf
                                $lines = explode("\n", $content);
                                foreach ($lines as $line):
                                    $line = trim($line);
                                    if (!empty($line)):
                                        echo '<p>' . nl2br(htmlspecialchars($line ?? '')) . '</p>';
                                    else:
                                        echo '<br>';
                                    endif;
                                endforeach;
                            }
                            ?>
                        </div>
                        
                        <?php if (!empty($news['tag'])): ?>
                        <div class="news-tags-container">
                            <i class="fas fa-tags"></i>
                            <?php
                            $tags = explode(',', $news['tag']);
                            foreach ($tags as $tag):
                                $tag = trim($tag);
                                if (!empty($tag)):
                            ?>
                            <a href="news.php?tag=<?php echo urlencode($tag); ?>" class="tag-item"><?php echo htmlspecialchars($tag ?? ''); ?></a>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Share Sosial Media -->
                        <div class="news-social-share-container">
                            <span class="share-label">Bagikan artikel ini:</span>
                            <div class="share-buttons">
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/news.php?slug=' . $news['slug']); ?>" 
                                   target="_blank" class="share-btn facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . '/news.php?slug=' . $news['slug']); ?>&text=<?php echo urlencode($news['judul']); ?>" 
                                   target="_blank" class="share-btn twitter">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="https://wa.me/?text=<?php echo urlencode($news['judul'] . ' - ' . SITE_URL . '/news.php?slug=' . $news['slug']); ?>" 
                                   target="_blank" class="share-btn whatsapp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <a href="mailto:?subject=<?php echo urlencode($news['judul']); ?>&body=<?php echo urlencode('Baca artikel ini: ' . SITE_URL . '/news.php?slug=' . $news['slug']); ?>" 
                                   class="share-btn email">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($relatedNews)): ?>
                    <div class="related-news-section section-container">
                        <div class="related-news-header">
                            <h3 class="related-news-title">Berita Terkait</h3>
                            <p class="related-news-subtitle">Pilihan artikel lain yang mungkin menarik.</p>
                        </div>
                        <div class="related-grid">
                            <?php foreach ($relatedNews as $related): 
                                $image = !empty($related['gambar']) ? $related['gambar'] : 'default-news.jpg';
                                $imagePath = SITE_URL . '/images/berita/' . $image;
                                $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                            ?>
                            <div class="related-item-card">
                                <a href="news.php?slug=<?php echo $related['slug']; ?>" class="related-link">
                                    <div class="related-image-container">
                                        <img src="<?php echo $imagePath; ?>" 
                                             alt="<?php echo htmlspecialchars($related['judul'] ?? ''); ?>"
                                             class="related-image"
                                             onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                                    </div>
                                    <div class="related-content">
                                        <h4 class="related-title"><?php echo htmlspecialchars($related['judul'] ?? ''); ?></h4>
                                        <div class="related-meta">
                                            <span class="related-date"><?php echo date('d M Y', strtotime($related['created_at'])); ?></span>
                                            <span class="related-views"><i class="fas fa-eye"></i> <?php echo number_format($related['views']); ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="news-controls-section section-container section-elevated">
                        <div class="search-container">
                            <form method="GET" action="news.php" class="search-form">
                                <div class="search-wrapper">
                                    <div class="search-icon">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <input type="text" 
                                           name="search" 
                                           placeholder="Cari berita..." 
                                           value="<?php echo htmlspecialchars($searchKeyword ?? ''); ?>"
                                           class="search-input">
                                    <button type="submit" class="search-button">Cari</button>
                                    <?php if (!empty($searchKeyword)): ?>
                                    <a href="news.php" class="clear-search-button" aria-label="Hapus pencarian">
                                        <i class="fas fa-times"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        
                        <div class="filter-container">
                            <div class="filter-row">
                                <?php if (!empty($allTags)): ?>
                                <div class="filter-group">
                                    <div class="filter-label">
                                        <i class="fas fa-tag"></i> Tag:
                                    </div>
                                    <form method="GET" action="news.php" class="filter-form" id="tagForm">
                                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchKeyword ?? ''); ?>">
                                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortBy ?? ''); ?>">
                                        <select name="tag" id="tag" class="filter-select" onchange="document.getElementById('tagForm').submit()">
                                            <option value="">Semua Tag</option>
                                            <?php foreach ($allTags as $tag): ?>
                                            <option value="<?php echo htmlspecialchars($tag ?? ''); ?>" 
                                                <?php echo ($tagFilter === $tag) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tag ?? ''); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </div>
                                <?php endif; ?>
                                
                                <div class="filter-group">
                                    <div class="filter-label">
                                        <i class="fas fa-sort-amount-down"></i> Urutkan:
                                    </div>
                                    <form method="GET" action="news.php" class="filter-form" id="sortForm">
                                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchKeyword ?? ''); ?>">
                                        <input type="hidden" name="tag" value="<?php echo htmlspecialchars($tagFilter ?? ''); ?>">
                                        <select name="sort" id="sort" class="filter-select" onchange="document.getElementById('sortForm').submit()">
                                            <option value="newest" <?php echo ($sortBy === 'newest') ? 'selected' : ''; ?>>Terbaru</option>
                                            <option value="popular" <?php echo ($sortBy === 'popular') ? 'selected' : ''; ?>>Terpopuler</option>
                                            <option value="oldest" <?php echo ($sortBy === 'oldest') ? 'selected' : ''; ?>>Terlama</option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($searchKeyword) || !empty($tagFilter)): ?>
                    <div class="results-info section-container">
                        <?php if (!empty($searchKeyword)): ?>
                        <div class="search-results">
                            <span class="results-label">Hasil pencarian:</span>
                            <span class="results-keyword">"<?php echo htmlspecialchars($searchKeyword ?? ''); ?>"</span>
                            <span class="results-count"><?php echo $totalNews; ?> hasil</span>
                            <a href="news.php" class="clear-results">
                                <i class="fas fa-times"></i> Hapus filter
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($tagFilter)): ?>
                            <div class="tag-results">
                            <span class="results-label">Filter tag:</span>
                            <span class="tag-badge"><?php echo htmlspecialchars($tagFilter ?? ''); ?></span>
                                <a href="news.php" class="clear-tag">
                            <i class="fas fa-times"></i> Hapus
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="news-content-layout">
                        <!-- Main News Grid -->
                        <div class="news-grid-container">
                            <?php if (empty($newsList)): ?>
                            <div class="no-news-found section-container">
                                <div class="no-results-icon">
                                    <i class="far fa-newspaper"></i>
                                </div>
                                <h3 class="no-results-title">Tidak ada berita ditemukan</h3>
                                <p class="no-results-message">
                                    <?php 
                                    if (!empty($searchKeyword)) {
                                        echo "Tidak ada hasil untuk '" . htmlspecialchars($searchKeyword ?? '') . "'";
                                    } elseif (!empty($tagFilter)) {
                                        echo "Tidak ada berita dengan tag '" . htmlspecialchars($tagFilter ?? '') . "'";
                                    } else {
                                        echo "Belum ada artikel berita tersedia";
                                    }
                                    ?>
                                </p>
                                <?php if (!empty($searchKeyword) || !empty($tagFilter)): ?>
                                <a href="news.php" class="view-all-button">
                                    <i class="fas fa-list"></i> Lihat Semua Berita
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="news-grid">
                                <?php foreach ($newsList as $news): 
                                    $image = !empty($news['gambar']) ? $news['gambar'] : 'default-news.jpg';
                                    $imagePath = SITE_URL . '/images/berita/' . $image;
                                    $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                                ?>
                                <div class="news-item">
                                    <div class="news-item-image">
                                        <a href="news.php?slug=<?php echo $news['slug']; ?>" class="news-image-link">
                                            <img src="<?php echo $imagePath; ?>" 
                                                 alt="<?php echo htmlspecialchars($news['judul'] ?? ''); ?>"
                                                 class="news-image"
                                                 onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                                            <div class="news-image-overlay"></div>
                                            <?php if ($news['views'] > 100): ?>
                                            <span class="news-trending">
                                                <i class="fas fa-fire"></i> Trending
                                            </span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                    
                                    <div class="news-item-content">
                                        <div class="news-category">
                                            <?php if (!empty($news['tag'])): 
                                                $tagsArray = explode(',', $news['tag']);
                                                $firstTag = trim($tagsArray[0]);
                                                if (!empty($firstTag)):
                                            ?>
                                            <a href="news.php?tag=<?php echo urlencode($firstTag); ?>" class="category-tag">
                                                <?php echo htmlspecialchars($firstTag ?? ''); ?>
                                            </a>
                                            <?php endif; endif; ?>
                                        </div>
                                        
                                        <h3 class="news-item-title">
                                            <a href="news.php?slug=<?php echo $news['slug']; ?>">
                                                <?php echo htmlspecialchars($news['judul'] ?? ''); ?>
                                            </a>
                                        </h3>
                                        
                                        <div class="news-item-meta">
                                            <div class="meta-item">
                                                <i class="far fa-calendar"></i>
                                                <span><?php echo date('d M Y', strtotime($news['created_at'])); ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="far fa-eye"></i>
                                                <span><?php echo number_format($news['views']); ?></span>
                                            </div>
                                            <?php if (!empty($news['penulis'])): ?>
                                            <div class="meta-item">
                                                <i class="far fa-user"></i>
                                                <span><?php echo htmlspecialchars($news['penulis'] ?? ''); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="news-item-excerpt">
                                            <?php 
                                            $excerpt = strip_tags($news['konten']);
                                            $excerpt = htmlspecialchars($excerpt ?? '');
                                            if (strlen($excerpt) > 120) {
                                                echo substr($excerpt, 0, 120) . '...';
                                            } else {
                                                echo $excerpt;
                                            }
                                            ?>
                                        </p>
                                        
                                        <div class="news-item-footer">
                                            <a href="news.php?slug=<?php echo $news['slug']; ?>" class="read-more-button">
                                                Baca Selengkapnya
                                                <i class="fas fa-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <div class="pagination-container">
                                <div class="pagination-info">
                                    Menampilkan <strong><?php echo ($offset + 1); ?>-<?php echo min($offset + $perPage, $totalNews); ?></strong> dari <strong><?php echo $totalNews; ?></strong> berita
                                </div>
                                
                                <nav class="pagination">
                                    <?php if ($currentPage > 1): ?>
                                    <a href="?page=1<?php echo getQueryString(['page']); ?>" class="pagination-button first" title="Halaman Pertama">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                    <a href="?page=<?php echo $currentPage - 1; ?><?php echo getQueryString(['page']); ?>" class="pagination-button prev">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $startPage = max(1, $currentPage - 2);
                                    $endPage = min($totalPages, $startPage + 4);
                                    if ($startPage > 1): ?>
                                    <span class="pagination-dots">...</span>
                                    <?php endif;
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                    <a href="?page=<?php echo $i; ?><?php echo getQueryString(['page']); ?>" 
                                       class="pagination-button <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                    <?php endfor; 
                                    
                                    if ($endPage < $totalPages): ?>
                                    <span class="pagination-dots">...</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($currentPage < $totalPages): ?>
                                    <a href="?page=<?php echo $currentPage + 1; ?><?php echo getQueryString(['page']); ?>" class="pagination-button next">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                    <a href="?page=<?php echo $totalPages; ?><?php echo getQueryString(['page']); ?>" class="pagination-button last" title="Halaman Terakhir">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Sidebar -->
                        <div class="news-sidebar">
                            <!-- Popular News -->
                            <div class="sidebar-section">
                                <div class="sidebar-header">
                                    <i class="fas fa-fire"></i>
                                    <h3 class="sidebar-title">Berita Populer</h3>
                                </div>
                                <div class="popular-news-list">
                                    <?php if (!empty($popularNews)): ?>
                                        <?php foreach ($popularNews as $index => $news): ?>
                                        <div class="popular-news-item">
                                            <div class="popular-rank">
                                                <span class="rank-number"><?php echo $index + 1; ?></span>
                                            </div>
                                            <div class="popular-content">
                                                <h4 class="popular-title">
                                                    <a href="news.php?slug=<?php echo $news['slug']; ?>">
                                                        <?php echo htmlspecialchars($news['judul'] ?? ''); ?>
                                                    </a>
                                                </h4>
                                                <div class="popular-meta">
                                                    <div class="popular-views">
                                                        <i class="far fa-eye"></i> <?php echo number_format($news['views']); ?>
                                                    </div>
                                                    <div class="popular-date">
                                                        <?php echo date('d M Y', strtotime($news['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-popular-news">
                                            <i class="far fa-newspaper"></i>
                                            <p>Belum ada berita populer</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Tags Cloud -->
                            <?php if (!empty($allTags)): ?>
                            <div class="sidebar-section">
                                <div class="sidebar-header">
                                    <i class="fas fa-tags"></i>
                                    <h3 class="sidebar-title">Tag Populer</h3>
                                </div>
                                <div class="tags-cloud">
                                    <?php 
                                    $tagLimit = min(20, count($allTags));
                                    for ($i = 0; $i < $tagLimit; $i++): 
                                        $tag = $allTags[$i];
                                        $tagSize = '';
                                        if ($i < 5) {
                                            $tagSize = 'large';
                                        } elseif ($i < 10) {
                                            $tagSize = 'medium';
                                        } else {
                                            $tagSize = 'small';
                                        }
                                    ?>
                                    <a href="news.php?tag=<?php echo urlencode($tag); ?>" 
                                       class="tag-item <?php echo $tagSize; ?>">
                                        #<?php echo htmlspecialchars($tag ?? ''); ?>
                                    </a>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
         <footer class="dashboard-footer">
            <p>&copy; 2026 ALVETRIX. Semua hak dilindungi.</p>
            <p>
                <a href="<?php echo SITE_URL; ?>">Beranda</a> |
                <a href="contact.php">Kontak</a> |
                <a href="bpjs.php">BPJSTK</a>
            </p>
        </footer>
    </main>
</div>

<script>
// Sidebar Dropdown Toggle
function toggleDropdown(element, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) {
        return;
    }
    
    // Toggle dropdown visibility
    dropdown.classList.toggle('show');
    
    // Rotate icon
    element.classList.toggle('open');
}

// Sidebar Toggle Strategy for Mobile
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');

const setSidebarOpen = (open) => {
    if (!sidebar || !sidebarToggle || !sidebarOverlay) {
        return;
    }
    sidebar.classList.toggle('active', open);
    sidebarOverlay.classList.toggle('active', open);
    sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
    sidebarOverlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('sidebar-open', open);
};

if (sidebarToggle && sidebar && sidebarOverlay) {
    sidebarToggle.addEventListener('click', () => {
        const isOpen = sidebar.classList.contains('active');
        setSidebarOpen(!isOpen);
    });

    sidebarOverlay.addEventListener('click', () => {
        setSidebarOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            setSidebarOpen(false);
        }
    });
}
</script>

<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>

