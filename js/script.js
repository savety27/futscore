// Global Variables
console.log('--- Dynamic Match Details Logic Loaded (v1.1) ---');
let currentMatchId = null;

// Match Detail Modal Data (Now Dynamic)
let matchDetailsCache = {};
let scheduleModalData = {};

// Match Modal Functions
function openMatchModal(matchId) {
    console.log('Opening match modal for ID:', matchId);
    currentMatchId = matchId;

    // Show loading state if needed (optional)

    // Fetch dynamic data
    const fetchUrl = `${SITE_URL}/get_match_detail.php?id=${matchId}`;
    console.log('Fetching from:', fetchUrl);
    fetch(fetchUrl)
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                alert(result.message || 'Match details not available');
                return;
            }

            const matchDetail = result.data;

            // Populate modal with match data
            document.getElementById('matchModalTitle').textContent = matchDetail.title;

            // Set team names and logos
            document.getElementById('team1Name').textContent = matchDetail.team1;
            document.getElementById('team2Name').textContent = matchDetail.team2;
            document.getElementById('team1NameLineup').textContent = matchDetail.team1;
            document.getElementById('team2NameLineup').textContent = matchDetail.team2;

            const team1Logo = document.getElementById('team1LogoLarge');
            const team2Logo = document.getElementById('team2LogoLarge');

            team1Logo.src = SITE_URL + '/images/teams/' + matchDetail.team1_logo;
            team2Logo.src = SITE_URL + '/images/teams/' + matchDetail.team2_logo;

            // Set fallback for logos
            team1Logo.onerror = function () {
                this.src = SITE_URL + '/images/teams/default-team.png';
            };
            team2Logo.onerror = function () {
                this.src = SITE_URL + '/images/teams/default-team.png';
            };

            // Set score
            document.getElementById('matchScoreLarge').textContent = matchDetail.score;

            // Set date and location
            document.getElementById('matchDateTime').textContent = matchDetail.date + ', ' + matchDetail.time;
            document.getElementById('matchLocation').textContent = matchDetail.location;

            // Populate content
            populateGoals(matchDetail.goals);
            populateLineups(matchDetail.lineups);

            // Show modal
            document.getElementById('matchModal').style.display = 'block';
            document.body.style.overflow = 'hidden';

            // Reset to Goals tab
            switchMatchTab('goals');
        })
        .catch(error => {
            console.error('Error fetching match details:', error);
            alert('An error occurred while loading match details.');
        });
}

function closeMatchModal() {
    document.getElementById('matchModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    currentMatchId = null;
}

function switchMatchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.match-tab-content').forEach(tab => {
        tab.classList.remove('active');
    });

    // Remove active class from all tabs
    document.querySelectorAll('.match-tab').forEach(tab => {
        tab.classList.remove('active');
    });

    // Show selected tab content
    document.getElementById(tabName + 'Content').classList.add('active');

    // Add active class to selected tab
    document.querySelector(`.match-tab[data-tab="${tabName}"]`).classList.add('active');
}

function populateGoals(goals) {
    const goalsList = document.getElementById('goalsList');
    goalsList.innerHTML = '';
    goalsList.classList.add('pro-goals-container');

    if (!goals || goals.length === 0) {
        goalsList.innerHTML = '<div class="no-data">Belum ada gol tercipta</div>';
        return;
    }

    goals.forEach(goal => {
        const goalItem = document.createElement('div');
        goalItem.className = 'goal-row';

        const isTeam1 = goal.team === 'team1';

        // Structure: [Team 1 Content] [Time] [Team 2 Content]

        let team1Content = '';
        let team2Content = '';

        const playerHtml = `
            <div class="goal-details">
                <span class="goal-player-name">${goal.player}</span>
                <span class="goal-icon">⚽</span>
                ${goal.number ? `<span class="goal-player-number">(${goal.number})</span>` : ''}
            </div>
        `;

        if (isTeam1) {
            team1Content = playerHtml;
        } else {
            team2Content = playerHtml;
        }

        goalItem.innerHTML = `
            <div class="goal-side team-1-side ${isTeam1 ? 'active' : ''}">
                ${team1Content}
            </div>
            <div class="goal-time-pill">${goal.time}</div>
            <div class="goal-side team-2-side ${!isTeam1 ? 'active' : ''}">
                ${team2Content}
            </div>
        `;

        goalsList.appendChild(goalItem);
    });
}


