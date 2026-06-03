(function () {
    var deferredInstallPrompt = null;
    var installShell = null;
    var installButton = null;
    var installModal = null;
    var installClose = null;
    var installDismiss = null;

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    }

    function isiOS() {
        return /iphone|ipad|ipod/i.test(window.navigator.userAgent) ||
            (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
    }

    function installDismissed() {
        try {
            return window.localStorage.getItem('tpPortalInstallDismissed') === '1';
        } catch (error) {
            return false;
        }
    }

    function dismissInstall() {
        try {
            window.localStorage.setItem('tpPortalInstallDismissed', '1');
        } catch (error) {}
    }

    function shouldHideInstall() {
        return isStandalone() || installDismissed();
    }

    function showInstallButton() {
        if (!installButton || shouldHideInstall()) {
            return;
        }

        if (installShell) {
            installShell.hidden = false;
        }

        installButton.hidden = false;
    }

    function hideInstallButton() {
        if (installShell) {
            installShell.hidden = true;
        }

        if (installButton) {
            installButton.hidden = true;
        }
    }

    function openInstructions() {
        if (!installModal) {
            return;
        }

        installModal.hidden = false;
        installModal.setAttribute('aria-hidden', 'false');
    }

    function closeInstructions() {
        if (!installModal) {
            return;
        }

        installModal.hidden = true;
        installModal.setAttribute('aria-hidden', 'true');
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallButton();
    });

    window.addEventListener('appinstalled', function () {
        hideInstallButton();
        closeInstructions();
        dismissInstall();
    });

    document.addEventListener('DOMContentLoaded', function () {
        installShell = document.querySelector('[data-tp-install-shell]');
        installButton = document.querySelector('[data-tp-install-app]');
        installModal = document.getElementById('tp-install-instructions');
        installClose = document.querySelector('[data-tp-install-close]');
        installDismiss = document.querySelector('[data-tp-install-dismiss]');

        if (installButton) {
            installButton.addEventListener('click', function () {
                if (deferredInstallPrompt) {
                    deferredInstallPrompt.prompt();
                    deferredInstallPrompt.userChoice.finally(function () {
                        deferredInstallPrompt = null;
                        hideInstallButton();
                    });
                    return;
                }

                if (isiOS()) {
                    openInstructions();
                }
            });
        }

        if (installClose) {
            installClose.addEventListener('click', closeInstructions);
        }

        if (installDismiss) {
            installDismiss.addEventListener('click', function () {
                dismissInstall();
                hideInstallButton();
                closeInstructions();
            });
        }

        if (installModal) {
            installModal.addEventListener('click', function (event) {
                if (event.target === installModal) {
                    closeInstructions();
                }
            });
        }

        if (deferredInstallPrompt || isiOS()) {
            showInstallButton();
        }

        document.querySelectorAll('[data-tp-notification-read]').forEach(function (notification) {
            var markAsRead = function () {
                var notificationId = notification.getAttribute('data-tp-notification-read');
                var nonce = notification.getAttribute('data-tp-notification-nonce');

                if (!notificationId || !nonce || notification.dataset.tpNotificationMarked === '1') {
                    return;
                }

                notification.dataset.tpNotificationMarked = '1';
                notification.classList.remove('is-unread');
                notification.classList.add('is-read');
                decrementNotificationBadge();

                if (!window.TPPortalPWA || !window.TPPortalPWA.adminPostUrl || !window.fetch) {
                    return;
                }

                var data = new FormData();
                data.append('action', 'tp_portal_notificacion_vista');
                data.append('notificacion_id', notificationId);
                data.append('_wpnonce', nonce);

                window.fetch(window.TPPortalPWA.adminPostUrl, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin'
                }).catch(function () {});
            };

            notification.addEventListener('mouseenter', markAsRead);
            notification.addEventListener('focus', markAsRead);
            notification.addEventListener('touchstart', markAsRead, { passive: true });
        });
    });

    function decrementNotificationBadge() {
        var badge = document.querySelector('.tp-notification-menu summary em');

        if (!badge) {
            return;
        }

        var count = parseInt(badge.textContent, 10);

        if (!count || count <= 1) {
            badge.remove();
            return;
        }

        badge.textContent = String(count - 1);
    }

    if (!('serviceWorker' in navigator) || !window.TPPortalPWA) {
        return;
    }

    window.addEventListener('load', function () {
        navigator.serviceWorker.register(
            window.TPPortalPWA.serviceWorkerUrl,
            { scope: window.TPPortalPWA.scope }
        ).catch(function () {});
    });
}());
