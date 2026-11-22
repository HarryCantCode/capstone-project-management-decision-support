{{-- Global Bottom-Center Processing Pill Indicator --}}
<style>
    .dex-processing-pill-wrap {
        position: fixed !important;
        bottom: 1.75rem !important;
        left: 50% !important;
        transform: translateX(-50%) translateY(16px) !important;
        z-index: 100000 !important;
        pointer-events: none !important;
        opacity: 0 !important;
        visibility: hidden !important;
        transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                    transform 0.22s cubic-bezier(0.16, 1, 0.3, 1),
                    visibility 0.22s !important;
        max-width: calc(100vw - 2rem) !important;
    }

    .dex-processing-pill-wrap.is-active {
        opacity: 1 !important;
        visibility: visible !important;
        transform: translateX(-50%) translateY(0) !important;
    }

    .dex-processing-pill {
        display: inline-flex !important;
        align-items: center !important;
        gap: 0.75rem !important;
        padding: 0.6875rem 1.25rem !important;
        border-radius: 9999px !important;
        background: rgba(15, 23, 42, 0.94) !important;
        color: #F8FAFC !important;
        border: 1px solid rgba(56, 189, 248, 0.35) !important;
        box-shadow: 0 12px 32px -6px rgba(0, 0, 0, 0.4),
                    0 4px 14px -2px rgba(0, 0, 0, 0.25),
                    0 0 0 1px rgba(255, 255, 255, 0.06) inset !important;
        backdrop-filter: blur(10px) !important;
        -webkit-backdrop-filter: blur(10px) !important;
        font-family: var(--font-sans, 'Inter', -apple-system, BlinkMacSystemFont, sans-serif) !important;
        font-size: 0.84375rem !important;
        font-weight: 600 !important;
        line-height: 1.3 !important;
        letter-spacing: 0.01em !important;
        white-space: nowrap !important;
        pointer-events: auto !important;
    }

    [data-theme="dark"] .dex-processing-pill {
        background: rgba(14, 21, 34, 0.96) !important;
        border-color: rgba(56, 189, 248, 0.45) !important;
        color: #F1F5F9 !important;
    }

    .dex-processing-spinner {
        width: 18px !important;
        height: 18px !important;
        flex-shrink: 0 !important;
        animation: dex-pill-spin 0.8s linear infinite !important;
        color: #38BDF8 !important;
    }

    .dex-processing-text {
        color: #F8FAFC !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }

    @keyframes dex-pill-spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }
</style>

<div
    id="dex-global-processing-pill"
    class="dex-processing-pill-wrap"
    role="status"
    aria-live="polite"
    aria-atomic="true"
>
    <div class="dex-processing-pill">
        <svg class="dex-processing-spinner" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" stroke-opacity="0.25"></circle>
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"></path>
        </svg>
        <span id="dex-global-processing-text" class="dex-processing-text">Processing your request, please wait...</span>
    </div>
</div>

