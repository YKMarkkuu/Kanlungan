(function () {
    'use strict';

    /* ---------- Create post: enable Post, show who you will appear as ---------- */

    var postForm = document.getElementById('post-form');

    if (postForm) {
        var body = document.getElementById('body');
        var postBtn = document.getElementById('post-btn');
        var anon = document.getElementById('anonymous');
        var anonHint = document.getElementById('anon-hint');
        var alias = postForm.getAttribute('data-alias');

        var syncPostBtn = function () {
            postBtn.disabled = body.value.trim().length < 3;
        };

        var syncAnon = function () {
            anonHint.textContent = anon.checked
                ? 'You\u2019ll appear under a random alias (like ' + alias + ') in this thread'
                : 'Your display name will be shown on this post';
        };

        body.addEventListener('input', syncPostBtn);
        anon.addEventListener('change', syncAnon);
        syncPostBtn();
        syncAnon();
    }

    /* ---------- Post page: anonymous toggle + reply-to ---------- */

    var commentForm = document.getElementById('comment-form');

    if (commentForm) {
        var toggle = document.getElementById('anon-toggle');
        var anonField = document.getElementById('anon-field');
        var hint = document.getElementById('comment-hint');
        var commentAlias = commentForm.getAttribute('data-alias');
        var parentId = document.getElementById('parent-id');
        var replyNote = document.getElementById('reply-note');
        var replyName = document.getElementById('reply-name');
        var replyCancel = document.getElementById('reply-cancel');
        var commentBody = document.getElementById('comment-body');

        toggle.addEventListener('click', function () {
            var on = anonField.value !== '1';

            anonField.value = on ? '1' : '0';
            toggle.classList.toggle('is-on', on);
            toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
            hint.textContent = on ? 'Commenting as ' + commentAlias : 'Commenting with your name';
        });

        var clearReply = function () {
            parentId.value = '';
            replyNote.hidden = true;
            commentBody.placeholder = 'Write a comment\u2026';
        };

        document.querySelectorAll('[data-reply-to]').forEach(function (button) {
            button.addEventListener('click', function () {
                parentId.value = button.getAttribute('data-reply-to');
                replyName.textContent = 'Replying to ' + button.getAttribute('data-reply-name');
                replyNote.hidden = false;
                commentBody.placeholder = 'Write a reply\u2026';
                commentBody.focus();
            });
        });

        replyCancel.addEventListener('click', clearReply);
    }

    /* ---------- Report bottom sheet ---------- */

    var sheet = document.getElementById('report-sheet');

    var photoInput = document.getElementById('photo');
    var photoName = document.getElementById('photo-name');

    if (photoInput && photoName) {
        photoInput.addEventListener('change', function () {
            photoName.textContent = photoInput.files.length ? photoInput.files[0].name : 'Add photo';
        });
    }

    if (sheet) {
        var reportForm = document.getElementById('report-form');
        var reportTitle = document.getElementById('report-title');

        var closeSheet = function () {
            sheet.hidden = true;
            reportForm.reset();
        };

        document.querySelectorAll('[data-report-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                reportForm.action = button.getAttribute('data-report-action');
                reportTitle.textContent = 'Report this ' + button.getAttribute('data-report-kind');
                sheet.hidden = false;
            });
        });

        sheet.querySelectorAll('[data-report-close]').forEach(function (el) {
            el.addEventListener('click', closeSheet);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !sheet.hidden) {
                closeSheet();
            }
        });
    }
})();
