<?php
require_once 'includes/header.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$pageTitle = "Contact";
?>

<div class="container">
    <!-- Header Section -->
    <div class="contact-header">
        <h1 class="contact-title">Hubungi Kami</h1>
        <p class="contact-subtitle">Tim MGP siap membantu operasional dan informasi event di seluruh Indonesia</p>
    </div>
    
    <div class="contact-layout">
        <!-- Main Content -->
        <div class="contact-main">
            <!-- Contact Center -->
            <div class="contact-section">
                <div class="section-card">
                    <h2><i class="fas fa-headset"></i> Kontak Pusat</h2>
                    <div class="contact-info-grid">
                        <div class="contact-info-item">
                            <i class="fas fa-building"></i>
                            <div>
                                <h3>Gema Digital</h3>
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
                                <a href="https://instagram.com/futscore.id" target="_blank" class="contact-link">Kunjungi Instagram</a>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <i class="fab fa-youtube"></i>
                            <div>
                                <h3>YouTube</h3>
                                <p>@futscoreindonesia4634</p>
                                <a href="https://youtube.com/@futscoreindonesia4634" target="_blank" class="contact-link">Kunjungi YouTube</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- DC Regional List -->
            <div class="contact-section">
                <div class="section-card">
                    <h2><i class="fas fa-map-marker-alt"></i> Daftar DC Wilayah</h2>
                    <p class="section-description">Tim Data Center (DC) MGP siap membantu operasional dan informasi event di area masing-masing. Waktu respons rata-rata: 1x24 jam kerja. Untuk kebutuhan mendesak, hubungi DC mana pun yang tersedia.</p>
                    
                    <div class="dc-grid">
                        <!-- DC Batam -->
                        <div class="dc-card" onclick="openWhatsApp('628117757222', 'Alfin', 'DC Batam')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <!-- DC Banten -->
                        <div class="dc-card" onclick="openWhatsApp('6282125434723', 'Agus', 'DC Banten')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <!-- DC DKI Jakarta -->
                        <div class="dc-card" onclick="openWhatsApp('6282186582328', 'Detang', 'DC DKI Jakarta')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <!-- DC Jawa Barat -->
                        <div class="dc-card" onclick="openWhatsApp('6283182526542', 'Abu', 'DC Jawa Barat')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <!-- DC Jawa Tengah -->
                        <div class="dc-card" onclick="openWhatsApp('6289607665222', 'Mahfudin', 'DC Jawa Tengah')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <!-- DC Jawa Timur -->
                        <div class="dc-card" onclick="openWhatsApp('6281336681197', 'Tyas', 'DC Jawa Timur')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <!-- DC Sumsel -->
                        <div class="dc-card" onclick="openWhatsApp('62895341836843', 'Hanif', 'DC Sumsel')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <!-- DC Kalteng -->
                        <div class="dc-card" onclick="openWhatsApp('628981434528', 'Robert', 'DC Kalteng')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <!-- DC Kaltim -->
                        <div class="dc-card" onclick="openWhatsApp('6282154000055', 'Phandi', 'DC Kaltim')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <!-- DC Ternate -->
                        <div class="dc-card" onclick="openWhatsApp('6282194754209', 'Takeshi', 'DC Ternate')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                        
                        <!-- DC General Support -->
                        <div class="dc-card" onclick="openWhatsApp('6288294336553', 'Ale', 'DC General Support')">
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
                                <button class="dc-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dc-note">
                        <i class="fas fa-info-circle"></i>
                        <p><strong>Catatan:</strong> Jika area Anda belum tersedia, silakan hubungi siapa pun dari daftar DC di atas atau kontak pusat.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WhatsApp API Script -->
