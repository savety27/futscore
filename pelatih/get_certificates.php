<?php
require_once 'config/database.php';

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
        echo '<div style="text-align: center; padding: 20px; color: var(--gray);">Sertifikat tidak ditemukan untuk staf ini</div>';
    } else {
        foreach ($certificates as $cert) {
            echo '<div class="certificate-item">';
            echo '<div class="certificate-info">';
            echo '<h4>' . htmlspecialchars($cert['certificate_name'] ?? '') . '</h4>';
            echo '<p><strong>Lembaga Penerbit:</strong> ' . htmlspecialchars($cert['issuing_authority'] ?? '') . '</p>';
            echo '<p><strong>Tanggal Terbit:</strong> ' . htmlspecialchars($cert['issue_date'] ?? '') . '</p>';
            echo '</div>';
            
            // Tampilkan gambar sertifikat
            $certificate_file = basename($cert['certificate_file']);
            echo '<img src="../uploads/certificates/' . $certificate_file . '" 
                  alt="' . htmlspecialchars($cert['certificate_name'] ?? '') . '" 
                  class="certificate-image"
                  onerror="this.onerror=null; this.src=\'../images/default-certificate.png\'">';
            echo '</div>';
        }
    }
    
} catch (PDOException $e) {
    echo '<div style="text-align: center; padding: 20px; color: var(--danger);">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>