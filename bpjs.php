<?php
require_once 'includes/header.php';

$pageTitle = "BPJS Ketenagakerjaan"; // tes
?>

<div class="container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        
    </div>
    
    <!-- Header Section -->
    <div class="bpjs-header">
        <h1 class="bpjs-title">BPJS Ketenagakerjaan untuk Atlet Muda</h1>
        <p class="bpjs-subtitle">Futscore bekerja sama dengan BPJS Ketenagakerjaan untuk memudahkan pendaftaran atlet usia muda</p>
    </div>
    
    <!-- Introduction Section -->
    <div class="bpjs-intro">
        <p>Siapkan <strong>NIK</strong> dan <strong>tanggal lahir</strong> anak sesuai KK/KIA, serta data wali (NIK/HP/email) dan alamat domisili agar proses pendaftaran berjalan lancar. Setelah terdaftar, kartu digital akan dikirim ke email yang didaftarkan.</p>
        
        <div class="bpjs-alert">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Catatan Singkat:</strong> Untuk program Bakat dan Minat, pendaftaran online saat ini hanya melalui Futscore. Jika ingin mendaftar langsung, silakan ke kantor cabang BPJS Ketenagakerjaan terdekat.
            </div>
        </div>
    </div>
    
    <!-- Program Info Cards -->
    <div class="bpjs-programs">
        <h2 class="section-title">Program BPJS Ketenagakerjaan</h2>
        <div class="programs-grid">
            <div class="program-item">
                <i class="fas fa-file-alt"></i>
                <h3>Daftar dan Bayar BPJS</h3>
                <p>Informasi program dan manfaat.</p>
            </div>
            <div class="program-item">
                <i class="fas fa-road"></i>
                <h3>Alur Layanan BPJS</h3>
                <p>Alur layanan BPJS untuk atlet.</p>
            </div>
            <div class="program-item">
                <i class="fas fa-clipboard-check"></i>
                <h3>Dokumen JKK</h3>
                <p>Dokumen pelaporan JKK.</p>
            </div>
        </div>
    </div>
    
    <!-- FAQ Section -->
    <div class="bpjs-faq">
        <div class="faq-section">
            <h3>1. Siapa yang bisa mendaftar?</h3>
            <p>BPJS Ketenagakerjaan Bakat dan Minat ditujukan untuk atlet muda usia minimal 6 tahun dan belum 15 tahun (6-14 tahun) pada saat pendaftaran. Artinya, per hari ini yang bisa mendaftar adalah anak yang lahir pada tanggal <strong>27 Januari 2011 s.d. 26 Januari 2020</strong>.</p>
            <p class="faq-note">Pendaftaran terbuka untuk seluruh Indonesia, tidak terbatas pada anak yang sudah/akan mengikuti event di Futscore.</p>
        </div>
        
        <div class="faq-section">
            <h3>2. Perlindungan dan Layanan</h3>
            <ul>
                <li><strong>Paket Basic:</strong> JKK + JKM - Perlindungan kecelakaan kerja saat latihan/pertandingan termasuk perjalanan berangkat/pulang</li>
                <li><strong>Paket Lengkap:</strong> JKK + JKM + JHT - Tambahan JHT sebagai tabungan hari tua/pensiun</li>
                <li><strong>Daftar Rumah Sakit/Faskes:</strong> <a href="https://www.bpjsketenagakerjaan.go.id/kontak.html#plkk" target="_blank" class="faskes-link">Lihat daftar rumah sakit rekanan BPJS</a></li>
            </ul>
            <p class="faq-note">Setelah resmi terdaftar, perpanjangan/iuran dapat dibayar melalui kanal online lain yang mendukung BPJS Ketenagakerjaan.</p>
        </div>
    </div>
    
   <!-- Images Gallery -->
