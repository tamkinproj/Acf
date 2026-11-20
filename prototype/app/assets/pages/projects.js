(function () {
  var A = ACF, t = A.t, esc = A.esc;

  function render() {
    return '<div class="head"><h1 class="title">' + t("proj_title") + '</h1></div><div class="grid g2">' + A.PROJECTS.map(function (p) {
      var pct = Math.round(p.spent / p.budget * 100);
      return '<div class="card"><span class="badge ' + (p.done ? "grey" : "") + '">' + t(p.done ? "st_done" : "st_active") + '</span><h3 style="margin-top:.7rem">' + esc(A.lang() === "ar" ? p.ar : p.en) + '</h3><p class="note" style="margin-top:0">' + esc(p.loc) + "</p>" +
        '<div class="bar"><i style="width:' + pct + '%"></i></div>' + A.facts([[t("budget"), A.money(p.budget)], [t("spent"), A.money(p.spent)], [t("progress"), pct + "%"]]) + "</div>";
    }).join("") + "</div>";
  }
  A.start({ page: "projects", nav: "projects", title: "n_proj", shell: true, render: render });
})();
