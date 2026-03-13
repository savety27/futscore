<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) {
        return;
    }

    const toggles = document.querySelectorAll('.mobile-menu-toggle');
    const overlays = document.querySelectorAll('.menu-overlay');
    const menuLinks = document.querySelectorAll('.menu-link');

    function setMenuOpen(isOpen) {
        if (isOpen) {
            sidebar.classList.add('active');
            document.body.classList.add('menu-open');
            overlays.forEach(o => o.classList.add('active'));
        } else {
            sidebar.classList.remove('active');
            document.body.classList.remove('menu-open');
            overlays.forEach(o => o.classList.remove('active'));
        }
    }

    if (toggles.length > 0) {
        toggles.forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const isOpen = !sidebar.classList.contains('active');
                setMenuOpen(isOpen);
            });
        });
    }

    if (overlays.length > 0) {
        overlays.forEach(function(overlay) {
            overlay.addEventListener('click', function() {
                setMenuOpen(false);
            });
        });
    }

    if (menuLinks.length > 0) {
        menuLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                setMenuOpen(false);
            });
        });
    }
});
</script>
