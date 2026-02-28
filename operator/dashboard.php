<?php
session_start();

$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'operator') {
    header('Location: ../login.php');
    exit;
}

$operator_id = (int) ($_SESSION['admin_id'] ?? 0);
$event_id = (int) ($_SESSION['event_id'] ?? 0);
$event_name = '';

if ($operator_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT au.event_id, e.name AS event_name
            FROM admin_users au
            LEFT JOIN events e ON e.id = au.event_id
            WHERE au.id = ?
            LIMIT 1
        ");
        $stmt->execute([$operator_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $event_id = (int) ($row['event_id'] ?? 0);
        $event_name = trim((string) ($row['event_name'] ?? ''));
        $_SESSION['event_id'] = $event_id > 0 ? $event_id : null;
    } catch (PDOException $e) {
        $event_name = '';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Operator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif; background: #f5f8fc; margin: 0; color: #1f2937; }
        .wrap { max-width: 980px; margin: 40px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 10px 24px rgba(15, 39, 68, 0.08); margin-bottom: 16px; }
        .title { margin: 0 0 8px; color: #0f2744; }
        .muted { color: #64748b; margin: 0; }
        .event { font-size: 20px; font-weight: 700; margin-top: 12px; }
        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 600; }
        .btn-primary { background: #0f2744; color: #fff; }
        .btn-light { background: #e2e8f0; color: #1f2937; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1 class="title">Dashboard Operator</h1>
            <p class="muted">Akun operator dibatasi pada event yang ditugaskan.</p>
            <div class="event">
                <?php echo $event_id > 0 ? htmlspecialchars($event_name !== '' ? $event_name : ('Event #' . $event_id)) : 'Event belum ditetapkan'; ?>
            </div>
            <div class="btn-row">
                <a class="btn btn-primary" href="../admin/event.php">
                    <i class="fas fa-calendar-alt"></i>
                    Buka Halaman Event
                </a>
                <a class="btn btn-light" href="../admin/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</body>
</html>

