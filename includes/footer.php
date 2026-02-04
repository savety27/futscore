    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>ENTANG FUTSCORE</h3>
                    <p>Futscore adalah aplikasi berbasis web yang berfokus pada pembinaan atlet dengan menyimpan data history pemain, team, dan event secara lengkap. Data Futscore dibuat dan dipelihara dengan kolaborasi admin, para Coach team, dan Event Organizer. Futscore juga mendukung Klub olahraga untuk manajemen kepelatihan.</p>
                </div>
                
                <div class="footer-section">
                    <h3>BERITA TERBARU</h3>
                    <ul class="footer-news">
                        <?php $latestNewsFooter = getLatestNews(2); ?>
                        <?php foreach ($latestNewsFooter as $news): ?>
                        <li>
                            <a href="<?php echo SITE_URL; ?>/news/<?php echo $news['slug']; ?>">
                                <?php echo $news['judul']; ?>
                            </a>
                            <span><?php echo formatDate($news['created_at']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>LINKS</h3>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/event.php"><i class="fas fa-calendar-alt"></i> Event</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/team.php"><i class="fas fa-users"></i> Team</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/player.php"><i class="fas fa-user"></i> Player</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/news.php"><i class="fas fa-newspaper"></i> News</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="footer-social">
                    <a href="https://www.youtube.com/@futscoreindonesia4634" target="_blank" class="social-icon youtube">
                        <i class="fab fa-youtube"></i>
                    </a>
                    <a href="https://www.instagram.com/futscore.id/" target="_blank" class="social-icon instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
                <div class="copyright">
                    2026 by Tim IT RPL
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        // Define SITE_URL for JavaScript
        const SITE_URL = '<?php echo SITE_URL; ?>';
    </script>
    <script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>