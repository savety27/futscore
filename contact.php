<?php
$hideNavbars = true;
$pageTitle = "Contact";
require_once 'includes/header.php';
$contactEmailLink = "https://mail.google.com/mail/?view=cm&fs=1&to=alvetrixofficial@gmail.com&su=Halo%20Alvetrix";
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo getAssetVersion('/css/redesign_core.css'); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/contact_redesign.css?v=<?php echo getAssetVersion('/css/contact_redesign.css'); ?>">

<div class="dashboard-wrapper">
<?php 
$currentPage = 'contact';
include 'includes/sidebar.php'; 
?>

    <!-- Main Content -->
    <main class="main-content-dashboard">
        <header class="dashboard-header contact-header">
            <div class="dashboard-header-inner">
                <div>
                    <span class="header-eyebrow">ALVETRIX</span>
                    <h1>Hubungi Kami</h1>
                    <p class="header-subtitle">Silakan menghubungi kami melalui email resmi atau WhatsApp layanan sesuai kebutuhan Anda.</p>
                </div>
                <div class="header-actions">
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
                        <p class="contact-card-subtitle">Gunakan WhatsApp untuk pertanyaan umum dan informasi cepat terkait layanan Alvetrix.</p>
                    </div>

                    <div class="dc-grid">
                        
                        <div class="dc-card" role="button" tabindex="0" data-contact-context="Layanan Alvetrix" onclick="openWhatsApp('6282186582328', 'Detang', 'Layanan Alvetrix')">
                            <div class="dc-header">
                                <h3>Kontak WhatsApp</h3>
                                <span class="dc-badge">Aktif</span>
                            </div>
                            <div class="dc-content">
                                <div class="dc-person">
                                    <i class="fas fa-user"></i>
                                    <span>Alvetrix</span>
                                </div>
                                <div class="dc-phone">
                                    <i class="fas fa-phone"></i>
                                    <span>+62 813-6891-226</span>
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
            const region = card.dataset.contactContext || 'layanan Alvetrix';
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

</script>

<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo getAssetVersion('/js/script.js'); ?>"></script>
</body>
</html>
