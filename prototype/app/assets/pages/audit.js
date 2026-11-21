(function () {
  var A = ACF, t = A.t, esc = A.esc;

  A.strings({
    audit_title: "Activity log", audit_note: "Who did what and when. It holds ids and short labels only, never names, account numbers or documents. Needs a connection; nothing is saved on the phone.",
    audit_none: "No activity yet.", by_user: "Account", on_subject: "About"
  }, {
    audit_title: "سجل النشاط", audit_note: "من فعل ماذا ومتى. يحوي أرقامًا وتسميات قصيرة فقط، لا أسماء ولا أرقام حسابات ولا مستندات. يحتاج اتصالًا؛ لا يُحفظ شيء على الهاتف.",
    audit_none: "لا نشاط بعد.", by_user: "الحساب", on_subject: "بخصوص"
  });

  function render(rows) {
    return '<div class="head"><h1 class="title">' + t("audit_title") + '</h1></div><p class="note">' + t("audit_note") + '</p><div class="list">' + (rows.length ? rows.map(function (r) {
      var when = A.dateTime(r.created_at);
      var subj = r.subject_type ? " · " + esc(r.subject_type) + " #" + esc(r.subject_id) : "";
      return '<div class="item"><span class="grow"><b dir="ltr" style="text-align:start">' + esc(r.action) + '</b><span class="sub">' + esc(when) + " · " + t("by_user") + " #" + esc(r.user_id === null ? "-" : r.user_id) + subj + "</span></span></div>";
    }).join("") : '<p class="note">' + t("audit_none") + "</p>") + "</div>";
  }
  A.start({ page: "audit", nav: "audit", title: "n_audit", needsConn: true, perm: "audit.read", shell: true, load: function () { return A.api.get("audit?per_page=100", { cache: false }); }, render: render });
})();
