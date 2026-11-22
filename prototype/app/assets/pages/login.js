(function () {
  var A = ACF, t = A.t, $ = A.$;

  A.form("loginForm", function () {
    var em = $("#lEmail").value.trim(), pw = $("#lPass").value, err = $("#loginErr"), btn = $("#loginForm button[type=submit]");
    err.textContent = "";
    if (!em || !pw) { err.textContent = t("login_err"); return; }
    if (navigator.onLine === false) { err.textContent = t("login_offline"); return; }
    btn.disabled = true; btn.textContent = t("login_busy");
    A.api.login(em, pw, $("#lTrust") && $("#lTrust").checked).then(function () { location.replace("dashboard.html"); }, function (e) {
      btn.disabled = false; btn.textContent = t("signin");
      err.textContent = e.network ? t("login_offline") : e.status === 429 ? t("login_locked") : e.status === 401 || e.status === 422 ? t("login_fail") : (e.message || t("login_fail"));
    });
  });

  A.start({
    page: "login", title: "signin", shell: false,
    after: function () { if (/[?&]expired=1/.test(location.search)) $("#loginErr").textContent = t("login_expired"); }
  });
})();
