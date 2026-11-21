(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;

  function when(iso) {
    if (!iso) return "";
    var d = new Date(iso), n = new Date(), same = d.toDateString() === n.toDateString();
    return same ? d.toLocaleTimeString(A.lang() === "ar" ? "ar" : "en-GB", { hour: "2-digit", minute: "2-digit" }) : d.toLocaleDateString(A.lang() === "ar" ? "ar" : "en-GB", { day: "numeric", month: "short" });
  }
  function preview(c) {
    if (!c.last) return t("chat_empty");
    var m = /^\[(\w+)\]$/.exec(c.last), txt = m ? t("k_" + m[1]) : c.last;
    return (c.last_by ? c.last_by.split(" ")[0] + ": " : "") + txt;
  }
  function render(rows) {
    A.setUnread(rows.reduce(function (n, c) { return n + (c.unread || 0); }, 0));
    return '<div class="head chat-head"><h1 class="title">' + t("n_chat") + '</h1><button class="btn btn-main btn-sm" type="button" data-act="newchat">' + ic("plus") + "<span>" + t("chat_new") + "</span></button></div>" +
      '<div class="list chat-list">' + rows.map(function (c) {
        var name = c.type === "general" ? t("chat_general") : c.title;
        return '<a class="item chat-row" href="room.html?c=' + c.id + '"><span class="avatar' + (c.type === "general" ? " grp" : "") + '">' + (c.type === "general" ? ic("users") : esc(String(name).charAt(0))) + '</span><span class="grow"><b>' + esc(name) + '</b><span class="sub">' + esc(preview(c)) + '</span></span>' +
          '<span class="right"><span class="sub">' + esc(when(c.last_at)) + "</span>" + (c.unread ? '<span class="nbadge in">' + (c.unread > 99 ? "99+" : c.unread) + "</span>" : "") + "</span></a>";
      }).join("") + "</div>";
  }

  A.act("newchat", function () {
    A.sheet(t("chat_new"), '<p class="note">' + t("loading") + "</p>");
    Promise.all([A.api.get("chat/people", { cache: false }), A.api.get("chat/conversations", { cache: false })]).then(function (r) {
      var people = r[0].data, have = {};
      r[1].data.forEach(function (c) { if (c.other) have[c.other.id] = c.id; });
      var body = people.length ? '<p class="note">' + t("chat_people") + '</p><div class="list">' + people.map(function (p) {
        return '<button class="item" type="button" data-act="startchat" data-user="' + p.id + '"><span class="avatar">' + esc(p.name.charAt(0)) + '</span><span class="grow"><b>' + esc(p.name) + '</b><span class="sub">' + esc(p.role_label + (p.department ? " · " + p.department : "")) + "</span></span>" + ic("chev") + "</button>";
      }).join("") + "</div>" : '<p class="note">' + t("chat_nolist") + "</p>";
      A.sheet(t("chat_new"), body + '<div class="err" id="startErr"></div>');
    }, function (e) { A.sheet(t("chat_new"), '<p class="err">' + esc(A.msg(e)) + "</p>"); });
  });
  A.act("startchat", function (el) {
    el.disabled = true;
    A.api.write("POST", "chat/direct", { user_id: parseInt(el.getAttribute("data-user"), 10) }, { online: true }).then(function (r) { location.href = "room.html?c=" + r.data.id; }, function (e) {
      el.disabled = false; var d = document.getElementById("startErr"); if (d) d.textContent = e.offline ? t("chat_offline") : A.msg(e);
    });
  });

  setInterval(function () { if (!document.hidden && navigator.onLine !== false && !(A.$("#sheetRoot") && A.$("#sheetRoot").innerHTML)) A.reload(true); }, 15000);
  A.start({ page: "chat", nav: "chat", title: "n_chat", perm: "chat.use", needsConn: true, shell: true, load: function () { return A.api.get("chat/conversations", { cache: false }); }, render: render });
})();
