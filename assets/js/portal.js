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

        document.querySelectorAll('[data-tp-notification-dismiss]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                var notificationId = button.getAttribute('data-tp-notification-dismiss');
                var nonce = button.getAttribute('data-tp-notification-nonce');
                var item = button.closest('[data-tp-notification-item]');

                if (!notificationId || !nonce || button.dataset.tpNotificationMarked === '1') {
                    return;
                }

                button.dataset.tpNotificationMarked = '1';
                button.disabled = true;

                if (!window.TPPortalPWA || !window.TPPortalPWA.adminPostUrl || !window.fetch) {
                    if (item) {
                        item.remove();
                        decrementNotificationBadge();
                        ensureNotificationEmptyState();
                    }
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
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('notification-not-marked');
                    }

                    if (item) {
                        item.remove();
                        decrementNotificationBadge();
                        ensureNotificationEmptyState();
                    }
                }).catch(function () {
                    button.dataset.tpNotificationMarked = '0';
                    button.disabled = false;
                });
            });
        });

        bindPortalNotificationActions();
    });

    function bindPortalNotificationActions() {
        document.querySelectorAll('[data-tp-portal-notification-mark], [data-tp-portal-notification-delete]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!window.TPPortalPWA || !window.TPPortalPWA.adminPostUrl || !window.fetch || form.dataset.tpNotificationBusy === '1') {
                    return;
                }

                event.preventDefault();

                var card = form.closest('[data-tp-portal-notification-card]');
                var notificationId = getFormNotificationId(form);
                var isDelete = form.hasAttribute('data-tp-portal-notification-delete');
                var wasUnread = card ? card.classList.contains('is-unread') : false;
                var submit = form.querySelector('button[type="submit"]');

                if (!card || !notificationId) {
                    form.submit();
                    return;
                }

                form.dataset.tpNotificationBusy = '1';

                if (submit) {
                    submit.disabled = true;
                    submit.setAttribute('aria-busy', 'true');
                }

                var requestUrl = form.getAttribute('action') || window.TPPortalPWA.adminPostUrl;

                window.fetch(requestUrl, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('notification-action-failed');
                    }

                    return response.json().catch(function () {
                        return { success: true };
                    });
                }).then(function (payload) {
                    if (payload && payload.success === false) {
                        throw new Error('notification-action-failed');
                    }

                    removeDropdownNotification(notificationId);

                    if (wasUnread) {
                        decrementNotificationBadge();
                    }

                    if (isDelete) {
                        removePortalNotificationCard(card);
                        return;
                    }

                    markPortalNotificationCardRead(card, form);
                }).catch(function () {
                    form.dataset.tpNotificationBusy = '0';

                    if (submit) {
                        submit.disabled = false;
                        submit.removeAttribute('aria-busy');
                    }
                });
            });
        });
    }

    function getFormNotificationId(form) {
        var field = form.querySelector('input[name="notificacion_id"]');
        return field ? field.value : '';
    }

    function markPortalNotificationCardRead(card, form) {
        var state = card.querySelector('[data-tp-portal-notification-state]');

        card.classList.remove('is-unread');
        card.classList.add('is-read');

        if (state) {
            state.classList.remove('tp-pill-active');
            state.textContent = 'Vista';
        }

        form.remove();
    }

    function removePortalNotificationCard(card) {
        card.classList.add('is-removing');

        window.setTimeout(function () {
            var list = card.closest('.tp-portal-notifications');

            card.remove();
            ensurePortalNotificationsEmptyState(list);
        }, 180);
    }

    function removeDropdownNotification(notificationId) {
        var item = document.querySelector('[data-tp-notification-item="' + notificationId + '"]');

        if (item) {
            item.remove();
        }

        ensureNotificationEmptyState();
    }

    function ensurePortalNotificationsEmptyState(list) {
        if (!list || list.querySelector('[data-tp-portal-notification-card]') || list.querySelector('.tp-empty-state')) {
            return;
        }

        var empty = document.createElement('p');
        empty.className = 'tp-empty-state';
        empty.textContent = 'No tienes notificaciones por ahora.';
        list.appendChild(empty);
    }

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

    function ensureNotificationEmptyState() {
        var dropdown = document.querySelector('.tp-notification-dropdown');

        if (!dropdown || dropdown.querySelector('[data-tp-notification-item]') || dropdown.querySelector('.tp-notification-empty')) {
            return;
        }

        var viewAll = dropdown.querySelector('.tp-notification-view-all');
        var empty = document.createElement('p');
        empty.className = 'tp-notification-empty';
        empty.textContent = 'Sin notificaciones.';

        if (viewAll) {
            dropdown.insertBefore(empty, viewAll);
            return;
        }

        dropdown.appendChild(empty);
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
