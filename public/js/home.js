(function () {
    'use strict';

    var MSG_NETWORK = 'Could not reach server.';
    var MSG_GENERIC = 'Something went wrong. Try again.';

    var button = document.getElementById('logout-btn');
    var banner = document.getElementById('logout-banner');

    if (!button) {
        return;
    }

    function showError(message) {
        banner.textContent = message;
        banner.hidden = false;
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    button.addEventListener('click', function () {
        var headers = { 'Accept': 'application/json' };
        var token = csrfToken();

        if (token) {
            headers['X-CSRF-TOKEN'] = token;
        }

        banner.hidden = true;
        button.disabled = true;

        fetch('/auth/logout', {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers
        }).then(function (response) {
            // 401 means the session already expired: the user is logged out
            // either way, so treat it the same as success.
            if (response.ok || response.status === 401) {
                window.location.href = '/';
                return;
            }

            button.disabled = false;
            showError(MSG_GENERIC);
        }, function () {
            button.disabled = false;
            showError(MSG_NETWORK);
        });
    });
})();
