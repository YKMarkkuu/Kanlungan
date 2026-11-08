// Helpers are duplicated in login.js — extract to a shared module if a third auth script is added.
(function () {
    'use strict';

    var CODE_LENGTH = 6;
    var RESEND_SECONDS = 60;
    var STEP_TITLES = ['Details', 'Code', 'Rules'];

    // Same pattern the server uses (SendCodeRequest, VerifyCodeRequest, RegisterRequest).
    // If the server rule changes, change this too.
    var EMAIL_PATTERN = /^[a-z]+[0-9]{4}pam@student\.fatima\.edu\.ph$/i;
    var STUDENT_ID_PATTERN = /^[0-9]{11}$/;

    var URL_SEND_CODE = '/auth/send-code';
    var URL_VERIFY_CODE = '/auth/verify-code';
    var URL_REGISTER = '/auth/register';

    var MSG_EMAIL_INVALID = 'Enter a valid OLFU Pampanga student email.';
    var MSG_STUDENT_ID_INVALID = 'Student ID must be 11 digits.';
    var MSG_RATE_LIMITED = 'Too many attempts. Try again later.';
    var MSG_NETWORK = 'Could not reach server.';
    var MSG_GENERIC = 'Something went wrong. Try again.';

    // Statuses the server returns with HTTP 200 when the code itself is the problem.
    var CODE_MESSAGES = {
        mismatch: 'Incorrect code. Try again.',
        locked_out: 'Too many attempts. Request a new code.',
        expired_or_missing: 'Code expired. Request a new one.'
    };

    var root = document.getElementById('register');
    if (!root) {
        return;
    }

    var panels = root.querySelectorAll('[data-step-panel]');
    var stepItems = root.querySelectorAll('.steps__item');
    var stepLabel = document.getElementById('step-label');

    var detailsForm = document.getElementById('details-form');
    var detailsSubmitBtn = detailsForm.querySelector('button[type="submit"]');
    var studentIdInput = document.getElementById('student_id');
    var studentIdError = document.getElementById('student-id-error');
    var emailInput = document.getElementById('email');
    var emailHint = document.getElementById('email-hint');

    var codeInputs = Array.prototype.slice.call(root.querySelectorAll('.code-input'));
    var codeBanner = document.getElementById('code-banner');
    var codeEmailLine = document.getElementById('code-email-line');
    var codeEmail = document.getElementById('code-email');
    var resendBtn = document.getElementById('resend-btn');
    var changeEmailBtn = document.getElementById('change-email-btn');

    var rulesForm = document.getElementById('rules-form');
    var rulesBanner = document.getElementById('rules-banner');
    var acceptCheckbox = document.getElementById('accept');
    var displayNameInput = document.getElementById('display_name');
    var displayNameError = document.getElementById('display-name-error');
    var createBtn = document.getElementById('create-btn');

    var countdownTimer = null;

    // True while any request is in flight; blocks double submits.
    var busy = false;

    // The email the current code was sent to. Verify and register use this,
    // not the live input value.
    var pendingEmail = '';

    // The code the server accepted in Step 2. /auth/verify-code does not consume
    // it, and /auth/register re-verifies it, so it is held here until register.
    // Must be cleared whenever the user is sent back to Step 1 or Step 2.
    var verifiedCode = null;

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

    /* ---------- Step navigation ---------- */

    function goToStep(step) {
        var i;

        for (i = 0; i < panels.length; i++) {
            panels[i].hidden = Number(panels[i].getAttribute('data-step-panel')) !== step;
        }

        for (i = 0; i < stepItems.length; i++) {
            stepItems[i].classList.toggle('is-done', i + 1 < step);
            stepItems[i].classList.toggle('is-active', i + 1 === step);
        }

        stepLabel.textContent = 'Step ' + step + ' of 3 · ' + STEP_TITLES[step - 1];

        if (step !== 2) {
            stopCountdown();
        }

        if (step === 1) {
            emailInput.focus();
        } else if (step === 2) {
            codeInputs[0].focus();
        } else if (step === 3) {
            acceptCheckbox.focus();
        }
    }

    /* ---------- Step 1: details ---------- */

    function normalizedEmail() {
        return emailInput.value.trim().toLowerCase();
    }

    studentIdInput.addEventListener('input', function () {
        var digits = studentIdInput.value.replace(/\D/g, '').slice(0, 11);
        if (studentIdInput.value !== digits) {
            studentIdInput.value = digits;
        }
        clearMessage(studentIdError);
    });

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

    emailInput.addEventListener('input', updateEmailHint);

    detailsForm.addEventListener('submit', function (event) {
        event.preventDefault();

        var email = normalizedEmail();
        var studentIdOk = STUDENT_ID_PATTERN.test(studentIdInput.value);
        var emailOk = EMAIL_PATTERN.test(email);

        // Checked client-side too: the otp-send throttle counts invalid requests.
        clearMessage(studentIdError);

        if (!studentIdOk) {
            showMessage(studentIdError, MSG_STUDENT_ID_INVALID);
        }

        if (!emailOk) {
            setEmailHint('error', MSG_EMAIL_INVALID);
        }

        if (!studentIdOk) {
            studentIdInput.focus();
            return;
        }

        if (!emailOk) {
            emailInput.focus();
            return;
        }

        sendCode({ email: email }, 'details');
    });

    // source: 'details' (Step 1 submit) or 'resend' (Step 2 button).
    // Decides which button shows the loading state and where errors appear.
    function sendCode(payload, source) {
        if (busy) {
            return;
        }

        var button = source === 'resend' ? resendBtn : detailsSubmitBtn;

        function report(message) {
            if (source === 'resend') {
                showMessage(codeBanner, message);
            } else {
                setEmailHint('error', message);
            }
        }

        busy = true;
        setBusy(button, 'Sending\u2026');

        postJson(URL_SEND_CODE, { email: payload.email }).then(function (res) {
            busy = false;
            clearBusy(button);

            if (res.networkError) {
                report(MSG_NETWORK);
                return;
            }

            if (res.status === 200 && res.data.status === 'sent') {
                onCodeSent(payload.email);
                return;
            }

            if (res.status === 422) {
                report(firstError(res.data, ['email']));
                return;
            }

            if (res.status === 429) {
                report(MSG_RATE_LIMITED);
                return;
            }

            if (res.data && typeof res.data.message === 'string') {
                report(res.data.message);
                return;
            }

            report(MSG_GENERIC);
        });
    }

    function onCodeSent(email) {
        resetCodeStep();
        pendingEmail = email;
        codeEmail.textContent = email;
        codeEmailLine.hidden = email === '';

        goToStep(2);
        startCountdown();
    }

    /* ---------- Step 2: code ---------- */

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

    // Returns Step 2 to a clean state: no code, no stored code, no banner,
    // inputs usable.
    //
    // Invariant: pendingEmail is non-empty iff a code has been sent and not yet
    // invalidated. A full reset invalidates, so it clears pendingEmail too.
    // keepEmail = true is only for returning to Step 2 while that code is still
    // pending (register rejected the code; the user retries or resends).
    function resetCodeStep(keepEmail) {
        if (!keepEmail) {
            pendingEmail = '';
        }

        verifiedCode = null;
        clearCode();
        clearMessage(codeBanner);
        setCodeInputsDisabled(false);
    }

    function maybeSubmitCode() {
        var code = getCode();
        if (code.length === CODE_LENGTH && !busy) {
            verifyCode(code);
        }
    }

    // Shows the banner for a code failure status. For locked_out and
    // expired_or_missing the code can't be retried, so the inputs are disabled
    // and Resend is enabled immediately.
    function applyCodeFailure(status) {
        clearCode();
        showMessage(codeBanner, CODE_MESSAGES[status]);

        if (status === 'locked_out' || status === 'expired_or_missing') {
            setCodeInputsDisabled(true);
            enableResend();
        } else {
            codeInputs[0].focus();
        }
    }

    // Step 2 has no verify button (it submits when the 6th digit lands), so the
    // inputs are disabled while the request is in flight instead.
    function verifyCode(code) {
        if (busy) {
            return;
        }

        busy = true;
        clearMessage(codeBanner);
        setCodeInputsDisabled(true);

        postJson(URL_VERIFY_CODE, { email: pendingEmail, code: code }).then(function (res) {
            busy = false;
            setCodeInputsDisabled(false);

            if (res.networkError) {
                clearCode();
                showMessage(codeBanner, MSG_NETWORK);
                codeInputs[0].focus();
                return;
            }

            if (res.status === 200) {
                if (res.data.status === 'ok') {
                    verifiedCode = code;
                    goToStep(3);
                    return;
                }

                if (isCodeFailure(res.data.status)) {
                    applyCodeFailure(res.data.status);
                    return;
                }
            }

            clearCode();

            if (res.status === 422) {
                showMessage(codeBanner, firstError(res.data, ['code', 'email']));
            } else if (res.status === 429) {
                showMessage(codeBanner, MSG_RATE_LIMITED);
            } else {
                showMessage(codeBanner, MSG_GENERIC);
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

        sendCode({ email: pendingEmail }, 'resend');
    });

    changeEmailBtn.addEventListener('click', function () {
        resetCodeStep();
        goToStep(1);
    });

    /* ---------- Step 3: rules ---------- */

    acceptCheckbox.addEventListener('change', function () {
        createBtn.disabled = !acceptCheckbox.checked;
    });

    displayNameInput.addEventListener('input', function () {
        clearMessage(displayNameError);
    });

    rulesForm.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!acceptCheckbox.checked) {
            return;
        }

        createAccount({
            studentId: studentIdInput.value,
            displayName: displayNameInput.value.trim()
        });
    });

    // Sends the user back to Step 2 after register rejected the code.
    // The stored code is cleared first so a stale code can never be reused.
    function returnToCodeStep(status) {
        resetCodeStep(true);
        goToStep(2);
        enableResend();
        applyCodeFailure(status);
    }

    // Register rejected email / student_id format: those fields live in Step 1,
    // so go back there. The code state is reset so the user must re-verify.
    function returnToDetailsStep(errors) {
        resetCodeStep();
        goToStep(1);

        if (errors.email) {
            setEmailHint('error', errors.email[0]);
        }

        if (errors.student_id) {
            showMessage(studentIdError, errors.student_id[0]);
            studentIdInput.focus();
        }
    }

    function handleRegisterValidation(data) {
        var errors = data.errors || {};

        if (errors.email || errors.student_id) {
            returnToDetailsStep(errors);
            return;
        }

        if (errors.code) {
            resetCodeStep(true);
            goToStep(2);
            enableResend();
            showMessage(codeBanner, errors.code[0]);
            return;
        }

        if (errors.terms) {
            showMessage(rulesBanner, errors.terms[0]);
            acceptCheckbox.focus();
            return;
        }

        if (errors.display_name) {
            showMessage(displayNameError, errors.display_name[0]);
            displayNameInput.focus();
            return;
        }

        showMessage(rulesBanner, firstError(data, []));
    }

    function createAccount(payload) {
        if (busy) {
            return;
        }

        clearMessage(rulesBanner);
        clearMessage(displayNameError);

        if (verifiedCode === null) {
            returnToCodeStep('expired_or_missing');
            return;
        }

        busy = true;
        setBusy(createBtn, 'Creating account\u2026');

        // campus is not sent: the server hardcodes it.
        // A blank display_name goes as '' and the server normalizes it to null.
        postJson(URL_REGISTER, {
            email: pendingEmail,
            code: verifiedCode,
            student_id: payload.studentId,
            display_name: payload.displayName,
            terms: acceptCheckbox.checked
        }).then(function (res) {
            busy = false;

            // Success: the server has created the account and started the session.
            // Follow its redirect (/onboarding). The button stays in its busy state
            // while the browser navigates. The fallback only matters if an old
            // response without `redirect` ever arrives; the guest/auth middleware
            // sorts out the rest.
            if (res.status === 201 && res.data.status === 'registered') {
                window.location.href = res.data.redirect || '/onboarding';
                return;
            }

            clearBusy(createBtn);
            createBtn.disabled = !acceptCheckbox.checked;

            if (res.networkError) {
                showMessage(rulesBanner, MSG_NETWORK);
                return;
            }

            if (res.status === 200 && isCodeFailure(res.data.status)) {
                returnToCodeStep(res.data.status);
                return;
            }

            if (res.status === 422) {
                // SECURITY NOTE: email_taken confirms an email is registered to
                // anyone holding a valid OTP for it. That requires inbox access,
                // so it's accepted for now. Revisit in the security pass.
                if (res.data.status === 'email_taken') {
                    showMessage(rulesBanner, 'This email is already registered.', {
                        href: '/',
                        text: 'Sign in instead.'
                    });
                    return;
                }

                if (res.data.status === 'student_id_taken') {
                    showMessage(rulesBanner, 'This Student ID is already registered.');
                    return;
                }

                handleRegisterValidation(res.data);
                return;
            }

            if (res.status === 429) {
                showMessage(rulesBanner, MSG_RATE_LIMITED);
                return;
            }

            showMessage(rulesBanner, MSG_GENERIC);
        });
    }
})();