<div class="bpjs-gallery">
    <h2 class="section-title">Galeri BPJS Ketenagakerjaan</h2>
    <div class="gallery-grid">
        <div class="gallery-item" onclick="openModal('<?php echo SITE_URL; ?>/images/bpjs/bpjs 1.jpg')">
            <img src="<?php echo SITE_URL; ?>/images/bpjs/bpjs 1.jpg" 
                 alt="BPJS Program Bakat dan Minat"
                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/default-image.jpg'">
            <div class="gallery-overlay">
                <i class="fas fa-expand"></i>
                <span>Klik untuk memperbesar</span>
            </div>
        </div>
        <div class="gallery-item" onclick="openModal('<?php echo SITE_URL; ?>/images/bpjs/bpjs 2.jpg')">
            <img src="<?php echo SITE_URL; ?>/images/bpjs/bpjs 2.jpg" 
                 alt="BPJS Pendaftaran Online"
                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/default-image.jpg'">
            <div class="gallery-overlay">
                <i class="fas fa-expand"></i>
                <span>Klik untuk memperbesar</span>
            </div>
        </div>
        <div class="gallery-item" onclick="openModal('<?php echo SITE_URL; ?>/images/bpjs/bpjs 3.jpg')">
            <img src="<?php echo SITE_URL; ?>/images/bpjs/bpjs 3.jpg" 
                 alt="BPJS Manfaat Perlindungan"
                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/default-image.jpg'">
            <div class="gallery-overlay">
                <i class="fas fa-expand"></i>
                <span>Klik untuk memperbesar</span>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal">
    <span class="modal-close" onclick="closeModal()">&times;</span>
    <div class="modal-content">
        <img id="modalImage" src="" alt="">
    </div>
    <div class="modal-caption">
        <p id="modalCaption"></p>
    </div>
</div>

<script>
// Image Modal Functionality
function openModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    
    modal.style.display = "block";
    modalImg.src = imageSrc;
    
    // Disable scroll on body when modal is open
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('imageModal');
    modal.style.display = "none";
    
    // Re-enable scroll
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside the image
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('imageModal');
    if (modal.style.display === "block") {
        if (e.key === "Escape") {
            closeModal();
        }
    }
});
</script>
    
    <!-- BPJS Form Section -->
    <div class="bpjs-form-section">
        <h2 class="section-title">
            <i class="fas fa-file-contract"></i> Formulir Pendaftaran BPJS Ketenagakerjaan
        </h2>
        
        <div class="form-notice">
            <i class="fas fa-exclamation-circle"></i>
            <p>Formulir pendaftaran tampil di bawah ini. Jika tampilan bermasalah di perangkat Anda, <a href="https://bpjstk.co/futscore" target="_blank">buka langsung di sini</a>.</p>
        </div>
        
        <!-- Form dari API BPJS -->
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
        
        <!-- Instructions -->
        <div class="form-instructions">
            <h3><i class="fas fa-question-circle"></i> Petunjuk Pengisian:</h3>
            <ol>
                <li>Isi formulir dengan data yang sesuai dengan dokumen asli (KTP/KK)</li>
                <li>Pastikan NIK yang dimasukkan benar (16 digit)</li>
                <li>Data akan langsung diproses oleh sistem BPJS Ketenagakerjaan</li>
                <li>Setelah submit, Anda akan menerima email konfirmasi dari BPJS</li>
                <li>Simpan nomor registrasi yang diberikan untuk keperluan selanjutnya</li>
            </ol>
        </div>
    </div>
    
    <!-- Contact Info -->
    <div class="contact-section">
        <h2><i class="fas fa-headset"></i> Butuh Bantuan?</h2>
        <div class="contact-grid">
            <div class="contact-item">
                <i class="fas fa-phone"></i>
                <h3>Call Center BPJS</h3>
                <p><strong>165</strong> (24 Jam)</p>
            </div>
            <div class="contact-item">
                <i class="fas fa-envelope"></i>
                <h3>Email</h3>
                <p>callcenter165@bpjs.go.id</p>
            </div>
            <div class="contact-item">
                <i class="fas fa-globe"></i>
                <h3>Website Resmi</h3>
                <p><a href="https://www.bpjsketenagakerjaan.go.id" target="_blank">bpjsketenagakerjaan.go.id</a></p>
            </div>
        </div>
    </div>
</div>

<style>
/* BPJS Page Styles */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.breadcrumb {
    margin: 20px 0;
    color: var(--gray-light);
    font-size: 14px;
}

.breadcrumb a {
    color: var(--primary-green);
    text-decoration: none;
    transition: color 0.3s ease;
}

.breadcrumb a:hover {
    color: var(--white);
}

.breadcrumb span {
    color: var(--white);
    font-weight: 600;
}

/* Header Section */
.bpjs-header {
    text-align: center;
    margin: 30px 0;
    padding: 30px;
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--gray-dark) 100%);
    border-radius: 10px;
    color: var(--white);
}

