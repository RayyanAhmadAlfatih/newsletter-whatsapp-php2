'use strict';

(function () {
    const formatSummary = (data) => {
        if (!data || typeof data !== 'object') {
            return String(data);
        }

        const lines = [];
        if (data.message) {
            lines.push(String(data.message));
        }

        if (typeof data.processed !== 'undefined') {
            lines.push(`Diproses: ${data.processed}`);
        }
        if (typeof data.sent !== 'undefined') {
            lines.push(`Terkirim: ${data.sent}`);
        }
        if (typeof data.failed !== 'undefined') {
            lines.push(`Gagal: ${data.failed}`);
        }
        if (typeof data.skipped !== 'undefined') {
            lines.push(`Dilewati: ${data.skipped}`);
        }

        if (Array.isArray(data.details) && data.details.length > 0) {
            const limitedDetails = data.details.slice(0, 5)
                .map((detail) => {
                    const subscriber = detail.subscriber ? `${detail.subscriber}` : 'Unknown';
                    const status = detail.status ? detail.status : 'unknown';
                    const extra = detail.error || detail.reason || '';
                    return `- ${subscriber} (${status}${extra ? `: ${extra}` : ''})`;
                });
            lines.push('Rincian:');
            lines.push(...limitedDetails);
            if (data.details.length > limitedDetails.length) {
                lines.push(`... dan ${data.details.length - limitedDetails.length} rincian lainnya.`);
            }
        }

        return lines.join('\n');
    };

    const showAlert = (message, isError = false) => {
        if (!message) {
            return;
        }

        const containerId = 'admin-flash-container';
        let container = document.getElementById(containerId);
        if (!container) {
            container = document.createElement('div');
            container.id = containerId;
            container.style.position = 'fixed';
            container.style.right = '20px';
            container.style.bottom = '20px';
            container.style.zIndex = '9999';
            container.style.maxWidth = '360px';
            document.body.appendChild(container);
        }

        const alertBox = document.createElement('div');
        alertBox.textContent = message;
        alertBox.style.background = isError ? '#dc3545' : '#28a745';
        alertBox.style.color = '#fff';
        alertBox.style.padding = '14px 18px';
        alertBox.style.marginTop = '10px';
        alertBox.style.borderRadius = '10px';
        alertBox.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
        alertBox.style.fontSize = '0.95rem';
        alertBox.style.whiteSpace = 'pre-line';

        container.appendChild(alertBox);

        setTimeout(() => {
            alertBox.classList.add('fade-out');
            alertBox.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            alertBox.style.opacity = '0';
            alertBox.style.transform = 'translateY(10px)';
            setTimeout(() => {
                if (alertBox.parentNode) {
                    alertBox.parentNode.removeChild(alertBox);
                }
            }, 450);
        }, 6000);
    };

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const confirmMessage = form.dataset.confirm;
        if (confirmMessage && !window.confirm(confirmMessage)) {
            event.preventDefault();
            return;
        }

        if (form.dataset.async === 'true') {
            event.preventDefault();

            const submitButton = form.querySelector('[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.dataset.originalText = submitButton.textContent || submitButton.value || '';
                if (submitButton.tagName === 'BUTTON') {
                    submitButton.textContent = 'Memproses...';
                } else {
                    submitButton.value = 'Memproses...';
                }
            }

            try {
                const response = await fetch(form.action, {
                    method: form.method || 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const contentType = response.headers.get('Content-Type') || '';
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                if (contentType.includes('application/json')) {
                    const payload = await response.json();
                    showAlert(formatSummary(payload), false);
                } else {
                    const text = await response.text();
                    showAlert(text, false);
                }
            } catch (error) {
                showAlert(`Terjadi kesalahan: ${error instanceof Error ? error.message : error}`, true);
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    if (submitButton.tagName === 'BUTTON') {
                        submitButton.textContent = submitButton.dataset.originalText || 'Kirim';
                    } else {
                        submitButton.value = submitButton.dataset.originalText || 'Kirim';
                    }
                }
            }
        }
    });
})();
