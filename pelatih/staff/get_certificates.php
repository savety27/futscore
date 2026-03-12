<?php
require_once '../config/database.php';

// Pastikan staff_id dikirim
if (!isset($_GET['staff_id']) || empty($_GET['staff_id'])) {
    echo '<div style="text-align: center; padding: 20px; color: var(--danger);">ID staf tidak ditemukan</div>';
    exit;
}

$staff_id = (int)$_GET['staff_id'];

try {
    // Query untuk mengambil sertifikat staff
    $stmt = $conn->prepare("
        SELECT certificate_name, certificate_file, issue_date, issuing_authority 
        FROM staff_certificates 
        WHERE staff_id = ? 
        ORDER BY issue_date DESC
    ");
    $stmt->execute([$staff_id]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($certificates)) {
        echo '<div style="text-align: center; padding: 40px 20px; color: var(--heritage-text-muted); font-family: var(--font-body);">';
        echo '<i class="fas fa-certificate" style="font-size: 48px; margin-bottom: 16px; opacity: 0.2;"></i>';
        echo '<p style="font-weight: 600; font-size: 1.1rem;">Sertifikat tidak ditemukan</p>';
        echo '<p style="font-size: 0.9rem; opacity: 0.7;">Staf ini belum memiliki sertifikat terdaftar.</p>';
        echo '</div>';
    } else {
        echo '<div class="certificates-grid">';
        foreach ($certificates as $cert) {
            echo '<div class="certificate-card">';
            
            echo '<div class="certificate-header">';
            echo '    <div class="cert-icon-wrapper">';
            echo '        <i class="fas fa-award"></i>';
            echo '    </div>';
            echo '    <div class="cert-title-group">';
            echo '        <h4 class="cert-name">' . htmlspecialchars($cert['certificate_name'] ?? '') . '</h4>';
            echo '        <span class="cert-date"><i class="far fa-calendar-alt"></i> ' . date('d M Y', strtotime($cert['issue_date'])) . '</span>';
            echo '    </div>';
            echo '</div>';
            
            echo '<div class="certificate-details">';
            echo '    <div class="detail-row">';
            echo '        <span class="detail-label">Lembaga Penerbit</span>';
            echo '        <span class="detail-value">' . htmlspecialchars($cert['issuing_authority'] ?? '') . '</span>';
            echo '    </div>';
            echo '</div>';
            
            // Tampilkan gambar sertifikat
            $certificate_file = basename($cert['certificate_file']);
            echo '<div class="certificate-preview">';
            echo '    <img src="../../uploads/certificates/' . $certificate_file . '" 
                      alt="' . htmlspecialchars($cert['certificate_name'] ?? '') . '" 
                      class="certificate-image"
                      onerror="this.onerror=null; this.src=\'../../images/default-certificate.png\'">';
            echo '    <div class="preview-overlay">';
            echo '        <a href="../../uploads/certificates/' . $certificate_file . '" target="_blank" class="btn-view-full"><i class="fas fa-expand"></i> Lihat Full Size</a>';
            echo '    </div>';
            echo '</div>';
            
            echo '</div>'; // close certificate-card
        }
        echo '</div>'; // close certificates-grid
    }
    
} catch (PDOException $e) {
    echo '<div style="text-align: center; padding: 20px; color: var(--danger);">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
