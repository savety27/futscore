<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

// Gunakan koneksi database dari functions.php
global $db;
$conn = $db->getConnection();

// Cek apakah ini single news atau news list
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$isSingleNews = !empty($slug);

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
    $relatedNews = [];
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
    
    // Tampilkan single news
    ?>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Home</a> › 
            <a href="news.php">Berita</a> › 
            <span><?php echo htmlspecialchars($news['judul']); ?></span>
        </div>
        
        <!-- Detail Berita -->
        <div class="news-detail">
            <div class="news-header-detail">
                <h1><?php echo htmlspecialchars($news['judul']); ?></h1>
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
                        <?php echo htmlspecialchars($news['penulis']); ?>
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
                     alt="<?php echo htmlspecialchars($news['judul']); ?>"
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
                            echo '<p>' . nl2br(htmlspecialchars($line)) . '</p>';
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
                <a href="news.php?tag=<?php echo urlencode($tag); ?>" class="tag-item"><?php echo htmlspecialchars($tag); ?></a>
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
        
        <!-- Berita Terkait -->
        <?php if (!empty($relatedNews)): ?>
        <div class="related-news-section">
            <h3 class="related-news-title">Berita Terkait</h3>
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
                                 alt="<?php echo htmlspecialchars($related['judul']); ?>"
                                 class="related-image"
                                 onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                        </div>
                        <div class="related-content">
                            <h4 class="related-title"><?php echo htmlspecialchars($related['judul']); ?></h4>
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
    </div>
    <?php
    
} else {
    // ============================
    // LIST BERITA (SEMUA BERITA)
    // ============================
    
    // Setup pagination
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($currentPage < 1) $currentPage = 1;
    
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
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) $currentPage = $totalPages;

    // Get news for current page
    $newsList = [];
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
    $popularNews = [];
    $popularSql = "SELECT * FROM berita WHERE status = 'published' ORDER BY views DESC LIMIT 5";
    $popularStmt = $conn->prepare($popularSql);
    $popularStmt->execute();
    $popularResult = $popularStmt->get_result();
    
    while ($row = $popularResult->fetch_assoc()) {
        $popularNews[] = $row;
    }

    // Get all unique tags
    $allTags = [];
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
    ?>
    
    <div class="container">
        <!-- Header Section -->
        <div class="news-page-header">
            <h1 class="page-title">Berita Terbaru</h1>
            <p class="page-subtitle">Ikuti berita terbaru futsal, laporan pertandingan, dan update pemain</p>
        </div>
        
        <!-- Search and Filter Section -->
        <div class="news-controls-section">
            <div class="search-container">
                <form method="GET" action="news.php" class="search-form">
                    <div class="search-wrapper">
                        <div class="search-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <input type="text" 
                               name="search" 
                               placeholder="Cari berita..." 
                               value="<?php echo htmlspecialchars($searchKeyword); ?>"
                               class="search-input">
                        <button type="submit" class="search-button">Cari</button>
                        <?php if (!empty($searchKeyword)): ?>
                        <a href="news.php" class="clear-search-button">
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
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchKeyword); ?>">
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortBy); ?>">
                            <select name="tag" id="tag" class="filter-select" onchange="document.getElementById('tagForm').submit()">
                                <option value="">Semua Tag</option>
                                <?php foreach ($allTags as $tag): ?>
                                <option value="<?php echo htmlspecialchars($tag); ?>" 
                                    <?php echo ($tagFilter === $tag) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tag); ?>
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
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchKeyword); ?>">
                            <input type="hidden" name="tag" value="<?php echo htmlspecialchars($tagFilter); ?>">
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
        <div class="results-info">
            <?php if (!empty($searchKeyword)): ?>
            <div class="search-results">
                <span class="results-label">Hasil pencarian:</span>
                <span class="results-keyword">"<?php echo htmlspecialchars($searchKeyword); ?>"</span>
                <span class="results-count">(<?php echo $totalNews; ?> hasil)</span>
                <a href="news.php" class="clear-results">
                    <i class="fas fa-times"></i> Hapus filter
                </a>
            </div>
            <?php endif; ?>
            <?php if (!empty($tagFilter)): ?>
                <div class="tag-results">
                <span class="results-label">Filter tag:</span>
                <span class="tag-badge"><?php echo htmlspecialchars($tagFilter); ?></span>
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
                <div class="no-news-found">
                    <div class="no-results-icon">
                        <i class="far fa-newspaper"></i>
                    </div>
                    <h3 class="no-results-title">Tidak ada berita ditemukan</h3>
                    <p class="no-results-message">
                        <?php 
                        if (!empty($searchKeyword)) {
                            echo "Tidak ada hasil untuk '" . htmlspecialchars($searchKeyword) . "'";
                        } elseif (!empty($tagFilter)) {
                            echo "Tidak ada berita dengan tag '" . htmlspecialchars($tagFilter) . "'";
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
                                     alt="<?php echo htmlspecialchars($news['judul']); ?>"
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
                                    <?php echo htmlspecialchars($firstTag); ?>
                                </a>
                                <?php endif; endif; ?>
                            </div>
                            
                            <h3 class="news-item-title">
                                <a href="news.php?slug=<?php echo $news['slug']; ?>">
                                    <?php echo htmlspecialchars($news['judul']); ?>
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
                                    <span><?php echo htmlspecialchars($news['penulis']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <p class="news-item-excerpt">
                                <?php 
                                $excerpt = strip_tags($news['konten']);
                                $excerpt = htmlspecialchars($excerpt);
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
                                            <?php echo htmlspecialchars($news['judul']); ?>
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
                            if ($i < 5) $tagSize = 'large';
                            elseif ($i < 10) $tagSize = 'medium';
                            else $tagSize = 'small';
                        ?>
                        <a href="news.php?tag=<?php echo urlencode($tag); ?>" 
                           class="tag-item <?php echo $tagSize; ?>">
                            #<?php echo htmlspecialchars($tag); ?>
                        </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
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

<style>
/* ===== VARIABLES ===== */
:root {
    --primary-green: #00ff88;
    --dark-green: #008055;
    --black: #0a0a0a;
    --gray-dark: #1a1a1a;
    --gray: #333333;
    --gray-light: #666666;
    --white: #ffffff;
}

/* ===== GENERAL STYLES ===== */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* ===== BREADCRUMB ===== */
.breadcrumb {
    margin: 20px 0 30px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-dark);
    color: var(--gray-light);
    font-size: 14px;
}

.breadcrumb a {
    color: var(--primary-green);
    text-decoration: none;
    transition: color 0.3s ease;
}

.breadcrumb a:hover {
    color: var(--white);
    text-decoration: underline;
}

.breadcrumb span {
    color: var(--white);
    font-weight: 500;
}

/* ===== PAGE HEADER ===== */
.news-page-header {
    text-align: center;
    margin: 30px 0 40px;
    padding: 40px 20px;
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--gray-dark) 100%);
    border-radius: 10px;
    color: var(--white);
}

