/* Registers the service worker (offline copy) and keeps the browser's install prompt for later. */
(function () {
  window.ACF_PWA = { canInstall: false, install: function () { return Promise.resolve(false); }, offlineReady: false };
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("sw.js").then(function (reg) {
        function ready() { window.ACF_PWA.offlineReady = true; document.documentElement.setAttribute("data-offline-ready", "1"); }
        if (reg.active) ready();
        navigator.serviceWorker.ready.then(ready);
      }).catch(function () {});
    });
  }
  var deferred = null;
  window.addEventListener("beforeinstallprompt", function (ev) {
    ev.preventDefault(); deferred = ev; window.ACF_PWA.canInstall = true;
    window.ACF_PWA.install = function () { var d = deferred; deferred = null; window.ACF_PWA.canInstall = false; d.prompt(); return d.userChoice.then(function (c) { return c.outcome === "accepted"; }); };
  });
})();
