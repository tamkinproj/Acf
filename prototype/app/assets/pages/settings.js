(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  function sw(k, label) {
    return '<div class="setrow"><b>' + label + '</b><button class="switch" type="button" role="switch" aria-checked="' + A.ui[k] + '" aria-label="' + esc(label) + '" data-act="toggle" data-k="' + k + '"></button></div>';
  }
  function render() {
    return '<div class="head"><h1 class="title">' + t("set_title") + '</h1></div><div class="card" style="max-width:36rem">' + sw("reminders", t("set_rem")) + sw("amounts", t("set_amt")) +
      '<div class="setrow"><b>' + t("set_lang") + '</b><button class="btn btn-line btn-sm" data-act="lang">' + t("lang_btn") + "</button></div>" +
      '<div class="setrow"><b>' + t("set_role") + '</b><span class="badge">' + t("role_admin") + "</span></div>" +
      '<div class="setrow"><button class="btn btn-line btn-sm" data-act="signout">' + ic("logout") + t("signout") + "</button></div>" +
      '<p class="note">' + t("set_data") + "</p></div>";
  }
  A.start({ page: "settings", nav: "settings", title: "n_set", shell: true, render: render });
})();
