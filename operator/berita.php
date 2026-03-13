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

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
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

$operator_read_only = !$operator_event_is_active;


// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Operator hanya boleh melihat berita miliknya sendiri.
// Prioritas pakai created_by (kalau kolom tersedia), fallback ke kolom penulis.
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

// Query untuk mengambil data berita
$base_query = "SELECT * FROM berita WHERE 1=1" . $ownership_where_sql;
$count_query = "SELECT COUNT(*) as total FROM berita WHERE 1=1" . $ownership_where_sql;
$base_params = $ownership_params;
$count_params = $ownership_params;

// Handle search condition
if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (judul LIKE ? OR konten LIKE ? OR penulis LIKE ? OR tag LIKE ?)";
    $count_query .= " AND (judul LIKE ? OR konten LIKE ? OR penulis LIKE ? OR tag LIKE ?)";
    $base_params = array_merge($base_params, [$search_term, $search_term, $search_term, $search_term]);
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term]);
}

// Handle status filter
if (!empty($status_filter)) {
    $base_query .= " AND status = ?";
    $count_query .= " AND status = ?";
    $base_params[] = $status_filter;
    $count_params[] = $status_filter;
}

$base_query .= " ORDER BY created_at DESC";

// Get total data
$total_data = 0;
$total_pages = 1;
$berita = [];

