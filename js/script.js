// Global Variables
const SITE_URL = window.location.origin + window.location.pathname.split('/').slice(0, -1).join('/');
let currentMatchId = null;
let scheduleModalData = {};

// Match Detail Modal Data
const matchDetailsData = {
    1: {
        id: 1,
        title: 'PAFCA vs 014 BUFC',
        team1: 'PAFCA',
        team1_logo: 'PAFCA.png',
        team2: '014 BUFC',
        team2_logo: '014-bufc.png',
        score: '5-1',
        date: '25 Jan 2026, 16:40',
        location: 'LAP SEPINGGAN PRATAMA',
        goals: [
            { player: 'ZAIN ZEFA ARCANA', number: 99, time: '6"', team: 'team1' },
            { player: 'MUHAMMAD AYUB KERTAPATI', number: 9, time: '18"', team: 'team1' },
            { player: 'RASYA MUHAMMAD ATHAYA', number: 21, time: '22"', team: 'team1' },
            { player: 'RASYA MUHAMMAD ATHAYA', number: 21, time: '31"', team: 'team1' },
            { player: 'BINTANG ALDEBARAN AYUDHA', number: 36, time: '35"', team: 'team1' },
            { player: 'MUHAMMAD FAYAD', number: 9, time: '32"', team: 'team2' }
        ],
        timeline: {
            'Babak 1': [
                { time: '6"', player: 'ZAIN ZEFA ARCANA', number: 99, type: 'goal' },
                { time: '18"', player: 'MUHAMMAD AYUB KERTAPATI', number: 9, type: 'goal' }
            ],
            'Babak 2': [
                { time: '22"', player: 'RASYA MUHAMMAD ATHAYA', number: 21, type: 'goal' },
                { time: '31"', player: 'RASYA MUHAMMAD ATHAYA', number: 21, type: 'goal' },
                { time: '32"', player: 'MUHAMMAD FAYAD', number: 9, type: 'goal' },
                { time: '35"', player: 'BINTANG ALDEBARAN AYUDHA', number: 36, type: 'goal' }
            ]
        },
        lineups: {
            team1: [
                { id: 1, name: 'AZRYQ FAREZEEL RAMAZAN MUDAWAM', number: 2, photo: 'default-player.jpg' },
                { id: 2, name: 'MUHAMMAD AYUB KERTAPATI', number: 9, photo: 'default-player.jpg' },
                { id: 3, name: 'REVANDRA ALFAREZKY SETIAWAN', number: 11, photo: 'default-player.jpg' },
                { id: 4, name: 'ABID TAMAM ACHMAD', number: 16, photo: 'default-player.jpg' },
                { id: 5, name: 'RAMEZA ALFARIZQI', number: 20, photo: 'default-player.jpg' },
                { id: 6, name: 'RASYA MUHAMMAD ATHAYA', number: 21, photo: 'default-player.jpg' },
                { id: 7, name: 'TRISTAN ALARIC AHZA', number: 26, photo: 'default-player.jpg' },
                { id: 8, name: 'BINTANG ALDEBARAN AYUDHA', number: 36, photo: 'default-player.jpg' },
                { id: 9, name: 'MUHAMMAD YUSUF SETIAWAN', number: 39, photo: 'default-player.jpg' },
                { id: 10, name: 'AFIF SURYA PRATAMA', number: 40, photo: 'default-player.jpg' },
                { id: 11, name: 'DIMAS DANENDRA PRANAGIA', number: 45, photo: 'default-player.jpg' },
                { id: 12, name: 'ZAIN ZEFA ARCANA', number: 99, photo: 'default-player.jpg' }
            ],
            team2: [
                { id: 13, name: 'RHANVI ALVIANUR', number: 1, photo: 'default-player.jpg' },
                { id: 14, name: 'IRSYAD ILHAM', number: 6, photo: 'default-player.jpg' },
                { id: 15, name: 'AZZAM MAULANA', number: 7, photo: 'default-player.jpg' },
                { id: 16, name: 'MUHAMMAD FAYAD', number: 9, photo: 'default-player.jpg' },
                { id: 17, name: 'SANDI PRATAMA', number: 11, photo: 'default-player.jpg' },
                { id: 18, name: 'AQILA PUTRA', number: 14, photo: 'default-player.jpg' },
                { id: 19, name: 'MUHAMMAD ARKANAH', number: 15, photo: 'default-player.jpg' },
                { id: 20, name: 'ADAM FAIZ', number: 16, photo: 'default-player.jpg' },
                { id: 21, name: 'NOVAR FAJAR', number: 17, photo: 'default-player.jpg' },
                { id: 22, name: 'MUHAMMAD FATHIAN', number: 18, photo: 'default-player.jpg' },
                { id: 23, name: 'AIMAR HAFIZ', number: 19, photo: 'default-player.jpg' },
                { id: 24, name: 'AVIZAR AL HASYAM', number: 25, photo: 'default-player.jpg' },
                { id: 25, name: 'ATHAYA RAQILA', number: 31, photo: 'default-player.jpg' }
            ]
        }
    },
    2: {
        id: 2,
        title: 'GENERASI FAB vs FAMILY FUTSAL BALIKPAPAN',
        team1: 'GENERASI FAB',
        team1_logo: 'generasi-fab.png',
        team2: 'FAMILY FUTSAL BALIKPAPAN',
        team2_logo: 'famili-balikpapan.png',
        score: '0-4',
        date: '25 Jan 2026, 15:50',
        location: 'LAP SEPINGGAN PRATAMA',
        goals: [
            { player: 'PLAYER A', number: 10, time: '15"', team: 'team2' },
            { player: 'PLAYER B', number: 7, time: '25"', team: 'team2' },
            { player: 'PLAYER C', number: 9, time: '40"', team: 'team2' },
            { player: 'PLAYER D', number: 11, time: '55"', team: 'team2' }
        ],
        timeline: {
            'Babak 1': [
                { time: '15"', player: 'PLAYER A', number: 10, type: 'goal' },
                { time: '25"', player: 'PLAYER B', number: 7, type: 'goal' }
            ],
            'Babak 2': [
                { time: '40"', player: 'PLAYER C', number: 9, type: 'goal' },
                { time: '55"', player: 'PLAYER D', number: 11, type: 'goal' }
            ]
        },
        lineups: {
            team1: [
                { id: 26, name: 'PLAYER 1', number: 1, photo: 'default-player.jpg' },
                { id: 27, name: 'PLAYER 2', number: 2, photo: 'default-player.jpg' }
            ],
            team2: [
                { id: 28, name: 'PLAYER A', number: 10, photo: 'default-player.jpg' },
                { id: 29, name: 'PLAYER B', number: 7, photo: 'default-player.jpg' }
            ]
        }
    },
    3: {
        id: 3,
        title: 'KUDA LAUT NUSANTARA vs ANTRI FUTSAL SCHOOL GNR',
        team1: 'KUDA LAUT NUSANTARA',
        team1_logo: 'kuda-laut-nusantara.png',
        team2: 'ANTRI FUTSAL SCHOOL GNR',
        team2_logo: 'antri-futsal.png',
        score: '3-2',
        date: '25 Jan 2026, 15:50',
        location: 'LAP SEPINGGAN PRATAMA',
        goals: [
            { player: 'PLAYER X', number: 10, time: '10"', team: 'team1' },
            { player: 'PLAYER Y', number: 7, time: '25"', team: 'team2' },
            { player: 'PLAYER Z', number: 9, time: '40"', team: 'team1' },
            { player: 'PLAYER W', number: 8, time: '55"', team: 'team2' },
            { player: 'PLAYER V', number: 11, time: '60"', team: 'team1' }
        ],
        timeline: {
            'Babak 1': [
                { time: '10"', player: 'PLAYER X', number: 10, type: 'goal' },
                { time: '25"', player: 'PLAYER Y', number: 7, type: 'goal' }
            ],
            'Babak 2': [
                { time: '40"', player: 'PLAYER Z', number: 9, type: 'goal' },
                { time: '55"', player: 'PLAYER W', number: 8, type: 'goal' },
                { time: '60"', player: 'PLAYER V', number: 11, type: 'goal' }
            ]
        },
        lineups: {
            team1: [
                { id: 30, name: 'PLAYER X', number: 10, photo: 'default-player.jpg' },
                { id: 31, name: 'PLAYER Z', number: 9, photo: 'default-player.jpg' }
            ],
            team2: [
                { id: 32, name: 'PLAYER Y', number: 7, photo: 'default-player.jpg' },
                { id: 33, name: 'PLAYER W', number: 8, photo: 'default-player.jpg' }
            ]
        }
    },
    4: {
        id: 4,
        title: 'APOLLO FUTSAL ACADEMY vs TWO IN ONE FA',
        team1: 'APOLLO FUTSAL ACADEMY',
        team1_logo: 'apollo futsal.png',
        team2: 'TWO IN ONE FA',
        team2_logo: 'two in one.png',
        score: '1-5',
        date: '25 Jan 2026, 15:40',
        location: 'LAP SEPINGGAN PRATAMA',
        goals: [
            { player: 'PLAYER 1', number: 9, time: '5"', team: 'team1' },
            { player: 'PLAYER A', number: 10, time: '15"', team: 'team2' },
            { player: 'PLAYER B', number: 7, time: '25"', team: 'team2' },
            { player: 'PLAYER C', number: 11, time: '40"', team: 'team2' },
            { player: 'PLAYER D', number: 8, time: '50"', team: 'team2' },
            { player: 'PLAYER E', number: 9, time: '60"', team: 'team2' }
        ],
        timeline: {
            'Babak 1': [
                { time: '5"', player: 'PLAYER 1', number: 9, type: 'goal' },
                { time: '15"', player: 'PLAYER A', number: 10, type: 'goal' },
                { time: '25"', player: 'PLAYER B', number: 7, type: 'goal' }
            ],
            'Babak 2': [
                { time: '40"', player: 'PLAYER C', number: 11, type: 'goal' },
                { time: '50"', player: 'PLAYER D', number: 8, type: 'goal' },
                { time: '60"', player: 'PLAYER E', number: 9, type: 'goal' }
            ]
        },
        lineups: {
            team1: [
                { id: 34, name: 'PLAYER 1', number: 9, photo: 'default-player.jpg' }
            ],
            team2: [
                { id: 35, name: 'PLAYER A', number: 10, photo: 'default-player.jpg' },
                { id: 36, name: 'PLAYER B', number: 7, photo: 'default-player.jpg' }
            ]
        }
    },
    5: {
        id: 5,
        title: 'MESS FUTSAL vs BAHATI FUTSAL',
        team1: 'MESS FUTSAL',
        team1_logo: 'mess-futsal.png',
        team2: 'BAHATI FUTSAL',
        team2_logo: 'bahati-futsal.png',
        score: '3-1',
        date: '26 Jan 2026, 10:15',
        location: 'Golden Sport Center',
        goals: [
            { player: 'ANDI PRATAMA', number: 7, time: '12"', team: 'team1' },
            { player: 'BUDI SANTOSO', number: 10, time: '28"', team: 'team1' },
            { player: 'RUDI HARTONO', number: 9, time: '45"', team: 'team2' },
            { player: 'ANDI PRATAMA', number: 7, time: '55"', team: 'team1' }
        ],
        timeline: {
            'Babak 1': [
                { time: '12"', player: 'ANDI PRATAMA', number: 7, type: 'goal' },
                { time: '28"', player: 'BUDI SANTOSO', number: 10, type: 'goal' }
            ],
            'Babak 2': [
                { time: '45"', player: 'RUDI HARTONO', number: 9, type: 'goal' },
                { time: '50"', player: 'ANDI PRATAMA', number: 7, type: 'yellow-card' },
                { time: '55"', player: 'AGUS SETIAWAN', number: 5, type: 'substitution' },
                { time: '55"', player: 'ANDI PRATAMA', number: 7, type: 'goal' }
            ]
        },
        lineups: {
            team1: [
                { id: 37, name: 'RIZKY MAULANA', number: 1, photo: 'default-player.jpg' },
                { id: 38, name: 'AGUS SETIAWAN', number: 5, photo: 'default-player.jpg' },
                { id: 39, name: 'ANDI PRATAMA', number: 7, photo: 'default-player.jpg' },
                { id: 40, name: 'BUDI SANTOSO', number: 10, photo: 'default-player.jpg' },
                { id: 41, name: 'DEDI KURNIAWAN', number: 11, photo: 'default-player.jpg' },
                { id: 42, name: 'ERWIN PERMANA', number: 15, photo: 'default-player.jpg' },
                { id: 43, name: 'FARIS RAMADHAN', number: 17, photo: 'default-player.jpg' },
                { id: 44, name: 'GALIH PRASETYA', number: 20, photo: 'default-player.jpg' }
            ],
            team2: [
                { id: 45, name: 'RUDI HARTONO', number: 9, photo: 'default-player.jpg' },
                { id: 46, name: 'HENDRA WIJAYA', number: 3, photo: 'default-player.jpg' },
                { id: 47, name: 'IRFAN SYAH', number: 6, photo: 'default-player.jpg' },
                { id: 48, name: 'JOKO SUSILO', number: 8, photo: 'default-player.jpg' },
                { id: 49, name: 'KURNIAWAN', number: 12, photo: 'default-player.jpg' },
                { id: 50, name: 'LUTFI RAMADHAN', number: 14, photo: 'default-player.jpg' },
                { id: 51, name: 'MOHAMMAD RIZAL', number: 16, photo: 'default-player.jpg' },
                { id: 52, name: 'NOVAL PRATAMA', number: 19, photo: 'default-player.jpg' }
            ]
        }
    }
};

