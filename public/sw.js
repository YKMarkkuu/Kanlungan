/* Kanlungan service worker.
   Caches ONLY static assets (the app shell). It never intercepts navigations,
   HTML pages, JSON responses, or /uploads/: those contain private,
   session-bound, or deletable content (anonymous posts, avatars, removed photos). */

var CACHE = 'kanlungan-shell-v3';

var SHELL = [
    '/css/base.css',
    '/css/components.css',
    '/css/community.css',
    '/js/community.js',
    '/js/login.js',
    '/js/register.js',
    '/js/onboarding.js',
    '/js/landing.js',
    '/images/icon-192.png',
    '/images/icon-512.png'
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (cache) {
            // Add one by one so a single missing file (e.g. icons not added yet)
            // does not make the whole install fail.
            return Promise.all(SHELL.map(function (url) {
                return cache.add(url).catch(function () { /* skip */ });
            }));
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (key) {
                return key !== CACHE;
            }).map(function (key) {
                return caches.delete(key);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

function isStaticAsset(url) {
    return url.pathname.indexOf('/css/') === 0 ||
        url.pathname.indexOf('/js/') === 0 ||
        url.pathname.indexOf('/images/') === 0;
}

self.addEventListener('fetch', function (event) {
    var request = event.request;

    // Let the browser handle back/forward and other document navigations directly.
    if (request.mode === 'navigate') {
        return;
    }

    // Never touch writes (likes, comments, logout, OTP...).
    if (request.method !== 'GET') {
        return;
    }

    var url = new URL(request.url);

    // Same-origin only. Leaves cdnjs (cropper) and everything else to the browser.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Static assets: serve from cache, refresh in the background.
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.open(CACHE).then(function (cache) {
                return cache.match(request).then(function (cached) {
                    var network = fetch(request).then(function (response) {
                        if (response && response.ok) {
                            cache.put(request, response.clone());
                        }
                        return response;
                    }).catch(function () {
                        return cached;
                    });

                    return cached || network;
                });
            })
        );
        return;
    }
});
