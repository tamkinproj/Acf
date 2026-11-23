(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  A.strings({ ci_tap: "Tap for settings and members", ci_title: "Chat settings", rm_photo: "Send a photo", rm_voice: "Record a voice message", rm_rec: "Recording…", rm_rec_send: "Send", rm_rec_cancel: "Cancel", rm_no_mic: "Voice messages need the microphone. Allow it in the browser, or this browser cannot record.", rm_sending: "Sending…", rm_dm: "Message {n}", rm_voice_msg: "Voice message", rm_photo_msg: "Photo" },
    { ci_tap: "اضغط للإعدادات والأعضاء", ci_title: "إعدادات المحادثة", rm_photo: "إرسال صورة", rm_voice: "تسجيل رسالة صوتية", rm_rec: "جارٍ التسجيل…", rm_rec_send: "إرسال", rm_rec_cancel: "إلغاء", rm_no_mic: "الرسائل الصوتية تحتاج الميكروفون. اسمح به في المتصفح، أو أن هذا المتصفح لا يستطيع التسجيل.", rm_sending: "جارٍ الإرسال…", rm_dm: "مراسلة {n}", rm_voice_msg: "رسالة صوتية", rm_photo_msg: "صورة" });
  var FILES = {};   // message id => blob: address of its photo or voice message (fetched with the sign-in token)
  var cid = parseInt((location.search.match(/[?&]c=(\d+)/) || [])[1], 10) || 0;
  var POP = {}, MSGS = [], META = null, lastId = 0, draft = "", timer = null, busy = false;
  var HOST = null, opened = 0;   // HOST: on a computer the conversation is drawn in the chat page's right-hand pane; null on its own page
  var KIND_ICON = { child: "users", application: "file", project: "folder", payment: "wallet", user: "shield", report: "file", site: "globe" };

  function lang() { return A.lang() === "ar" ? "ar" : "en-GB"; }
  function day(iso) {
    var d = new Date(iso), n = new Date(), y = new Date(n.getTime() - 86400000);
    if (d.toDateString() === n.toDateString()) return t("chat_today"); if (d.toDateString() === y.toDateString()) return t("chat_yesterday");
    return d.toLocaleDateString(lang(), { weekday: "short", day: "numeric", month: "short" });
  }
  function hm(iso) { return A.timeOnly(iso); }

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
  function fileHtml(m) {
    var f = m.file; if (!f) return "";
    var src = FILES[m.id] ? ' src="' + esc(FILES[m.id]) + '"' : "";
    if (f.kind === "image") return '<button type="button" class="rm-img' + (POP[m.id] ? " pop" : "") + '" data-act="rmimg" data-id="' + m.id + '" aria-label="' + esc(t("rm_photo_msg")) + '"><img alt="" data-file="' + m.id + '"' + src + "></button>";
    return '<div class="rm-voice' + (POP[m.id] ? " pop" : "") + '">' + ic("bell") + '<audio controls preload="metadata" data-file="' + m.id + '"' + src + ' aria-label="' + esc(t("rm_voice_msg")) + '"></audio></div>';
  }
  function one(m) {
    var c = m.card ? card(m.card) : ""; if (c && POP[m.id]) c = c.replace('class="cc', 'class="pop cc');
    c += fileHtml(m);
    return c + (m.body ? '<p class="b' + (POP[m.id] ? " pop" : "") + '" data-id="' + m.id + '" title="' + esc(hm(m.at)) + '">' + esc(m.body) + "</p>" : "");
  }
  function listHtml() {
    if (!MSGS.length) return '<p class="note rm-empty">' + esc(t("chat_empty")) + "</p>";
    var out = "", i = 0;
    while (i < MSGS.length) {
      var m = MSGS[i], g = [m], j = i + 1;
      if (sep(MSGS[i - 1], m)) out += '<div class="rm-time">' + esc(stamp(m.at)) + "</div>";
      while (j < MSGS.length && MSGS[j].user_id === m.user_id && !sep(MSGS[j - 1], MSGS[j])) { g.push(MSGS[j]); j++; }
      var grpChat = META && (META.type === "general" || META.type === "group"), dm = grpChat && !m.mine ? ' data-act="dmuser" data-id="' + m.user_id + '" aria-label="' + esc(t("rm_dm", { n: m.user || "" })) + '"' : "";
      out += '<div class="grp ' + (m.mine ? "me" : "you") + '">' + (m.mine ? "" : (dm ? '<button type="button" class="rm-avbtn"' + dm + ">" + A.avatar({ id: m.user_id, name: m.user, avatar: m.av }, "rm-av") + "</button>" : A.avatar({ id: m.user_id, name: m.user, avatar: m.av }, "rm-av"))) +
        '<div class="stack">' + (!m.mine && grpChat ? '<button type="button" class="msg-who"' + dm + ">" + esc(m.user || "") + "</button>" : "") + g.map(one).join("") + "</div></div>";
      i = j;
    }
    return out;
  }
  function nearBottom() {
    if (HOST) { var l = document.getElementById("msgs"); return !l || l.scrollHeight - l.scrollTop - l.clientHeight < 160; }
    return window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 160;
  }
  /* show the newest message just above the box you type in. When everything fits on the screen the page stays at the top. */
  function toBottom() {
    if (HOST) { var pl = document.getElementById("msgs"); if (pl) pl.scrollTop = pl.scrollHeight; return; }
    var h = document.documentElement, b = h.style.scrollBehavior, list = document.getElementById("msgs"), comp = document.querySelector(".rm-comp");
    var end = list ? list.getBoundingClientRect().bottom + window.scrollY : h.scrollHeight, boxH = comp ? comp.offsetHeight : 0;
    h.style.scrollBehavior = "auto"; window.scrollTo(0, Math.max(0, Math.round(end + boxH + 8 - window.innerHeight))); h.style.scrollBehavior = b;
  }

  function render() {
    var title = META ? (META.type === "general" ? t("chat_general") : META.title) : "";
    var sub = META && META.other ? META.other.role_label : (META ? "" : "");
    var isGrp = META && META.is_group, info = "chatinfo.html?c=" + cid;
    // Back always goes to the chat list (never to the browser's previous page, which could be the settings and loop back here)
    var head = (HOST ? "" : '<a class="dp-back" href="chat.html" aria-label="' + esc(t("back")) + '">' + ic("back", "flip") + "</a>") +
      (isGrp ? '<a class="rm-title" href="' + info + '" data-act="rminfo"><b>' + esc(title) + "</b><small>" + esc(t("ci_tap")) + "</small></a>" : '<span class="rm-title"><b>' + esc(title) + "</b>" + (sub ? "<small>" + esc(sub) + "</small>" : "") + "</span>") +
      (isGrp ? '<a class="rm-hav grpav" id="rmCover" href="' + info + '" data-act="rminfo" aria-label="' + esc(t("ci_title")) + '">' + ic("users") + "</a>" : (META && META.other ? A.avatar(META.other, "rm-hav") : '<span class="dp-act rm-gap"></span>'));
    return '<div class="rm' + (HOST ? " in-pane" : "") + '"><div class="dp-bar">' + head + "</div>" +
      '<div class="rm-list" id="msgs">' + listHtml() + "</div>" +
      '<form class="rm-comp" id="rmForm" novalidate><button type="button" class="rm-clip" data-act="attach" aria-label="' + esc(t("chat_attach")) + '">' + ic("clip") + '</button>' +
      '<button type="button" class="rm-clip" data-act="rmphoto" aria-label="' + esc(t("rm_photo")) + '">' + ic("camera") + '</button><input type="file" id="rmPhotoIn" accept="image/*" hidden>' +
      '<textarea id="rmText" rows="1" maxlength="2000" placeholder="' + esc(t("chat_type")) + '" aria-label="' + esc(t("chat_type")) + '">' + esc(draft) + '</textarea>' +
      '<button type="button" class="rm-clip rm-mic" data-act="rmvoice" aria-label="' + esc(t("rm_voice")) + '">' + ic("mic") + "</button>" +
      '<button type="submit" class="rm-send" aria-label="' + esc(t("chat_send")) + '">' + ic("send") + "</button></form><div class=\"err rm-err\" id=\"rmErr\" role=\"alert\"></div></div>";
  }

  function addMessages(rows) {
    var fresh = rows.filter(function (m) { return m.id > lastId; }); if (!fresh.length) return false;
    var stick = nearBottom(), list = document.getElementById("msgs");
    fresh.forEach(function (m) { MSGS.push(m); lastId = Math.max(lastId, m.id); POP[m.id] = 1; });
    var playing = list && Array.prototype.filter.call(list.querySelectorAll("audio"), function (a) { return !a.paused; })[0], at = playing ? [playing.getAttribute("data-file"), playing.currentTime] : null;
    if (list) list.innerHTML = listHtml();   // the runs of messages are regrouped as new ones arrive
    loadFiles();
    if (at) { var again = list.querySelector('audio[data-file="' + at[0] + '"]'); if (again && again.src) { again.currentTime = at[1]; again.play().catch(function () {}); } }
    setTimeout(function () { POP = {}; }, 900);
    if (fresh.some(function (m) { return !m.mine; })) A.sound("receive"); else A.sound("send");   // like iMessage: a soft pop in, and a sound
    if (stick || fresh.some(function (m) { return m.mine; })) toBottom();
    return true;
  }
  function poll() {
    if (document.hidden || navigator.onLine === false || !META) return;
    A.api.get("chat/conversations/" + cid + "/messages?after_id=" + lastId, { cache: false }).then(function (r) { addMessages(r.data); }, function () {});
  }
  function init() {
    paintLook();
    var tx = document.getElementById("rmText"); if (!tx) return;
    tx.addEventListener("input", function () { draft = tx.value; tx.style.height = "auto"; tx.style.height = Math.min(tx.scrollHeight, 130) + "px"; });
    tx.addEventListener("keydown", function (e) { if (e.key === "Enter" && !e.shiftKey && (window.matchMedia && window.matchMedia("(hover:hover)").matches)) { e.preventDefault(); send(); } });
    loadFiles();
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

  /* ---- photos and voice messages ---- */
  function loadFiles() {
    Array.prototype.forEach.call(document.querySelectorAll("#msgs [data-file]"), function (el) {
      var id = el.getAttribute("data-file"); if (el.getAttribute("src")) return;
      if (FILES[id]) { el.src = FILES[id]; return; }
      FILES[id] = ""; A.api.blobUrl("chat/conversations/" + cid + "/messages/" + id + "/file").then(function (u) {
        FILES[id] = u; Array.prototype.forEach.call(document.querySelectorAll('#msgs [data-file="' + id + '"]'), function (x) { x.src = u; });
        if (el.tagName === "IMG") el.addEventListener("load", function () { if (nearBottom()) toBottom(); }, { once: true });
      }, function () { delete FILES[id]; });
    });
  }
  function sendFile(kind, blob, name, ms) {
    var err = document.getElementById("rmErr"), tx = document.getElementById("rmText"), fd = new FormData();
    if (navigator.onLine === false) { err.textContent = t("chat_offline"); return; }
    fd.append("kind", kind); fd.append("file", blob, name); if (ms) fd.append("ms", String(Math.round(ms)));
    var text = kind === "image" && tx ? tx.value.trim() : ""; if (text) fd.append("body", text);
    err.textContent = t("rm_sending");
    A.api.upload("chat/conversations/" + cid + "/files", fd).then(function (d) {
      err.textContent = ""; if (text) { draft = ""; tx.value = ""; tx.style.height = "auto"; }
      addMessages([d]); toBottom();
    }, function (e) { err.textContent = e.offline ? t("chat_offline") : A.msg(e); });
  }
  A.act("rmphoto", function () {
    var f = document.getElementById("rmPhotoIn"); if (!f) return;
    f.onchange = function () { var file = f.files[0]; f.value = ""; if (file) sendFile("image", file, file.name || "photo.jpg"); };
    f.click();
  });
  A.act("rmimg", function (el) { var u = FILES[el.getAttribute("data-id")]; if (u) window.open(u, "_blank", "noopener"); });

  // voice: tap the microphone, talk, then Send (or Cancel). At most two minutes.
  var REC = null;
  function recBar(sec) { return '<div class="rm-rec" role="status"><span class="rm-dot"></span><b>' + esc(t("rm_rec")) + '</b><span class="rm-t" id="rmRecT">' + Math.floor(sec / 60) + ":" + ("0" + (sec % 60)).slice(-2) + '</span><button type="button" class="btn btn-line btn-sm" data-act="rmrecx">' + t("rm_rec_cancel") + '</button><button type="button" class="btn btn-main btn-sm" data-act="rmrecgo">' + ic("send") + t("rm_rec_send") + "</button></div>"; }
  function recStop(send) {
    if (!REC) return; var r = REC; REC = null; clearInterval(r.tick); r.send = send;
    try { r.rec.stop(); } catch (e) { r.done(); }
  }
  A.act("rmvoice", function () {
    var err = document.getElementById("rmErr"); if (REC) return;
    if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder)) { err.textContent = t("rm_no_mic"); return; }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      var type = ["audio/webm;codecs=opus", "audio/webm", "audio/mp4", "audio/ogg;codecs=opus"].filter(function (x) { return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(x); })[0];
      var rec = type ? new MediaRecorder(stream, { mimeType: type }) : new MediaRecorder(stream), parts = [], started = Date.now();
      var form = document.getElementById("rmForm"), bar = document.createElement("div"); bar.className = "rm-rec"; bar.innerHTML = recBar(0).replace(/^<div class="rm-rec" role="status">|<\/div>$/g, ""); bar.setAttribute("role", "status"); form.appendChild(bar); form.classList.add("recording");
      var r = { rec: rec, send: false };
      r.done = function () {
        stream.getTracks().forEach(function (x) { x.stop(); }); bar.remove(); form.classList.remove("recording");
        var ms = Date.now() - started, mime = (rec.mimeType || type || "audio/webm").split(";")[0];
        if (r.send && parts.length && ms > 700) sendFile("audio", new Blob(parts, { type: mime }), "voice." + (/mp4/.test(mime) ? "m4a" : /ogg/.test(mime) ? "ogg" : "webm"), ms);
      };
      rec.ondataavailable = function (e) { if (e.data && e.data.size) parts.push(e.data); };
      rec.onstop = r.done;
      r.tick = setInterval(function () { var sec = Math.floor((Date.now() - started) / 1000), el = document.getElementById("rmRecT"); if (el) el.textContent = Math.floor(sec / 60) + ":" + ("0" + (sec % 60)).slice(-2); if (sec >= 120) recStop(true); }, 500);
      REC = r; err.textContent = ""; rec.start(250);
    }, function () { err.textContent = t("rm_no_mic"); });
  });
  A.act("rmrecgo", function () { recStop(true); });
  A.act("rmrecx", function () { recStop(false); });

  // tap a person in a group to message them on their own
  function openDirect(userId) {
    A.api.write("POST", "chat/direct", { user_id: userId }, { online: true }).then(function (r) {
      var id = (r.data || r).id; location.href = (window.matchMedia && window.matchMedia("(min-width:1024px)").matches ? "chat.html?c=" : "room.html?c=") + id;
    }, function (e) { var err = document.getElementById("rmErr"); if (err) err.textContent = A.msg(e); else A.toast(A.msg(e)); });
  }
  A.act("dmuser", function (el) { openDirect(+el.getAttribute("data-id")); });
  A.openDirect = openDirect;
  // group settings: a panel beside the conversation on a computer, their own page on a phone
  A.act("rminfo", function (el, e) {
    if (!HOST || !A.chatinfo) return;
    e.preventDefault(); var host = HOST, id = cid;
    A.chatinfo.open(id, host, function () { open(id, host); });
  });

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

  function paintLook() {
    var h = document.documentElement, c = h.className.split(" ").filter(function (x) { return x.indexOf("th-") !== 0; });
    if (META && META.theme) c.push("th-" + META.theme); h.className = c.join(" ");
    var cv = document.getElementById("rmCover");
    if (cv && META && META.cover) A.api.blobUrl("chat/conversations/" + cid + "/cover").then(function (u) { cv.style.backgroundImage = 'url("' + u + '")'; cv.classList.add("has-photo"); }, function () {});
  }
  function load() {
    if (!cid) return Promise.reject(new Error(t("chat_gone")));
    return A.api.get("chat/conversations/" + cid + "/messages", { cache: false, forceNet: true }).then(function (r) {
      META = Object.assign({}, r.meta.conversation, { shareable: r.meta.shareable }); MSGS = []; lastId = 0; MSGS = r.data.slice(); r.data.forEach(function (m) { lastId = Math.max(lastId, m.id); });
      if (!HOST) document.title = (META.type === "general" ? t("chat_general") : META.title) + " | " + t("brand");
      return { data: MSGS };
    });
  }
  /* Open a conversation inside a pane (the chat page on a computer). */
  function open(id, host) {
    clearInterval(timer); if (id !== opened) draft = ""; opened = id;
    cid = id; HOST = host; MSGS = []; META = null; lastId = 0; POP = {}; busy = false;
    host.innerHTML = '<p class="note rm-loading">' + esc(t("loading")) + "</p>";
    return load().then(function () { if (HOST !== host || cid !== id) return; host.innerHTML = render(); init(); },
      function (e) { if (HOST === host) host.innerHTML = '<p class="err" style="padding:1rem">' + esc(A.msg(e)) + "</p>"; });
  }
  function close() { clearInterval(timer); HOST = null; }
  A.room = { open: open, close: close };
  // on a computer a conversation opens in the chat page's right-hand pane
  if (document.body.getAttribute("data-page") === "room" && cid && window.matchMedia && window.matchMedia("(min-width:1024px)").matches) { location.replace("chat.html?c=" + cid); return; }
  if (document.body.getAttribute("data-page") === "room") A.start({ page: "room", nav: "chat", title: "n_chat", perm: "chat.use", needsConn: true, shell: true, bare: true, load: load, render: render, after: init });
})();
