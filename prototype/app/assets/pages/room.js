(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  var cid = parseInt((location.search.match(/[?&]c=(\d+)/) || [])[1], 10) || 0;
  var MSGS = [], META = null, lastId = 0, draft = "", timer = null, busy = false;
  var KIND_ICON = { child: "users", application: "file", project: "folder", payment: "wallet", user: "shield", report: "file", site: "globe" };

  function lang() { return A.lang() === "ar" ? "ar" : "en-GB"; }
  function day(iso) {
    var d = new Date(iso), n = new Date(), y = new Date(n.getTime() - 86400000);
    if (d.toDateString() === n.toDateString()) return t("chat_today"); if (d.toDateString() === y.toDateString()) return t("chat_yesterday");
    return d.toLocaleDateString(lang(), { weekday: "short", day: "numeric", month: "short" });
  }
  function hm(iso) { return new Date(iso).toLocaleTimeString(lang(), { hour: "2-digit", minute: "2-digit" }); }

  /* a card inside a bubble: a link to the page when this person may open it, otherwise only the kind of card */
  function card(c) {
    var ik = ic(KIND_ICON[c.kind] || "file");
    if (c.gone) return '<div class="cc off"><span class="cc-i">' + ik + '</span><span class="grow"><b>' + esc(t("k_" + c.kind)) + '</b><span class="sub">' + esc(t("chat_gone")) + "</span></span></div>";
    if (!c.access) return '<div class="cc off"><span class="cc-i">' + ik + '</span><span class="grow"><b>' + esc(t("k_" + c.kind)) + '</b>' + (c.sub ? '<span class="sub" dir="ltr" style="text-align:start">' + esc(c.sub) + "</span>" : "") + '<span class="sub">' + esc(t("chat_noaccess")) + "</span></span></div>";
    return '<a class="cc" href="' + esc(c.href) + '"><span class="cc-i">' + ik + '</span><span class="grow"><span class="cc-k">' + esc(t("k_" + c.kind)) + '</span><b>' + esc(c.label) + '</b>' + (c.sub ? '<span class="sub" dir="ltr" style="text-align:start">' + esc(c.sub) + "</span>" : "") + "</span>" + ic("chev", "flip") + "</a>";
  }
  // Messenger style: a centred time when the talk pauses, one small avatar per run of messages from the same person, pill bubbles, no time inside them
  function sep(a, b) { return !a || new Date(a.at).toDateString() !== new Date(b.at).toDateString() || (new Date(b.at) - new Date(a.at)) > 20 * 60000; }
  function stamp(iso) { return new Date(iso).toDateString() === new Date().toDateString() ? hm(iso) : day(iso) + " · " + hm(iso); }
  function one(m) { return (m.card ? card(m.card) : "") + (m.body ? '<p class="b" data-id="' + m.id + '" title="' + esc(hm(m.at)) + '">' + esc(m.body) + "</p>" : ""); }
  function listHtml() {
    if (!MSGS.length) return '<p class="note rm-empty">' + esc(t("chat_empty")) + "</p>";
    var out = "", i = 0;
    while (i < MSGS.length) {
      var m = MSGS[i], g = [m], j = i + 1;
      if (sep(MSGS[i - 1], m)) out += '<div class="rm-time">' + esc(stamp(m.at)) + "</div>";
      while (j < MSGS.length && MSGS[j].user_id === m.user_id && !sep(MSGS[j - 1], MSGS[j])) { g.push(MSGS[j]); j++; }
      out += '<div class="grp ' + (m.mine ? "me" : "you") + '">' + (m.mine ? "" : A.avatar({ id: m.user_id, name: m.user, avatar: m.av }, "rm-av")) +
        '<div class="stack">' + (!m.mine && META && META.type === "general" ? '<span class="msg-who">' + esc(m.user || "") + "</span>" : "") + g.map(one).join("") + "</div></div>";
      i = j;
    }
    return out;
  }
  function nearBottom() { return window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 160; }
  function toBottom() { var h = document.documentElement, b = h.style.scrollBehavior; h.style.scrollBehavior = "auto"; window.scrollTo(0, h.scrollHeight); h.style.scrollBehavior = b; }

  function render() {
    var title = META ? (META.type === "general" ? t("chat_general") : META.title) : "";
    var sub = META && META.other ? META.other.role_label : (META ? "" : "");
    return '<div class="rm"><div class="dp-bar"><a class="dp-back" href="chat.html" data-act="rmback" aria-label="' + esc(t("back")) + '">' + ic("back", "flip") + '</a><span class="rm-title"><b>' + esc(title) + "</b>" + (sub ? "<small>" + esc(sub) + "</small>" : "") + "</span>" + (META && META.other ? A.avatar(META.other, "rm-hav") : '<span class="dp-act rm-gap"></span>') + "</div>" +
      '<div class="rm-list" id="msgs">' + listHtml() + "</div>" +
      '<form class="rm-comp" id="rmForm" novalidate><button type="button" class="rm-clip" data-act="attach" aria-label="' + esc(t("chat_attach")) + '">' + ic("clip") + '</button>' +
      '<textarea id="rmText" rows="1" maxlength="2000" placeholder="' + esc(t("chat_type")) + '" aria-label="' + esc(t("chat_type")) + '">' + esc(draft) + '</textarea>' +
      '<button type="submit" class="rm-send" aria-label="' + esc(t("chat_send")) + '">' + ic("send") + "</button></form><div class=\"err rm-err\" id=\"rmErr\" role=\"alert\"></div></div>";
  }

  function addMessages(rows) {
    var fresh = rows.filter(function (m) { return m.id > lastId; }); if (!fresh.length) return false;
    var stick = nearBottom(), list = document.getElementById("msgs");
    fresh.forEach(function (m) { MSGS.push(m); lastId = Math.max(lastId, m.id); });
    if (list) list.innerHTML = listHtml();   // the runs of messages are regrouped as new ones arrive
    if (stick || fresh.some(function (m) { return m.mine; })) toBottom();
    return true;
  }
  function poll() {
    if (document.hidden || navigator.onLine === false || !META) return;
    A.api.get("chat/conversations/" + cid + "/messages?after_id=" + lastId, { cache: false }).then(function (r) { addMessages(r.data); }, function () {});
  }
  function init() {
    var tx = document.getElementById("rmText"); if (!tx) return;
    tx.addEventListener("input", function () { draft = tx.value; tx.style.height = "auto"; tx.style.height = Math.min(tx.scrollHeight, 130) + "px"; });
    tx.addEventListener("keydown", function (e) { if (e.key === "Enter" && !e.shiftKey && (window.matchMedia && window.matchMedia("(hover:hover)").matches)) { e.preventDefault(); send(); } });
    if (MSGS.length) toBottom();
    clearInterval(timer); timer = setInterval(poll, 4000);
  }
  function send(extra) {
    var tx = document.getElementById("rmText"), err = document.getElementById("rmErr"), body = { };
    var text = (tx ? tx.value : draft).trim(); if (extra && extra.card) body.card = extra.card; if (text && !(extra && extra.card && extra.keepText)) body.body = text;
    if (!body.body && !body.card) return; if (busy) return;
    if (navigator.onLine === false) { err.textContent = t("chat_offline"); return; }
    busy = true; err.textContent = "";
    A.api.write("POST", "chat/conversations/" + cid + "/messages", body, { online: true }).then(function (r) {
      busy = false; if (body.body) { draft = ""; if (tx) { tx.value = ""; tx.style.height = "auto"; } }
      addMessages([r.data]); toBottom();
    }, function (e) { busy = false; err.textContent = e.offline ? t("chat_offline") : A.msg(e); });
  }
  A.form("rmForm", function () { send(); });
  A.act("rmback", function (el, e) { if (document.referrer && document.referrer.indexOf(location.origin) === 0 && history.length > 1) { e.preventDefault(); history.back(); } });

  /* ---- attach: choose a kind of card, then the item ---- */
  var PICK = null;
  function months6() { var out = [], d = new Date(); for (var i = 0; i < 6; i++) { out.push(d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2)); d.setMonth(d.getMonth() - 1); } return out; }
  A.act("attach", function () {
    var kinds = (META && META.shareable) || [];
    A.sheet(t("chat_attach"), '<p class="note">' + esc(t("chat_pick_kind")) + '</p><div class="list">' + kinds.map(function (k) {
      return '<button class="item" type="button" data-act="attkind" data-kind="' + k + '"><span class="avatar">' + ic(KIND_ICON[k] || "file") + '</span><span class="grow"><b>' + esc(t("k_" + k)) + "</b></span>" + ic("chev", "flip") + "</button>";
    }).join("") + "</div>");
  });
  function items(kind, month) {
    if (kind === "child") return A.api.get("children?per_page=100", { cache: false }).then(function (r) { return r.data.map(function (c) { return { id: c.id, label: A.name(c) || c.ref, sub: c.ref }; }); });
    if (kind === "application") return A.api.get("applications", { cache: false }).then(function (r) { return (r.data.items || r.data).map(function (a) { return { id: a.id, label: (A.lang() === "ar" && a.name_ar ? a.name_ar : (a.name_en || a.name_ar || a.reference)), sub: a.reference + " · " + a.status }; }); });
    if (kind === "project") return A.api.get("projects", { cache: false }).then(function (r) { return r.data.map(function (p) { return { id: p.id, label: A.name(p) || p.name_en, sub: p.status || "" }; }); });
    if (kind === "user") return A.api.get("users", { cache: false }).then(function (r) { return r.data.map(function (u) { return { id: u.id, label: u.name, sub: u.role_label || "" }; }); });
    if (kind === "payment") return A.api.get("payments?month=" + month, { cache: false }).then(function (r) { return r.data.map(function (p) { return { id: p.child_id, label: A.name(p) || p.ref || "", sub: month, month: month }; }); });
    return Promise.resolve([]);
  }
  function showItems(kind, month) {
    A.sheet(t("k_" + kind), '<p class="note">' + esc(t("loading")) + "</p>");
    items(kind, month).then(function (list) {
      PICK = { kind: kind, list: list, month: month };
      A.sheet(t("k_" + kind), (kind === "payment" ? '<div class="filters" role="group">' + months6().map(function (m) { return '<button class="fbtn" type="button" data-act="attmonth" data-v="' + m + '" aria-pressed="' + (m === month) + '">' + m + "</button>"; }).join("") + "</div>" : "") +
        '<input id="attSearch" type="search" placeholder="' + esc(t("chat_search")) + '" aria-label="' + esc(t("chat_search")) + '"><div class="list" id="attList">' + attList(list, "") + '</div><div class="err" id="attErr"></div>');
      var s = document.getElementById("attSearch"); if (s) s.addEventListener("input", function () { document.getElementById("attList").innerHTML = attList(PICK.list, s.value); });
    }, function (e) { A.sheet(t("k_" + kind), '<p class="err">' + esc(A.msg(e)) + "</p>"); });
  }
  function attList(list, q) {
    q = q.trim().toLowerCase(); var rows = list.filter(function (x) { return !q || (x.label + " " + x.sub).toLowerCase().indexOf(q) > -1; }).slice(0, 60);
    return rows.length ? rows.map(function (x) { var i = PICK.list.indexOf(x); return '<button class="item" type="button" data-act="attpick" data-i="' + i + '"><span class="grow"><b>' + esc(x.label) + '</b><span class="sub" dir="ltr" style="text-align:start">' + esc(x.sub) + "</span></span>" + ic("send") + "</button>"; }).join("") : '<p class="note">' + esc(t("chat_none_item")) + "</p>";
  }
  function attach(card) { A.closeSheet(); send({ card: card, keepText: true }); }
  A.act("attkind", function (el) {
    var k = el.getAttribute("data-kind");
    if (k === "site") return attach({ kind: "site" });
    if (k === "report") {
      A.sheet(t("k_report"), '<div class="filters" role="group">' + months6().map(function (m) { return '<button class="fbtn" type="button" data-act="attreport" data-v="' + m + '">' + m + "</button>"; }).join("") + "</div>"); return;
    }
    showItems(k, months6()[0]);
  });
  A.act("attreport", function (el) { attach({ kind: "report", month: el.getAttribute("data-v") }); });
  A.act("attmonth", function (el) { showItems("payment", el.getAttribute("data-v")); });
  A.act("attpick", function (el) {
    var x = PICK.list[parseInt(el.getAttribute("data-i"), 10)], c = { kind: PICK.kind, id: x.id }; if (PICK.kind === "payment") c.month = PICK.month; attach(c);
  });

  function load() {
    if (!cid) return Promise.reject(new Error(t("chat_gone")));
    return A.api.get("chat/conversations/" + cid + "/messages", { cache: false, forceNet: true }).then(function (r) {
      META = Object.assign({}, r.meta.conversation, { shareable: r.meta.shareable }); MSGS = []; lastId = 0; MSGS = r.data.slice(); r.data.forEach(function (m) { lastId = Math.max(lastId, m.id); });
      document.title = (META.type === "general" ? t("chat_general") : META.title) + " | " + t("brand");
      return { data: MSGS };
    });
  }
  A.start({ page: "room", nav: "chat", title: "n_chat", perm: "chat.use", needsConn: true, shell: true, bare: true, load: load, render: render, after: init });
})();