// Schedule Modal Data
const scheduleMatchData = [
    {
        team1: 'PAFCA',
        team1_logo: 'PAFCA.png',
        team2: '014 BUFC',
        team2_logo: '014-bufc.png',
        date: '01 Feb 2026, 10:15',
        event: 'PL AAFI 2026',
        round: 'Semi Final - Pekan ke-3',
        venue: 'LAP SEPINGGAN PRATAMA - Lap 2',
        jerseyInfo: {
            team1: 'Jersey Putih',
            team2: 'Jersey Biru'
        }
    },
    {
        team1: 'GENERASI FAB',
        team1_logo: 'generasi-fab.png',
        team2: 'FAMILY FUTSAL BALIKPAPAN',
        team2_logo: 'famili-balikpapan.png',
        date: '01 Feb 2026, 10:45',
        event: 'PL AAFI 2026',
        round: 'Semi Final - Pekan ke-3',
        venue: 'LAP SEPINGGAN PRATAMA - Lap 2',
        jerseyInfo: {
            team1: 'Jersey Merah',
            team2: 'Jersey Kuning'
        }
    },
    {
        team1: 'KUDA LAUT NUSANTARA',
        team1_logo: 'kuda-laut-nusantara.png',
        team2: 'ANTRI FUTSAL SCHOOL GNR',
        team2_logo: 'antri-futsal.png',
        date: '01 Feb 2026, 10:45',
        event: 'JTFL',
        round: 'Semi Final - Pekan ke-3',
        venue: 'LAP SEPINGGAN PRATAMA - Lap 2',
        jerseyInfo: {
            team1: 'Jersey Hijau',
            team2: 'Jersey Putih'
        }
    },
    {
        team1: 'APOLLO FUTSAL ACADEMY',
        team1_logo: 'apollo futsal.png',
        team2: 'TWO IN ONE FA',
        team2_logo: 'two in one.png',
        date: '01 Feb 2026, 10:45',
        event: 'AAFI TANGGERANG 1',
        round: 'Semi Final - Pekan ke-3',
        venue: 'LAP SEPINGGAN PRATAMA - Lap 2',
        jerseyInfo: {
            team1: 'Jersey Biru',
            team2: 'Jersey Merah'
        }
    },
    {
        team1: 'MESS FUTSAL',
        team1_logo: 'mess-futsal.png',
        team2: 'BAHATI FUTSAL',
        team2_logo: 'bahati-futsal.png',
        date: '01 Feb 2026, 11:15',
        event: 'JFTL',
        round: 'Semi Final - Pekan ke-3',
        venue: 'Golden Sport Center - Lap 2',
        jerseyInfo: {
            team1: 'Jersey Hitam',
            team2: 'Jersey Putih'
        }
    }
];

