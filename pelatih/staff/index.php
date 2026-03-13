<?php
$page_title = 'Daftar Staf Team';
$current_page = 'team_staff';
require_once '../config/database.php';
require_once '../includes/header.php';

// Handle filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_active = trim((string)($_GET['active'] ?? ''));
if (!in_array($filter_active, ['', '1', '0'], true)) {
    $filter_active = '';
}
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get team_id from session
$team_id = $_SESSION['team_id'] ?? 0;

// Base Query (Filtered by team_id)
$base_query = "
    SELECT ts.id, ts.name, ts.photo, ts.position, ts.email, ts.phone, ts.birth_date, ts.is_active,
           t.name as team_name, t.alias as team_alias,
           (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) as certificate_count
    FROM team_staff ts
    LEFT JOIN teams t ON ts.team_id = t.id
    WHERE ts.team_id = ?
";

$count_query = "SELECT COUNT(DISTINCT ts.id) as total FROM team_staff ts 
                LEFT JOIN teams t ON ts.team_id = t.id
                WHERE ts.team_id = ?";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ? OR t.name LIKE ?)";
    $count_query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ? OR t.name LIKE ?)";
}

if ($filter_active !== '') {
    $base_query .= " AND ts.is_active = ?";
    $count_query .= " AND ts.is_active = ?";
}

$base_query .= " GROUP BY ts.id ORDER BY ts.created_at DESC";

$total_data = 0;
$total_pages = 1;
$staff_list = [];