<script>
    (function () {
        if (window.__dexProcessingIndicatorInitialized) return;
        window.__dexProcessingIndicatorInitialized = true;

        const DEFAULT_MESSAGE = 'Processing your request, please wait...';
        let activeFetchCount = 0;
        let formSubmitting = false;
        let safetyTimer = null;

        function getElements() {
            return {
                wrap: document.getElementById('dex-global-processing-pill'),
                text: document.getElementById('dex-global-processing-text')
            };
        }

        function updateVisibility(customMessage) {
            const { wrap, text } = getElements();
            if (!wrap) return;

            const shouldShow = formSubmitting || activeFetchCount > 0;
            if (shouldShow) {
                if (text) {
                    text.textContent = customMessage || DEFAULT_MESSAGE;
                }
                wrap.classList.add('is-active');
            } else {
                wrap.classList.remove('is-active');
            }
        }

        window.showProcessingIndicator = function (message) {
            formSubmitting = true;
            updateVisibility(message);
            clearTimeout(safetyTimer);
            safetyTimer = setTimeout(() => {
                formSubmitting = false;
                updateVisibility();
            }, 45000);
        };

        window.hideProcessingIndicator = function () {
            formSubmitting = false;
            activeFetchCount = 0;
            clearTimeout(safetyTimer);
            updateVisibility();
        };

        window.addEventListener('processing:start', function (e) {
            window.showProcessingIndicator(e.detail && e.detail.message ? e.detail.message : DEFAULT_MESSAGE);
        });

        window.addEventListener('processing:end', function () {
            window.hideProcessingIndicator();
        });

        // Reset indicator when page is restored from bfcache or finishes loading
        window.addEventListener('pageshow', function () {
            window.hideProcessingIndicator();
        });

        function isDownloadOrNewTabForm(form) {
            if (!form) return false;
            const target = (form.getAttribute('target') || '').toLowerCase();
            if (target === '_blank') return true;
            const action = (form.getAttribute('action') || '').toLowerCase();
            if (action.includes('/export') || action.includes('/download') || action.includes('/pdf')) {
                return true;
            }
            return false;
        }

        // 1. Standard Form Submissions (bubble phase so client-side validation can preventDefault first)
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!form || form.tagName !== 'FORM') return;
            if (form.hasAttribute('data-no-processing-pill')) return;

            // Defer to microtask/tick so any synchronous submit listeners (like Alpine or custom validation)
            // that call e.preventDefault() have already run.
            setTimeout(function () {
                if (e.defaultPrevented) return;
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

                window.showProcessingIndicator(form.getAttribute('data-processing-message') || DEFAULT_MESSAGE);

                // If form triggers a file download or opens in a new tab, auto-hide after 3.5s
                if (isDownloadOrNewTabForm(form)) {
                    setTimeout(function () {
                        window.hideProcessingIndicator();
                    }, 3500);
                }
            }, 0);
        }, false);

        // 2. Programmatic form.submit() calls (which bypass the DOM 'submit' event)
        if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype.submit) {
            const nativeSubmit = HTMLFormElement.prototype.submit;
            HTMLFormElement.prototype.submit = function () {
                if (!this.hasAttribute('data-no-processing-pill')) {
                    window.showProcessingIndicator(this.getAttribute('data-processing-message') || DEFAULT_MESSAGE);
                    if (isDownloadOrNewTabForm(this)) {
                        setTimeout(function () {
                            window.hideProcessingIndicator();
                        }, 3500);
                    }
                }
                return nativeSubmit.apply(this, arguments);
            };
        }

        // 3. Global Fetch Interceptor for mutating AJAX actions (POST, PUT, PATCH, DELETE)
        if (typeof window.fetch === 'function') {
            const prevFetch = window.fetch;
            window.fetch = function (input, init) {
                const method = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
                const url = typeof input === 'string' ? input : (input && input.url ? input.url : '');

                const isBackgroundPoll = url.includes('/live-updates/poll')
                    || url.includes('/notifications/unread-count')
                    || url.includes('/projects/check-similarity')
                    || url.includes('/global-search');

                const isMutatingAction = !isBackgroundPoll && method !== 'GET' && method !== 'HEAD';

                if (isMutatingAction) {
                    activeFetchCount++;
                    updateVisibility();
                }

                try {
                    const promise = prevFetch.apply(this, arguments);
                    if (isMutatingAction && promise && typeof promise.finally === 'function') {
                        return promise.finally(function () {
                            activeFetchCount = Math.max(0, activeFetchCount - 1);
                            // Slight delay so if the handler immediately triggers a page reload/redirect, the pill stays smooth
                            setTimeout(function () {
                                updateVisibility();
                            }, 120);
                        });
                    }
                    return promise;
                } catch (err) {
                    if (isMutatingAction) {
                        activeFetchCount = Math.max(0, activeFetchCount - 1);
                        updateVisibility();
                    }
                    throw err;
                }
            };
        }
    })();
</script>
