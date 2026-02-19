<?php
require_once 'config/database.php';
session_start();

// Ensure user is logged in and is a pelatih
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
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Function to handle file upload
    function uploadPlayerFile($file, $upload_dir = '../images/players/', $prefix = 'player_') {
        if (!isset($file) || $file['error'] != 0) {
            return null;
        }
        
        // Check if file is an image
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG, GIF allowed.');
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File too large. Maximum 5MB allowed.');
        }
        
        // Create directory if not exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $prefix . uniqid() . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            throw new Exception('Failed to upload file.');
        }
        
        return $filename;
    }

    // Check duplicate player name globally across all teams.
    function isDuplicatePlayerName($conn, $name, $exclude_id = 0) {
        $sql = "SELECT id FROM players WHERE TRIM(name) = TRIM(?)";
        $params = [$name];

        if ($exclude_id > 0) {
            $sql .= " AND id <> ?";
            $params[] = $exclude_id;
        }

        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }
    
    // Track newly uploaded files so we can clean them up if DB write fails.
    $uploaded_files_to_cleanup = [];

    try {
        if (!$team_id) {
            throw new Exception('Tim belum terhubung ke akun pelatih. Silakan set tim terlebih dulu.');
        }
        // Get form data
        $name = trim($_POST['name'] ?? '');
        $jersey_number = trim($_POST['jersey_number'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $position_detail = trim($_POST['position_detail'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $birth_place = trim($_POST['birth_place'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $height = !empty($_POST['height']) ? (int)$_POST['height'] : null;
        $weight = !empty($_POST['weight']) ? (int)$_POST['weight'] : null;
        $dominant_foot = trim($_POST['dominant_foot'] ?? '');
        $nisn = trim($_POST['nisn'] ?? '');
        $nik = trim($_POST['nik'] ?? '');
        $sport_type = trim($_POST['sport_type'] ?? 'Futsal');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $nationality = trim($_POST['nationality'] ?? 'Indonesia');
        $street = trim($_POST['street'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? 'Indonesia');
        $status = isset($_POST['status']) ? 'active' : 'inactive';
        $position_detail_db = $position_detail !== '' ? $position_detail : null;
        
        // Skills data
        $dribbling = (int)($_POST['dribbling'] ?? 5);
        $technique = (int)($_POST['technique'] ?? 5);
        $speed = (int)($_POST['speed'] ?? 5);
        $juggling = (int)($_POST['juggling'] ?? 5);
        $shooting = (int)($_POST['shooting'] ?? 5);
        $setplay_position = (int)($_POST['setplay_position'] ?? 5);
        $passing = (int)($_POST['passing'] ?? 5);
        $control = (int)($_POST['control'] ?? 5);
        
        // Validate required fields (ONLY for add/edit)
        if ($action === 'add' || $action === 'edit') {
            if (empty($name) || empty($jersey_number) || empty($position) || 
                empty($birth_date) || empty($birth_place) || empty($gender) || 
                empty($nik) || empty($sport_type)) {
                throw new Exception('All required fields must be filled.');
            }
            
            // Validate NIK
            if (!preg_match('/^[0-9]{16}$/', $nik)) {
                throw new Exception('NIK must be exactly 16 digits.');
            }

            if ((function_exists('mb_strlen') ? mb_strlen($position_detail, 'UTF-8') : strlen($position_detail)) > 100) {
                throw new Exception('Detail posisi maksimal 100 karakter!');
            }
        }
        
        // --- ADD PLAYER ---
        if ($action === 'add') {
            if (isDuplicatePlayerName($conn, $name)) {
                throw new Exception('Nama pemain sudah terdaftar. Gunakan nama yang berbeda.');
            }

            // Generate slug
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            $slug = trim($slug, '-');
            $slug .= '-' . substr(md5(uniqid()), 0, 6);

            // KK is mandatory for add
            if (!isset($_FILES['kk_image']) || $_FILES['kk_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File Kartu Keluarga (KK) wajib diupload!');
            }
            
            // Upload files
            $photo = null;
            $ktp_image = null;
            $kk_image = null;
            $birth_cert_image = null;
            $diploma_image = null;
            
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                $photo = uploadPlayerFile($_FILES['photo']);
            }
            
            if (isset($_FILES['ktp_image']) && $_FILES['ktp_image']['error'] == 0) {
                $ktp_image = uploadPlayerFile($_FILES['ktp_image'], '../images/players/', 'ktp_');
            }
            
            if (isset($_FILES['kk_image']) && $_FILES['kk_image']['error'] == 0) {
                $kk_image = uploadPlayerFile($_FILES['kk_image'], '../images/players/', 'kk_');
            }
            
            if (isset($_FILES['birth_cert_image']) && $_FILES['birth_cert_image']['error'] == 0) {
                $birth_cert_image = uploadPlayerFile($_FILES['birth_cert_image'], '../images/players/', 'akte_');
            }
            
            if (isset($_FILES['diploma_image']) && $_FILES['diploma_image']['error'] == 0) {
                $diploma_image = uploadPlayerFile($_FILES['diploma_image'], '../images/players/', 'ijazah_');
            }
            
            // Insert player data
            $stmt = $conn->prepare("
                INSERT INTO players (
                    name, slug, team_id, jersey_number, position, position_detail, 
                    birth_date, birth_place, gender, height, weight, photo, 
                    dominant_foot, nisn, nik, sport_type, email, phone, 
                    nationality, street, city, province, postal_code, country,
                    dribbling, technique, speed, juggling, shooting, 
                    setplay_position, passing, control, status,
                    ktp_image, kk_image, birth_cert_image, diploma_image
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $name, $slug, $team_id, $jersey_number, $position, $position_detail_db,
                $birth_date, $birth_place, $gender, $height, $weight, $photo,
                $dominant_foot, $nisn, $nik, $sport_type, $email, $phone,
                $nationality, $street, $city, $province, $postal_code, $country,
                $dribbling, $technique, $speed, $juggling, $shooting,
                $setplay_position, $passing, $control, $status,
                $ktp_image, $kk_image, $birth_cert_image, $diploma_image
            ]);
            
            header("Location: players.php?msg=added");
            exit;
        }
        
        // --- EDIT PLAYER ---
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid player ID.');
            }

            if (isDuplicatePlayerName($conn, $name, $id)) {
                throw new Exception('Nama pemain sudah terdaftar. Gunakan nama yang berbeda.');
            }
            
            // Build update query
            $sql = "UPDATE players SET 
                    name = ?, jersey_number = ?, position = ?, position_detail = ?, 
                    birth_date = ?, birth_place = ?, gender = ?, 
                    height = ?, weight = ?, dominant_foot = ?, 
                    nisn = ?, nik = ?, sport_type = ?, email = ?, phone = ?, 
                    nationality = ?, street = ?, city = ?, province = ?, 
                    postal_code = ?, country = ?,
                    dribbling = ?, technique = ?, speed = ?, juggling = ?, shooting = ?,
                    setplay_position = ?, passing = ?, control = ?, status = ?, updated_at = NOW()";
            
            $params = [
                $name, $jersey_number, $position, $position_detail_db,
                $birth_date, $birth_place, $gender,
                $height, $weight, $dominant_foot,
                $nisn, $nik, $sport_type, $email, $phone,
                $nationality, $street, $city, $province,
                $postal_code, $country,
                $dribbling, $technique, $speed, $juggling, $shooting,
                $setplay_position, $passing, $control, $status
            ];
            
            // Handle file uploads and delete old files
            $files_to_check = [
                'photo' => 'photo',
                'ktp_image' => 'ktp_image',
                'kk_image' => 'kk_image',
                'birth_cert_image' => 'birth_cert_image',
                'diploma_image' => 'diploma_image'
            ];
            
            // Get current file names
            $current_files = [];
            if ($action === 'edit') {
                $stmt = $conn->prepare("SELECT photo, ktp_image, kk_image, birth_cert_image, diploma_image 
                                       FROM players WHERE id = ? AND team_id = ?");
                $stmt->execute([$id, $team_id]);
                $current_files = $stmt->fetch();
            }

            $kk_has_existing = ($current_files && !empty($current_files['kk_image']));
            $kk_new_uploaded = isset($_FILES['kk_image']) && $_FILES['kk_image']['error'] === UPLOAD_ERR_OK;
            if (!$kk_has_existing && !$kk_new_uploaded) {
                throw new Exception('File Kartu Keluarga (KK) wajib diupload!');
            }
            
            foreach ($files_to_check as $file_key => $db_field) {
                if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
                    $prefix = ($file_key === 'photo') ? 'player_' : ($file_key . '_');
                    $new_filename = uploadPlayerFile($_FILES[$file_key], '../images/players/', $prefix);
                    
                    if ($new_filename) {
                        $uploaded_files_to_cleanup[] = $new_filename;
                        $sql .= ", $db_field = ?";
                        $params[] = $new_filename;
                    }
                }
            }
            
            $sql .= " WHERE id = ? AND team_id = ?";
            $params[] = $id;
            $params[] = $team_id;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            $affectedRows = $stmt->rowCount();
            
            if ($affectedRows > 0) {
                // Delete replaced old files only after DB update succeeds.
                foreach ($files_to_check as $file_key => $db_field) {
                    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
                        if ($current_files && !empty($current_files[$db_field])) {
                            $old_file = '../images/players/' . $current_files[$db_field];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                    }
                }
                header("Location: players.php?msg=updated");
            } else {
                // No DB row changed (e.g. unauthorized id/team): remove newly uploaded files.
                foreach ($uploaded_files_to_cleanup as $new_file) {
                    $new_file_path = '../images/players/' . $new_file;
                    if (file_exists($new_file_path)) {
                        unlink($new_file_path);
                    }
                }
                header("Location: players.php?msg=no_changes");
            }
            exit;
        }
        
        // --- DELETE PLAYER ---
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('Invalid player ID.');
            }
            
            // Get player files for deletion
            $stmt = $conn->prepare("SELECT photo, ktp_image, kk_image, birth_cert_image, diploma_image 
                                   FROM players WHERE id = ? AND team_id = ?");
            $stmt->execute([$id, $team_id]);
            $player_files = $stmt->fetch();
            
            // Delete player
            $stmt = $conn->prepare("DELETE FROM players WHERE id = ? AND team_id = ?");
            $stmt->execute([$id, $team_id]);
            
            $affectedRows = $stmt->rowCount();
            
            if ($affectedRows > 0) {
                // Delete all associated files
                $files_to_delete = [
                    'photo', 'ktp_image', 'kk_image', 'birth_cert_image', 'diploma_image'
                ];
                
                foreach ($files_to_delete as $file_field) {
                    if ($player_files && !empty($player_files[$file_field])) {
                        $file_path = '../images/players/' . $player_files[$file_field];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                }
                
                header("Location: players.php?msg=deleted");
            } else {
                header("Location: players.php?msg=not_found");
            }
            exit;
        }
    } catch (Exception $e) {
        // Roll back uploaded files when operation fails.
        foreach ($uploaded_files_to_cleanup as $new_file) {
            $new_file_path = '../images/players/' . $new_file;
            if (file_exists($new_file_path)) {
                unlink($new_file_path);
            }
        }

        // Redirect back with error message
        $error_msg = $e->getMessage();
        
        // Check for foreign key constraint violation
        if (strpos($error_msg, '1451') !== false || strpos($error_msg, 'foreign key constraint fails') !== false) {
            $error_msg = 'Tidak dapat menghapus data player yang sudah pernah berkontribusi pada suatu team.';
        }

        // Check duplicate key violation from DB (e.g. NIK or global name unique)
        if ($e instanceof PDOException) {
            $driver_code = (int)($e->errorInfo[1] ?? 0);
            $message_lc = strtolower($e->getMessage());

            if ($driver_code === 1062) {
                if (strpos($message_lc, 'nik') !== false) {
                    $error_msg = 'NIK sudah terdaftar. Gunakan NIK yang berbeda.';
                } elseif (
                    strpos($message_lc, 'uq_players_name') !== false ||
                    strpos($message_lc, 'name') !== false
                ) {
                    $error_msg = 'Nama pemain sudah terdaftar. Gunakan nama yang berbeda.';
                } else {
                    $error_msg = 'Data duplikat terdeteksi. Periksa kembali input Anda.';
                }
            }
        }
        
        $error_msg = urlencode($error_msg);
        
        if ($action === 'add') {
            $redirect_url = 'player_form.php?error=' . $error_msg;
        } elseif ($action === 'edit') {
            $player_id = $_POST['id'] ?? '';
            $redirect_url = "player_form.php?id=$player_id&error=$error_msg";
        } else {
            // Delete or unknown
            $redirect_url = 'players.php?error=' . $error_msg;
        }
        
        header("Location: $redirect_url");
        exit;
    }
} else {
    header("Location: players.php");
    exit;
}
?>
