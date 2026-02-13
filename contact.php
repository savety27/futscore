<?php
$hideNavbars = true;
$pageTitle = "Contact";
require_once 'includes/header.php';
$contactEmailLink = "https://mail.google.com/mail/?view=cm&fs=1&to=alvetrixofficial@gmail.com&su=Halo%20ALVETRIX";
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/contact_redesign.css?v=<?php echo time(); ?>">

<div class="dashboard-wrapper">
    <!-- Mobile Header -->
    <header class="mobile-dashboard-header">
        <div class="mobile-logo">
            <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Buka/Tutup Sidebar" aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar" aria-hidden="true">
        <div class="sidebar-logo">
            <a href="<?php echo SITE_URL; ?>">
                <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> <span>BERANDA</span></a>
            <a href="event.php"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="team.php"><i class="fas fa-users"></i> <span>TIM</span></a>
            <div class="nav-item-dropdown">
                <a href="#" class="nav-has-dropdown" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                    <div class="nav-link-content">
                        <i class="fas fa-users"></i> <span>PEMAIN</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <div id="playerDropdown" class="sidebar-dropdown">
                    <a href="player.php">Pemain</a>
                    <a href="staff.php">Staf Tim</a>
                </div>
            </div>
            <a href="news.php"><i class="fas fa-newspaper"></i> <span>BERITA</span></a>
            <a href="bpjs.php"><i class="fas fa-shield-alt"></i> <span>BPJSTK</span></a>
            <a href="contact.php" class="active"><i class="fas fa-envelope"></i> <span>KONTAK</span></a>

            <div class="sidebar-divider" style="margin: 15px 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                <a href="<?php echo ($_SESSION['admin_role'] === 'pelatih' ? SITE_URL.'/pelatih/dashboard.php' : SITE_URL.'/admin/dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i> <span>DASHBOARD</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/logout.php" style="color: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i> <span>KELUAR</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-login-sidebar">
                    <i class="fas fa-sign-in-alt"></i> <span>MASUK</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content-dashboard">
        <header class="dashboard-header contact-header">
            <div class="contact-hero">
                <span class="contact-eyebrow">ALVETRIX</span>
                <h1>Hubungi Kami</h1>
                <p class="contact-subtitle">Silakan menghubungi kami melalui email resmi atau WhatsApp layanan sesuai kebutuhan Anda.</p>
                <div class="contact-actions">
                    <a class="contact-action primary" href="<?php echo $contactEmailLink; ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-envelope"></i> Kirim Email
                    </a>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <div class="container contact-sections">
                <section class="contact-card">
                    <div class="contact-card-header">
                        <div>
                            <span class="contact-label">Email Resmi</span>
                            <h2 class="contact-card-title">Kontak Administrasi</h2>
                        </div>
                        <p class="contact-card-subtitle">Gunakan email resmi untuk administrasi, kerja sama, dan komunikasi formal.</p>
                    </div>

                    <div class="contact-info-grid">
                        <div class="contact-info-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h3>Email</h3>
                                <p>alvetrixofficial@gmail.com</p>
                                <a href="<?php echo $contactEmailLink; ?>" class="contact-link" target="_blank" rel="noopener noreferrer">Kirim Email</a>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="contact-card">
                    <div class="contact-card-header">
                        <div>
                            <span class="contact-label">Layanan Cepat</span>
                            <h2 class="contact-card-title">WhatsApp Layanan</h2>
                        </div>
                        <p class="contact-card-subtitle">Gunakan WhatsApp untuk pertanyaan umum dan informasi cepat terkait layanan ALVETRIX.</p>
                    </div>

                    <div class="dc-grid">
                        
                        <div class="dc-card" role="button" tabindex="0" data-contact-context="Layanan ALVETRIX" onclick="openWhatsApp('6282186582328', 'Detang', 'Layanan ALVETRIX')">
                            <div class="dc-header">
                                <h3>Kontak WhatsApp</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Savety</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 878-9895-4988</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="dc-note">
                        <i class="fas fa-info-circle"></i>
                        <p><strong>Catatan:</strong> Untuk respons lebih cepat, kirim pesan melalui WhatsApp. Layanan ini hanya menerima chat.</p>
                    </div>
                </section>
            </div>
        </div>

         <footer class="dashboard-footer">
            <p>&copy; 2026 ALVETRIX. Semua hak dilindungi.</p>
            <p>
                <a href="<?php echo SITE_URL; ?>">Beranda</a> |
                <a href="contact.php">Kontak</a> |
                <a href="bpjs.php">BPJSTK</a>
            </p>
        </footer>
    </main>
</div>

<script>
function openWhatsApp(phoneNumber, personName, region) {
    const cleanPhone = phoneNumber.replace(/\D/g, '');
    const message = `Halo ${personName},\nSaya ingin bertanya terkait ${region}.\nTerima kasih.`;
    const encodedMessage = encodeURIComponent(message);
    const whatsappURL = `https://wa.me/${cleanPhone}?text=${encodedMessage}`;
    window.open(whatsappURL, '_blank');
}

document.addEventListener('DOMContentLoaded', function() {
    const whatsappButtons = document.querySelectorAll('.dc-whatsapp');
    whatsappButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const card = this.closest('.dc-card');
            const region = card.dataset.contactContext || 'layanan ALVETRIX';
            const person = card.querySelector('.dc-person span').textContent;
            const phone = card.querySelector('.dc-phone span').textContent.replace(/\D/g, '');
            openWhatsApp(phone, person, region);
        });
    });

    const dcCards = document.querySelectorAll('.dc-card');
    dcCards.forEach(card => {
        card.addEventListener('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                this.click();
            }
        });
    });
});

function toggleDropdown(element, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;
    dropdown.classList.toggle('show');
    element.classList.toggle('open');
}

const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');

const setSidebarOpen = (open) => {
    if (!sidebar || !sidebarToggle || !sidebarOverlay) return;
    sidebar.classList.toggle('active', open);
    sidebarOverlay.classList.toggle('active', open);
    sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
    sidebarOverlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('sidebar-open', open);
};

if (sidebarToggle && sidebar && sidebarOverlay) {
    sidebarToggle.addEventListener('click', () => {
        const isOpen = sidebar.classList.contains('active');
        setSidebarOpen(!isOpen);
    });

    sidebarOverlay.addEventListener('click', () => {
        setSidebarOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            setSidebarOpen(false);
        }
    });
}
</script>

<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>