function populateLineups(lineups) {
    const team1Players = document.getElementById('team1Players');
    const team2Players = document.getElementById('team2Players');

    if (!team1Players || !team2Players) return;

    team1Players.innerHTML = '';
    team2Players.innerHTML = '';

    // Team 1 players
    if (lineups && lineups.team1 && lineups.team1.length > 0) {
        lineups.team1.forEach(player => {
            const playerDiv = createPlayerLineupItem(player);
            if (playerDiv) team1Players.appendChild(playerDiv);
        });
    } else {
        team1Players.innerHTML = '<div class="no-data"><i class="fas fa-info-circle"></i> No lineup data submitted for this team</div>';
    }

    // Team 2 players
    if (lineups && lineups.team2 && lineups.team2.length > 0) {
        lineups.team2.forEach(player => {
            const playerDiv = createPlayerLineupItem(player);
            if (playerDiv) team2Players.appendChild(playerDiv);
        });
    } else {
        team2Players.innerHTML = '<div class="no-data"><i class="fas fa-info-circle"></i> No lineup data submitted for this team</div>';
    }
}

function createPlayerLineupItem(player) {
    if (!player) return null;

    const playerDiv = document.createElement('div');
    playerDiv.className = 'player-lineup-item';

    // Safety checks for player properties
    const playerName = player.name || 'Unknown Player';
    const playerNumber = player.number !== undefined && player.number !== null ? player.number : '?';
    const playerPhoto = player.photo || 'default-player.jpg';
    const playerId = player.id || '';

    playerDiv.dataset.playerId = playerId;
    playerDiv.dataset.playerName = playerName.toLowerCase();
    playerDiv.dataset.playerNumber = playerNumber;

    playerDiv.innerHTML = `
        <img src="${SITE_URL}/images/players/${playerPhoto}" 
             alt="${playerName}" 
             class="player-photo-small"
             onerror="this.src='${SITE_URL}/images/players/default-player.jpg'">
        <div class="player-info-lineup">
            <div class="player-name-lineup">${playerName}</div>
            <div class="player-number-lineup">#${playerNumber}</div>
        </div>
    `;

    return playerDiv;
}


function searchPlayers() {
    const searchTerm = document.getElementById('playerSearch').value.toLowerCase().trim();
    const players = document.querySelectorAll('.player-lineup-item');

    if (!searchTerm) {
        // Reset all players
        players.forEach(player => {
            player.style.display = 'flex';
            player.style.backgroundColor = '';
        });
        return;
    }

    players.forEach(player => {
        const playerName = player.dataset.playerName || '';
        const playerNumber = player.dataset.playerNumber || '';
        const playerId = player.dataset.playerId || '';

        if (playerName.includes(searchTerm) ||
            playerNumber.includes(searchTerm) ||
            playerId.includes(searchTerm)) {
            player.style.display = 'flex';
            player.style.backgroundColor = 'rgba(0, 255, 136, 0.2)';
        } else {
            player.style.display = 'none';
        }
    });
}

