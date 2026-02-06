<?php
session_start();
require_once 'admin/config/database.php'; // Sesuaikan path

// Gunakan koneksi database yang sudah ada
$db = $conn;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input_username = trim($_POST['username'] ?? '');
    $input_password = trim($_POST['password'] ?? '');
    
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
    <meta name="theme-color" content="#0D0D0D">
    <meta name="description" content="MGP Admin Dashboard - Sistem manajemen pertandingan futsal">
    <title>Login - MGP</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================
           DESIGN SYSTEM: STADIUM TUNNEL
           Mobile-First | Dark Theme | Sport Tech
           ============================================ */
        
        :root {
            /* Colors */
            --bg-void: #0A0A0A;
            --bg-dark: #0D0D0D;
            --bg-card: #141414;
            --bg-input: #1A1A1A;
            --bg-input-focus: #1F1F1F;
            
            --accent-lime: #ADFF2F;
            --accent-lime-dim: rgba(173, 255, 47, 0.15);
            --accent-lime-glow: rgba(173, 255, 47, 0.4);
            
            --text-primary: #FAFAFA;
            --text-secondary: #B8B8B8;
            --text-muted: #6A6A6A;
            
            --error-red: #FF4757;
            --error-bg: rgba(255, 71, 87, 0.1);
            --success-green: #2ED573;
            --success-bg: rgba(46, 213, 115, 0.1);
            
            /* Typography */
            --font-display: 'Bebas Neue', Impact, sans-serif;
            --font-body: 'DM Sans', system-ui, sans-serif;
            
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
            
            /* Transitions */
            --ease-out: cubic-bezier(0.4, 0, 0.2, 1);
            --ease-spring: cubic-bezier(0.34, 1.56, 0.64, 1);
            
            /* Shadows */
            --shadow-glow: 0 0 30px var(--accent-lime-glow);
            --shadow-card: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
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
        }
        
        body {
            font-family: var(--font-body);
            background: var(--bg-void);
            color: var(--text-primary);
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* ============================================
           ATMOSPHERIC BACKGROUND
           ============================================ */
        
        .stadium-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }
        
        /* Radial spotlight effect */
        .stadium-bg::before {
            content: '';
            position: absolute;
            top: -30%;
            left: 50%;
            transform: translateX(-50%);
            width: 150%;
            height: 80%;
            background: radial-gradient(
                ellipse at center,
                rgba(173, 255, 47, 0.08) 0%,
                rgba(173, 255, 47, 0.02) 40%,
                transparent 70%
            );
            pointer-events: none;
        }
        
        /* Diagonal accent lines */
        .accent-beams {
            position: absolute;
            inset: 0;
            overflow: hidden;
            opacity: 0.04;
        }
        
        .accent-beams::before,
        .accent-beams::after {
            content: '';
            position: absolute;
            width: 200%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent-lime), transparent);
        }
        
        .accent-beams::before {
            top: 20%;
            left: -50%;
            transform: rotate(-15deg);
            animation: beam-slide 12s linear infinite;
        }
        
        .accent-beams::after {
            bottom: 30%;
            right: -50%;
            transform: rotate(15deg);
            animation: beam-slide 15s linear infinite reverse;
        }
        
        @keyframes beam-slide {
            from { transform: translateX(-20%) rotate(-15deg); }
            to { transform: translateX(20%) rotate(-15deg); }
        }
        
        /* Noise texture overlay */
        .noise-overlay {
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.03;
            pointer-events: none;
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
        }
        
        /* ============================================
           LOGIN CARD - MOBILE FIRST
           ============================================ */
        
        .login-card {
            width: 100%;
            flex: 1;
            display: flex;
            flex-direction: column;
            animation: card-rise 0.8s var(--ease-out) forwards;
            opacity: 0;
        }
        
        @keyframes card-rise {
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
           BRAND HEADER
           ============================================ */
        
        .brand {
            text-align: center;
            padding: var(--space-xl) 0;
            animation: brand-reveal 0.6s var(--ease-out) 0.2s forwards;
            opacity: 0;
        }
        
        @keyframes brand-reveal {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            background: var(--bg-card);
            border: 2px solid var(--accent-lime);
            border-radius: var(--radius-lg);
            font-family: var(--font-display);
            font-size: 2rem;
            color: var(--accent-lime);
            margin-bottom: var(--space-md);
            position: relative;
            box-shadow: var(--shadow-glow);
            animation: logo-pulse 3s ease-in-out infinite;
        }
        
        @keyframes logo-pulse {
            0%, 100% { box-shadow: 0 0 20px var(--accent-lime-glow); }
            50% { box-shadow: 0 0 40px var(--accent-lime-glow), 0 0 60px rgba(173, 255, 47, 0.2); }
        }
        
        .brand h1 {
            font-family: var(--font-display);
            font-size: 2.5rem;
            letter-spacing: 0.05em;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: var(--space-xs);
        }
        
        .brand .tagline {
            font-size: 0.875rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.2em;
        }
        
        /* ============================================
           FORM CONTAINER
           ============================================ */
        
        .form-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
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
            animation: alert-shake 0.5s var(--ease-spring);
        }
        
        @keyframes alert-shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-8px); }
            40% { transform: translateX(8px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }
        
        .alert-error {
            background: var(--error-bg);
            border: 1px solid rgba(255, 71, 87, 0.3);
            color: var(--error-red);
        }
        
        .alert-success {
            background: var(--success-bg);
            border: 1px solid rgba(46, 213, 115, 0.3);
            color: var(--success-green);
        }
        
        .alert-icon {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
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
        }
        
        .form-group:nth-child(1) { animation-delay: 0.3s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }
        .form-group:nth-child(3) { animation-delay: 0.5s; }
        
        @keyframes field-reveal {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-label {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        
        .form-label i {
            color: var(--accent-lime);
            font-size: 0.75rem;
        }
        
        /* ============================================
           INPUT FIELDS
           ============================================ */
        
        .input-wrapper {
            position: relative;
        }
        
        .form-input {
            width: 100%;
            padding: var(--space-md) var(--space-lg);
            padding-left: 3rem;
            background: var(--bg-input);
            border: 2px solid transparent;
            border-radius: var(--radius-md);
            font-family: var(--font-body);
            font-size: 1rem;
            color: var(--text-primary);
            transition: all 0.3s var(--ease-out);
            -webkit-appearance: none;
            appearance: none;
        }
        
        .form-input::placeholder {
            color: var(--text-muted);
        }
        
        .form-input:focus {
            outline: none;
            background: var(--bg-input-focus);
            border-color: var(--accent-lime);
            box-shadow: 0 0 0 4px var(--accent-lime-dim);
        }
        
        .input-icon {
            position: absolute;
            left: var(--space-md);
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1rem;
            transition: color 0.3s var(--ease-out);
            pointer-events: none;
        }
        
        .form-input:focus ~ .input-icon {
            color: var(--accent-lime);
        }
        
        .toggle-password {
            position: absolute;
            right: var(--space-md);
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1rem;
            cursor: pointer;
            padding: var(--space-sm);
            transition: color 0.3s var(--ease-out);
            -webkit-tap-highlight-color: transparent;
        }
        
        .toggle-password:hover,
        .toggle-password:focus {
            color: var(--accent-lime);
            outline: none;
        }
        
        /* ============================================
           PASSWORD NOTES & CAPS WARNING
           ============================================ */
        
        .password-notes {
            padding: var(--space-sm) var(--space-md);
            background: var(--bg-card);
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--accent-lime);
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
            color: var(--text-muted);
        }
        
        .password-notes li i {
            color: var(--accent-lime);
            font-size: 0.625rem;
        }
        
        .caps-warning {
            display: none;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            background: var(--error-bg);
            border-radius: var(--radius-sm);
            color: var(--error-red);
            font-size: 0.8125rem;
            font-weight: 500;
            animation: alert-shake 0.4s var(--ease-spring);
        }
        
        .caps-warning.show {
            display: flex;
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
        }
        
        .checkbox-custom {
            width: 20px;
            height: 20px;
            border: 2px solid var(--text-muted);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s var(--ease-out);
            flex-shrink: 0;
        }
        
        .checkbox-custom::after {
            content: '';
            width: 10px;
            height: 10px;
            background: var(--accent-lime);
            border-radius: 2px;
            transform: scale(0);
            transition: transform 0.2s var(--ease-spring);
        }
        
        .remember-row.checked .checkbox-custom {
            border-color: var(--accent-lime);
        }
        
        .remember-row.checked .checkbox-custom::after {
            transform: scale(1);
        }
        
        .remember-text {
            font-size: 0.875rem;
            color: var(--text-secondary);
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
            padding: var(--space-md) var(--space-xl);
            background: var(--accent-lime);
            color: var(--bg-dark);
            border: none;
            border-radius: var(--radius-md);
            font-family: var(--font-display);
            font-size: 1.25rem;
            letter-spacing: 0.1em;
            cursor: pointer;
            overflow: hidden;
            transition: all 0.3s var(--ease-out);
            -webkit-tap-highlight-color: transparent;
            min-height: 56px;
            animation: btn-reveal 0.5s var(--ease-out) 0.6s forwards;
            opacity: 0;
        }
        
        @keyframes btn-reveal {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }
        
        .btn-submit:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn-submit:hover::before {
            left: 100%;
        }
        
        .btn-text {
            position: relative;
            z-index: 1;
        }
        
        .btn-icon {
            position: relative;
            z-index: 1;
            transition: transform 0.3s var(--ease-out);
        }
        
        .btn-submit:hover .btn-icon {
            transform: translateX(4px);
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(13, 13, 13, 0.3);
            border-top-color: var(--bg-dark);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn-submit.loading .btn-text,
        .btn-submit.loading .btn-icon {
            display: none;
        }
        
        .btn-submit.loading .loading-spinner {
            display: block;
        }
        
        /* ============================================
           FOOTER
           ============================================ */
        
        .card-footer {
            text-align: center;
            padding: var(--space-xl) 0 var(--space-md);
            animation: footer-reveal 0.5s var(--ease-out) 0.7s forwards;
            opacity: 0;
        }
        
        @keyframes footer-reveal {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .db-indicator {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-xs) var(--space-md);
            background: var(--bg-card);
            border-radius: 100px;
            font-size: 0.6875rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        
        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            animation: dot-pulse 2s infinite;
        }
        
        .status-dot.connected {
            background: var(--success-green);
            box-shadow: 0 0 8px var(--success-green);
        }
        
        .status-dot.disconnected {
            background: var(--error-red);
            box-shadow: 0 0 8px var(--error-red);
        }
        
        @keyframes dot-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .copyright {
            margin-top: var(--space-md);
            font-size: 0.6875rem;
            color: var(--text-muted);
        }
        
        /* ============================================
           TABLET BREAKPOINT (768px+)
           ============================================ */
        
        @media (min-width: 768px) {
            .login-main {
                justify-content: center;
                align-items: center;
                padding: var(--space-xl);
            }
            
            .login-card {
                max-width: 420px;
                flex: 0 1 auto;
                background: var(--bg-card);
                border-radius: var(--radius-xl);
                padding: var(--space-2xl);
                box-shadow: var(--shadow-card);
                border: 1px solid rgba(255, 255, 255, 0.05);
            }
            
            .brand {
                padding-top: 0;
            }
            
            .logo-mark {
                width: 80px;
                height: 80px;
                font-size: 2.25rem;
            }
            
            .brand h1 {
                font-size: 3rem;
            }
            
            .form-input {
                padding: 1.125rem var(--space-lg);
                padding-left: 3.25rem;
            }
        }
        
        /* ============================================
           DESKTOP BREAKPOINT (1024px+)
           ============================================ */
        
        @media (min-width: 1024px) {
            .login-card {
                max-width: 440px;
                padding: var(--space-2xl) 3rem;
            }
            
            .brand h1 {
                font-size: 3.5rem;
            }
            
            .btn-submit {
                font-size: 1.375rem;
                min-height: 60px;
            }
        }
        
        /* ============================================
           LARGE DESKTOP (1440px+)
           ============================================ */
        
        @media (min-width: 1440px) {
            .stadium-bg::before {
                height: 60%;
                top: -20%;
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
            }
        }
        
        /* ============================================
           PRINT STYLES
           ============================================ */
        
        @media print {
            .stadium-bg, .db-indicator {
                display: none;
            }
            
            body {
                background: white;
                color: black;
            }
        }
    </style>
</head>
<body>
    <!-- Atmospheric Background -->
    <div class="stadium-bg" aria-hidden="true">
        <div class="noise-overlay"></div>
        <div class="accent-beams"></div>
    </div>
    
    <!-- Main Content -->
    <main class="login-main">
        <div class="login-card">
            <!-- Brand Header -->
            <header class="brand">
                <div class="logo-mark">FS</div>
                <h1>MGP</h1>
                <p class="tagline">Admin Dashboard</p>
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
                <p class="copyright">&copy; <?php echo date('Y'); ?> MGP. All rights reserved.</p>
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
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                this.innerHTML = type === 'password' 
                    ? '<i class="fas fa-eye"></i>' 
                    : '<i class="fas fa-eye-slash"></i>';
            });
            
            // Caps Lock detection
            passwordInput.addEventListener('keyup', function(e) {
                capsWarning.classList.toggle('show', e.getModifierState('CapsLock'));
            });
            
            passwordInput.addEventListener('keydown', function(e) {
                capsWarning.classList.toggle('show', e.getModifierState('CapsLock'));
            });
            
            // Remember me toggle
            function toggleRemember() {
                const isChecked = rememberMe.classList.toggle('checked');
                rememberMe.setAttribute('aria-checked', isChecked);
                
                if (isChecked && usernameInput.value) {
                    localStorage.setItem('futscore_remembered_user', usernameInput.value);
                } else {
                    localStorage.removeItem('futscore_remembered_user');
                }
            }
            
            rememberMe.addEventListener('click', toggleRemember);
            rememberMe.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleRemember();
                }
            });
            
            // Load remembered username
            const rememberedUser = localStorage.getItem('futscore_remembered_user');
            if (rememberedUser) {
                usernameInput.value = rememberedUser;
                rememberMe.classList.add('checked');
                rememberMe.setAttribute('aria-checked', 'true');
            }
            
            // Form submission
            loginForm.addEventListener('submit', function(e) {
                const username = usernameInput.value.trim();
                const password = passwordInput.value.trim();
                
                if (!username || !password) {
                    e.preventDefault();
                    showAlert('error', 'Username/email dan password harus diisi!');
                    return false;
                }
                
                // Show loading state
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                
                // Update remember me storage
                if (rememberMe.classList.contains('checked')) {
                    localStorage.setItem('futscore_remembered_user', username);
                }
                
                return true;
            });
            
            // Dynamic alert function
            function showAlert(type, message) {
                // Remove existing dynamic alerts
                const existingAlert = document.getElementById('dynamicAlert');
                if (existingAlert) existingAlert.remove();
                
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
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    alertDiv.style.opacity = '0';
                    alertDiv.style.transform = 'translateY(-10px)';
                    alertDiv.style.transition = 'all 0.3s ease';
                    setTimeout(() => alertDiv.remove(), 300);
                }, 5000);
            }
            
            // Input focus enhancement
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
                
                // Clear error on input
                input.addEventListener('input', function() {
                    const errorAlert = document.getElementById('errorAlert');
                    const dynamicAlert = document.getElementById('dynamicAlert');
                    if (errorAlert) errorAlert.style.display = 'none';
                    if (dynamicAlert) dynamicAlert.style.display = 'none';
                });
            });
            
            // Auto-focus first empty input
            if (!usernameInput.value) {
                usernameInput.focus();
            } else if (!passwordInput.value) {
                passwordInput.focus();
            }
        });
    </script>
</body>
</html>
