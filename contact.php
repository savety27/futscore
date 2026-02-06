<?php
$hideNavbars = true;
$pageTitle = "Contact";
require_once 'includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/contact_redesign.css?v=<?php echo time(); ?>">

<div class="dashboard-wrapper">
    <!-- Mobile Header -->
    <header class="mobile-dashboard-header">
        <div class="mobile-logo">
            <img src="<?php echo SITE_URL; ?>/images/mgp-no-bg.png" alt="Logo">
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar" aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar" aria-hidden="true">
        <div class="sidebar-logo">
            <a href="<?php echo SITE_URL; ?>">
                <img src="<?php echo SITE_URL; ?>/images/mgp-no-bg.png" alt="Logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> <span>HOME</span></a>
            <a href="event.php"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="team.php"><i class="fas fa-users"></i> <span>TEAM</span></a>
            <div class="nav-item-dropdown">
                <a href="#" class="nav-has-dropdown" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                    <div class="nav-link-content">
                        <i class="fas fa-users"></i> <span>PLAYER</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <div id="playerDropdown" class="sidebar-dropdown">
                    <a href="player.php">Player</a>
                    <a href="staff.php">Team Staff</a>
                </div>
            </div>
            <a href="news.php"><i class="fas fa-newspaper"></i> <span>NEWS</span></a>
            <a href="bpjs.php"><i class="fas fa-shield-alt"></i> <span>BPJSTK</span></a>
            <a href="contact.php" class="active"><i class="fas fa-envelope"></i> <span>CONTACT</span></a>

            <div class="sidebar-divider" style="margin: 15px 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                <a href="<?php echo ($_SESSION['admin_role'] === 'pelatih' ? SITE_URL.'/pelatih/dashboard.php' : SITE_URL.'/admin/dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i> <span>DASHBOARD</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/logout.php" style="color: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i> <span>LOGOUT</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-login-sidebar">
                    <i class="fas fa-sign-in-alt"></i> <span>LOGIN</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content-dashboard">
        <header class="dashboard-header contact-header">
            <div class="contact-hero">
                <span class="contact-eyebrow">FUTSCORE</span>
                <h1>Hubungi Kami</h1>
                <p class="contact-subtitle">Tim MGP siap membantu operasional dan informasi event di seluruh Indonesia. Respons rata-rata 1x24 jam kerja.</p>
                <div class="contact-actions">
                    <a class="contact-action primary" href="mailto:info@futscore.com">
                        <i class="fas fa-envelope"></i> Kirim Email
                    </a>
                    <a class="contact-action ghost" href="https://instagram.com/futscore.id" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-instagram"></i> Instagram
                    </a>
                    <a class="contact-action ghost" href="https://youtube.com/@futscoreindonesia4634" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-youtube"></i> YouTube
                    </a>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <div class="container contact-sections">
                <section class="contact-card">
                    <div class="contact-card-header">
                        <div>
                            <span class="contact-label">Kontak Pusat</span>
                            <h2 class="contact-card-title">Gema Digital</h2>
                        </div>
                        <p class="contact-card-subtitle">Pusat layanan resmi untuk kebutuhan operasional, informasi event, dan koordinasi nasional.</p>
                    </div>

                    <div class="contact-info-grid">
                        <div class="contact-info-item">
                            <i class="fas fa-building"></i>
                            <div>
                                <h3>Alamat Kantor</h3>
                                <p>Jalan Kebagusan 1 No. 50, Kec. Pasar Minggu<br>Jakarta Selatan 12520</p>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h3>Email</h3>
                                <p>info@futscore.com</p>
                                <a href="mailto:info@futscore.com" class="contact-link">Kirim Email</a>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <i class="fab fa-instagram"></i>
                            <div>
                                <h3>Instagram</h3>
                                <p>@futscore.id</p>
                                <a href="https://instagram.com/futscore.id" target="_blank" rel="noopener noreferrer" class="contact-link">Kunjungi Instagram</a>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <i class="fab fa-youtube"></i>
                            <div>
                                <h3>YouTube</h3>
                                <p>@futscoreindonesia4634</p>
                                <a href="https://youtube.com/@futscoreindonesia4634" target="_blank" rel="noopener noreferrer" class="contact-link">Kunjungi YouTube</a>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="contact-card">
                    <div class="contact-card-header">
                        <div>
                            <span class="contact-label">DC Wilayah</span>
                            <h2 class="contact-card-title">Daftar DC Wilayah</h2>
                        </div>
                        <p class="contact-card-subtitle">Tim Data Center (DC) MGP siap membantu operasional dan informasi event di area masing-masing.</p>
                    </div>

                    <div class="dc-grid">
                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('628117757222', 'Alfin', 'DC Batam')">
                            <div class="dc-header">
                                <h3>DC Batam</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Alfin</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 811-7757-222</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('6282125434723', 'Agus', 'DC Banten')">
                            <div class="dc-header">
                                <h3>DC Banten</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Agus</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 821-2543-4723</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('6282186582328', 'Detang', 'DC DKI Jakarta')">
                            <div class="dc-header">
                                <h3>DC DKI Jakarta</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Detang</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 821-8658-2328</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('6283182526542', 'Abu', 'DC Jawa Barat')">
                            <div class="dc-header">
                                <h3>DC Jawa Barat</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Abu</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 831-8252-6542</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('6289607665222', 'Mahfudin', 'DC Jawa Tengah')">
                            <div class="dc-header">
                                <h3>DC Jawa Tengah</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Mahfudin</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 896-0766-5222</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('6281336681197', 'Tyas', 'DC Jawa Timur')">
                            <div class="dc-header">
                                <h3>DC Jawa Timur</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Tyas</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 813-3668-1197</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('62895341836843', 'Hanif', 'DC Sumsel')">
                            <div class="dc-header">
                                <h3>DC Sumsel</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Hanif</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 895-3418-3684-3</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('628981434528', 'Robert', 'DC Kalteng')">
                            <div class="dc-header">
                                <h3>DC Kalteng</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Robert</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 898-1434-528</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('6282154000055', 'Phandi', 'DC Kaltim')">
                            <div class="dc-header">
                                <h3>DC Kaltim</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Phandi</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 821-5400-0055</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('6282194754209', 'Takeshi', 'DC Ternate')">
                            <div class="dc-header">
                                <h3>DC Ternate</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Takeshi</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 821-9475-4209</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>

                        <div class="dc-card" role="button" tabindex="0" onclick="openWhatsApp('6288294336553', 'Ale', 'DC General Support')">
                            <div class="dc-header">
                                <h3>DC General Support</h3>
                                <span class="dc-badge support">Support</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Ale</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 882-9433-6553</span>
                                </div>
                                <button class="dc-whatsapp" type="button">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="dc-note">
                        <i class="fas fa-info-circle"></i>
                        <p><strong>Catatan:</strong> Jika area Anda belum tersedia, silakan hubungi siapa pun dari daftar DC di atas atau kontak pusat.</p>
                    </div>
                </section>
            </div>
        </div>

        <footer class="dashboard-footer">
            <p>&copy; 2026 MGP Indonesia. All rights reserved.</p>
            <p>
                <a href="<?php echo SITE_URL; ?>">Home</a> |
                <a href="contact.php">Contact</a> |
                <a href="privacy.php">Privacy Policy</a>
            </p>
        </footer>
    </main>
</div>

<script>
function openWhatsApp(phoneNumber, personName, region) {
    const cleanPhone = phoneNumber.replace(/\D/g, '');
    const message = `Halo ${personName} (${region}),\n\nSaya ingin bertanya terkait informasi yang ada di wilayah ${region}.\n\nTerima kasih.`;
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
            const region = card.querySelector('h3').textContent;
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