// Schedule Modal Functions
function openScheduleMatchModal(matchId) {
    console.log('Opening schedule modal for ID:', matchId);

    fetch(`${SITE_URL}/get_match_detail.php?id=${matchId}`)
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                alert(result.message || 'Schedule details not available');
                return;
            }

            const scheduleData = result.data;
            scheduleModalData = scheduleData;

            // Populate modal
            document.getElementById('scheduleModalTitle').textContent = 'Match Details';

            const modalContent = document.getElementById('scheduleModalContent');
            modalContent.innerHTML = `
                <div class="schedule-detail-content">
                    <h4 class="schedule-event-title">${scheduleData.event}</h4>
                    <p class="schedule-round">${scheduleData.round}</p>
                    
                    <div class="schedule-date-venue">
                        <p><i class="fas fa-calendar-alt"></i> <strong>Date & Time:</strong> ${scheduleData.date}, ${scheduleData.time}</p>
                        <p><i class="fas fa-map-marker-alt"></i> <strong>Venue:</strong> ${scheduleData.location}</p>
                    </div>
                    
                    <div class="schedule-teams-large">
                        <div class="schedule-team-large">
                            <img src="${SITE_URL}/images/teams/${scheduleData.team1_logo}" 
                                 alt="${scheduleData.team1}" 
                                 class="team-logo-large-modal"
                                 onerror="this.src='${SITE_URL}/images/teams/default-team.png'">
                            <h4>${scheduleData.team1}</h4>
                        </div>
                        
                        <div class="schedule-vs-large">VS</div>
                        
                        <div class="schedule-team-large">
                            <img src="${SITE_URL}/images/teams/${scheduleData.team2_logo}" 
                                 alt="${scheduleData.team2}" 
                                 class="team-logo-large-modal"
                                 onerror="this.src='${SITE_URL}/images/teams/default-team.png'">
                            <h4>${scheduleData.team2}</h4>
                        </div>
                    </div>
                    
                    <div class="jersey-info">
                        <div class="jersey-input">
                            <label>${scheduleData.team1}:</label>
                            <span class="jersey-info-text">${scheduleData.jerseyInfo.team1}</span>
                        </div>
                        <div class="jersey-input">
                            <label>${scheduleData.team2}:</label>
                            <span class="jersey-info-text">${scheduleData.jerseyInfo.team2}</span>
                        </div>
                    </div>
                    
                    <div class="schedule-share">
                        <h4>Share This Match</h4>
                        <div class="share-buttons-grid">
                            <a href="https://wa.me/?text=Check out this match: ${scheduleData.team1} vs ${scheduleData.team2} on ${scheduleData.date} at ${scheduleData.location}" 
                               target="_blank" class="share-btn-modal whatsapp">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(window.location.href)}" 
                               target="_blank" class="share-btn-modal facebook">
                                <i class="fab fa-facebook"></i> Facebook
                            </a>
                            <a href="https://t.me/share/url?url=${encodeURIComponent(window.location.href)}&text=Check out this match: ${scheduleData.team1} vs ${scheduleData.team2}" 
                               target="_blank" class="share-btn-modal telegram">
                                <i class="fab fa-telegram"></i> Telegram
                            </a>
                            <a href="https://twitter.com/intent/tweet?text=Check out this match: ${scheduleData.team1} vs ${scheduleData.team2} on ${scheduleData.date}&url=${encodeURIComponent(window.location.href)}" 
                               target="_blank" class="share-btn-modal twitter">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                        </div>
                    </div>
                </div>
            `;

            // Show modal
            document.getElementById('scheduleModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        })
        .catch(error => {
            console.error('Error fetching schedule details:', error);
            alert('An error occurred while loading schedule details.');
        });
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    scheduleModalData = {};
}

// Horizontal Scroll Functionality
function initHorizontalScroll() {
    // Match cards scroll
    const matchScrollContainer = document.querySelector('.match-cards-scroll');
    const scrollPrev = document.querySelector('.scroll-prev');
    const scrollNext = document.querySelector('.scroll-next');

    if (matchScrollContainer && scrollPrev && scrollNext) {
        const cardWidth = 280;
        const scrollAmount = cardWidth * 2;

        scrollPrev.addEventListener('click', () => {
            matchScrollContainer.scrollBy({
                left: -scrollAmount,
                behavior: 'smooth'
            });
        });

        scrollNext.addEventListener('click', () => {
            matchScrollContainer.scrollBy({
                left: scrollAmount,
                behavior: 'smooth'
            });
        });

        function updateScrollButtons() {
            const maxScrollLeft = matchScrollContainer.scrollWidth - matchScrollContainer.clientWidth;

            scrollPrev.disabled = matchScrollContainer.scrollLeft <= 0;
            scrollNext.disabled = matchScrollContainer.scrollLeft >= maxScrollLeft;

            scrollPrev.style.opacity = scrollPrev.disabled ? '0.5' : '1';
            scrollPrev.style.cursor = scrollPrev.disabled ? 'not-allowed' : 'pointer';
            scrollNext.style.opacity = scrollNext.disabled ? '0.5' : '1';
            scrollNext.style.cursor = scrollNext.disabled ? 'not-allowed' : 'pointer';
        }

        matchScrollContainer.addEventListener('scroll', updateScrollButtons);
        window.addEventListener('resize', updateScrollButtons);
        updateScrollButtons();
    }
}

// Tab System - Each section handles its own tabs
function initTabSystem() {
    // Initialize all section tabs independently
    document.querySelectorAll('.section-container').forEach(section => {
        const tabButtons = section.querySelectorAll('.tab-button');
        const tabContents = section.querySelectorAll('.tab-content');

        if (tabButtons.length === 0) return;

        tabButtons.forEach(button => {
            button.addEventListener('click', function () {
                const tabId = this.dataset.tab;

                // Remove active class from all tabs in this section
                tabButtons.forEach(btn => {
                    btn.classList.remove('active');
                });

                tabContents.forEach(tab => {
                    tab.classList.remove('active');
                });

                // Add active class to current tab and content
                this.classList.add('active');
                const targetTab = section.querySelector(`#${tabId}`);
                if (targetTab) {
                    targetTab.classList.add('active');
                }
            });
        });
    });
}

