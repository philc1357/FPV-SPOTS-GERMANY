// =============================================================
// FPV Spots Germany – PWA: Service Worker + Install-Prompt
// =============================================================
(function () {
    'use strict';

    // ── Service Worker registrieren ─────────────────────────
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .then(handleSWRegistration)
            .catch(function (err) {
                console.error('SW Registrierung fehlgeschlagen:', err);
            });
    }

    // ── SW Update-Erkennung ─────────────────────────────────
    function handleSWRegistration(reg) {
        reg.addEventListener('updatefound', function () {
            var newWorker = reg.installing;
            newWorker.addEventListener('statechange', function () {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                    showUpdateBanner();
                }
            });
        });
    }

    // ── iOS-Hinweis (Safari erkennen, noch nicht installiert) ──
    var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
    var isInStandaloneMode = ('standalone' in navigator) && navigator.standalone;

    if (isIos && !isInStandaloneMode) {
        var iosDismissed = localStorage.getItem('pwa-ios-dismissed');
        if (!iosDismissed || Date.now() - parseInt(iosDismissed, 10) > 30 * 24 * 60 * 60 * 1000) {
            setTimeout(showIosBanner, 3000);
        }
    }

    function showIosBanner() {
        var banner = document.getElementById('pwaIosBanner');
        if (banner) banner.classList.remove('d-none');
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('#pwaIosDismiss')) {
            localStorage.setItem('pwa-ios-dismissed', Date.now().toString());
            var banner = document.getElementById('pwaIosBanner');
            if (banner) banner.classList.add('d-none');
        }
    });

    // ── Install-Prompt ──────────────────────────────────────
    var deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;

        // Nicht anzeigen, wenn innerhalb der letzten 30 Tage abgelehnt
        var dismissed = localStorage.getItem('pwa-install-dismissed');
        if (dismissed && Date.now() - parseInt(dismissed, 10) < 30 * 24 * 60 * 60 * 1000) {
            return;
        }

        showInstallBanner();
    });

    window.addEventListener('appinstalled', function () {
        hideInstallBanner();
        deferredPrompt = null;
    });

    // ── Install-Banner ──────────────────────────────────────
    function showInstallBanner() {
        var banner = document.getElementById('pwaInstallBanner');
        if (banner) banner.classList.remove('d-none');
    }

    function hideInstallBanner() {
        var banner = document.getElementById('pwaInstallBanner');
        if (banner) banner.classList.add('d-none');
    }

    // Install-Button
    document.addEventListener('click', function (e) {
        if (e.target.closest('#pwaInstallBtn')) {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function (result) {
                if (result.outcome === 'dismissed') {
                    localStorage.setItem('pwa-install-dismissed', Date.now().toString());
                }
                deferredPrompt = null;
                hideInstallBanner();
            });
        }

        if (e.target.closest('#pwaInstallDismiss')) {
            localStorage.setItem('pwa-install-dismissed', Date.now().toString());
            hideInstallBanner();
        }

        if (e.target.closest('#pwaUpdateBtn')) {
            if (navigator.serviceWorker.controller) {
                navigator.serviceWorker.controller.postMessage('SKIP_WAITING');
            }
            location.reload();
        }
    });

    // ── Update-Banner ───────────────────────────────────────
    function showUpdateBanner() {
        var banner = document.getElementById('pwaUpdateBanner');
        if (banner) banner.classList.remove('d-none');
    }
})();