.bpjs-title {
    font-size: 2.2rem;
    margin-bottom: 10px;
    color: var(--white);
}

.bpjs-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    max-width: 800px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Introduction */
.bpjs-intro {
    background: var(--gray-dark);
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 30px;
    line-height: 1.7;
}

.bpjs-intro p {
    color: var(--white);
    margin-bottom: 20px;
    font-size: 1.05rem;
}

.bpjs-intro strong {
    color: var(--primary-green);
}

.bpjs-alert {
    background: rgba(255, 193, 7, 0.1);
    border-left: 4px solid #ffc107;
    padding: 15px;
    border-radius: 4px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    color: var(--white);
}

.bpjs-alert i {
    color: #ffc107;
    font-size: 1.2rem;
    margin-top: 2px;
}

.bpjs-alert strong {
    color: #ffc107;
}

/* Programs Section */
.section-title {
    color: var(--primary-green);
    font-size: 1.8rem;
    margin: 40px 0 25px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-green);
    display: flex;
    align-items: center;
    gap: 10px;
}

.bpjs-programs {
    margin-bottom: 40px;
}

.programs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.program-item {
    background: var(--gray-dark);
    border-radius: 10px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid var(--gray);
}

.program-item:hover {
    transform: translateY(-5px);
    border-color: var(--primary-green);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.1);
}

.program-item i {
    font-size: 2.5rem;
    color: var(--primary-green);
    margin-bottom: 15px;
}

.program-item h3 {
    color: var(--white);
    margin-bottom: 10px;
    font-size: 1.2rem;
}

.program-item p {
    color: var(--gray-light);
    font-size: 0.95rem;
    line-height: 1.5;
}

/* FAQ Section */
.bpjs-faq {
    background: var(--gray-dark);
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 40px;
}

.faq-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--gray);
}

.faq-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.faq-section h3 {
    color: var(--primary-green);
    font-size: 1.3rem;
    margin-bottom: 15px;
}

.faq-section p {
    color: var(--white);
    line-height: 1.7;
    margin-bottom: 10px;
}

.faq-section ul {
    color: var(--white);
    padding-left: 20px;
    margin: 15px 0;
}

.faq-section li {
    margin-bottom: 8px;
    line-height: 1.6;
}

.faq-section strong {
    color: var(--primary-green);
}

.faq-note {
    background: rgba(0, 255, 136, 0.1);
    padding: 12px 15px;
    border-radius: 5px;
    margin-top: 15px;
    font-size: 0.95rem;
    color: var(--gray-light);
}

.faskes-link {
    color: #4dabf7 !important;
    text-decoration: none;
    transition: color 0.3s ease;
}

.faskes-link:hover {
    color: #74c0fc !important;
    text-decoration: underline;
}

/* Gallery Section */
.bpjs-gallery {
    margin-bottom: 40px;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 25px;
}

.gallery-item {
    background: var(--gray-dark);
    border-radius: 10px;
    overflow: hidden;
    transition: transform 0.3s ease;
}

.gallery-item:hover {
    transform: translateY(-5px);
}

.gallery-item img {
    width: 100%;
    height: 550px;
    object-fit: cover;
    display: block;
}

.gallery-item p {
    padding: 15px;
    text-align: center;
    color: var(--white);
    margin: 0;
    font-weight: 500;
    background: rgba(0, 0, 0, 0.3);
}

/* Gallery Section with Hover Effect */
.bpjs-gallery {
    margin-bottom: 40px;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 25px;
}

.gallery-item {
    background: var(--gray-dark);
    border-radius: 10px;
    overflow: hidden;
    transition: transform 0.3s ease;
    cursor: pointer;
    position: relative;
    height: 550px;
}

.gallery-item:hover {
    transform: translateY(-5px);
}

.gallery-item:hover .gallery-overlay {
    opacity: 1;
}

.gallery-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}

.gallery-item:hover img {
    transform: scale(1.05);
}

.gallery-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    opacity: 0;
    transition: opacity 0.3s ease;
    text-align: center;
    padding: 20px;
}

.gallery-overlay i {
    font-size: 2.5rem;
    margin-bottom: 15px;
    color: var(--primary-green);
}

.gallery-overlay span {
    font-size: 1rem;
    font-weight: 500;
}

/* Image Modal Styles */
.image-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    padding-top: 60px;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.9);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 35px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s ease;
    z-index: 1001;
    line-height: 40px;
    width: 40px;
    height: 40px;
    text-align: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
}

