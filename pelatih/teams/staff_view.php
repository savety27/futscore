<?php
$page_title = 'Staf Team';
$current_page = 'team'; // Keep 'team' as current page for sidebar
require_once '../config/database.php';
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="css/teams.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/teams.css'); ?>">
<?php
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$team_info = null;

if ($team_id) {
    // Basic team info
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$team_info) {
    echo "<div class='card'><div class='alert alert-danger'>Team tidak ditemukan.</div><a href='index.php' class='btn-premium btn-export'>Kembali ke Daftar Team</a></div>";
    require_once '../includes/footer.php';
    exit;
}

// Update page title
$page_title = htmlspecialchars($team_info['name'] ?? '') . ' - Staf';

// Search function
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Query for Team Staff
$base_query = "SELECT 
    ts.id,
    ts.team_id,
    ts.name,
    ts.position,
    ts.email,
    ts.phone,
    ts.photo,
    ts.birth_date,
    (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) as certificate_count
    FROM team_staff ts
    WHERE ts.team_id = ?";

$count_query = "SELECT COUNT(DISTINCT ts.id) as total FROM team_staff ts WHERE ts.team_id = ?";

$params = [$team_id];
$count_params = [$team_id];

if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ?)";
    $count_query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ?)";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
    array_push($count_params, $search_term, $search_term, $search_term, $search_term);
}

$base_query .= " GROUP BY ts.id ORDER BY ts.created_at DESC";

$total_data = 0;
$total_pages = 1;
$staff_list = [];