.page-title {
    font-size: 2.5rem;
    margin-bottom: 15px;
    color: var(--white);
    font-weight: 700;
}

.page-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    max-width: 700px;
    margin: 0 auto;
    line-height: 1.6;
}

/* ===== SEARCH AND FILTER ===== */
.news-controls-section {
    background: var(--gray-dark);
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.search-container {
    margin-bottom: 20px;
}

.search-wrapper {
    display: flex;
    align-items: center;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    padding: 5px;
    border: 1px solid var(--gray);
    transition: all 0.3s ease;
}

.search-wrapper:focus-within {
    border-color: var(--primary-green);
    box-shadow: 0 0 0 2px rgba(0, 255, 136, 0.1);
}

.search-icon {
    padding: 0 15px;
    color: var(--gray-light);
    font-size: 16px;
}

.search-input {
    flex: 1;
    background: transparent;
    border: none;
    color: var(--white);
    padding: 12px 0;
    font-size: 16px;
    outline: none;
}

.search-input::placeholder {
    color: var(--gray-light);
}

.search-button {
    background: var(--primary-green);
    color: var(--black);
    border: none;
    padding: 12px 25px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    margin-left: 10px;
}

.search-button:hover {
    background: var(--white);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 255, 136, 0.3);
}

.clear-search-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: var(--gray);
    color: var(--gray-light);
    border-radius: 50%;
    text-decoration: none;
    margin-left: 10px;
    transition: all 0.3s ease;
}

