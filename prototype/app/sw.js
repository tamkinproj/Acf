/* Service worker: the whole staff app is stored on the device, so it opens and works with no connection.
   Change VERSION whenever any file of the app changes; old copies are removed on the next visit. */
var VERSION = "insania-app-v2";
var FILES = [
  "./", "index.html", "login.html", "dashboard.html", "people.html", "person.html", "payments.html", "projects.html", "reports.html", "settings.html", "family.html", "users.html", "audit.html", "system.html",
  "assets/style.css", "assets/api.js", "assets/app.js",
  "assets/pages/dashboard.js", "assets/pages/login.js", "assets/pages/payments.js", "assets/pages/people.js",
  "assets/pages/person.js", "assets/pages/projects.js", "assets/pages/reports.js", "assets/pages/settings.js", "assets/pages/family.js", "assets/pages/users.js", "assets/pages/audit.js", "assets/pages/system.js",
  "assets/logo.jpg", "assets/pwa.js", "assets/icon-192.png", "assets/icon-512.png", "assets/icon-maskable-512.png", "manifest.webmanifest"
];

self.addEventListener("install", function (e) {
  e.waitUntil(caches.open(VERSION).then(function (c) { return c.addAll(FILES); }).then(function () { return self.skipWaiting(); }));
});

self.addEventListener("activate", function (e) {
  e.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k !== VERSION; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

self.addEventListener("fetch", function (e) {
  var req = e.request;
  if (req.method !== "GET") return;
  var url = new URL(req.url);
  if (url.origin !== location.origin) return; // never touch other sites
  if (url.pathname.indexOf("/server/") > -1) return; // the API is never stored here: api.js keeps its own per-account copy

  // Stored copy first (instant, works offline); fetch in the background to keep it fresh.
  e.respondWith(caches.open(VERSION).then(function (cache) {
    return cache.match(req, { ignoreSearch: true }).then(function (hit) {
      var net = fetch(req).then(function (res) {
        if (res && res.ok) cache.put(req, res.clone());
        return res;
      }).catch(function () { return null; });
      if (hit) { e.waitUntil(net); return hit; }
      return net.then(function (res) {
        if (res) return res;
        // Offline and not stored: pages fall back to the sign-in page, anything else fails quietly.
        return req.mode === "navigate" ? cache.match("login.html") : Response.error();
      });
    });
  }));
});
