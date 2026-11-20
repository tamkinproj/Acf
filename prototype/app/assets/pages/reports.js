(function () {
  var A = ACF, t = A.t, ic = A.ic;

  function render() {
    var m = A.ui.month, rows = A.scoped(), paid = rows.filter(function (c) { return A.pay[m][c.id] === "paid"; });
    var total = rows.reduce(function (a, c) { return a + c.amount; }, 0), got = paid.reduce(function (a, c) { return a + c.amount; }, 0);
    return '<div class="head"><h1 class="title">' + t("rep_title") + "</h1></div>" +
      '<p class="note" style="margin:0 0 .4rem">' + t("rep_scope") + "</p>" +
      '<div class="filters" role="group">' + ["all", "bank", "cash"].map(function (k) { return '<button class="fbtn" type="button" data-act="scope" data-v="' + k + '" aria-pressed="' + (A.ui.scope === k) + '">' + t(k) + "</button>"; }).join("") + "</div>" +
      '<p class="note" style="margin:0 0 .4rem">' + t("rep_month") + "</p>" + A.monthPills() +
      '<div class="card"><h3>' + t("rep_sum") + " · " + A.monthLabel(m) + "</h3>" + A.facts([[t("s_children"), rows.length], [t("paid"), paid.length + " / " + rows.length], [t("total_paid"), A.money(got)], [t("total_due"), A.money(total - got)]]) +
      '<div class="actions" style="margin-top:1rem"><button class="btn btn-main" data-act="pdf">' + ic("file") + t("rep_pdf") + '</button><button class="btn btn-line" data-act="csv-pay">' + ic("download") + t("export_csv") + "</button></div></div>";
  }
  A.start({ page: "reports", nav: "reports", title: "rep_title", shell: true, render: render });
})();