// News Click Tracking Function
function initNewsClickTracking() {
    const newsLinks = document.querySelectorAll('.news-link');

    newsLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            const newsId = this.dataset.newsId;
            const newsUrl = this.href;

            // Update view count via AJAX
            updateNewsViews(newsId).then(() => {
                window.location.href = newsUrl;
            }).catch(error => {
                console.error('Error updating views:', error);
                window.location.href = newsUrl;
            });
        });
    });
}

// AJAX function to update news views
function updateNewsViews(newsId) {
    return new Promise((resolve, reject) => {
        // Simulate API call
        setTimeout(() => {
            const viewCountElements = document.querySelectorAll(`#view-count-${newsId}`);
            viewCountElements.forEach(element => {
                const currentCount = parseInt(element.textContent) || 0;
                element.textContent = currentCount + 1;
            });
            resolve({ success: true, message: 'Views updated' });
        }, 500);
    });
}

// Initialize match table functionality
function initMatchTables() {
    // Schedule table buttons
    document.querySelectorAll('.btn-view-schedule').forEach(button => {
        button.addEventListener('click', function (e) {
            e.stopPropagation();
            const matchId = this.dataset.matchId;
            if (matchId) {
                openScheduleMatchModal(parseInt(matchId));
            }
        });
    });

    // Result table buttons
    document.querySelectorAll('.btn-view-result').forEach(button => {
        button.addEventListener('click', function (e) {
            e.stopPropagation();
            const matchId = this.dataset.matchId;
            if (matchId) {
                openMatchModal(parseInt(matchId));
            }
        });
    });

    // Row click for table rows
    document.querySelectorAll('.schedule-row').forEach(row => {
        row.addEventListener('click', function (e) {
            if (!e.target.closest('.btn-view-schedule')) {
                const matchId = this.dataset.matchId;
                if (matchId) {
                    openScheduleMatchModal(parseInt(matchId));
                }
            }
        });
    });

    document.querySelectorAll('.result-row').forEach(row => {
        row.addEventListener('click', function (e) {
            if (!e.target.closest('.btn-view-result')) {
                const matchId = this.dataset.matchId;
                if (matchId) {
                    openMatchModal(parseInt(matchId));
                }
            }
        });
    });
}

// News ticker functionality
function initNewsTicker() {
    const tickerItems = document.querySelectorAll('.ticker-item');
    if (tickerItems.length === 0) return;

    let currentIndex = 0;

    function showNextNews() {
        tickerItems[currentIndex].classList.remove('active');
        currentIndex = (currentIndex + 1) % tickerItems.length;
        tickerItems[currentIndex].classList.add('active');
    }

    // Auto rotate every 5 seconds
    const tickerInterval = setInterval(showNextNews, 5000);

    // Manual controls
    const tickerNext = document.querySelector('.ticker-next');
    const tickerPrev = document.querySelector('.ticker-prev');

    if (tickerNext) {
        tickerNext.addEventListener('click', function () {
            clearInterval(tickerInterval);
            showNextNews();
        });
    }

    if (tickerPrev) {
        tickerPrev.addEventListener('click', function () {
            clearInterval(tickerInterval);
            tickerItems[currentIndex].classList.remove('active');
            currentIndex = (currentIndex - 1 + tickerItems.length) % tickerItems.length;
            tickerItems[currentIndex].classList.add('active');
        });
    }
}

// Chat modal functionality
function initChatModal() {
    const chatButton = document.getElementById('chatButton');
    const chatModal = document.getElementById('chatModal');
    const chatClose = document.getElementById('chatClose');

    if (!chatButton || !chatModal || !chatClose) return;

    chatButton.addEventListener('click', function () {
        chatModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    });

    chatClose.addEventListener('click', function () {
        chatModal.style.display = 'none';
        document.body.style.overflow = 'auto';
    });

    window.addEventListener('click', function (event) {
        if (event.target === chatModal) {
            chatModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });

    // Contact form submission
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();
            alert('Pesan berhasil dikirim! Kami akan membalas sesegera mungkin.');
            this.reset();
        });
    }
}

// Mobile Navigation
function initMobileNavigation() {
    const hamburger = document.querySelector('.hamburger');
    const navMenu = document.querySelector('.nav-menu');

    if (hamburger && navMenu) {
        hamburger.addEventListener('click', function () {
            this.classList.toggle('active');
            navMenu.classList.toggle('active');
            document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : 'auto';
        });

        document.querySelectorAll('.nav-menu a').forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
                document.body.style.overflow = 'auto';
            });
        });
    }
}

