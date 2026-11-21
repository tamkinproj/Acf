/* Service worker: the whole staff app is stored on the device, so it opens and works with no connection.
   Change VERSION whenever any file of the app changes; old copies are removed on the next visit. */
var VERSION = "insania-app-v8";
var FILES = [
  "./", "index.html", "login.html", "notifications.html", "departments.html", "education.html", "edu-teacher.html", "edu-place.html", "edu-curriculum.html", "edu-new.html", "newgroup.html", "chatinfo.html", "add.html", "welcome.html", "dashboard.html", "people.html", "person.html", "payments.html", "projects.html", "reports.html", "settings.html", "family.html", "users.html", "audit.html", "system.html", "setup.html", "updates.html", "signer.html", "website.html", "applications.html", "application.html", "chat.html", "room.html", "repair.html",
  "assets/style.css", "assets/legacy.css", "assets/boot.js", "assets/nozoom.js", "assets/api.js", "assets/gate.js", "assets/signer.js", "assets/signer-gate.js", "assets/app.js", "assets/orphan-schema.js", "assets/orphan.js",
  "assets/pages/dashboard.js", "assets/pages/login.js", "assets/pages/notifications.js", "assets/pages/edu-kit.js", "assets/pages/departments.js", "assets/pages/education.js", "assets/pages/edu-teacher.js", "assets/pages/edu-place.js", "assets/pages/edu-curriculum.js", "assets/pages/edu-new.js", "assets/pages/newgroup.js", "assets/pages/chatinfo.js", "assets/pages/add.js", "assets/pages/orphan-tools.js", "assets/pages/welcome.js", "assets/pages/payments.js", "assets/pages/people.js",
  "assets/pages/person.js", "assets/pages/projects.js", "assets/pages/reports.js", "assets/pages/settings.js", "assets/pages/family.js", "assets/pages/users.js", "assets/pages/audit.js", "assets/pages/system.js", "assets/pages/setup.js", "assets/pages/updates.js", "assets/pages/website.js", "assets/pages/applications.js", "assets/pages/application.js", "assets/pages/chat.js", "assets/pages/room.js",
  "assets/logo.jpg", "assets/pwa.js", "assets/icon-192.png", "assets/icon-512.png", "assets/icon-maskable-512.png", "manifest.webmanifest"
];

self.addEventListener("install", function (e) {
  // Every file is fetched fresh from the server ("reload"), never from the browser's own cache, so a new version is never mixed
  // with old files. One file that fails does not stop the update: the old copy must never stay stuck on the phone.
  e.waitUntil(caches.open(VERSION).then(function (c) {
    return Promise.all(FILES.map(function (f) {
      return fetch(new Request(f, { cache: "reload" })).then(function (res) { if (res && res.ok) return c.put(f, res); }).catch(function () {});
    }));
  }).then(function () { return self.skipWaiting(); }));
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

  // The app's own files come from the stored copy of THIS release (instant, works offline). They are never refreshed one by one:
  // a new release brings a new sw.js (its VERSION is the release id), which stores the whole new set at once, so old and new never mix.
  e.respondWith(caches.open(VERSION).then(function (cache) {
    return cache.match(req, { ignoreSearch: true }).then(function (hit) {
      if (hit) return hit;
      return fetch(req).catch(function () {
        // Offline and not stored: pages fall back to the sign-in page, anything else fails quietly.
        return req.mode === "navigate" ? cache.match("login.html") : Response.error();
      });
    });
  }));
});
