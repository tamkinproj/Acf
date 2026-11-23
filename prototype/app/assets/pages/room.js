(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  A.strings({ ci_tap: "Tap for settings and members", ci_title: "Chat settings", rm_photo: "Send a photo", rm_voice: "Record a voice message", rm_rec: "Recording…", rm_rec_send: "Send", rm_rec_cancel: "Cancel", rm_no_mic: "Voice messages need the microphone. Allow it in the browser, or this browser cannot record.", rm_sending: "Sending…", rm_dm: "Message {n}", rm_voice_msg: "Voice message", rm_photo_msg: "Photo", rm_photo_item: "Photo", rm_photo_sub: "From your gallery or camera", rm_share: "Or share a record", rm_listen: "Stop and listen", rm_delete: "Delete", rm_play: "Play", rm_pause: "Pause", rm_cant_play: "This voice message cannot be played on this phone or browser." },
    { ci_tap: "اضغط للإعدادات والأعضاء", ci_title: "إعدادات المحادثة", rm_photo: "إرسال صورة", rm_voice: "تسجيل رسالة صوتية", rm_rec: "جارٍ التسجيل…", rm_rec_send: "إرسال", rm_rec_cancel: "إلغاء", rm_no_mic: "الرسائل الصوتية تحتاج الميكروفون. اسمح به في المتصفح، أو أن هذا المتصفح لا يستطيع التسجيل.", rm_sending: "جارٍ الإرسال…", rm_dm: "مراسلة {n}", rm_voice_msg: "رسالة صوتية", rm_photo_msg: "صورة", rm_photo_item: "صورة", rm_photo_sub: "من المعرض أو الكاميرا", rm_share: "أو شارك سجلًا", rm_listen: "إيقاف والاستماع", rm_delete: "حذف", rm_play: "تشغيل", rm_pause: "إيقاف مؤقت", rm_cant_play: "لا يمكن تشغيل هذه الرسالة الصوتية على هذا الهاتف أو المتصفح." });
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
    return '<div class="rm-voice' + (POP[m.id] ? " pop" : "") + '" data-voice="' + m.id + '" role="group" aria-label="' + esc(t("rm_voice_msg")) + '"><button type="button" class="rv-play" data-act="rvplay" data-id="' + m.id + '" aria-label="' + esc(t("rm_play")) + '">' + ic("play") + "</button>" +
      '<span class="rv-wave" data-act="rvseek" data-id="' + m.id + '">' + barsHtml(levelsOf(f.peaks)) + '</span><span class="rv-time" data-ms="' + (f.ms || 0) + '">' + fmt(f.ms || 0) + "</span></div>";
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
      var grpChat = META && (META.type === "general" || META.type === "group"), dm = grpChat && !m.mine && !m.guest && A.can("chat.use") ? ' data-act="dmuser" data-id="' + m.user_id + '" aria-label="' + esc(t("rm_dm", { n: m.user || "" })) + '"' : "";
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
      '<input type="file" id="rmPhotoIn" accept="image/*" hidden>' +
      '<textarea id="rmText" rows="1" maxlength="2000" placeholder="' + esc(t("chat_type")) + '" aria-label="' + esc(t("chat_type")) + '">' + esc(draft) + '</textarea>' +
      '<button type="button" class="rm-clip rm-mic" data-act="rmvoice" aria-label="' + esc(t("rm_voice")) + '">' + ic("mic") + "</button>" +
      '<button type="submit" class="rm-send" aria-label="' + esc(t("chat_send")) + '">' + ic("send") + "</button></form><div class=\"err rm-err\" id=\"rmErr\" role=\"alert\"></div></div>";
  }

  function addMessages(rows) {
    var fresh = rows.filter(function (m) { return m.id > lastId; }); if (!fresh.length) return false;
    var stick = nearBottom(), list = document.getElementById("msgs");
    fresh.forEach(function (m) { MSGS.push(m); lastId = Math.max(lastId, m.id); POP[m.id] = 1; });
    if (list) list.innerHTML = listHtml();   // the runs of messages are regrouped as new ones arrive
    loadFiles(); paintVoice();
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
    loadFiles(); paintVoice();
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
    Array.prototype.forEach.call(document.querySelectorAll("#msgs [data-voice]"), function (el) {
      var id = el.getAttribute("data-voice"); if (FILES[id] !== undefined) return;
      FILES[id] = ""; A.api.blobUrl("chat/conversations/" + cid + "/messages/" + id + "/file").then(function (u) { FILES[id] = u; }, function () { delete FILES[id]; });
    });
  }
  function sendFile(kind, blob, name, ms, peaks) {
    var err = document.getElementById("rmErr"), tx = document.getElementById("rmText"), fd = new FormData();
    if (navigator.onLine === false) { err.textContent = t("chat_offline"); return; }
    fd.append("kind", kind); fd.append("file", blob, name); if (ms) fd.append("ms", String(Math.round(ms))); if (peaks) fd.append("peaks", peaks);
    var text = kind === "image" && tx ? tx.value.trim() : ""; if (text) fd.append("body", text);
    err.textContent = t("rm_sending");
    A.api.upload("chat/conversations/" + cid + "/files", fd).then(function (d) {
      err.textContent = ""; if (text) { draft = ""; tx.value = ""; tx.style.height = "auto"; }
      addMessages([d]); toBottom();
    }, function (e) { err.textContent = e.offline ? t("chat_offline") : A.msg(e); });
  }
  A.act("attphoto", function () {
    var f = document.getElementById("rmPhotoIn"); A.closeSheet(); if (!f) return;
    f.onchange = function () { var file = f.files[0]; f.value = ""; if (file) sendFile("image", file, file.name || "photo.jpg"); };
    f.click();
  });
  A.act("rmimg", function (el) { var u = FILES[el.getAttribute("data-id")]; if (u) window.open(u, "_blank", "noopener"); });

  /* ---- voice messages: a player that shows the shape of the sound ---- */
  var BARS = 32, PLAY = null;   // PLAY: the voice message playing now { id, audio, ms }
  function fmt(ms) { var s = Math.max(0, Math.round(ms / 1000)); return Math.floor(s / 60) + ":" + ("0" + (s % 60)).slice(-2); }
  function levelsOf(peaks) { var out = []; for (var i = 0; i < BARS; i++) { var c = peaks ? peaks.charAt(Math.floor(i * peaks.length / BARS)) : ""; out.push(c ? parseInt(c, 32) / 31 : 0.3 + 0.25 * Math.abs(Math.sin(i * 1.7))); } return out; }
  function barsHtml(levels) { return levels.map(function (v) { return '<i style="height:' + Math.round(16 + v * 84) + '%"></i>'; }).join(""); }
  function durOf(a, ms) { return a && isFinite(a.duration) && a.duration > 0 ? a.duration * 1000 : ms; }
  function paintBars(box, frac) { var bars = box.querySelectorAll("i"); for (var i = 0; i < bars.length; i++) bars[i].classList.toggle("on", (i + 0.5) / bars.length <= frac); }
  function paintVoice() {
    Array.prototype.forEach.call(document.querySelectorAll("#msgs .rm-voice"), function (v) {
      var on = !!(PLAY && String(PLAY.id) === v.getAttribute("data-voice")), a = on ? PLAY.audio : null, going = on && !a.paused;
      var dur = on ? durOf(a, PLAY.ms) : 0, frac = on && dur ? Math.min(1, a.currentTime * 1000 / dur) : 0;
      v.classList.toggle("playing", going);
      var b = v.querySelector(".rv-play"); if (b) { b.innerHTML = ic(going ? "pause" : "play"); b.setAttribute("aria-label", t(going ? "rm_pause" : "rm_play")); }
      paintBars(v.querySelector(".rv-wave"), frac);
      var tm = v.querySelector(".rv-time"); if (tm) tm.textContent = fmt(on && a.currentTime > 0 ? a.currentTime * 1000 : +tm.getAttribute("data-ms"));
    });
  }
  function cantPlay(e) {
    if (e && e.name === "AbortError") return;
    if (e && e.name === "NotAllowedError") { stopPlay(); paintVoice(); return; }   // the phone wanted a fresh tap: the next one plays
    var err = document.getElementById("rmErr"); if (err) err.textContent = t("rm_cant_play");
    if (PLAY) { PLAY.audio.pause(); PLAY = null; paintVoice(); }
  }
  function urlOf(id) { return FILES[id] ? Promise.resolve(FILES[id]) : A.api.blobUrl("chat/conversations/" + cid + "/messages/" + id + "/file").then(function (u) { FILES[id] = u; return u; }); }
  function stopPlay() { if (PLAY) { PLAY.audio.pause(); PLAY = null; } }
  function seekTo(frac) {
    var a = PLAY.audio, go = function () { var dur = durOf(a, PLAY && PLAY.audio === a ? PLAY.ms : 0); if (dur) { a.currentTime = frac * dur / 1000; paintVoice(); } };
    if (a.readyState >= 1) go(); else a.addEventListener("loadedmetadata", go, { once: true });
  }
  function play(id, frac) {
    var m = MSGS.filter(function (x) { return x.id === id; })[0], err = document.getElementById("rmErr");
    if (err) err.textContent = "";
    if (PLAY && PLAY.id === id) { if (frac != null) seekTo(frac); else if (!PLAY.audio.paused) { PLAY.audio.pause(); return; } if (PLAY.audio.paused) PLAY.audio.play().catch(cantPlay); paintVoice(); return; }
    stopPlay(); paintVoice();
    var a = new Audio(); a.preload = "auto"; PLAY = { id: id, audio: a, ms: (m && m.file && m.file.ms) || 0 };   // made inside the tap, so phones allow the sound
    a.addEventListener("timeupdate", paintVoice); a.addEventListener("play", paintVoice); a.addEventListener("pause", paintVoice);
    a.addEventListener("ended", function () { if (PLAY && PLAY.audio === a) PLAY = null; paintVoice(); });
    a.addEventListener("error", function () { if (PLAY && PLAY.audio === a && a.src) cantPlay(); });
    var start = function (u) { if (!PLAY || PLAY.audio !== a) return; a.src = u; if (frac) seekTo(frac); return a.play(); };
    if (FILES[id]) { start(FILES[id]).catch(cantPlay); paintVoice(); return; }   // already here: it starts inside the tap
    urlOf(id).then(start).catch(cantPlay);
  }
  A.act("rvplay", function (el) { play(+el.getAttribute("data-id")); });
  A.act("rvseek", function (el, e) {   // tap the wave to jump there (it starts playing if it was not)
    var r = el.getBoundingClientRect(), frac = Math.max(0, Math.min(1, ((e && e.clientX != null ? e.clientX : r.left) - r.left) / r.width));
    if (getComputedStyle(el).direction === "rtl") frac = 1 - frac;
    play(+el.getAttribute("data-id"), frac);
  });

  /* ---- recording: tap the microphone. The level shows your voice while you talk; Stop to listen first, or Send at once. At most two minutes. ---- */
  var REC = null;   // { state: rec | stopping | review, rec, stream, parts, levels, started, ms, blob, peaks, audio }
  function recUi(html) {
    var form = document.getElementById("rmForm"); if (!form) return;
    var box = document.getElementById("rmRecBox"); if (!box) { box = document.createElement("div"); box.id = "rmRecBox"; box.className = "rm-rec"; box.setAttribute("role", "status"); form.appendChild(box); }
    form.classList.add("recording"); box.innerHTML = html;
  }
  function recEnd() { var form = document.getElementById("rmForm"), box = document.getElementById("rmRecBox"); if (box) box.remove(); if (form) form.classList.remove("recording"); }
  function delBtn() { return '<button type="button" class="rr-btn rr-del" data-act="rmrecx" aria-label="' + esc(t("rm_delete")) + '">' + ic("trash") + "</button>"; }
  function sendBtn() { return '<button type="button" class="rm-send" data-act="rmrecgo" aria-label="' + esc(t("rm_rec_send")) + '">' + ic("send") + "</button>"; }
  function recHtml() {
    return delBtn() + '<span class="rr-pill" aria-label="' + esc(t("rm_rec")) + '"><span class="rm-dot"></span><span class="rm-t" id="rmRecT">0:00</span><canvas class="rr-level" id="rmLevel" aria-hidden="true"></canvas></span>' +
      '<button type="button" class="rr-btn" data-act="rmrecstop" aria-label="' + esc(t("rm_listen")) + '">' + ic("stop") + "</button>" + sendBtn();
  }
  function reviewHtml(r) {
    return delBtn() + '<span class="rr-pill"><button type="button" class="rv-play" data-act="rrplay" aria-label="' + esc(t("rm_play")) + '">' + ic("play") + '</button><span class="rv-wave" id="rrWave">' + barsHtml(levelsOf(r.peaks)) + '</span><span class="rm-t" id="rmRecT">' + fmt(r.ms) + "</span></span>" + sendBtn();
  }
  function peaksOf(levels) {
    if (!levels.length) return "";
    var out = "";
    for (var i = 0; i < BARS; i++) {
      var a = Math.floor(i * levels.length / BARS), b = Math.max(a + 1, Math.floor((i + 1) * levels.length / BARS)), mx = 0;
      for (var j = a; j < b && j < levels.length; j++) mx = Math.max(mx, levels[j]);
      out += Math.min(31, Math.round(mx * 31)).toString(32);
    }
    return out;
  }
  function drawLevel(r) {   // the newest sound at the end, bars sliding along like a voice note being drawn
    var c = document.getElementById("rmLevel"); if (!c) return;
    var dpr = window.devicePixelRatio || 1, w = c.clientWidth, h = c.clientHeight; if (!w || !h) return;
    if (c.width !== Math.round(w * dpr) || c.height !== Math.round(h * dpr)) { c.width = Math.round(w * dpr); c.height = Math.round(h * dpr); }
    var g = c.getContext("2d"), step = 5, n = Math.floor(w / step), lv = r.levels.slice(-n), rtl = getComputedStyle(c).direction === "rtl";
    g.setTransform(dpr, 0, 0, dpr, 0, 0); g.clearRect(0, 0, w, h); g.fillStyle = getComputedStyle(c).color;
    for (var i = 0; i < lv.length; i++) {
      var bh = Math.max(3, lv[i] * h), k = lv.length - i, x = rtl ? (k - 1) * step : w - k * step;
      g.fillRect(x, (h - bh) / 2, 3, bh);
    }
  }
  function meter() { var AC = window.AudioContext || window.webkitAudioContext; if (!AC) return null; try { var c = new AC(); if (c.resume) c.resume().catch(function () {}); return c; } catch (e) { return null; } }
  function listen(r) {   // how loud the voice is, many times a second
    if (!r.ctx) return;
    try { var src = r.ctx.createMediaStreamSource(r.stream); r.an = r.ctx.createAnalyser(); r.an.fftSize = 1024; src.connect(r.an); } catch (e) { r.an = null; }
  }
  function frame(r) {
    var buf = r.an ? new Uint8Array(r.an.fftSize) : null, last = 0;
    (function tick(ts) {
      if (REC !== r || r.state !== "rec") return;
      if (buf && ts - last > 55) {
        r.an.getByteTimeDomainData(buf); var sum = 0; for (var i = 0; i < buf.length; i++) { var v = (buf[i] - 128) / 128; sum += v * v; }
        r.levels.push(Math.max(0, Math.min(1, (Math.sqrt(Math.sqrt(sum / buf.length)) - 0.08) * 1.9))); last = ts; drawLevel(r);
      }
      var el = document.getElementById("rmRecT"), ms = Date.now() - r.started; if (el) el.textContent = fmt(ms);
      if (ms >= 120000) { recStop(r, false); return; }
      r.raf = requestAnimationFrame(tick);
    })(0);
  }
  function release(r) {
    cancelAnimationFrame(r.raf);
    if (r.stream) { r.stream.getTracks().forEach(function (x) { x.stop(); }); r.stream = null; }
    if (r.ctx && r.ctx.close) { r.ctx.close().catch(function () {}); r.ctx = null; }
  }
  function recStop(r, sendNow) {
    if (!r || r.state !== "rec") return;
    r.state = "stopping"; r.sendNow = sendNow; r.ms = Date.now() - r.started; cancelAnimationFrame(r.raf);
    try { r.rec.stop(); } catch (e) { finish(r); }
  }
  function finish(r) {
    release(r);
    if (REC !== r) return;   // deleted meanwhile
    r.blob = new Blob(r.parts, { type: r.mime }); r.peaks = peaksOf(r.levels);
    if (!r.parts.length || r.ms < 700) { REC = null; recEnd(); return; }   // a tap, not a message
    if (r.sendNow) { recSend(r); return; }
    r.state = "review"; recUi(reviewHtml(r));
  }
  function recSend(r) {
    if (r.audio) r.audio.pause(); if (r.url) URL.revokeObjectURL(r.url);
    REC = null; recEnd();
    sendFile("audio", r.blob, "voice." + (/mp4/.test(r.mime) ? "m4a" : /ogg/.test(r.mime) ? "ogg" : "webm"), r.ms, r.peaks);
  }
  function recDelete() {
    var r = REC; if (!r) return; REC = null;
    if (r.state === "rec") { try { r.rec.stop(); } catch (e) {} }
    release(r); if (r.audio) r.audio.pause(); if (r.url) URL.revokeObjectURL(r.url); recEnd();
  }
  A.act("rmvoice", function () {
    var err = document.getElementById("rmErr"); if (REC) return; stopPlay(); paintVoice();
    if (!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder)) { err.textContent = t("rm_no_mic"); return; }
    var ctx = meter();
    navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }).then(function (stream) {
      var type = ["audio/webm;codecs=opus", "audio/mp4", "audio/webm", "audio/ogg;codecs=opus"].filter(function (x) { return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(x); })[0];
      var rec = type ? new MediaRecorder(stream, { mimeType: type }) : new MediaRecorder(stream);
      var r = { state: "rec", rec: rec, stream: stream, ctx: ctx, parts: [], levels: [], started: Date.now(), mime: (rec.mimeType || type || "audio/webm").split(";")[0] };
      rec.ondataavailable = function (e) { if (e.data && e.data.size) r.parts.push(e.data); };
      rec.onstop = function () { finish(r); };
      REC = r; err.textContent = ""; recUi(recHtml());
      listen(r); rec.start(250); frame(r);
    }, function () { if (ctx && ctx.close) ctx.close().catch(function () {}); err.textContent = t("rm_no_mic"); });
  });
  A.act("rmrecstop", function () { recStop(REC, false); });
  A.act("rmrecgo", function () { if (!REC) return; if (REC.state === "rec") recStop(REC, true); else if (REC.state === "review") recSend(REC); });
  A.act("rmrecx", function () { recDelete(); });
  A.act("rrplay", function (el) {   // listen before sending
    var r = REC; if (!r || r.state !== "review") return;
    if (!r.audio) {
      r.url = URL.createObjectURL(r.blob); r.audio = new Audio(r.url);
      var paint = function () {
        var wave = document.getElementById("rrWave"), tm = document.getElementById("rmRecT"), b = document.querySelector("[data-act=rrplay]"), going = !r.audio.paused;
        var dur = durOf(r.audio, r.ms); if (wave) paintBars(wave, dur ? Math.min(1, r.audio.currentTime * 1000 / dur) : 0);
        if (tm) tm.textContent = fmt(r.audio.currentTime > 0 && going ? r.audio.currentTime * 1000 : r.ms);
        if (b) { b.innerHTML = ic(going ? "pause" : "play"); b.setAttribute("aria-label", t(going ? "rm_pause" : "rm_play")); }
      };
      r.audio.addEventListener("timeupdate", paint); r.audio.addEventListener("play", paint); r.audio.addEventListener("pause", paint);
      r.audio.addEventListener("ended", function () { r.audio.currentTime = 0; paint(); });
      r.audio.addEventListener("error", function () { cantPlay(); });
    }
    if (r.audio.paused) r.audio.play().catch(cantPlay); else r.audio.pause();
  });

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
    A.sheet(t("chat_attach"), '<div class="list"><button class="item" type="button" data-act="attphoto"><span class="avatar att-photo">' + ic("camera") + '</span><span class="grow"><b>' + esc(t("rm_photo_item")) + '</b><span class="sub">' + esc(t("rm_photo_sub")) + "</span></span>" + ic("chev", "flip") + "</button></div>" +
      (kinds.length ? '<p class="note att-note">' + esc(t("rm_share")) + '</p><div class="list">' + kinds.map(function (k) {
        return '<button class="item" type="button" data-act="attkind" data-kind="' + k + '"><span class="avatar">' + ic(KIND_ICON[k] || "file") + '</span><span class="grow"><b>' + esc(t("k_" + k)) + "</b></span>" + ic("chev", "flip") + "</button>";
      }).join("") + "</div>" : ""));
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
    clearInterval(timer); recDelete(); stopPlay(); if (id !== opened) draft = ""; opened = id;
    cid = id; HOST = host; MSGS = []; META = null; lastId = 0; POP = {}; busy = false;
    host.innerHTML = '<p class="note rm-loading">' + esc(t("loading")) + "</p>";
    return load().then(function () { if (HOST !== host || cid !== id) return; host.innerHTML = render(); init(); },
      function (e) { if (HOST === host) host.innerHTML = '<p class="err" style="padding:1rem">' + esc(A.msg(e)) + "</p>"; });
  }
  function close() { clearInterval(timer); recDelete(); stopPlay(); HOST = null; }
  A.room = { open: open, close: close };
  // on a computer a conversation opens in the chat page's right-hand pane
  if (document.body.getAttribute("data-page") === "room" && cid && window.matchMedia && window.matchMedia("(min-width:1024px)").matches) { location.replace("chat.html?c=" + cid); return; }
  if (document.body.getAttribute("data-page") === "room") A.start({ page: "room", nav: "chat", title: "n_chat", perm: "chat.use|chat.guest", needsConn: true, shell: true, bare: true, load: load, render: render, after: init });
})();
