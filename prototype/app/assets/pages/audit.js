(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  var AREAS = ["all", "security", "accounts", "orphans", "finance", "projects", "education", "website", "chat", "attendance", "system"], ROWS = [], page = 1, last = 1, area = "all", open = {};

  A.strings({
    audit_title: "Activity log", audit_note: "Who did what, when, and to which record. It holds references and short labels only, never names, account numbers or documents. Needs a connection; nothing is saved on the phone.",
    audit_none: "No activity yet.", au_all: "All", au_security: "Sign-ins and security", au_accounts: "Accounts", au_orphans: "Orphans", au_finance: "Finance", au_projects: "Projects", au_education: "Education", au_attendance: "Attendance", au_website: "Website", au_chat: "Chat", au_system: "System",
    au_more: "Show older activity", au_unknown: "Someone not signed in", au_from: "From address", au_about: "Record", au_today: "Today", au_yesterday: "Yesterday"
  }, {
    audit_title: "سجل النشاط", audit_note: "من فعل ماذا ومتى وعلى أي سجل. يحوي مراجع وتسميات قصيرة فقط، لا أسماء ولا أرقام حسابات ولا مستندات. يحتاج اتصالًا؛ لا يُحفظ شيء على الهاتف.",
    audit_none: "لا نشاط بعد.", au_all: "الكل", au_security: "الدخول والأمان", au_accounts: "الحسابات", au_orphans: "الأيتام", au_finance: "المالية", au_projects: "المشاريع", au_education: "التعليم", au_attendance: "الحضور", au_website: "الموقع", au_chat: "المحادثات", au_system: "النظام",
    au_more: "عرض نشاط أقدم", au_unknown: "شخص غير مسجّل الدخول", au_from: "من العنوان", au_about: "السجل", au_today: "اليوم", au_yesterday: "أمس"
  });
  var ICON = { security: "shield", accounts: "users", orphans: "users", finance: "wallet", projects: "folder", education: "book", attendance: "clock", website: "globe", chat: "chat", system: "sliders" };

  function dayLabel(iso) {
    var d = new Date(iso), n = new Date(), y = new Date(n.getTime() - 86400000);
    return d.toDateString() === n.toDateString() ? t("au_today") : d.toDateString() === y.toDateString() ? t("au_yesterday") : A.dateOnly(d);
  }
  function row(r) {
    var who = r.actor ? esc(r.actor.name) : esc(t("au_unknown")), role = r.actor && r.actor.role_label ? esc(r.actor.role_label) : "";
    var text = A.lang() === "ar" ? r.text.ar : r.text.en, on = !!open[r.id];
    var det = r.details.map(function (d) { return '<div class="au-d"><span>' + esc(A.lang() === "ar" ? d.ar : d.en) + "</span><b dir=\"auto\">" + esc(d.value) + "</b></div>"; }).join("") +
      (r.subject ? '<div class="au-d"><span>' + t("au_about") + '</span><b dir="ltr">' + esc(r.subject.label) + "</b></div>" : "") + (r.ip ? '<div class="au-d"><span>' + t("au_from") + '</span><b dir="ltr">' + esc(r.ip) + "</b></div>" : "");
    return '<div class="au-row ' + r.area + (on ? " open" : "") + '" data-act="auopen" data-id="' + r.id + '" role="button" tabindex="0"><span class="au-ic">' + ic(ICON[r.area] || "sliders") + '</span><div class="grow"><b>' + esc(text) + (r.subject ? ' <span class="au-sub" dir="ltr">· ' + esc(r.subject.label) + "</span>" : "") + '</b><span class="sub">' + who + (role ? " · " + role : "") + '</span>' +
      (on && det ? '<div class="au-det">' + det + "</div>" : "") + '</div><span class="au-time">' + esc(A.timeOnly(r.at)) + "</span></div>";
  }
  function list() {
    var out = "", lastDay = "";
    ROWS.forEach(function (r) { var d = dayLabel(r.at); if (d !== lastDay) { out += '<h4 class="optlbl">' + esc(d) + "</h4>"; lastDay = d; } out += row(r); });
    return out || '<p class="note">' + t("audit_none") + "</p>";
  }
  function render() {
    return '<div class="head"><h1 class="title">' + t("audit_title") + '</h1></div><p class="note">' + t("audit_note") + '</p><div class="filters" role="group">' +
      AREAS.map(function (k) { return '<button class="fbtn" type="button" data-act="auarea" data-v="' + k + '" aria-pressed="' + (area === k) + '">' + t("au_" + k) + "</button>"; }).join("") + '</div><div id="auList" class="au-list">' + list() + "</div>" +
      (page < last ? '<div class="actions" style="margin-top:1rem"><button class="btn btn-line" type="button" data-act="aumore">' + t("au_more") + "</button></div>" : "");
  }
  function fetchPage(p, append) {
    return A.api.get("audit?per_page=50&page=" + p + (area !== "all" ? "&area=" + area : ""), { cache: false }).then(function (r) {
      ROWS = append ? ROWS.concat(r.data) : r.data; page = (r.meta && r.meta.page) || p; last = (r.meta && r.meta.last_page) || 1; return { data: ROWS };
    });
  }
  A.act("auarea", function (el) { area = el.getAttribute("data-v"); page = 1; open = {}; A.reload(false); });
  A.act("aumore", function (el) { el.disabled = true; fetchPage(page + 1, true).then(function () { A.paint(); }, function () { el.disabled = false; }); });
  A.act("auopen", function (el) { var id = el.getAttribute("data-id"); open[id] = !open[id]; A.paint(); });
  A.start({ page: "audit", nav: "audit", title: "n_audit", needsConn: true, perm: "audit.read", shell: true, load: function () { return fetchPage(1, false); }, render: render });
})();
