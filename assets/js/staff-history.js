/**
 * Staff History and Profile Logic
 * Handles: Share Panel, Match History Fetching (AJAX), and Hash Navigation
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

    function initStaffProfile() {
        // 1. Share Panel Logic
        const shareToggle = document.getElementById('staffShareToggle');
        const sharePanel = document.getElementById('staffSharePanel');
        const copyBtn = document.getElementById('staffShareCopyBtn');
        const feedback = document.getElementById('staffShareFeedback');

        if (shareToggle && sharePanel) {
            // Remove existing listener if any
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

        // 2. History Toggle & Fetching
        const historyToggle = document.getElementById('staffHistoryToggle');
        const historyPanel = document.getElementById('staffHistoryPanel');
        const historyContent = document.getElementById('staffHistoryContent');

        let historyLoaded = false;

        function fetchHistory() {
            if (!historyPanel || !historyContent) return;
            const staffId = historyPanel.getAttribute('data-staff-id');
            if (!staffId || staffId === '0') return;

            historyContent.innerHTML = '<div class="history-loading"><i class="fas fa-spinner fa-spin"></i> Memuat riwayat pertandingan...</div>';

            const siteUrl = window.SITE_URL || '';
            const fetchUrl = (siteUrl ? siteUrl + '/' : '') + 'get_staff_match_history.php?staff_id=' + staffId;

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

                    let html = '<div class="staff-history-summary">';
                    data.event_summary.forEach(ev => {
                        html += '<span class="h-event-badge">' + escapeHTML(ev.name) + ' (' + parseInt(ev.match_count) + ' match)</span>';
                    });
                    html += '</div>';

                    html += '<div class="history-table-wrap"><table class="history-table"><thead><tr>';
                    html += '<th class="col-no">No</th>';
                    html += '<th class="col-tanggal">Tanggal</th>';
                    html += '<th class="col-pertandingan">Pertandingan</th>';
                    html += '<th class="col-event">Event</th>';
                    html += '<th class="col-jabatan">Jabatan</th>';
                    html += '<th class="col-peran">Peran</th>';
                    html += '<th class="col-babak">Babak</th>';
                    html += '<th class="col-status">Status</th>';
                    html += '</tr></thead><tbody>';

                    data.matches.forEach((m, idx) => {
                        const statusClass = 'h-status-' + (m.status || '').toLowerCase().replace(/\s+/g, '-');
                        
                        html += '<tr>';
                        html += '<td class="col-no">' + (idx + 1) + '</td>';
                        html += '<td class="col-tanggal">' + escapeHTML(m.challenge_date_fmt) + '</td>';
                        html += '<td class="col-pertandingan"><b>' + escapeHTML(m.challenger_name) + '</b> VS <b>' + escapeHTML(m.opponent_name) + '</b></td>';
                        html += '<td class="col-event"><span class="h-event-badge">' + escapeHTML(m.event_name) + '</span></td>';
                        html += '<td class="col-jabatan">' + escapeHTML(m.sport_type || '-') + '</td>';
                        html += '<td class="col-peran"><span class="h-role-badge">' + escapeHTML(m.role || 'Official') + '</span></td>';
                        html += '<td class="col-babak">' + (m.half ? '<span class="h-half-pill">Half ' + escapeHTML(m.half) + '</span>' : '-') + '</td>';
                        html += '<td class="col-status"><span class="h-status-pill ' + statusClass + '">' + escapeHTML(m.status || '-') + '</span></td>';
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
            
            // Allow global call from data-staff-id buttons
            document.querySelectorAll('.btn-staff-history').forEach(btn => {
                btn.addEventListener('click', function() {
                    const isOpen = historyPanel.classList.contains('open');
                    if (!isOpen) {
                        historyPanel.classList.add('open');
                        newHistoryToggle.setAttribute('aria-expanded', 'true');
                    }
                    if (!historyLoaded) fetchHistory();
                    historyPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });
        }

        // 3. Handle Hash Navigation
        function handleHash() {
            if (window.location.hash === '#staffHistoryPanel') {
                const historySec = document.getElementById('staffHistoryPanel');
                if (historySec) {
                    historySec.classList.add('open');
                    const toggleBtn = document.getElementById('staffHistoryToggle');
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
        document.addEventListener('DOMContentLoaded', initStaffProfile);
    } else {
        initStaffProfile();
    }

    // Handle hash change for single-page feel
    window.addEventListener('hashchange', function() {
        const historySec = document.getElementById('staffHistoryPanel');
        if (window.location.hash === '#staffHistoryPanel' && historySec && !historySec.classList.contains('open')) {
            initStaffProfile();
        }
    });

})();
