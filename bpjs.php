
<?php
$hideNavbars = true;
require_once 'includes/header.php';

$pageTitle = "BPJS Ketenagakerjaan";
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/bpjs_redesign.css?v=<?php echo time(); ?>">

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
            <a href="bpjs.php" class="active"><i class="fas fa-shield-alt"></i> <span>BPJSTK</span></a>
            <a href="contact.php"><i class="fas fa-envelope"></i> <span>CONTACT</span></a>

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
        <header class="dashboard-header dashboard-header-bpjs">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">FUTSCORE</div>
                    <h1>BPJS Ketenagakerjaan</h1>
                    <p class="header-subtitle">Futscore bekerja sama dengan BPJS Ketenagakerjaan untuk memudahkan pendaftaran atlet usia muda.</p>
                </div>
                <div class="header-actions">
                    <a href="#bpjs-form" class="btn-primary"><i class="fas fa-file-contract"></i> Daftar BPJS</a>
                    <a href="#bpjs-faq" class="btn-secondary"><i class="fas fa-question-circle"></i> Lihat FAQ</a>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <!-- Introduction Section -->
            <section class="section-container section-elevated bpjs-intro-section" id="bpjs-intro">
                <div class="section-header">
                    <h2 class="section-title">Persiapan Pendaftaran</h2>
                </div>
                <div class="bpjs-intro-grid">
                    <div class="bpjs-intro-card">
                        <p>Siapkan <strong>NIK</strong> dan <strong>tanggal lahir</strong> anak sesuai KK/KIA, serta data wali (NIK/HP/email) dan alamat domisili agar proses pendaftaran berjalan lancar. Setelah terdaftar, kartu digital akan dikirim ke email yang didaftarkan.</p>
                        <div class="bpjs-highlight-list">
                            <div class="bpjs-highlight">
                                <i class="fas fa-id-card"></i>
                                <span>NIK anak (16 digit) sesuai KK/KIA</span>
                            </div>
                            <div class="bpjs-highlight">
                                <i class="fas fa-user-shield"></i>
                                <span>Data wali: NIK, HP, dan email aktif</span>
                            </div>
                            <div class="bpjs-highlight">
                                <i class="fas fa-map-location-dot"></i>
                                <span>Alamat domisili lengkap</span>
                            </div>
                        </div>
                    </div>
                    <div class="bpjs-alert-card">
                        <div class="alert-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="alert-content">
                            <h3>Catatan Singkat</h3>
                            <p>Untuk program Bakat dan Minat, pendaftaran online saat ini hanya melalui Futscore. Jika ingin mendaftar langsung, silakan ke kantor cabang BPJS Ketenagakerjaan terdekat.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Program Info Cards -->
            <section class="section-container bpjs-programs" id="bpjs-programs">
                <div class="section-header">
                    <h2 class="section-title">Program BPJS Ketenagakerjaan</h2>
                </div>
                <div class="programs-grid">
                    <div class="program-item">
                        <div class="program-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h3>Daftar dan Bayar BPJS</h3>
                        <p>Informasi program dan manfaat yang tersedia untuk atlet muda.</p>
                    </div>
                    <div class="program-item">
                        <div class="program-icon">
                            <i class="fas fa-road"></i>
                        </div>
                        <h3>Alur Layanan BPJS</h3>
                        <p>Alur layanan BPJS untuk atlet dari pendaftaran sampai layanan.</p>
                    </div>
                    <div class="program-item">
                        <div class="program-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h3>Dokumen JKK</h3>
                        <p>Dokumen pelaporan JKK untuk kebutuhan klaim dan layanan.</p>
                    </div>
                </div>
            </section>

            <!-- FAQ Section -->
            <section class="section-container bpjs-faq" id="bpjs-faq">
                <div class="section-header">
                    <h2 class="section-title">Pertanyaan Umum</h2>
                </div>
                <div class="faq-grid">
                    <article class="faq-item">
                        <h3>1. Siapa yang bisa mendaftar?</h3>
                        <p>BPJS Ketenagakerjaan Bakat dan Minat ditujukan untuk atlet muda usia minimal 6 tahun dan belum 15 tahun (6-14 tahun) pada saat pendaftaran. Artinya, per hari ini yang bisa mendaftar adalah anak yang lahir pada tanggal <strong>27 Januari 2011 s.d. 26 Januari 2020</strong>.</p>
                        <p class="faq-note">Pendaftaran terbuka untuk seluruh Indonesia, tidak terbatas pada anak yang sudah/akan mengikuti event di Futscore.</p>
                    </article>
                    <article class="faq-item">
                        <h3>2. Perlindungan dan Layanan</h3>
                        <ul>
                            <li><strong>Paket Basic:</strong> JKK + JKM - Perlindungan kecelakaan kerja saat latihan/pertandingan termasuk perjalanan berangkat/pulang</li>
                            <li><strong>Paket Lengkap:</strong> JKK + JKM + JHT - Tambahan JHT sebagai tabungan hari tua/pensiun</li>
                            <li><strong>Daftar Rumah Sakit/Faskes:</strong> <a href="https://www.bpjsketenagakerjaan.go.id/kontak.html#plkk" target="_blank" class="faskes-link">Lihat daftar rumah sakit rekanan BPJS</a></li>
                        </ul>
                        <p class="faq-note">Setelah resmi terdaftar, perpanjangan/iuran dapat dibayar melalui kanal online lain yang mendukung BPJS Ketenagakerjaan.</p>
                    </article>
                </div>
            </section>

            <!-- Images Gallery -->
            <section class="section-container bpjs-gallery" id="bpjs-gallery">
                <div class="section-header">
                    <h2 class="section-title">Galeri BPJS Ketenagakerjaan</h2>
                </div>
                <div class="gallery-grid">
                    <div class="gallery-item" onclick="openModal('<?php echo SITE_URL; ?>/images/bpjs/bpjs 1.jpg', 'BPJS Program Bakat dan Minat')">
                        <img src="<?php echo SITE_URL; ?>/images/bpjs/bpjs 1.jpg" 
                             alt="BPJS Program Bakat dan Minat"
                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/default-image.jpg'">
                        <div class="gallery-overlay">
                            <i class="fas fa-expand"></i>
                            <span>Klik untuk memperbesar</span>
                        </div>
                    </div>
                    <div class="gallery-item" onclick="openModal('<?php echo SITE_URL; ?>/images/bpjs/bpjs 2.jpg', 'BPJS Pendaftaran Online')">
                        <img src="<?php echo SITE_URL; ?>/images/bpjs/bpjs 2.jpg" 
                             alt="BPJS Pendaftaran Online"
                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/default-image.jpg'">
                        <div class="gallery-overlay">
                            <i class="fas fa-expand"></i>
                            <span>Klik untuk memperbesar</span>
                        </div>
                    </div>
                    <div class="gallery-item" onclick="openModal('<?php echo SITE_URL; ?>/images/bpjs/bpjs 3.jpg', 'BPJS Manfaat Perlindungan')">
                        <img src="<?php echo SITE_URL; ?>/images/bpjs/bpjs 3.jpg" 
                             alt="BPJS Manfaat Perlindungan"
                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/default-image.jpg'">
                        <div class="gallery-overlay">
                            <i class="fas fa-expand"></i>
                            <span>Klik untuk memperbesar</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Image Modal -->
            <div id="imageModal" class="image-modal" aria-hidden="true">
                <button class="modal-close" onclick="closeModal()" aria-label="Close">&times;</button>
                <div class="modal-content">
                    <img id="modalImage" src="" alt="">
                </div>
                <div class="modal-caption">
                    <p id="modalCaption"></p>
                </div>
            </div>

            <!-- BPJS Form Section -->
            <section class="section-container bpjs-form-section" id="bpjs-form">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-file-contract"></i> Formulir Pendaftaran
                    </h2>
                </div>
                <div class="form-notice">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Formulir pendaftaran tampil di bawah ini. Jika tampilan bermasalah di perangkat Anda, <a href="https://bpjstk.co/futscore" target="_blank">buka langsung di sini</a>.</p>
                </div>
                <div class="form-wrapper">
                    <iframe 
                        src="https://eform.bpjsketenagakerjaan.go.id/?token=cGoWgAzb6be8bE4V9LdWaYbBxELUxaFHZGX4VOoaI6MLcvAMAf6_al_Ap8L2fr7gRz4hrhYjADWHfU3CAoOOr1nboqNmfrMUc0cJ8LUVvHb5v4gXbjy0s2ciwsf5m7JwTyThErHRuZldqhkzmt2jFII9Mjv0Fd22t33EmzkhhA1kt4Qj4uhybX8-nUyF_Pi0ImddIS-ugcZpb5mEVKJH2kg9AwgWW5wy5dCNL17KQ4r7Vol_3Z-ElavQd-EKOBhxWkvNq1-GVzx6iJeoT75-o8KlFeLAkoxwlIC137DDRO_FZEzdd96ckp9LrExVlJjGlY1ENbXxaQAqNxUSilId-KKHlbmBbt4OiGmLMDCCVE1YWu5Bd8AJeA1FxW7kEnbsrMceJSwa7ygr23dYZPvBIZ7rHAyPZkO79qZ3RZFPcPlMpvJ60c0i9t6xArvoC4t_bc0Y7FNhXqfPBvtCB5C0-E_PM6p9r_9-nT1tTasxsLy5J1_MRHDxIAk10q44mqKH"
                        class="bpjs-iframe"
                        title="Formulir Pendaftaran BPJS Ketenagakerjaan"
                        frameborder="0"
                        scrolling="yes"
                        allow="autoplay; encrypted-media"
                        sandbox="allow-forms allow-scripts allow-same-origin allow-popups"
                    >
                        Browser Anda tidak mendukung iframe. 
                        <a href="https://eform.bpjsketenagakerjaan.go.id/?token=cGoWgAzb6be8bE4V9LdWaYbBxELUxaFHZGX4VOoaI6MLcvAMAf6_al_Ap8L2fr7gRz4hrhYjADWHfU3CAoOOr1nboqNmfrMUc0cJ8LUVvHb5v4gXbjy0s2ciwsf5m7JwTyThErHRuZldqhkzmt2jFII9Mjv0Fd22t33EmzkhhA1kt4Qj4uhybX8-nUyF_Pi0ImddIS-ugcZpb5mEVKJH2kg9AwgWW5wy5dCNL17KQ4r7Vol_3Z-ElavQd-EKOBhxWkvNq1-GVzx6iJeoT75-o8KlFeLAkoxwlIC137DDRO_FZEzdd96ckp9LrExVlJjGlY1ENbXxaQAqNxUSilId-KKHlbmBbt4OiGmLMDCCVE1YWu5Bd8AJeA1FxW7kEnbsrMceJSwa7ygr23dYZPvBIZ7rHAyPZkO79qZ3RZFPcPlMpvJ60c0i9t6xArvoC4t_bc0Y7FNhXqfPBvtCB5C0-E_PM6p9r_9-nT1tTasxsLy5J1_MRHDxIAk10q44mqKH" 
                           target="_blank">
                            Klik di sini untuk membuka formulir di halaman baru
                        </a>
                    </iframe>
                </div>
                <div class="form-instructions">
                    <h3><i class="fas fa-question-circle"></i> Petunjuk Pengisian</h3>
                    <ol>
                        <li>Isi formulir dengan data yang sesuai dengan dokumen asli (KTP/KK)</li>
                        <li>Pastikan NIK yang dimasukkan benar (16 digit)</li>
                        <li>Data akan langsung diproses oleh sistem BPJS Ketenagakerjaan</li>
                        <li>Setelah submit, Anda akan menerima email konfirmasi dari BPJS</li>
                        <li>Simpan nomor registrasi yang diberikan untuk keperluan selanjutnya</li>
                    </ol>
                </div>
            </section>

            <!-- Contact Info -->
            <section class="section-container contact-section" id="bpjs-contact">
                <div class="section-header">
                    <h2 class="section-title">Butuh Bantuan?</h2>
                </div>
                <div class="contact-grid">
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h3>Call Center BPJS</h3>
                        <p><strong>165</strong> (24 Jam)</p>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3>Email</h3>
                        <p>callcenter165@bpjs.go.id</p>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <h3>Website Resmi</h3>
                        <p><a href="https://www.bpjsketenagakerjaan.go.id" target="_blank">bpjsketenagakerjaan.go.id</a></p>
                    </div>
                </div>
            </section>
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
// Image Modal Functionality
function openModal(imageSrc, caption) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    const modalCaption = document.getElementById('modalCaption');

    modal.style.display = "block";
    modal.setAttribute('aria-hidden', 'false');
    modalImg.src = imageSrc;
    modalCaption.textContent = caption || '';

    // Disable scroll on body when modal is open
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('imageModal');
    modal.style.display = "none";
    modal.setAttribute('aria-hidden', 'true');

    // Re-enable scroll
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside the image
const modalEl = document.getElementById('imageModal');
if (modalEl) {
    modalEl.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('imageModal');
    if (modal && modal.style.display === "block") {
        if (e.key === "Escape") {
            closeModal();
        }
    }
});
</script>

<script>
// Sidebar Dropdown Toggle
function toggleDropdown(element, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;

    dropdown.classList.toggle('show');
    element.classList.toggle('open');
}

// Sidebar Toggle Strategy for Mobile
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

<?php require_once 'includes/footer.php'; ?>
