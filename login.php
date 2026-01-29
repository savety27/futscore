<?php
session_start();
require_once 'admin/config/database.php'; // Sesuaikan path

// Gunakan koneksi database yang sudah ada
$db = $conn;

$error = '';
$success = '';

// Kode warna sesuai history FutScore
$primary_color = "#0A2463"; // Biru tua
$accent_color = "#FFD700"; // Kuning emas
$text_color = "#FFFFFF";
$light_bg = "#F8F9FA";

// Animasi background particles
$particles_bg = true;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input_username = trim($_POST['username'] ?? '');
    $input_password = trim($_POST['password'] ?? '');
    
    // Validasi input
    if (empty($input_username) || empty($input_password)) {
        $error = "Username/email dan password harus diisi!";
    } else {
        try {
            // Query untuk mencari admin berdasarkan username atau email
            $query = "SELECT id, username, email, password_hash, full_name, role, is_active 
                      FROM admin_users 
                      WHERE (username = :username OR email = :email) 
                      AND is_active = 1 
                      LIMIT 1";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $input_username);
            $stmt->bindParam(':email', $input_username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verifikasi password (case-sensitive)
                if (password_verify($input_password, $admin['password_hash'])) {
                    // Password benar
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_fullname'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $updateQuery = "UPDATE admin_users SET last_login = NOW() WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindParam(':id', $admin['id']);
                    $updateStmt->execute();
                    
                    // Redirect ke dashboard
                    header('Location: admin/dashboard.php');
                    exit();
                } else {
                    // Password salah
                    $error = "Password salah! Pastikan huruf besar/kecil sesuai.";
                }
            } else {
                // Admin tidak ditemukan
                $error = "Username/email tidak ditemukan!";
            }
        } catch(PDOException $e) {
            $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
            error_log("Login Error: " . $e->getMessage());
        }
    }
}

