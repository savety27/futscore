<?php
session_start();

$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

function formatUsiaFromDateOfBirth($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '-';
    }

    $dob = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
    if ($dob && $dob->format('Y-m-d') === $raw) {
        $today = new DateTimeImmutable('today');
        if ($dob > $today) {
            return '-';
        }
        return (string) $dob->diff($today)->y;
    }

    if (is_numeric($raw)) {
        return (string) max(0, (int) $raw);
    }

    return '-';
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page < 1 ? 1 : $page;
$limit = 10;
$offset = ($page - 1) * $limit;

$base_query = "SELECT p.*, (SELECT COUNT(*) FROM perangkat_licenses pl WHERE pl.perangkat_id = p.id) AS license_count
               FROM perangkat p WHERE 1=1";
$count_query = "SELECT COUNT(*) AS total FROM perangkat p WHERE 1=1";

// Handle search condition (same pattern as team.php)
if ($search !== '') {
    $search_term = "%{$search}%";
    $base_query .= " AND (p.name LIKE ? OR p.no_ktp LIKE ? OR p.email LIKE ? OR p.phone LIKE ? OR p.city LIKE ? OR p.province LIKE ?)";
    $count_query .= " AND (p.name LIKE ? OR p.no_ktp LIKE ? OR p.email LIKE ? OR p.phone LIKE ? OR p.city LIKE ? OR p.province LIKE ?)";
}

$base_query .= " ORDER BY p.created_at DESC";