try {
    // Count total records
    $stmt = $conn->prepare($count_query);
    $stmt->execute($count_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = (int)($result['total'] ?? 0);
    
    $total_pages = max(1, (int)ceil($total_data / $limit));
    
    // Get data with pagination
    $query = $base_query . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $paramIndex = 1;
    foreach ($base_params as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }
    $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $berita = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($berita as &$row) {
        $statusRaw = strtolower(trim((string)($row['status'] ?? '')));
        $viewsCount = (int)($row['views'] ?? 0);
        $isAllowedStatus = in_array($statusRaw, ['draft', 'archived'], true);
        $hasRelatedData = $viewsCount > 0;

        $row['can_delete'] = $isAllowedStatus && !$hasRelatedData;
        if (!$isAllowedStatus) {
            $row['delete_block_reason'] = 'Delete hanya untuk status draft/archived.';
        } elseif ($hasRelatedData) {
            $row['delete_block_reason'] = 'Tidak bisa dihapus karena sudah memiliki data turunan (views/interaksi).';
        } else {
            $row['delete_block_reason'] = 'Delete';
        }
    }
    unset($row);
    
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

// Fungsi untuk membuat excerpt dari konten
function createExcerpt($text, $maxLength = 100) {
    $text = strip_tags($text);
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
        $text = substr($text, 0, strrpos($text, ' ')) . '...';
    }
    return $text;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Berita Management</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../pelatih/css/style.css'); ?>">
<link rel="stylesheet" href="css/challenge.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/challenge.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    /* Specific overrides for Berita status */
    .status-published { background: #dcfce7 !important; color: #166534 !important; }
    .status-draft { background: #f1f5f9 !important; color: #475569 !important; }
    .status-archived { background: #fee2e2 !important; color: #991b1b !important; }
    
    .tag-item {
        display: inline-block;
        background: #f1f5f9;
        color: #475569;
        padding: 2px 8px;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 700;
        margin: 2px;
        border: 1px solid var(--heritage-border);
    }
    
    .news-image {
        width: 80px;
        height: 60px;
        border-radius: 12px;
        object-fit: cover;
        box-shadow: var(--heritage-shadow-sm);
        transition: transform 0.2s;
    }
    
    .news-image:hover {
        transform: scale(1.1);
    }
    
    .news-image-placeholder {
        width: 80px;
        height: 60px;
        border-radius: 12px;
        background: #f1f5f9;
        color: var(--heritage-text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        border: 1px solid var(--heritage-border);
    }

    .judul-cell-wrap {
        max-width: 250px;
    }

    .judul-text {
        display: block;
        font-weight: 700;
        color: var(--heritage-slate);
        margin-bottom: 4px;
        line-height: 1.4;
    }

    .slug-text {
        display: block;
        font-size: 0.7rem;
        color: var(--heritage-text-muted);
    }

    .excerpt-text {
        font-size: 0.8rem;
        color: var(--heritage-text-muted);
        line-height: 1.5;
        max-width: 300px;
    }
</style>
</head>
<body>
<div class="menu-overlay"></div>
<button class="mobile-menu-toggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar reveal">
            <div class="greeting">
                <h1>Berita Management 📰</h1>
                <p>Kelola konten berita dan pengumuman dengan mudah</p>
            </div>
            
            <div class="user-actions">
                <a href="../operator/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </a>
            </div>
        </div>

        <div class="challenge-container">
            <!-- Editorial Header -->
            <header class="dashboard-hero reveal d-1">
                <div class="hero-content">
                    <span class="hero-label">Manajemen Konten</span>
                    <h1 class="hero-title">Direktori Berita</h1>
                    <p class="hero-description">Publikasikan informasi terbaru, artikel, dan pengumuman penting untuk audiens Anda secara real-time.</p>
                </div>
                <div class="hero-actions">
                    <span class="summary-pill"><i class="fas fa-newspaper"></i> <?php echo (int)$total_data; ?> Total Berita</span>
                </div>
            </header>

            <!-- Filters -->
            <div class="filter-container reveal d-2">
                <div class="challenge-filter-card">
                    <form method="GET" class="challenge-filter-form" id="searchForm">
                        <div class="filter-group">
                            <label>Pencarian</label>
                            <div class="challenge-search-group">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" class="challenge-search-input" 
                                       placeholder="Cari berita (judul, konten, penulis)..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status" class="challenge-filter-select" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                                <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                        <div class="challenge-filter-actions">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                            <a href="berita.php" class="clear-filter-btn"><i class="fas fa-times"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="reveal d-3">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <h2 class="section-title">Daftar Berita</h2>
                        <div class="section-line"></div>
                    </div>
                    <div class="section-actions">
                        <?php if (!$operator_read_only): ?>
                            <a href="berita_create.php" class="btn-premium btn-add">
                                <i class="fas fa-plus"></i> Tambah Berita
                            </a>
                            <button type="button" class="btn-premium btn-export" onclick="exportBerita()">
                                <i class="fas fa-download"></i> Export Excel
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($operator_read_only): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-lock"></i>
                        <span>Event Anda sedang non-aktif. Mode operator hanya lihat data.</span>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
                </div>
                <?php endif; ?>

                <!-- BERITA TABLE -->
                <div class="table-responsive">
                    <table class="data-table" id="beritaTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Gambar</th>
                                <th>Judul & Konten</th>
                                <th>Penulis</th>
                                <th>Tag</th>
                                <th>Status</th>
                                <th>Views</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($berita) && count($berita) > 0): ?>
                                <?php $no = $offset + 1; ?>
                                <?php foreach($berita as $b): ?>
                                <tr>
                                    <td><strong><?php echo $no++; ?></strong></td>
                                    <td>
                                        <?php if (!empty($b['gambar'])): ?>
                                            <img src="../images/berita/<?php echo htmlspecialchars($b['gambar'] ?? ''); ?>" 
                                                 alt="Gambar berita" 
                                                 onerror="this.onerror=null; this.style.display='none'; this.insertAdjacentHTML('afterend','<div class=&quot;news-image-placeholder&quot;><i class=&quot;fas fa-newspaper&quot;></i></div>');"
                                                 class="news-image">
                                        <?php else: ?>
                                            <div class="news-image-placeholder">
                                                <i class="fas fa-newspaper"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="judul-cell-wrap">
                                            <span class="judul-text"><?php echo htmlspecialchars($b['judul'] ?? ''); ?></span>
                                            <div class="excerpt-text"><?php echo createExcerpt($b['konten'], 80); ?></div>
                                            <span class="slug-text">Slug: <?php echo htmlspecialchars($b['slug'] ?? ''); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--heritage-slate);">
                                            <?php echo !empty($b['penulis']) ? htmlspecialchars($b['penulis'] ?? '') : '-'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($b['tag'])): ?>
                                            <?php 
                                            $tags = explode(',', $b['tag']);
                                            foreach ($tags as $tag):
                                                $tag = trim($tag);
                                                if (!empty($tag)):
                                            ?>
                                            <span class="tag-item"><?php echo htmlspecialchars($tag ?? ''); ?></span>
                                            <?php endif; endforeach; ?>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = 'status-' . strtolower($b['status']);
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars($b['status'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="score-badge" style="background: #f1f5f9; color: var(--heritage-primary);">
                                            <?php echo $b['views']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.8rem; font-weight: 600;">
                                            <?php echo date('d M Y', strtotime($b['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons-inline">
                                            <?php
                                            $deleteDisabled = $operator_read_only || empty($b['can_delete']);
                                            $deleteTitle = $operator_read_only
                                                ? 'Event non-aktif. Mode hanya lihat data.'
                                                : (string)($b['delete_block_reason'] ?? 'Delete');
                                            ?>
                                            <a href="berita_view.php?id=<?php echo $b['id']; ?>" 
                                               class="action-btn btn-view" title="Lihat">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (!$operator_read_only): ?>
                                                <a href="berita_edit.php?id=<?php echo $b['id']; ?>" 
                                                   class="action-btn btn-edit" title="Ubah">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="action-btn btn-delete<?php echo $deleteDisabled ? ' btn-delete-disabled' : ''; ?>"
                                                        <?php if (!$deleteDisabled): ?>
                                                        data-berita-id="<?php echo (int) $b['id']; ?>"
                                                        data-berita-title="<?php echo htmlspecialchars($b['judul'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                        <?php else: ?>
                                                        disabled aria-disabled="true"
                                                        <?php endif; ?>
                                                        title="<?php echo htmlspecialchars($deleteTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 60px;">
                                        <div style="text-align: center; color: var(--heritage-text-muted);">
                                            <i class="fas fa-newspaper" style="font-size: 48px; opacity: 0.2; margin-bottom: 20px; display: block;"></i>
                                            <h3>Belum Ada Berita</h3>
                                            <p>Mulai dengan membuat berita pertama menggunakan tombol di atas.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Statistics Summary -->
                <div class="stats-summary reveal d-3">
                    <?php 
                    try {
                        // Count published
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM berita WHERE status = 'published'" . $ownership_where_sql);
                        $stmt->execute($ownership_params);
                        $published = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        // Count draft
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM berita WHERE status = 'draft'" . $ownership_where_sql);
                        $stmt->execute($ownership_params);
                        $draft = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        // Total views
                        $stmt = $conn->prepare("SELECT COALESCE(SUM(views), 0) as total_views FROM berita WHERE 1=1" . $ownership_where_sql);
                        $stmt->execute($ownership_params);
                        $total_views = $stmt->fetch(PDO::FETCH_ASSOC)['total_views'] ?? 0;
                    } catch (PDOException $e) {
                        $published = $draft = $total_views = 0;
                    }
                    ?>
                    <div class="stat-item">
                        <span class="stat-label">Total Berita</span>
                        <span class="stat-value"><?php echo (int)$total_data; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Published</span>
                        <span class="stat-value"><?php echo (int)$published; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Draft</span>
                        <span class="stat-value"><?php echo (int)$draft; ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Total Views</span>
                        <span class="stat-value"><?php echo (int)$total_views; ?></span>
                    </div>
                </div>

                <!-- PAGINATION -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination reveal">
                    <?php if ($page > 1): ?>
                        <a href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-link" title="Halaman Pertama">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-link" title="Sebelumnya">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                           <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-link" title="Berikutnya">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" class="page-link" title="Halaman Terakhir">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- DELETE HANDLER WITH SWEETALERT2 ---
    document.querySelectorAll('.btn-delete[data-berita-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const beritaId = this.getAttribute('data-berita-id');
            const beritaTitle = this.getAttribute('data-berita-title') || '-';
            
            confirmDelete(beritaTitle).then(confirmed => {
                if (confirmed) {
                    deleteBerita(beritaId);
                }
            });
        });
    });
});

function confirmDelete(beritaTitle) {
    return new Promise((resolve) => {
        Swal.fire({
            title: 'Hapus Berita?',
            html: `<div style="text-align: left;">
                <p>Apakah Anda yakin ingin menghapus berita <strong>"${beritaTitle}"</strong>?</p>
                <p style="color: #666; font-size: 14px; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle" style="color: #ff9800;"></i>
                    Tindakan ini tidak dapat dibatalkan. Data yang dihapus akan hilang permanen.
                </p>
            </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-trash"></i> Hapus',
            cancelButtonText: '<i class="fas fa-times"></i> Batal',
            confirmButtonColor: '#991b1b',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            customClass: {
                confirmButton: 'swal-delete-btn',
                cancelButton: 'swal-cancel-btn'
            }
        }).then((result) => {
            resolve(result.isConfirmed);
        });
    });
}

function deleteBerita(beritaId) {
    fetch(`berita_delete.php?id=${beritaId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success('Berita berhasil dihapus!');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: data.message || 'Gagal menghapus berita.'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('Terjadi kesalahan saat menghapus berita.');
    });
}

function exportBerita() {
    window.location.href = 'berita_export.php' + (window.location.search ? window.location.search + '&export=excel' : '?export=excel');
}

// Add SweetAlert2 styles
const style = document.createElement('style');
style.textContent = `
.swal-delete-btn {
    padding: 12px 24px !important;
    border-radius: 12px !important;
    font-weight: 700 !important;
    font-family: 'Bricolage Grotesque', sans-serif !important;
}
.swal-cancel-btn {
    padding: 12px 24px !important;
    border-radius: 12px !important;
    font-weight: 700 !important;
    font-family: 'Bricolage Grotesque', sans-serif !important;
}
.swal2-popup {
    border-radius: 24px !important;
}
`;
document.head.appendChild(style);
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
