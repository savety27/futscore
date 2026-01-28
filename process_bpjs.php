<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bpjs.php');
    exit;
}

// Database connection
$db = new Database();
$conn = $db->getConnection();

// Get form data
$nik = $_POST['nik'] ?? '';
$full_name = $_POST['full_name'] ?? '';
$birth_date = $_POST['birth_date'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$address = $_POST['address'] ?? '';
$kelurahan = $_POST['kelurahan'] ?? '';
$work_location = $_POST['work_location'] ?? '';
$job_type1 = $_POST['job_type1'] ?? '';
$job_type2 = $_POST['job_type2'] ?? '';
$package_type = $_POST['package_type'] ?? '';

// Validation
$errors = [];

if (strlen($nik) !== 16 || !is_numeric($nik)) {
    $errors[] = 'NIK harus 16 digit angka';
}

if (empty($full_name)) {
    $errors[] = 'Nama lengkap harus diisi';
}

// Calculate age
$birthDate = new DateTime($birth_date);
$today = new DateTime();
$age = $today->diff($birthDate)->y;

if ($age < 6 || $age > 65) {
    $errors[] = 'Usia harus antara 6 - 65 tahun';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email tidak valid';
}

if (empty($errors)) {
    // Save to database
    $stmt = $conn->prepare("INSERT INTO bpjs_registrations 
        (nik, full_name, birth_date, phone, email, address, kelurahan, 
         work_location, job_type1, job_type2, package_type, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
    
    $stmt->bind_param("sssssssssss", 
        $nik, $full_name, $birth_date, $phone, $email, $address, 
        $kelurahan, $work_location, $job_type1, $job_type2, $package_type);
    
    if ($stmt->execute()) {
        $registration_id = $conn->insert_id;
        
        // TODO: Integrate with BPJS API here
        
        // For demo, just save to database
        $_SESSION['registration_success'] = true;
        $_SESSION['registration_id'] = $registration_id;
        
        header('Location: bpjs_success.php?id=' . $registration_id);
        exit;
    } else {
        $errors[] = 'Gagal menyimpan data: ' . $conn->error;
    }
}

// If errors, redirect back with error messages
$_SESSION['bpjs_errors'] = $errors;
$_SESSION['form_data'] = $_POST;
header('Location: bpjs.php?error=1');
exit;
?>