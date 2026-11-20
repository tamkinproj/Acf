(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, rowsNow = [];

  A.strings({
    rep_title: "Reports", rep_scope: "Scope", rep_month: "Month", rep_pdf: "Download PDF", pdf_next: "PDF export arrives in the next phase", rep_sum: "Summary",
    all: "All", bank: "Bank", cash: "Cash", s_children: "Children", paid: "Paid", total_paid: "Total paid", total_due: "Total due", export_csv: "Export CSV", exported: "CSV downloaded"
  }, {
    rep_title: "التقارير", rep_scope: "النطاق", rep_month: "الشهر", rep_pdf: "تنزيل PDF", pdf_next: "تصدير PDF يصل في المرحلة التالية", rep_sum: "الملخص",
    all: "الكل", bank: "بنك", cash: "نقدًا", s_children: "الأطفال", paid: "مدفوع", total_paid: "إجمالي المدفوع", total_due: "إجمالي المستحق", export_csv: "تصدير CSV", exported: "تم تنزيل CSV"
  });

  function scoped(rows) { return rows.filter(function (r) { return A.ui.scope === "all" || r.method === A.ui.scope; }); }
  function render(rows) {
    rowsNow = scoped(rows);
    var paid = rowsNow.filter(function (r) { return r.status === "paid"; }), sum = function (a) { return a.reduce(function (s, r) { return s + Number(r.amount); }, 0); };
    var pills = '<div class="filters" role="group">' + A.months().map(function (m) { return '<button class="fbtn" type="button" data-act="month" data-v="' + m.k + '" aria-pressed="' + (A.ui.month === m.k) + '">' + esc(m.label) + "</button>"; }).join("") + "</div>";
    return '<div class="head"><h1 class="title">' + t("rep_title") + "</h1></div>" +
      '<p class="note" style="margin:0 0 .4rem">' + t("rep_scope") + "</p>" +
      '<div class="filters" role="group">' + ["all", "bank", "cash"].map(function (k) { return '<button class="fbtn" type="button" data-act="scope" data-v="' + k + '" aria-pressed="' + (A.ui.scope === k) + '">' + t(k) + "</button>"; }).join("") + "</div>" +
      '<p class="note" style="margin:0 0 .4rem">' + t("rep_month") + "</p>" + pills +
      '<div class="card"><h3>' + t("rep_sum") + " · " + esc(A.monthLabel(A.ui.month)) + "</h3>" +
      A.facts([[t("s_children"), rowsNow.length], [t("paid"), paid.length + " / " + rowsNow.length], [t("total_paid"), A.money(sum(paid))], [t("total_due"), A.money(sum(rowsNow) - sum(paid))]]) +
      '<div class="actions" style="margin-top:1rem"><button class="btn btn-main" data-act="pdf">' + ic("file") + t("rep_pdf") + "</button>" +
      (A.can("reports.export") ? '<button class="btn btn-line" data-act="csv-pay">' + ic("download") + t("export_csv") + "</button>" : "") + "</div></div>";
  }

  // Spreadsheet programs run text that starts with = + - @ as a formula; a leading ' makes it plain text.
  function cell(v) { var s = String(v === null || v === undefined ? "" : v); if (/^[=+\-@\t\r]/.test(s)) s = "'" + s; return '"' + s.replace(/"/g, '""') + '"'; }
  A.act("pdf", function () { A.toast(t("pdf_next")); });
  A.act("csv-pay", function () {
    var out = [["ref", "name_en", "method", "status", "amount_" + A.CUR]].concat(rowsNow.map(function (r) { return [r.ref, r.name_en, r.method, r.status, r.amount]; }));
    var text = out.map(function (r) { return r.map(cell).join(","); }).join("\r\n");
    var a = document.createElement("a"); a.href = URL.createObjectURL(new Blob(["﻿" + text], { type: "text/csv;charset=utf-8" }));
    a.download = "payments-" + A.ui.month + ".csv"; document.body.appendChild(a); a.click(); a.remove(); A.toast(t("exported"));
  });

  A.start({ page: "reports", nav: "reports", title: "rep_title", perm: "reports.view", shell: true, load: function () { return A.api.get("payments?month=" + A.ui.month); }, render: render });
})();
