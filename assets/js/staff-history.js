/**
 * Staff Profile Logic
 * Handles: Share Panel, Certificates Modal, Match History Fetching (AJAX), and Hash Navigation
 */
(function() {
    'use strict';

    const IMAGE_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp']);

    // Helper to escape HTML and prevent XSS
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function getSiteUrl() {
        return (typeof window.SITE_URL !== 'undefined' && window.SITE_URL) ? window.SITE_URL : '';
    }

    function buildCertificateFileUrl(filename) {
        const clean = String(filename || '').trim();
        if (!clean) return '';
        const base = getSiteUrl();
        const prefix = base ? base + '/uploads/certificates/' : 'uploads/certificates/';
        return prefix + encodeURIComponent(clean);
    }

    function buildCertificateApiUrl(staffId) {
        const base = getSiteUrl();
        const path = 'includes/ajax_get_certificates.php?staff_id=' + encodeURIComponent(String(staffId || 0));
        return base ? base + '/' + path : path;
    }

    function openImageViewer(url, title) {
        const viewer = document.getElementById('imageViewer');
        const img = document.getElementById('fullSizeImage');
        const caption = document.getElementById('imageTitle');
        if (!viewer || !img || !caption) return;

        img.src = url;
        img.alt = title || '';
        caption.textContent = title || '';
        viewer.style.display = 'flex';
    }

    function closeImageViewer() {
        const viewer = document.getElementById('imageViewer');
        const img = document.getElementById('fullSizeImage');
        const caption = document.getElementById('imageTitle');
        if (!viewer || !img || !caption) return;

        img.src = '';
        caption.textContent = '';
        viewer.style.display = 'none';
    }

    function closeCertificateModal() {
        const modal = document.getElementById('certificateModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function buildCertificateCard(certificate) {
        const card = document.createElement('div');
        card.className = 'certificate-card';

        const header = document.createElement('div');
        header.className = 'certificate-header';

        const title = document.createElement('div');
        title.className = 'certificate-title';
        title.textContent = certificate.certificate_name || 'Lisensi';
        header.appendChild(title);

        const meta = document.createElement('div');
        meta.className = 'certificate-meta';

        if (certificate.issuing_authority) {
            const authority = document.createElement('div');
            authority.innerHTML = '<i class="fas fa-building"></i> ' + escapeHTML(certificate.issuing_authority);
            meta.appendChild(authority);
        }

        if (certificate.issue_date) {
            const issueDate = document.createElement('div');
            issueDate.innerHTML = '<i class="fas fa-calendar"></i> ' + escapeHTML(certificate.issue_date);
            meta.appendChild(issueDate);
        }

        if (meta.childNodes.length > 0) {
            header.appendChild(meta);
        }

        card.appendChild(header);

        const preview = document.createElement('div');
        preview.className = 'certificate-preview';

        const filename = String(certificate.certificate_file || '').trim();
        const fileExt = filename.includes('.') ? filename.split('.').pop().toLowerCase() : '';
        const fileUrl = buildCertificateFileUrl(filename);

        if (filename && IMAGE_EXTENSIONS.has(fileExt)) {
            const img = document.createElement('img');
            img.className = 'certificate-image';
            img.src = fileUrl;
            img.alt = certificate.certificate_name || 'Lisensi';
            img.addEventListener('click', function() {
                openImageViewer(fileUrl, certificate.certificate_name || 'Lisensi');
            });
            preview.appendChild(img);
        } else {
            const filePreview = document.createElement('div');
            filePreview.className = 'file-preview';

            const icon = document.createElement('div');
            icon.className = 'file-icon';
            icon.innerHTML = '<i class="fas fa-file-alt" aria-hidden="true"></i>';
            filePreview.appendChild(icon);

            const fileName = document.createElement('div');
            fileName.className = 'file-name';
            fileName.textContent = filename || 'Tidak ada file';
            filePreview.appendChild(fileName);

            if (fileUrl) {
                const actions = document.createElement('div');
                actions.className = 'file-actions';

                const viewLink = document.createElement('a');
                viewLink.className = 'btn-view';
                viewLink.href = fileUrl;
                viewLink.target = '_blank';
                viewLink.rel = 'noopener noreferrer';
                viewLink.innerHTML = '<i class="fas fa-eye"></i> Lihat';

                const downloadLink = document.createElement('a');
                downloadLink.className = 'btn-download';
                downloadLink.href = fileUrl;
                downloadLink.setAttribute('download', filename);
                downloadLink.innerHTML = '<i class="fas fa-download"></i> Unduh';

                actions.appendChild(viewLink);
                actions.appendChild(downloadLink);
                filePreview.appendChild(actions);
            }

            preview.appendChild(filePreview);
        }

        card.appendChild(preview);
        return card;
    }

    function renderCertificateGrid(certificates) {
        const grid = document.createElement('div');
        grid.className = 'certificates-grid';

        certificates.forEach(function(certificate) {
            grid.appendChild(buildCertificateCard(certificate));
        });

        return grid;
    }

    function initCertificateModal() {
        const modal = document.getElementById('certificateModal');
        if (!modal || modal.dataset.bound === '1') return;
        modal.dataset.bound = '1';

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeCertificateModal();
            }
        });

        const viewer = document.getElementById('imageViewer');
        if (viewer && viewer.dataset.bound !== '1') {
            viewer.dataset.bound = '1';
            viewer.addEventListener('click', function(e) {
                if (e.target === viewer) {
                    closeImageViewer();
                }
            });
        }

        // Expose for inline handlers
        window.closeCertificateModal = closeCertificateModal;
        window.closeImageViewer = closeImageViewer;
    }

    function loadCertificates(staffId, staffName) {
        const modal = document.getElementById('certificateModal');
        const modalTitle = document.getElementById('modalStaffName');
        const content = document.getElementById('certificateContent');

        if (!modal || !modalTitle || !content) return;

        modalTitle.textContent = staffName ? ('Lisensi Staf: ' + staffName) : 'Lisensi Staf';
        content.innerHTML = '<div class="loading-container"><div class="spinner"></div><div>Memuat lisensi...</div></div>';
        modal.style.display = 'flex';

        fetch(buildCertificateApiUrl(staffId), { headers: { 'Accept': 'application/json' } })
            .then(function(response) {
                if (!response.ok) {
                    return Promise.reject('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                const certificates = data && data.success && Array.isArray(data.certificates)
                    ? data.certificates
                    : [];
                content.innerHTML = '';

                if (certificates.length === 0) {
                    content.innerHTML = '<div class="no-certificates"><i class="fas fa-folder-open"></i><h3>Belum ada lisensi</h3><p>Lisensi staff belum tersedia.</p></div>';
                    return;
                }

                content.appendChild(renderCertificateGrid(certificates));
            })
            .catch(function() {
                content.innerHTML = '<div class="no-certificates"><i class="fas fa-exclamation-circle"></i><h3>Gagal memuat data</h3><p>Silakan coba lagi.</p></div>';
            });
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
                newShareToggle.setAttribute('aria-expanded', String(!isOpen));
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
                const url = sharePanel ? sharePanel.getAttribute('data-share-url') : window.location.href;

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(() => {
                        handleCopySuccess(newCopyBtn, feedback);
                    }).catch(() => {
                        handleCopyError(feedback);
                    });
                } else {
                    // Fallback for non-HTTPS or older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = url;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    textArea.style.top = '-999999px';
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

        // 2. Certificates Modal
        initCertificateModal();
        window.loadCertificates = loadCertificates;

        // 3. History Modal
        const historyModal = document.getElementById('staffHistoryModal');
        const historyBody = document.getElementById('staffHistoryBody');
        const historyTitle = document.getElementById('staffHistoryName');
        const historyMeta = document.getElementById('staffHistoryMeta');
        const historyClose = document.getElementById('staffHistoryClose');

        function closeHistoryModal() {
            if (!historyModal) return;
            historyModal.classList.remove('open');
            historyModal.setAttribute('aria-hidden', 'true');
        }

        function openHistoryModal(staffId, staffName, teamName) {
            if (!historyModal || !historyBody || !historyTitle || !historyMeta) return;
            if (!staffId || staffId === '0') return;

            historyModal.classList.add('open');
            historyModal.setAttribute('aria-hidden', 'false');
            historyTitle.textContent = staffName ? staffName : '-';
            historyMeta.textContent = teamName ? ('Team: ' + teamName) : 'Memuat data...';
            historyBody.innerHTML = '<div class="staff-history-loading"><i class="fas fa-spinner fa-spin"></i> Memuat riwayat...</div>';

            const siteUrl = getSiteUrl();
            const fetchUrl = (siteUrl ? siteUrl + '/' : '') + 'get_staff_match_history.php?staff_id=' + encodeURIComponent(String(staffId));

            fetch(fetchUrl)
                .then(function(res) {
                    if (!res.ok) {
                        return Promise.reject('HTTP ' + res.status);
                    }
                    return res.json();
                })
                .then(function(data) {
                    if (!data || !data.success) {
                        historyMeta.textContent = teamName ? ('Team: ' + teamName) : '-';
                        historyBody.innerHTML = '<div class="staff-history-empty"><i class="fas fa-exclamation-circle"></i> ' + escapeHTML((data && data.message) ? data.message : 'Gagal memuat riwayat.') + '</div>';
                        return;
                    }

                    const total = parseInt(data.total || 0, 10);
                    const eventTotal = parseInt(data.event_total || 0, 10);
                    const metaParts = [];
                    if (teamName) metaParts.push('Team: ' + teamName);
                    metaParts.push('Total ' + total + ' match');
                    metaParts.push(eventTotal + ' event');
                    historyMeta.textContent = metaParts.join(' | ');

                    if (!Array.isArray(data.matches) || data.matches.length === 0) {
                        historyBody.innerHTML = '<div class="staff-history-empty"><i class="fas fa-info-circle"></i> Belum ada riwayat pertandingan.</div>';
                        return;
                    }

                    let html = '<div class="staff-history-table-wrap"><table class="staff-history-table"><thead><tr>';
                    html += '<th class="col-no">No</th>';
                    html += '<th class="col-tanggal">Tanggal</th>';
                    html += '<th class="col-pertandingan">Pertandingan</th>';
                    html += '<th class="col-event">Event</th>';
                    html += '<th class="col-jabatan">Jabatan</th>';
                    html += '<th class="col-peran">Peran</th>';
                    html += '<th class="col-babak">Babak</th>';
                    html += '<th class="col-status">Status</th>';
                    html += '</tr></thead><tbody>';

                    data.matches.forEach(function(m, idx) {
                        const halfLabel = m.half_label || (m.half ? ('Half ' + m.half) : '-');
                        html += '<tr>';
                        html += '<td class="col-no">' + (idx + 1) + '</td>';
                        html += '<td class="col-tanggal">' + escapeHTML(m.challenge_date_fmt || '-') + '</td>';
                        html += '<td class="col-pertandingan"><b>' + escapeHTML(m.challenger_name || '-') + '</b> VS <b>' + escapeHTML(m.opponent_name || '-') + '</b></td>';
                        html += '<td class="col-event"><span class="staff-history-pill">' + escapeHTML(m.event_name || '-') + '</span></td>';
                        html += '<td class="col-jabatan">' + escapeHTML(m.sport_type || '-') + '</td>';
                        html += '<td class="col-peran"><span class="staff-history-pill">' + escapeHTML(m.role || 'Official') + '</span></td>';
                        html += '<td class="col-babak">' + (halfLabel ? '<span class="staff-history-pill">' + escapeHTML(halfLabel) + '</span>' : '-') + '</td>';
                        html += '<td class="col-status"><span class="staff-history-pill">' + escapeHTML(m.status || '-') + '</span></td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table></div>';
                    historyBody.innerHTML = html;
                })
                .catch(function() {
                    historyMeta.textContent = teamName ? ('Team: ' + teamName) : '-';
                    historyBody.innerHTML = '<div class="staff-history-empty"><i class="fas fa-exclamation-triangle"></i> Terjadi kesalahan koneksi.</div>';
                });
        }

        if (historyModal && historyModal.dataset.bound !== '1') {
            historyModal.dataset.bound = '1';
            historyModal.addEventListener('click', function(e) {
                if (e.target === historyModal) {
                    closeHistoryModal();
                }
            });
        }

        if (historyClose && historyClose.dataset.bound !== '1') {
            historyClose.dataset.bound = '1';
            historyClose.addEventListener('click', closeHistoryModal);
        }

        function bindCloneButton(btn, handler) {
            if (!btn || !btn.parentNode) return null;
            const clone = btn.cloneNode(true);
            btn.parentNode.replaceChild(clone, btn);
            clone.addEventListener('click', handler);
            return clone;
        }

        function openHistoryFromButton(btn) {
            const staffId = btn.getAttribute('data-staff-id') || '';
            const staffName = btn.getAttribute('data-staff-name') || '';
            const teamName = btn.getAttribute('data-team-name') || '';
            openHistoryModal(staffId, staffName, teamName);
        }

        const historyToggle = document.getElementById('staffHistoryToggle');
        bindCloneButton(historyToggle, function() {
            openHistoryFromButton(this);
        });

        document.querySelectorAll('.btn-staff-history').forEach(function(btn) {
            bindCloneButton(btn, function() {
                openHistoryFromButton(this);
            });
        });
    }

    // Initial load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStaffProfile);
    } else {
        initStaffProfile();
    }

})();
