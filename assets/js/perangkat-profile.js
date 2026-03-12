/**
 * Perangkat profile interactions:
 * - Share panel (toggle, copy, native share)
 * - License modal (AJAX load + image viewer)
 * - Match history modal (AJAX load)
 */
(function () {
    'use strict';

    const IMAGE_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp']);

    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function getSiteUrl() {
        return (typeof SITE_URL !== 'undefined' && SITE_URL) ? SITE_URL : '';
    }

    function buildLicenseFileUrl(filename) {
        const base = getSiteUrl();
        const clean = String(filename || '').trim();
        if (!clean) return '';
        const prefix = base ? base + '/uploads/perangkat/licenses/' : 'uploads/perangkat/licenses/';
        return prefix + encodeURIComponent(clean);
    }

    function buildLicenseApiUrl(perangkatId) {
        const base = getSiteUrl();
        const path = 'includes/ajax_get_perangkat_licenses.php?perangkat_id=' + encodeURIComponent(String(perangkatId || 0));
        return base ? base + '/' + path : path;
    }

    function initSharePanel() {
        const shareToggle = document.getElementById('perangkatShareToggle');
        const sharePanel = document.getElementById('perangkatSharePanel');
        const copyBtn = document.getElementById('perangkatShareCopyBtn');
        const feedback = document.getElementById('perangkatShareFeedback');
        const nativeBtn = document.getElementById('perangkatShareNativeBtn');

        if (shareToggle && sharePanel) {
            shareToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = sharePanel.classList.contains('open');
                sharePanel.classList.toggle('open', !isOpen);
                shareToggle.setAttribute('aria-expanded', String(!isOpen));
            });

            document.addEventListener('click', function (e) {
                if (sharePanel.classList.contains('open') && !sharePanel.contains(e.target)) {
                    sharePanel.classList.remove('open');
                    shareToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        if (nativeBtn && sharePanel) {
            if (navigator.share) {
                nativeBtn.style.display = 'inline-flex';
                nativeBtn.addEventListener('click', function () {
                    const url = sharePanel.getAttribute('data-share-url') || window.location.href;
                    const text = sharePanel.getAttribute('data-share-text') || 'Bagikan profil perangkat';
                    navigator.share({ title: text, text: text, url: url })
                        .catch(function () { /* ignore cancel */ });
                });
            } else {
                nativeBtn.style.display = 'none';
            }
        }

        if (copyBtn && sharePanel) {
            copyBtn.addEventListener('click', function () {
                const url = sharePanel.getAttribute('data-share-url') || window.location.href;

                function handleCopySuccess() {
                    copyBtn.classList.add('copied');
                    const span = copyBtn.querySelector('span');
                    const originalText = span ? span.textContent : 'Salin Link';
                    if (span) span.textContent = 'Copied!';
                    if (feedback) {
                        feedback.textContent = 'Link copied to clipboard!';
                        feedback.classList.remove('error');
                    }
                    setTimeout(function () {
                        copyBtn.classList.remove('copied');
                        if (span) span.textContent = originalText;
                        if (feedback) feedback.textContent = '';
                    }, 2000);
                }

                function handleCopyError() {
                    if (feedback) {
                        feedback.textContent = 'Failed to copy link.';
                        feedback.classList.add('error');
                    }
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(handleCopySuccess).catch(handleCopyError);
                } else {
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
                        handleCopySuccess();
                    } catch (err) {
                        handleCopyError();
                    }
                    document.body.removeChild(textArea);
                }
            });
        }
    }

    function buildLicenseCard(certificate) {
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
        const fileUrl = buildLicenseFileUrl(filename);

        if (filename && IMAGE_EXTENSIONS.has(fileExt)) {
            const img = document.createElement('img');
            img.className = 'certificate-image';
            img.src = fileUrl;
            img.alt = certificate.certificate_name || 'Lisensi';
            img.addEventListener('click', function () {
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

    function renderLicenseGrid(certificates) {
        const grid = document.createElement('div');
        grid.className = 'certificates-grid';

        certificates.forEach(function (certificate) {
            grid.appendChild(buildLicenseCard(certificate));
        });

        return grid;
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

    function initCertificateModal() {
        const modal = document.getElementById('certificateModal');
        if (!modal) return;

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeCertificateModal();
            }
        });

        const viewer = document.getElementById('imageViewer');
        if (viewer) {
            viewer.addEventListener('click', function (e) {
                if (e.target === viewer) {
                    closeImageViewer();
                }
            });
        }

        // Expose for inline handlers
        window.closeCertificateModal = closeCertificateModal;
        window.closeImageViewer = closeImageViewer;
    }

    function closeCertificateModal() {
        const modal = document.getElementById('certificateModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function loadLicenses(perangkatId, perangkatName) {
        const modal = document.getElementById('certificateModal');
        const modalTitle = document.getElementById('modalPerangkatName');
        const content = document.getElementById('certificateContent');

        if (!modal || !modalTitle || !content) return;

        modalTitle.textContent = perangkatName ? ('Lisensi Perangkat: ' + perangkatName) : 'Lisensi Perangkat';
        content.innerHTML = '<div class="loading-container"><div class="spinner"></div><div>Memuat lisensi...</div></div>';
        modal.style.display = 'flex';

        fetch(buildLicenseApiUrl(perangkatId), { headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                if (!response.ok) {
                    return Promise.reject('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                const certificates = data && data.success && Array.isArray(data.certificates) ? data.certificates : [];
                content.innerHTML = '';

                if (certificates.length === 0) {
                    content.innerHTML = '<div class="no-certificates"><i class="fas fa-folder-open"></i><h3>Belum ada lisensi</h3><p>Lisensi perangkat belum tersedia.</p></div>';
                    return;
                }

                content.appendChild(renderLicenseGrid(certificates));
            })
            .catch(function () {
                content.innerHTML = '<div class="no-certificates"><i class="fas fa-exclamation-circle"></i><h3>Gagal memuat data</h3><p>Silakan coba lagi.</p></div>';
            });
    }

    function initMatchHistoryModal() {
        const modal = document.getElementById('perangkatHistoryModal');
        const body = document.getElementById('perangkatHistoryBody');
        const title = document.getElementById('perangkatHistoryName');
        const meta = document.getElementById('perangkatHistoryMeta');
        const closeBtn = document.getElementById('perangkatHistoryClose');

        if (!modal || !body || !title || !meta) return;

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }

        function openModal(perangkatId, perangkatName) {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            title.textContent = perangkatName ? ('Riwayat Match: ' + perangkatName) : 'Riwayat Match';
            meta.textContent = 'Memuat data...';
            body.innerHTML = '<div class="match-history-loading"><i class="fas fa-spinner fa-spin"></i> Memuat riwayat...</div>';

            const base = getSiteUrl();
            const url = (base ? base + '/' : '') + 'get_perangkat_match_history.php?perangkat_id=' + encodeURIComponent(String(perangkatId || 0));

            fetch(url)
                .then(function (response) {
                    if (!response.ok) {
                        return Promise.reject('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.success) {
                        meta.textContent = '-';
                        body.innerHTML = '<div class="match-history-empty"><i class="fas fa-exclamation-circle"></i> ' + escapeHTML((data && data.message) ? data.message : 'Gagal memuat riwayat.') + '</div>';
                        return;
                    }

                    const total = parseInt(data.total || 0, 10);
                    const eventTotal = parseInt(data.event_total || 0, 10);
                    meta.textContent = 'Total ' + total + ' match • ' + eventTotal + ' event';

                    if (!Array.isArray(data.matches) || data.matches.length === 0) {
                        body.innerHTML = '<div class="match-history-empty"><i class="fas fa-info-circle"></i> Belum ada riwayat pertandingan.</div>';
                        return;
                    }

                    let html = '<table class="match-history-table">';
                    html += '<thead><tr>';
                    html += '<th>No</th>';
                    html += '<th>Tanggal</th>';
                    html += '<th>Pertandingan</th>';
                    html += '<th>Event</th>';
                    html += '<th>Skor</th>';
                    html += '<th>Status</th>';
                    html += '</tr></thead><tbody>';

                    data.matches.forEach(function (m, idx) {
                        const date = escapeHTML(m.challenge_date_fmt || '-');
                        const challenger = escapeHTML(m.challenger_name || '-');
                        const opponent = escapeHTML(m.opponent_name || '-');
                        const eventName = escapeHTML(m.event_name || '-');
                        const score = (m.challenger_score !== null && m.opponent_score !== null)
                            ? (parseInt(m.challenger_score, 10) + ' - ' + parseInt(m.opponent_score, 10))
                            : 'VS';
                        const statusText = escapeHTML(m.status || '-');
                        const statusKey = String(m.status || '').toLowerCase();
                        let statusClass = 'default';
                        if (statusKey.includes('complete') || statusKey.includes('selesai') || statusKey.includes('finish')) {
                            statusClass = 'completed';
                        } else if (statusKey.includes('accept') || statusKey.includes('approve')) {
                            statusClass = 'accepted';
                        } else if (statusKey.includes('pending') || statusKey.includes('wait') || statusKey.includes('tunda')) {
                            statusClass = 'pending';
                        }

                        html += '<tr>';
                        html += '<td>' + (idx + 1) + '</td>';
                        html += '<td>' + date + '</td>';
                        html += '<td><strong>' + challenger + '</strong> vs <strong>' + opponent + '</strong></td>';
                        html += '<td>' + eventName + '</td>';
                        html += '<td>' + score + '</td>';
                        html += '<td><span class="history-status-pill ' + statusClass + '">' + statusText + '</span></td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    body.innerHTML = html;
                })
                .catch(function () {
                    meta.textContent = '-';
                    body.innerHTML = '<div class="match-history-empty"><i class="fas fa-exclamation-triangle"></i> Terjadi kesalahan koneksi.</div>';
                });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        document.querySelectorAll('.btn-perangkat-history').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const perangkatId = btn.getAttribute('data-perangkat-id');
                const perangkatName = btn.getAttribute('data-perangkat-name') || '';
                openModal(perangkatId, perangkatName);
            });
        });
    }

    function initPerangkatProfile() {
        initSharePanel();
        initCertificateModal();
        initMatchHistoryModal();

        // expose for inline handlers
        window.loadLicenses = loadLicenses;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPerangkatProfile);
    } else {
        initPerangkatProfile();
    }
})();