<script>
// Function to open WhatsApp with predefined message
function openWhatsApp(phoneNumber, personName, region) {
    const cleanPhone = phoneNumber.replace(/\D/g, '');
    const message = `Halo ${personName} (${region}),\n\nSaya ingin bertanya terkait informasi yang ada di wilayah ${region}.\n\nTerima kasih.`;
    const encodedMessage = encodeURIComponent(message);
    const whatsappURL = `https://wa.me/${cleanPhone}?text=${encodedMessage}`;
    window.open(whatsappURL, '_blank');
}

// Add click event to WhatsApp buttons
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
    
    // Add hover effects to DC cards
    const dcCards = document.querySelectorAll('.dc-card');
    dcCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script>

<style>
/* Contact Page Styles */
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* Header Section */
.contact-header {
    text-align: center;
    margin: 30px 0 40px;
    padding: 30px;
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--gray-dark) 100%);
    border-radius: 10px;
    color: var(--white);
}

.contact-title {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: var(--white);
}

.contact-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    max-width: 800px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Layout */
.contact-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: 30px;
    margin-bottom: 50px;
}

.contact-section {
    margin-bottom: 40px;
}

.section-card {
    background: var(--gray-dark);
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.section-card h2 {
    color: var(--primary-green);
    font-size: 1.5rem;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--gray);
}

.section-description {
    color: var(--white);
    line-height: 1.6;
    margin-bottom: 25px;
    padding: 15px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    border-left: 4px solid var(--primary-green);
}

/* Contact Info Grid */
.contact-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.contact-info-item {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    transition: transform 0.3s ease;
}

.contact-info-item:hover {
    transform: translateY(-3px);
}

.contact-info-item i {
    font-size: 1.8rem;
    color: var(--primary-green);
    margin-top: 5px;
    flex-shrink: 0;
}

.contact-info-item h3 {
    color: var(--white);
    margin-bottom: 8px;
    font-size: 1.1rem;
}

.contact-info-item p {
    color: var(--gray-light);
    margin-bottom: 10px;
    font-size: 0.95rem;
    line-height: 1.5;
}

.contact-link {
    display: inline-block;
    color: #4dabf7;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    transition: color 0.3s ease;
}

.contact-link:hover {
    color: #74c0fc;
    text-decoration: underline;
}

/* DC Grid */
.dc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.dc-card {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 10px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid var(--gray);
    position: relative;
    overflow: hidden;
}

.dc-card:hover {
    border-color: var(--primary-green);
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.1);
}

.dc-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--primary-green);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.dc-card:hover::before {
    transform: scaleX(1);
}

.dc-header {
    display: flex;  
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.dc-header h3 {
    color: var(--white);
    margin: 0;
    font-size: 1.2rem;
}

.dc-badge {
    background: var(--primary-green);
    color: var(--black);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.dc-badge.support {
    background: linear-gradient(135deg, #4dabf7, #228be6);
    color: white;
}

.dc-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.dc-person, .dc-phone {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--white);
}

.dc-person i, .dc-phone i {
    color: var(--primary-green);
    width: 20px;
    text-align: center;
}

.dc-whatsapp {
    background: linear-gradient(135deg, #25d366, #128c7e);
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 10px;
}

.dc-whatsapp:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(37, 211, 102, 0.3);
}

.dc-note {
    background: rgba(255, 193, 7, 0.1);
    border-left: 4px solid #ffc107;
    padding: 15px;
    border-radius: 4px;
    margin-top: 25px;
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.dc-note i {
    color: #ffc107;
    font-size: 1.2rem;
    margin-top: 2px;
}

.dc-note p {
    color: var(--white);
    margin: 0;
    font-size: 0.95rem;
}

.dc-note strong {
    color: #ffc107;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .dc-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .contact-title {
        font-size: 2rem;
    }
    
    .dc-grid {
        grid-template-columns: 1fr;
    }
    
    .contact-header {
        padding: 20px;
    }
    
    .section-card {
        padding: 20px;
    }
    
    .contact-info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .contact-title {
        font-size: 1.6rem;
    }
    
    .contact-subtitle {
        font-size: 1rem;
    }
    
    .dc-card {  
        padding: 15px;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>