/* Only a signed-in System Admin sees the tool (the server never needs the key, this is just a convenience). */
(function () {
  var ok = false;
  try { var s = JSON.parse(localStorage.getItem("acf_session") || sessionStorage.getItem("acf_session")); ok = !!(s && s.token && (s.user.permissions || []).indexOf("system.updates") > -1); } catch (e) {}
  if (!ok) { document.getElementById("tool").hidden = true; document.getElementById("gate").hidden = false; }
})();