// Function khusus untuk halaman all.php
function initAllPageFunctionality() {
    // Check if we're on all.php page
    const isAllPage = window.location.pathname.includes('all.php') ||
        window.location.pathname.includes('match/all');

    if (!isAllPage) return;

    console.log('Initializing all.php functionality');

    // Override click handlers untuk all.php
    document.querySelectorAll('.btn-view-result, .btn-view-schedule, .match-row').forEach(element => {
        element.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-view-result') ||
                e.target.classList.contains('btn-view-schedule')) {
                e.stopPropagation();
            }

            const matchId = this.dataset.matchId || this.closest('.match-row')?.dataset.matchId;
            if (matchId) {
                // Always redirect to match detail page
                window.location.href = `${SITE_URL}/match.php?id=${matchId}`;
            }
        });
    });

    // Reset filter pada page load
    document.getElementById('statusFilter')?.addEventListener('change', function () {
        document.getElementById('applyFilter').click();
    });
}

// Main JavaScript Initialization
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM fully loaded');

    // Initialize all functionalities
    initHorizontalScroll();
    initTabSystem();
    initNewsClickTracking();
    initMatchTables();  // Initialize match table functionality
    initNewsTicker();
    initChatModal();
    initMobileNavigation();
    initAllPageFunctionality(); // Initialize all page functionality

    // Match card click handlers
    document.querySelectorAll('.match-card').forEach(card => {
        card.addEventListener('click', function (e) {
            if (!e.target.closest('.btn-details')) {
                const matchId = this.dataset.matchId;
                if (matchId) {
                    openMatchModal(parseInt(matchId));
                }
            }
        });
    });

    // View Details buttons
    document.querySelectorAll('.btn-details').forEach(button => {
        button.addEventListener('click', function (e) {
            e.stopPropagation();
            const matchId = this.dataset.matchId;
            if (matchId) {
                openMatchModal(parseInt(matchId));
            }
        });
    });

    // Player card click handlers
    document.querySelectorAll('.player-card').forEach(card => {
        card.addEventListener('click', function () {
            const playerId = this.dataset.playerId;
            if (playerId) {
                window.location.href = `${SITE_URL}/player.php?id=${playerId}`;
            }
        });
    });

    // Team card click handlers
    document.querySelectorAll('.team-card').forEach(card => {
        card.addEventListener('click', function () {
            const teamId = this.dataset.teamId;
            if (teamId) {
                window.location.href = `${SITE_URL}/team.php?id=${teamId}`;
            }
        });
    });

    // Close match modal
    const closeMatchModalBtn = document.getElementById('closeMatchModal');
    if (closeMatchModalBtn) {
        closeMatchModalBtn.addEventListener('click', closeMatchModal);
    }

    // Close schedule modal
    const closeScheduleModalBtn = document.getElementById('closeScheduleModal');
    if (closeScheduleModalBtn) {
        closeScheduleModalBtn.addEventListener('click', closeScheduleModal);
    }

    // Close modal when clicking outside
    window.addEventListener('click', function (event) {
        if (event.target === document.getElementById('matchModal')) {
            closeMatchModal();
        }
        if (event.target === document.getElementById('scheduleModal')) {
            closeScheduleModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (document.getElementById('matchModal').style.display === 'block') {
                closeMatchModal();
            }
            if (document.getElementById('scheduleModal').style.display === 'block') {
                closeScheduleModal();
            }
            if (document.getElementById('chatModal').style.display === 'block') {
                document.getElementById('chatModal').style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
    });

    // Match modal tabs
    document.querySelectorAll('.match-tab').forEach(tab => {
        tab.addEventListener('click', function () {
            const tabName = this.dataset.tab;
            switchMatchTab(tabName);
        });
    });


    // Player search
    const searchPlayerBtn = document.getElementById('searchPlayerBtn');
    if (searchPlayerBtn) {
        searchPlayerBtn.addEventListener('click', searchPlayers);
    }

    // Player search on Enter key
    const playerSearchInput = document.getElementById('playerSearch');
    if (playerSearchInput) {
        playerSearchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                searchPlayers();
            }
        });
    }

    console.log('JavaScript initialization complete');
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;

        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            window.scrollTo({
                top: targetElement.offsetTop - 100,
                behavior: 'smooth'
            });
        }
    });
});

// Lazy loading for images
function initLazyLoading() {
    const lazyImages = document.querySelectorAll('img[data-src]');

    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                observer.unobserve(img);
            }
        });
    });

    lazyImages.forEach(img => imageObserver.observe(img));
}

// Initialize when DOM is loaded
window.addEventListener('load', function () {
    initLazyLoading();
});

// Add active class to current page in navigation
function setActiveNavItem() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-menu a');

    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPath) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

// Call when page loads
setActiveNavItem();