// Match Modal Functions
function openMatchModal(matchId) {
    console.log('Opening match modal for ID:', matchId);
    currentMatchId = matchId;
    const matchDetail = matchDetailsData[matchId];
    
    if (!matchDetail) {
        console.error('Match detail not found for ID:', matchId);
        alert('Match details not available');
        return;
    }
    
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
    team1Logo.onerror = function() {
        this.src = SITE_URL + '/images/teams/default-team.png';
    };
    team2Logo.onerror = function() {
        this.src = SITE_URL + '/images/teams/default-team.png';
    };
    
    // Set score
    document.getElementById('matchScoreLarge').textContent = matchDetail.score;
    
    // Set date and location
    document.getElementById('matchDateTime').textContent = matchDetail.date;
    document.getElementById('matchLocation').textContent = matchDetail.location;
    
    // Populate content
    populateGoals(matchDetail.goals);
    populateTimeline(matchDetail.timeline);
    populateLineups(matchDetail.lineups);
    
    // Show modal
    document.getElementById('matchModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Reset to Goals tab
    switchMatchTab('goals');
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
    
    if (!goals || goals.length === 0) {
        goalsList.innerHTML = '<div class="no-data">No goals data available</div>';
        return;
    }
    
    goals.forEach(goal => {
        const goalItem = document.createElement('div');
        goalItem.className = 'goal-item';
        
        goalItem.innerHTML = `
            <div class="goal-player">
                <span class="goal-player-number">${goal.number}</span>
                <span class="goal-player-name">${goal.player}</span>
            </div>
            <span class="goal-time">${goal.time}</span>
        `;
        
        goalsList.appendChild(goalItem);
    });
}

function populateTimeline(timeline) {
    const timelineList = document.getElementById('timelineList');
    timelineList.innerHTML = '';
    
    if (!timeline || Object.keys(timeline).length === 0) {
        timelineList.innerHTML = '<div class="no-data">No timeline data available</div>';
        return;
    }
    
    for (const [half, events] of Object.entries(timeline)) {
        const halfDiv = document.createElement('div');
        halfDiv.className = 'timeline-half';
        
        halfDiv.innerHTML = `<div class="timeline-half-title">${half}</div>`;
        
        events.forEach(event => {
            const eventDiv = document.createElement('div');
            eventDiv.className = 'timeline-event';
            eventDiv.dataset.type = event.type;
            
            eventDiv.innerHTML = `
                <span class="timeline-time">${event.time}</span>
                <div class="timeline-player">
                    <span class="timeline-player-number">${event.number}</span>
                    <span class="timeline-player-name">${event.player}</span>
                </div>
                <span class="timeline-event-type">${event.type}</span>
            `;
            
            halfDiv.appendChild(eventDiv);
        });
        
        timelineList.appendChild(halfDiv);
    }
}

function populateLineups(lineups) {
    const team1Players = document.getElementById('team1Players');
    const team2Players = document.getElementById('team2Players');
    
    team1Players.innerHTML = '';
    team2Players.innerHTML = '';
    
    // Team 1 players
    if (lineups.team1 && lineups.team1.length > 0) {
        lineups.team1.forEach(player => {
            const playerDiv = createPlayerLineupItem(player);
            team1Players.appendChild(playerDiv);
        });
    } else {
        team1Players.innerHTML = '<div class="no-data">No lineup data available</div>';
    }
    
    // Team 2 players
    if (lineups.team2 && lineups.team2.length > 0) {
        lineups.team2.forEach(player => {
            const playerDiv = createPlayerLineupItem(player);
            team2Players.appendChild(playerDiv);
        });
    } else {
        team2Players.innerHTML = '<div class="no-data">No lineup data available</div>';
    }
}

function createPlayerLineupItem(player) {
    const playerDiv = document.createElement('div');
    playerDiv.className = 'player-lineup-item';
    playerDiv.dataset.playerId = player.id;
    playerDiv.dataset.playerName = player.name.toLowerCase();
    playerDiv.dataset.playerNumber = player.number;
    
    playerDiv.innerHTML = `
        <img src="${SITE_URL}/images/players/${player.photo}" 
             alt="${player.name}" 
             class="player-photo-small"
             onerror="this.src='${SITE_URL}/images/players/default-player.jpg'">
        <div class="player-info-lineup">
            <div class="player-name-lineup">${player.name}</div>
            <div class="player-number-lineup">#${player.number}</div>
        </div>
    `;
    
    return playerDiv;
}

function filterTimeline() {
    const filterType = document.getElementById('timelineFilter').value;
    const events = document.querySelectorAll('.timeline-event');
    
    events.forEach(event => {
        if (filterType === 'all' || event.dataset.type === filterType) {
            event.style.display = 'flex';
        } else {
            event.style.display = 'none';
        }
    });
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
function openScheduleMatchModal(scheduleIndex) {
    console.log('Opening schedule modal for index:', scheduleIndex);
    const scheduleData = scheduleMatchData[scheduleIndex];
    
    if (!scheduleData) {
        console.error('Schedule data not found for index:', scheduleIndex);
        alert('Schedule details not available');
        return;
    }
    
    scheduleModalData = scheduleData;
    
    // Populate modal
    document.getElementById('scheduleModalTitle').textContent = 'Schedule Details';
    
    const modalContent = document.getElementById('scheduleModalContent');
    modalContent.innerHTML = `
        <div class="schedule-detail-content">
            <h4 class="schedule-event-title">${scheduleData.event}</h4>
            <p class="schedule-round">${scheduleData.round}</p>
            
            <div class="schedule-date-venue">
                <p><i class="fas fa-calendar-alt"></i> <strong>Date & Time:</strong> ${scheduleData.date}</p>
                <p><i class="fas fa-map-marker-alt"></i> <strong>Venue:</strong> ${scheduleData.venue}</p>
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
                    <a href="https://wa.me/?text=Check out this match: ${scheduleData.team1} vs ${scheduleData.team2} on ${scheduleData.date} at ${scheduleData.venue}" 
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
            button.addEventListener('click', function() {
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
        link.addEventListener('click', function(e) {
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
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const scheduleIndex = this.dataset.matchId - 101; // Convert to index
            if (scheduleIndex !== undefined) {
                openScheduleMatchModal(scheduleIndex);
            }
        });
    });
    
    // Result table buttons
    document.querySelectorAll('.btn-view-result').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const matchId = this.dataset.matchId;
            if (matchId) {
                openMatchModal(parseInt(matchId));
            }
        });
    });
    
    // Row click for table rows
    document.querySelectorAll('.schedule-row').forEach(row => {
        row.addEventListener('click', function(e) {
            if (!e.target.closest('.btn-view-schedule')) {
                const matchId = this.dataset.matchId;
                if (matchId) {
                    const scheduleIndex = parseInt(matchId) - 101; // Convert to index
                    openScheduleMatchModal(scheduleIndex);
                }
            }
        });
    });
    
    document.querySelectorAll('.result-row').forEach(row => {
        row.addEventListener('click', function(e) {
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
        tickerNext.addEventListener('click', function() {
            clearInterval(tickerInterval);
            showNextNews();
        });
    }
    
    if (tickerPrev) {
        tickerPrev.addEventListener('click', function() {
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
    
    chatButton.addEventListener('click', function() {
        chatModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    });
    
    chatClose.addEventListener('click', function() {
        chatModal.style.display = 'none';
        document.body.style.overflow = 'auto';
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === chatModal) {
            chatModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
    
    // Contact form submission
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
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
        hamburger.addEventListener('click', function() {
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
        element.addEventListener('click', function(e) {
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
    document.getElementById('statusFilter')?.addEventListener('change', function() {
        document.getElementById('applyFilter').click();
    });
}

// Main JavaScript Initialization
document.addEventListener('DOMContentLoaded', function() {
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
        card.addEventListener('click', function(e) {
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
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const matchId = this.dataset.matchId;
            if (matchId) {
                openMatchModal(parseInt(matchId));
            }
        });
    });
    
    // Player card click handlers
    document.querySelectorAll('.player-card').forEach(card => {
        card.addEventListener('click', function() {
            const playerId = this.dataset.playerId;
            if (playerId) {
                window.location.href = `${SITE_URL}/player.php?id=${playerId}`;
            }
        });
    });
    
    // Team card click handlers
    document.querySelectorAll('.team-card').forEach(card => {
        card.addEventListener('click', function() {
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
    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('matchModal')) {
            closeMatchModal();
        }
        if (event.target === document.getElementById('scheduleModal')) {
            closeScheduleModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
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
        tab.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            switchMatchTab(tabName);
        });
    });
    
    // Timeline filter
    const timelineFilter = document.getElementById('timelineFilter');
    if (timelineFilter) {
        timelineFilter.addEventListener('change', filterTimeline);
    }
    
    // Player search
    const searchPlayerBtn = document.getElementById('searchPlayerBtn');
    if (searchPlayerBtn) {
        searchPlayerBtn.addEventListener('click', searchPlayers);
    }
    
    // Player search on Enter key
    const playerSearchInput = document.getElementById('playerSearch');
    if (playerSearchInput) {
        playerSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchPlayers();
            }
        });
    }
    
    console.log('JavaScript initialization complete');
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
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
window.addEventListener('load', function() {
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