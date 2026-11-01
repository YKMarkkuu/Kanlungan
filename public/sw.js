/* Kanlungan service worker.
   Caches ONLY static assets (the app shell). It never caches HTML pages,
   JSON responses, or /uploads/: those contain private, session-bound, or
   deletable content (anonymous posts, avatars, removed photos). */

var CACHE = 'kanlungan-shell-v1';

var SHELL = [
    '/css/base.css',
    '/css/components.css',
    '/css/community.css',
    '/js/community.js',
    '/js/login.js',
    '/js/register.js',
    '/images/icon-192.png',
    '/images/icon-512.png'
];

var OFFLINE_HTML =
    '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">' +
    '<meta name="viewport" content="width=device-width, initial-scale=1">' +
    '<meta name="theme-color" content="#1a6b4a"><title>Offline · Kanlungan</title></head>' +
    '<body style="margin:0;background:#f5f3ee;color:#1e1e1e;font-family:system-ui,Segoe UI,Tahoma,sans-serif;">' +
    '<div style="max-width:420px;margin:0 auto;padding:64px 16px;">' +
    '<h1 style="font-size:22px;margin:0 0 8px;">You\u2019re offline</h1>' +
    '<p style="margin:0 0 16px;color:#6a6a6a;">Kanlungan needs a connection to load posts. Check your internet and try again.</p>' +
    '<button onclick="location.reload()" style="min-height:44px;padding:10px 16px;border:1px solid #1a6b4a;' +
    'border-radius:8px;background:#1a6b4a;color:#fff;font:inherit;font-weight:600;">Try again</button>' +
    '</div></body></html>';

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

    // Page loads: always network (fresh, session-aware). Offline notice only on failure.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(function () {
                return new Response(OFFLINE_HTML, {
                    status: 503,
                    headers: { 'Content-Type': 'text/html; charset=utf-8' }
                });
            })
        );
    }
});
