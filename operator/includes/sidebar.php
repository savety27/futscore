<?php
$current_page = $current_page ?? pathinfo((string)basename($_SERVER['PHP_SELF'] ?? ''), PATHINFO_FILENAME);
$operator_event_name = $operator_event_name ?? 'Operator';
$operator_event_image = trim((string)($operator_event_image ?? ''));
$has_event_logo = ($operator_event_image !== '' && file_exists(__DIR__ . '/../../images/events/' . $operator_event_image));
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo <?php echo $has_event_logo ? 'has-team-logo' : ''; ?>">
                <?php if ($has_event_logo): ?>
                    <img src="../images/events/<?php echo htmlspecialchars($operator_event_image); ?>" alt="<?php echo htmlspecialchars($operator_event_name); ?>" class="sidebar-team-logo">
                <?php endif; ?>
            </div>
        </div>
        <div class="academy-info">
            <div class="academy-name"><?php echo htmlspecialchars($operator_event_name); ?></div>
            <div style="font-size: 14px; opacity: 0.8; color: white;">Portal Operator</div>
        </div>
    </div>

    <div class="menu">
        <div class="menu-item">
            <a href="dashboard.php" class="menu-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-home"></i></span>
                <span class="menu-text">Dashboard</span>
            </a>
        </div>

        <div class="menu-item">
            <a href="challenge.php" class="menu-link <?php echo $current_page === 'challenge' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-futbol"></i></span>
                <span class="menu-text">Challenge</span>
            </a>
        </div>

        <div class="menu-item">
            <a href="event.php" class="menu-link <?php echo in_array($current_page, ['event', 'event_value', 'event_bracket'], true) ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-trophy"></i></span>
                <span class="menu-text">Event</span>
            </a>
        </div>

        <div class="menu-item">
            <a href="berita.php" class="menu-link <?php echo $current_page === 'berita' ? 'active' : ''; ?>">
                <span class="menu-icon"><i class="fas fa-newspaper"></i></span>
                <span class="menu-text">Berita</span>
            </a>
        </div>
    </div>
</div>
