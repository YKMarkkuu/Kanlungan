(function () {
    'use strict';

    var card = document.getElementById('auth-card');
    var openButton = document.getElementById('auth-open');
    var emailInput = document.getElementById('email');

    if (!card || !openButton || !emailInput) {
        return;
    }

    function openCard(focusEmail) {
        if (card.classList.contains('is-open')) {
            return;
        }

        card.classList.add('is-open');
        openButton.setAttribute('aria-expanded', 'true');

        if (focusEmail) {
            emailInput.focus({ preventScroll: true });
        }
    }

    openButton.addEventListener('click', function () {
        openCard(true);
    });

    function openFromHash() {
        if (window.location.hash === '#signin') {
            openCard(false);
        }
    }

    window.addEventListener('hashchange', openFromHash);
    openFromHash();
})();
