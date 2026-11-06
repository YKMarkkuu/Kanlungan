(function () {
    'use strict';

    var confirmModal = document.getElementById('confirm-modal');
    var confirmTitle = document.getElementById('confirm-modal-title');
    var confirmMessage = document.getElementById('confirm-modal-message');
    var confirmSubmit = document.getElementById('confirm-modal-submit');
    var activeConfirmResolve = null;
    var confirmReturnFocus = null;
    var toast = document.getElementById('global-toast');
    var toastTimer = null;

    function finishConfirm(confirmed) {
        if (!activeConfirmResolve) {
            return;
        }

        var resolve = activeConfirmResolve;
        activeConfirmResolve = null;
        confirmModal.hidden = true;
        resolve(confirmed);

        if (confirmReturnFocus && confirmReturnFocus.isConnected) {
            confirmReturnFocus.focus({ preventScroll: true });
        }
        confirmReturnFocus = null;
    }

    window.showConfirm = function (title, message, isDestructive) {
        if (!confirmModal) {
            console.error('The confirmation modal is missing from this page.');
            return Promise.resolve(false);
        }

        if (activeConfirmResolve) {
            finishConfirm(false);
        }

        confirmTitle.textContent = title;
        confirmMessage.textContent = message;
        confirmSubmit.classList.toggle('btn--danger', Boolean(isDestructive));
        confirmSubmit.classList.toggle('btn--primary', !isDestructive);
        confirmReturnFocus = document.activeElement;
        confirmModal.hidden = false;

        return new Promise(function (resolve) {
            activeConfirmResolve = resolve;
            confirmModal.querySelector('[data-confirm-cancel]:not(.cm-modal__backdrop)').focus();
        });
    };

    confirmModal?.querySelectorAll('[data-confirm-cancel]').forEach(function (button) {
        button.addEventListener('click', function () {
            finishConfirm(false);
        });
    });

    confirmSubmit?.addEventListener('click', function () {
        finishConfirm(true);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && activeConfirmResolve) {
            finishConfirm(false);
        }
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-submit')) {
            return;
        }

        event.preventDefault();

        if (form.dataset.confirmPending === 'true') {
            return;
        }
        form.dataset.confirmPending = 'true';

        window.showConfirm(
            form.dataset.confirmTitle || 'Are you sure?',
            form.dataset.confirmMessage || '',
            form.hasAttribute('data-confirm-destructive')
        ).then(function (confirmed) {
            delete form.dataset.confirmPending;

            if (confirmed && form.isConnected) {
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    });

    window.showToast = function (message, isError) {
        if (!toast) {
            console.error('The notification toast is missing from this page.');
            return;
        }

        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.toggle('is-error', Boolean(isError));
        toast.hidden = false;
        toastTimer = window.setTimeout(function () {
            toast.hidden = true;
        }, 3200);
    };

    window.copyLink = function (url) {
        if (!navigator.clipboard || !navigator.clipboard.writeText) {
            window.showToast('Clipboard access is unavailable in this browser.', true);
            return;
        }

        navigator.clipboard.writeText(url).then(function () {
            window.showToast('Link copied to clipboard!');
        }, function () {
            window.showToast('Could not copy link to clipboard.', true);
        });
    };

    var restartOnboardingButton = document.querySelector('[data-restart-onboarding]');

    if (restartOnboardingButton) {
        restartOnboardingButton.addEventListener('click', function () {
            var tokenMeta = document.querySelector('meta[name="csrf-token"]');
            var headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            if (tokenMeta) {
                headers['X-CSRF-TOKEN'] = tokenMeta.getAttribute('content');
            }

            restartOnboardingButton.disabled = true;
            fetch('/onboarding/restart', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Restarting onboarding failed with HTTP ' + response.status);
                }

                return response.json();
            }).then(function (data) {
                if (!data || data.status !== 'success') {
                    throw new Error('Restarting onboarding returned an unexpected response.');
                }

                window.location.href = '/onboarding';
            }).catch(function (error) {
                console.error(error);
                window.showToast('Could not restart the tour. Please try again.', true);
                restartOnboardingButton.disabled = false;
            });
        });
    }

    var onboardingAnonToggle = document.getElementById('onboarding-anon-toggle');
    var onboardingAnonCaption = document.getElementById('onboarding-anon-caption');
    var onboardingDemoWrappers = document.querySelectorAll('.onboarding__demo');

    onboardingDemoWrappers.forEach(function (demo) {
        demo.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    });

    if (onboardingAnonToggle && onboardingAnonCaption) {
        onboardingAnonToggle.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            var isAnonymous = onboardingAnonToggle.getAttribute('aria-pressed') !== 'true';
            onboardingAnonToggle.setAttribute('aria-pressed', String(isAnonymous));
            onboardingAnonCaption.textContent = isAnonymous
                ? "You'll appear as Anonymous Falcon"
                : "You'll appear as Mark";
        });
    }

    var onboardingHelpfulButton = document.getElementById('onboarding-helpful-btn');
    var onboardingPointsCount = document.getElementById('onboarding-points-count');
    var onboardingPointsEarned = document.getElementById('onboarding-points-earned');

    if (onboardingHelpfulButton && onboardingPointsCount && onboardingPointsEarned) {
        onboardingHelpfulButton.addEventListener('click', function (event) {
            event.stopPropagation();
            event.preventDefault();

            onboardingHelpfulButton.classList.remove('is-popping');
            void onboardingHelpfulButton.offsetWidth;
            onboardingHelpfulButton.classList.add('is-popping');

            if (onboardingHelpfulButton.getAttribute('aria-pressed') === 'true') {
                return;
            }

            onboardingHelpfulButton.setAttribute('aria-pressed', 'true');
            onboardingHelpfulButton.classList.add('is-active');
            onboardingPointsCount.textContent = String(Number(onboardingPointsCount.textContent) + 3);
            onboardingPointsEarned.hidden = false;
        });
    }

    var onboardingReportToggle = document.getElementById('onboarding-report-menu-toggle');
    var onboardingReportMenu = document.getElementById('onboarding-report-menu');
    var onboardingReportOption = document.getElementById('onboarding-report-option');
    var onboardingReportNote = document.getElementById('onboarding-report-note');

    if (onboardingReportToggle && onboardingReportMenu && onboardingReportOption && onboardingReportNote) {
        onboardingReportToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            event.preventDefault();

            var isOpen = onboardingReportToggle.getAttribute('aria-expanded') === 'true';
            onboardingReportToggle.setAttribute('aria-expanded', String(!isOpen));
            onboardingReportMenu.hidden = isOpen;
        });

        onboardingReportOption.addEventListener('click', function (event) {
            event.stopPropagation();
            event.preventDefault();

            onboardingReportNote.hidden = false;
        });
    }

    function setupOnboardingDemoLoop(button) {
        var target = button.closest('.cm-demo-target');
        var slide = button.closest('[data-onboarding-slide]');

        if (!target || !slide) {
            return;
        }

        var timer = null;
        var stopped = false;

        function clearAnimation() {
            if (timer !== null) {
                window.clearTimeout(timer);
                timer = null;
            }

            target.classList.remove('is-tapping');
            button.classList.remove('is-rippling');
        }

        function scheduleTap(delay) {
            if (!stopped) {
                timer = window.setTimeout(tap, delay);
            }
        }

        function tap() {
            timer = null;

            if (stopped) {
                return;
            }

            if (!slide.classList.contains('is-active')) {
                scheduleTap(500);
                return;
            }

            target.classList.add('is-tapping');
            timer = window.setTimeout(function () {
                timer = null;

                if (stopped || !slide.classList.contains('is-active')) {
                    clearAnimation();
                    scheduleTap(500);
                    return;
                }

                if (button === onboardingReportToggle && onboardingReportMenu) {
                    onboardingReportToggle.setAttribute('aria-expanded', 'false');
                    onboardingReportMenu.hidden = true;
                }

                button.click();
                timer = window.setTimeout(function () {
                    timer = null;
                    target.classList.remove('is-tapping');
                    button.classList.remove('is-rippling');
                    scheduleTap(3000);
                }, 500);
            }, 500);
        }

        button.addEventListener('click', function (event) {
            if (event.isTrusted) {
                stopped = true;
                clearAnimation();
            }

            button.classList.remove('is-rippling');
            void button.offsetWidth;
            button.classList.add('is-rippling');
            window.setTimeout(function () {
                button.classList.remove('is-rippling');
            }, 500);
        }, true);

        scheduleTap(1000);
    }

    var reduceMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!reduceMotion) {
        [onboardingAnonToggle, onboardingHelpfulButton, onboardingReportToggle].forEach(function (button) {
            if (button) {
                setupOnboardingDemoLoop(button);
            }
        });
    }

    /* ---------- Notification dropdown and unread-count polling ---------- */

    var notificationToggle = document.querySelector('[data-notification-toggle]');
    var notificationDropdown = document.querySelector('[data-notification-dropdown]');

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a.cm-notification-dropdown__item, a.cm-alert');

        if (!link || event.defaultPrevented || event.button !== 0 ||
            event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ||
            (link.target && link.target !== '_self')) {
            return;
        }

        event.preventDefault();
        window.location.href = link.href;
    });

    if (notificationToggle && notificationDropdown) {
        function closeNotificationDropdown() {
            notificationDropdown.classList.add('hidden');
            notificationToggle.setAttribute('aria-expanded', 'false');
        }

        notificationToggle.addEventListener('click', function () {
            var isOpen = notificationToggle.getAttribute('aria-expanded') === 'true';
            notificationDropdown.classList.toggle('hidden', isOpen);
            notificationToggle.setAttribute('aria-expanded', String(!isOpen));
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-notification-menu]')) {
                closeNotificationDropdown();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeNotificationDropdown();
            }
        });

        var unreadCountUrl = notificationToggle.getAttribute('data-unread-count-url');
        var renderedUnreadCount = Number(notificationToggle.getAttribute('data-unread-count'));

        function wobbleBells() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            document.querySelectorAll('.cm-bell .icon, [data-alert-activity-link] .icon, [data-unread-badge]').forEach(function (el) {
                el.classList.remove('is-wobbling');
                void el.getBoundingClientRect();
                el.classList.add('is-wobbling');
                el.addEventListener('animationend', function done() {
                    el.classList.remove('is-wobbling');
                    el.removeEventListener('animationend', done);
                });
            });
        }

        function refreshUnreadCount() {
            fetch(unreadCountUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'application/json' }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Unread notification polling failed with HTTP ' + response.status);
                }

                return response.json();
            }).then(function (data) {
                var nextCount = Number(data.count);

                if (!Number.isInteger(nextCount) || nextCount < 0 || nextCount === renderedUnreadCount) {
                    return;
                }

                var hasNewAlert = nextCount > renderedUnreadCount;
                renderedUnreadCount = nextCount;
                notificationToggle.setAttribute('data-unread-count', String(nextCount));
                notificationToggle.setAttribute('aria-label', 'Notifications' + (nextCount > 0 ? ', ' + nextCount + ' unread' : ''));

                document.querySelectorAll('[data-unread-badge]').forEach(function (badge) {
                    badge.textContent = String(nextCount);
                    badge.hidden = nextCount === 0;
                });

                var activityLink = document.querySelector('[data-alert-activity-link]');
                if (activityLink) {
                    activityLink.setAttribute('aria-label', 'Activity' + (nextCount > 0 ? ', ' + nextCount + ' unread' : ''));
                }

                if (hasNewAlert) {
                    wobbleBells();
                }
            }).catch(function (error) {
                console.error(error);
            });
        }

        window.setInterval(refreshUnreadCount, 30000);
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                if (document.querySelector('[data-activity]')) {
                    window.location.reload();
                    return;
                }

                refreshUnreadCount();
            }
        });
    }

    /* ---------- Profile avatar preview and crop ---------- */

    /* ---------- Profile: tap your name to edit it inline ---------- */

    var nameForm = document.querySelector('[data-name-form]');
    var nameTrigger = document.querySelector('[data-name-edit]');

    if (nameForm && nameTrigger) {
        var nameRoot = nameForm.closest('.cm-identity');
        var nameInput = nameForm.querySelector('input[name="display_name"]');
        var nameOriginal = nameInput.getAttribute('data-original') || '';
        var nameLocked = false;

        var openNameEditor = function () {
            nameLocked = false;
            nameTrigger.hidden = true;
            nameForm.hidden = false;
            nameForm.classList.add('cm-animate-fade');
            nameRoot.classList.add('is-editing');
            nameInput.focus();
            nameInput.setSelectionRange(nameInput.value.length, nameInput.value.length);
        };

        var closeNameEditor = function () {
            nameInput.value = nameOriginal;
            nameForm.hidden = true;
            nameTrigger.hidden = false;
            nameRoot.classList.remove('is-editing');
            nameTrigger.focus({ preventScroll: true });
        };

        var commitName = function () {
            if (nameLocked) {
                return;
            }

            nameLocked = true;

            if (nameInput.value.trim() === nameOriginal.trim()) {
                closeNameEditor();
                return;
            }

            if (nameForm.requestSubmit) {
                nameForm.requestSubmit();
            } else {
                nameForm.submit();
            }
        };

        nameTrigger.addEventListener('click', openNameEditor);
        nameInput.addEventListener('blur', commitName);
        nameInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                commitName();
            } else if (event.key === 'Escape') {
                nameLocked = true;
                closeNameEditor();
            }
        });
        nameForm.addEventListener('submit', function () { nameLocked = true; });

        if (!nameForm.hidden) {
            nameInput.focus();
        }
    }

    var avatarForm = document.querySelector('[data-avatar-upload]');

    if (avatarForm) {
        var avatarInput = avatarForm.querySelector('[data-avatar-input]');
        var avatarChoose = avatarForm.querySelector('[data-avatar-choose]');
        var avatarModal = document.querySelector('[data-avatar-crop]');
        var avatarPreview = avatarModal.querySelector('[data-avatar-preview]');
        var avatarError = avatarModal.querySelector('[data-avatar-error]');
        var avatarSave = avatarModal.querySelector('[data-avatar-save]');
        var avatarCancel = avatarModal.querySelectorAll('[data-avatar-cancel]');
        var avatarObjectUrl = null;
        var avatarCropper = null;
        var avatarOpener = null;

        function showAvatarError(message) {
            avatarError.textContent = message;
            avatarError.hidden = false;
        }

        function destroyAvatarCropper() {
            if (avatarCropper) {
                avatarCropper.destroy();
                avatarCropper = null;
            }
        }

        function closeAvatarModal() {
            avatarModal.hidden = true;
            document.body.classList.remove('cm-no-scroll');
            destroyAvatarCropper();

            if (avatarObjectUrl) {
                URL.revokeObjectURL(avatarObjectUrl);
                avatarObjectUrl = null;
            }

            avatarPreview.onload = null;
            avatarPreview.onerror = null;
            avatarPreview.removeAttribute('src');
            avatarError.hidden = true;
            avatarError.textContent = '';
            avatarSave.disabled = false;
            avatarSave.textContent = 'Save Avatar';

            if (avatarOpener) {
                avatarOpener.focus();
            }
        }

        avatarChoose.addEventListener('click', function () {
            avatarOpener = avatarChoose;
            avatarInput.click();
        });

        avatarInput.addEventListener('change', function () {
            if (!avatarInput.files || !avatarInput.files[0]) {
                return;
            }

            destroyAvatarCropper();
            if (avatarObjectUrl) {
                URL.revokeObjectURL(avatarObjectUrl);
                avatarObjectUrl = null;
            }

            avatarError.hidden = true;
            avatarError.textContent = '';
            avatarSave.disabled = true;
            avatarSave.textContent = 'Save Avatar';
            avatarModal.hidden = false;
            document.body.classList.add('cm-no-scroll');
            avatarModal.querySelector('.cm-avatar-crop__actions [data-avatar-cancel]').focus();

            if (typeof window.Cropper !== 'function') {
                showAvatarError('The photo editor could not be loaded. Refresh the page and try again.');
                return;
            }

            try {
                avatarObjectUrl = URL.createObjectURL(avatarInput.files[0]);
            } catch (error) {
                showAvatarError('Could not open this image. Please choose another photo.');
                return;
            }

            avatarPreview.onload = function () {
                try {
                    avatarCropper = new window.Cropper(avatarPreview, {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        cropBoxMovable: false,
                        guides: false,
                        center: true,
                        highlight: false,
                        background: false,
                        autoCropArea: 1,
                        cropBoxResizable: false,
                        toggleDragModeOnDblclick: false,
                        responsive: true,
                        zoomOnTouch: true,
                        zoomOnWheel: false,
                        ready: function () {
                            avatarSave.disabled = false;
                        }
                    });
                } catch (error) {
                    showAvatarError('Could not prepare this image for cropping. Please choose another photo.');
                }
            };
            avatarPreview.onerror = function () {
                showAvatarError('This image could not be previewed. Choose a different image.');
            };
            avatarPreview.src = avatarObjectUrl;
        });

        avatarCancel.forEach(function (button) {
            button.addEventListener('click', function () {
                closeAvatarModal();
            });
        });

        avatarSave.addEventListener('click', function () {
            if (!avatarCropper || !avatarInput.files[0] || avatarSave.disabled) {
                return;
            }

            avatarSave.disabled = true;
            avatarError.hidden = true;
            avatarError.textContent = '';
            avatarSave.textContent = 'Uploading…';

            try {
                var canvas = avatarCropper.getCroppedCanvas({
                    width: 150,
                    height: 150,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high'
                });

                if (!canvas) {
                    throw new Error('Could not crop this image. Please try again.');
                }

                canvas.toBlob(function (blob) {
                    if (!blob) {
                        showAvatarError('Could not prepare this image. Please try again.');
                        avatarSave.disabled = false;
                        avatarSave.textContent = 'Save Avatar';
                        return;
                    }

                    var formData = new FormData(avatarForm);
                    formData.set('avatar', blob, 'avatar.jpg');

                    fetch(avatarForm.action, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' }
                    }).then(function (response) {
                        if (response.ok) {
                            window.location.reload();
                            return;
                        }

                        return response.json().then(function (payload) {
                            var message = payload.errors && payload.errors.avatar
                                ? payload.errors.avatar[0]
                                : payload.message;
                            throw new Error(message || 'Avatar upload failed. Please try again.');
                        }, function () {
                            throw new Error('Avatar upload failed (HTTP ' + response.status + '). Please try again.');
                        });
                    }).catch(function (error) {
                        showAvatarError(error.message || 'Avatar upload failed. Please try again.');
                        avatarSave.disabled = false;
                        avatarSave.textContent = 'Save Avatar';
                    });
                }, 'image/jpeg', 0.9);
            } catch (error) {
                showAvatarError(error.message || 'Could not prepare this image. Please try again.');
                avatarSave.disabled = false;
                avatarSave.textContent = 'Save Avatar';
            }
        });

        avatarForm.addEventListener('submit', function (event) {
            event.preventDefault();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !avatarModal.hidden) {
                closeAvatarModal();
            }
        });
    }

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

    var loadMoreButton = document.getElementById('load-more-btn');

    if (loadMoreButton) {
        var feedPosts = document.getElementById('feed-posts');
        var loadMoreContainer = document.getElementById('load-more-container');
        var loadMoreError = document.getElementById('load-more-error');
        var feedPageLoading = false;
        var pauseAutoLoad = false;
        var loadMoreLabel = loadMoreButton.textContent;

        function loadNextFeedPage() {
            if (feedPageLoading || loadMoreButton.disabled) {
                return;
            }

            var nextUrl = loadMoreButton.getAttribute('data-next-url');
            if (!nextUrl) {
                loadMoreContainer.hidden = true;
                return;
            }

            var pageUrl = new URL(nextUrl, window.location.href);
            if (pageUrl.origin !== window.location.origin) {
                loadMoreError.textContent = 'Could not load more posts because the page address was invalid.';
                loadMoreError.hidden = false;
                return;
            }

            feedPageLoading = true;
            loadMoreButton.disabled = true;
            loadMoreButton.setAttribute('aria-busy', 'true');
            loadMoreButton.textContent = 'Loading…';
            loadMoreError.hidden = true;
            loadMoreError.textContent = '';

            if (feedSkeleton) {
                feedSkeleton.classList.remove('hidden');
                feedSkeleton.setAttribute('aria-hidden', 'false');
            }

            fetch(pageUrl.href, {
                credentials: 'same-origin',
                headers: { Accept: 'text/html' }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Could not load more posts (HTTP ' + response.status + ').');
                }

                if (response.redirected) {
                    throw new Error('Your session may have expired. Refresh the page and sign in again.');
                }

                return response.text();
            }).then(function (html) {
                var nextDocument = new DOMParser().parseFromString(html, 'text/html');
                var newPosts = nextDocument.querySelectorAll('#feed-posts > article.cm-post');
                newPosts.forEach(function (post) {
                    if (post.id && document.getElementById(post.id)) {
                        return;
                    }
                    feedPosts.appendChild(post);
                });

                var nextPageButton = nextDocument.getElementById('load-more-btn');
                if (nextPageButton && nextPageButton.getAttribute('data-next-url')) {
                    loadMoreButton.setAttribute('data-next-url', nextPageButton.getAttribute('data-next-url'));
                    loadMoreContainer.hidden = false;
                    loadMoreButton.disabled = false;
                    loadMoreButton.textContent = loadMoreLabel;
                } else {
                    loadMoreButton.removeAttribute('data-next-url');
                    loadMoreContainer.hidden = true;
                    loadMoreButton.textContent = loadMoreLabel;
                }

                if (window.cmRefreshPostClamps) {
                    window.cmRefreshPostClamps();
                }
            }).catch(function (error) {
                pauseAutoLoad = true;
                loadMoreError.textContent = error.message || 'Could not load more posts. Please try again.';
                loadMoreError.hidden = false;
                loadMoreButton.disabled = false;
                loadMoreButton.textContent = loadMoreLabel;
            }).then(function () {
                feedPageLoading = false;
                loadMoreButton.removeAttribute('aria-busy');

                if (feedSkeleton) {
                    feedSkeleton.classList.add('hidden');
                    feedSkeleton.setAttribute('aria-hidden', 'true');
                }

                if (typeof feedObserver !== 'undefined' && feedObserver && loadMoreButton.getAttribute('data-next-url')) {
                    feedObserver.unobserve(loadMoreContainer);
                    feedObserver.observe(loadMoreContainer);
                }
            });
        }

        loadMoreButton.addEventListener('click', function () {
            pauseAutoLoad = false;
            loadNextFeedPage();
        });

        if ('IntersectionObserver' in window) {
            var feedObserver = new IntersectionObserver(function (entries) {
                if (!pauseAutoLoad && entries.some(function (entry) { return entry.isIntersecting; })) {
                    loadNextFeedPage();
                }
            }, { rootMargin: '250px 0px' });
            feedObserver.observe(loadMoreContainer);
        }
    }

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

    function selectActivityTab(activity, selected, updateUrl) {
        var tabs = activity.querySelectorAll('[data-activity-tab]');
        var panels = activity.querySelectorAll('[data-activity-panel]');
        var validTab = false;

        tabs.forEach(function (tab) {
            if (tab.getAttribute('data-activity-tab') === selected) {
                validTab = true;
            }
        });

        if (!validTab) {
            return false;
        }

        tabs.forEach(function (tab) {
            var isSelected = tab.getAttribute('data-activity-tab') === selected;
            tab.classList.toggle('is-active', isSelected);
            tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            tab.setAttribute('tabindex', isSelected ? '0' : '-1');
        });

        panels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-activity-panel') !== selected;
        });

        if (updateUrl && window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', selected);
            window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
        }

        return true;
    }

    document.querySelectorAll('[data-activity]').forEach(function (activity) {
        var urlTab = activity.getAttribute('data-query-tab');
        var sessionTab = activity.getAttribute('data-session-tab');

        if (!selectActivityTab(activity, urlTab, false) &&
            !selectActivityTab(activity, sessionTab, false)) {
            selectActivityTab(activity, 'notifications', false);
        }
    });

    document.addEventListener('click', function (event) {
        var tab = event.target.closest('[data-activity-tab]');

        if (!tab) {
            return;
        }

        var activity = tab.closest('[data-activity]');

        if (!activity) {
            return;
        }

        selectActivityTab(activity, tab.getAttribute('data-activity-tab'), true);
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

    // Sends a form as multipart FormData. `prepare(data)` can edit the payload first.
    // Resolves to { ok, status, data } or { networkError: true }.
    function sendForm(form, prepare) {
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        var token = reactCsrf();
        var payload = new FormData(form);

        if (token) {
            headers['X-CSRF-TOKEN'] = token;
        }

        if (prepare) {
            prepare(payload);
        }

        return fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: payload
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

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-report-vote]');

        if (!form) {
            return;
        }

        event.preventDefault();

        if (form.dataset.sending === 'true') {
            return;
        }

        var submitter = event.submitter;
        var feedback = document.createElement('p');
        feedback.className = 'text-small text-muted';
        feedback.setAttribute('role', 'status');
        feedback.setAttribute('aria-live', 'polite');
        feedback.textContent = 'Vote recorded.';
        form.hidden = true;
        form.dataset.sending = 'true';
        form.parentNode.insertBefore(feedback, form.nextSibling);

        sendForm(form, function (payload) {
            if (submitter && submitter.name) {
                payload.set(submitter.name, submitter.value);
            }
            payload.set('tab', 'verdicts');
        }).then(function (result) {
            if (result.ok && result.data.status === 'success') {
                form.remove();
                feedback.textContent = result.data.message || 'Vote recorded.';
                return;
            }

            form.hidden = false;
            delete form.dataset.sending;
            feedback.remove();

            if (result.networkError) {
                showReactError('Could not reach server. Your vote was not recorded.');
                return;
            }

            if (result.status === 401) {
                window.location.href = '/';
                return;
            }

            if (result.status === 419) {
                showReactError('Your session expired. Refresh the page and try again.');
                return;
            }

            if (result.status === 429) {
                showReactError('You are going too fast. Try again in a moment.');
                return;
            }

            var errors = result.data.errors;
            var message = result.data.message ||
                (errors && errors.report && errors.report[0]) ||
                'Your vote could not be recorded. Please try again.';
            showReactError(message);
        });
    });

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
        var fields = ['body', 'category', 'condition', 'display_name', 'photos', 'photos.0', 'photos.1', 'photos.2', 'photo', 'parent_id', 'report'];
        var itemKey = errors ? Object.keys(errors).filter(function (key) {
            return key === 'items' || key.indexOf('items.') === 0;
        })[0] : null;
        var message = '';
        var i;

        if (itemKey) {
            fields.splice(1, 0, itemKey);
        }

        if (res.networkError) {
            showInlineError(errorEl, 'Could not reach server.');
            return;
        }

        if (res.status === 401) {
            window.location.href = '/';
            return;
        }

        if (res.status === 410) {
            window.location.reload();
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
        var marketFields = document.querySelector('[data-market-fields]');
        var conditionInput = document.getElementById('condition');
        var marketNameHint = document.getElementById('market-name-hint');
        var marketOn = false;
        var anonBeforeMarket = true;
        var itemsIncomplete;
        var charCount = document.getElementById('char-count');
        var alias = postForm.getAttribute('data-alias');
        var displayName = postForm.getAttribute('data-display-name');
        var postSubmitting = false;
        var photoItems = [];   // { id, blob, url, name, width, height, pending }
        var photosBusy = 0;    // photos still being resized

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
            var itemsMissing = marketOn && itemsIncomplete();
            postBtn.disabled = postSubmitting || photosBusy > 0 || itemsMissing || body.value.trim().length < 3;
        };

        var syncAnon = function () {
            if (marketOn) {
                anonHint.textContent = postForm.getAttribute('data-has-name') === '1'
                    ? 'Listings always show your display name.'
                    : 'Listings show your display name. Add one in Me first.';
                return;
            }

            anonHint.textContent = anon.checked
                ? 'Posting anonymously as ' + alias
                : 'Posting as ' + displayName;
        };

        /* -- Auto-expanding textarea: no cap, the page scrolls instead -- */

        var autoGrow = function () {
            body.style.height = 'auto';
            body.style.height = body.scrollHeight + 'px';
            body.style.overflowY = 'hidden';
        };

        body.addEventListener('input', function () {
            syncPostBtn();
            syncCharCount();
            autoGrow();
        });

        // Width changes re-wrap the text, which changes the height it needs.
        window.addEventListener('resize', autoGrow);

        anon.addEventListener('change', syncAnon);

        /* -- Marketplace: slide-down items + condition, anonymity locked OFF -- */

        var itemsEditor = document.getElementById('items-editor');
        var itemRowsBox = document.getElementById('item-rows');
        var itemTemplate = document.getElementById('item-row-template');
        var addItemBtn = document.getElementById('add-item-btn');
        var itemsTitle = document.getElementById('items-title');
        var anonLabel = anon.closest('.cm-anon-badge');
        var maxItems = parseInt(itemsEditor.getAttribute('data-max-items'), 10) || 5;
        var rowAnimationMs = 280;

        var liveRows = function () {
            return Array.prototype.filter.call(itemRowsBox.querySelectorAll('[data-item-row]'), function (row) {
                return !row.hasAttribute('data-removing');
            });
        };

        var rowPrice = function (row) { return row.querySelector('[data-item-price]'); };
        var rowName = function (row) { return row.querySelector('[data-item-name] input'); };

        itemsIncomplete = function () {
            var rows = liveRows();
            var multi = rows.length > 1;

            return rows.some(function (row) {
                return rowPrice(row).value.trim() === '' || (multi && rowName(row).value.trim() === '');
            });
        };

        var syncItems = function () {
            var rows = liveRows();
            var multi = rows.length > 1;

            itemsEditor.classList.toggle('is-multi', multi);
            itemsTitle.textContent = multi ? 'Items for sale' : 'Price';
            addItemBtn.hidden = rows.length >= maxItems;

            rows.forEach(function (row, index) {
                var name = rowName(row);
                var price = rowPrice(row);
                var nameLabel = row.querySelector('[data-item-name] label');
                var priceLabel = row.querySelector('.cm-item-row__price label');
                var remove = row.querySelector('[data-item-remove]');

                name.name = 'items[' + index + '][label]';
                name.id = 'items-' + index + '-label';
                nameLabel.htmlFor = name.id;
                nameLabel.textContent = 'Item ' + (index + 1) + ' name';

                price.name = 'items[' + index + '][price]';
                price.id = 'items-' + index + '-price';
                priceLabel.htmlFor = price.id;
                priceLabel.textContent = multi ? 'Item ' + (index + 1) + ' price in pesos' : 'Price in pesos';

                remove.setAttribute('aria-label', 'Remove item ' + (index + 1));
                remove.disabled = !marketOn || !multi;
                price.disabled = !marketOn;
                name.disabled = !marketOn || !multi;
            });
        };

        var isMarketplace = function () {
            var checked = postForm.querySelector('input[name="category"]:checked');

            return Boolean(checked && checked.value === 'Marketplace');
        };

        var setMarket = function (on, instant) {
            if (!marketFields || on === marketOn) {
                return;
            }

            marketOn = on;

            if (instant) {
                marketFields.style.transition = 'none';
            }

            marketFields.classList.toggle('is-open', on);
            marketFields.setAttribute('aria-hidden', on ? 'false' : 'true');

            if (instant) {
                void marketFields.offsetWidth;
                marketFields.style.transition = '';
            }

            conditionInput.disabled = !on;
            syncItems();

            if (marketNameHint) {
                marketNameHint.hidden = postForm.getAttribute('data-has-name') === '1' || !on;
            }

            if (on) {
                anonBeforeMarket = anon.checked;
                anon.checked = false;
                anon.disabled = true;
                anonLabel.classList.add('is-locked');

                if (!instant) {
                    window.setTimeout(function () {
                        rowPrice(liveRows()[0]).focus({ preventScroll: true });
                    }, rowAnimationMs);
                }
            } else {
                anon.disabled = false;
                anon.checked = anonBeforeMarket;
                anonLabel.classList.remove('is-locked');
            }

            syncAnon();
            syncPostBtn();
        };

        addItemBtn.addEventListener('click', function () {
            var rows = liveRows();

            if (rows.length >= maxItems) {
                return;
            }

            var wasSingle = rows.length === 1;
            var holder = document.createElement('div');
            holder.innerHTML = itemTemplate.innerHTML.replace(/__i__/g, String(rows.length));

            var slot = holder.firstElementChild;
            slot.classList.add('is-hidden');
            itemRowsBox.appendChild(slot);
            syncItems();
            void slot.offsetWidth;
            slot.classList.remove('is-hidden');

            var target = rowName(wasSingle ? rows[0] : slot);
            window.setTimeout(function () {
                target.focus({ preventScroll: true });
            }, rowAnimationMs);
            syncPostBtn();
        });

        itemRowsBox.addEventListener('click', function (event) {
            var button = event.target.closest('[data-item-remove]');

            if (!button) {
                return;
            }

            var row = button.closest('[data-item-row]');
            var rows = liveRows();

            if (!row || rows.length <= 1) {
                return;
            }

            row.setAttribute('data-removing', '');
            row.querySelectorAll('input, button').forEach(function (element) {
                element.disabled = true;
            });
            row.classList.add('is-hidden');

            syncItems();
            syncPostBtn();

            window.setTimeout(function () {
                if (row.parentNode) {
                    row.parentNode.removeChild(row);
                }
            }, rowAnimationMs);

            (addItemBtn.hidden ? rowPrice(liveRows()[0]) : addItemBtn).focus({ preventScroll: true });
        });

        itemRowsBox.addEventListener('input', function (event) {
            var field = event.target;

            if (field.matches('[data-item-price]')) {
                var digits = field.value.replace(/\D/g, '').slice(0, 7);

                if (digits !== '' && parseInt(digits, 10) > 1000000) {
                    digits = '1000000';
                }

                if (field.value !== digits) {
                    field.value = digits;
                }
            }

            syncPostBtn();
        });

        postForm.querySelectorAll('input[name="category"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var chipContainer = radio.closest('.cm-chips--compose');
                var chipLabel = radio.closest('label');

                if (chipContainer && chipLabel) {
                    var containerRect = chipContainer.getBoundingClientRect();
                    var labelRect = chipLabel.getBoundingClientRect();
                    var targetLeft = chipContainer.scrollLeft
                        + labelRect.left
                        - containerRect.left
                        - (chipContainer.clientWidth - labelRect.width) / 2;
                    var maxScrollLeft = chipContainer.scrollWidth - chipContainer.clientWidth;

                    chipContainer.scrollLeft = Math.max(0, Math.min(targetLeft, maxScrollLeft));
                }

                setMarket(isMarketplace(), false);
            });
        });

        syncItems();
        setMarket(isMarketplace(), true);

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

            sendForm(postForm, function (data) {
                // Replace the raw picks with the resized blobs.
                data.delete('photos[]');
                photoItems.forEach(function (item) {
                    if (item.blob) {
                        data.append('photos[]', item.blob, item.name);
                    }
                });
            }).then(function (res) {
                if (res.ok && res.data.status === 'success') {
                    try { sessionStorage.setItem('cm-new-post', '1'); } catch (e) { /* private mode */ }
                    window.location.href = res.data.redirect || '/feed';
                    return;
                }

                postSubmitting = false;
                clearSending(postBtn);
                syncPostBtn();
                composerFailure(res, postError);
            });
        });

        /* -- Photos: pick up to 3, shrink to 1600px in the browser, preview as a grid -- */

        var photoInput = document.getElementById('photo');
        var photoName = document.getElementById('photo-name');
        var photoLabel = document.querySelector('label[for="photo"]');
        var previewBox = document.getElementById('image-preview-container');

        if (photoInput && previewBox) {
            // Mirrors PostController::store() (type and count). Size is checked AFTER shrinking.
            var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
            var MAX_PHOTOS = 3;
            var MAX_EDGE = 1600;
            var MAX_SOURCE_BYTES = 25 * 1024 * 1024;
            var MAX_UPLOAD_BYTES = 4 * 1024 * 1024;
            var DEFAULT_LABEL = 'Add photos (max 3)';

            var photoError = null;
            var nextPhotoId = 1;

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

            /* ---- resize ---- */

            var loadImage = function (file) {
                return new Promise(function (resolve, reject) {
                    var url = URL.createObjectURL(file);
                    var img = new Image();

                    img.onload = function () { resolve({ img: img, url: url }); };
                    img.onerror = function () {
                        URL.revokeObjectURL(url);
                        reject(new Error('read'));
                    };
                    img.src = url;
                });
            };

            var canvasToBlob = function (canvas, quality) {
                return new Promise(function (resolve) {
                    canvas.toBlob(resolve, 'image/jpeg', quality);
                });
            };

            // Resolves to { blob, name, width, height }. Photos that are already small are sent untouched.
            var preparePhoto = function (file) {
                return loadImage(file).then(function (loaded) {
                    var img = loaded.img;
                    var width = img.naturalWidth;
                    var height = img.naturalHeight;

                    if (width * height > 24000000) {
                        URL.revokeObjectURL(loaded.url);
                        throw new Error('pixels');
                    }

                    var scale = Math.min(1, MAX_EDGE / Math.max(width, height));

                    if (scale === 1 && file.size <= MAX_UPLOAD_BYTES) {
                        URL.revokeObjectURL(loaded.url);
                        return { blob: file, name: file.name, width: width, height: height };
                    }

                    var targetW = Math.max(1, Math.round(width * scale));
                    var targetH = Math.max(1, Math.round(height * scale));
                    var canvas = document.createElement('canvas');

                    canvas.width = targetW;
                    canvas.height = targetH;

                    var ctx = canvas.getContext('2d');

                    if (!ctx) {
                        URL.revokeObjectURL(loaded.url);
                        throw new Error('read');
                    }

                    ctx.fillStyle = '#ffffff'; // transparent PNGs would turn black as JPEG
                    ctx.fillRect(0, 0, targetW, targetH);
                    ctx.imageSmoothingQuality = 'high';
                    ctx.drawImage(img, 0, 0, targetW, targetH);
                    URL.revokeObjectURL(loaded.url);

                    return canvasToBlob(canvas, 0.85).then(function (blob) {
                        if (blob && blob.size > MAX_UPLOAD_BYTES) {
                            return canvasToBlob(canvas, 0.7);
                        }

                        return blob;
                    }).then(function (blob) {
                        canvas.width = 0; // frees the pixel buffer sooner on iOS
                        canvas.height = 0;

                        if (!blob || blob.size > MAX_UPLOAD_BYTES) {
                            throw new Error('size');
                        }

                        return { blob: blob, name: 'photo-' + Date.now() + '-' + nextPhotoId + '.jpg', width: targetW, height: targetH };
                    });
                });
            };

            /* ---- preview grid ---- */

            var render = function () {
                var count = photoItems.length;

                while (previewBox.firstChild) {
                    previewBox.removeChild(previewBox.firstChild);
                }

                if (count === 0) {
                    previewBox.classList.add('hidden');
                } else {
                    var grid = document.createElement('div');
                    grid.className = 'cm-photos cm-photos--' + count;

                    if (count === 1 && photoItems[0].width && photoItems[0].height) {
                        // Same clamp as post-card.blade.php.
                        var ratio = Math.max(0.8, Math.min(1.91, photoItems[0].width / photoItems[0].height));
                        grid.style.setProperty('--cm-ar', ratio.toFixed(3));
                    }

                    photoItems.forEach(function (item, index) {
                        var tile = document.createElement('div');
                        tile.className = 'cm-photos__item' + (item.pending ? ' cm-skeleton' : '');

                        if (!item.pending) {
                            var img = document.createElement('img');
                            img.className = 'cm-photos__img';
                            img.src = item.url;
                            img.alt = 'Selected photo ' + (index + 1) + ' of ' + count;

                            var remove = document.createElement('button');
                            remove.type = 'button';
                            remove.className = 'cm-photos__remove';
                            remove.setAttribute('aria-label', 'Remove photo ' + (index + 1));
                            remove.appendChild(buildCloseIcon());
                            remove.addEventListener('click', function () {
                                removePhoto(item);
                                body.focus();
                            });

                            tile.appendChild(img);
                            tile.appendChild(remove);
                        }

                        grid.appendChild(tile);
                    });

                    previewBox.appendChild(grid);
                    previewBox.classList.remove('hidden');
                }

                photoInput.disabled = count >= MAX_PHOTOS;

                if (photoLabel) {
                    photoLabel.classList.toggle('is-disabled', count >= MAX_PHOTOS);
                }

                if (photoName) {
                    photoName.textContent = count === 0 ? DEFAULT_LABEL : count + ' of ' + MAX_PHOTOS + ' photos';
                }

                autoGrow(); // the textarea gives room back once photos appear
            };

            var removePhoto = function (item) {
                var index = photoItems.indexOf(item);

                if (index === -1) {
                    return;
                }

                if (item.url) {
                    URL.revokeObjectURL(item.url);
                }

                photoItems.splice(index, 1);
                clearPhotoError();
                render();
                syncPostBtn();
            };

            var addPhotos = function (files) {
                var message = '';
                var room = MAX_PHOTOS - photoItems.length;

                clearPhotoError();

                if (files.length > room) {
                    message = 'You can only upload up to ' + MAX_PHOTOS + ' photos.';
                    files = files.slice(0, Math.max(0, room));
                }

                files = files.filter(function (file) {
                    if (ALLOWED_TYPES.indexOf(file.type) === -1) {
                        message = 'Please choose JPG, PNG or WebP photos.';
                        return false;
                    }

                    if (file.size > MAX_SOURCE_BYTES) {
                        message = 'That photo is too large. Choose one under 25 MB.';
                        return false;
                    }

                    return true;
                });

                files.forEach(function (file) {
                    var item = { id: nextPhotoId++, blob: null, url: null, name: '', width: 0, height: 0, pending: true };

                    photoItems.push(item);
                    photosBusy += 1;

                    preparePhoto(file).then(function (result) {
                        if (photoItems.indexOf(item) === -1) {
                            return;
                        }

                        item.blob = result.blob;
                        item.name = result.name;
                        item.width = result.width;
                        item.height = result.height;
                        item.url = URL.createObjectURL(result.blob);
                        item.pending = false;
                    }, function (error) {
                        var index = photoItems.indexOf(item);

                        if (index !== -1) {
                            photoItems.splice(index, 1);
                        }

                        showPhotoError(error && error.message === 'size'
                            ? 'That photo is still over 4 MB after shrinking. Try another one.'
                            : error && error.message === 'pixels'
                                ? 'That photo has too many pixels to process. Try a smaller one.'
                                : 'That file could not be read as an image.');
                    }).then(function () {
                        photosBusy -= 1;
                        render();
                        syncPostBtn();
                    });
                });

                if (message) {
                    showPhotoError(message);
                }

                render();
                syncPostBtn();
            };

            photoInput.addEventListener('change', function () {
                var files = photoInput.files ? Array.prototype.slice.call(photoInput.files) : [];

                // Clear the input so picking the same file again still fires "change".
                // The real list lives in photoItems.
                photoInput.value = '';

                if (files.length > 0) {
                    addPhotos(files);
                }
            });
        }

        /* -- Tap anywhere on the canvas to focus the textarea -- */

        var canvas = postForm.querySelector('.cm-compose--canvas');

        if (canvas) {
            canvas.addEventListener('click', function (event) {
                if (event.target === canvas || event.target.classList.contains('cm-compose__main') || event.target.classList.contains('cm-compose__row')) {
                    body.focus();
                }
            });
        }

        /* -- Lift the action bar above the iOS keyboard -- */

        if (window.visualViewport) {
            var vv = window.visualViewport;

            var syncKeyboard = function () {
                var offset = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
                var open = offset > 80; // ignore URL-bar resize noise

                postForm.style.setProperty('--cm-kb', open ? offset + 'px' : '0px');
                postForm.classList.toggle('is-keyboard', open);
            };

            vv.addEventListener('resize', syncKeyboard);
            vv.addEventListener('scroll', syncKeyboard);
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
        var commentDisplayName = commentForm.getAttribute('data-display-name');
        var parentId = document.getElementById('parent-id');
        var replyNote = document.getElementById('reply-note');
        var replyName = document.getElementById('reply-name');
        var replyCancel = document.getElementById('reply-cancel');
        var commentBody = document.getElementById('comment-body');
        var replyMentionPrefix = '';
        var replyTarget = null;

        var autoGrowComment = function () {
            commentBody.style.height = 'auto';
            commentBody.style.height = commentBody.scrollHeight + 'px';
            commentBody.style.overflowY = 'hidden';
        };

        var alignReplyTarget = function (behavior) {
            if (!replyTarget) {
                return;
            }

            var top = replyTarget.getBoundingClientRect().top;
            var targetTop = 16;

            if (Math.abs(top - targetTop) > 8) {
                window.scrollBy({
                    top: top - targetTop,
                    behavior: behavior || 'auto'
                });
            }
        };

        commentBody.addEventListener('input', autoGrowComment);
        window.addEventListener('resize', autoGrowComment);
        autoGrowComment();

        if (window.visualViewport) {
            var commentViewport = window.visualViewport;
            var syncCommentKeyboard = function () {
                var keyboardOffset = Math.max(0, window.innerHeight - commentViewport.height - commentViewport.offsetTop);
                var keyboardOpen = keyboardOffset > 80;

                commentForm.style.setProperty('--cm-comment-kb', keyboardOpen ? keyboardOffset + 'px' : '0px');

                if (keyboardOpen) {
                    window.requestAnimationFrame(function () {
                        alignReplyTarget('auto');
                    });
                }
            };

            commentViewport.addEventListener('resize', syncCommentKeyboard);
            commentViewport.addEventListener('scroll', syncCommentKeyboard);
        }

        toggle.addEventListener('click', function () {
            var on = anonField.value !== '1';

            anonField.value = on ? '1' : '0';
            toggle.classList.toggle('is-on', on);
            toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
            hint.textContent = on
                ? 'Commenting anonymously as ' + commentAlias
                : 'Commenting as ' + commentDisplayName;
        });

        hint.textContent = 'Commenting anonymously as ' + commentAlias;

        var clearReply = function () {
            if (replyMentionPrefix && commentBody.value.indexOf(replyMentionPrefix) === 0) {
                commentBody.value = commentBody.value.slice(replyMentionPrefix.length);
                autoGrowComment();
            }

            replyMentionPrefix = '';
            replyTarget = null;
            parentId.value = '';
            replyNote.hidden = true;
            commentBody.placeholder = 'Write a comment\u2026';
        };

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-reply-to]');

            if (!button) {
                return;
            }

            var existingDraft = commentBody.value;

            if (replyMentionPrefix && existingDraft.indexOf(replyMentionPrefix) === 0) {
                existingDraft = existingDraft.slice(replyMentionPrefix.length);
            }

            var alias = button.getAttribute('data-reply-name');
            replyMentionPrefix = button.getAttribute('data-reply-self') === '1' ? '' : '@' + alias + ' ';
            commentBody.value = replyMentionPrefix + existingDraft;
            parentId.value = button.getAttribute('data-reply-to');
            replyTarget = document.getElementById('comment-' + parentId.value);
            replyName.textContent = 'Replying to ' + alias;
            replyNote.hidden = false;
            commentBody.placeholder = 'Write a reply\u2026';
            autoGrowComment();
            commentBody.focus({ preventScroll: true });

            if (replyMentionPrefix) {
                commentBody.setSelectionRange(commentBody.value.length, commentBody.value.length);
            }

            window.requestAnimationFrame(function () {
                alignReplyTarget('smooth');
            });
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

            var actionsLeft = parentEl.querySelector('.cm-comment__actions-left');
            (actionsLeft || wrapper).appendChild(toggleBtn);
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
                var addedWrap = added.closest('.cm-comment-wrapper');

                if (addedWrap) {
                    addedWrap.classList.add('cm-animate-in');
                }

                added.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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
            commentSubmit.disabled = true;

            sendForm(commentForm).then(function (res) {
                commentSending = false;
                commentSubmit.disabled = false;

                if (res.ok && res.data.status === 'success') {
                    insertComment(res.data);
                    commentBody.value = '';
                    autoGrowComment();
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

    function readReactionCount(el) {
        var count = el.querySelector('.cm-stat__count');

        return count ? (parseInt(count.textContent, 10) || 0) : 0;
    }

    // Restarts the pop animation even if it is already running.
    function popReaction(el) {
        el.classList.remove('is-popping');
        void el.offsetWidth; // force reflow so the animation restarts

        el.classList.add('is-popping');
        el.addEventListener('animationend', function done() {
            el.classList.remove('is-popping');
            el.removeEventListener('animationend', done);
        });
    }

    // Updates EVERY matching button on the page (the same post can appear
    // twice, e.g. reports list + feed), not just the one that was tapped.
    // Used for the optimistic update, the server confirmation and the rollback.
    function applyReaction(button, data) {
        var kind = button.hasAttribute('data-post-id') ? 'post' : 'comment';
        var id = button.getAttribute('data-' + kind + '-id');
        var type = button.getAttribute('data-react');
        var selector = '[data-react="' + type + '"][data-' + kind + '-id="' + id + '"]';

        document.querySelectorAll(selector).forEach(function (el) {
            var count = el.querySelector('.cm-stat__count');
            var wasActive = el.classList.contains('is-active');

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

            // Pop only on off -> on, so a server confirmation or a rollback never re-pops.
            if (data.is_active && !wasActive) {
                popReaction(el);
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

        // What to restore if the request fails.
        var wasActive = button.classList.contains('is-active');
        var previous = { is_active: wasActive, new_count: readReactionCount(button) };

        // What we show right now, before the server answers.
        var optimistic = {
            is_active: !wasActive,
            new_count: Math.max(0, previous.new_count + (wasActive ? -1 : 1))
        };

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
        applyReaction(button, optimistic);

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
                // Snap to the server's real count (covers two people reacting at once).
                applyReaction(button, res.data);
                return;
            }

            applyReaction(button, previous);
            handleReactFailure(res);
        }, function () {
            applyReaction(button, previous);
            showReactError('Could not reach server.');
        }).then(function () {
            button.classList.remove('is-loading');
            button.removeAttribute('aria-busy');
        });
    });

    /* ---------- Bookmarks (save ribbon) ---------- */

    function popBookmark(el) {
        el.classList.remove('is-popping');
        void el.offsetWidth;
        el.classList.add('is-popping');
        clearTimeout(el._popTimer);
        el._popTimer = setTimeout(function () {
            el.classList.remove('is-popping');
        }, 520);
    }

    function bookmarkButtons(postId) {
        return document.querySelectorAll('[data-bookmark][data-post-id="' + postId + '"]');
    }

    function applyBookmark(postId, active) {
        bookmarkButtons(postId).forEach(function (el) {
            var wasActive = el.classList.contains('is-active');
            var label = active ? 'Remove from saved' : 'Save post';

            el.classList.toggle('is-active', active);
            el.setAttribute('aria-pressed', active ? 'true' : 'false');
            el.setAttribute('aria-label', label);
            el.title = label;

            if (active && !wasActive) {
                popBookmark(el);
            }
        });
    }

    function setBookmarkLoading(postId, loading) {
        bookmarkButtons(postId).forEach(function (el) {
            el.classList.toggle('is-loading', loading);
            el.setAttribute('aria-busy', loading ? 'true' : 'false');

            if (!loading) {
                el.removeAttribute('aria-busy');
            }
        });
    }

    function showSavedEmptyIfDone() {
        var empty = document.querySelector('[data-saved-empty]');

        if (empty && document.querySelectorAll('[data-saved-item]').length === 0) {
            empty.hidden = false;
            empty.classList.add('cm-animate-fade');
        }
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-bookmark]');

        if (!button || !button.form) {
            return;
        }

        event.preventDefault();

        if (button.classList.contains('is-loading')) {
            return;
        }

        var postId = button.getAttribute('data-post-id');
        var wasActive = button.classList.contains('is-active');
        var item = wasActive ? button.closest('[data-saved-item]') : null;
        var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        var token = reactCsrf();

        if (token) {
            headers['X-CSRF-TOKEN'] = token;
        }

        setBookmarkLoading(postId, true);
        applyBookmark(postId, !wasActive);

        if (!wasActive && navigator.vibrate && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            navigator.vibrate(8);
        }

        if (item) {
            item.classList.add('is-leaving');
        }

        var request = fetch(button.form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, data: data || {} };
            }, function () {
                return { ok: response.ok, status: response.status, data: {} };
            });
        }, function () {
            return { networkError: true };
        });
        var settle = new Promise(function (resolve) {
            window.setTimeout(resolve, item ? 280 : 0);
        });

        Promise.all([request, settle]).then(function (results) {
            var res = results[0];
            setBookmarkLoading(postId, false);

            if (res.ok && res.data && res.data.status === 'success') {
                applyBookmark(postId, Boolean(res.data.is_active));

                if (item) {
                    if (res.data.is_active) {
                        item.classList.remove('is-leaving');
                    } else if (item.parentNode) {
                        item.parentNode.removeChild(item);
                        showSavedEmptyIfDone();
                    }
                }

                return;
            }

            applyBookmark(postId, wasActive);

            if (item) {
                item.classList.remove('is-leaving');
            }

            if (res.networkError) {
                showReactError('Could not reach server.');
            } else {
                handleReactFailure(res);
            }
        });
    });

    /* ---------- Report bottom sheet ---------- */

    var sheet = document.getElementById('report-sheet');

    if (sheet) {
        var reportForm = document.getElementById('report-form');
        var reportTitle = document.getElementById('report-title');
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        var sheetCloseTimer = null;

        var finishCloseSheet = function () {
            clearTimeout(sheetCloseTimer);
            sheetCloseTimer = null;
            sheet.classList.remove('is-closing');
            sheet.hidden = true;
            reportForm.reset();
        };

        // Plays the slide-down (240ms, matches the CSS), then hides the sheet.
        var closeSheet = function () {
            if (sheet.hidden || sheetCloseTimer !== null) {
                return;
            }

            if (reduceMotion.matches) {
                finishCloseSheet();
                return;
            }

            sheet.classList.add('is-closing');
            sheetCloseTimer = setTimeout(finishCloseSheet, 240);
        };

        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-report-open]');

            if (!button) {
                return;
            }

            event.preventDefault();
            clearTimeout(sheetCloseTimer);
            sheetCloseTimer = null;
            sheet.classList.remove('is-closing');

            reportForm.action = button.getAttribute('data-report-action');
            reportTitle.textContent = 'Report this ' + button.getAttribute('data-report-kind');
            sheet.hidden = false;
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

    function syncSeeMore(wrap) {
        var text = wrap.querySelector('.cm-clamp');
        var button = wrap.querySelector('[data-see-more]');

        if (!text || !button) {
            return;
        }

        button.hidden = !(text.scrollHeight > text.clientHeight);
    }

    function syncAllSeeMore() {
        document.querySelectorAll('[data-clamp-wrap]').forEach(syncSeeMore);
    }

    window.cmRefreshPostClamps = syncAllSeeMore;

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

    /* ---------- Feed polish: photo lightbox (swipe through a post's photos) ---------- */

    var lightbox = document.getElementById('lightbox');

    if (lightbox) {
        var lightboxImg = document.getElementById('lightbox-img');
        var lightboxClose = document.getElementById('lightbox-close');
        var lightboxCount = document.getElementById('lightbox-count');
        var lightboxPrev = document.getElementById('lightbox-prev');
        var lightboxNext = document.getElementById('lightbox-next');
        var lightboxOpener = null;
        var lightboxItems = [];
        var lightboxIndex = 0;
        var lightboxMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        var swipe = { x: 0, dx: 0, active: false };
        var lightboxAnimation = null;

        var lightboxSrc = function (img) {
            return img.getAttribute('data-lightbox-src') || img.src;
        };

        // direction: +1 = came from the right (next), -1 = came from the left (previous), none = just opened.
        var showLightbox = function (index, direction) {
            var img = lightboxItems[index];
            var multi = lightboxItems.length > 1;

            lightboxIndex = index;
            lightboxImg.style.transition = 'none';
            lightboxImg.style.transform = '';
            lightboxImg.src = lightboxSrc(img);
            lightboxImg.alt = img.alt || '';

            lightboxCount.hidden = !multi;
            lightboxCount.textContent = (index + 1) + ' / ' + lightboxItems.length;
            lightboxPrev.hidden = !multi || index === 0;
            lightboxNext.hidden = !multi || index === lightboxItems.length - 1;

            if (lightboxAnimation) {
                lightboxAnimation.cancel();
                lightboxAnimation = null;
            }

            if (direction && lightboxImg.animate && !lightboxMotion.matches) {
                lightboxAnimation = lightboxImg.animate([
                    { transform: 'translate3d(' + (direction * 48) + 'px, 0, 0)', opacity: 0 },
                    { transform: 'translate3d(0, 0, 0)', opacity: 1 }
                ], { duration: 240, easing: 'cubic-bezier(0.32, 0.72, 0, 1)' });
            }

            // Warm the neighbours so the next swipe is instant.
            [index - 1, index + 1].forEach(function (i) {
                if (lightboxItems[i]) {
                    new Image().src = lightboxSrc(lightboxItems[i]);
                }
            });
        };

        var step = function (delta) {
            var next = lightboxIndex + delta;

            if (next >= 0 && next < lightboxItems.length) {
                showLightbox(next, delta);
            }
        };

        var openLightbox = function (img) {
            var grid = img.closest('.cm-photos');

            lightboxOpener = img;
            lightboxItems = grid ? Array.prototype.slice.call(grid.querySelectorAll('.cm-post__img')) : [img];
            lightbox.hidden = false;
            document.body.classList.add('cm-no-scroll');
            showLightbox(Math.max(0, lightboxItems.indexOf(img)), 0);
            lightboxClose.focus();
        };

        var closeLightbox = function () {
            lightbox.hidden = true;
            lightboxImg.removeAttribute('src');
            lightboxItems = [];
            swipe.active = false;
            if (lightboxAnimation) {
                lightboxAnimation.cancel();
                lightboxAnimation = null;
            }
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
            if (event.target.closest('[data-lightbox-nav]')) {
                return;
            }

            if (event.target === lightbox) {
                closeLightbox();
            }
        });

        lightboxClose.addEventListener('click', closeLightbox);
        lightboxPrev.addEventListener('click', function () { step(-1); });
        lightboxNext.addEventListener('click', function () { step(1); });

        document.addEventListener('keydown', function (event) {
            if (lightbox.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                closeLightbox();
            } else if (event.key === 'ArrowLeft') {
                step(-1);
            } else if (event.key === 'ArrowRight') {
                step(1);
            }
        });

        /* ---- swipe: the photo follows the finger, then snaps or advances ---- */

        lightbox.addEventListener('touchstart', function (event) {
            if (event.touches.length !== 1 || lightboxItems.length < 2) {
                swipe.active = false;
                return;
            }

            swipe.x = event.touches[0].clientX;
            swipe.dx = 0;
            swipe.active = true;
            lightboxImg.style.transition = 'none';
        }, { passive: true });

        lightbox.addEventListener('touchmove', function (event) {
            if (!swipe.active) {
                return;
            }

            var dx = event.touches[0].clientX - swipe.x;
            var atEdge = (dx > 0 && lightboxIndex === 0) || (dx < 0 && lightboxIndex === lightboxItems.length - 1);

            swipe.dx = dx;
            lightboxImg.style.transform = 'translate3d(' + (atEdge ? dx * 0.3 : dx) + 'px, 0, 0)'; // rubber-band at the ends
        }, { passive: true });

        var endSwipe = function () {
            if (!swipe.active) {
                return;
            }

            var dx = swipe.dx;

            swipe.active = false;
            swipe.dx = 0;

            if (dx <= -60 && lightboxIndex < lightboxItems.length - 1) {
                step(1);
                return;
            }

            if (dx >= 60 && lightboxIndex > 0) {
                step(-1);
                return;
            }

            lightboxImg.style.transition = 'transform 220ms cubic-bezier(0.32, 0.72, 0, 1)';
            lightboxImg.style.transform = '';
        };

        lightbox.addEventListener('touchend', endSwipe, { passive: true });
        lightbox.addEventListener('touchcancel', endSwipe, { passive: true });
    }

    /* ---------- Account deletion confirmation ---------- */

    var deleteModal = document.querySelector('[data-delete-modal]');

    if (deleteModal) {
        var deleteInput = deleteModal.querySelector('[data-delete-input]');
        var deleteSubmit = deleteModal.querySelector('[data-delete-submit]');
        var deleteOpener = null;

        var syncDeleteSubmit = function () {
            deleteSubmit.disabled = deleteInput.value.trim() !== 'DELETE';
        };

        var openDeleteModal = function (opener) {
            deleteOpener = opener || null;
            deleteModal.hidden = false;
            document.body.classList.add('cm-no-scroll');
            syncDeleteSubmit();
            deleteInput.focus();
        };

        var closeDeleteModal = function () {
            deleteModal.hidden = true;
            document.body.classList.remove('cm-no-scroll');
            deleteInput.value = '';
            syncDeleteSubmit();

            if (deleteOpener) {
                deleteOpener.focus();
            }
        };

        document.querySelectorAll('[data-delete-open]').forEach(function (button) {
            button.addEventListener('click', function () { openDeleteModal(button); });
        });

        deleteModal.querySelectorAll('[data-delete-cancel]').forEach(function (button) {
            button.addEventListener('click', closeDeleteModal);
        });

        deleteInput.addEventListener('input', syncDeleteSubmit);

        deleteModal.querySelector('form').addEventListener('submit', function (event) {
            if (deleteInput.value.trim() !== 'DELETE') {
                event.preventDefault();
                return;
            }

            deleteSubmit.textContent = 'Deleting\u2026';
            deleteSubmit.disabled = true;
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !deleteModal.hidden) {
                closeDeleteModal();
            }
        });

        if (!deleteModal.hidden) {
            openDeleteModal(null);
        }
    }

    /* ---------- Feed: a just-published post slides down from the top ---------- */

    (function () {
        var list = document.getElementById('feed-posts');
        var flagged = false;

        try {
            flagged = sessionStorage.getItem('cm-new-post') === '1';
            sessionStorage.removeItem('cm-new-post');
        } catch (e) { /* storage unavailable */ }

        if (!list || !flagged || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        var first = list.firstElementChild;

        if (!first || !first.classList.contains('cm-post')) {
            return;
        }

        var second = first.nextElementSibling;
        var gap = second ? (parseFloat(window.getComputedStyle(second).marginTop) || 0) : 0;

        list.style.setProperty('--cm-arrive-h', (first.offsetHeight + gap) + 'px');
        list.classList.add('is-arriving');
        first.classList.add('cm-post--arrive');
        window.scrollTo(0, 0);

        window.setTimeout(function () {
            list.classList.remove('is-arriving');
            first.classList.remove('cm-post--arrive');
            list.style.removeProperty('--cm-arrive-h');
        }, 900);
    })();

    /* ---------- Post deletion: collapse like a native iOS list row ---------- */

    function collapseAway(row, done) {
        var finished = false;
        var finish = function () {
            if (finished) {
                return;
            }

            finished = true;

            if (row.parentNode) {
                row.parentNode.removeChild(row);
            }

            if (done) {
                done();
            }
        };

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            finish();
            return;
        }

        row.style.height = row.getBoundingClientRect().height + 'px';
        row.style.overflow = 'hidden';
        void row.offsetHeight;

        row.classList.remove('is-deleting');
        row.classList.add('cm-row--leaving');
        row.style.height = '0px';

        row.addEventListener('transitionend', function (event) {
            if (event.target === row && event.propertyName === 'height') {
                finish();
            }
        });
        window.setTimeout(finish, 520);
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (event.defaultPrevented || !(form instanceof HTMLFormElement)) {
            return;
        }

        var override = form.querySelector('input[name="_method"]');
        var path;

        try {
            path = new URL(form.action, window.location.href).pathname;
        } catch (error) {
            return;
        }

        if (!override || override.value.toUpperCase() !== 'DELETE' || !/^\/post\/\d+$/.test(path)) {
            return;
        }

        var card = form.closest('.cm-post');

        if (!card || /^\/post\/\d+$/.test(window.location.pathname)) {
            return;
        }

        event.preventDefault();
        closeMoreMenus();

        var row = card.closest('[data-saved-item]') || card;
        var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        var token = reactCsrf();

        if (token) {
            headers['X-CSRF-TOKEN'] = token;
        }

        card.classList.add('is-deleting');

        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: new FormData(form)
        }).then(function (response) {
            if (response.status === 401) {
                window.location.href = '/';
                return;
            }

            if (!response.ok) {
                throw new Error('Could not delete that post. Try again.');
            }

            collapseAway(row);
        }).catch(function (error) {
            card.classList.remove('is-deleting');
            showReactError(error.message === 'Failed to fetch' ? 'Could not reach server.' : error.message);
        });
    });
})();
