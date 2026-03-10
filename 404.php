<?php
require_once 'includes/functions.php';

$hideNavbars = true;
$pageTitle = "404 - Halaman Tidak Ditemukan";
$extraStyles = [
    '<link rel="stylesheet" href="' . SITE_URL . '/css/redesign_core.css?v=' . getAssetVersion('/css/redesign_core.css') . '">',
    '<link rel="stylesheet" href="' . SITE_URL . '/css/index_redesign.css?v=' . getAssetVersion('/css/index_redesign.css') . '">'
];
require_once 'includes/header.php';
?>

<style>
/* PREMIUM LIGHT ATHLETIC 404 AESTHETIC */
.error-dashboard-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 20px;
    background-color: var(--white);
    background-image: 
        url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.04'/%3E%3C/svg%3E"),
        linear-gradient(135deg, var(--white-blue) 0%, var(--gray-50) 50%, var(--white) 100%);
    position: relative;
    overflow: hidden;
    isolation: isolate;
}

/* Abstract Light Court Lines */
.error-dashboard-wrapper::before {
    content: '';
    position: absolute;
    inset: 0;
    background: 
        linear-gradient(90deg, rgba(59, 130, 246, 0.03) 1px, transparent 1px) 0 0 / 60px 60px,
        linear-gradient(0deg, rgba(59, 130, 246, 0.03) 1px, transparent 1px) 0 0 / 60px 60px;
    transform: perspective(800px) rotateX(45deg) translateY(-50px) translateZ(-100px);
    z-index: -2;
    pointer-events: none;
}

/* Soft glowing orbs */
.error-dashboard-wrapper::after {
    content: '';
    position: absolute;
    width: 60vw;
    height: 60vw;
    max-width: 800px;
    max-height: 800px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.08) 0%, transparent 60%);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: -1;
    pointer-events: none;
    animation: slowPulse 8s ease-in-out infinite alternate;
}

@keyframes slowPulse {
    0% { transform: translate(-50%, -50%) scale(0.9); opacity: 0.7; }
    100% { transform: translate(-50%, -50%) scale(1.1); opacity: 1; }
}

.light-error-container {
    text-align: center;
    position: relative;
    z-index: 10;
    max-width: 600px;
    width: 100%;
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    padding: 60px 40px;
    border-radius: 32px;
    border: 1px solid rgba(255, 255, 255, 0.8);
    box-shadow: 0 20px 60px rgba(10, 22, 40, 0.04), inset 0 0 0 1px rgba(255, 255, 255, 0.5);
    animation: fadeUpIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeUpIn {
    0% { opacity: 0; transform: translateY(40px); }
    100% { opacity: 1; transform: translateY(0); }
}

.icon-wrapper {
    font-size: clamp(48px, 8vw, 64px);
    color: var(--blue-primary);
    margin-bottom: clamp(16px, 4vw, 24px);
    display: inline-block;
    animation: floatIcon 4s ease-in-out infinite;
    filter: drop-shadow(0 10px 15px rgba(59, 130, 246, 0.2));
}

@keyframes floatIcon {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
}

.light-404 {
    font-size: clamp(72px, 18vw, 140px);
    font-weight: 900;
    line-height: 1;
    margin: 0;
    background: linear-gradient(135deg, var(--blue-primary) 0%, var(--blue-accent) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: clamp(-3px, -1vw, -6px);
    text-shadow: 0 15px 30px rgba(59, 130, 246, 0.15);
    position: relative;
    display: inline-block;
}

.light-404::after {
    content: '';
    position: absolute;
    bottom: clamp(-6px, -1vw, -12px);
    left: 50%;
    transform: translateX(-50%);
    width: clamp(40px, 10vw, 60px);
    height: clamp(4px, 1vw, 6px);
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
    border-radius: 6px;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
}

.error-title {
    font-size: clamp(22px, 5vw, 28px);
    font-weight: 800;
    color: var(--navy-dark);
    text-transform: uppercase;
    letter-spacing: clamp(1px, 0.5vw, 2px);
    margin-top: clamp(24px, 5vw, 32px);
    margin-bottom: clamp(12px, 3vw, 16px);
}

.error-desc {
    color: var(--gray-600);
    font-size: clamp(14px, 3vw, 16px);
    line-height: 1.6;
    margin-bottom: clamp(24px, 6vw, 40px);
    max-width: 440px;
    margin-left: auto;
    margin-right: auto;
    font-weight: 500;
    padding: 0 10px;
}

.light-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: clamp(16px, 4vw, 18px) clamp(24px, 6vw, 40px);
    background: linear-gradient(135deg, var(--navy) 0%, var(--blue-primary) 100%);
    color: white;
    font-size: clamp(13px, 3vw, 14px);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-decoration: none;
    border-radius: clamp(12px, 3vw, 16px);
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 12px 24px rgba(15, 39, 68, 0.15), 0 4px 8px rgba(15, 39, 68, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.1);
    min-height: 48px;
}

.light-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--blue-primary) 0%, var(--blue-light) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 1;
}

