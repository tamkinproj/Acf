/* Entry point: signed-in staff go to the dashboard, everyone else to the sign-in page. */
(function () {
  var ok = false;
  try { ok = !!JSON.parse(localStorage.getItem("acf_session")).token; } catch (e) {}
  location.replace(ok ? "dashboard.html" : "login.html");
})();
