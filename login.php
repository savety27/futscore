<?php
session_start();
require_once 'admin/config/database.php'; // Sesuaikan path

// Gunakan koneksi database yang sudah ada
$db = $conn;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input_username = trim($_POST['username'] ?? '');
    // Keep password input as-is: password comparison is case-sensitive
    // and should not ignore leading/trailing spaces.
    $input_password = $_POST['password'] ?? '';
    
    // Validasi input
    if (empty($input_username) || empty($input_password)) {
        $error = "Username/email dan password harus diisi!";
    } else {
        try {
            // Query untuk mencari admin berdasarkan username atau email
            $query = "SELECT id, username, email, password_hash, full_name, role, team_id, is_active 
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
                    $_SESSION['team_id'] = $admin['team_id'] ?? null; // Store team_id
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $updateQuery = "UPDATE admin_users SET last_login = NOW() WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindParam(':id', $admin['id']);
                    $updateStmt->execute();
                    
                    // Redirect based on role
                    if ($admin['role'] === 'pelatih') {
                        header('Location: pelatih/dashboard.php');
                    } else {
                        header('Location: admin/dashboard.php');
                    }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0F172A">
    <meta name="description" content="Alvetrix Admin Dashboard - Sistem manajemen pertandingan futsal">
    <title>Login - Alvetrix</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================
           DESIGN SYSTEM: BLUE THEME WITH ANIMATIONS
           Mobile-First | Blue Theme | Futuristic
           ============================================ */
        
        :root {
            /* Colors - Blue Theme */
            --bg-deep-navy: #0F172A;
            --bg-dark-blue: #1E293B;
            --bg-card-blue: #334155;
            --bg-input-blue: #475569;
            --bg-input-focus: #64748B;
            
            --accent-sky: #38BDF8;
            --accent-teal: #0EA5E9;
            --accent-indigo: #3B82F6;
            --accent-blue-dim: rgba(56, 189, 248, 0.15);
            --accent-blue-glow: rgba(56, 189, 248, 0.4);
            
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
            --text-muted: #94A3B8;
            
            --error-red: #EF4444;
            --error-bg: rgba(239, 68, 68, 0.1);
            --success-green: #10B981;
            --success-bg: rgba(16, 185, 129, 0.1);
            
            /* Typography */
            --font-display: 'Bebas Neue', Impact, sans-serif;
            --font-body: 'Plus Jakarta Sans', system-ui, sans-serif;
            
            /* Spacing */
            --space-xs: 0.25rem;
            --space-sm: 0.5rem;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --space-xl: 2rem;
            --space-2xl: 3rem;
            
            /* Borders */
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --radius-full: 9999px;
            
            /* Transitions */
            --ease-out: cubic-bezier(0.4, 0, 0.2, 1);
            --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
            --ease-bounce: cubic-bezier(0.68, -0.55, 0.265, 1.55);
            
            /* Shadows */
            --shadow-glow: 0 0 30px var(--accent-blue-glow);
            --shadow-card: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            --shadow-deep: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        /* ============================================
           RESET & BASE
           ============================================ */
        
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            font-size: 16px;
            -webkit-text-size-adjust: 100%;
            scroll-behavior: smooth;
        }
        
        body {
            font-family: var(--font-body);
            background: var(--bg-deep-navy);
            color: var(--text-primary);
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            animation: body-appear 0.8s var(--ease-out);
        }

        /* Prevent blank page when returning via back/forward cache */
        body.no-anim .login-main,
        body.no-anim .login-card,
        body.no-anim .brand,
        body.no-anim .form-container,
        body.no-anim .btn-submit {
            animation: none !important;
            opacity: 1 !important;
            transform: none !important;
        }
        
        @keyframes body-appear {
            from {
                opacity: 0;
                background: #000;
            }
            to {
                opacity: 1;
            }
        }
        
        /* ============================================
           ATMOSPHERIC BACKGROUND - BLUE THEME
           ============================================ */
        
        .stadium-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            animation: bg-pulse 20s ease-in-out infinite;
        }
        
        @keyframes bg-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.9; }
        }
        
        /* Animated gradient background */
        .stadium-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(56, 189, 248, 0.15), transparent 40%),
                radial-gradient(circle at 80% 80%, rgba(59, 130, 246, 0.1), transparent 40%),
                linear-gradient(135deg, 
                    rgba(15, 23, 42, 1) 0%,
                    rgba(30, 41, 59, 1) 50%,
                    rgba(15, 23, 42, 1) 100%);
            animation: gradient-shift 15s ease infinite;
            background-size: 200% 200%;
        }
        
        @keyframes gradient-shift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Floating particles */
        .particles {
            position: absolute;
            inset: 0;
            opacity: 0.6;
        }
        
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--accent-sky);
            border-radius: 50%;
            animation: float-particle 20s linear infinite;
            filter: blur(0.5px);
        }
        
        .particle:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
            animation-duration: 25s;
        }
        
        .particle:nth-child(2) {
            top: 60%;
            left: 80%;
            animation-delay: 2s;
            animation-duration: 20s;
        }
        
        .particle:nth-child(3) {
            top: 40%;
            left: 30%;
            animation-delay: 4s;
            animation-duration: 30s;
        }
        
        .particle:nth-child(4) {
            top: 80%;
            left: 60%;
            animation-delay: 6s;
            animation-duration: 22s;
        }
        
        .particle:nth-child(5) {
            top: 30%;
            left: 90%;
            animation-delay: 8s;
            animation-duration: 28s;
        }
        
        @keyframes float-particle {
            0% {
                transform: translateY(0) translateX(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) translateX(100px);
                opacity: 0;
            }
        }
        
        /* Animated grid lines */
        .grid-lines {
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(56, 189, 248, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(56, 189, 248, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: grid-move 20s linear infinite;
            mask-image: radial-gradient(circle at center, black 30%, transparent 70%);
        }
        
        @keyframes grid-move {
            0% { transform: translateY(0) translateX(0); }
            100% { transform: translateY(50px) translateX(50px); }
        }
        
        /* Wave animation */
        .wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100px;
            background: linear-gradient(90deg, transparent, rgba(56, 189, 248, 0.1), transparent);
            animation: wave-animation 10s linear infinite;
            opacity: 0.3;
        }
        
        @keyframes wave-animation {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Noise texture overlay */
        .noise-overlay {
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.03;
            pointer-events: none;
            animation: noise-animation 0.2s steps(1) infinite;
        }
        
        @keyframes noise-animation {
            0%, 100% { opacity: 0.03; }
            50% { opacity: 0.05; }
        }
        
        /* ============================================
           MAIN LAYOUT
           ============================================ */
        
        .login-main {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: var(--space-lg);
            padding-top: env(safe-area-inset-top, var(--space-lg));
            padding-bottom: env(safe-area-inset-bottom, var(--space-lg));
            animation: main-appear 0.6s var(--ease-out) 0.2s both;
        }
        
        @keyframes main-appear {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ============================================
           LOGIN CARD - MOBILE FIRST
           ============================================ */
        
        .login-card {
            width: 100%;
            flex: 1;
            display: flex;
            flex-direction: column;
            animation: card-rise 0.8s var(--ease-bounce) 0.3s forwards;
            opacity: 0;
            transform-style: preserve-3d;
            perspective: 1000px;
        }
        
        @keyframes card-rise {
            0% {
                opacity: 0;
                transform: translateY(40px) rotateX(10deg);
            }
            100% {
                opacity: 1;
                transform: translateY(0) rotateX(0);
            }
        }
        
        /* ============================================
           BRAND HEADER
           ============================================ */
        
        .brand {
            text-align: center;
            padding: var(--space-xl) 0;
            animation: brand-reveal 0.6s var(--ease-out) 0.4s forwards;
            opacity: 0;
        }
        
        @keyframes brand-reveal {
            0% {
                opacity: 0;
                transform: translateY(-20px) scale(0.9);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .logo-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--bg-card-blue), var(--bg-dark-blue));
            border: 3px solid var(--accent-teal);
            border-radius: var(--radius-lg);
            font-family: var(--font-display);
            font-size: 2.2rem;
            color: var(--accent-sky);
            margin-bottom: var(--space-md);
            position: relative;
            box-shadow: 
                0 0 30px var(--accent-blue-glow),
                inset 0 0 20px rgba(255, 255, 255, 0.1);
            animation: 
                logo-pulse 3s ease-in-out infinite,
                logo-rotate 20s linear infinite;
            transform-style: preserve-3d;
        }
        
        .logo-mark::before {
            content: '';
            position: absolute;
            inset: -3px;
            background: linear-gradient(45deg, transparent, var(--accent-sky), transparent);
            border-radius: inherit;
            animation: logo-glow 2s ease-in-out infinite alternate;
            z-index: -1;
        }
        
        @keyframes logo-pulse {
            0%, 100% { 
                transform: scale(1) rotate(0deg);
                box-shadow: 0 0 30px var(--accent-blue-glow);
            }
            50% { 
                transform: scale(1.05) rotate(180deg);
                box-shadow: 0 0 50px var(--accent-blue-glow), 0 0 80px rgba(56, 189, 248, 0.3);
            }
        }
        
        @keyframes logo-rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @keyframes logo-glow {
            from { opacity: 0.3; }
            to { opacity: 0.8; }
        }
        
        .brand h1 {
            font-family: var(--font-display);
            font-size: 2.8rem;
            letter-spacing: 0.1em;
            background: linear-gradient(to right, var(--accent-sky), var(--accent-indigo));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: var(--space-xs);
            animation: title-shimmer 3s ease-in-out infinite alternate;
        }
        
        @keyframes title-shimmer {
            0% {
                background-position: -200%;
            }
            100% {
                background-position: 200%;
            }
        }
        
        .brand .tagline {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.2em;
            position: relative;
            display: inline-block;
            padding: 0 var(--space-md);
        }
        
        .brand .tagline::before,
        .brand .tagline::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent-sky));
            animation: tagline-line 2s ease-in-out infinite;
        }
        
        .brand .tagline::before {
            right: 100%;
            animation-delay: 0.5s;
        }
        
        .brand .tagline::after {
            left: 100%;
        }
        
        @keyframes tagline-line {
            0%, 100% { width: 0; opacity: 0; }
            50% { width: 30px; opacity: 1; }
        }
        
        /* ============================================
           FORM CONTAINER
           ============================================ */
        
        .form-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            animation: form-slide 0.8s var(--ease-out) 0.5s both;
        }
        
        @keyframes form-slide {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .login-form {
            display: flex;
            flex-direction: column;
            gap: var(--space-lg);
        }
        
        /* ============================================
           ALERTS
           ============================================ */
        
        .alert {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            animation: 
                alert-shake 0.5s var(--ease-spring),
                alert-fade-in 0.3s var(--ease-out);
            border-left: 4px solid;
            backdrop-filter: blur(10px);
            background: rgba(30, 41, 59, 0.8);
        }
        
        @keyframes alert-fade-in {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes alert-shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-8px); }
            40% { transform: translateX(8px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }
        
        .alert-error {
            border-left-color: var(--error-red);
            color: var(--error-red);
            background: rgba(239, 68, 68, 0.1);
        }
        
        .alert-success {
            border-left-color: var(--success-green);
            color: var(--success-green);
            background: rgba(16, 185, 129, 0.1);
        }
        
        .alert-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            animation: icon-bounce 0.6s var(--ease-bounce);
        }
        
        @keyframes icon-bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        
        /* ============================================
           FORM GROUPS
           ============================================ */
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
            animation: field-reveal 0.5s var(--ease-out) forwards;
            opacity: 0;
            transform-origin: center top;
        }
        
        .form-group:nth-child(1) { animation-delay: 0.6s; }
        .form-group:nth-child(2) { animation-delay: 0.7s; }
        .form-group:nth-child(3) { animation-delay: 0.8s; }
        
        @keyframes field-reveal {
            0% {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .form-label {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--accent-sky);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding-left: var(--space-sm);
            position: relative;
        }
        
        .form-label::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 4px;
            height: 12px;
            background: var(--accent-teal);
            border-radius: 2px;
            transform: translateY(-50%);
            animation: label-pulse 2s ease-in-out infinite;
        }
        
        @keyframes label-pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }
        
        .form-label i {
            color: var(--accent-teal);
            font-size: 0.75rem;
            animation: icon-float 3s ease-in-out infinite;
        }
        
        @keyframes icon-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-2px); }
        }
        
        /* ============================================
           INPUT FIELDS
           ============================================ */
        
        .input-wrapper {
            position: relative;
            animation: input-wrapper-glow 3s ease-in-out infinite alternate;
        }
        
        @keyframes input-wrapper-glow {
            from {
                filter: drop-shadow(0 0 5px rgba(56, 189, 248, 0.3));
            }
            to {
                filter: drop-shadow(0 0 10px rgba(56, 189, 248, 0.5));
            }
        }
        
        .form-input {
            width: 100%;
            padding: var(--space-md) var(--space-lg);
            padding-left: 3.5rem;
            background: rgba(71, 85, 105, 0.3);
            backdrop-filter: blur(10px);
            border: 2px solid transparent;
            border-radius: var(--radius-md);
            font-family: var(--font-body);
            font-size: 1rem;
            color: var(--text-primary);
            transition: all 0.3s var(--ease-out);
            -webkit-appearance: none;
            appearance: none;
            animation: input-shimmer 3s ease-in-out infinite;
            background-image: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.05),
                transparent
            );
            background-size: 200% 100%;
        }
        
        @keyframes input-shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .form-input::placeholder {
            color: var(--text-muted);
            animation: placeholder-pulse 2s ease-in-out infinite;
        }
        
        @keyframes placeholder-pulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }
        
        .form-input:focus {
            outline: none;
            background: rgba(100, 116, 139, 0.4);
            border-color: var(--accent-sky);
            box-shadow: 
                0 0 0 4px var(--accent-blue-dim),
                0 0 30px rgba(56, 189, 248, 0.3);
            animation: input-focus-pulse 0.5s var(--ease-out);
            transform: translateY(-1px);
        }
        
        @keyframes input-focus-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .input-icon {
            position: absolute;
            left: var(--space-lg);
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent-sky);
            font-size: 1.1rem;
            transition: all 0.3s var(--ease-out);
            pointer-events: none;
            animation: icon-glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes icon-glow {
            from {
                text-shadow: 0 0 5px var(--accent-blue-glow);
            }
            to {
                text-shadow: 0 0 10px var(--accent-blue-glow);
            }
        }
        
        .form-input:focus ~ .input-icon {
            color: var(--accent-teal);
            animation: icon-spin 0.5s var(--ease-out);
        }
        
        @keyframes icon-spin {
            from { transform: translateY(-50%) rotate(0); }
            to { transform: translateY(-50%) rotate(360deg); }
        }
        
        .toggle-password {
            position: absolute;
            right: var(--space-md);
            top: 50%;
            transform: translateY(-50%);
            background: rgba(56, 189, 248, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.2);
            color: var(--accent-sky);
            font-size: 1rem;
            cursor: pointer;
            padding: var(--space-sm);
            border-radius: var(--radius-sm);
            transition: all 0.3s var(--ease-out);
            -webkit-tap-highlight-color: transparent;
            animation: button-float 2s ease-in-out infinite;
        }
        
        @keyframes button-float {
            0%, 100% { transform: translateY(-50%); }
            50% { transform: translateY(-60%); }
        }
        
        .toggle-password:hover,
        .toggle-password:focus {
            background: var(--accent-sky);
            color: var(--bg-dark-blue);
            box-shadow: 0 0 20px rgba(56, 189, 248, 0.5);
            animation: none;
        }
        
        /* ============================================
           PASSWORD NOTES & CAPS WARNING
           ============================================ */
        
        .password-notes {
            padding: var(--space-sm) var(--space-md);
            background: rgba(51, 65, 85, 0.5);
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--accent-teal);
            animation: notes-appear 0.6s var(--ease-out) 0.9s both;
            backdrop-filter: blur(5px);
        }
        
        @keyframes notes-appear {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .password-notes ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }
        
        .password-notes li {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: 0.75rem;
            color: var(--text-secondary);
            animation: list-item-appear 0.3s var(--ease-out) forwards;
            opacity: 0;
        }
        
        .password-notes li:nth-child(1) { animation-delay: 1s; }
        .password-notes li:nth-child(2) { animation-delay: 1.1s; }
        
        @keyframes list-item-appear {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .password-notes li i {
            color: var(--accent-sky);
            font-size: 0.625rem;
            animation: check-spin 1s ease-in-out infinite;
        }
        
        @keyframes check-spin {
            0%, 100% { transform: rotate(0); }
            50% { transform: rotate(10deg); }
        }
        
        .caps-warning {
            display: none;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            background: rgba(239, 68, 68, 0.15);
            border-radius: var(--radius-sm);
            color: var(--error-red);
            font-size: 0.8125rem;
            font-weight: 500;
            animation: 
                caps-warn-shake 0.4s var(--ease-spring),
                caps-warn-pulse 1s ease-in-out infinite alternate;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        @keyframes caps-warn-pulse {
            from {
                box-shadow: 0 0 10px rgba(239, 68, 68, 0.3);
            }
            to {
                box-shadow: 0 0 20px rgba(239, 68, 68, 0.5);
            }
        }
        
        @keyframes caps-warn-shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .caps-warning.show {
            display: flex;
            animation: 
                caps-warn-in 0.3s var(--ease-out),
                caps-warn-pulse 1s ease-in-out infinite alternate;
        }
        
        @keyframes caps-warn-in {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        /* ============================================
           REMEMBER ME
           ============================================ */
        
        .remember-row {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
            padding: var(--space-sm);
            border-radius: var(--radius-sm);
            background: rgba(71, 85, 105, 0.2);
            transition: all 0.3s var(--ease-out);
            animation: remember-appear 0.5s var(--ease-out) 1s both;
        }
        
        @keyframes remember-appear {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .remember-row:hover {
            background: rgba(71, 85, 105, 0.4);
            transform: translateX(5px);
        }
        
        .checkbox-custom {
            width: 22px;
            height: 22px;
            border: 2px solid var(--accent-sky);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s var(--ease-out);
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }
        
        .checkbox-custom::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(45deg, transparent, var(--accent-sky), transparent);
            animation: checkbox-shimmer 2s linear infinite;
            opacity: 0;
        }
        
        @keyframes checkbox-shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .checkbox-custom::after {
            content: '';
            width: 12px;
            height: 12px;
            background: var(--accent-teal);
            border-radius: 2px;
            transform: scale(0);
            transition: transform 0.3s var(--ease-bounce);
            z-index: 1;
        }
        
        .remember-row.checked .checkbox-custom {
            border-color: var(--accent-teal);
            box-shadow: 0 0 15px rgba(56, 189, 248, 0.5);
        }
        
        .remember-row.checked .checkbox-custom::before {
            opacity: 0.3;
        }
        
        .remember-row.checked .checkbox-custom::after {
            transform: scale(1);
            animation: check-bounce 0.5s var(--ease-bounce);
        }
        
        @keyframes check-bounce {
            0% { transform: scale(0); }
            60% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .remember-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
            transition: color 0.3s var(--ease-out);
        }
        
        .remember-row.checked .remember-text {
            color: var(--accent-sky);
        }
        
        /* ============================================
           SUBMIT BUTTON
           ============================================ */
        
        .btn-submit {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            width: 100%;
            padding: var(--space-lg) var(--space-xl);
            background: linear-gradient(135deg, var(--accent-teal), var(--accent-indigo));
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            font-family: var(--font-display);
            font-size: 1.4rem;
            letter-spacing: 0.15em;
            cursor: pointer;
            overflow: hidden;
            transition: all 0.3s var(--ease-out);
            -webkit-tap-highlight-color: transparent;
            min-height: 60px;
            animation: none;
            opacity: 1;
            transform-style: preserve-3d;
            perspective: 1000px;
        }
        
        @keyframes btn-reveal {
            0% {
                opacity: 0;
                transform: translateY(30px) rotateX(20deg);
            }
            100% {
                opacity: 1;
                transform: translateY(0) rotateX(0);
            }
        }
        
        @keyframes btn-glow {
            from {
                box-shadow: 
                    0 10px 30px rgba(56, 189, 248, 0.4),
                    0 0 30px rgba(56, 189, 248, 0.2);
            }
            to {
                box-shadow: 
                    0 15px 40px rgba(56, 189, 248, 0.6),
                    0 0 50px rgba(56, 189, 248, 0.4);
            }
        }
        
        .btn-submit:hover:not(:disabled) {
            transform: none;
            box-shadow: none;
            animation: none;
        }
        
        @keyframes btn-hover-pulse {
            0%, 100% { transform: translateY(-3px) scale(1.02); }
            50% { transform: translateY(-3px) scale(1.05); }
        }
        
        .btn-submit:active:not(:disabled) {
            transform: none;
            transition: none;
        }
        
        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            animation: none;
            transform: none !important;
        }
        
        .btn-submit::before {
            display: none;
        }
        
        .btn-submit:hover::before {
            left: 100%;
            animation: btn-shimmer 0.7s;
        }
        
        @keyframes btn-shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .btn-submit::after {
            display: none;
        }
        
        .btn-submit:hover::after {
            animation: none;
        }
        
        @keyframes btn-sparkle {
            0% { background-position: 0% 0%; }
            100% { background-position: 100% 100%; }
        }
        
        .btn-text {
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            animation: text-shimmer 3s ease-in-out infinite alternate;
        }
        
        @keyframes text-shimmer {
            from {
                text-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
            }
            to {
                text-shadow: 0 0 10px rgba(255, 255, 255, 0.8);
            }
        }
        
        .btn-icon {
            position: relative;
            z-index: 1;
            transition: none;
            animation: none;
        }
        
        @keyframes icon-move {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(5px); }
        }
        
        .btn-submit:hover .btn-icon {
            transform: none;
            animation: none;
        }
        
        @keyframes icon-dash {
            0% { transform: translateX(0); }
            50% { transform: translateX(15px); }
            100% { transform: translateX(8px); }
        }
        
        .loading-spinner {
            display: none;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            position: relative;
        }
        
        .loading-spinner::before {
            content: '';
            position: absolute;
            inset: -5px;
            border: 2px solid transparent;
            border-top-color: rgba(56, 189, 248, 0.5);
            border-radius: 50%;
            animation: spin 1s linear infinite reverse;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn-submit.loading .btn-text,
        .btn-submit.loading .btn-icon {
            opacity: 0.6;
        }
        
        .btn-submit.loading .loading-spinner {
            display: block;
            animation: 
                spin 0.8s linear infinite,
                spinner-glow 1s ease-in-out infinite alternate;
        }
        
        @keyframes spinner-glow {
            from {
                box-shadow: 0 0 10px rgba(56, 189, 248, 0.5);
            }
            to {
                box-shadow: 0 0 20px rgba(56, 189, 248, 0.8);
            }
        }
        
        /* ============================================
           FOOTER
           ============================================ */
        
        .card-footer {
            text-align: center;
            padding: var(--space-xl) 0 var(--space-md);
            animation: footer-reveal 0.5s var(--ease-out) 1.2s forwards;
            opacity: 0;
        }
        
        @keyframes footer-reveal {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .db-indicator {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-lg);
            background: rgba(30, 41, 59, 0.8);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(56, 189, 248, 0.2);
            animation: indicator-pulse 2s ease-in-out infinite;
        }
        
        @keyframes indicator-pulse {
            0%, 100% { 
                transform: scale(1);
                box-shadow: 0 0 10px rgba(56, 189, 248, 0.2);
            }
            50% { 
                transform: scale(1.05);
                box-shadow: 0 0 20px rgba(56, 189, 248, 0.4);
            }
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: dot-pulse 2s infinite;
            position: relative;
        }
        
        .status-dot::before {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            animation: dot-ring 2s infinite;
        }
        
        .status-dot.connected {
            background: var(--success-green);
            box-shadow: 0 0 10px var(--success-green);
        }
        
        .status-dot.connected::before {
            border: 1px solid var(--success-green);
        }
        
        .status-dot.disconnected {
            background: var(--error-red);
            box-shadow: 0 0 10px var(--error-red);
        }
        
        .status-dot.disconnected::before {
            border: 1px solid var(--error-red);
        }
        
        @keyframes dot-pulse {
            0%, 100% { 
                opacity: 1;
                transform: scale(1);
            }
            50% { 
                opacity: 0.7;
                transform: scale(1.2);
            }
        }
        
        @keyframes dot-ring {
            0% {
                opacity: 0;
                transform: scale(1);
            }
            50% {
                opacity: 1;
            }
            100% {
                opacity: 0;
                transform: scale(1.5);
            }
        }
        
        .copyright {
            margin-top: var(--space-md);
            font-size: 0.75rem;
            color: var(--text-muted);
            animation: copyright-fade 3s ease-in-out infinite alternate;
        }
        
        @keyframes copyright-fade {
            from { opacity: 0.5; }
            to { opacity: 1; }
        }
        
        /* ============================================
           TABLET BREAKPOINT (768px+)
           ============================================ */
        
        @media (min-width: 768px) {
            .login-main {
                justify-content: center;
                align-items: center;
                padding: var(--space-xl);
                animation: main-appear-tablet 0.8s var(--ease-out) 0.2s both;
            }
            
            @keyframes main-appear-tablet {
                from {
                    opacity: 0;
                    transform: scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            .login-card {
                max-width: 460px;
                flex: 0 1 auto;
                background: rgba(30, 41, 59, 0.7);
                backdrop-filter: blur(20px);
                border-radius: var(--radius-xl);
                padding: var(--space-2xl);
                box-shadow: 
                    var(--shadow-deep),
                    0 0 0 1px rgba(56, 189, 248, 0.1);
                border: 1px solid rgba(56, 189, 248, 0.1);
                animation: card-rise-tablet 0.8s var(--ease-bounce) 0.3s forwards;
            }
            
            @keyframes card-rise-tablet {
                0% {
                    opacity: 0;
                    transform: translateY(50px) rotateX(15deg);
                }
                100% {
                    opacity: 1;
                    transform: translateY(0) rotateX(0);
                }
            }
            
            .brand {
                padding-top: 0;
            }
            
            .logo-mark {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
            
            .brand h1 {
                font-size: 3.2rem;
            }
            
            .form-input {
                padding: 1.2rem var(--space-lg);
                padding-left: 3.5rem;
            }
        }
        
        /* ============================================
           DESKTOP BREAKPOINT (1024px+)
           ============================================ */
        
        @media (min-width: 1024px) {
            .login-card {
                max-width: 480px;
                padding: var(--space-2xl) 3.5rem;
                animation: card-rise-desktop 1s var(--ease-bounce) 0.3s forwards;
            }
            
            @keyframes card-rise-desktop {
                0% {
                    opacity: 0;
                    transform: 
                        translateY(60px) 
                        rotateX(20deg) 
                        scale(0.95);
                }
                60% {
                    opacity: 1;
                    transform: 
                        translateY(-20px) 
                        rotateX(-5deg) 
                        scale(1.02);
                }
                100% {
                    opacity: 1;
                    transform: 
                        translateY(0) 
                        rotateX(0) 
                        scale(1);
                }
            }
            
            .brand h1 {
                font-size: 3.8rem;
            }
            
            .btn-submit {
                font-size: 1.5rem;
                min-height: 65px;
                animation: none;
            }
            
            @keyframes btn-glow-desktop {
                from {
                    box-shadow: 
                        0 15px 40px rgba(56, 189, 248, 0.5),
                        0 0 40px rgba(56, 189, 248, 0.3),
                        inset 0 0 20px rgba(255, 255, 255, 0.1);
                }
                to {
                    box-shadow: 
                        0 20px 50px rgba(56, 189, 248, 0.7),
                        0 0 60px rgba(56, 189, 248, 0.5),
                        inset 0 0 30px rgba(255, 255, 255, 0.2);
                }
            }
        }
        
        /* ============================================
           LARGE DESKTOP (1440px+)
           ============================================ */
        
        @media (min-width: 1440px) {
            .stadium-bg::before {
                background-size: 300% 300%;
                animation: gradient-shift-large 20s ease infinite;
            }
            
            @keyframes gradient-shift-large {
                0% { background-position: 0% 0%; }
                50% { background-position: 100% 100%; }
                100% { background-position: 0% 0%; }
            }
            
            .particles {
                opacity: 0.8;
            }
            
            .grid-lines {
                background-size: 80px 80px;
            }
        }
        
        /* ============================================
           REDUCED MOTION
           ============================================ */
        
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                animation-delay: 0ms !important;
            }
            
            .login-card {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
            
            .btn-submit:hover:not(:disabled) {
                transform: none !important;
            }
        }
        
        /* ============================================
           PRINT STYLES
           ============================================ */
        
        @media print {
            .stadium-bg, 
            .db-indicator,
            .particles,
            .grid-lines,
            .wave {
                display: none;
            }
            
            body {
                background: white;
                color: black;
            }
            
            .login-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body class="login-body">
    <!-- Atmospheric Background -->
    <div class="stadium-bg" aria-hidden="true">
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
        <div class="grid-lines"></div>
        <div class="wave"></div>
        <div class="noise-overlay"></div>
    </div>
    
    <!-- Main Content -->
    <main class="login-main">
        <div class="login-card">
            <!-- Brand Header -->
            <header class="brand">
                <div class="logo-mark">
                    AVX
                </div>
                <h1>Login</h1>
                <p class="tagline">Secure Access Portal</p>
            </header>
            
            <!-- Form Container -->
            <div class="form-container">
                <!-- Alerts -->
                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert" id="errorAlert">
                        <span class="alert-icon"><i class="fas fa-exclamation-circle"></i></span>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert" id="successAlert">
                        <span class="alert-icon"><i class="fas fa-check-circle"></i></span>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" action="" class="login-form" id="loginForm" novalidate>
                    <!-- Username Field -->
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i>
                            Username atau Email
                        </label>
                        <div class="input-wrapper">
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="form-input" 
                                placeholder="Masukkan username atau email"
                                required
                                autocomplete="username"
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            >
                            <span class="input-icon" aria-hidden="true">
                                <i class="fas fa-at"></i>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Password Field -->
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <div class="input-wrapper">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="form-input" 
                                placeholder="Masukkan password"
                                required
                                autocomplete="current-password"
                            >
                            <span class="input-icon" aria-hidden="true">
                                <i class="fas fa-key"></i>
                            </span>
                            <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        
                        <div class="caps-warning" id="capsWarning" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Caps Lock aktif!</span>
                        </div>
                        
                        <div class="password-notes">
                            <ul>
                                <li><i class="fas fa-circle"></i> Password bersifat case-sensitive</li>
                                <li><i class="fas fa-circle"></i> Huruf besar/kecil berpengaruh</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Remember Me -->
                    <div class="form-group">
                        <div class="remember-row" id="rememberMe" role="checkbox" aria-checked="false" tabindex="0">
                            <div class="checkbox-custom"></div>
                            <span class="remember-text">Ingat saya di perangkat ini</span>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="btn-text">MASUK</span>
                        <i class="fas fa-arrow-right btn-icon"></i>
                        <div class="loading-spinner"></div>
                    </button>
                </form>
            </div>
            
            <!-- Footer -->
            <footer class="card-footer">
                <div class="db-indicator">
                    <span class="status-dot <?php echo $db_status; ?>"></span>
                    <span>Database <?php echo strtoupper($db_status); ?></span>
                </div>
                <p class="copyright">&copy; <?php echo date('Y'); ?> ALVETRIX. Semua hak dilindungi.</p>
            </footer>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const capsWarning = document.getElementById('capsWarning');
            const rememberMe = document.getElementById('rememberMe');
            const usernameInput = document.getElementById('username');
            
            // Add ripple effect to all buttons
            function createRipple(event) {
                const button = event.currentTarget;
                const circle = document.createElement("span");
                const diameter = Math.max(button.clientWidth, button.clientHeight);
                const radius = diameter / 2;
                
                circle.style.width = circle.style.height = `${diameter}px`;
                circle.style.left = `${event.clientX - button.getBoundingClientRect().left - radius}px`;
                circle.style.top = `${event.clientY - button.getBoundingClientRect().top - radius}px`;
                circle.classList.add("ripple");
                
                const ripple = button.getElementsByClassName("ripple")[0];
                if (ripple) ripple.remove();
                
                button.appendChild(circle);
            }
            
            // Add ripple effect to buttons
            const buttons = document.querySelectorAll('.toggle-password, .btn-submit');
            buttons.forEach(button => {
                button.addEventListener('click', createRipple);
            });
            
            // Toggle password visibility with animation
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                
                // Animate the icon
                const icon = this.querySelector('i');
                icon.style.animation = 'none';
                void icon.offsetWidth; // Trigger reflow
                
                if (type === 'password') {
                    icon.className = 'fas fa-eye';
                    icon.style.animation = 'icon-bounce 0.5s var(--ease-bounce)';
                } else {
                    icon.className = 'fas fa-eye-slash';
                    icon.style.animation = 'icon-spin 0.5s var(--ease-out)';
                }
                
                // Add animation to input
                passwordInput.style.animation = 'none';
                void passwordInput.offsetWidth;
                passwordInput.style.animation = 'input-focus-pulse 0.5s var(--ease-out)';
            });
            
            // Caps Lock detection with enhanced animation
            passwordInput.addEventListener('keyup', function(e) {
                const isCapsOn = e.getModifierState('CapsLock');
                capsWarning.classList.toggle('show', isCapsOn);
                
                if (isCapsOn) {
                    passwordInput.style.borderColor = 'var(--error-red)';
                    passwordInput.style.boxShadow = '0 0 20px rgba(239, 68, 68, 0.3)';
                } else {
                    passwordInput.style.borderColor = '';
                    passwordInput.style.boxShadow = '';
                }
            });
            
            passwordInput.addEventListener('keydown', function(e) {
                const isCapsOn = e.getModifierState('CapsLock');
                capsWarning.classList.toggle('show', isCapsOn);
            });
            
            // Remember me toggle with animation
            function toggleRemember() {
                const isChecked = rememberMe.classList.toggle('checked');
                rememberMe.setAttribute('aria-checked', isChecked);
                
                // Add animation
                rememberMe.style.animation = 'none';
                void rememberMe.offsetWidth;
                rememberMe.style.animation = 'checkbox-bounce 0.5s var(--ease-bounce)';
                
                if (isChecked && usernameInput.value) {
                    localStorage.setItem('mgp_remembered_user', usernameInput.value);
                } else {
                    localStorage.removeItem('mgp_remembered_user');
                }
            }
            
            rememberMe.addEventListener('click', function(e) {
                e.preventDefault();
                toggleRemember();
            });
            
            rememberMe.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleRemember();
                }
            });
            
            // Load remembered username
            const rememberedUser = localStorage.getItem('mgp_remembered_user');
            if (rememberedUser) {
                usernameInput.value = rememberedUser;
                rememberMe.classList.add('checked');
                rememberMe.setAttribute('aria-checked', 'true');
                
                // Animate the input
                usernameInput.style.animation = 'none';
                void usernameInput.offsetWidth;
                usernameInput.style.animation = 'input-focus-pulse 0.8s var(--ease-out)';
            }
            
            // Form submission with enhanced animations
            loginForm.addEventListener('submit', function(e) {
                const username = usernameInput.value.trim();
                const password = passwordInput.value.trim();
                
                if (!username || !password) {
                    e.preventDefault();
                    
                    // Animate empty fields
                    if (!username) {
                        usernameInput.style.animation = 'none';
                        void usernameInput.offsetWidth;
                        usernameInput.style.animation = 'alert-shake 0.5s var(--ease-spring)';
                    }
                    
                    if (!password) {
                        passwordInput.style.animation = 'none';
                        void passwordInput.offsetWidth;
                        passwordInput.style.animation = 'alert-shake 0.5s var(--ease-spring)';
                    }
                    
                    showAlert('error', 'Username/email dan password harus diisi!');
                    return false;
                }
                
                // Show loading state with animation
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                // Add loading animation to the card
                const loginCard = document.querySelector('.login-card');
                loginCard.style.animation = 'none';
                void loginCard.offsetWidth;
                loginCard.style.animation = 'card-rise 0.3s var(--ease-out)';
                
                // Update remember me storage
                if (rememberMe.classList.contains('checked')) {
                    localStorage.setItem('mgp_remembered_user', username);
                }
                
                return true;
            });

            // Reset loading state when navigating back to this page (bfcache)
            window.addEventListener('pageshow', function(e) {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;

                if (e.persisted) {
                    document.body.classList.add('no-anim');
                }
            });
            
            // Dynamic alert function with animation
            function showAlert(type, message) {
                // Remove existing dynamic alerts
                const existingAlert = document.getElementById('dynamicAlert');
                if (existingAlert) {
                    existingAlert.style.animation = 'alert-fade-out 0.3s var(--ease-out)';
                    setTimeout(() => existingAlert.remove(), 300);
                }
                
                const alertDiv = document.createElement('div');
                alertDiv.id = 'dynamicAlert';
                alertDiv.className = `alert alert-${type}`;
                alertDiv.setAttribute('role', 'alert');
                alertDiv.innerHTML = `
                    <span class="alert-icon">
                        <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                    </span>
                    <span>${message}</span>
                `;
                
                loginForm.insertAdjacentElement('beforebegin', alertDiv);
                
                // Animate in
                alertDiv.style.animation = 'none';
                void alertDiv.offsetWidth;
                alertDiv.style.animation = 'alert-fade-in 0.3s var(--ease-out), alert-shake 0.5s var(--ease-spring)';
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    alertDiv.style.animation = 'alert-fade-out 0.3s var(--ease-out)';
                    setTimeout(() => alertDiv.remove(), 300);
                }, 5000);
            }
            
            // Add animation for alert fade out
            const style = document.createElement('style');
            style.textContent = `
                @keyframes alert-fade-out {
                    from {
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                    to {
                        opacity: 0;
                        transform: translateY(-10px) scale(0.95);
                    }
                }
                
                @keyframes checkbox-bounce {
                    0%, 100% { transform: translateX(0); }
                    50% { transform: translateX(10px); }
                }
                
                .ripple {
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.7);
                    transform: scale(0);
                    animation: ripple-animation 0.6s linear;
                    pointer-events: none;
                }
                
                @keyframes ripple-animation {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
            
            // Input focus enhancement with animation
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                    this.style.animation = 'none';
                    void this.offsetWidth;
                    this.style.animation = 'input-focus-pulse 0.5s var(--ease-out)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
                
                // Clear error on input with animation
                input.addEventListener('input', function() {
                    const errorAlert = document.getElementById('errorAlert');
                    const dynamicAlert = document.getElementById('dynamicAlert');
                    
                    if (errorAlert) {
                        errorAlert.style.animation = 'alert-fade-out 0.3s var(--ease-out)';
                        setTimeout(() => errorAlert.remove(), 300);
                    }
                    
                    if (dynamicAlert) {
                        dynamicAlert.style.animation = 'alert-fade-out 0.3s var(--ease-out)';
                        setTimeout(() => dynamicAlert.remove(), 300);
                    }
                });
            });
            
            // Auto-focus first empty input with animation
            if (!usernameInput.value) {
                setTimeout(() => {
                    usernameInput.focus();
                    usernameInput.style.animation = 'none';
                    void usernameInput.offsetWidth;
                    usernameInput.style.animation = 'input-focus-pulse 0.8s var(--ease-out)';
                }, 800);
            } else if (!passwordInput.value) {
                setTimeout(() => {
                    passwordInput.focus();
                    passwordInput.style.animation = 'none';
                    void passwordInput.offsetWidth;
                    passwordInput.style.animation = 'input-focus-pulse 0.8s var(--ease-out)';
                }, 800);
            }
            
            // Add floating animation to form groups on hover
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach(group => {
                group.addEventListener('mouseenter', () => {
                    group.style.transform = 'translateY(-5px)';
                    group.style.transition = 'transform 0.3s var(--ease-out)';
                });
                
                group.addEventListener('mouseleave', () => {
                    group.style.transform = 'translateY(0)';
                });
            });
            
            // Add parallax effect on mouse move
            document.addEventListener('mousemove', (e) => {
                const x = (e.clientX / window.innerWidth - 0.5) * 10;
                const y = (e.clientY / window.innerHeight - 0.5) * 10;
                
                const particles = document.querySelector('.particles');
                const gridLines = document.querySelector('.grid-lines');
                
                if (particles) {
                    particles.style.transform = `translate(${x}px, ${y}px)`;
                }
                
                if (gridLines) {
                    gridLines.style.transform = `translate(${x * 0.5}px, ${y * 0.5}px)`;
                }
            });
        });
    </script>
</body>
</html>
