(function () {
  var A = ACF, t = A.t, $ = A.$;

  A.form("loginForm", function () {
    var em = $("#lEmail").value.trim(), pw = $("#lPass").value, err = $("#loginErr"), btn = $("#loginForm button[type=submit]");
    err.textContent = "";
    if (!em || !pw) { err.textContent = t("login_err"); return; }
    if (navigator.onLine === false) { err.textContent = t("login_offline"); return; }
    btn.disabled = true; btn.textContent = t("login_busy");
    var keep = !!($("#lRemember") && $("#lRemember").checked);
    A.api.login(em, pw, keep).then(function () {
      // "Remember me" is kept as this device's choice, with the email or username to fill in next time
      try { localStorage.setItem("acf_remember", keep ? "1" : "0"); if (keep) localStorage.setItem("acf_last_login", em); else localStorage.removeItem("acf_last_login"); } catch (x) {}
      location.replace("dashboard.html");
    }, function (e) {
      btn.disabled = false; btn.textContent = t("signin");
      err.textContent = e.network ? t("login_offline") : e.code === "THROTTLED" ? e.message : e.status === 429 ? t("login_locked") : e.status === 401 || e.status === 422 ? t("login_fail") : (e.message || t("login_fail"));
    });
  });

  A.start({
    page: "login", title: "signin", shell: false,
    after: function () {
      if (/[?&]expired=1/.test(location.search)) $("#loginErr").textContent = t("login_expired");
      var rem = $("#lRemember"), em = $("#lEmail");
      try {
        var pref = localStorage.getItem("acf_remember"); if (rem && pref !== null) rem.checked = pref === "1";
        var last = localStorage.getItem("acf_last_login"); if (last && rem && rem.checked && em && !em.value) { em.value = last; $("#lPass").focus(); }
      } catch (x) {}
      var eye = $("#lEye"); if (eye) eye.setAttribute("aria-label", t("pw_show"));
    }
  });
})();