try {
    if (!empty($search)) {
        $stmt = $conn->prepare($count_query);
        $count_params = [$team_id, $search_term, $search_term, $search_term, $search_term, $search_term];
        if ($filter_active !== '') {
            $count_params[] = (int)$filter_active;
        }
        $stmt->execute($count_params);
    } else {
        $stmt = $conn->prepare($count_query);
        $count_params = [$team_id];
        if ($filter_active !== '') {
            $count_params[] = (int)$filter_active;
        }
        $stmt->execute($count_params);
    }
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = $result['total'];
    
    $total_pages = max(1, (int)ceil($total_data / $limit));
    
    $query = $base_query . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    
    if (!empty($search)) {
        $stmt->bindValue(1, $team_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $search_term);
        $stmt->bindValue(5, $search_term);
        $stmt->bindValue(6, $search_term);
        $next_index = 7;
        if ($filter_active !== '') {
            $stmt->bindValue($next_index++, (int)$filter_active, PDO::PARAM_INT);
        }
        $stmt->bindValue($next_index++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($next_index, $offset, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $team_id, PDO::PARAM_INT);
        $next_index = 2;
        if ($filter_active !== '') {
            $stmt->bindValue($next_index++, (int)$filter_active, PDO::PARAM_INT);
        }
        $stmt->bindValue($next_index++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($next_index, $offset, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate Age
    foreach ($staff_list as &$staff) {
        if (!empty($staff['birth_date'])) {
            $birthDate = new DateTime($staff['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            $staff['age'] = $age;
        } else {
            $staff['age'] = '-';
        }
    }
    unset($staff);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$filter_query_params = [];
if ($search !== '') {
    $filter_query_params['search'] = $search;
}
if ($filter_active !== '') {
    $filter_query_params['active'] = $filter_active;
}

$team_staff_export_url = 'export.php' . (!empty($filter_query_params) ? '?' . http_build_query($filter_query_params) : '');
$build_page_url = function(int $page) use ($filter_query_params): string {
    $params = $filter_query_params;
    $params['page'] = $page;
    return '?' . http_build_query($params);
};
?>

<link rel="stylesheet" href="css/staff.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/staff.css'); ?>">

<div class="teams-container">
    <!-- Editorial Header -->
    <header class="dashboard-hero reveal">
        <div class="hero-content">
            <span class="hero-label">Direktori</span>
            <h1 class="hero-title">Direktori Staf Team</h1>
            <p class="hero-description">Kelola data staf, cek status aktif, dan lihat sertifikat tiap anggota tim.</p>
        </div>
        <div class="hero-actions">
            <span class="summary-pill"><i class="fas fa-users-cog"></i> <?php echo (int)$total_data; ?> Staf</span>
        </div>
    </header>

    <!-- Modal untuk menampilkan sertifikat -->
    <div id="certificatesModal" class="modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px);">
        <div class="modal-content" style="background-color: var(--heritage-card); margin: 5% auto; padding: 30px; border-radius: 24px; width: 90%; max-width: 600px; position: relative; border: 1px solid var(--heritage-border); box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
            <span class="close-modal" style="position: absolute; right: 24px; top: 20px; font-size: 28px; cursor: pointer; color: var(--heritage-crimson); transition: transform 0.2s;">&times;</span>
            <h3 id="modalTitle" style="color: var(--heritage-text); font-family: var(--font-display); font-weight: 800; font-size: 1.5rem; margin-bottom: 24px;">Sertifikat</h3>
            <div id="certificatesList" style="max-height: 60vh; overflow-y: auto; padding-right: 10px;">
                <!-- Daftar sertifikat akan dimuat di sini -->
            </div>
        </div>
    </div>

    <!-- Filters container -->
    <div class="filter-container reveal d-1">
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="message-alert">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="message-alert" style="border-left-color: var(--heritage-crimson);">
            <i class="fas fa-exclamation-circle" style="color: var(--heritage-crimson);"></i>
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
        <?php endif; ?>

        <div class="teams-filter-card">
            <form action="" method="GET" class="teams-filter-form">
                <div class="filter-group">
                    <label>Pencarian</label>
                    <div class="teams-search-group">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="teams-search-input" placeholder="Cari nama, email, telepon, jabatan..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="filter-group">
                    <label>Status Aktif</label>
                    <div class="schedule-select-wrap">
                        <div class="schedule-custom-select" id="staffStatusSelect">
                            <input type="hidden" name="active" id="staffStatusValue" value="<?php echo htmlspecialchars($filter_active); ?>">
                            <button type="button" class="schedule-custom-select-trigger" id="staffStatusTrigger" aria-expanded="false">
                                <span class="schedule-custom-select-label">
                                    <i class="fas fa-filter"></i>
                                    <span id="staffStatusLabel" class="schedule-custom-select-text">
                                        <?php
                                        $status_labels = [
                                            '' => 'Semua Status',
                                            '1' => 'Aktif',
                                            '0' => 'Non-Aktif'
                                        ];
                                        echo htmlspecialchars($status_labels[$filter_active] ?? 'Semua Status');
                                        ?>
                                    </span>
                                </span>
                                <i class="fas fa-chevron-down select-icon-right"></i>
                            </button>
                            <div class="schedule-custom-select-menu" id="staffStatusMenu">
                                <button type="button" class="schedule-custom-option <?php echo $filter_active === '' ? 'active' : ''; ?>" data-value="">Semua Status</button>
                                <button type="button" class="schedule-custom-option <?php echo $filter_active === '1' ? 'active' : ''; ?>" data-value="1">Aktif</button>
                                <button type="button" class="schedule-custom-option <?php echo $filter_active === '0' ? 'active' : ''; ?>" data-value="0">Non-Aktif</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="teams-filter-actions" style="margin-top: auto;">
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Terapkan</button>
                    <a href="index.php" class="clear-filter-btn"><i class="fas fa-times"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="reveal d-2">
        <div class="section-header">
            <div class="section-title-wrap">
                <h2 class="section-title">Daftar Staf</h2>
                <div class="section-line"></div>
            </div>
            <div class="section-actions" style="display: flex; gap: 12px;">
                <a href="form.php" class="btn-premium" style="background: var(--heritage-accent); color: white; border: none;">
                    <i class="fas fa-plus"></i> Tambah Staf Baru
                </a>
                <a href="<?php echo htmlspecialchars($team_staff_export_url); ?>" class="btn-premium btn-export">
                    <i class="fas fa-download"></i> Export Excel
                </a>
            </div>
        </div>

    <?php if (empty($staff_list)): ?>
        <div class="empty-state">
            <i class="fas fa-user-tie"></i>
            <p>Staf tidak ditemukan.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="logo-cell">Foto</th>
                        <th>Nama Staf</th>
                        <th>Jabatan</th>
                        <th style="text-align: center;">Umur</th>
                        <th style="text-align: center;">Sertifikat</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_list as $staff): 
                        $staff_photo_url = '';
                        if (!empty($staff['photo'])) {
                            $photo_file = basename($staff['photo']);
                            $possible_paths = [
                                'uploads/staff/' . $photo_file,
                                '../uploads/staff/' . $photo_file,
                                '../../uploads/staff/' . $photo_file,
                                '../../../uploads/staff/' . $photo_file,
                                'images/staff/' . $photo_file,
                                '../images/staff/' . $photo_file,
                                '../../images/staff/' . $photo_file,
                                '../../../images/staff/' . $photo_file,
                                $staff['photo'],
                                '../' . ltrim($staff['photo'], '/'),
                            ];

                            foreach ($possible_paths as $path) {
                                if (file_exists($path)) {
                                    $staff_photo_url = $path;
                                    break;
                                }
                            }
                        }
                    ?>
                    <tr>
                         <td class="logo-cell">
                            <div class="player-photo">
                            <?php if (!empty($staff_photo_url)): ?>
                                <img src="<?php echo htmlspecialchars($staff_photo_url); ?>" 
                                     alt="<?php echo htmlspecialchars($staff['name'] ?? ''); ?>" 
                                     onerror="this.onerror=null; this.src='../images/staff/default-staff.png'">
                            <?php else: ?>
                                <div class="default-photo">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                            <?php endif; ?>
                            </div>
                        </td>
                        <td class="name-cell">
                            <a href="view.php?id=<?php echo $staff['id']; ?>" class="player-name-link" title="Lihat detail">
                                <strong><?php echo htmlspecialchars($staff['name'] ?? ''); ?></strong>
                                <span class="player-click-hint">Lihat Profil &rarr;</span>
                            </a>
                            <div class="player-info">
                                <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['email'] ?? '-'); ?></small>
                                <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($staff['phone'] ?? '-'); ?></small>
                            </div>
                        </td>
                        <td>
                            <span class="position-badge" data-position="<?php echo htmlspecialchars(substr($staff['position'] ?? '', 0, 1)); ?>">
                                <?php echo htmlspecialchars($staff['position'] ?? ''); ?>
                            </span>
                        </td>
                        <td class="age-cell"><?php echo $staff['age']; ?> Thn</td>
                        <td style="text-align: center;">
                            <a href="javascript:void(0);" class="view-certificates count-link" data-staff-id="<?php echo $staff['id']; ?>" data-staff-name="<?php echo htmlspecialchars($staff['name'] ?? ''); ?>" title="Lihat Sertifikat">
                                <span class="count-cell matches" style="display:inline-flex; align-items:center; gap:6px;">
                                    <?php echo $staff['certificate_count']; ?> <i class="fas fa-certificate" style="font-size: 11px; opacity:0.8;"></i>
                                </span>
                            </a>
                        </td>
                        <td>
                            <?php if ($staff['is_active']): ?>
                                <span class="status-badge active"><i class="fas fa-check-circle"></i> Aktif</span>
                            <?php else: ?>
                                <span class="status-badge inactive"><i class="fas fa-times-circle"></i> Non-Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <div class="action-buttons">
                                <a href="view.php?id=<?php echo $staff['id']; ?>" class="btn-primary btn-sm btn-view" title="Detail">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="form.php?id=<?php echo $staff['id']; ?>" class="btn-primary btn-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?php echo htmlspecialchars($build_page_url(1)); ?>" class="page-link" title="Halaman Pertama">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="<?php echo htmlspecialchars($build_page_url($page - 1)); ?>" class="page-link" title="Sebelumnya">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="<?php echo htmlspecialchars($build_page_url($i)); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="<?php echo htmlspecialchars($build_page_url($page + 1)); ?>" class="page-link" title="Berikutnya">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="<?php echo htmlspecialchars($build_page_url($total_pages)); ?>" class="page-link" title="Halaman Terakhir">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('certificatesModal');
    const modalTitle = document.getElementById('modalTitle');
    const certificatesList = document.getElementById('certificatesList');
    const closeModal = document.querySelector('.close-modal');
    const viewCertificatesLinks = document.querySelectorAll('.view-certificates');
    
    function closeCertificateModal() {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // allow scroll
    }
    
    closeModal.addEventListener('click', closeCertificateModal);
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeCertificateModal();
        }
    });
    
    viewCertificatesLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const staffId = this.getAttribute('data-staff-id');
            const staffName = this.getAttribute('data-staff-name');
            
            modalTitle.textContent = 'Sertifikat - ' + staffName;
            
            certificatesList.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--heritage-gold);"></i><p style="margin-top: 16px; color: var(--heritage-text-muted); font-family: var(--font-body);">Memuat sertifikat...</p></div>';
            
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // prevent scroll
            
            loadCertificates(staffId);
        });
    });
    
    function loadCertificates(staffId) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_certificates.php?staff_id=' + staffId, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                // Wrap response in heritage styled classes if needed, or get_certificates.php already does
                certificatesList.innerHTML = xhr.responseText;
                
                // Add minor specific styling to children dynamically if needed
                const items = certificatesList.querySelectorAll('.certificate-item');
                items.forEach(item => {
                    item.style.backgroundColor = 'var(--heritage-bg)';
                    item.style.border = '1px solid var(--heritage-border)';
                    item.style.borderRadius = '16px';
                    item.style.padding = '20px';
                    item.style.marginBottom = '16px';
                });
            } else {
                certificatesList.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--heritage-crimson);"><i class="fas fa-exclamation-circle" style="font-size: 32px;"></i><p style="margin-top: 16px;">Gagal memuat sertifikat. Status: ' + xhr.status + '</p></div>';
            }
        };
        
        xhr.onerror = function() {
            certificatesList.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--heritage-crimson);"><i class="fas fa-wifi" style="font-size: 32px;"></i><p style="margin-top: 16px;">Kesalahan jaringan saat memuat data.</p></div>';
        };
        
        xhr.send();
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectRoot = document.getElementById('staffStatusSelect');
    if (!selectRoot) return;

    const trigger = document.getElementById('staffStatusTrigger');
    const menu = document.getElementById('staffStatusMenu');
    const hiddenInput = document.getElementById('staffStatusValue');
    const label = document.getElementById('staffStatusLabel');
    const options = menu.querySelectorAll('.schedule-custom-option');

    function closeMenu() {
        selectRoot.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    }

    function openMenu() {
        selectRoot.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
    }

    trigger.addEventListener('click', function() {
        if (selectRoot.classList.contains('open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    options.forEach(function(opt) {
        opt.addEventListener('click', function() {
            const value = opt.getAttribute('data-value') || '';
            hiddenInput.value = value;
            label.textContent = opt.textContent.trim();
            options.forEach(function(o) { o.classList.remove('active'); });
            opt.classList.add('active');
            closeMenu();
        });
    });

    document.addEventListener('click', function(e) {
        if (!selectRoot.contains(e.target)) {
            closeMenu();
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