// Cek koneksi database
$db_status = $db ? "connected" : "disconnected";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - FutScore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: <?php echo $primary_color; ?>;
            --secondary: <?php echo $accent_color; ?>;
            --accent: #4CC9F0;
            --success: #2E7D32;
            --danger: #D32F2F;
            --light: <?php echo $light_bg; ?>;
            --dark: #1A1A2E;
            --gray: #6C757D;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow-lg: 0 20px 40px -10px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, #1a365d 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Background Particles Animation */
        .particles {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        .particle:nth-child(1) { width: 15px; height: 15px; top: 10%; left: 10%; animation-delay: 0s; }
        .particle:nth-child(2) { width: 25px; height: 25px; top: 20%; right: 15%; animation-delay: 3s; }
        .particle:nth-child(3) { width: 20px; height: 20px; bottom: 15%; left: 20%; animation-delay: 6s; }
        .particle:nth-child(4) { width: 12px; height: 12px; bottom: 30%; right: 25%; animation-delay: 9s; }
        .particle:nth-child(5) { width: 30px; height: 30px; top: 40%; left: 5%; animation-delay: 12s; }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            25% { transform: translateY(-15px) rotate(90deg); }
            50% { transform: translateY(0) rotate(180deg); }
            75% { transform: translateY(15px) rotate(270deg); }
        }

        /* Floating Balls Animation */
        .floating-balls {
            position: fixed;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .ball {
            position: absolute;
            background: radial-gradient(circle at 30% 30%, var(--secondary), rgba(255, 215, 0, 0.15));
            border-radius: 50%;
            filter: blur(1px);
            animation: bounce 12s infinite ease-in-out;
        }

        .ball:nth-child(1) { width: 40px; height: 40px; top: 15%; left: 5%; animation-delay: 0s; }
        .ball:nth-child(2) { width: 30px; height: 30px; top: 60%; right: 10%; animation-delay: 2s; }
        .ball:nth-child(3) { width: 60px; height: 60px; bottom: 20%; left: 15%; animation-delay: 4s; }

        @keyframes bounce {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.05); }
        }

        /* Main Container */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 1100px;
            display: flex;
            gap: 30px;
            align-items: stretch;
            min-height: auto;
        }

        /* Left Side - Hero Section */
        .login-hero {
            flex: 1;
            color: white;
            padding: 35px 30px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: var(--shadow-lg);
            animation: slideInLeft 0.8s ease-out;
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-height: 650px;
            overflow: hidden;
        }

        .hero-logo {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--secondary) 0%, #FFEC8B 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            border: 4px solid white;
            box-shadow: 0 0 25px rgba(255, 215, 0, 0.3);
            animation: pulse 2s infinite;
        }

        .hero-logo i {
            font-size: 45px;
            color: var(--primary);
        }

        .hero-content h1 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 15px;
            background: linear-gradient(to right, var(--secondary), white);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.2;
        }

        .hero-content p {
            font-size: 16px;
            opacity: 0.9;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 30px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            transition: var(--transition);
        }

        .feature:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
        }

        .feature-icon {
            width: 42px;
            height: 42px;
            background: var(--secondary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 20px;
            flex-shrink: 0;
        }

        .feature-text h4 {
            font-size: 14px;
            margin-bottom: 4px;
            color: var(--secondary);
            font-weight: 600;
        }

        .feature-text p {
            font-size: 12px;
            opacity: 0.8;
            margin: 0;
            line-height: 1.4;
        }

        /* Right Side - Login Form */
        .login-container {
            flex: 0 0 420px;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 25px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: slideInRight 0.8s ease-out;
            display: flex;
            flex-direction: column;
            max-height: 650px;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary) 0%, #1a365d 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 215, 0, 0.08) 0%, transparent 70%);
            animation: rotate 25s linear infinite;
        }

        .login-logo {
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
            color: var(--primary);
            border: 3px solid var(--secondary);
            position: relative;
            z-index: 2;
        }

        .login-header h2 {
            font-size: 26px;
            margin-bottom: 8px;
            color: var(--secondary);
            font-weight: 700;
            position: relative;
            z-index: 2;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 13px;
            position: relative;
            z-index: 2;
        }

        .login-form {
            padding: 30px;
            position: relative;
            flex: 1;
            overflow-y: auto;
        }

        .login-form::-webkit-scrollbar {
            width: 6px;
        }

        .login-form::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .login-form::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }

        .form-group {
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease-out;
        }

        .form-group:nth-child(1) { animation-delay: 0.2s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .input-container {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 16px 20px 16px 45px;
            border: 2px solid #e1e5eb;
            border-radius: 12px;
            font-size: 15px;
            transition: var(--transition);
            background-color: var(--light);
            color: var(--dark);
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
            transform: translateY(-1px);
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 16px;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 16px;
            transition: var(--transition);
            padding: 5px;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .password-notes {
            margin-top: 10px;
            padding: 10px 12px;
            background: rgba(10, 36, 99, 0.04);
            border-radius: 8px;
            font-size: 12px;
            color: var(--gray);
            border-left: 3px solid var(--secondary);
        }

        .password-notes ul {
            list-style: none;
            padding-left: 0;
        }

        .password-notes li {
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .password-notes li i {
            font-size: 11px;
        }

        .caps-warning {
            display: none;
            margin-top: 8px;
            padding: 8px 10px;
            background: rgba(211, 47, 47, 0.08);
            border-radius: 6px;
            color: var(--danger);
            font-size: 12px;
            font-weight: 500;
            animation: shake 0.5s ease;
        }

        .caps-warning.show {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Error/Success Messages */
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.5s ease-out;
        }

        .alert-error {
            background: linear-gradient(135deg, #FFE5E5 0%, #FFCCCC 100%);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .alert-success {
            background: linear-gradient(135deg, #E8F5E9 0%, #C8E6C9 100%);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-icon {
            font-size: 20px;
        }

        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary) 0%, #1a365d 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(10, 36, 99, 0.2);
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .loading-spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            padding: 20px;
            background-color: var(--light);
            color: var(--gray);
            font-size: 12px;
            border-top: 1px solid #e1e5eb;
            flex-shrink: 0;
        }

        .db-status {
            position: fixed;
            bottom: 15px;
            left: 15px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 100;
            backdrop-filter: blur(8px);
            animation: fadeIn 1s ease-out;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: <?php echo $db ? '#4CAF50' : '#f44336'; ?>;
            animation: pulse 2s infinite;
        }

        /* Animations */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-4px); }
            75% { transform: translateX(4px); }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Custom Checkbox */
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 15px;
            cursor: pointer;
        }

        .checkbox {
            width: 18px;
            height: 18px;
            border: 2px solid var(--primary);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .checkbox.checked {
            background: var(--primary);
        }

        .checkbox.checked::after {
            content: '✓';
            color: white;
            font-size: 11px;
            font-weight: bold;
        }

        .remember-text {
            color: var(--gray);
            font-size: 13px;
        }

        /* ========== RESPONSIVE DESIGN ========== */
        /* Tablet Landscape */
        @media (max-width: 1024px) {
            .login-wrapper {
                max-width: 900px;
                gap: 25px;
            }
            
            .login-hero {
                padding: 30px 25px;
            }
            
            .hero-content h1 {
                font-size: 32px;
            }
            
            .login-container {
                flex: 0 0 380px;
            }
        }

        /* Tablet Portrait */
        @media (max-width: 900px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 600px;
                gap: 25px;
                margin: 20px auto;
            }
            
            .login-hero, .login-container {
                width: 100%;
                max-height: none;
            }
            
            .login-hero {
                order: 2;
                margin-top: 0;
            }
            
            .login-container {
                order: 1;
                flex: none;
            }
            
            .features {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .hero-content h1 {
                font-size: 30px;
            }
            
            body {
                padding: 15px;
                align-items: flex-start;
            }
        }

        /* Mobile Landscape */
        @media (max-width: 768px) and (orientation: landscape) {
            body {
                padding: 10px;
            }
            
            .login-wrapper {
                max-width: 95%;
                margin: 10px auto;
            }
            
            .login-hero {
                padding: 20px;
            }
            
            .login-container {
                flex: 0 0 350px;
            }
            
            .hero-content h1 {
                font-size: 26px;
            }
            
            .features {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }

        /* Mobile Portrait */
        @media (max-width: 576px) {
            body {
                padding: 10px;
                min-height: 100vh;
                align-items: flex-start;
                overflow-y: auto;
            }
            
            .login-wrapper {
                flex-direction: column;
                width: 100%;
                max-width: 100%;
                gap: 20px;
                margin: 10px 0;
            }
            
            .login-hero, .login-container {
                width: 100%;
                border-radius: 20px;
                padding: 25px 20px;
            }
            
            .login-header {
                padding: 25px 20px;
            }
            
            .hero-logo {
                width: 80px;
                height: 80px;
                margin-bottom: 20px;
            }
            
            .hero-logo i {
                font-size: 40px;
            }
            
            .hero-content h1 {
                font-size: 28px;
                margin-bottom: 12px;
            }
            
            .hero-content p {
                font-size: 15px;
                line-height: 1.4;
            }
            
            .features {
                grid-template-columns: 1fr;
                gap: 10px;
                margin-top: 20px;
            }
            
            .feature {
                padding: 10px;
            }
            
            .feature-icon {
                width: 36px;
                height: 36px;
                font-size: 18px;
            }
            
            .login-form {
                padding: 25px 20px;
            }
            
            .form-control {
                padding: 14px 15px 14px 40px;
                font-size: 14px;
            }
            
            .btn-login {
                padding: 14px;
                font-size: 15px;
            }
            
            .login-footer {
                padding: 15px;
                font-size: 11px;
            }
            
            .db-status {
                bottom: 10px;
                left: 10px;
                padding: 6px 12px;
                font-size: 10px;
            }
        }

        /* Small Mobile */  
        @media (max-width: 380px) {
            .login-hero, .login-container {
                padding: 20px 15px;
            }
            
            .login-header {
                padding: 20px 15px;
            }
            
            .hero-content h1 {
                font-size: 24px;
            }
            
            .login-logo {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }
            
            .login-header h2 {
                font-size: 22px;
            }
            
            .form-control {
                padding: 12px 12px 12px 35px;
            }
            
            .input-icon {
                left: 12px;
                font-size: 14px;
            }
            
            .toggle-password {
                right: 12px;
                font-size: 14px;
            }
            
            .password-notes {
                font-size: 11px;
                padding: 8px 10px;
            }
        }

        /* Very Small Mobile */
        @media (max-width: 320px) {
            .hero-content h1 {
                font-size: 22px;
            }
            
            .hero-content p {
                font-size: 14px;
            }
            
            .login-header h2 {
                font-size: 20px;
            }
            
            .form-label {
                font-size: 13px;
            }
            
            .btn-login {
                font-size: 14px;
            }
        }

        /* Height-based adjustments */
        @media (max-height: 700px) {
            .login-wrapper {
                margin: 10px auto;
            }
            
            .login-hero, .login-container {
                max-height: 550px;
            }
            
            .hero-logo {
                width: 70px;
                height: 70px;
                margin-bottom: 15px;
            }
            
            .hero-logo i {
                font-size: 35px;
            }
            
            .hero-content h1 {
                font-size: 28px;
                margin-bottom: 10px;
            }
            
            .hero-content p {
                font-size: 14px;
                margin-bottom: 15px;
            }
            
            .features {
                margin-top: 20px;
                gap: 10px;
            }
            
            .feature {
                padding: 10px;
            }
            
            .login-form {
                padding: 25px;
            }
        }

        @media (max-height: 600px) and (orientation: landscape) {
            body {
                padding: 5px;
            }
            
            .login-wrapper {
                flex-direction: row;
                gap: 15px;
            }
            
            .login-hero {
                display: none;
            }
            
            .login-container {
                flex: 1;
                max-height: 95vh;
            }
        }

        /* Print styles */
        @media print {
            .particles, .floating-balls, .db-status {
                display: none;
            }
            
            .login-wrapper {
                box-shadow: none;
            }
            
            .login-container {
                background: white;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <!-- Background Particles -->
    <?php if ($particles_bg): ?>
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <div class="floating-balls">
        <div class="ball"></div>
        <div class="ball"></div>
        <div class="ball"></div>
    </div>
    <?php endif; ?>

    <div class="login-wrapper">
        <!-- Left Side - Hero Section -->
        <div class="login-hero">
            <div class="hero-logo">
                <i class="fas fa-futbol"></i>
            </div>
            <div class="hero-content">
                <h1>FutScore<br>Admin Dashboard</h1>
                <p>Sistem manajemen pertandingan futsal terintegrasi untuk mengelola jadwal, statistik, dan performa tim dengan mudah dan efisien.</p>
                
                <div class="features">
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Jadwal Otomatis</h4>
                            <p>Kelola jadwal pertandingan dengan mudah</p>
                        </div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Analisis Statistik</h4>
                            <p>Pantau performa tim secara real-time</p>
                        </div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Manajemen Tim</h4>
                            <p>Kelola pemain dan staff dengan sistem terpadu</p>
                        </div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Notifikasi Real-time</h4>
                            <p>Update langsung ke perangkat Anda</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo">FS</div>
                <h2>Masuk ke Sistem</h2>
                <p>Akses panel administrasi FutScore</p>
            </div>
            
            <div class="login-form">
                <?php if ($error): ?>
                    <div class="alert alert-error" id="errorMessage">
                        <span class="alert-icon">⚠</span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" id="successMessage">
                        <span class="alert-icon">✓</span>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i>
                            Username atau Email
                        </label>
                        <div class="input-container">
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="form-control" 
                                placeholder="masukkan username atau email"
                                required
                                autofocus
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                autocomplete="username"
                            >
                            <span class="input-icon">
                                <i class="fas fa-user-circle"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <div class="input-container">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-control" 
                                placeholder="masukkan password"
                                required
                                autocomplete="current-password"
                            >
                            <span class="input-icon">
                                <i class="fas fa-key"></i>
                            </span>
                            <button type="button" class="toggle-password" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        
                        <div class="caps-warning" id="capsWarning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Caps Lock aktif!
                        </div>
                        
                        <div class="password-notes">
                            <ul>
                                <li><i class="fas fa-info-circle"></i> Password bersifat case-sensitive</li>
                                <li><i class="fas fa-info-circle"></i> Huruf besar/kecil berpengaruh</li>
                                <li><i class="fas fa-info-circle"></i> Gunakan kombinasi huruf dan angka</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="remember-me" id="rememberMe">
                        <div class="checkbox" id="rememberCheckbox"></div>
                        <span class="remember-text">Ingat saya di perangkat ini</span>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginButton">
                        <span id="buttonText">Masuk ke Dashboard</span>
                        <div class="loading-spinner" id="loadingSpinner"></div>
                        <i class="fas fa-arrow-right" id="arrowIcon"></i>
                    </button>
                </form>
            </div>
            
            
        </div>
    </div>
    
    <!-- Database Status Indicator -->
    <div class="db-status">
        <div class="status-dot"></div>
        <span>Database: <?php echo strtoupper($db_status); ?></span>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const capsWarning = document.getElementById('capsWarning');
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const buttonText = document.getElementById('buttonText');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const arrowIcon = document.getElementById('arrowIcon');
            const rememberMe = document.getElementById('rememberMe');
            const rememberCheckbox = document.getElementById('rememberCheckbox');
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? 
                    '<i class="fas fa-eye"></i>' : 
                    '<i class="fas fa-eye-slash"></i>';
            });
            
            // Caps Lock detection
            passwordInput.addEventListener('keyup', function(e) {
                if (e.getModifierState('CapsLock')) {
                    capsWarning.classList.add('show');
                } else {
                    capsWarning.classList.remove('show');
                }
            });
            
            // Remember me checkbox
            rememberMe.addEventListener('click', function() {
                rememberCheckbox.classList.toggle('checked');
                
                // Save to localStorage
                const username = document.getElementById('username').value;
                if (rememberCheckbox.classList.contains('checked') && username) {
                    localStorage.setItem('rememberedUsername', username);
                } else {
                    localStorage.removeItem('rememberedUsername');
                }
            });
            
            // Load remembered username
            const rememberedUsername = localStorage.getItem('rememberedUsername');
            if (rememberedUsername) {
                document.getElementById('username').value = rememberedUsername;
                rememberCheckbox.classList.add('checked');
            }
            
            // Form submission with animation
            loginForm.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value.trim();
                
                if (!username || !password) {
                    e.preventDefault();
                    showAlert('error', 'Username/email dan password harus diisi!');
                    shakeElement(loginForm);
                    return false;
                }
                
                // Show loading state
                buttonText.style.display = 'none';
                loadingSpinner.style.display = 'block';
                arrowIcon.style.display = 'none';
                loginButton.disabled = true;
                loginButton.style.opacity = '0.8';
                
                return true;
            });
            
            // Input validation on blur
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.style.borderColor = '#f44336';
                        this.style.boxShadow = '0 0 0 3px rgba(244, 67, 54, 0.1)';
                    } else {
                        this.style.borderColor = '#4CAF50';
                        this.style.boxShadow = '0 0 0 3px rgba(76, 175, 80, 0.1)';
                        setTimeout(() => {
                            this.style.borderColor = '';
                            this.style.boxShadow = '';
                        }, 1000);
                    }
                });
                
                input.addEventListener('input', function() {
                    // Clear error when user starts typing
                    const errorAlert = document.getElementById('errorMessage');
                    if (errorAlert) errorAlert.style.display = 'none';
                });
            });
            
            // Add floating animation to form groups on hover
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach(group => {
                group.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                    this.style.transition = 'transform 0.3s ease';
                });
                
                group.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Add ripple effect to login button
            loginButton.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.4);
                    transform: scale(0);
                    animation: ripple-animation 0.6s linear;
                    width: ${size}px;
                    height: ${size}px;
                    top: ${y}px;
                    left: ${x}px;
                    pointer-events: none;
                `;
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
            
            // Auto focus username if empty
            if (!document.getElementById('username').value) {
                document.getElementById('username').focus();
            }
            
            // Handle orientation change
            window.addEventListener('orientationchange', function() {
                // Reset form position
                setTimeout(() => {
                    document.body.scrollTop = 0;
                    document.documentElement.scrollTop = 0;
                }, 100);
            });
            
            // Prevent zoom on mobile
            document.addEventListener('touchstart', function(e) {
                if (e.touches.length > 1) {
                    e.preventDefault();
                }
            }, { passive: false });
            
            // Add CSS for ripple animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple-animation {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Adjust layout for very small screens
            function adjustLayout() {
                const isMobile = window.innerWidth <= 768;
                const isSmallMobile = window.innerWidth <= 380;
                
                if (isMobile) {
                    // Hide some elements on very small screens
                    if (isSmallMobile) {
                        const features = document.querySelector('.features');
                        if (features) {
                            features.style.display = 'none';
                        }
                    }
                }
            }
            
            // Run on load and resize
            adjustLayout();
            window.addEventListener('resize', adjustLayout);
        });
        
        function showAlert(type, message) {
            // Remove existing alerts
            const existingAlert = document.querySelector('.alert');
            if (existingAlert) existingAlert.remove();
            
            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.id = 'dynamicAlert';
            alertDiv.innerHTML = `
                <span class="alert-icon">${type === 'error' ? '⚠' : '✓'}</span>
                <span>${message}</span>
            `;
            
            // Insert before form
            const loginForm = document.getElementById('loginForm');
            loginForm.parentNode.insertBefore(alertDiv, loginForm);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.style.opacity = '0';
                    alertDiv.style.transform = 'translateY(-10px)';
                    alertDiv.style.transition = 'all 0.3s ease';
                    setTimeout(() => alertDiv.remove(), 300);
                }
            }, 5000);
        }
        
        function shakeElement(element) {
            element.style.animation = 'shake 0.5s ease';
            setTimeout(() => {
                element.style.animation = '';
            }, 500);
        }
        
        // Detect Enter key for quick submission
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.target.matches('button')) {
                const submitButton = document.querySelector('.btn-login');
                if (submitButton && !submitButton.disabled) {
                    submitButton.click();
                }
            }
        });
    </script>
</body>
</html>
