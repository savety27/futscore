<?php
$page_title = 'Daftar Staf Tim';
$current_page = 'team_staff';
require_once 'config/database.php';
require_once 'includes/header.php';

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get team_id from session
$team_id = $_SESSION['team_id'] ?? 0;

// Base Query (Filtered by team_id)
$base_query = "SELECT 
    ts.id,
    ts.team_id,
    ts.name,
    ts.position,
    ts.email,
    ts.phone,
    ts.photo,
    ts.birth_date,
    t.name as team_name,
    (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) as certificate_count
    FROM team_staff ts
    LEFT JOIN teams t ON ts.team_id = t.id
    WHERE ts.team_id = ?";

$count_query = "SELECT COUNT(DISTINCT ts.id) as total FROM team_staff ts 
                LEFT JOIN teams t ON ts.team_id = t.id
                WHERE ts.team_id = ?";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ? OR t.name LIKE ?)";
    $count_query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ? OR t.name LIKE ?)";
}

$base_query .= " GROUP BY ts.id ORDER BY ts.created_at DESC";

$total_data = 0;
$total_pages = 1;
$staff_list = [];

try {
    if (!empty($search)) {
        $stmt = $conn->prepare($count_query);
        $stmt->execute([$team_id, $search_term, $search_term, $search_term, $search_term, $search_term]);
    } else {
        $stmt = $conn->prepare($count_query);
        $stmt->execute([$team_id]);
    }
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = $result['total'];
    
    $total_pages = ceil($total_data / $limit);
    
    $query = $base_query . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    
    if (!empty($search)) {
        $stmt->bindValue(1, $team_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $search_term);
        $stmt->bindValue(5, $search_term);
        $stmt->bindValue(6, $search_term);
        $stmt->bindValue(7, $limit, PDO::PARAM_INT);
        $stmt->bindValue(8, $offset, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $team_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
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
?>

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
    <div class="section-header">
        <h2 class="section-title">Staf Tim</h2>
         <!-- Read Only: No Add Button -->
    </div>

    <div class="search-bar" style="margin-bottom: 20px;">
        <form action="" method="GET">
            <input type="text" name="search" placeholder="Cari staf..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
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
                        <th>Tim</th>
                        <th style="text-align: center;">Jabatan</th>
                        <th style="text-align: center;">Umur</th>
                        <th style="text-align: center;">Sertifikat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_list as $staff): ?>
                    <tr>
                         <td>
                            <?php if (!empty($staff['photo'])): ?>
                                <img src="../uploads/staff/<?php echo basename($staff['photo']); ?>" 
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
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">&laquo; Seb</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">Sel &raquo;</a>
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
</style>

<?php require_once 'includes/footer.php'; ?>
