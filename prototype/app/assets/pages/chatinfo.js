(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  var cid = parseInt((location.search.match(/[?&]c=(\d+)/) || [])[1], 10) || 0, INFO = null, editing = false, adding = false, POOL = null, picked = {};
  var PANE = null, BACK = null;   // on a computer the settings open in the chat page's right-hand panel; BACK returns to the conversation

  A.strings({
    ci_title: "Chat settings", ci_cover: "Cover photo", ci_cover_set: "Change cover", ci_cover_rm: "Remove", ci_name: "Group name", ci_theme: "Theme", ci_members: "Members", ci_owner: "Owner", ci_add: "Add people", ci_guests: "Orphans and families (they see only this group)", ci_k_orphan: "Orphan", ci_k_family: "Family", ci_add_go: "Add", ci_leave: "Leave group",
    ci_leave_q: "Leave this group? You will not see it any more unless someone adds you again.", ci_rm_q: "Remove this person from the group?", ci_saved: "Saved", ci_none_to_add: "Everyone is already in this group.", ci_general: "General", ci_view_only: "Only the group's owner and the President Admin can change these.",
    th_lime: "Lime", th_ocean: "Ocean", th_sunset: "Sunset", th_rose: "Rose", th_violet: "Violet", th_graphite: "Graphite", ci_back: "Back", ci_n: "{n} members"
  }, {
    ci_title: "إعدادات المحادثة", ci_cover: "صورة الغلاف", ci_cover_set: "تغيير الغلاف", ci_cover_rm: "إزالة", ci_name: "اسم المجموعة", ci_theme: "المظهر", ci_members: "الأعضاء", ci_owner: "المالك", ci_add: "إضافة أشخاص", ci_guests: "الأيتام والأسر (يرون هذه المجموعة فقط)", ci_k_orphan: "يتيم", ci_k_family: "أسرة", ci_add_go: "إضافة", ci_leave: "مغادرة المجموعة",
    ci_leave_q: "مغادرة هذه المجموعة؟ لن تراها بعد الآن إلا إذا أضافك أحد مرة أخرى.", ci_rm_q: "إزالة هذا الشخص من المجموعة؟", ci_saved: "تم الحفظ", ci_none_to_add: "الجميع موجودون في هذه المجموعة.", ci_general: "العام", ci_view_only: "مالك المجموعة ومدير الرئاسة فقط يمكنهم تغيير هذه.",
    th_lime: "ليموني", th_ocean: "محيطي", th_sunset: "غروب", th_rose: "وردي", th_violet: "بنفسجي", th_graphite: "رمادي", ci_back: "رجوع", ci_n: "{n} عضوًا"
  });

  function look(theme) {
    var h = document.documentElement, c = h.className.split(" ").filter(function (x) { return x.indexOf("th-") !== 0; }); if (theme) c.push("th-" + theme); h.className = c.join(" ");
  }
  function person(m, d, man) {
    var rm = man && d.type === "group" && !m.owner && m.id !== d.me ? '<button class="ci-x" type="button" data-act="ci_rm" data-id="' + m.id + '" aria-label="' + esc(t("ci_cover_rm")) + '">&times;</button>' : "";
    var tap = m.id !== d.me && !m.guest && A.can("chat.use") ? ' role="button" tabindex="0" data-act="ci_dm" data-id="' + m.id + '"' : "";   // tap someone to message them on their own
    return '<div class="ios-row no-tile dp-row ci-person' + (tap ? " dp-tap" : "") + '"' + tap + '>' + A.avatar(m, "avatar") + '<span class="grow"><b>' + esc(m.name) + (m.owner ? ' <span class="badge">' + t("ci_owner") + "</span>" : "") + '</b><span class="sub">' + esc((m.guest ? t(m.role_label === "Orphan" ? "ci_k_orphan" : "ci_k_family") : m.role_label) + (m.department ? " · " + m.department : "")) + "</span></span>" + rm + "</div>";
  }
  function render(d) {
    INFO = d; look(d.theme); var grp = d.type === "group", man = d.can_manage;
    var cover = '<div class="ci-cover" id="ciCover">' + (d.cover ? "" : ic("users")) + (man ? '<div class="ci-cover-btns"><button class="btn btn-sm" type="button" data-act="ci_cover">' + ic("edit") + "<span>" + t("ci_cover_set") + "</span></button>" + (d.cover ? '<button class="btn btn-sm btn-line" type="button" data-act="ci_cover_rm">' + t("ci_cover_rm") + "</button>" : "") + "</div>" : "") + '</div><input type="file" id="ciFile" accept="image/*" hidden>';
    var nameRow;
    if (!grp) nameRow = '<div class="ios-row no-tile dp-row"><span class="ios-lbl">' + t("ci_name") + '</span><span class="ios-val">' + t("ci_general") + "</span></div>";
    else if (editing) nameRow = '<div class="dp-inrow"><div><label for="ci_title">' + t("ci_name") + '</label><input id="ci_title" type="text" maxlength="60" value="' + esc(d.title) + '"><div class="err" id="ci_titleErr"></div></div><div class="dp-inbtns"><button class="btn btn-line btn-sm" type="button" data-act="ci_cancel">' + t("cancel") + '</button><button class="btn btn-main btn-sm" type="button" data-act="ci_save">' + t("save") + "</button></div></div>";
    else nameRow = '<div class="ios-row no-tile dp-row' + (man ? " dp-tap" : "") + '"' + (man ? ' role="button" tabindex="0" data-act="ci_edit"' : "") + '><span class="ios-lbl">' + t("ci_name") + '</span><span class="ios-val">' + esc(d.title) + "</span></div>";
    var cur = d.theme || "lime";
    var themes = '<div class="ios-group dp-pad"><div class="sw-row">' + d.themes.map(function (k) { return '<button class="sw-th th-' + k + (cur === k ? " on" : "") + '" type="button" data-act="ci_theme" data-k="' + k + '" aria-label="' + esc(t("th_" + k)) + '"' + (man ? "" : " disabled") + "></button>"; }).join("") + "</div></div>";
    var pool = "";
    if (adding) {
      var have = {}; d.members.forEach(function (m) { have[m.id] = 1; });
      var rest = (POOL || []).filter(function (p) { return !have[p.id]; }), firstGuest = true;
      pool = '<div class="ios-group">' + (rest.map(function (p) {
        var head = p.guest && firstGuest ? (firstGuest = false, '<div class="ios-row no-tile ci-sub"><b>' + esc(t("ci_guests")) + "</b></div>") : "";
        var on = !!picked[p.id];
        return head + '<button class="ios-row no-tile pick' + (on ? " on" : "") + '" type="button" data-act="ci_pick" data-id="' + p.id + '" role="checkbox" aria-checked="' + on + '">' + A.avatar(p, "avatar") + '<span class="grow"><b>' + esc(p.name) + '</b><span class="sub">' + esc((p.guest ? t(p.kind === "orphan" ? "ci_k_orphan" : "ci_k_family") : p.role_label) + (p.department ? " · " + p.department : "")) + '</span></span><span class="tick">' + (on ? ic("check") : "") + "</span></button>"; }).join("") || '<p class="note" style="padding:1rem">' + t("ci_none_to_add") + "</p>") +
        '</div><div class="actions" style="margin:.7rem 0 0"><button class="btn btn-line btn-sm" type="button" data-act="ci_add_cancel">' + t("cancel") + '</button><button class="btn btn-main btn-sm" type="button" data-act="ci_add_go">' + t("ci_add_go") + "</button></div>";
    }
    var members = '<div class="ios-group dp-rows ci-members">' + d.members.map(function (m) { return person(m, d, man); }).join("") + "</div>" + (grp && man && !adding ? '<div class="actions" style="margin-top:.7rem"><button class="btn btn-line btn-sm" type="button" data-act="ci_add">' + ic("plus") + "<span>" + t("ci_add") + "</span></button></div>" : "") + pool;
    var leave = grp ? '<div class="ios-group" style="margin-top:1rem"><button class="ios-row no-tile ios-danger" type="button" data-act="ci_leave"><span class="ios-lbl">' + t("ci_leave") + "</span></button></div>" : "";
    return '<div class="dp-bar">' + (PANE ? '<button class="dp-back" type="button" data-act="ci_back" aria-label="' + t("ci_back") + '">' + ic("back", "flip") + "</button>" : '<a class="dp-back" href="room.html?c=' + d.id + '" aria-label="' + t("ci_back") + '">' + ic("back", "flip") + "</a>") + '<span class="dp-title">' + esc(d.type === "general" ? t("ci_general") : d.title) + '</span><span class="dp-act ghost"></span></div>' +
      '<div class="addwrap">' + cover + '<div class="dp-sec" style="margin-top:1rem"><div class="ios-head"><h3>' + t("ci_title") + '</h3></div><div class="ios-group dp-rows">' + nameRow + "</div>" + (man ? "" : '<p class="ios-note">' + t("ci_view_only") + "</p>") + "</div>" +
      '<div class="dp-sec"><div class="ios-head"><h3>' + t("ci_theme") + "</h3></div>" + themes + "</div>" +
      '<div class="dp-sec"><div class="ios-head"><h3>' + t("ci_members") + '</h3><span class="note">' + t("ci_n", { n: d.members.length }) + "</span></div>" + members + "</div>" + leave + "</div>";
  }
  function coverAfter(d) { var cv = document.getElementById("ciCover"); if (cv && d && d.cover) A.api.blobUrl("chat/conversations/" + cid + "/cover").then(function (u) { cv.style.backgroundImage = 'url("' + u + '")'; cv.classList.add("has-photo"); }, function () {}); }
  function paint() { if (PANE) { PANE.innerHTML = render(INFO); coverAfter(INFO); } else A.paint(); }
  function load() { return A.api.get("chat/conversations/" + cid + "/info", { cache: false, forceNet: true }); }
  function again() { if (!PANE) return A.reload(true); return load().then(function (r) { INFO = r.data; paint(); }, fail); }
  A.act("ci_back", function () { if (BACK) BACK(); });
  function fail(e) { A.toast(e.offline ? t("need_conn") : A.msg(e)); }
  A.act("ci_edit", function () { editing = true; paint(); });
  A.act("ci_cancel", function () { editing = false; paint(); });
  A.act("ci_save", function () {
    var v = (document.getElementById("ci_title").value || "").trim(); if (v.length < 2) { document.getElementById("ci_titleErr").textContent = t("required"); return; }
    A.api.write("PATCH", "chat/conversations/" + cid, { title: v }, { online: true }).then(function () { editing = false; A.toast(t("ci_saved")); again(); }, fail);
  });
  A.act("ci_theme", function (el) { A.api.write("PATCH", "chat/conversations/" + cid, { theme: el.getAttribute("data-k") }, { online: true }).then(function () { again(); }, fail); });
  A.act("ci_cover", function () {
    var f = document.getElementById("ciFile");
    f.onchange = function () { var file = f.files[0]; f.value = ""; if (!file) return; var fd = new FormData(); fd.append("photo", file); A.toast("…"); A.api.upload("chat/conversations/" + cid + "/cover", fd).then(function () { A.toast(t("ci_saved")); again(); }, fail); };
    f.click();
  });
  A.act("ci_cover_rm", function () { A.api.write("DELETE", "chat/conversations/" + cid + "/cover", undefined, { online: true }).then(function () { again(); }, fail); });
  A.act("ci_add", function () {
    adding = true; picked = {};
    // staff first, then orphans and families (they only see this group, never the General chat or private chats)
    (POOL ? Promise.resolve() : Promise.all([A.api.get("chat/people", { cache: false }), A.api.get("chat/people?guests=1", { cache: false })]).then(function (r) { POOL = r[0].data.concat(r[1].data); })).then(function () { paint(); }, fail);
  });
  A.act("ci_pick", function (el) { var id = el.getAttribute("data-id"); if (picked[id]) delete picked[id]; else picked[id] = 1; paint(); });
  A.act("ci_add_cancel", function () { adding = false; paint(); });
  A.act("ci_add_go", function () {
    var ids = Object.keys(picked).map(Number); if (!ids.length) { adding = false; paint(); return; }
    A.api.write("POST", "chat/conversations/" + cid + "/members", { user_ids: ids }, { online: true }).then(function () { adding = false; again(); }, fail);
  });
  A.act("ci_rm", function (el) { if (!confirm(t("ci_rm_q"))) return; A.api.write("DELETE", "chat/conversations/" + cid + "/members/" + el.getAttribute("data-id"), undefined, { online: true }).then(function () { again(); }, fail); });
  A.act("ci_dm", function (el) { if (A.openDirect) A.openDirect(+el.getAttribute("data-id")); else A.api.write("POST", "chat/direct", { user_id: +el.getAttribute("data-id") }, { online: true }).then(function (r) { location.href = "room.html?c=" + (r.data || r).id; }, fail); });
  A.act("ci_leave", function () { if (!confirm(t("ci_leave_q"))) return; A.api.write("DELETE", "chat/conversations/" + cid + "/members/" + INFO.me, undefined, { online: true }).then(function () { location.replace("chat.html"); }, fail); });
  function open(id, pane, back) {
    cid = id; PANE = pane; BACK = back; editing = false; adding = false; picked = {};
    pane.innerHTML = '<p class="note" style="padding:1rem">' + esc(t("loading")) + "</p>";
    return load().then(function (r) { if (PANE !== pane || cid !== id) return; INFO = r.data; paint(); }, function (e) { pane.innerHTML = '<p class="err" style="padding:1rem">' + esc(A.msg(e)) + "</p>"; });
  }
  A.chatinfo = { open: open };

  if (document.body.getAttribute("data-page") === "chatinfo") A.start({
    page: "chatinfo", nav: "chat", title: "ci_title", perm: "chat.use|chat.guest", needsConn: true, shell: true, bare: true,
    load: load, render: render, after: coverAfter
  });
})();
