(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  A.strings({
    nt_title: "Notifications", nt_none: "Nothing new. Messages and reminders for you will appear here.", nt_all: "Mark all as read", nt_chat: "{n} new messages", nt_back: "Back", nt_sound: "Sounds are on. You can turn them off in Settings.",
    nt_today: "Today", nt_earlier: "Earlier"
  }, {
    nt_title: "الإشعارات", nt_none: "لا جديد. ستظهر هنا الرسائل والتنبيهات الخاصة بك.", nt_all: "تحديد الكل كمقروء", nt_chat: "{n} رسائل جديدة", nt_back: "رجوع", nt_sound: "الأصوات مفعّلة. يمكنك إيقافها من الإعدادات.",
    nt_today: "اليوم", nt_earlier: "سابقًا"
  });
  var ICON = { chat: "chat", application: "file", education: "book", security: "shield", system: "bell" };
  function when(iso) { if (!iso) return ""; var d = new Date(iso); return d.toDateString() === new Date().toDateString() ? A.timeOnly(d) : A.dateOnly(d); }
  var ITEMS = [];
  function item(n) {
    var inner = '<span class="nt-ic">' + ic(ICON[n.type] || "bell") + '</span><span class="grow"><b>' + esc(n.title) + (n.kind === "chat" && n.count > 1 ? ' <span class="nbadge in">' + n.count + "</span>" : "") + "</b>" + (n.body ? '<span class="sub">' + esc(n.body) + "</span>" : "") + '</span><span class="nt-when">' + esc(when(n.at)) + "</span>" + (n.unread ? '<i class="nt-dot"></i>' : "");
    var cls = "nt-row" + (n.unread ? " unread" : "");
    return n.url ? '<a class="' + cls + '" href="' + esc(n.url) + '" data-act="ntopen" data-id="' + esc(n.id) + '" data-kind="' + n.kind + '">' + inner + "</a>" : '<div class="' + cls + '">' + inner + "</div>";
  }
  function render(d) {
    ITEMS = d.items; var unread = d.unread;
    var today = d.items.filter(function (n) { return n.at && new Date(n.at).toDateString() === new Date().toDateString(); }), earlier = d.items.filter(function (n) { return today.indexOf(n) < 0; });
    var group = function (title, list) { return list.length ? '<h4 class="optlbl">' + title + '</h4><div class="nt-list">' + list.map(item).join("") + "</div>" : ""; };
    return '<div class="head chat-head"><h1 class="title">' + t("nt_title") + "</h1>" + (unread ? '<button class="btn btn-line btn-sm" type="button" data-act="ntall">' + ic("check") + "<span>" + t("nt_all") + "</span></button>" : "") + "</div>" +
      (d.items.length ? group(t("nt_today"), today) + group(t("nt_earlier"), earlier) : '<p class="note">' + t("nt_none") + "</p>");
  }
  A.act("ntall", function () { A.api.write("POST", "notices/read", {}, { online: true }).then(function () { A.setNotices(ITEMS.filter(function (n) { return n.kind === "chat"; }).reduce(function (a, n) { return a + n.count; }, 0)); A.reload(true); }, function () {}); });
  A.act("ntopen", function (el) {   // a stored notice is marked as read when it is opened; chat messages are read in the chat
    if (el.getAttribute("data-kind") === "notice") { try { A.api.write("POST", "notices/read", { ids: [parseInt(el.getAttribute("data-id"), 10)] }, { online: true }); } catch (e) {} }
  });
  A.start({ page: "notifications", nav: "settings", title: "nt_title", needsConn: true, shell: true, load: function () { return A.api.get("notices", { cache: false }); }, render: render, after: function (d) { if (d) A.setNotices(d.unread || 0); } });
})();
