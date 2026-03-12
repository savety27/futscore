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

<div class="page-header">
    <div class="page-title-wrap">
        <h1 class="page-title"><i class="fas fa-user-tie"></i> Direktori Staf Team</h1>
        <p class="page-subtitle">Kelola data staf, cek status aktif, dan lihat sertifikat tiap anggota tim.</p>
    </div>
    <div class="page-summary">
        <span class="summary-pill"><i class="fas fa-users-cog"></i> <?php echo (int)$total_data; ?> Staf</span>
    </div>
</div>

<!-- Modal untuk menampilkan sertifikat -->
<div id="certificatesModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);">
    <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 20px; border-radius: 15px; width: 80%; max-width: 600px; position: relative;">
        <span class="close-modal" style="position: absolute; right: 20px; top: 10px; font-size: 28px; cursor: pointer; color: var(--danger);">&times;</span>
        <h3 id="modalTitle" style="color: var(--primary); margin-bottom: 20px;">Sertifikat</h3>
        <div id="certificatesList" style="max-height: 400px; overflow-y: auto;">
            <!-- Daftar sertifikat akan dimuat di sini -->
        </div>
    </div>
</div>

<div class="card">
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="success-message">
        <i class="fas fa-check-circle"></i>
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>
    
    <div class="section-header">
        <h2 class="section-title">Daftar Staf Team</h2>
        <div class="section-actions">
            <a href="form.php" class="btn-primary">
                <i class="fas fa-plus"></i> Tambah Staf Baru
            </a>
            <a href="<?php echo htmlspecialchars($team_staff_export_url); ?>" class="btn-export">
                <i class="fas fa-download"></i> Export Excel
            </a>
        </div>
    </div>

    <div class="filter-container">
        <div class="staff-filter-card">
            <form action="" method="GET" class="staff-filter-form">
                <div class="staff-search-group">
                    <i class="fas fa-search"></i>
                    <input
                        type="text"
                        name="search"
                        class="staff-search-input"
                        placeholder="Cari nama, email, telepon, jabatan, atau team..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>

                <select name="active" class="staff-filter-select">
                    <option value="">Semua Status</option>
                    <option value="1" <?php echo $filter_active === '1' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="0" <?php echo $filter_active === '0' ? 'selected' : ''; ?>>Non-Aktif</option>
                </select>

                <div class="staff-filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Terapkan
                    </button>
                    <?php if ($search !== '' || $filter_active !== ''): ?>
                        <a href="index.php" class="clear-filter-btn">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($staff_list)): ?>
        <p style="text-align: center; color: var(--gray); padding: 20px;">Staf tidak ditemukan.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                                <thead>
                    <tr>
                        <th class="photo-cell">Foto</th>
                        <th>Nama</th>
                        <th>Team</th>
                        <th style="text-align: center;">Jabatan</th>
                        <th style="text-align: center;">Umur</th>
                        <th style="text-align: center;">Sertifikat</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Aksi</th>
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
                         <td>
                            <?php if (!empty($staff_photo_url)): ?>
                                <img src="<?php echo htmlspecialchars($staff_photo_url); ?>" 
                                     alt="<?php echo htmlspecialchars($staff['name'] ?? ''); ?>" 
                                     class="staff-photo"
                                     onerror="this.onerror=null; this.src='../images/staff/default-staff.png'">
                            <?php else: ?>
                                <div class="staff-photo" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user-tie" style="color: #999; font-size: 24px;"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="name-cell">
                            <?php echo htmlspecialchars($staff['name'] ?? ''); ?>
                            <div style="font-size: 11px; color: var(--gray); font-weight: normal;"><?php echo htmlspecialchars($staff['email'] ?? ''); ?></div>
                        </td>
                        <td class="team-cell"><?php echo htmlspecialchars($staff['team_name'] ?? ''); ?></td>
                        <td class="position-cell">
                            <span class="position-badge"><?php echo htmlspecialchars($staff['position'] ?? ''); ?></span>
                        </td>
                        <td class="age-cell"><?php echo $staff['age']; ?></td>
                        <td class="certificate-cell">
                            <a href="javascript:void(0);" 
                            class="view-certificates" 
                            data-staff-id="<?php echo $staff['id']; ?>"
                            data-staff-name="<?php echo htmlspecialchars($staff['name'] ?? ''); ?>"
                            style="text-decoration: none;">
                                <span class="certificate-count clickable" style="cursor: pointer; position: relative; padding-right: 20px;">
                        <?php echo $staff['certificate_count']; ?> Sertifikat
                                <span style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%);">▶</span>
                            </span>
                            </a>
                        </td>
                        <td class="status-cell" style="text-align: center;">
                            <?php if ($staff['is_active']): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Non-Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="action-cell" style="text-align: center;">
                            <a href="view.php?id=<?php echo $staff['id']; ?>" 
                               class="btn-action btn-view" 
                               title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </a>
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
                <a href="<?php echo htmlspecialchars($build_page_url($page - 1)); ?>" class="page-link">&laquo; Seb</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo htmlspecialchars($build_page_url($i)); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="<?php echo htmlspecialchars($build_page_url($page + 1)); ?>" class="page-link">Sel &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
