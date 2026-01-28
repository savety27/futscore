<?php
require_once 'includes/header.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Inisialisasi koneksi database
$db = new Database();
$conn = $db->getConnection();

// Cek apakah ini single news atau news list
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$isSingleNews = !empty($slug);

if ($isSingleNews) {
    // ============================
    // SINGLE NEWS DETAIL
    // ============================
    
    // Get news from database
    $news = getNewsBySlug($slug);

    if (!$news) {
        header('Location: index.php');
        exit;
    }

    // Increment view count for this news
    incrementNewsViews($news['id']);

    // Get fresh news data after increment
    $news = getNewsBySlug($slug);

    $pageTitle = $news['title'];

    // Get related news
    $relatedNews = getRelatedNews($news['id'], 3);
    
    // Tampilkan single news
    ?>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
        </div>
        
        <!-- News Detail -->
        <div class="news-detail">
            <div class="news-header">
                <h1><?php echo htmlspecialchars($news['title']); ?></h1>
                <div class="news-meta-detail">
                    <span class="news-date"><i class="fas fa-calendar"></i> <?php echo formatDate($news['created_at']); ?></span>
                    <span class="news-views"><i class="fas fa-eye"></i> <span id="news-detail-views"><?php echo $news['views']; ?></span>x dilihat</span>
                    <?php if (!empty($news['author'])): ?>
                    <span class="news-author"><i class="fas fa-user"></i> <?php echo htmlspecialchars($news['author']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="news-image-detail">
                <?php
                $image = !empty($news['image']) ? $news['image'] : 'default-news.jpg';
                $imagePath = SITE_URL . '/images/news/' . $image;
                $defaultImage = SITE_URL . '/images/news/default-news.jpg';
                ?>
                <img src="<?php echo $imagePath; ?>" 
                     alt="<?php echo htmlspecialchars($news['title']); ?>"
                     class="news-image-full"
                     onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
            </div>
            
            <div class="news-content-detail">
                <?php 
                // Pisahkan konten berdasarkan baris baru
                $content = htmlspecialchars_decode($news['content']);
                $lines = explode("\n", $content);
                
                foreach ($lines as $line):
                    $line = trim($line);
                    if (!empty($line)):
                        // Deteksi apakah ini heading (mengandung "By" di awal)
                        if (preg_match('/^By\s+/i', $line) || preg_match('/^\d{1,2}\s+[A-Za-z]+\s+\d{4}/', $line)):
                            echo '<p class="news-meta-line"><strong>' . nl2br(htmlspecialchars($line)) . '</strong></p>';
                        // Deteksi apakah ini subjudul (semua huruf besar atau mengandung titik)
                        elseif (preg_match('/^[A-Z\s.,:!?]+$/', $line) && strlen($line) > 10):
                            echo '<h3 class="news-subtitle">' . nl2br(htmlspecialchars($line)) . '</h3>';
                        else:
                            echo '<p>' . nl2br(htmlspecialchars($line)) . '</p>';
                        endif;
                    else:
                        echo '<br>';
                    endif;
                endforeach;
                ?>
            </div>
            
            <?php if (!empty($news['tags'])): ?>
            <div class="news-tags">
                <i class="fas fa-tags"></i>
                <?php
                $tags = explode(',', $news['tags']);
                foreach ($tags as $tag):
                    $tag = trim($tag);
                    if (!empty($tag)):
                ?>
                <a href="news.php?tag=<?php echo urlencode($tag); ?>" class="tag"><?php echo htmlspecialchars($tag); ?></a>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
            <?php endif; ?>
            
            <!-- Social Share -->
            <div class="news-social-share">
                <span class="share-label">Share this article:</span>
                <div class="share-buttons">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/news.php?slug=' . $news['slug']); ?>" 
                       target="_blank" class="share-btn facebook">
                        <i class="fab fa-facebook-f"></i> Facebook
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . '/news.php?slug=' . $news['slug']); ?>&text=<?php echo urlencode($news['title']); ?>" 
                       target="_blank" class="share-btn twitter">
                        <i class="fab fa-twitter"></i> Twitter
                    </a>
                    <a href="https://wa.me/?text=<?php echo urlencode($news['title'] . ' - ' . SITE_URL . '/news.php?slug=' . $news['slug']); ?>" 
                       target="_blank" class="share-btn whatsapp">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Related News -->
        <?php if (!empty($relatedNews)): ?>
        <div class="related-news">
            <h3>Berita Terkait</h3>
            <div class="related-grid">
                <?php foreach ($relatedNews as $related): 
                    $image = !empty($related['image']) ? $related['image'] : 'default-news.jpg';
                    $imagePath = SITE_URL . '/images/news/' . $image;
                    $defaultImage = SITE_URL . '/images/news/default-news.jpg';
                ?>
                <div class="related-item">
                    <a href="news.php?slug=<?php echo $related['slug']; ?>">
                        <img src="<?php echo $imagePath; ?>" 
                             alt="<?php echo htmlspecialchars($related['title']); ?>"
                             class="related-image"
                             onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                        <div class="related-content">
                            <h4><?php echo htmlspecialchars($related['title']); ?></h4>
                            <div class="related-meta">
                                <span><?php echo formatDate($related['created_at']); ?></span>
                                <span><i class="fas fa-eye"></i> <?php echo $related['views']; ?>x</span>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // AJAX untuk update views real-time
    document.addEventListener('DOMContentLoaded', function() {
        const newsId = <?php echo $news['id']; ?>;
        
        // Cek jika view sudah dihitung dalam sesi ini
        const viewedKey = 'viewed_news_' + newsId;
        if (!sessionStorage.getItem(viewedKey)) {
            // Kirim request untuk update views
            fetch('update_views.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'news_id=' + newsId + '&action=increment'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.new_count) {
                    // Update tampilan views
                    document.getElementById('news-detail-views').textContent = data.new_count;
                    // Set flag bahwa view sudah dihitung
                    sessionStorage.setItem(viewedKey, 'true');
                }
            })
            .catch(error => console.error('Error updating views:', error));
        }
    });
    </script>
    <?php
    
} else {
    // ============================
    // NEWS LIST (ALL NEWS)
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

    // Build query dengan kondisi yang benar
    $whereConditions = ["status = 'published'"];
    $params = [];
    $types = '';

    if (!empty($searchKeyword)) {
        $whereConditions[] = "(title LIKE ? OR content LIKE ?)";
        $searchTerm = "%{$searchKeyword}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
        
        // Cek jika kolom tags ada, tambahkan ke search
        try {
            $checkTags = $conn->query("SHOW COLUMNS FROM news LIKE 'tags'");
            if ($checkTags->num_rows > 0) {
                array_pop($whereConditions); // Hapus kondisi sebelumnya
                $whereConditions[] = "(title LIKE ? OR content LIKE ? OR tags LIKE ?)";
                $params[] = $searchTerm;
                $types .= 's';
            }
        } catch (Exception $e) {
            // Kolom tags tidak ada, lanjutkan tanpa tags
        }
    }

    if (!empty($tagFilter)) {
        // Cek apakah kolom tags ada
        try {
            $checkTags = $conn->query("SHOW COLUMNS FROM news LIKE 'tags'");
            if ($checkTags->num_rows > 0) {
                $whereConditions[] = "FIND_IN_SET(?, tags) > 0";
                $params[] = $tagFilter;
                $types .= 's';
            }
        } catch (Exception $e) {
            // Kolom tags tidak ada, abaikan filter tag
            $tagFilter = '';
        }
    }

    $whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

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
    $countSql = "SELECT COUNT(*) as total FROM news {$whereClause}";
    
    if (empty($params)) {
        $countResult = $conn->query($countSql);
        if ($countResult) {
            $totalNews = $countResult->fetch_assoc()['total'];
        } else {
            $totalNews = 0;
        }
    } else {
        $countStmt = $conn->prepare($countSql);
        if ($countStmt) {
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $totalNews = $countResult->fetch_assoc()['total'];
            $countStmt->close();
        } else {
            $totalNews = 0;
        }
    }
    
    $totalPages = ceil($totalNews / $perPage);
    if ($totalPages < 1) $totalPages = 1;
    if ($currentPage > $totalPages) $currentPage = $totalPages;

    // Get news for current page
    $newsList = [];
    $limitParam = $perPage;
    $offsetParam = $offset;
    
    $listSql = "SELECT * FROM news {$whereClause} ORDER BY {$orderBy} LIMIT ? OFFSET ?";
    $listStmt = $conn->prepare($listSql);
    
    if ($listStmt) {
        if (!empty($params)) {
            // Gabungkan semua parameter
            $allParams = array_merge($params, [$limitParam, $offsetParam]);
            $allTypes = $types . 'ii';
            $listStmt->bind_param($allTypes, ...$allParams);
        } else {
            $listStmt->bind_param("ii", $limitParam, $offsetParam);
        }
        
        $listStmt->execute();
        $listResult = $listStmt->get_result();
        
        while ($row = $listResult->fetch_assoc()) {
            $newsList[] = $row;
        }
        $listStmt->close();
    }

    // Jangan tutup koneksi di sini! Kita masih butuh untuk sidebar
    // Simpan data yang dibutuhkan untuk sidebar sebelum koneksi ditutup nanti
    $popularNews = getPopularNews(5);

    // Get all unique tags (jika kolom tags ada)
    $allTags = [];
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM news LIKE 'tags'");
        if ($checkColumn->num_rows > 0) {
            $tagsSql = "SELECT tags FROM news WHERE status = 'published' AND tags IS NOT NULL AND tags != ''";
            $tagsResult = $conn->query($tagsSql);
            if ($tagsResult) {
                while ($row = $tagsResult->fetch_assoc()) {
                    if (!empty($row['tags'])) {
                        $tagArray = explode(',', $row['tags']);
                        foreach ($tagArray as $tag) {
                            $tag = trim($tag);
                            if (!empty($tag) && !in_array($tag, $allTags)) {
                                $allTags[] = $tag;
                            }
                        }
                    }
                }
            }
            sort($allTags);
        }
    } catch (Exception $e) {
        // Kolom tags tidak ada, array tetap kosong
    }

    // JANGAN tutup koneksi di sini - footer masih butuh
    $pageTitle = "News";
    ?>
    
    <div class="container">
        <!-- Header Section -->
        <div class="news-header">
            <h1 class="news-title">Latest News</h1>
            <p class="news-subtitle">Stay updated with the latest futsal news, match reports, and player updates</p>
        </div>
        
        <!-- Search and Filter Section -->
        <div class="news-controls">
            <div class="search-box">
                <form method="GET" action="news.php" class="search-form">
                    <div class="search-input-group">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               name="search" 
                               placeholder="Search news by title or content..." 
                               value="<?php echo htmlspecialchars($searchKeyword); ?>"
                               class="search-input">
                        <button type="submit" class="search-btn">Search</button>
                    </div>
                    <?php if (!empty($searchKeyword)): ?>
                    <a href="news.php" class="clear-search">Clear search</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="filter-controls">
                <?php if (!empty($allTags)): ?>
                <div class="filter-group">
                    <label for="tag"><i class="fas fa-tag"></i> Filter by Tag:</label>
                    <form method="GET" action="news.php" class="filter-form" id="tagForm">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchKeyword); ?>">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortBy); ?>">
                        <select name="tag" id="tag" class="filter-select" onchange="document.getElementById('tagForm').submit()">
                            <option value="">All Tags</option>
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
                    <label for="sort"><i class="fas fa-sort"></i> Sort by:</label>
                    <form method="GET" action="news.php" class="filter-form" id="sortForm">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchKeyword); ?>">
                        <input type="hidden" name="tag" value="<?php echo htmlspecialchars($tagFilter); ?>">
                        <select name="sort" id="sort" class="filter-select" onchange="document.getElementById('sortForm').submit()">
                            <option value="newest" <?php echo ($sortBy === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="popular" <?php echo ($sortBy === 'popular') ? 'selected' : ''; ?>>Most Popular</option>
                            <option value="oldest" <?php echo ($sortBy === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
        
        <?php if (!empty($searchKeyword)): ?>
        <div class="search-results-info">
            <p>Search results for: <strong>"<?php echo htmlspecialchars($searchKeyword); ?>"</strong> 
            (<?php echo $totalNews; ?> results found)</p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($tagFilter)): ?>
        <div class="tag-filter-info">
            <p>Showing news with tag: <span class="tag-badge"><?php echo htmlspecialchars($tagFilter); ?></span>
            <a href="news.php<?php echo getQueryString(['tag', 'page']); ?>" class="clear-filter">Clear filter</a></p>
        </div>
        <?php endif; ?>
        
        <div class="news-page-layout">
            <!-- Main News Grid -->
            <div class="news-main-content">
                <?php if (empty($newsList)): ?>
                <div class="no-results">
                    <i class="fas fa-newspaper"></i>
                    <h3>No news found</h3>
                    <p><?php 
                        if (!empty($searchKeyword)) {
                            echo "No results for '" . htmlspecialchars($searchKeyword) . "'";
                        } elseif (!empty($tagFilter)) {
                            echo "No news with tag '" . htmlspecialchars($tagFilter) . "'";
                        } else {
                            echo "No news articles available";
                        }
                    ?></p>
                    <?php if (!empty($searchKeyword) || !empty($tagFilter)): ?>
                    <a href="news.php" class="btn-view-all">View All News</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="news-grid-large">
                    <?php foreach ($newsList as $news): 
                        $image = !empty($news['image']) ? $news['image'] : 'default-news.jpg';
                        $imagePath = SITE_URL . '/images/news/' . $image;
                        $defaultImage = SITE_URL . '/images/news/default-news.jpg';
                    ?>
                    <div class="news-card">
                        <div class="news-card-image">
                            <a href="news.php?slug=<?php echo $news['slug']; ?>">
                                <img src="<?php echo $imagePath; ?>" 
                                     alt="<?php echo htmlspecialchars($news['title']); ?>"
                                     onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                            </a>
                            <?php if ($news['views'] > 100): ?>
                            <span class="trending-badge"><i class="fas fa-fire"></i> TRENDING</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="news-card-content">
                            <h3 class="news-card-title">
                                <a href="news.php?slug=<?php echo $news['slug']; ?>">
                                    <?php echo htmlspecialchars($news['title']); ?>
                                </a>
                            </h3>
                            
                            <div class="news-card-meta">
                                <span class="news-date"><i class="far fa-calendar"></i> <?php echo formatDate($news['created_at']); ?></span>
                                <span class="news-views"><i class="far fa-eye"></i> <?php echo $news['views']; ?></span>
                                <?php if (!empty($news['author'])): ?>
                                <span class="news-author"><i class="far fa-user"></i> <?php echo htmlspecialchars($news['author']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <p class="news-card-excerpt">
                                <?php 
                                $excerpt = strip_tags($news['content']);
                                if (strlen($excerpt) > 120) {
                                    echo htmlspecialchars(substr($excerpt, 0, 120)) . '...';
                                } else {
                                    echo htmlspecialchars($excerpt);
                                }
                                ?>
                            </p>
                            
                            <div class="news-card-footer">
                                <a href="news.php?slug=<?php echo $news['slug']; ?>" class="read-more">
                                    Read More <i class="fas fa-arrow-right"></i>
                                </a>
                                <?php if (!empty($news['tags'])): 
                                    $tagsArray = explode(',', $news['tags']);
                                    $firstTag = trim($tagsArray[0]);
                                    if (!empty($firstTag)):
                                ?>
                                <a href="news.php?tag=<?php echo urlencode($firstTag); ?>" class="news-tag">
                                    #<?php echo htmlspecialchars($firstTag); ?>
                                </a>
                                <?php endif; endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-wrapper">
                    <nav class="pagination-nav">
                        <?php if ($currentPage > 1): ?>
                        <a href="?page=1<?php echo getQueryString(['page']); ?>" class="pagination-link" title="First Page">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $currentPage - 1; ?><?php echo getQueryString(['page']); ?>" class="pagination-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $startPage + 4);
                        if ($startPage > 1) echo '<span class="pagination-ellipsis">...</span>';
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="?page=<?php echo $i; ?><?php echo getQueryString(['page']); ?>" 
                           class="pagination-link <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; 
                        
                        if ($endPage < $totalPages) echo '<span class="pagination-ellipsis">...</span>';
                        ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo $currentPage + 1; ?><?php echo getQueryString(['page']); ?>" class="pagination-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                        <a href="?page=<?php echo $totalPages; ?><?php echo getQueryString(['page']); ?>" class="pagination-link" title="Last Page">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                        <?php endif; ?>
                    </nav>
                    
                    <div class="pagination-info">
                        Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?> 
                        (Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $perPage, $totalNews); ?> of <?php echo $totalNews; ?> news)
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="news-sidebar">
                <!-- Popular News -->
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <i class="fas fa-fire"></i> Popular News
                    </h3>
                    <div class="popular-news-list">
                        <?php if (!empty($popularNews)): ?>
                            <?php foreach ($popularNews as $index => $news): ?>
                            <div class="popular-news-item">
                                <div class="popular-rank">
                                    <span class="rank-number"><?php echo $index + 1; ?></span>
                                </div>
                                <div class="popular-content">
                                    <h4>
                                        <a href="news.php?slug=<?php echo $news['slug']; ?>">
                                            <?php echo htmlspecialchars($news['title']); ?>
                                        </a>
                                    </h4>
                                    <div class="popular-meta">
                                        <span><i class="far fa-eye"></i> <?php echo $news['views']; ?></span>
                                        <span><?php echo formatDate($news['created_at']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-data">No popular news yet</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tags Cloud (hanya jika ada tags) -->
                <?php if (!empty($allTags)): ?>
                <div class="sidebar-widget">
                    <h3 class="widget-title">
                        <i class="fas fa-tags"></i> Popular Tags
                    </h3>
                    <div class="tags-cloud">
                        <?php 
                        $tagLimit = min(15, count($allTags));
                        for ($i = 0; $i < $tagLimit; $i++): 
                            $tag = $allTags[$i];
                            $tagClass = '';
                            if ($i < 5) $tagClass = 'tag-large';
                            elseif ($i < 10) $tagClass = 'tag-medium';
                            else $tagClass = 'tag-small';
                        ?>
                        <a href="news.php?tag=<?php echo urlencode($tag); ?>" 
                           class="tag-cloud-item <?php echo $tagClass; ?>">
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

// JANGAN tutup koneksi di sini
?>

<style>
/* Tambahan CSS untuk Header News */
.news-header {
    text-align: center;
    margin: 30px 0 40px;
    padding: 30px;
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--gray-dark) 100%);
    border-radius: 10px;
    color: var(--white);
}