try {
    // Count total records
    if ($search !== '') {
        $stmt = $conn->prepare($count_query);
        $stmt->execute([$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
    } else {
        $stmt = $conn->prepare($count_query);
        $stmt->execute();
    }

    $total_data = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $total_pages = (int)ceil($total_data / $limit);
    if ($total_pages < 1) {
        $total_pages = 1;
    }

    // Get data with pagination
    $query = $base_query . " LIMIT ? OFFSET ?";
    if ($search !== '') {
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $search_term);
        $stmt->bindValue(5, $search_term);
        $stmt->bindValue(6, $search_term);
        $stmt->bindValue(7, $limit, PDO::PARAM_INT);
        $stmt->bindValue(8, $offset, PDO::PARAM_INT);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    }

    $stmt->execute();
    $perangkat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    $perangkat_list = [];
    $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Perangkat Management</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
:root { --primary:#0f2744; --secondary:#f59e0b; --accent:#3b82f6; --success:#10b981; --danger:#ef4444; --gray:#64748b; --dark:#1e293b; --card-shadow:0 10px 15px -3px rgba(0,0,0,.05),0 4px 6px -2px rgba(0,0,0,.03);}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Plus Jakarta Sans','Segoe UI',system-ui,-apple-system,sans-serif;background:linear-gradient(180deg,#eaf6ff 0%,#dff1ff 45%,#f4fbff 100%);color:var(--dark);min-height:100vh;overflow-x:hidden}
.wrapper{display:flex;min-height:100vh}.main{flex:1;padding:30px;margin-left:280px;width:calc(100% - 280px);max-width:calc(100vw - 280px);overflow-x:hidden}.topbar,.page-header,.table-container{background:#fff;border-radius:20px;box-shadow:var(--card-shadow)}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:40px;padding:20px 25px}.greeting h1{font-size:28px;color:var(--primary)}.greeting p{color:var(--gray);font-size:14px}
.logout-btn{background:linear-gradient(135deg,var(--danger) 0%,#B71C1C 100%);color:#fff;padding:12px 28px;border-radius:12px;text-decoration:none;font-weight:600;display:flex;align-items:center;gap:10px}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;background:rgba(255,255,255,.85);backdrop-filter:blur(10px);padding:25px;gap:15px;flex-wrap:wrap;border:1px solid rgba(255,255,255,.6)}.page-title{font-size:28px;color:var(--primary);display:flex;align-items:center;gap:12px}.page-title i{color:var(--secondary)}
.search-bar{position:relative;width:400px;max-width:100%}.search-bar input{width:100%;padding:15px 50px 15px 20px;border:2px solid #e0e0e0;border-radius:12px;font-size:16px;background:#f8f9fa}.search-bar input:focus{outline:none;border-color:var(--primary);background:#fff;box-shadow:0 0 0 3px rgba(10,36,99,.1)}.search-bar button{position:absolute;right:15px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--primary);font-size:18px;cursor:pointer}
.btn{padding:12px 25px;border-radius:12px;border:none;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:10px;text-decoration:none;font-size:15px}.btn-primary{background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff}.btn-secondary{background:#6c757d;color:#fff}.btn-danger{background:var(--danger);color:#fff}
.table-container{background:rgba(255,255,255,.9);backdrop-filter:blur(5px);overflow:auto;border-radius:24px;margin-bottom:30px;max-width:100%;border:1px solid rgba(255,255,255,.8)}.data-table{width:100%;border-collapse:collapse;min-width:1200px}.data-table thead{background:linear-gradient(135deg,var(--primary),#1a365d);color:#fff}.data-table th{padding:12px 8px;text-align:left;font-weight:600;border-bottom:2px solid var(--secondary);font-size:12px;white-space:nowrap}.data-table tbody tr{border-bottom:1px solid #f0f0f0;transition:all .3s cubic-bezier(.4,0,.2,1);position:relative}.data-table tbody tr:hover{background:#eef5ff;transform:translateY(-3px);box-shadow:0 12px 24px rgba(10,36,99,.2),0 0 0 1px rgba(76,138,255,.35);z-index:2}.data-table tbody tr:first-child:hover{transform:translateY(0)}.data-table td{padding:8px;border-bottom:1px solid #f0f0f0;font-size:12px}
.staff-photo{width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid #e0e0e0}.badge{display:inline-block;padding:6px 10px;border-radius:14px;font-size:12px;font-weight:600}.badge-success{background:rgba(46,125,50,.1);color:var(--success)}.badge-danger{background:rgba(211,47,47,.1);color:var(--danger)}
.certificate-count{display:inline-block;padding:6px 12px;background:#e8f5e9;color:var(--success);border-radius:20px;font-weight:600;cursor:pointer;transition:.2s}.certificate-count:hover{background:var(--success);color:#fff}.action-buttons{display:flex;gap:8px}.action-btn{width:36px;height:36px;border-radius:10px;border:none;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;text-decoration:none}
.certificate-link{text-decoration:none}
.btn-view{background:rgba(10,36,99,.1);color:var(--primary)}.btn-edit{background:rgba(46,125,50,.1);color:var(--success)}.btn-delete{background:rgba(211,47,47,.1);color:var(--danger)}
.pagination{display:flex;justify-content:center;align-items:center;gap:10px;margin-top:20px}.page-link{padding:10px 14px;background:#fff;border:2px solid #e0e0e0;border-radius:10px;color:var(--dark);text-decoration:none;font-weight:600}.page-link.active{background:var(--primary);color:#fff;border-color:var(--primary)}.page-link.disabled{opacity:.5;pointer-events:none}
.alert{padding:15px 20px;border-radius:12px;margin-bottom:18px}.alert-danger{background:rgba(211,47,47,.1);border-left:4px solid var(--danger);color:var(--danger)}.alert-success{background:rgba(46,125,50,.1);border-left:4px solid var(--success);color:var(--success)}
.empty-state{text-align:center;padding:50px 20px;color:var(--gray)}.empty-state i{font-size:56px;opacity:.25;margin-bottom:10px}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center}.modal-content{background:#fff;padding:30px;border-radius:20px;max-width:500px;width:90%}.modal-header{display:flex;align-items:center;gap:12px;color:var(--danger);margin-bottom:12px}.modal-body p{margin:8px 0;color:var(--dark)}.modal-footer{display:flex;justify-content:flex-end;gap:10px;margin-top:15px}
@media (max-width:768px){.main{margin-left:0;padding:20px 15px;width:100%;max-width:100vw}.topbar,.page-header{flex-direction:column;align-items:flex-start}.page-title{font-size:24px}.search-bar{width:100%;max-width:100%}}
</style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar">
            <div class="greeting">
                <h1>Perangkat Management 📣</h1>
                <p>Kelola data perangkat</p>
            </div>
            <div class="user-actions">
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>

        <div class="page-header">
            <div class="page-title"><i class="fas fa-user-tie"></i><span>Daftar Perangkat</span></div>
            <form method="GET" action="perangkat.php" class="search-bar" id="searchForm">
                <input type="hidden" name="page" value="1">
                <input type="text" name="search" placeholder="Cari perangkat (nama, no.KTP, usia)" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <div class="action-buttons">
                <a href="perangkat_create.php" class="btn btn-primary"><i class="fas fa-plus"></i>Tambah Staff</a>
            </div>
        </div>

        <?php if (isset($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div><?php endif; ?>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Foto</th>
                        <th>Nama</th>
                        <th>No. KTP</th>
                        <th>Usia</th>
                        <th>Lisensi</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($perangkat_list)): ?>
                        <?php $no = $offset + 1; foreach ($perangkat_list as $row): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <?php if (!empty($row['photo'])): ?>
                                        <img src="../<?php echo htmlspecialchars($row['photo']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" class="staff-photo">
                                    <?php else: ?>
                                        <div class="staff-photo" style="background:#f0f0f0;display:flex;align-items:center;justify-content:center;"><i class="fas fa-user-tie" style="color:#999;"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['no_ktp']); ?></td>
                                <td><?php echo htmlspecialchars(formatUsiaFromDateOfBirth($row['age'] ?? '')); ?></td>
                                <td>
                                    <?php if ((int) $row['license_count'] > 0): ?>
                                        <span class="certificate-count" onclick="viewLicenses(<?php echo (int) $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">
                                            <i class="fas fa-certificate"></i> <?php echo (int) $row['license_count']; ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo (int)$row['is_active'] === 1 ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-danger">Non-Aktif</span>'; ?></td>
                                <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="perangkat_view.php?id=<?php echo (int)$row['id']; ?>" class="action-btn btn-view"><i class="fas fa-eye"></i></a>
                                        <a href="perangkat_edit.php?id=<?php echo (int)$row['id']; ?>" class="action-btn btn-edit"><i class="fas fa-edit"></i></a>
                                        <button class="action-btn btn-delete" data-staff-id="<?php echo (int)$row['id']; ?>" data-staff-name="<?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:40px;">
                                <div class="empty-state">
                                    <i class="fas fa-user-tie"></i>
                                    <h3>Belum Ada Data Staff</h3>
                                    <p>Mulai dengan menambahkan staff pertama Anda menggunakan tombol "Add Staff" di atas.</p>
                                    <a href="perangkat_create.php" class="btn btn-primary" style="margin-top:15px;"><i class="fas fa-plus"></i>Tambah Staff Pertama</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-left"></i></a><?php else: ?><span class="page-link disabled"><i class="fas fa-chevron-left"></i></span><?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?><a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a><?php endfor; ?>
                <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link"><i class="fas fa-chevron-right"></i></a><?php else: ?><span class="page-link disabled"><i class="fas fa-chevron-right"></i></span><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="licensesModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:15px;width:90%;max-width:800px;max-height:80vh;overflow-y:auto;padding:30px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 id="licensesModalTitle" style="color:var(--primary);"></h3>
            <button onclick="closeLicensesModal()" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--danger);">&times;</button>
        </div>
        <div id="licensesModalContent"></div>
    </div>
</div>

<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Konfirmasi Hapus Staff</h3>
        </div>
        <div class="modal-body">
            <p>Apakah Anda yakin ingin menghapus staff <strong>"<span id="deleteStaffName"></span>"</strong>?</p>
            <p style="color: var(--danger); font-weight: 600; margin-top: 10px;"><i class="fas fa-exclamation-circle"></i> Data yang dihapus tidak dapat dikembalikan!</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
            <button class="btn btn-danger" id="confirmDeleteBtn">Hapus</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
let currentStaffId = null;

document.addEventListener('DOMContentLoaded', function() {
    const deleteStaffName = document.getElementById('deleteStaffName');
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const searchForm = document.getElementById('searchForm');

    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            const pageInput = this.querySelector('input[name="page"]');
            if (pageInput) {
                pageInput.value = '1';
            }
        });
    }

    document.querySelectorAll('.btn-delete[data-staff-id]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            currentStaffId = this.getAttribute('data-staff-id');
            if (deleteStaffName) {
                deleteStaffName.textContent = this.getAttribute('data-staff-name') || '-';
            }
            if (deleteModal) {
                deleteModal.style.display = 'flex';
            }
        });
    });

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (currentStaffId) {
                deleteStaff(currentStaffId);
            }
        });
    }

    if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    }
});

function closeDeleteModal() {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.style.display = 'none';
    }
    currentStaffId = null;
}

function deleteStaff(staffId) {
    fetch(`perangkat_delete.php?id=${staffId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            toastr.success('Staff berhasil dihapus!');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            toastr.error('Error: ' + data.message);
            closeDeleteModal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('Terjadi kesalahan saat menghapus staff.');
        closeDeleteModal();
    });
}

function viewLicenses(staffId, staffName) {
    const modal = document.getElementById('licensesModal');
    const modalTitle = document.getElementById('licensesModalTitle');
    const content = document.getElementById('licensesModalContent');

    modalTitle.textContent = `Lisensi: ${staffName}`;
    content.innerHTML = '<p style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Memuat data...</p>';
    modal.style.display = 'flex';

    fetch(`perangkat_licenses.php?id=${staffId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.licenses.length > 0) {
                let html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:15px;">';
                data.licenses.forEach(lic => {
                    const file = String(lic.license_file || '');
                    const fileExt = file.split('.').pop().toLowerCase();
                    const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);
                    const fileUrl = `../uploads/perangkat/licenses/${encodeURIComponent(file)}`;
                    const safeName = String(lic.license_name || 'Lisensi');
                    const safeAuthority = String(lic.issuing_authority || '');
                    const safeIssueDate = String(lic.issue_date || '');

                    html += `
                    <div style="border:1px solid #e0e0e0;border-radius:10px;padding:15px;background:#f8f9fa;">
                        <h4 style="margin-bottom:10px;color:var(--primary);">${safeName}</h4>
                        ${safeAuthority ? `<p><strong>Penerbit:</strong> ${safeAuthority}</p>` : ''}
                        ${safeIssueDate ? `<p><strong>Tanggal Terbit:</strong> ${safeIssueDate}</p>` : ''}
                        <div style="margin-top:15px;">
                            ${isImage
                                ? `<img src="${fileUrl}" alt="${safeName}" style="width:100%;height:150px;object-fit:cover;border-radius:5px;cursor:pointer;"
                                      onclick="viewLicenseImage('${file}', '${safeName.replace(/'/g, "\\'")}')">`
                                : `<div style="background:#e0e0e0;padding:20px;text-align:center;border-radius:5px;">
                                    <i class="fas fa-file-alt" style="font-size:40px;color:#666;"></i>
                                    <p style="margin-top:10px;word-break:break-word;">${file}</p>
                                   </div>`
                            }
                        </div>
                    </div>`;
                });
                html += '</div>';
                content.innerHTML = html;
            } else {
                content.innerHTML = '<p style="text-align:center;padding:40px;color:var(--gray);">Belum ada lisensi</p>';
            }
        })
        .catch(() => {
            content.innerHTML = '<p style="text-align:center;padding:40px;color:var(--danger);">Error memuat data lisensi</p>';
        });
}

function closeLicensesModal() {
    const modal = document.getElementById('licensesModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function viewLicenseImage(filename, title) {
    const safeFilename = encodeURIComponent(String(filename || ''));
    const safeTitle = String(title || 'Lisensi');
    const modal = document.createElement('div');
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.9);z-index:2000;display:flex;align-items:center;justify-content:center;';
    modal.innerHTML = `
        <div style="position:relative;">
            <button onclick="this.parentElement.parentElement.remove()" style="position:absolute;top:-40px;right:0;background:none;border:none;color:#fff;font-size:24px;cursor:pointer;">&times;</button>
            <img src="../uploads/perangkat/licenses/${safeFilename}" alt="${safeTitle}" style="max-width:90vw;max-height:90vh;border-radius:5px;">
            <p style="text-align:center;color:#fff;margin-top:10px;">${safeTitle}</p>
        </div>
    `;
    document.body.appendChild(modal);
}

document.getElementById('licensesModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeLicensesModal();
    }
});
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
