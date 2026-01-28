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
                                <?php echo $news['title']; ?>
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

    <!-- Floating Chat Button -->
    <button class="chat-button" id="chatButton">
        <i class="fas fa-comment"></i>
    </button>

    <!-- Chat Modal -->
    <div class="chat-modal" id="chatModal">
        <div class="chat-modal-header">
            <h3>Hubungi Kami</h3>
            <button class="chat-close" id="chatClose">&times;</button>
        </div>
        <div class="chat-modal-body">
            <p>Mohon isi formulir di bawah dan kami akan membalasnya sesegera mungkin.</p>
            <form id="contactForm">
                <div class="form-group">
                    <label for="name">* Nama</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">* Email <span class="required-note">Bagian ini diperlukan</span></label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="message">* Pesan</label>
                    <textarea id="message" name="message" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn-submit">Kirim</button>
            </form>
            <div class="chat-buttons">
                <button class="btn-back">Kembali</button>
                <button class="btn-messages">Messages</button>
            </div>
            <div class="chat-history">
                <h4>Recent</h4>
                <p class="no-conversations">No recent conversations</p>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo SITE_URL; ?>/js/script.js"></script>
    <script>
        // News ticker functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tickerItems = document.querySelectorAll('.ticker-item');
            let currentIndex = 0;
            
            function showNextNews() {
                tickerItems[currentIndex].classList.remove('active');
                currentIndex = (currentIndex + 1) % tickerItems.length;
                tickerItems[currentIndex].classList.add('active');
            }
            
            // Auto rotate every 5 seconds
            setInterval(showNextNews, 5000);
            
            // Manual controls
            document.querySelector('.ticker-next')?.addEventListener('click', showNextNews);
            
            document.querySelector('.ticker-prev')?.addEventListener('click', function() {
                tickerItems[currentIndex].classList.remove('active');
                currentIndex = (currentIndex - 1 + tickerItems.length) % tickerItems.length;
                tickerItems[currentIndex].classList.add('active');
            });
            
            // Chat modal
            const chatButton = document.getElementById('chatButton');
            const chatModal = document.getElementById('chatModal');
            const chatClose = document.getElementById('chatClose');
            
            if (chatButton && chatModal) {
                chatButton.addEventListener('click', function() {
                    chatModal.style.display = 'block';
                });
                
                chatClose.addEventListener('click', function() {
                    chatModal.style.display = 'none';
                });
                
                window.addEventListener('click', function(event) {
                    if (event.target === chatModal) {
                        chatModal.style.display = 'none';
                    }
                });
            }
        });
          // Define SITE_URL for JavaScript
        const SITE_URL = '<?php echo SITE_URL; ?>';
        console.log('SITE_URL set to:', SITE_URL);
    </script>
     <script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>

     <!-- Chat Button -->
<button class="chat-button" id="chatButton">
    <i class="fas fa-comment-alt"></i>
</button>

<!-- Chat Modal -->
<div class="chat-modal" id="chatModal">
    <div class="chat-modal-body">
        <div class="chat-modal-header">
            <h3>Live Chat Support</h3>
            <button class="chat-close" id="chatClose">&times;</button>
        </div>
        <p>Have questions? Our team is here to help!</p>
        <form id="contactForm">
            <div class="form-group">
                <label for="name">Name <span class="required-note">*</span></label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email <span class="required-note">*</span></label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="message">Message <span class="required-note">*</span></label>
                <textarea id="message" name="message" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn-submit">Send Message</button>
        </form>
    </div>
</div>
</body>
</html>