<script>
document.addEventListener('DOMContentLoaded', function() {
    const MOBILE_BREAKPOINT = 768;
    const TRANSITION_LOCK_MS = 220;

    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.menu-overlay');
    const menuRoot = sidebar ? sidebar.querySelector('.menu') : null;
    let isTransitionLocked = false;

    if (!menuToggle || !sidebar || !overlay) {
        return;
    }

    function setToggleIcon(isOpen) {
        const icon = menuToggle.querySelector('i');
        if (!icon) {
            return;
        }

        if (isOpen) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
            return;
        }

        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
    }

    function setMenuState(isOpen) {
        sidebar.classList.toggle('active', isOpen);
        document.body.classList.toggle('menu-open', isOpen);
        setToggleIcon(isOpen);
    }

    function lockTransition() {
        isTransitionLocked = true;
        setTimeout(function() {
            isTransitionLocked = false;
        }, TRANSITION_LOCK_MS);
    }

    menuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        if (isTransitionLocked) {
            return;
        }

        lockTransition();
        setMenuState(!sidebar.classList.contains('active'));
    });

    overlay.addEventListener('click', function() {
        setMenuState(false);
    });

    document.addEventListener('click', function(e) {
        if (window.innerWidth > MOBILE_BREAKPOINT || !sidebar.classList.contains('active')) {
            return;
        }

        if (sidebar.contains(e.target) || menuToggle.contains(e.target)) {
            return;
        }

        setMenuState(false);
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            setMenuState(false);
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > MOBILE_BREAKPOINT) {
            setMenuState(false);
        }
    });

    if (menuRoot) {
        menuRoot.addEventListener('click', function(e) {
            const menuLink = e.target.closest('.menu-link');
            if (!menuLink || !menuRoot.contains(menuLink)) {
                return;
            }

            const arrow = menuLink.querySelector('.menu-arrow');
            if (arrow) {
                e.preventDefault();
                const submenu = menuLink.nextElementSibling;
                if (!submenu) {
                    return;
                }

                submenu.classList.toggle('open');
                arrow.classList.toggle('rotate');
                return;
            }

            if (window.innerWidth <= MOBILE_BREAKPOINT) {
                setMenuState(false);
            }
        });
    }

    document.querySelectorAll('.submenu-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= MOBILE_BREAKPOINT) {
                setMenuState(false);
            }
        });
    });
});
</script>