try {
    // Count Query
    $stmt = $conn->prepare($count_query);
    $stmt->execute($count_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = $result['total'];
    
    $total_pages = ceil($total_data / $limit);
    
    // Validate Page
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
    $offset = ($page - 1) * $limit;
    
    // Main Query
    $query = $base_query . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    
    // Bind parameters dynamically
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    
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
?>

<!-- Modal untuk menampilkan sertifikat -->
<div id="certificatesModal" class="modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); backdrop-filter: blur(5px);">
    <div class="modal-content" style="background-color: var(--heritage-card); margin: 5% auto; padding: 30px; border-radius: 24px; width: 90%; max-width: 600px; position: relative; border: 1px solid var(--heritage-border); box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <span class="close-modal" style="position: absolute; right: 24px; top: 20px; font-size: 28px; cursor: pointer; color: var(--heritage-crimson); transition: transform 0.2s;">&times;</span>
        <h3 id="modalTitle" style="color: var(--heritage-text); font-family: var(--font-display); font-weight: 800; font-size: 1.5rem; margin-bottom: 24px;">Sertifikat</h3>
        <div id="certificatesList" style="max-height: 60vh; overflow-y: auto; padding-right: 10px;"></div>
    </div>
</div>

<div class="teams-container">
    <header class="dashboard-hero reveal">
        <div class="hero-content">
            <span class="hero-label">Staf Team</span>
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 16px;">
                <?php if (!empty($team_info['logo'])): ?>
                    <img src="../../images/teams/<?php echo $team_info['logo']; ?>" alt="Logo" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid var(--heritage-border);" onerror="this.onerror=null; this.src='../../images/teams/default-team.png'">
                <?php endif; ?>
                <h1 class="hero-title" style="margin: 0;"><?php echo htmlspecialchars($team_info['name'] ?? ''); ?></h1>
            </div>
            <p class="hero-description">Kelola dan pantau profil staf tim ini secara komprehensif.</p>
        </div>
        <div class="hero-actions" style="display: flex; flex-direction: column; gap: 12px; align-items: flex-end;">
            <a href="index.php" class="btn-premium btn-export">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Team
            </a>
        </div>
    </header>

    <div class="reveal d-2">
        <div class="filter-container">
            <div class="teams-filter-card">
                <form action="" method="GET" class="teams-inline-filter">
                    <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
                    <div class="filter-input-wrapper">
                        <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--heritage-text); opacity: 0.5;"></i>
                        <input type="text" name="search" class="teams-search-input" placeholder="Cari staf berdasarkan nama, jabatan..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn-premium">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(!empty($search)): ?>
                        <a href="?team_id=<?php echo $team_id; ?>" class="btn-premium btn-export">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    <?php if (empty($staff_list)): ?>
        <div class="empty-state">
            <i class="fas fa-user-tie"></i>
            <p>Tidak ada staf ditemukan di team ini.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="photo-cell">Foto</th>
                        <th>Nama</th>
                        <!-- Removed Team Column as we are in a single team view -->
                        <th style="text-align: center;">Jabatan</th>
                        <th style="text-align: center;">Umur</th>
                        <th style="text-align: center;">Sertifikat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_list as $staff): ?>
                    <tr>
                         <td>
                            <img src="../../uploads/staff/<?php echo basename($staff['photo']); ?>" 
                            alt="<?php echo htmlspecialchars($staff['name'] ?? ''); ?>" class="staff-photo" onerror="this.onerror=null; this.src='../../images/staff/default-staff.png'">
                        </td>
                        <td class="name-cell">
                            <strong><?php echo htmlspecialchars($staff['name'] ?? ''); ?></strong>
                            <div class="player-info" style="margin-top: 4px;">
                                <small style="display: flex; align-items: center; gap: 4px;">
                                    <i class="fas fa-envelope" style="font-size: 10px; opacity: 0.7;"></i> 
                                    <?php echo htmlspecialchars($staff['email'] ?? '-'); ?>
                                </small>
                            </div>
                        </td>
                        <td class="position-cell">
                            <span class="position-badge"><?php echo htmlspecialchars($staff['position'] ?? ''); ?></span>
                        </td>
                        <td class="age-cell"><?php echo $staff['age']; ?></td>
                        <td class="certificate-cell">
                             <?php if ($staff['certificate_count'] > 0): ?>
                                <a href="javascript:void(0);" 
                                class="view-certificates" 
                                data-staff-id="<?php echo $staff['id']; ?>"
                                data-staff-name="<?php echo htmlspecialchars($staff['name'] ?? ''); ?>"
                                style="text-decoration: none;">
                                    <span class="certificate-count clickable" style="cursor: pointer;">
                                        <?php echo $staff['certificate_count']; ?> Sertifikat ▶
                                    </span>
                                </a>
                            <?php else: ?>
                                <span class="certificate-count" style="background: #eee; color: #999;">0 Sertifikat</span>
                            <?php endif; ?>
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
                <a href="?team_id=<?php echo $team_id; ?>&page=1&search=<?php echo urlencode($search); ?>" class="page-link" title="Halaman Pertama"><i class="fas fa-angle-double-left"></i></a>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Sebelumnya"><i class="fas fa-angle-left"></i></a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                echo '<span class="page-dots">...</span>';
            }
            
            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; 
            
            if ($end_page < $total_pages) {
                echo '<span class="page-dots">...</span>';
            }
            ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Berikutnya"><i class="fas fa-angle-right"></i></a>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Halaman Terakhir"><i class="fas fa-angle-double-right"></i></a>
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
        document.body.style.overflow = '';
    }
    
    closeModal.addEventListener('click', closeCertificateModal);
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeCertificateModal();
        }
    });
    
    viewCertificatesLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            const staffId = this.getAttribute('data-staff-id');
            const staffName = this.getAttribute('data-staff-name');
            
            modalTitle.textContent = 'Sertifikat - ' + staffName;
            certificatesList.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--heritage-gold);"></i>
                    <p style="margin-top: 16px; color: var(--heritage-text-muted); font-family: var(--font-body);">Memuat sertifikat...</p>
                </div>
            `;
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            loadCertificates(staffId);
        });
    });
    
    function loadCertificates(staffId) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', '../staff/get_certificates.php?staff_id=' + staffId, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                certificatesList.innerHTML = xhr.responseText;
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

<style>
/* Staff Specific Table Cells Overrides */
.staff-photo { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.08); background: var(--heritage-bg); }
.position-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 700; background: rgba(30, 64, 175, 0.1); color: #1e40af; border: 1px solid rgba(30, 64, 175, 0.2); text-transform: uppercase; letter-spacing: 0.5px; }
.age-cell { text-align: center; font-weight: 600; color: var(--heritage-text); font-size: 14px; }
.certificate-cell { text-align: center; }
.certificate-count { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.2); transition: all 0.3s ease; }
.certificate-count.clickable:hover { transform: translateY(-2px); background: var(--heritage-gold); color: #fff; border-color: var(--heritage-gold); box-shadow: 0 4px 12px rgba(180, 83, 9, 0.2); }
.certificate-count[style*="background: #eee"] { background: var(--heritage-bg) !important; color: #64748b !important; border-color: var(--heritage-border) !important; font-weight: 500; }

/* Certificate Modal Layout (Match Staff Index) */
#certificatesList { max-height: 60vh; overflow-y: auto; padding-right: 10px; }
.certificates-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
.certificate-card { background: #fff; border: 1px solid var(--heritage-border); border-radius: 16px; padding: 20px; }
.certificate-header { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; }
.cert-icon-wrapper { width: 40px; height: 40px; border-radius: 12px; background: rgba(180, 83, 9, 0.12); color: var(--heritage-gold); display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; flex: 0 0 auto; }
.cert-title-group { min-width: 0; }
.cert-name { margin: 0 0 4px 0; font-family: var(--font-display); font-weight: 800; font-size: 1rem; color: var(--heritage-text); word-break: break-word; }
.cert-date { font-size: 0.85rem; color: var(--heritage-text-muted); }
.certificate-details { background: var(--heritage-bg); border: 1px solid var(--heritage-border); border-radius: 12px; padding: 10px 12px; margin-bottom: 12px; }
.certificate-details .detail-row { display: flex; flex-direction: column; gap: 4px; font-size: 0.9rem; }
.certificate-details .detail-label { color: var(--heritage-text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; font-size: 0.72rem; }
.certificate-details .detail-value { color: var(--heritage-text); font-weight: 600; }
.certificate-preview { width: 100%; border-radius: 16px; overflow: hidden; border: 1px solid var(--heritage-border); position: relative; background: #fff; }
.certificate-image { display: block; width: 100%; height: auto; max-width: 100%; }
.preview-overlay { position: absolute; inset: auto 0 0 0; padding: 10px; background: linear-gradient(180deg, rgba(0,0,0,0), rgba(0,0,0,0.55)); display: flex; justify-content: flex-end; }
.btn-view-full { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; background: rgba(255,255,255,0.92); color: var(--heritage-text); border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.8rem; }
.btn-view-full:hover { background: var(--heritage-gold); color: #fff; }

</style>

<?php require_once '../includes/footer.php'; ?>
