(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  A.strings({ grp_new: "New group", chat_select: "Select a chat", chat_select_sub: "Choose a conversation on the left to start." }, { grp_new: "مجموعة جديدة", chat_select: "اختر محادثة", chat_select_sub: "اختر محادثة من القائمة للبدء." });

  function when(iso) {
    if (!iso) return "";
    var d = new Date(iso), n = new Date(), same = d.toDateString() === n.toDateString();
    return same ? A.timeOnly(d) : d.toLocaleDateString(A.lang() === "ar" ? "ar" : "en-GB", { day: "numeric", month: "short" });
  }
  function preview(c) {
    if (!c.last) return t("chat_empty");
    var m = /^\[(\w+)\]$/.exec(c.last), txt = m ? t("k_" + m[1]) : c.last;
    return (c.last_by ? c.last_by.split(" ")[0] + ": " : "") + txt;
  }
  /* On a computer the list sits on the left and the conversation opens on the right (like WhatsApp Web); on a phone each opens on its own page. */
  var ACTIVE = parseInt((location.search.match(/[?&]c=(\d+)/) || [])[1], 10) || 0, PANE = null;
  function split() { return !!(window.matchMedia && window.matchMedia("(min-width:1024px)").matches); }
  function paneEl() { return document.getElementById("chatPane"); }
  function mark() { Array.prototype.forEach.call(document.querySelectorAll(".chat-row"), function (r) { r.classList.toggle("on", split() && +r.getAttribute("data-c") === ACTIVE); }); }
  function showEmpty() { var p = paneEl(); if (p) p.innerHTML = '<div class="chat-none">' + ic("chat") + "<b>" + t("chat_select") + "</b><span>" + t("chat_select_sub") + "</span></div>"; }
  function openIn(id) {
    ACTIVE = id; mark(); var p = paneEl(); if (!p) return;
    try { history.replaceState(null, "", "chat.html?c=" + id); } catch (e) {}
    A.room.open(id, p);
  }
  function sync() {   // the page becomes two columns on a wide screen
    var on = split(); document.body.classList.toggle("chat-split", on);
    if (on && !PANE) { PANE = true; if (ACTIVE) openIn(ACTIVE); else showEmpty(); }
    else if (!on && PANE) { PANE = null; A.room.close(); }
  }
  window.addEventListener("resize", function () { clearTimeout(sync.t); sync.t = setTimeout(sync, 150); });
  function render(rows) {
    A.setUnread(rows.reduce(function (n, c) { return n + (c.unread || 0); }, 0));
    return '<div class="head chat-head"><h1 class="title">' + t("n_chat") + '</h1><div class="actions"><a class="btn btn-line btn-sm" href="newgroup.html">' + ic("users") + "<span>" + t("grp_new") + '</span></a><button class="btn btn-main btn-sm" type="button" data-act="newchat">' + ic("plus") + "<span>" + t("chat_new") + "</span></button></div></div>" +
      '<div class="list chat-list">' + rows.map(function (c) {
        var name = c.type === "general" ? t("chat_general") : c.title;
        return '<a class="item chat-row' + (split() && ACTIVE === c.id ? " on" : "") + '" href="room.html?c=' + c.id + '" data-act="openroom" data-c="' + c.id + '">' + (c.type === "general" || c.type === "group" ? '<span class="avatar grp"' + (c.cover ? ' data-cov="' + c.id + '"' : "") + ">" + ic("users") + "</span>" : A.avatar(c.other || { name: name }, "avatar")) + '<span class="grow"><b>' + esc(name) + '</b><span class="sub">' + esc(preview(c)) + '</span></span>' +
          '<span class="right"><span class="sub">' + esc(when(c.last_at)) + "</span>" + (c.unread ? '<span class="nbadge in">' + (c.unread > 99 ? "99+" : c.unread) + "</span>" : "") + "</span></a>";
      }).join("") + "</div>";
  }

  A.act("openroom", function (el, e) { if (!split()) return; e.preventDefault(); openIn(+el.getAttribute("data-c")); });
  A.act("newchat", function () {
    A.sheet(t("chat_new"), '<p class="note">' + t("loading") + "</p>");
    Promise.all([A.api.get("chat/people", { cache: false }), A.api.get("chat/conversations", { cache: false })]).then(function (r) {
      var people = r[0].data, have = {};
      r[1].data.forEach(function (c) { if (c.other) have[c.other.id] = c.id; });
      var body = people.length ? '<p class="note">' + t("chat_people") + '</p><div class="list">' + people.map(function (p) {
        return '<button class="item" type="button" data-act="startchat" data-user="' + p.id + '">' + A.avatar(p, "avatar") + '<span class="grow"><b>' + esc(p.name) + '</b><span class="sub">' + esc(p.role_label + (p.department ? " · " + p.department : "")) + "</span></span>" + ic("chev") + "</button>";
      }).join("") + "</div>" : '<p class="note">' + t("chat_nolist") + "</p>";
      A.sheet(t("chat_new"), body + '<div class="err" id="startErr"></div>');
    }, function (e) { A.sheet(t("chat_new"), '<p class="err">' + esc(A.msg(e)) + "</p>"); });
  });
  A.act("startchat", function (el) {
    el.disabled = true;
    A.api.write("POST", "chat/direct", { user_id: parseInt(el.getAttribute("data-user"), 10) }, { online: true }).then(function (r) { if (split()) { A.closeSheet(); openIn(r.data.id); A.reload(true); } else location.href = "room.html?c=" + r.data.id; }, function (e) {
      el.disabled = false; var d = document.getElementById("startErr"); if (d) d.textContent = e.offline ? t("chat_offline") : A.msg(e);
    });
  });

  setInterval(function () { if (!document.hidden && navigator.onLine !== false && !(A.$("#sheetRoot") && A.$("#sheetRoot").innerHTML)) A.reload(true); }, 15000);
  A.start({ page: "chat", nav: "chat", title: "n_chat", perm: "chat.use", needsConn: true, shell: true, load: function () { return A.api.get("chat/conversations", { cache: false }); }, render: render, after: function () { sync(); Array.prototype.forEach.call(document.querySelectorAll("[data-cov]"), function (el) { A.api.blobUrl("chat/conversations/" + el.getAttribute("data-cov") + "/cover").then(function (u) { el.style.backgroundImage = 'url(\"' + u + '\")'; el.classList.add("has-photo"); }, function () {}); }); } });
})();