.news-title {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: var(--white);
}

.news-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    max-width: 800px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Styles for SINGLE NEWS */
.breadcrumb {
    margin: 20px 0;
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
}

.breadcrumb span {
    color: var(--white);
    font-weight: 600;
}

.news-detail {
    background-color: var(--gray-dark);
    border-radius: 10px;
    padding: 30px;
    margin: 20px 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.news-header h1 {
    color: var(--white);
    font-size: 28px;
    margin-bottom: 15px;
    line-height: 1.4;
}

.news-meta-detail {
    display: flex;
    gap: 20px;
    color: var(--gray-light);
    font-size: 14px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--gray);
}

.news-meta-detail i {
    color: var(--primary-green);
    margin-right: 5px;
}

.news-image-detail {
    margin-bottom: 30px;
    border-radius: 8px;
    overflow: hidden;
}

.news-image-full {
    width: 100%;
    height: auto;
    max-height: 500px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.news-image-full:hover {
    transform: scale(1.02);
}

.news-content-detail {
    color: var(--white);
    font-size: 16px;
    line-height: 1.8;
    margin-bottom: 30px;
}

.news-content-detail p {
    margin-bottom: 15px;
}

.news-content-detail .news-meta-line {
    color: var(--gray-light);
    font-size: 14px;
    font-style: italic;
    margin-bottom: 10px;
}

.news-content-detail .news-subtitle {
    color: var(--primary-green);
    font-size: 20px;
    margin: 25px 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray);
}