.clear-search-button:hover {
    background: var(--primary-green);
    color: var(--black);
}

/* Filter Controls */
.filter-container {
    margin-top: 20px;
}

.filter-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-label {
    color: var(--white);
    font-weight: 500;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 5px;
}

.filter-label i {
    color: var(--primary-green);
}

.filter-select {
    background: rgba(0, 0, 0, 0.2);
    color: var(--white);
    border: 1px solid var(--gray);
    padding: 10px 15px;
    border-radius: 6px;
    min-width: 150px;
    cursor: pointer;
    outline: none;
    font-size: 14px;
    transition: all 0.3s ease;
}

.filter-select:focus {
    border-color: var(--primary-green);
}

/* ===== RESULTS INFO ===== */
.results-info {
    background: var(--gray-dark);
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 25px;
    border-left: 4px solid var(--primary-green);
}

.search-results,
.tag-results {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.results-label {
    color: var(--gray-light);
    font-size: 14px;
}

.results-keyword {
    color: var(--primary-green);
    font-weight: 600;
}

.results-count {
    color: var(--white);
    background: var(--gray);
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
}

.clear-results,
.clear-tag {
    color: var(--primary-green);
    text-decoration: none;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
    transition: color 0.3s ease;
}

.clear-results:hover,
.clear-tag:hover {
    color: var(--white);
    text-decoration: underline;
}

.tag-badge {
    background: var(--primary-green);
    color: var(--black);
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
}

/* ===== NEWS LAYOUT ===== */
.news-content-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 30px;
    margin-bottom: 50px;
}

.news-grid-container {
    min-height: 500px;
}

/* ===== NEWS GRID ===== */
.news-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.news-item {
    background: var(--gray-dark);
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid var(--gray);
}

.news-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 255, 136, 0.1);
    border-color: var(--primary-green);
}

.news-item-image {
    position: relative;
    height: 200px;
    overflow: hidden;
}

.news-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.news-item:hover .news-image {
    transform: scale(1.05);
}

.news-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, transparent 0%, rgba(0, 0, 0, 0.7) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.news-item:hover .news-image-overlay {
    opacity: 1;
}

.news-trending {
    position: absolute;
    top: 15px;
    right: 15px;
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    z-index: 2;
}

.news-item-content {
    padding: 20px;
}

.news-category {
    margin-bottom: 10px;
}

.category-tag {
    display: inline-block;
    background: rgba(0, 0, 0, 0.2);
    color: var(--primary-green);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--primary-green);
    transition: all 0.3s ease;
}

.category-tag:hover {
    background: var(--primary-green);
    color: var(--black);
}

.news-item-title {
    margin: 0 0 15px;
    font-size: 18px;
    line-height: 1.4;
}

.news-item-title a {
    color: var(--white);
    text-decoration: none;
    transition: color 0.3s ease;
}

.news-item-title a:hover {
    color: var(--primary-green);
}

.news-item-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
    color: var(--gray-light);
    font-size: 13px;
}

.meta-item i {
    color: var(--primary-green);
    font-size: 12px;
}

.news-item-excerpt {
    color: var(--gray-light);
    line-height: 1.6;
    margin-bottom: 20px;
    font-size: 14px;
}

.news-item-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.read-more-button {
    color: var(--primary-green);
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.read-more-button:hover {
    color: var(--white);
    gap: 12px;
}

.read-more-button i {
    font-size: 12px;
    transition: all 0.3s ease;
}

/* ===== NO RESULTS ===== */
.no-news-found {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-light);
}