.light-btn span, .light-btn i {
    z-index: 2;
    position: relative;
}

.light-btn:hover, .light-btn:active {
    transform: translateY(-2px);
    box-shadow: 0 16px 32px rgba(15, 39, 68, 0.25), 0 8px 16px rgba(15, 39, 68, 0.15);
    color: white;
}

.light-btn:hover::before, .light-btn:active::before {
    opacity: 1;
}

.light-btn i {
    font-size: clamp(16px, 4vw, 18px);
    color: var(--gold-light);
    transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.light-btn:hover i {
    transform: translateX(-4px) scale(1.1);
}

/* Floating decorative elements */
.deco-shape {
    position: absolute;
    background: linear-gradient(135deg, var(--blue-light) 0%, var(--blue-accent) 100%);
    border-radius: 50%;
    filter: blur(8px);
    opacity: 0.1;
    z-index: -1;
    animation: floatShape 10s ease-in-out infinite;
}

.shape-1 {
    width: clamp(80px, 20vw, 150px);
    height: clamp(80px, 20vw, 150px);
    top: -10%;
    right: -5%;
    animation-delay: 0s;
}

.shape-2 {
    width: clamp(60px, 15vw, 100px);
    height: clamp(60px, 15vw, 100px);
    bottom: -5%;
    left: -5%;
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
    animation-delay: -5s;
    animation-duration: 8s;
}

@keyframes floatShape {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-30px) rotate(10deg); }
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .error-dashboard-wrapper {
        min-height: calc(100vh - 60px);
        height: auto;
    }
}

@media (max-width: 768px) {
    .light-error-container {
        padding: 40px 24px;
        border-radius: 24px;
    }
}

@media (max-width: 480px) {
    .error-dashboard-wrapper {
        padding: 16px;
    }
    
    .light-error-container {
        padding: 40px 20px;
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.6);
        box-shadow: 0 10px 40px rgba(10, 22, 40, 0.05), inset 0 0 0 1px rgba(255, 255, 255, 0.4);
    }
    
    .shape-1 {
        top: -5%;
        right: -10%;
    }
    
    .shape-2 {
        bottom: 0%;
        left: -10%;
    }
}
</style>

<div class="dashboard-wrapper">
<?php 
$currentPage = '404';
include 'includes/sidebar.php'; 
?>

    <!-- Main Content -->
    <div class="main-content-dashboard">
        <div class="error-dashboard-wrapper">
            <div class="deco-shape shape-1"></div>
            <div class="deco-shape shape-2"></div>
            
            <div class="light-error-container">
                <div class="icon-wrapper">
                    <i class="fas fa-ghost"></i>
                </div>
                <div>
                    <h1 class="light-404">404</h1>
                </div>
                <h2 class="error-title">OFFSIDE!</h2>
                <p class="error-desc">Halaman yang Anda cari sedang tidak tersedia atau telah dipindahkan ke sisi lapangan yang lain.</p>
                
                <a href="<?php echo SITE_URL; ?>" class="light-btn">
                    <span>KEMBALI KE BERANDA</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
</script>

<?php require_once 'includes/footer.php'; ?>
