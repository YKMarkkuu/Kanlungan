(function () {
    'use strict';

    /* ---------- Create post: enable Post, alias hint, auto-grow, photo preview ---------- */

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

        /* -- Auto-expanding textarea -- */

        var TEXTAREA_MAX_HEIGHT = 360; // px; past this the textarea scrolls instead of growing

        var autoGrow = function () {
            // Reset first so the box can also shrink when text is deleted.
            // min-height from CSS still applies, so it never gets smaller than the default.
            body.style.height = 'auto';

            var needed = body.scrollHeight;

            body.style.height = Math.min(needed, TEXTAREA_MAX_HEIGHT) + 'px';
            body.style.overflowY = needed > TEXTAREA_MAX_HEIGHT ? 'auto' : 'hidden';
        };

        body.addEventListener('input', function () {
            syncPostBtn();
            autoGrow();
        });

        // Width changes re-wrap the text, which changes the height it needs.
        window.addEventListener('resize', autoGrow);

        anon.addEventListener('change', syncAnon);

        /* -- Photo preview -- */

        var photoInput = document.getElementById('photo');
        var photoName = document.getElementById('photo-name');
        var previewBox = document.getElementById('image-preview-container');

        if (photoInput && previewBox) {
            // Mirrors the server rules in PostController::store().
            var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
            var MAX_BYTES = 4 * 1024 * 1024;
            var MAX_DIMENSION = 2000;
            var DEFAULT_LABEL = 'Add photo';

            var photoError = null;

            // Bumped on every change/remove so a slow FileReader from an
            // earlier pick can never overwrite a newer one.
            var previewToken = 0;

            var showPhotoError = function (message) {
                if (!photoError) {
                    photoError = document.createElement('p');
                    photoError.className = 'field__error';
                    photoError.setAttribute('aria-live', 'polite');
                    previewBox.parentNode.insertBefore(photoError, previewBox.nextSibling);
                }

                photoError.textContent = message;
                photoError.hidden = false;
            };

            var clearPhotoError = function () {
                if (photoError) {
                    photoError.textContent = '';
                    photoError.hidden = true;
                }
            };

            var emptyPreview = function () {
                while (previewBox.firstChild) {
                    previewBox.removeChild(previewBox.firstChild);
                }
            };

            // Clears the file input (so nothing is uploaded) and hides the preview.
            var resetPhoto = function () {
                previewToken += 1;
                photoInput.value = '';
                emptyPreview();
                previewBox.classList.add('hidden');
                clearPhotoError();

                if (photoName) {
                    photoName.textContent = DEFAULT_LABEL;
                }
            };

            // Inline SVG (no icon font, no emoji), same path as the "close" icon.
            var buildCloseIcon = function () {
                var ns = 'http://www.w3.org/2000/svg';
                var svg = document.createElementNS(ns, 'svg');
                var path = document.createElementNS(ns, 'path');

                svg.setAttribute('class', 'icon');
                svg.setAttribute('viewBox', '0 0 24 24');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('stroke-width', '1.75');
                svg.setAttribute('stroke-linecap', 'round');
                svg.setAttribute('stroke-linejoin', 'round');
                svg.setAttribute('aria-hidden', 'true');
                path.setAttribute('d', 'M6 6l12 12M18 6 6 18');
                svg.appendChild(path);

                return svg;
            };

            var showPreview = function (dataUrl, fileName, token) {
                var img = document.createElement('img');

                img.alt = 'Selected photo preview';

                img.onload = function () {
                    if (token !== previewToken) {
                        return;
                    }

                    // The server rejects anything over 2000px on a side; say so now.
                    if (img.naturalWidth > MAX_DIMENSION || img.naturalHeight > MAX_DIMENSION) {
                        resetPhoto();
                        showPhotoError('That photo is larger than ' + MAX_DIMENSION + ' \u00d7 ' + MAX_DIMENSION + ' pixels. Choose a smaller one.');
                        return;
                    }

                    var remove = document.createElement('button');

                    remove.type = 'button';
                    remove.className = 'cm-compose__preview-remove';
                    remove.setAttribute('aria-label', 'Remove photo');
                    remove.appendChild(buildCloseIcon());
                    remove.addEventListener('click', function () {
                        resetPhoto();
                        body.focus();
                    });

                    emptyPreview();
                    previewBox.appendChild(img);
                    previewBox.appendChild(remove);
                    previewBox.classList.remove('hidden');

                    if (photoName) {
                        photoName.textContent = fileName;
                    }
                };

                img.onerror = function () {
                    if (token !== previewToken) {
                        return;
                    }

                    resetPhoto();
                    showPhotoError('That file could not be read as an image.');
                };

                img.src = dataUrl;
            };

            photoInput.addEventListener('change', function () {
                var file = photoInput.files && photoInput.files[0];

                // User opened the picker and cancelled, or the selection was cleared.
                if (!file) {
                    resetPhoto();
                    return;
                }

                clearPhotoError();

                if (ALLOWED_TYPES.indexOf(file.type) === -1) {
                    resetPhoto();
                    showPhotoError('Please choose a JPG, PNG or WebP photo.');
                    return;
                }

                if (file.size > MAX_BYTES) {
                    resetPhoto();
                    showPhotoError('The photo must be 4 MB or smaller.');
                    return;
                }

                previewToken += 1;

                var token = previewToken;
                var reader = new FileReader();

                reader.onload = function () {
                    if (token !== previewToken) {
                        return;
                    }

                    showPreview(String(reader.result), file.name, token);
                };

                reader.onerror = function () {
                    if (token !== previewToken) {
                        return;
                    }

                    resetPhoto();
                    showPhotoError('That file could not be read. Try another photo.');
                };

                reader.readAsDataURL(file);
            });
        }

        syncPostBtn();
        syncAnon();
        autoGrow(); // handles old('body') after a failed validation
    }

    /* ---------- Post page: anonymous toggle + reply-to ---------- */

    document.querySelectorAll('[data-toggle-replies]').forEach(function (button) {
        button.addEventListener('click', function () {
            var replies = document.getElementById(button.getAttribute('data-toggle-replies'));

            if (!replies) {
                return;
            }

            var isExpanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
            replies.classList.toggle('hidden', isExpanded);
            button.textContent = isExpanded
                ? 'View ' + replies.children.length + ' ' + (replies.children.length === 1 ? 'reply' : 'replies')
                : button.getAttribute('data-expanded-label');
        });
    });

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

    /* ---------- AJAX reactions (Like / Helpful) ----------
       One delegated listener for the whole page. A button opts in with
       data-react="like|helpful" plus data-post-id or data-comment-id.
       The URL comes from the button's own <form action>, so Blade stays the
       single source of truth for routes and the no-JS fallback still works. */

    var reactErrorTimer = null;

    function reactCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    function showReactError(message) {
        var main = document.querySelector('main');
        var box = document.getElementById('react-error');

        if (!main) {
            return;
        }

        if (!box) {
            box = document.createElement('div');
            box.id = 'react-error';
            box.className = 'card cm-notice cm-notice--error';
            box.setAttribute('role', 'alert');
            main.insertBefore(box, main.children[1] || null);
        }

        box.textContent = message;

        clearTimeout(reactErrorTimer);
        reactErrorTimer = setTimeout(function () {
            if (box.parentNode) {
                box.parentNode.removeChild(box);
            }
        }, 5000);
    }

    // Updates EVERY matching button on the page (the same post can appear
    // twice, e.g. pinned + feed), not just the one that was tapped.
    function applyReaction(button, data) {
        var kind = button.hasAttribute('data-post-id') ? 'post' : 'comment';
        var id = button.getAttribute('data-' + kind + '-id');
        var type = button.getAttribute('data-react');
        var selector = '[data-react="' + type + '"][data-' + kind + '-id="' + id + '"]';

        document.querySelectorAll(selector).forEach(function (el) {
            var count = el.querySelector('.cm-stat__count');

            el.classList.toggle('is-active', !!data.is_active);
            el.setAttribute('aria-pressed', data.is_active ? 'true' : 'false');

            if (count) {
                // Comment buttons hide a zero ("Like"), post buttons show it ("0").
                count.textContent = (data.new_count > 0 || !count.hasAttribute('data-hide-zero'))
                    ? String(data.new_count)
                    : '';
            }
        });
    }

    function handleReactFailure(res) {
        var errors = res.data && res.data.errors;
        var message = (errors && errors.report && errors.report[0]) || (res.data && res.data.message) || '';

        if (res.status === 401) {
            window.location.href = '/';
            return;
        }

        if (res.status === 403 && message === '') {
            window.location.reload(); // banned: the server renders the suspended page
            return;
        }

        if (res.status === 419) {
            message = 'Your session expired. Refresh the page and try again.';
        } else if (res.status === 429) {
            message = 'You are going too fast. Try again in a moment.';
        }

        showReactError(message || 'Something went wrong. Try again.');
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-react]');

        if (!button || !button.form) {
            return;
        }

        event.preventDefault();

        // Ignore taps while this button's request is still in flight.
        if (button.classList.contains('is-loading')) {
            return;
        }

        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        var token = reactCsrf();

        if (token) {
            headers['X-CSRF-TOKEN'] = token;
        }

        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');

        fetch(button.form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers
        }).then(function (response) {
            return response.json().then(function (data) {
                return data;
            }, function () {
                return {};
            }).then(function (data) {
                return { ok: response.ok, status: response.status, data: data || {} };
            });
        }).then(function (res) {
            if (res.ok && res.data.status === 'success') {
                applyReaction(button, res.data);
                return;
            }

            handleReactFailure(res);
        }, function () {
            showReactError('Could not reach server.');
        }).then(function () {
            button.classList.remove('is-loading');
            button.removeAttribute('aria-busy');
        });
    });

    /* ---------- Report bottom sheet ---------- */

    var sheet = document.getElementById('report-sheet');

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
