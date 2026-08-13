'use strict';

var CACHE_NAME = 'assist-platform-static-v2';
var STATIC_ASSETS = [
    '/assets/css/app.css',
    '/assets/js/app.js'
];

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE_NAME).then(function (cache) { return cache.addAll(STATIC_ASSETS); }));
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (keys) {
        return Promise.all(keys.filter(function (key) { return key !== CACHE_NAME; }).map(function (key) { return caches.delete(key); }));
    }).then(function () {
        return self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    }).then(function (clients) {
        clients.forEach(function (client) {
            if (client.url && client.url.indexOf(self.location.origin) === 0) {
                client.navigate(client.url);
            }
        });
    }));
    self.clients.claim();
});

self.addEventListener('fetch', function (event) {
    var request = event.request;
    if (request.method !== 'GET') { return; }
    var url = new URL(request.url);
    if (url.origin !== self.location.origin || !url.pathname.startsWith('/assets/')) { return; }
    event.respondWith(fetch(request).then(function (response) {
            if (response && response.ok) {
                var copy = response.clone();
                caches.open(CACHE_NAME).then(function (cache) { cache.put(request, copy); });
            }
            return response;
        }).catch(function () { return caches.match(request); }));
});
