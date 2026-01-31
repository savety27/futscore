<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}
// File untuk membersihkan file-file setup yang tidak perlu

echo "<h2>🧹 File Setup Cleanup</h2>";
echo "<p>File-file setup telah berhasil dibuat dan database sudah siap digunakan.</p>";
echo "<p>Untuk keamanan, Anda dapat menghapus file-file berikut:</p>";
echo "<ul>";
echo "<li>admin/setup_database.php</li>";
echo "<li>admin/check_admin.php</li>";
echo "<li>admin/reset_password.php</li>";
echo "<li>admin/cleanup.php (file ini)</li>";
echo "</ul>";
echo "<p>📁 File-file penting yang harus dipertahankan:</p>";
echo "<ul>";
echo "<li>admin/config/database.php</li>";
echo "<li>login.php (was admin/index.php)</li>";
echo "<li>admin/dashboard.php</li>";
echo "<li>admin/logout.php</li>";
echo "</ul>";
echo "<p>✅ Sistem admin sudah siap digunakan!</p>";
?>