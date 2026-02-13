<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Ensure user is logged in and is a coach
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'pelatih') {
    header('Location: ../login.php');
    exit;
}

$team_id = $_SESSION['team_id'] ?? 0;

// Fallback: load team_id from admin_users if missing
if (!$team_id && isset($_SESSION['admin_id'])) {
    $stmtTeam = $conn->prepare("SELECT team_id FROM admin_users WHERE id = ?");
    $stmtTeam->execute([$_SESSION['admin_id']]);
    $team_id = (int)($stmtTeam->fetchColumn() ?? 0);
    if ($team_id > 0) {
        $_SESSION['team_id'] = $team_id;
    }
}

if (!$team_id) {
    $_SESSION['error_message'] = 'Tim belum terhubung ke akun pelatih. Silakan hubungi administrator.';
    header('Location: team_staff.php');
    exit;
}

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Initialize errors array
    $errors = [];
    
    try {
        if ($action !== 'add') {
            throw new Exception('Aksi ini tidak diizinkan. Pelatih hanya dapat menambah staf baru.');
        }

        // Get form data
        $name = trim($_POST['name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $birth_place = trim($_POST['birth_place'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? 'Indonesia');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // --- ADD STAFF ---
        if ($action === 'add') {
            // Validation
            if (empty($name)) {
                $errors[] = "Nama staff harus diisi";
            }
            
            if (empty($position)) {
                $errors[] = "Jabatan harus dipilih";
            }
            
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Format email tidak valid";
            }
            
            // Handle photo upload
            $photo_path = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                $file = $_FILES['photo'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                // Check file type
                if (!in_array($file['type'], $allowed_types)) {
                    $errors[] = "Format file harus JPG, PNG, atau GIF";
                }
                
                // Check file size
                if ($file['size'] > $max_size) {
                    $errors[] = "Ukuran file maksimal 5MB";
                }
                
                if (empty($errors)) {
                    // Generate unique filename
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'staff_' . time() . '_' . uniqid() . '.' . $ext;
                    $upload_dir = '../uploads/staff/';
                    
                    // Create directory if not exists
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        $photo_path = 'uploads/staff/' . $filename;
                    } else {
                        $errors[] = "Gagal mengupload foto";
                    }
                }
            }
            
            // Handle certificates upload
            $certificates = [];
            if (isset($_FILES['certificates']) && is_array($_FILES['certificates']['name'])) {
                $certificate_names = $_POST['certificate_name'] ?? [];
                $issuing_authorities = $_POST['certificate_authority'] ?? [];
                $issue_dates = $_POST['certificate_date'] ?? [];
                
                for ($i = 0; $i < count($_FILES['certificates']['name']); $i++) {
                    if ($_FILES['certificates']['error'][$i] == UPLOAD_ERR_OK) {
                        $cert_file = [
                            'name' => $_FILES['certificates']['name'][$i],
                            'type' => $_FILES['certificates']['type'][$i],
                            'tmp_name' => $_FILES['certificates']['tmp_name'][$i],
                            'size' => $_FILES['certificates']['size'][$i]
                        ];
                        
                        $max_size = 10 * 1024 * 1024; // 10MB
                        
                        // Check file type
                        $file_ext = strtolower(pathinfo($cert_file['name'], PATHINFO_EXTENSION));
                        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
                        
                        if (!in_array($file_ext, $allowed_ext)) {
                            $errors[] = "Format file sertifikat harus JPG, PNG, GIF, PDF, atau DOC";
                            break;
                        }
                        
                        // Check file size
                        if ($cert_file['size'] > $max_size) {
                            $errors[] = "Ukuran file sertifikat maksimal 10MB per file";
                            break;
                        }
                        
                        // Generate unique filename
                        $filename = 'cert_' . time() . '_' . uniqid() . '.' . $file_ext;
                        $upload_dir = '../uploads/certificates/';
                        
                        // Create directory if not exists
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($cert_file['tmp_name'], $target_path)) {
                            $certificates[] = [
                                'name' => $certificate_names[$i] ?? $cert_file['name'],
                                'file' => $filename,
                                'authority' => $issuing_authorities[$i] ?? '',
                                'date' => $issue_dates[$i] ?? null
                            ];
                        } else {
                            $errors[] = "Gagal mengupload file sertifikat";
                        }
                    }
                }
            }
            
            // If no errors, insert to database
            if (empty($errors)) {
                $conn->beginTransaction();
                
                // Insert staff data with coach's team_id
                $stmt = $conn->prepare("
                    INSERT INTO team_staff (
                        team_id, name, position, email, phone, photo, 
                        birth_place, birth_date, address, city, province, 
                        postal_code, country, is_active, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $team_id,  // Automatically use coach's team_id
                    $name,
                    $position,
                    $email,
                    $phone,
                    $photo_path,
                    $birth_place,
                    $birth_date ?: null,
                    $address,
                    $city,
                    $province,
                    $postal_code,
                    $country,
                    $is_active
                ]);
                
                $staff_id = $conn->lastInsertId();
                
                // Insert certificates if any
                if (!empty($certificates)) {
                    $stmt = $conn->prepare("
                        INSERT INTO staff_certificates (staff_id, certificate_name, certificate_file, issuing_authority, issue_date, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    
                    foreach ($certificates as $cert) {
                        $stmt->execute([
                            $staff_id,
                            $cert['name'],
                            $cert['file'],
                            $cert['authority'],
                            $cert['date'] ?: null
                        ]);
                    }
                }
                
                $conn->commit();
                
                $_SESSION['success_message'] = "Staff berhasil ditambahkan!";
                header("Location: team_staff.php");
                exit;
            } else {
                $_SESSION['error_message'] = implode('<br>', $errors);
                header("Location: staff_form.php");
                exit;
            }
        }
        
        // --- EDIT STAFF ---
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid staff ID.');
            }
            
            // Verify staff belongs to coach's team
            $stmt = $conn->prepare("SELECT id FROM team_staff WHERE id = ? AND team_id = ?");
            $stmt->execute([$id, $team_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Akses ditolak. Staff ini bukan milik tim Anda.');
            }
            
            // Validation
            if (empty($name)) {
                $errors[] = "Nama staff harus diisi";
            }
            
            if (empty($position)) {
                $errors[] = "Jabatan harus dipilih";
            }
            
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Format email tidak valid";
            }
            
            // Get current photo
            $stmt = $conn->prepare("SELECT photo FROM team_staff WHERE id = ? AND team_id = ?");
            $stmt->execute([$id, $team_id]);
            $current_staff = $stmt->fetch(PDO::FETCH_ASSOC);
            $photo_path = $current_staff['photo'];
            
            // Handle photo deletion
            if (isset($_POST['delete_photo']) && $photo_path) {
                if (file_exists('../' . $photo_path)) {
                    @unlink('../' . $photo_path);
                }
                $photo_path = null;
            }
            
            // Handle new photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
                $file = $_FILES['photo'];
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                // Check file type
                if (!in_array($file['type'], $allowed_types)) {
                    $errors[] = "Format file harus JPG, PNG, atau GIF";
                }
                
                // Check file size
                if ($file['size'] > $max_size) {
                    $errors[] = "Ukuran file maksimal 5MB";
                }
                
                if (empty($errors)) {
                    // Generate unique filename
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'staff_' . time() . '_' . uniqid() . '.' . $ext;
                    $upload_dir = '../uploads/staff/';
                    
                    // Create directory if not exists
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        // Delete old photo if exists
                        if ($photo_path && file_exists('../' . $photo_path)) {
                            @unlink('../' . $photo_path);
                        }
                        $photo_path = 'uploads/staff/' . $filename;
                    } else {
                        $errors[] = "Gagal mengupload foto";
                    }
                }
            }
            
            // Handle certificate deletions
            $delete_certificates = $_POST['delete_certificates'] ?? [];
            $new_certificates = [];
            
            // Handle new certificates upload
            if (isset($_FILES['new_certificates']) && is_array($_FILES['new_certificates']['name'])) {
                $certificate_names = $_POST['new_certificate_name'] ?? [];
                $issuing_authorities = $_POST['new_certificate_authority'] ?? [];
                $issue_dates = $_POST['new_certificate_date'] ?? [];
                
                for ($i = 0; $i < count($_FILES['new_certificates']['name']); $i++) {
                    if ($_FILES['new_certificates']['error'][$i] == UPLOAD_ERR_OK) {
                        $cert_file = [
                            'name' => $_FILES['new_certificates']['name'][$i],
                            'type' => $_FILES['new_certificates']['type'][$i],
                            'tmp_name' => $_FILES['new_certificates']['tmp_name'][$i],
                            'size' => $_FILES['new_certificates']['size'][$i]
                        ];
                        
                        $max_size = 10 * 1024 * 1024; // 10MB
                        
                        // Check file type
                        $file_ext = strtolower(pathinfo($cert_file['name'], PATHINFO_EXTENSION));
                        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
                        
                        if (!in_array($file_ext, $allowed_ext)) {
                            $errors[] = "Format file sertifikat harus JPG, PNG, GIF, PDF, atau DOC";
                            break;
                        }
                        
                        // Check file size
                        if ($cert_file['size'] > $max_size) {
                            $errors[] = "Ukuran file sertifikat maksimal 10MB per file";
                            break;
                        }
                        
                        // Generate unique filename
                        $filename = 'cert_' . time() . '_' . uniqid() . '.' . $file_ext;
                        $upload_dir = '../uploads/certificates/';
                        
                        // Create directory if not exists
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($cert_file['tmp_name'], $target_path)) {
                            $new_certificates[] = [
                                'name' => $certificate_names[$i] ?? $cert_file['name'],
                                'file' => $filename,
                                'authority' => $issuing_authorities[$i] ?? '',
                                'date' => $issue_dates[$i] ?? null
                            ];
                        } else {
                            $errors[] = "Gagal mengupload file sertifikat";
                        }
                    }
                }
            }
            
            // If no errors, update database
            if (empty($errors)) {
                $conn->beginTransaction();
                
                // Update staff data (with team_id check for security)
                $stmt = $conn->prepare("
                    UPDATE team_staff SET 
                        name = ?, 
                        position = ?, 
                        email = ?, 
                        phone = ?, 
                        photo = ?, 
                        birth_place = ?, 
                        birth_date = ?, 
                        address = ?, 
                        city = ?, 
                        province = ?, 
                        postal_code = ?, 
                        country = ?, 
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = ? AND team_id = ?
                ");
                
                $stmt->execute([
                    $name,
                    $position,
                    $email,
                    $phone,
                    $photo_path,
                    $birth_place,
                    $birth_date ?: null,
                    $address,
                    $city,
                    $province,
                    $postal_code,
                    $country,
                    $is_active,
                    $id,
                    $team_id  // Security: only update if belongs to coach's team
                ]);
                
                // Delete selected certificates
                if (!empty($delete_certificates)) {
                    foreach ($delete_certificates as $cert_id) {
                        // Get file path first (with team security check)
                        $stmt = $conn->prepare("
                            SELECT sc.certificate_file 
                            FROM staff_certificates sc
                            JOIN team_staff ts ON sc.staff_id = ts.id
                            WHERE sc.id = ? AND sc.staff_id = ? AND ts.team_id = ?
                        ");
                        $stmt->execute([$cert_id, $id, $team_id]);
                        if ($cert = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $file_path = '../uploads/certificates/' . $cert['certificate_file'];
                            if (file_exists($file_path)) {
                                @unlink($file_path);
                            }
                        }
                        
                        // Delete from database
                        $stmt = $conn->prepare("
                            DELETE sc FROM staff_certificates sc
                            JOIN team_staff ts ON sc.staff_id = ts.id
                            WHERE sc.id = ? AND sc.staff_id = ? AND ts.team_id = ?
                        ");
                        $stmt->execute([$cert_id, $id, $team_id]);
                    }
                }
                
                // Insert new certificates
                if (!empty($new_certificates)) {
                    $stmt = $conn->prepare("
                        INSERT INTO staff_certificates (staff_id, certificate_name, certificate_file, issuing_authority, issue_date, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    
                    foreach ($new_certificates as $cert) {
                        $stmt->execute([
                            $id,
                            $cert['name'],
                            $cert['file'],
                            $cert['authority'],
                            $cert['date'] ?: null
                        ]);
                    }
                }
                
                $conn->commit();
                
                $_SESSION['success_message'] = "Staff berhasil diperbarui!";
                header("Location: team_staff.php");
                exit;
            } else {
                $_SESSION['error_message'] = implode('<br>', $errors);
                header("Location: staff_form.php?id=" . $id);
                exit;
            }
        }
        
        // --- DELETE STAFF ---
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid staff ID.');
            }
            
            // Get staff data for file deletion (with team security check)
            $stmt = $conn->prepare("SELECT photo FROM team_staff WHERE id = ? AND team_id = ?");
            $stmt->execute([$id, $team_id]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$staff) {
                throw new Exception('Staff tidak ditemukan atau akses ditolak.');
            }
            
            $conn->beginTransaction();
            
            // Get certificates for deletion (with team security check)
            $stmt = $conn->prepare("
                SELECT sc.certificate_file 
                FROM staff_certificates sc
                JOIN team_staff ts ON sc.staff_id = ts.id
                WHERE sc.staff_id = ? AND ts.team_id = ?
            ");
            $stmt->execute([$id, $team_id]);
            $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Delete certificates files
            foreach ($certificates as $cert) {
                $file_path = '../uploads/certificates/' . $cert['certificate_file'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            
            // Delete staff photo
            if (!empty($staff['photo']) && file_exists('../' . $staff['photo'])) {
                @unlink('../' . $staff['photo']);
            }
            
            // Delete certificates from database
            $stmt = $conn->prepare("
                DELETE sc FROM staff_certificates sc
                JOIN team_staff ts ON sc.staff_id = ts.id
                WHERE sc.staff_id = ? AND ts.team_id = ?
            ");
            $stmt->execute([$id, $team_id]);
            
            // Delete staff (with team security check)
            $stmt = $conn->prepare("DELETE FROM team_staff WHERE id = ? AND team_id = ?");
            $success = $stmt->execute([$id, $team_id]);
            
            if ($success && $stmt->rowCount() > 0) {
                $conn->commit();
                $_SESSION['success_message'] = "Staff berhasil dihapus!";
            } else {
                $conn->rollBack();
                $_SESSION['error_message'] = "Gagal menghapus staff atau akses ditolak.";
            }
            
            header("Location: team_staff.php");
            exit;
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        $_SESSION['error_message'] = $e->getMessage();
        
        if ($action === 'add') {
            header("Location: staff_form.php");
        } else {
            header("Location: team_staff.php");
        }
        exit;
    }
} else {
    header("Location: team_staff.php");
    exit;
}
?>