.no-results-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    color: var(--gray);
}

.no-results-title {
    color: var(--white);
    margin-bottom: 15px;
    font-size: 24px;
}

.no-results-message {
    font-size: 16px;
    margin-bottom: 25px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.view-all-button {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: var(--primary-green);
    color: var(--black);
    padding: 12px 25px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.view-all-button:hover {
    background: var(--white);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 255, 136, 0.3);
}

/* ===== PAGINATION ===== */
.pagination-container {
    margin-top: 40px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    align-items: center;
}

.pagination-info {
    color: var(--gray-light);
    font-size: 14px;
}

.pagination {
    display: flex;
    gap: 8px;
    align-items: center;
}

.pagination-button {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 15px;
    background: var(--gray-dark);
    color: var(--white);
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    border: 1px solid var(--gray);
    transition: all 0.3s ease;
}

.pagination-button:hover {
    background: var(--primary-green);
    color: var(--black);
    border-color: var(--primary-green);
}

.pagination-button.active {
    background: var(--primary-green);
    color: var(--black);
    font-weight: 700;
    border-color: var(--primary-green);
}

.pagination-dots {
    color: var(--gray-light);
    padding: 0 10px;
}

/* ===== SIDEBAR ===== */
.news-sidebar {
    position: sticky;
    top: 20px;
    height: fit-content;
}

.sidebar-section {
    background: var(--gray-dark);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
    border: 1px solid var(--gray);
}

.sidebar-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--gray);
}

.sidebar-header i {
    color: var(--primary-green);
    font-size: 18px;
}

.sidebar-title {
    color: var(--white);
    font-size: 18px;
    margin: 0;
}

/* Popular News */
.popular-news-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.popular-news-item {
    display: flex;
    gap: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--gray);
}

.popular-news-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.popular-rank {
    flex-shrink: 0;
}

.rank-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
    color: var(--black);
    border-radius: 50%;
    font-weight: 700;
    font-size: 14px;
}

.popular-content {
    flex: 1;
}

.popular-title {
    margin: 0 0 5px;
    line-height: 1.4;
}

.popular-title a {
    color: var(--white);
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
}

.popular-title a:hover {
    color: var(--primary-green);
}

.popular-meta {
    display: flex;
    gap: 15px;
    color: var(--gray-light);
    font-size: 12px;
}

.no-popular-news {
    text-align: center;
    padding: 20px;
    color: var(--gray-light);
}

.no-popular-news i {
    font-size: 2rem;
    margin-bottom: 10px;
    display: block;
}

/* Tags Cloud */
.tags-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tag-item {
    display: inline-block;
    padding: 6px 12px;
    background: rgba(0, 0, 0, 0.2);
    color: var(--white);
    border-radius: 20px;
    text-decoration: none;
    font-size: 13px;
    transition: all 0.3s ease;
    border: 1px solid var(--gray);
}

.tag-item:hover {
    background: var(--primary-green);
    color: var(--black);
    transform: translateY(-2px);
    border-color: var(--primary-green);
}

.tag-item.large {
    font-size: 14px;
    padding: 8px 15px;
}

.tag-item.medium {
    font-size: 13px;
    padding: 6px 12px;
}

.tag-item.small {
    font-size: 12px;
    padding: 5px 10px;
}

/* ===== SINGLE NEWS DETAIL ===== */
.news-detail {
    background: var(--gray-dark);
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 40px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.news-header-detail {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--gray);
}

.news-header-detail h1 {
    color: var(--white);
    font-size: 32px;
    line-height: 1.3;
    margin-bottom: 15px;
    font-weight: 700;
}

.news-meta-detail {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
    color: var(--gray-light);
    font-size: 14px;
}

.news-meta-detail span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.news-meta-detail i {
    color: var(--primary-green);
    font-size: 12px;
}

/* News Image */
.news-image-detail-container {
    margin-bottom: 30px;
    border-radius: 8px;
    overflow: hidden;
    position: relative;
    background: rgba(0, 0, 0, 0.2);
}

