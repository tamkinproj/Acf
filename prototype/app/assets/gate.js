/* Entry point: signed-in staff go to the dashboard, everyone else to the sign-in page. */
(function () {
  var ok = false;
  // a remembered sign-in is kept on the device; one that is not remembered only until the browser or app is closed
  try { ok = !!JSON.parse(localStorage.getItem("acf_session") || sessionStorage.getItem("acf_session")).token; } catch (e) {}
  location.replace(ok ? "dashboard.html" : "login.html");
})();
