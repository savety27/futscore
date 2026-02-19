<?php
$page_title = 'Staf Team';
$current_page = 'team'; // Keep 'team' as current page for sidebar
require_once 'config/database.php';
require_once 'includes/header.php';

$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$team_info = null;

if ($team_id) {
    // Basic team info
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$team_info) {
    echo "<div class='card'><div class='alert alert-danger'>Team tidak ditemukan.</div><a href='team.php' class='btn-secondary'>Kembali ke Daftar Team</a></div>";
    require_once 'includes/footer.php';
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

<!-- Modal untuk menampilkan sertifikat (Reused) -->
<div id="certificatesModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);">
    <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 20px; border-radius: 15px; width: 80%; max-width: 600px; position: relative;">
        <span class="close-modal" style="position: absolute; right: 20px; top: 10px; font-size: 28px; cursor: pointer; color: var(--danger);">&times;</span>
        <h3 id="modalTitle" style="color: var(--primary); margin-bottom: 20px;">Sertifikat</h3>
        <div id="certificatesList" style="max-height: 400px; overflow-y: auto;"></div>
    </div>
</div>

<div class="card">
    <div class="section-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <?php if (!empty($team_info['logo'])): ?>
                <img src="../images/teams/<?php echo $team_info['logo']; ?>" alt="Logo" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
            <?php endif; ?>
            <div>
                <h2 class="section-title"><?php echo htmlspecialchars($team_info['name'] ?? ''); ?> <span style="font-weight: normal; font-size: 0.8em; color: var(--gray);">Staf</span></h2>
            </div>
        </div>
        <a href="team.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Team
        </a>
    </div>

    <!-- Search Bar -->
    <div class="search-bar" style="margin-bottom: 20px;">
        <form action="" method="GET">
            <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
            <input type="text" name="search" placeholder="Cari staf..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
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
                            <img src="../uploads/staff/<?php echo basename($staff['photo']); ?>" 
                            alt="<?php echo htmlspecialchars($staff['name'] ?? ''); ?>" class="staff-photo" onerror="this.onerror=null; this.src='../images/staff/default-staff.png'">
                        </td>
                        <td class="name-cell">
                            <?php echo htmlspecialchars($staff['name'] ?? ''); ?>
                            <div style="font-size: 11px; color: var(--gray); font-weight: normal;"><?php echo htmlspecialchars($staff['email'] ?? ''); ?></div>
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
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">&laquo; Seb</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">Sel &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
// JavaScript for Certificates Modal
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('certificatesModal');
    const modalTitle = document.getElementById('modalTitle');
    const certificatesList = document.getElementById('certificatesList');
    const closeModal = document.querySelector('.close-modal');
    const viewCertificatesLinks = document.querySelectorAll('.view-certificates');
    
    function closeCertificateModal() {
        modal.style.display = 'none';
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
            certificatesList.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px; color: var(--primary);"></i><p>Memuat sertifikat...</p></div>';
            modal.style.display = 'block';
            
            loadCertificates(staffId);
        });
    });
    
    function loadCertificates(staffId) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_certificates.php?staff_id=' + staffId, true); // Ensure get_certificates.php handles this correctly without session checks if intended for public? Actually this is pelatih folder so session is required, which we have via header.php -> functions.php
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                certificatesList.innerHTML = xhr.responseText;
            } else {
                certificatesList.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--danger);"><i class="fas fa-exclamation-circle"></i><p>Gagal memuat sertifikat</p></div>';
            }
        };
        
        xhr.onerror = function() {
            certificatesList.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--danger);"><i class="fas fa-exclamation-circle"></i><p>Kesalahan jaringan</p></div>';
        };
        
        xhr.send();
    }
});
</script>

<style>
.main {
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
}

/* Reused Styles */
.empty-state { text-align: center; padding: 50px 20px; color: var(--gray); }
.empty-state i { font-size: 48px; margin-bottom: 20px; color: #ddd; }
.btn-secondary { background: #e0e0e0; color: #333; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; transition: all 0.2s; }
.btn-secondary:hover { background: #d5d5d5; color: #000; }
.data-table { width: 100%; border-collapse: separate; border-spacing: 0; background: white; border-radius: 12px; overflow: hidden; }
.data-table thead { background: linear-gradient(135deg, var(--primary), #1a365d); }
.data-table th { padding: 15px 12px; text-align: left; font-weight: 600; color: white; border: none; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
.data-table td { padding: 15px 12px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; }

.staff-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.position-badge { display: inline-block; padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; background: #e3f2fd; color: #1565c0; }
.age-cell { text-align: center; font-weight: 600; color: #555; }
.certificate-cell { text-align: center; }
.certificate-count { display: inline-block; padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 600; background: #e8f5e9; color: #2e7d32; transition: all 0.2s; }
.certificate-count.clickable:hover { transform: scale(1.05); background: #c8e6c9; }

/* Modal Styles */
.modal-content { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
.certificate-item { margin-bottom: 15px; padding: 15px; border: 1px solid #e0e0e0; border-radius: 10px; background: #f8f9fa; }
.certificate-image { max-width: 100%; height: auto; border-radius: 8px; margin-top: 10px; border: 2px solid #ddd; }
</style>

<?php require_once 'includes/footer.php'; ?>
