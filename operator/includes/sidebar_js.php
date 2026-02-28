<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.menu-overlay');

    if (menuToggle && sidebar && overlay) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            document.body.classList.remove('menu-open');
        });

        const menuLinks = document.querySelectorAll('.menu-link');
        menuLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                sidebar.classList.remove('active');
                document.body.classList.remove('menu-open');
            });
        });
    }
});
</script>
