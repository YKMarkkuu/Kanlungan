(function () {
    'use strict';

    var EMAIL_DOMAIN = '@student.fatima.edu.ph';
    var CAMPUS_MARKER = 'pam';
    var CODE_LENGTH = 6;
    var RESEND_SECONDS = 60;
    var STEP_TITLES = ['Details', 'Code', 'Rules'];

    var root = document.getElementById('register');
    if (!root) {
        return;
    }

    var panels = root.querySelectorAll('[data-step-panel]');
    var stepItems = root.querySelectorAll('.steps__item');
    var stepLabel = document.getElementById('step-label');

    var detailsForm = document.getElementById('details-form');
    var studentIdInput = document.getElementById('student_id');
    var emailInput = document.getElementById('email');
    var emailHint = document.getElementById('email-hint');

    var codeInputs = Array.prototype.slice.call(root.querySelectorAll('.code-input'));
    var codeEmailLine = document.getElementById('code-email-line');
    var codeEmail = document.getElementById('code-email');
    var resendBtn = document.getElementById('resend-btn');
    var changeEmailBtn = document.getElementById('change-email-btn');

    var rulesForm = document.getElementById('rules-form');
    var acceptCheckbox = document.getElementById('accept');
    var displayNameInput = document.getElementById('display_name');
    var createBtn = document.getElementById('create-btn');

    var countdownTimer = null;

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

    studentIdInput.addEventListener('input', function () {
        var digits = studentIdInput.value.replace(/\D/g, '').slice(0, 11);
        if (studentIdInput.value !== digits) {
            studentIdInput.value = digits;
        }
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
        var value = emailInput.value.trim().toLowerCase();

        if (value === '') {
            setEmailHint(null, '');
            return;
        }

        if (value.slice(-EMAIL_DOMAIN.length) !== EMAIL_DOMAIN) {
            setEmailHint('error', 'Must be an @student.fatima.edu.ph email.');
            return;
        }

        var localPart = value.slice(0, value.length - EMAIL_DOMAIN.length);

        if (localPart.indexOf(CAMPUS_MARKER) === -1) {
            setEmailHint('error', 'Email must be from the Pampanga campus.');
            return;
        }

        setEmailHint('ok', 'Pampanga campus verified.');
    }

    emailInput.addEventListener('input', updateEmailHint);

    detailsForm.addEventListener('submit', function (event) {
        event.preventDefault();
        // Validation hints above are visual only. The backend enforces the rules later.
        sendCode({
            studentId: studentIdInput.value,
            email: emailInput.value.trim()
        });
    });

    /* Placeholder: replace with a fetch() to the send-code endpoint when auth lands. */
    function sendCode(payload) {
        console.log('[register] sendCode (placeholder)', payload);

        var email = payload.email;
        codeEmail.textContent = email;
        codeEmailLine.hidden = email === '';

        clearCode();
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

    function maybeSubmitCode() {
        var code = getCode();
        if (code.length === CODE_LENGTH) {
            verifyCode(code);
        }
    }

    /* Placeholder: replace with a fetch() to the verify-code endpoint when auth lands. */
    function verifyCode(code) {
        console.log('[register] verifyCode (placeholder)', code);
        goToStep(3);
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

    function startCountdown() {
        var remaining = RESEND_SECONDS;

        stopCountdown();
        resendBtn.disabled = true;
        renderResend(remaining);

        countdownTimer = setInterval(function () {
            remaining -= 1;

            if (remaining <= 0) {
                stopCountdown();
                resendBtn.disabled = false;
                resendBtn.textContent = 'Resend code';
            } else {
                renderResend(remaining);
            }
        }, 1000);
    }

    resendBtn.addEventListener('click', function () {
        if (resendBtn.disabled) {
            return;
        }

        sendCode({
            studentId: studentIdInput.value,
            email: emailInput.value.trim()
        });
    });

    changeEmailBtn.addEventListener('click', function () {
        clearCode();
        goToStep(1);
    });

    /* ---------- Step 3: rules ---------- */

    acceptCheckbox.addEventListener('change', function () {
        createBtn.disabled = !acceptCheckbox.checked;
    });

    rulesForm.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!acceptCheckbox.checked) {
            return;
        }

        createAccount({
            studentId: studentIdInput.value,
            email: emailInput.value.trim(),
            campus: 'pampanga',
            displayName: displayNameInput.value.trim()
        });
    });

    /* Placeholder: replace with a fetch() to the register endpoint when auth lands. */
    function createAccount(payload) {
        console.log('[register] createAccount (placeholder)', payload);
    }
})();