// JavaScript untuk menangani modal sertifikat
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('certificatesModal');
    const modalTitle = document.getElementById('modalTitle');
    const certificatesList = document.getElementById('certificatesList');
    const closeModal = document.querySelector('.close-modal');
    const viewCertificatesLinks = document.querySelectorAll('.view-certificates');
    
    // Fungsi untuk menutup modal
    function closeCertificateModal() {
        modal.style.display = 'none';
    }
    
    // Event listener untuk tombol close
    closeModal.addEventListener('click', closeCertificateModal);
    
    // Event listener untuk klik di luar modal
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeCertificateModal();
        }
    });
    
    // Event listener untuk setiap link sertifikat
    viewCertificatesLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            const staffId = this.getAttribute('data-staff-id');
            const staffName = this.getAttribute('data-staff-name');
            
            // Set judul modal
            modalTitle.textContent = 'Sertifikat - ' + staffName;
            
            // Tampilkan loading
            certificatesList.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px; color: var(--primary);"></i><p>Memuat sertifikat...</p></div>';
            
            // Tampilkan modal
            modal.style.display = 'block';
            
            // Load sertifikat via AJAX
            loadCertificates(staffId);
        });
    });
    
    // Fungsi untuk memuat sertifikat via AJAX
    function loadCertificates(staffId) {
        // Buat objek XMLHttpRequest
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_certificates.php?staff_id=' + staffId, true);
        
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

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 20px;
    padding: 22px 24px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    gap: 12px;
    flex-wrap: wrap;
}

.page-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.page-title {
    margin: 0;
    font-size: 28px;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 12px;
    line-height: 1.15;
}

.page-title i {
    color: var(--secondary);
}

.page-subtitle {
    margin: 0;
    color: var(--gray);
    font-size: 14px;
}

.summary-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    background: #eef5ff;
    color: var(--primary);
    border: 1px solid #dbeafe;
    font-size: 13px;
    font-weight: 700;
}

.section-header {
    margin-bottom: 16px;
}

.filter-container {
    margin-bottom: 24px;
}

.staff-filter-card {
    padding: 16px;
    border: 1px solid #dbe5f3;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    box-shadow: 0 8px 20px rgba(10, 36, 99, 0.06);
}

.staff-filter-form {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) minmax(180px, 0.6fr) auto;
    gap: 12px;
    align-items: center;
}

.staff-search-group {
    position: relative;
}

.staff-search-group i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
    font-size: 14px;
}

.staff-search-input,
.staff-filter-select {
    width: 100%;
    min-height: 46px;
    border: 1px solid #d1d9e6;
    border-radius: 12px;
    background: #ffffff;
    color: var(--dark);
    font-size: 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.staff-search-input {
    padding: 12px 14px 12px 44px;
}

.staff-filter-select {
    padding: 12px 14px;
}

.staff-search-input:focus,
.staff-filter-select:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.18);
}

.staff-filter-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-filter,
.clear-filter-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 46px;
    padding: 0 18px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}

.btn-filter {
    border: none;
    background: linear-gradient(135deg, var(--primary), #2563eb);
    color: #ffffff;
    box-shadow: 0 8px 16px rgba(37, 99, 235, 0.18);
}

.btn-filter:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.22);
}

.clear-filter-btn {
    border: 1px solid #d1d9e6;
    background: #ffffff;
    color: var(--gray);
}

.clear-filter-btn:hover {
    border-color: #93c5fd;
    color: var(--primary);
    background: #eff6ff;
}

/* Styling untuk modal */
.modal-content {
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Styling untuk daftar sertifikat */
.certificate-item {
    margin-bottom: 15px;
    padding: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    background: #f8f9fa;
}

.certificate-image {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin-top: 10px;
    border: 2px solid #ddd;
}

.certificate-info {
    margin-bottom: 10px;
}

.certificate-info h4 {
    color: var(--primary);
    margin-bottom: 5px;
}

.certificate-info p {
    color: var(--gray);
    font-size: 14px;
    margin: 3px 0;
}

/* Action buttons */
.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 35px;
    height: 35px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    margin: 0 5px;
    font-size: 14px;
}

.btn-view {
    background: #3b82f6;
    color: white;
    text-decoration: none;
}

.btn-view:hover {
    background: #2563eb;
    color: white;
    transform: translateY(-2px);
}


@media (max-width: 768px) {
    .page-header {
        padding: 18px;
        border-radius: 16px;
    }

    .page-title {
        font-size: 23px;
    }

    .staff-filter-form {
        grid-template-columns: 1fr;
    }

    .staff-filter-actions {
        width: 100%;
    }

    .staff-filter-actions .btn-filter,
    .staff-filter-actions .clear-filter-btn {
        width: 100%;
    }
}

/* Success and Error Messages */
.success-message {
    background: rgba(46, 125, 50, 0.1);
    border-left: 4px solid var(--success);
    color: var(--success);
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

.error-message {
    background: rgba(211, 47, 47, 0.1);
    border-left: 4px solid var(--danger);
    color: var(--danger);
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

/* Badge styles */
.badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: rgba(46, 125, 50, 0.1);
    color: var(--success);
}

.badge-danger {
    background: rgba(211, 47, 47, 0.1);
    color: var(--danger);
}

/* Hover batas baris tabel */
.data-table tbody tr {
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    position: relative;
    will-change: transform;
}

.data-table tbody tr:hover,
.data-table tbody tr:focus-within {
    background: #eef5ff;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(10, 36, 99, 0.18), 0 0 0 1px rgba(76, 138, 255, 0.35);
    z-index: 2;
}

@media (max-width: 768px) {
    .data-table tbody tr:hover,
    .data-table tbody tr:focus-within {
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(10, 36, 99, 0.14), 0 0 0 1px rgba(76, 138, 255, 0.28);
    }
}

@media (hover: none) {
    .data-table tbody tr:hover,
    .data-table tbody tr:focus-within {
        transform: none;
        box-shadow: none;
        background: #f8f9fa;
    }
}
</style>

<?php require_once '../includes/footer.php'; ?>