.news-image-detail-main {
    width: 100%;
    height: auto;
    max-height: 500px;
    object-fit: contain;
    object-position: center;
    display: block;
    transition: transform 0.5s ease;
}

.news-image-detail-main:hover {
    transform: scale(1.01);
}

/* Content */
.news-content-detail-wrapper {
    color: var(--white);
    font-size: 16px;
    line-height: 1.8;
    margin-bottom: 30px;
}

.news-content-detail-wrapper p {
    margin-bottom: 20px;
}

.news-content-detail-wrapper h2,
.news-content-detail-wrapper h3,
.news-content-detail-wrapper h4 {
    color: var(--primary-green);
    margin: 25px 0 15px;
}

/* Tags */
.news-tags-container {
    margin: 30px 0;
    padding-top: 20px;
    border-top: 1px solid var(--gray);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.news-tags-container i {
    color: var(--primary-green);
    margin-right: 5px;
}

/* Social Share */
.news-social-share-container {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid var(--gray);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.share-label {
    color: var(--gray-light);
    font-size: 14px;
    font-weight: 500;
}

.share-buttons {
    display: flex;
    gap: 10px;
}

.share-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 16px;
}

.share-btn.facebook {
    background: #1877f2;
}

.share-btn.twitter {
    background: #1da1f2;
}

.share-btn.whatsapp {
    background: #25d366;
}

.share-btn.email {
    background: #ea4335;
}

.share-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

/* Related News */
.related-news-section {
    margin-top: 50px;
}

.related-news-title {
    color: var(--primary-green);
    font-size: 24px;
    margin-bottom: 25px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-green);
}

.related-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.related-item-card {
    background: var(--gray-dark);
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.3s ease;
    border: 1px solid var(--gray);
}

.related-item-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 255, 136, 0.1);
    border-color: var(--primary-green);
}

.related-link {
    text-decoration: none;
    color: inherit;
    display: block;
}

.related-image-container {
    height: 180px;
    overflow: hidden;
    position: relative;
}

.related-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.related-item-card:hover .related-image {
    transform: scale(1.05);
}

.related-content {
    padding: 15px;
}

.related-title {
    color: var(--white);
    font-size: 16px;
    line-height: 1.4;
    margin-bottom: 10px;
    font-weight: 600;
}

.related-meta {
    display: flex;
    justify-content: space-between;
    color: var(--gray-light);
    font-size: 12px;
}

.related-meta i {
    color: var(--primary-green);
    margin-right: 3px;
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 1200px) {
    .news-grid {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }
}

@media (max-width: 992px) {
    .news-content-layout {
        grid-template-columns: 1fr;
    }
    
    .news-sidebar {
        position: static;
        margin-top: 30px;
    }
    
    .news-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
}

@media (max-width: 768px) {
    .page-title {
        font-size: 2rem;
    }
    
    .page-subtitle {
        font-size: 1rem;
    }
    
    .news-page-header {
        padding: 30px 15px;
    }
    
    .news-detail {
        padding: 20px;
    }
    
    .news-header-detail h1 {
        font-size: 24px;
    }
    
    .related-grid {
        grid-template-columns: 1fr;
    }
    
    .search-wrapper {
        flex-wrap: wrap;
    }
    
    .search-input {
        order: 1;
        width: 100%;
        margin-top: 10px;
    }
    
    .search-button {
        order: 2;
        margin-left: 0;
        margin-right: 10px;
    }
    
    .clear-search-button {
        order: 3;
    }
    
    .filter-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-select {
        width: 100%;
    }
    
    .news-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .news-item-image {
        height: 180px;
    }
    
    .pagination {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .news-meta-detail {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}

@media (max-width: 480px) {
    .container {
        padding: 0 10px;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
    
    .news-controls-section {
        padding: 15px;
    }
    
    .news-grid {
        gap: 15px;
    }
    
    .news-item-content {
        padding: 15px;
    }
    
    .news-item-meta {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>