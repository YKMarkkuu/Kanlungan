// Helpers duplicated from register.js — extract to a shared module if a third auth script is added.
(function () {
    'use strict';

    var CODE_LENGTH = 6;
    var RESEND_SECONDS = 60;

    // Same pattern the server uses (SendCodeRequest, VerifyCodeRequest, LoginRequest).
    // If the server rule changes, change this too.
    var EMAIL_PATTERN = /^[a-z]+[0-9]{4}pam@student\.fatima\.edu\.ph$/i;

    var URL_SEND_CODE = '/auth/send-code';
    var URL_LOGIN = '/auth/login';
    var URL_REGISTER_PAGE = '/register';
    var URL_HOME = '/home';

    var MSG_EMAIL_INVALID = 'Enter a valid OLFU Pampanga student email.';
    var MSG_RATE_LIMITED = 'Too many attempts. Try again later.';
    var MSG_NETWORK = 'Could not reach server.';
    var MSG_GENERIC = 'Something went wrong. Try again.';
    var MSG_NOT_REGISTERED = 'This email is not registered.';

    // Statuses the server returns with HTTP 200 when the code itself is the problem.
    var CODE_MESSAGES = {
        mismatch: 'Incorrect code. Try again.',
        locked_out: 'Too many attempts. Request a new code.',
        expired_or_missing: 'Code expired. Request a new one.'
    };

    var form = document.getElementById('login-form');
    if (!form) {
        return;
    }

    var emailInput = document.getElementById('email');
    var emailHint = document.getElementById('email-hint');
    var banner = document.getElementById('login-banner');
    var sendBtn = document.getElementById('send-btn');
    var codeBlock = document.getElementById('code-block');
    var codeInputs = Array.prototype.slice.call(form.querySelectorAll('.code-input'));
    var resendBtn = document.getElementById('resend-btn');
    var changeEmailBtn = document.getElementById('change-email-btn');

    var countdownTimer = null;

    // True while any request is in flight; blocks double submits.
    var busy = false;

    // The email the current code was sent to. Login uses this, not the live
    // input value (the field is readonly while the code block is open anyway).
    var pendingEmail = '';

    /* ---------- Messages ---------- */

    // Fills a banner / inline error element. Empty message hides it.
    // Optional link: { href, text } is appended after the message.
    function showMessage(el, message, link) {
        el.textContent = '';

        if (!message) {
            el.hidden = true;
            return;
        }

        el.appendChild(document.createTextNode(message));

        if (link) {
            var anchor = document.createElement('a');
            anchor.href = link.href;
            anchor.textContent = link.text;
            el.appendChild(document.createTextNode(' '));
            el.appendChild(anchor);
        }

        el.hidden = false;
    }

    function clearMessage(el) {
        showMessage(el, '');
    }

    // First message for the given fields from a Laravel 422 body, else its
    // top-level message, else a generic fallback.
    function firstError(data, fields) {
        var errors = data && data.errors;
        var i;
        var list;

        if (errors) {
            for (i = 0; i < fields.length; i++) {
                list = errors[fields[i]];
                if (list && list.length) {
                    return list[0];
                }
            }
        }

        return (data && data.message) || MSG_GENERIC;
    }

    function isCodeFailure(status) {
        return Object.prototype.hasOwnProperty.call(CODE_MESSAGES, status);
    }

    /* ---------- Network ---------- */

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    // Resolves to { status, data } or { networkError: true }. Never rejects.
    function postJson(url, body) {
        var headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        var token = csrfToken();

        if (token) {
            headers['X-CSRF-TOKEN'] = token;
        }

        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json().then(function (data) {
                return data;
            }, function () {
                return {};
            }).then(function (data) {
                return { status: response.status, data: data || {} };
            });
        }, function () {
            return { networkError: true };
        });
    }

    /* ---------- Loading states ---------- */

    function setBusy(button, label) {
        button.dataset.label = button.textContent;
        button.textContent = label;
        button.disabled = true;
    }

    function clearBusy(button) {
        if (button.dataset.label) {
            button.textContent = button.dataset.label;
        }
        button.disabled = false;
    }

    /* ---------- Email ---------- */

    function normalizedEmail() {
        return emailInput.value.trim().toLowerCase();
    }

    function setEmailHint(kind, message) {
        if (!message) {
            emailHint.hidden = true;
            emailHint.textContent = '';
            emailHint.className = 'field__hint';
            return;
        }

        emailHint.hidden = false;
        emailHint.textContent = message;
        emailHint.className = kind === 'ok' ? 'field__success' : 'field__error';
    }

    function updateEmailHint() {
        var value = normalizedEmail();

        if (value === '') {
            setEmailHint(null, '');
            return;
        }

        if (!EMAIL_PATTERN.test(value)) {
            setEmailHint('error', MSG_EMAIL_INVALID);
            return;
        }

        setEmailHint('ok', 'Pampanga campus verified.');
    }

    emailInput.addEventListener('input', function () {
        updateEmailHint();
        clearMessage(banner);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        // Once the code block is open, Enter must not re-send the code.
        if (!codeBlock.hidden) {
            return;
        }

        var email = normalizedEmail();

        // Checked client-side too: the otp-send throttle counts invalid requests.
        if (!EMAIL_PATTERN.test(email)) {
            setEmailHint('error', MSG_EMAIL_INVALID);
            emailInput.focus();
            return;
        }

        clearMessage(banner);
        sendCode(email, 'details');
    });

    /* ---------- Send code ---------- */

    // source: 'details' (Send code button) or 'resend' (Resend button).
    // Decides which button shows the loading state and where a 422 appears.
    function sendCode(email, source) {
        if (busy) {
            return;
        }

        var button = source === 'resend' ? resendBtn : sendBtn;

        busy = true;
        clearMessage(banner);
        setBusy(button, 'Sending\u2026');

        postJson(URL_SEND_CODE, { email: email }).then(function (res) {
            busy = false;
            clearBusy(button);

            if (res.networkError) {
                showMessage(banner, MSG_NETWORK);
                return;
            }

            if (res.status === 200 && res.data.status === 'sent') {
                onCodeSent(email);
                return;
            }

            if (res.status === 422) {
                var message = firstError(res.data, ['email']);

                if (source === 'details') {
                    setEmailHint('error', message);
                } else {
                    showMessage(banner, message);
                }
                return;
            }

            if (res.status === 429) {
                showMessage(banner, MSG_RATE_LIMITED);
                return;
            }

            showMessage(banner, MSG_GENERIC);
        });
    }

    // Opens (or refreshes, on resend) the code block.
    function onCodeSent(email) {
        pendingEmail = email;

        resetCodeInputs();
        emailInput.readOnly = true;
        setEmailHint(null, '');

        // .btn--block sets display:flex, which beats the hidden attribute,
        // so the Send button is hidden through its inline style instead.
        sendBtn.style.display = 'none';
        codeBlock.hidden = false;

        codeInputs[0].focus();
        startCountdown();
    }

    /* ---------- Code entry ---------- */

    function getCode() {
        return codeInputs.map(function (input) {
            return input.value;
        }).join('');
    }

    function clearCode() {
        codeInputs.forEach(function (input) {
            input.value = '';
        });
    }

    function setCodeInputsDisabled(disabled) {
        codeInputs.forEach(function (input) {
            input.disabled = disabled;
        });
    }

    // Clean code state: no digits, no banner, inputs usable.
    function resetCodeInputs() {
        clearCode();
        clearMessage(banner);
        setCodeInputsDisabled(false);
    }

    function maybeSubmitCode() {
        var code = getCode();
        if (code.length === CODE_LENGTH && !busy) {
            login(code);
        }
    }

    // Shows the banner for a code failure status. For locked_out and
    // expired_or_missing the code can't be retried, so the inputs are disabled
    // and Resend is enabled immediately.
    function applyCodeFailure(status) {
        clearCode();
        showMessage(banner, CODE_MESSAGES[status]);

        if (status === 'locked_out' || status === 'expired_or_missing') {
            setCodeInputsDisabled(true);
            enableResend();
        } else {
            codeInputs[0].focus();
        }
    }

    // The code was valid but no account exists. Retrying the same code is
    // pointless, so the inputs are disabled; Change email stays available.
    function applyNotRegistered() {
        clearCode();
        showMessage(banner, MSG_NOT_REGISTERED, {
            href: URL_REGISTER_PAGE,
            text: 'Sign up instead.'
        });
        setCodeInputsDisabled(true);
    }

    // There is no verify button (it submits when the 6th digit lands), so the
    // inputs are disabled while the request is in flight instead.
    function login(code) {
        if (busy) {
            return;
        }

        busy = true;
        clearMessage(banner);
        setCodeInputsDisabled(true);

        postJson(URL_LOGIN, { email: pendingEmail, code: code }).then(function (res) {
            busy = false;

            // Success: leave the inputs disabled while the browser navigates.
            if (res.status === 200 && res.data.status === 'authenticated') {
                window.location.href = res.data.redirect || URL_HOME;
                return;
            }

            setCodeInputsDisabled(false);

            if (res.networkError) {
                clearCode();
                showMessage(banner, MSG_NETWORK);
                codeInputs[0].focus();
                return;
            }

            if (res.status === 200) {
                if (res.data.status === 'not_registered') {
                    applyNotRegistered();
                    return;
                }

                if (isCodeFailure(res.data.status)) {
                    applyCodeFailure(res.data.status);
                    return;
                }
            }

            clearCode();

            if (res.status === 422) {
                showMessage(banner, firstError(res.data, ['code', 'email']));
            } else if (res.status === 429) {
                showMessage(banner, MSG_RATE_LIMITED);
            } else {
                showMessage(banner, MSG_GENERIC);
            }

            codeInputs[0].focus();
        });
    }

    function fillFrom(start, digits) {
        var i;
        for (i = 0; i < digits.length && start + i < CODE_LENGTH; i++) {
            codeInputs[start + i].value = digits.charAt(i);
        }

        var next = Math.min(start + digits.length, CODE_LENGTH - 1);
        codeInputs[next].focus();

        maybeSubmitCode();
    }

    codeInputs.forEach(function (input, index) {
        input.addEventListener('focus', function () {
            input.select();
        });

        input.addEventListener('input', function () {
            var digits = input.value.replace(/\D/g, '');
            input.value = '';

            if (digits === '') {
                return;
            }

            fillFrom(index, digits);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Backspace') {
                if (input.value === '' && index > 0) {
                    event.preventDefault();
                    codeInputs[index - 1].value = '';
                    codeInputs[index - 1].focus();
                }
            } else if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                codeInputs[index - 1].focus();
            } else if (event.key === 'ArrowRight' && index < CODE_LENGTH - 1) {
                event.preventDefault();
                codeInputs[index + 1].focus();
            }
        });

        input.addEventListener('paste', function (event) {
            event.preventDefault();

            var clipboard = event.clipboardData || window.clipboardData;
            var text = clipboard ? clipboard.getData('text') : '';
            var digits = text.replace(/\D/g, '').slice(0, CODE_LENGTH);

            if (digits === '') {
                return;
            }

            clearCode();
            fillFrom(0, digits);
        });
    });

    /* ---------- Resend countdown ---------- */

    function renderResend(seconds) {
        resendBtn.textContent = 'Resend code (' + seconds + 's)';
    }

    function stopCountdown() {
        if (countdownTimer !== null) {
            clearInterval(countdownTimer);
            countdownTimer = null;
        }
    }

    function enableResend() {
        stopCountdown();
        resendBtn.disabled = false;
        resendBtn.textContent = 'Resend code';
    }

    function startCountdown() {
        var remaining = RESEND_SECONDS;

        stopCountdown();
        resendBtn.disabled = true;
        renderResend(remaining);

        countdownTimer = setInterval(function () {
            remaining -= 1;

            if (remaining <= 0) {
                enableResend();
            } else {
                renderResend(remaining);
            }
        }, 1000);
    }

    resendBtn.addEventListener('click', function () {
        if (resendBtn.disabled) {
            return;
        }

        sendCode(pendingEmail, 'resend');
    });

    /* ---------- Change email ---------- */

    // Fully resets the panel back to the email-only state.
    changeEmailBtn.addEventListener('click', function () {
        stopCountdown();
        pendingEmail = '';

        resetCodeInputs();
        codeBlock.hidden = true;
        sendBtn.style.display = '';

        emailInput.readOnly = false;
        updateEmailHint();
        emailInput.focus();
    });
})();
