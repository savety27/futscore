/**
 * Player History and Profile Logic
 * Handles: Share Panel, Skills Toggle, Match History Fetching (AJAX), and Hash Navigation
 */
(function() {
    'use strict';

    // Helper to escape HTML and prevent XSS
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function initPlayerProfile() {
        // 1. Share Panel Logic
        const shareToggle = document.getElementById('playerShareToggle');
        const sharePanel = document.getElementById('playerSharePanel');
        const copyBtn = document.getElementById('playerShareCopyBtn');
        const feedback = document.getElementById('playerShareFeedback');

        if (shareToggle && sharePanel) {
            // Remove existing listener if any (to prevent multiple bindings on hashchange)
            const newShareToggle = shareToggle.cloneNode(true);
            shareToggle.parentNode.replaceChild(newShareToggle, shareToggle);
            
            newShareToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                const isOpen = sharePanel.classList.contains('open');
                sharePanel.classList.toggle('open', !isOpen);
                newShareToggle.setAttribute('aria-expanded', !isOpen);
            });

            document.addEventListener('click', function(e) {
                if (sharePanel.classList.contains('open') && !sharePanel.contains(e.target)) {
                    sharePanel.classList.remove('open');
                    newShareToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        if (copyBtn) {
            const newCopyBtn = copyBtn.cloneNode(true);
            copyBtn.parentNode.replaceChild(newCopyBtn, copyBtn);

            newCopyBtn.addEventListener('click', function() {
                const url = sharePanel.getAttribute('data-share-url');
                
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(() => {
                        handleCopySuccess(newCopyBtn, feedback);
                    }).catch(() => {
                        handleCopyError(feedback);
                    });
                } else {
                    // Fallback for non-HTTPS or older browsers
                    const textArea = document.createElement("textarea");
                    textArea.value = url;
                    textArea.style.position = "fixed";
                    textArea.style.left = "-999999px";
                    textArea.style.top = "-999999px";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        handleCopySuccess(newCopyBtn, feedback);
                    } catch (err) {
                        handleCopyError(feedback);
                    }
                    document.body.removeChild(textArea);
                }
            });
        }

        function handleCopySuccess(btn, feedbackElem) {
            btn.classList.add('copied');
            const span = btn.querySelector('span');
            const originalText = span ? span.textContent : 'Salin Link';
            if (span) span.textContent = 'Copied!';
            if (feedbackElem) {
                feedbackElem.textContent = 'Link copied to clipboard!';
                feedbackElem.classList.remove('error');
            }
            setTimeout(() => {
                btn.classList.remove('copied');
                if (span) span.textContent = originalText;
                if (feedbackElem) feedbackElem.textContent = '';
            }, 2000);
        }

        function handleCopyError(feedbackElem) {
            if (feedbackElem) {
                feedbackElem.textContent = 'Failed to copy link.';
                feedbackElem.classList.add('error');
            }
        }

        // 2. Skills Toggle
        const skillsToggle = document.getElementById('playerSkillsToggle');
        const skillsPanel = document.getElementById('playerSkillsPanel');
        if (skillsToggle && skillsPanel) {
            const newSkillsToggle = skillsToggle.cloneNode(true);
            skillsToggle.parentNode.replaceChild(newSkillsToggle, skillsToggle);

            newSkillsToggle.addEventListener('click', function() {
                const isOpen = skillsPanel.classList.contains('open');
                skillsPanel.classList.toggle('open', !isOpen);
                newSkillsToggle.setAttribute('aria-expanded', !isOpen);
            });
        }

        // 3. History Toggle & Fetching
        const historyToggle = document.getElementById('playerHistoryToggle');
        const historyPanel = document.getElementById('playerHistoryPanel');
        const historyContent = document.getElementById('playerHistoryContent');

        let historyLoaded = false;

        function fetchHistory() {
            if (!historyPanel || !historyContent) return;
            const playerId = historyPanel.getAttribute('data-player-id');
            if (!playerId || playerId === '0') return;

            historyContent.innerHTML = '<div class="history-loading"><i class="fas fa-spinner fa-spin"></i> Memuat riwayat pertandingan...</div>';

            // Get SITE_URL from global config or fallback to relative
            const siteUrl = window.FUTSCORE_SITE_URL || '';
            const fetchUrl = (siteUrl ? siteUrl + '/' : '') + 'get_player_match_history.php?player_id=' + playerId;

            fetch(fetchUrl)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        historyContent.innerHTML = '<div class="history-empty"><i class="fas fa-exclamation-circle"></i> ' + escapeHTML(data.message || 'Gagal memuat riwayat.') + '</div>';
                        return;
                    }

                    if (data.total === 0) {
                        historyContent.innerHTML = '<div class="history-empty"><i class="fas fa-info-circle"></i> Belum ada riwayat pertandingan.</div>';
                        return;
                    }

                    let html = '<div class="player-history-summary">';
                    data.event_summary.forEach(ev => {
                        html += '<span class="h-event-badge">' + escapeHTML(ev.name) + ' (' + parseInt(ev.match_count) + ' match)</span>';
                    });
                    html += '</div>';

                    html += '<div class="history-table-wrap"><table class="history-table"><thead><tr>';
                    html += '<th class="col-no">No</th>';
                    html += '<th class="col-tanggal">Tanggal</th>';
                    html += '<th class="col-pertandingan">Pertandingan</th>';
                    html += '<th class="col-event">Event</th>';
                    html += '<th class="col-posisi">Posisi</th>';
                    html += '<th class="col-peran">Peran</th>';
                    html += '<th class="col-babak">Babak</th>';
                    html += '<th class="col-hasil">Hasil</th>';
                    html += '<th>Goal</th>';
                    html += '</tr></thead><tbody>';

                    data.matches.forEach((m, idx) => {
                        let resultClass = 'h-result-na';
                        let resultText = '-';
                        if (m.player_team_side) {
                            const myScore = m.player_team_side === 'challenger' ? m.challenger_score : m.opponent_score;
                            const oppScore = m.player_team_side === 'challenger' ? m.opponent_score : m.challenger_score;
                            if (myScore !== null && oppScore !== null) {
                                if (myScore > oppScore) { resultClass = 'h-result-win'; resultText = 'W'; }
                                else if (myScore < oppScore) { resultClass = 'h-result-lose'; resultText = 'L'; }
                                else { resultClass = 'h-result-draw'; resultText = 'D'; }
                            }
                        }

                        const starterBadge = m.is_starting === 1 
                            ? '<span class="h-starter-badge h-starter-yes"><i class="fas fa-star"></i> Starter</span>' 
                            : (m.is_starting === 0 ? '<span class="h-starter-badge h-starter-sub">Cadangan</span>' : '-');
                        
                        const scoreText = (m.challenger_score !== null && m.opponent_score !== null) 
                            ? m.challenger_score + ' - ' + m.opponent_score 
                            : 'VS';

                        html += '<tr>';
                        html += '<td class="col-no">' + (idx + 1) + '</td>';
                        html += '<td class="col-tanggal">' + escapeHTML(m.challenge_date_fmt) + '</td>';
                        html += '<td class="col-pertandingan"><b>' + escapeHTML(m.challenger_name) + '</b> ' + scoreText + ' <b>' + escapeHTML(m.opponent_name) + '</b></td>';
                        html += '<td class="col-event"><span class="h-event-badge">' + escapeHTML(m.event_name) + '</span></td>';
                        html += '<td class="col-posisi">' + escapeHTML(m.position || '-') + '</td>';
                        html += '<td class="col-peran">' + starterBadge + '</td>';
                        html += '<td class="col-babak">' + (m.half ? '<span class="h-half-pill">Half ' + escapeHTML(m.half) + '</span>' : '-') + '</td>';
                        html += '<td class="col-hasil"><span class="' + resultClass + '">' + resultText + '</span></td>';
                        html += '<td>' + (parseInt(m.goal_count) > 0 ? '<b>' + parseInt(m.goal_count) + '</b>' : '0') + '</td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table></div>';
                    historyContent.innerHTML = html;
                    historyLoaded = true;
                })
                .catch(() => {
                    historyContent.innerHTML = '<div class="history-empty"><i class="fas fa-exclamation-triangle"></i> Terjadi kesalahan koneksi.</div>';
                });
        }

        if (historyToggle && historyPanel) {
            const newHistoryToggle = historyToggle.cloneNode(true);
            historyToggle.parentNode.replaceChild(newHistoryToggle, historyToggle);

            newHistoryToggle.addEventListener('click', function() {
                const isOpen = historyPanel.classList.contains('open');
                historyPanel.classList.toggle('open', !isOpen);
                newHistoryToggle.setAttribute('aria-expanded', !isOpen);
                if (!isOpen && !historyLoaded) {
                    fetchHistory();
                }
            });
        }

        // 4. Handle Hash Navigation
        function handleHash() {
            if (window.location.hash === '#playerHistoryPanel') {
                const historySec = document.getElementById('playerHistoryPanel');
                if (historySec) {
                    historySec.classList.add('open');
                    const toggleBtn = document.getElementById('playerHistoryToggle');
                    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
                    if (!historyLoaded) fetchHistory();
                    setTimeout(() => {
                        historySec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 500);
                }
            }
        }

        // Run hash check on init
        handleHash();
    }

    // Initial load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPlayerProfile);
    } else {
        initPlayerProfile();
    }

    // Handle hash change for single-page feel
    window.addEventListener('hashchange', function() {
        // Only re-init hash specific logic to avoid double fetching if already open
        const historySec = document.getElementById('playerHistoryPanel');
        if (window.location.hash === '#playerHistoryPanel' && historySec && !historySec.classList.contains('open')) {
            initPlayerProfile();
        }
    });

})();
