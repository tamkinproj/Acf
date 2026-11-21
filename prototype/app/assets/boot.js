/* Loaded first on every page of the staff app. It makes sure the app never shows a blank page:
   if a file of the app fails to load or the app stops while starting (for example because the phone mixed
   an old saved copy with new files), it shows what happened and a button that loads a fresh copy. */
(function () {
  var failed = [], crashed = [], shown = false;

  window.addEventListener("error", function (e) {
    var t = e.target;
    if (t && t !== window && (t.src || t.href)) { failed.push(String(t.src || t.href).replace(location.origin, "")); return; }
    if (e.message && e.message !== "Script error." && e.message !== "Script error") crashed.push(e.message);
  }, true);

  /* Removes the copy of the app saved on this phone (not the sign-in, not changes waiting to be sent), then reloads. */
  function fresh(then) {
    var jobs = [];
    try {
      if (navigator.serviceWorker && navigator.serviceWorker.getRegistrations) {
        jobs.push(navigator.serviceWorker.getRegistrations().then(function (rs) { return Promise.all(rs.map(function (r) { return r.unregister(); })); }));
      }
    } catch (e) {}
    try {
      if (window.caches && caches.keys) jobs.push(caches.keys().then(function (ks) { return Promise.all(ks.map(function (k) { return caches.delete(k); })); }));
    } catch (e) {}
    var done = function () { if (then) then(); else location.replace(location.pathname + "?fresh=" + Date.now()); };
    Promise.all(jobs).then(done, done);
  }
  window.ACF_FRESH = fresh;

  function ar() { try { return localStorage.getItem("acf_lang") === "ar"; } catch (e) { return false; } }

  function show() {
    if (shown) return; shown = true;
    var a = ar(), box = document.createElement("div");
    var T = a ? { h: "لم يكتمل تحميل التطبيق", p: "غالبًا يحتفظ هذا الهاتف بنسخة قديمة من التطبيق. اضغط الزر لتحميل نسخة جديدة. تبقى بيانات دخولك والتغييرات غير المرسلة.", b: "حمّل نسخة جديدة", l: "العودة إلى تسجيل الدخول", d: "إن تكرر ذلك، أرسل لقطة شاشة لهذه الرسالة." }
                : { h: "The app did not load completely", p: "This phone probably kept an old copy of the app. Press the button to load a fresh copy. Your sign-in and any changes not sent yet are kept.", b: "Load a fresh copy", l: "Back to sign in", d: "If this keeps happening, send a screenshot of this message." };
    var details = failed.slice(0, 4).map(function (f) { return "✗ " + f; }).concat(crashed.slice(0, 2).map(function (m) { return "! " + m; })).join("\n");
    box.setAttribute("role", "alert");
    box.style.cssText = "position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem;background:rgba(255,255,255,.96);font:16px/1.5 system-ui,-apple-system,sans-serif;color:#17300d";
    box.innerHTML = '<div style="max-width:26rem;width:100%;border:1px solid #e3e8df;border-radius:1.2rem;padding:1.4rem;background:#fff;box-shadow:0 12px 32px rgba(23,48,13,.14)" dir="' + (a ? "rtl" : "ltr") + '">' +
      '<h2 style="margin:0 0 .5rem;font-size:1.2rem"></h2><p style="margin:0 0 1rem;color:#4b5a45"></p>' +
      '<button type="button" style="width:100%;padding:.85rem;border:0;border-radius:999px;background:#8cc63f;color:#111;font-weight:700;font-size:1rem"></button>' +
      '<a href="login.html" style="display:block;text-align:center;margin-top:.8rem;color:#2f6b1c"></a>' +
      '<pre dir="ltr" style="white-space:pre-wrap;word-break:break-all;font-size:.72rem;color:#7a8574;margin:1rem 0 0"></pre><p style="font-size:.8rem;color:#7a8574;margin:.5rem 0 0"></p></div>';
    box.querySelector("h2").textContent = T.h; box.querySelector("p").textContent = T.p; box.querySelector("a").textContent = T.l;
    var btn = box.querySelector("button"); btn.textContent = T.b; btn.onclick = function () { btn.disabled = true; btn.textContent = "…"; fresh(); };
    box.querySelector("pre").textContent = details; box.querySelectorAll("p")[1].textContent = details ? T.d : "";
    document.body.appendChild(box);
  }

  function check() {
    var bar = document.getElementById("tabbar");
    var blank = !!bar && !bar.children.length;            // the app always draws the bottom bar first: empty means it never started
    var loginBroken = !bar && document.getElementById("loginForm") && crashed.length; // the sign-in page has no bar
    if (blank || failed.length || loginBroken) show();
  }

  document.addEventListener("DOMContentLoaded", function () {
    if (document.body && document.body.getAttribute("data-page") === "repair") {
      fresh(function () { var m = document.getElementById("repairMsg"); if (m) m.hidden = false; var w = document.getElementById("repairWait"); if (w) w.hidden = true; });
    }
  });
  window.addEventListener("load", function () { setTimeout(check, 2500); });
})();