.modal-close:hover {
    color: var(--primary-green);
    background: rgba(255, 255, 255, 0.2);
}

.modal-content {
    margin: auto;
    display: block;
    width: 80%;
    max-width: 1200px;
    height: calc(100vh - 140px);
    position: relative;
}

.modal-content img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
    border-radius: 5px;
    background: #000;
}

.modal-caption {
    text-align: center;
    color: white;
    padding: 20px;
    font-size: 1.1rem;
    margin-top: 10px;
}

/* Responsive Modal */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        height: calc(100vh - 160px);
    }
    
    .modal-close {
        top: 10px;
        right: 20px;
        font-size: 35px;
    }
    
    .gallery-item {
        height: 400px;
    }
}

@media (max-width: 480px) {
    .modal-content {
        width: 100%;
        height: calc(100vh - 180px);
    }
    
    .modal-close {
        top: 10px;
        right: 15px;
        font-size: 30px;
    }
    
    .gallery-item {
        height: 300px;
    }
    
    .gallery-overlay i {
        font-size: 2rem;
    }
    
    .gallery-overlay span {
        font-size: 0.9rem;
    }
}

/* Add smooth scrolling for whole page */
html {
    scroll-behavior: smooth;
}

/* Form Section */
.bpjs-form-section {
    margin-bottom: 40px;
}

.form-notice {
    background: rgba(255, 87, 87, 0.1);
    border: 1px solid #ff5757;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.form-notice i {
    color: #ff5757;
    font-size: 1.2rem;
}

.form-notice p {
    color: var(--white);
    margin: 0;
}

.form-notice a {
    color: #4dabf7;
    text-decoration: none;
}

.form-notice a:hover {
    text-decoration: underline;
}

.form-wrapper {
    background: var(--gray-dark);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 25px;
    border: 1px solid var(--gray);
}

.bpjs-iframe {
    width: 100%;
    height: 800px;
    border: none;
    display: block;
}

.form-instructions {
    background: rgba(0, 123, 255, 0.1);
    border-radius: 10px;
    padding: 25px;
}

.form-instructions h3 {
    color: #4dabf7;
    margin-bottom: 20px;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-instructions ol {
    color: var(--white);
    padding-left: 20px;
    margin: 0;
}

.form-instructions li {
    margin-bottom: 12px;
    line-height: 1.6;
}

/* Contact Section */
.contact-section {
    margin-bottom: 50px;
}

.contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 25px;
}

.contact-item {
    background: var(--gray-dark);
    border-radius: 10px;
    padding: 25px;
    text-align: center;
    transition: transform 0.3s ease;
}

.contact-item:hover {
    transform: translateY(-5px);
}

.contact-item i {
    font-size: 2.2rem;
    color: var(--primary-green);
    margin-bottom: 15px;
}

.contact-item h3 {
    color: var(--white);
    margin-bottom: 10px;
    font-size: 1.2rem;
}

.contact-item p {
    color: var(--gray-light);
    margin: 0;
}

.contact-item a {
    color: #4dabf7;
    text-decoration: none;
}

.contact-item a:hover {
    text-decoration: underline;
}

/* Responsive Design */
@media (max-width: 992px) {
    .bpjs-title {
        font-size: 1.8rem;
    }
    
    .section-title {
        font-size: 1.5rem;
    }
    
    .programs-grid,
    .gallery-grid,
    .contact-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .container {
        padding: 0 15px;
    }
    
    .bpjs-header {
        padding: 20px;
    }
    
    .bpjs-title {
        font-size: 1.6rem;
    }
    
    .bpjs-subtitle {
        font-size: 1rem;
    }
    
    .programs-grid,
    .gallery-grid,
    .contact-grid {
        grid-template-columns: 1fr;
    }
    
    .bpjs-iframe {
        height: 600px;
    }
    
    .faq-section h3 {
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .bpjs-title {
        font-size: 1.4rem;
    }
    
    .section-title {
        font-size: 1.3rem;
    }
    
    .bpjs-intro,
    .bpjs-faq,
    .form-instructions {
        padding: 20px;
    }
    
    .program-item,
    .contact-item {
        padding: 20px;
    }
    
    .bpjs-iframe {
        height: 500px;
    }
    
    .gallery-item img {
        height: 200px;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>