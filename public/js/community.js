(function () {
    'use strict';

    window.copyLink = function (url) {
        if (!navigator.clipboard || !navigator.clipboard.writeText) {
            window.alert('Clipboard access is unavailable in this browser.');
            return;
        }

        navigator.clipboard.writeText(url).then(function () {
            window.alert('Link copied to clipboard!');
        }, function () {
            window.alert('Could not copy link to clipboard.');
        });
    };

    /* ---------- Context-aware card menus ---------- */

    function closeMoreMenus(except) {
        document.querySelectorAll('.cm-more-menu__dropdown:not(.hidden)').forEach(function (menu) {
            if (except && except.contains(menu)) {
                return;
            }

            menu.classList.add('hidden');
            var toggle = document.querySelector('[aria-controls="' + menu.id + '"]');

            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-menu-toggle]');

        if (toggle) {
            var menu = document.getElementById(toggle.getAttribute('aria-controls'));

            if (!menu) {
                return;
            }

            var willOpen = menu.classList.contains('hidden');
            closeMoreMenus(toggle.closest('.cm-more-menu'));
            menu.classList.toggle('hidden', !willOpen);
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            return;
        }

        var menuItem = event.target.closest('.cm-more-menu__dropdown');

        if (menuItem) {
            closeMoreMenus();
            return;
        }

        closeMoreMenus();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        var openMenu = document.querySelector('.cm-more-menu__dropdown:not(.hidden)');

        if (!openMenu) {
            return;
        }

        var toggle = document.querySelector('[aria-controls="' + openMenu.id + '"]');
        closeMoreMenus();

        if (toggle) {
            toggle.focus();
        }
    });

    /* ---------- Feed pull-to-refresh ---------- */

    var feedSkeleton = document.getElementById('skeleton-loader');

    if (feedSkeleton) {
        var touchStartY = null;
        var touchStartX = null;
        var refreshing = false;

        window.addEventListener('touchstart', function (event) {
            if (event.touches.length !== 1 || window.scrollY > 0) {
                touchStartY = null;
                touchStartX = null;
                return;
            }

            touchStartY = event.touches[0].clientY;
            touchStartX = event.touches[0].clientX;
        }, { passive: true });

        window.addEventListener('touchmove', function (event) {
            if (touchStartY === null || refreshing || event.touches.length !== 1 || window.scrollY > 0) {
                return;
            }

            var deltaY = event.touches[0].clientY - touchStartY;
            var deltaX = event.touches[0].clientX - touchStartX;

            if (deltaY <= 80 || Math.abs(deltaX) > Math.abs(deltaY)) {
                return;
            }

            refreshing = true;
            feedSkeleton.classList.remove('hidden');
            feedSkeleton.setAttribute('aria-hidden', 'false');
            document.querySelectorAll('.cm-post, .cm-empty').forEach(function (item) {
                item.hidden = true;
            });

            window.setTimeout(function () {
                window.location.reload();
            }, 120);
        }, { passive: true });

        window.addEventListener('touchend', function () {
            touchStartY = null;
            touchStartX = null;
        }, { passive: true });

        window.addEventListener('touchcancel', function () {
            touchStartY = null;
            touchStartX = null;
        }, { passive: true });
    }

    /* ---------- Activity tabs ---------- */

    document.addEventListener('click', function (event) {
        var tab = event.target.closest('[data-activity-tab]');

        if (!tab) {
            return;
        }

        var activity = tab.closest('[data-activity]');

        if (!activity) {
            return;
        }

        var selected = tab.getAttribute('data-activity-tab');

        activity.querySelectorAll('[data-activity-tab]').forEach(function (item) {
            var isSelected = item === tab;
            item.classList.toggle('is-active', isSelected);
            item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            item.setAttribute('tabindex', isSelected ? '0' : '-1');
        });

        activity.querySelectorAll('[data-activity-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-activity-panel') !== selected;
        });
    });

    document.addEventListener('keydown', function (event) {
        var tab = event.target.closest('[data-activity-tab]');

        if (!tab || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) {
            return;
        }

        var activity = tab.closest('[data-activity]');
        var tabs = activity ? Array.prototype.slice.call(activity.querySelectorAll('[data-activity-tab]')) : [];
        var currentIndex = tabs.indexOf(tab);

        if (tabs.length < 2 || currentIndex < 0) {
            return;
        }

        event.preventDefault();
        var direction = event.key === 'ArrowRight' ? 1 : -1;
        var nextTab = tabs[(currentIndex + direction + tabs.length) % tabs.length];
        nextTab.focus();
        nextTab.click();
    });

    /* ---------- Shared helpers for AJAX forms (composer) ---------- */

    // Sends a form as multipart FormData. Resolves to { ok, status, data } or
    // { networkError: true }.
    function sendForm(form) {
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        var token = reactCsrf();

        if (token) {
            headers['X-CSRF-TOKEN'] = token;
        }

        return fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: new FormData(form)
        }).then(function (response) {
            return response.json().then(function (data) {
                return data;
            }, function () {
                return {};
            }).then(function (data) {
                return { ok: response.ok, status: response.status, data: data || {} };
            });
        }, function () {
            return { networkError: true };
        }).then(function (result) {
            return result;
        }, function () {
            return { networkError: true };
        });
    }

    function showInlineError(el, message) {
        if (!el) {
            return;
        }

        el.textContent = message || '';
        el.hidden = !message;
    }

    function setSending(button, label) {
        button.dataset.label = button.textContent;
        button.textContent = label;
        button.disabled = true;
    }

    function clearSending(button) {
        if (button.dataset.label) {
            button.textContent = button.dataset.label;
        }

        button.disabled = false;
    }

    function composerFailure(res, errorEl) {
        var errors = res.data && res.data.errors;
        var fields = ['body', 'category', 'photo', 'parent_id', 'report'];
        var message = '';
        var i;

        if (res.networkError) {
            showInlineError(errorEl, 'Could not reach server.');
            return;
        }

        if (res.status === 401) {
            window.location.href = '/';
            return;
        }

        if (res.status === 403) {
            window.location.reload();
            return;
        }

        if (errors) {
            for (i = 0; i < fields.length && message === ''; i++) {
                if (errors[fields[i]] && errors[fields[i]].length) {
                    message = errors[fields[i]][0];
                }
            }
        }

        if (message === '') {
            if (res.status === 419) {
                message = 'Your session expired. Refresh the page and try again.';
            } else if (res.status === 429) {
                message = 'You are posting too fast. Try again in a moment.';
            } else if (res.status === 413) {
                message = 'That upload is too large.';
            } else {
                message = (res.data && res.data.message) || 'Something went wrong. Try again.';
            }
        }

        showInlineError(errorEl, message);
    }

    /* ---------- Create post: enable Post, alias hint, auto-grow, photo preview ---------- */

    var postForm = document.getElementById('post-form');

    if (postForm) {
        var body = document.getElementById('body');
        var postBtn = document.getElementById('post-btn');
        var anon = document.getElementById('anonymous');
        var anonHint = document.getElementById('anon-hint');
        var charCount = document.getElementById('char-count');
        var alias = postForm.getAttribute('data-alias');
        var postSubmitting = false;

        var syncCharCount = function () {
            if (!charCount) {
                return;
            }

            var length = body.value.length;
            charCount.textContent = length + ' / 2000';
            charCount.classList.toggle('text-warning', length > 1800);
            charCount.classList.toggle('text-danger', length > 1950);
        };

        var syncPostBtn = function () {
            postBtn.disabled = postSubmitting || body.value.trim().length < 3;
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
            syncCharCount();
            autoGrow();
        });

        // Width changes re-wrap the text, which changes the height it needs.
        window.addEventListener('resize', autoGrow);

        anon.addEventListener('change', syncAnon);

        /* -- AJAX submit -- */

        var postError = document.getElementById('post-error');

        postForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (postSubmitting || postBtn.disabled) {
                return;
            }

            postSubmitting = true;
            showInlineError(postError, '');
            setSending(postBtn, 'Posting\u2026');

            sendForm(postForm).then(function (res) {
                if (res.ok && res.data.status === 'success') {
                    window.location.href = res.data.redirect || '/feed';
                    return;
                }

                postSubmitting = false;
                clearSending(postBtn);
                syncPostBtn();
                composerFailure(res, postError);
            });
        });

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
        syncCharCount();
        syncAnon();
        autoGrow(); // handles old('body') after a failed validation
    }

    /* ---------- Post page: anonymous toggle + reply-to ---------- */

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-toggle-replies]');

        if (!button) {
            return;
        }

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

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-reply-to]');

            if (!button) {
                return;
            }

            parentId.value = button.getAttribute('data-reply-to');
            replyName.textContent = 'Replying to ' + button.getAttribute('data-reply-name');
            replyNote.hidden = false;
            commentBody.placeholder = 'Write a reply\u2026';
            commentBody.focus();
        });

        replyCancel.addEventListener('click', clearReply);

        /* -- AJAX submit -- */

        var commentError = document.getElementById('comment-error');
        var commentList = document.getElementById('comment-list');
        var commentSubmit = commentForm.querySelector('button[type="submit"]');
        var commentSending = false;

        var repliesContainerFor = function (parent) {
            var containerId = 'comment-replies-' + parent;
            var container = document.getElementById(containerId);

            if (container) {
                return container;
            }

            var parentEl = document.getElementById('comment-' + parent);
            var wrapper = parentEl ? parentEl.closest('.cm-comment-wrapper') : null;

            if (!wrapper) {
                return null;
            }

            var toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'cm-view-replies-btn';
            toggleBtn.setAttribute('data-toggle-replies', containerId);
            toggleBtn.setAttribute('data-expanded-label', 'Hide replies');
            toggleBtn.setAttribute('aria-controls', containerId);
            toggleBtn.setAttribute('aria-expanded', 'true');
            toggleBtn.textContent = 'Hide replies';

            container = document.createElement('div');
            container.className = 'cm-replies-container';
            container.id = containerId;

            wrapper.appendChild(toggleBtn);
            wrapper.appendChild(container);

            return container;
        };

        var insertComment = function (data) {
            var target = commentList;
            var empty = document.getElementById('comments-empty');

            if (empty) {
                empty.parentNode.removeChild(empty);
            }

            if (data.parent_id) {
                var container = repliesContainerFor(data.parent_id);

                if (container) {
                    target = container;

                    if (container.classList.contains('hidden')) {
                        container.classList.remove('hidden');

                        var toggleButton = document.querySelector('[data-toggle-replies="' + container.id + '"]');

                        if (toggleButton) {
                            toggleButton.setAttribute('aria-expanded', 'true');
                            toggleButton.textContent = toggleButton.getAttribute('data-expanded-label');
                        }
                    }
                }
            }

            if (!target) {
                window.location.reload();
                return;
            }

            target.insertAdjacentHTML('beforeend', data.html);

            document.querySelectorAll('[data-comments-count]').forEach(function (el) {
                el.textContent = String(data.comments_count);
            });

            var added = document.getElementById('comment-' + data.comment_id);

            if (added) {
                added.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        };

        commentForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (commentSending) {
                return;
            }

            if (commentBody.value.trim() === '') {
                commentBody.focus();
                return;
            }

            commentSending = true;
            showInlineError(commentError, '');
            setSending(commentSubmit, 'Sending\u2026');

            sendForm(commentForm).then(function (res) {
                commentSending = false;
                clearSending(commentSubmit);

                if (res.ok && res.data.status === 'success') {
                    insertComment(res.data);
                    commentBody.value = '';
                    clearReply();
                    commentBody.focus();
                    return;
                }

                composerFailure(res, commentError);
            });
        });
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

            if (type === 'like') {
                var likeAction = data.is_active ? 'Unlike' : 'Like';
                el.setAttribute('aria-label', likeAction + ' ' + kind + ', ' + data.new_count + ' likes');
                el.title = likeAction + ' ' + kind;
            } else {
                var helpfulAction = data.is_active ? 'Remove helpful mark' : 'Mark helpful';
                el.setAttribute('aria-label', helpfulAction + ', ' + data.new_count + ' helpful marks');
                el.title = helpfulAction;
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

    /* ---------- Feed polish: "See more" truncation ---------- */

    var clampWraps = Array.prototype.slice.call(document.querySelectorAll('[data-clamp-wrap]'));

    function syncSeeMore(wrap) {
        var text = wrap.querySelector('.cm-clamp');
        var button = wrap.querySelector('[data-see-more]');

        if (!text || !button) {
            return;
        }

        button.hidden = !(text.scrollHeight > text.clientHeight);
    }

    function syncAllSeeMore() {
        clampWraps.forEach(syncSeeMore);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-see-more]');

        if (!button) {
            return;
        }

        var wrap = button.closest('[data-clamp-wrap]');
        var text = wrap ? wrap.querySelector('.cm-clamp') : null;

        if (text) {
            text.classList.remove('cm-clamp');
        }

        button.setAttribute('aria-expanded', 'true');
        button.hidden = true;
    });

    syncAllSeeMore();
    window.addEventListener('load', syncAllSeeMore);
    window.addEventListener('resize', syncAllSeeMore);

    /* ---------- Feed polish: photo lightbox ---------- */

    var lightbox = document.getElementById('lightbox');

    if (lightbox) {
        var lightboxImg = document.getElementById('lightbox-img');
        var lightboxClose = document.getElementById('lightbox-close');
        var lightboxOpener = null;

        var openLightbox = function (img) {
            lightboxOpener = img;
            lightboxImg.src = img.getAttribute('data-lightbox-src') || img.src;
            lightboxImg.alt = img.alt || '';
            lightbox.hidden = false;
            document.body.classList.add('cm-no-scroll');
            lightboxClose.focus();
        };

        var closeLightbox = function () {
            lightbox.hidden = true;
            lightboxImg.removeAttribute('src');
            document.body.classList.remove('cm-no-scroll');

            if (lightboxOpener && lightboxOpener.focus) {
                lightboxOpener.focus();
            }

            lightboxOpener = null;
        };

        document.addEventListener('click', function (event) {
            var img = event.target.closest('.cm-post__img');

            if (img) {
                event.preventDefault();
                openLightbox(img);
            }
        });

        lightbox.addEventListener('click', function (event) {
            if (event.target !== lightboxImg) {
                closeLightbox();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !lightbox.hidden) {
                closeLightbox();
            }
        });
    }
})();