.news-tags {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--gray);
}

.news-tags i {
    color: var(--primary-green);
    margin-right: 10px;
}

.tag {
    display: inline-block;
    background-color: var(--gray);
    color: var(--white);
    padding: 5px 15px;
    border-radius: 20px;
    margin: 5px;
    font-size: 14px;
    transition: all 0.3s ease;
    text-decoration: none;
}

.tag:hover {
    background-color: var(--primary-green);
    color: var(--gray-dark);
    transform: translateY(-2px);
}

.news-social-share {
    margin-top: 30px;
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
}

.share-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.share-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.share-btn.facebook {
    background-color: #1877f2;
    color: white;
}

.share-btn.twitter {
    background-color: #1da1f2;
    color: white;
}

.share-btn.whatsapp {
    background-color: #25d366;
    color: white;
}

.share-btn:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.related-news {
    margin-top: 50px;
}

.related-news h3 {
    color: var(--primary-green);
    font-size: 22px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-green);
}

.related-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.related-item {
    background-color: var(--gray-dark);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.related-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.1);
}

.related-item a {
    text-decoration: none;
    color: inherit;
    display: block;
}

.related-image {
    width: 100%;
    height: 180px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.related-item:hover .related-image {
    transform: scale(1.05);
}

.related-content {
    padding: 15px;
}

.related-content h4 {
    color: var(--white);
    font-size: 16px;
    margin-bottom: 10px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
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

/* Styles for NEWS LIST */
.news-controls {
    background: var(--gray-dark);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 30px;
}

.search-box {
    margin-bottom: 20px;
}

.search-form {
    position: relative;
}

.search-input-group {
    display: flex;
    align-items: center;
    background: var(--black);
    border-radius: 8px;
    padding: 5px 15px;
    border: 1px solid var(--gray);
}

.search-input-group i {
    color: var(--gray-light);
    margin-right: 10px;
}

.search-input {
    flex: 1;
    background: transparent;
    border: none;
    color: var(--white);
    padding: 12px 0;
    font-size: 1rem;
    outline: none;
}

.search-input:focus {
    background: transparent;
}

.search-btn {
    background: var(--primary-green);
    color: var(--black);
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    outline: none;
}

.search-btn:hover {
    background: var(--white);
}

.clear-search {
    display: inline-block;
    margin-top: 10px;
    color: var(--primary-green);
    text-decoration: none;
    font-size: 0.9rem;
}

.filter-controls {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-group label {
    color: var(--white);
    font-weight: 500;
    white-space: nowrap;
}

.filter-form {
    display: inline;
}

.filter-select {
    background: var(--black);
    color: var(--white);
    border: 1px solid var(--gray);
    padding: 8px 15px;
    border-radius: 6px;
    min-width: 150px;
    cursor: pointer;
    outline: none;
    font-size: 14px;
}

.filter-select:focus {
    border-color: var(--primary-green);
}

.news-page-layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 30px;
    margin-bottom: 50px;
}

.news-main-content {
    min-height: 500px;
}

.no-results {
    text-align: center;
    padding: 50px 20px;
    color: var(--gray-light);
}

.no-results i {
    font-size: 3rem;
    margin-bottom: 20px;
    color: var(--gray);
}

.no-results h3 {
    color: var(--white);
    margin-bottom: 10px;
}

.btn-view-all {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 20px;
    background: var(--primary-green);
    color: var(--black);
    text-decoration: none;
    border-radius: 5px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-view-all:hover {
    background: var(--white);
}

.news-grid-large {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.news-card {
    background: var(--gray-dark);
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.news-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.1);
}

.news-card-image {
    position: relative;
    height: 200px;
    overflow: hidden;
    flex-shrink: 0;
}

.news-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.news-card:hover .news-card-image img {
    transform: scale(1.05);
}

.trending-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #ff4757;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    z-index: 1;
}

.news-card-content {
    padding: 20px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.news-card-title {
    font-size: 1.2rem;
    color: var(--white);
    margin-bottom: 10px;
    line-height: 1.4;
}

.news-card-title a {
    color: inherit;
    text-decoration: none;
    transition: color 0.3s ease;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.news-card-title a:hover {
    color: var(--primary-green);
}

.news-card-meta {
    display: flex;
    gap: 15px;
    color: var(--gray-light);
    font-size: 0.85rem;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.news-card-meta i {
    margin-right: 5px;
}

.news-card-excerpt {
    color: var(--gray-light);
    line-height: 1.6;
    margin-bottom: 20px;
    flex-grow: 1;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.news-card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
    flex-wrap: wrap;
    gap: 10px;
}

.read-more {
    color: var(--primary-green);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.read-more:hover {
    color: var(--white);
}

.read-more i {
    margin-left: 5px;
    transition: transform 0.3s ease;
}

.read-more:hover i {
    transform: translateX(5px);
}

.news-tag {
    background: var(--gray);
    color: var(--white);
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.8rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.news-tag:hover {
    background: var(--primary-green);
    color: var(--black);
}

/* Pagination */
.pagination-wrapper {
    margin-top: 40px;
}

.pagination-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.pagination-link {
    display: inline-flex;
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
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.pagination-link:hover {
    background: var(--primary-green);
    color: var(--black);
}

.pagination-link.active {
    background: var(--primary-green);
    color: var(--black);
    font-weight: 700;
}

.pagination-link i {
    font-size: 0.9rem;
}

.pagination-info {
    text-align: center;
    color: var(--gray-light);
    margin-top: 15px;
    font-size: 0.9rem;
}

.pagination-ellipsis {
    color: var(--gray-light);
    padding: 0 10px;
}

/* Sidebar */
.news-sidebar {
    position: sticky;
    top: 20px;
    height: fit-content;
}

.sidebar-widget {
    background: var(--gray-dark);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
}

.widget-title {
    color: var(--white);
    font-size: 1.1rem;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.widget-title i {
    color: var(--primary-green);
}

.popular-news-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.popular-news-item {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--gray);
}

.popular-news-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.popular-rank .rank-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    background: var(--primary-green);
    color: var(--black);
    border-radius: 50%;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.popular-content {
    flex: 1;
    min-width: 0;
}

.popular-content h4 {
    margin-bottom: 5px;
    line-height: 1.4;
}

.popular-content h4 a {
    color: var(--white);
    text-decoration: none;
    font-size: 0.95rem;
    transition: color 0.3s ease;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.popular-content h4 a:hover {
    color: var(--primary-green);
}

.popular-meta {
    display: flex;
    gap: 15px;
    color: var(--gray-light);
    font-size: 0.8rem;
    flex-wrap: wrap;
}

.popular-meta i {
    margin-right: 3px;
}

.tags-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tag-cloud-item {
    display: inline-block;
    padding: 6px 12px;
    background: var(--gray);
    color: var(--white);
    border-radius: 20px;
    text-decoration: none;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.tag-cloud-item:hover {
    background: var(--primary-green);
    color: var(--black);
    transform: translateY(-2px);
}

.tag-large { font-size: 1rem; padding: 8px 15px; }
.tag-medium { font-size: 0.9rem; padding: 6px 12px; }
.tag-small { font-size: 0.8rem; padding: 5px 10px; }

/* Additional styles */
.search-results-info, .tag-filter-info {
    background: var(--gray-dark);
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
    color: var(--white);
}

.tag-badge {
    background: var(--primary-green);
    color: var(--black);
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 600;
    margin: 0 5px;
    display: inline-block;
}

.clear-filter {
    color: var(--primary-green);
    text-decoration: none;
    margin-left: 15px;
    font-size: 0.9rem;
    white-space: nowrap;
}

.clear-filter:hover {
    text-decoration: underline;
}

.no-data {
    color: var(--gray-light);
    font-size: 0.9rem;
    text-align: center;
    padding: 10px 0;
}

/* Mobile Responsive */
@media (max-width: 1200px) {
    .news-grid-large {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }
}

@media (max-width: 992px) {
    .news-page-layout {
        grid-template-columns: 1fr;
    }
    
    .news-sidebar {
        position: static;
        margin-top: 30px;
    }
    
    .news-grid-large {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }
}

@media (max-width: 768px) {
    .news-title {
        font-size: 2rem;
    }
    
    .news-subtitle {
        font-size: 1rem;
    }
    
    .news-header {
        padding: 20px;
    }
    
    .news-header h1 {
        font-size: 22px;
    }
    
    .news-meta-detail {
        flex-direction: column;
        gap: 10px;
    }
    
    .news-detail {
        padding: 20px;
    }
    
    .related-grid {
        grid-template-columns: 1fr;
    }
    
    .tag {
        font-size: 12px;
        padding: 4px 12px;
    }
    
    .share-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .share-btn {
        justify-content: center;
        width: 100%;
    }
    
    .filter-controls {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-select {
        width: 100%;
        min-width: auto;
    }
    
    .news-grid-large {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .pagination-nav {
        gap: 5px;
    }
    
    .pagination-link {
        min-width: 35px;
        height: 35px;
        padding: 0 10px;
        font-size: 0.9rem;
    }
    
    .pagination-info {
        font-size: 0.8rem;
    }
    
    .search-input-group {
        flex-wrap: wrap;
    }
    
    .search-input {
        order: 1;
        width: 100%;
        margin-bottom: 10px;
    }
    
    .search-btn {
        order: 2;
        width: 100%;
    }
    
    .news-card-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .read-more {
        align-self: flex-start;
    }
}

@media (max-width: 480px) {
    .news-title {
        font-size: 1.8rem;
    }
    
    .news-subtitle {
        font-size: 0.9rem;
    }
    
    .news-card-meta {
        flex-direction: column;
        gap: 8px;
    }
    
    .popular-news-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .popular-rank {
        align-self: flex-start;
    }
    
    .pagination-link {
        min-width: 30px;
        height: 30px;
        padding: 0 8px;
        font-size: 0.8rem;
    }
    
    .pagination-link i {
        font-size: 0.8rem;
    }
}

/* Tambahan untuk form filter */
.filter-form {
    display: block;
}

.filter-select {
    width: 100%;
}

/* Fix untuk mobile touch */
@media (hover: none) and (pointer: coarse) {
    .tag:hover,
    .share-btn:hover,
    .pagination-link:hover,
    .news-card:hover,
    .related-item:hover {
        transform: none;
    }
    
    .tag:active,
    .share-btn:active,
    .pagination-link:active {
        transform: scale(0.95);
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>