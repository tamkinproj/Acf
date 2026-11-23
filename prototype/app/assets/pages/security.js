(function () {
  /* Security (System Admin only): who reaches the server, from where, what they opened, what was refused, and the block list.
     Blocking is by hand. Nothing here is saved on the phone, and the office never sees it. */
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  A.strings({
    sc_title: "Security", sc_note: "Who reaches the app's server, from where, and what was refused. Only you see this page. Details are deleted after {n} days.",
    sc_cf_note: "Countries show when the website is reached through Cloudflare.", sc_r_today: "Today", sc_r_7d: "7 days", sc_r_30d: "30 days",
    sc_requests: "Requests", sc_visitors: "Addresses", sc_countries: "Countries", sc_signins: "Sign-ins", sc_failed: "Wrong passwords", sc_blocked: "Blocked requests", sc_throttled: "Too many requests",
    sc_chart_h: "Requests per hour", sc_chart_d: "Requests per day", sc_table: "Show as a table", sc_time: "Time", sc_none: "Nothing yet.",
    sc_by_country: "Countries", sc_unknown: "Unknown", sc_pages: "Most opened", sc_top: "Busiest addresses", sc_all: "All addresses", sc_recent: "Recent events", sc_all_ev: "All events",
    sc_addr_n: "{n} addresses", sc_addr_1: "1 address", sc_tip_req: "{n} requests", sc_tip_req1: "1 request", sc_tip_bl: "{n} blocked requests", sc_tip_bl1: "1 blocked request", sc_you: "You", sc_is_blocked: "Blocked", sc_last: "Last seen {d}", sc_req_n: "{n} requests",
    k_ev_blocked: "Blocked", k_ev_login: "Signed in", k_ev_login_failed: "Wrong password", k_ev_login_locked: "Sign-in locked", k_ev_throttled: "Too many requests", k_ev_denied: "Refused", k_ev_not_found: "Not found", sc_ev_all: "All",
    sc_blocks: "Blocked addresses", sc_blocks_note: "A blocked address cannot sign in or use the app at all. Your own trusted device is never blocked, so you can always undo a block.",
    sc_block_btn: "Block an address", sc_no_blocks: "No address is blocked.", sc_until: "Until {d}", sc_forever: "Until unblocked", sc_ended: "Ended {d}", sc_by: "by {n}", sc_unblock: "Unblock", sc_unblock_q: "Unblock {ip}?",
    sc_ip: "Address (IP)", sc_reason: "Reason (only you see it)", sc_for: "For how long", sc_d1: "1 day", sc_d7: "7 days", sc_d30: "30 days", sc_d90: "90 days", sc_dforever: "Until I unblock it", sc_block_go: "Block", sc_block_any: "Block anyway",
    sc_blocked_ok: "Address blocked", sc_unblocked_ok: "Address unblocked",
    sc_settings: "Settings", sc_logging: "Record visitors", sc_logging_note: "Counts every request by address and page. Turn off if the hosting is struggling; blocking still works.",
    sc_blocking: "Block list on", sc_blocking_note: "Off: blocked addresses are let in again (the list is kept).", sc_keep: "Keep details for", sc_days: "{n} days", sc_saved: "Saved",
    sc_addr: "Address", sc_first: "First seen", sc_lastseen: "Last seen", sc_devices: "Devices and browsers", sc_accounts: "{n} signed-in account(s) used this address in the last 7 days. It may be the office's shared address or a family's phone: blocking it locks them out too.",
    sc_is_me: "This is the address you are using now.", sc_cf_addr: "This is one of Cloudflare's own addresses: the server is not reading the visitor's real address.", sc_more: "Show more"
  }, {
    sc_title: "الأمان", sc_note: "من يصل إلى خادم التطبيق ومن أين وما الذي رُفض. أنت وحدك ترى هذه الصفحة. تُحذف التفاصيل بعد {n} يومًا.",
    sc_cf_note: "تظهر الدول عندما يُوصل إلى الموقع عبر كلاودفلير.", sc_r_today: "اليوم", sc_r_7d: "7 أيام", sc_r_30d: "30 يومًا",
    sc_requests: "الطلبات", sc_visitors: "العناوين", sc_countries: "الدول", sc_signins: "مرات الدخول", sc_failed: "كلمات مرور خاطئة", sc_blocked: "طلبات محظورة", sc_throttled: "طلبات كثيرة",
    sc_chart_h: "الطلبات في الساعة", sc_chart_d: "الطلبات في اليوم", sc_table: "عرض كجدول", sc_time: "الوقت", sc_none: "لا شيء بعد.",
    sc_by_country: "الدول", sc_unknown: "غير معروف", sc_pages: "الأكثر فتحًا", sc_top: "أكثر العناوين نشاطًا", sc_all: "كل العناوين", sc_recent: "أحدث الأحداث", sc_all_ev: "كل الأحداث",
    sc_addr_n: "{n} عنوانًا", sc_addr_1: "عنوان واحد", sc_tip_req: "{n} طلبًا", sc_tip_req1: "طلب واحد", sc_tip_bl: "{n} طلبًا محظورًا", sc_tip_bl1: "طلب محظور واحد", sc_you: "أنت", sc_is_blocked: "محظور", sc_last: "آخر ظهور {d}", sc_req_n: "{n} طلبًا",
    k_ev_blocked: "محظور", k_ev_login: "دخول", k_ev_login_failed: "كلمة مرور خاطئة", k_ev_login_locked: "دخول مقفل", k_ev_throttled: "طلبات كثيرة", k_ev_denied: "مرفوض", k_ev_not_found: "غير موجود", sc_ev_all: "الكل",
    sc_blocks: "العناوين المحظورة", sc_blocks_note: "العنوان المحظور لا يستطيع تسجيل الدخول ولا استخدام التطبيق. جهازك الموثوق لا يُحظر أبدًا، لتتمكن دائمًا من إلغاء الحظر.",
    sc_block_btn: "حظر عنوان", sc_no_blocks: "لا يوجد عنوان محظور.", sc_until: "حتى {d}", sc_forever: "حتى إلغاء الحظر", sc_ended: "انتهى {d}", sc_by: "بواسطة {n}", sc_unblock: "إلغاء الحظر", sc_unblock_q: "إلغاء حظر {ip}؟",
    sc_ip: "العنوان (IP)", sc_reason: "السبب (تراه أنت فقط)", sc_for: "المدة", sc_d1: "يوم واحد", sc_d7: "7 أيام", sc_d30: "30 يومًا", sc_d90: "90 يومًا", sc_dforever: "حتى ألغي الحظر", sc_block_go: "حظر", sc_block_any: "احظر على أي حال",
    sc_blocked_ok: "تم حظر العنوان", sc_unblocked_ok: "تم إلغاء الحظر",
    sc_settings: "الإعدادات", sc_logging: "تسجيل الزوار", sc_logging_note: "يعدّ كل طلب حسب العنوان والصفحة. أطفئه إذا كانت الاستضافة مثقلة؛ الحظر يبقى يعمل.",
    sc_blocking: "تفعيل قائمة الحظر", sc_blocking_note: "عند الإطفاء يُسمح للعناوين المحظورة بالدخول (تبقى القائمة محفوظة).", sc_keep: "الاحتفاظ بالتفاصيل", sc_days: "{n} يومًا", sc_saved: "تم الحفظ",
    sc_addr: "العنوان", sc_first: "أول ظهور", sc_lastseen: "آخر ظهور", sc_devices: "الأجهزة والمتصفحات", sc_accounts: "{n} حساب(ات) مسجّلة الدخول استخدمت هذا العنوان في آخر 7 أيام. قد يكون عنوان المكتب المشترك أو هاتف أسرة: حظره يمنعهم أيضًا.",
    sc_is_me: "هذا هو العنوان الذي تستخدمه الآن.", sc_cf_addr: "هذا أحد عناوين كلاودفلير نفسها: الخادم لا يقرأ عنوان الزائر الحقيقي.", sc_more: "عرض المزيد"
  });
  var range = "today", DATA = null, timer = null, PENDING = null;
  try { range = localStorage.getItem("acf_sc_range") || "today"; } catch (e) {}
  if (["today", "7d", "30d"].indexOf(range) < 0) range = "today";

  function num(n) { n = +n || 0; return n >= 1e6 ? (n / 1e6).toFixed(1).replace(/\.0$/, "") + "M" : n >= 1e4 ? (n / 1e3).toFixed(1).replace(/\.0$/, "") + "K" : n.toLocaleString(A.lang() === "ar" ? "ar" : "en"); }
  function flag(c) { return c && /^[A-Z]{2}$/.test(c) ? String.fromCodePoint(0x1F1E6 + c.charCodeAt(0) - 65, 0x1F1E6 + c.charCodeAt(1) - 65) : ""; }
  function cname(c) {
    if (!c) return t("sc_unknown");
    try { return new Intl.DisplayNames([A.lang() === "ar" ? "ar" : "en"], { type: "region" }).of(c) || c; } catch (e) { return c; }
  }
  function device(ua) {
    ua = ua || ""; var os = /iPhone|iPad/.test(ua) ? "iPhone" : /Android/.test(ua) ? "Android" : /Windows/.test(ua) ? "Windows" : /Mac OS/.test(ua) ? "Mac" : /Linux/.test(ua) ? "Linux" : "";
    var br = /Edg\//.test(ua) ? "Edge" : /SamsungBrowser/.test(ua) ? "Samsung Internet" : /Firefox|FxiOS/.test(ua) ? "Firefox" : /Chrome|CriOS/.test(ua) ? "Chrome" : /Safari/.test(ua) ? "Safari" : /curl|python|bot|spider|crawl/i.test(ua) ? ua.split(/[\/ ]/)[0] : "";
    return (os && br ? os + " · " + br : os || br || ua.slice(0, 60)) || "?";
  }
  function when(iso) { return iso ? A.dateTime(iso) : ""; }
  function kindChip(k) { var cls = k === "login" ? "" : (k === "login_failed" || k === "throttled" || k === "not_found") ? " grey" : " warn"; return '<span class="badge' + cls + '">' + esc(t("k_ev_" + k)) + "</span>"; }
  function ipBtn(ip) { return '<button type="button" class="sc-ip" data-act="scip" data-ip="' + esc(ip) + '" dir="ltr">' + esc(ip) + "</button>"; }

  /* ---- the chart: requests per hour (today) or per day, one series, thin columns, a tooltip per column, a table view ---- */
  function niceMax(v) { if (v <= 4) return 4; var p = Math.pow(10, Math.floor(Math.log10(v))), m = v / p; return (m <= 1 ? 1 : m <= 2 ? 2 : m <= 5 ? 5 : 10) * p; }
  function tick(s, i, daily, n) {
    var d = new Date(s.t);
    if (!daily) return i % 3 === 0 ? ("0" + d.getHours()).slice(-2) : "";
    if (n <= 7) return d.toLocaleDateString(A.lang() === "ar" ? "ar" : "en-GB", { weekday: "short" });
    return i % 5 === 0 || i === n - 1 ? d.getDate() + "/" + (d.getMonth() + 1) : "";
  }
  function slot(s, daily) { var d = new Date(s.t); return daily ? A.dateOnly(d) : A.dateOnly(d) + ", " + ("0" + d.getHours()).slice(-2) + ":00"; }
  function chart(d) {
    var s = d.series, max = niceMax(Math.max.apply(null, s.map(function (x) { return x.requests; }).concat([0]))), n = s.length;
    var grid = [max, max / 2, 0].map(function (v) { return '<div class="sc-gl" style="bottom:' + (v / max * 100) + '%"><span>' + num(v) + "</span></div>"; }).join("");
    var bars = s.map(function (x, i) {
      var h = x.requests ? Math.max(1.5, x.requests / max * 100) : 0;
      return '<div class="sc-col" tabindex="0" data-i="' + i + '" aria-label="' + esc(slot(x, d.daily) + ": " + num(x.requests)) + '"><i style="height:' + h + '%"></i></div>';
    }).join("");
    var ticks = s.map(function (x, i) { return "<span>" + esc(tick(x, i, d.daily, n)) + "</span>"; }).join("");
    var rows = s.slice().reverse().map(function (x) { return "<tr><td>" + esc(slot(x, d.daily)) + "</td><td>" + num(x.requests) + "</td><td>" + num(x.blocked) + "</td></tr>"; }).join("");
    return '<div class="card sc-chart"><h3>' + esc(t(d.daily ? "sc_chart_d" : "sc_chart_h")) + '</h3><div class="sc-plot" id="scPlot"><div class="sc-grid">' + grid + '</div><div class="sc-bars" style="--n:' + n + '">' + bars + '</div><div class="sc-tip" id="scTip" hidden></div></div>' +
      '<div class="sc-ticks" style="--n:' + n + '">' + ticks + "</div>" +
      '<details class="sc-tbl"><summary>' + esc(t("sc_table")) + '</summary><div class="sc-tblw"><table><thead><tr><th>' + esc(t("sc_time")) + "</th><th>" + esc(t("sc_requests")) + "</th><th>" + esc(t("sc_blocked")) + "</th></tr></thead><tbody>" + rows + "</tbody></table></div></details></div>";
  }
  function tipShow(col) {
    var tip = document.getElementById("scTip"), plot = document.getElementById("scPlot"); if (!tip || !plot || !DATA) return;
    var x = DATA.o.series[+col.getAttribute("data-i")]; if (!x) return;
    tip.textContent = "";
    var v = document.createElement("b"); v.textContent = x.requests === 1 ? t("sc_tip_req1") : t("sc_tip_req", { n: num(x.requests) }); tip.appendChild(v);
    if (x.blocked) { var bl = document.createElement("span"); bl.textContent = x.blocked === 1 ? t("sc_tip_bl1") : t("sc_tip_bl", { n: num(x.blocked) }); tip.appendChild(bl); }
    var w = document.createElement("small"); w.textContent = slot(x, DATA.o.daily); tip.appendChild(w);
    tip.hidden = false;
    var pr = plot.getBoundingClientRect(), cr = col.getBoundingClientRect(), tw = tip.offsetWidth;
    tip.style.left = Math.max(0, Math.min(pr.width - tw, cr.left - pr.left + cr.width / 2 - tw / 2)) + "px";
    Array.prototype.forEach.call(document.querySelectorAll(".sc-col.on"), function (c) { c.classList.remove("on"); }); col.classList.add("on");
  }
  function tipHide() { var tip = document.getElementById("scTip"); if (tip) tip.hidden = true; Array.prototype.forEach.call(document.querySelectorAll(".sc-col.on"), function (c) { c.classList.remove("on"); }); }

  /* ---- the page ---- */
  function tile(k, v) { return '<div class="stat sc-stat"><span class="sc-lbl">' + esc(t(k)) + '</span><div class="num">' + num(v) + "</div></div>"; }
  function visitorRow(v, me) {
    var sub = [cname(v.country), t("sc_req_n", { n: num(v.requests) })]; if (v.failed) sub.push(num(v.failed) + " " + t("sc_failed").toLowerCase()); sub.push(t("sc_last", { d: when(v.last_at) }));
    return '<button type="button" class="item sc-row" data-act="scip" data-ip="' + esc(v.ip) + '"><span class="sc-flag" aria-hidden="true">' + (flag(v.country) || ic("globe")) + '</span><span class="grow"><b dir="ltr" style="text-align:start">' + esc(v.ip) +
      (v.ip === me ? ' <span class="badge grey">' + esc(t("sc_you")) + "</span>" : "") + (v.is_blocked ? ' <span class="badge warn">' + esc(t("sc_is_blocked")) + "</span>" : "") + '</b><span class="sub">' + esc(sub.join(" · ")) + "</span></span>" + ic("chev", "flip") + "</button>";
  }
  function eventRow(e) {
    return '<div class="sc-ev">' + kindChip(e.kind) + '<span class="grow"><span class="sc-evl">' + ipBtn(e.ip) + (e.country ? ' <span aria-hidden="true">' + flag(e.country) + "</span>" : "") + ' <code dir="ltr">' + esc(e.method + " " + e.path) + "</code></span>" +
      '<span class="sub">' + esc(when(e.at) + " · " + e.status + (e.user_agent ? " · " + device(e.user_agent) : "")) + "</span></span></div>";
  }
  function blockRow(b) {
    var until = b.expires_at ? (b.active ? t("sc_until", { d: when(b.expires_at) }) : t("sc_ended", { d: when(b.expires_at) })) : t("sc_forever");
    return '<div class="item sc-block' + (b.active ? "" : " off") + '"><span class="grow"><b dir="ltr" style="text-align:start">' + esc(b.ip) + '</b><span class="sub">' + esc([until, b.reason || "", b.by ? t("sc_by", { n: b.by }) : ""].filter(Boolean).join(" · ")) + "</span></span>" +
      (b.active ? '<button class="btn btn-line btn-sm" type="button" data-act="scunblock" data-id="' + b.id + '" data-ip="' + esc(b.ip) + '">' + esc(t("sc_unblock")) + "</button>" : "") + "</div>";
  }
  function sw(on, k) { return '<button class="switch" type="button" role="switch" aria-checked="' + !!on + '" data-act="scset" data-k="' + k + '"></button>'; }
  function render(d) {
    DATA = d; var o = d.o, me = o.my_ip, st = o.settings;
    var head = '<div class="head"><h1 class="title">' + esc(t("sc_title")) + '</h1></div><p class="note">' + esc(t("sc_note", { n: st.retention_days })) + "</p>" +
      (!o.via_cloudflare && !o.totals.countries ? '<p class="note sc-cf">' + ic("globe") + " " + esc(t("sc_cf_note")) + "</p>" : "");
    var filters = '<div class="filters sc-range" role="group">' + ["today", "7d", "30d"].map(function (k) { return '<button class="fbtn" type="button" data-act="scrange" data-v="' + k + '" aria-pressed="' + (range === k) + '">' + esc(t("sc_r_" + k)) + "</button>"; }).join("") + "</div>";
    var tiles = '<div class="sc-tiles">' + tile("sc_requests", o.totals.requests) + tile("sc_visitors", o.totals.visitors) + tile("sc_countries", o.totals.countries) + tile("sc_signins", o.totals.signins) + tile("sc_failed", o.totals.failed) + tile("sc_blocked", o.totals.blocked) + tile("sc_throttled", o.totals.throttled) + "</div>";
    var none = '<p class="note">' + esc(t("sc_none")) + "</p>";
    var countries = '<div class="card"><h3>' + esc(t("sc_by_country")) + "</h3>" + (o.countries.length ? '<div class="sc-list">' + o.countries.map(function (c) {
      return '<div class="sc-li"><span class="sc-flag" aria-hidden="true">' + (flag(c.code) || ic("globe")) + '</span><span class="grow">' + esc(cname(c.code)) + '</span><span class="sc-n">' + num(c.requests) + '</span><span class="sc-n sub">' + esc(c.visitors === 1 ? t("sc_addr_1") : t("sc_addr_n", { n: num(c.visitors) })) + "</span></div>";
    }).join("") + "</div>" : none) + "</div>";
    var pages = '<div class="card"><h3>' + esc(t("sc_pages")) + "</h3>" + (o.paths.length ? '<div class="sc-list">' + o.paths.map(function (p) { return '<div class="sc-li"><code class="grow" dir="ltr">' + esc(p.path) + '</code><span class="sc-n">' + num(p.requests) + "</span></div>"; }).join("") + "</div>" : none) + "</div>";
    var visitors = '<div class="card"><div class="sc-h"><h3>' + esc(t("sc_top")) + '</h3><button class="btn btn-line btn-sm" type="button" data-act="scall">' + esc(t("sc_all")) + '</button></div><div class="list">' + (o.visitors.length ? o.visitors.map(function (v) { return visitorRow(v, me); }).join("") : none) + "</div></div>";
    var events = '<div class="card"><div class="sc-h"><h3>' + esc(t("sc_recent")) + '</h3><button class="btn btn-line btn-sm" type="button" data-act="scevents">' + esc(t("sc_all_ev")) + "</button></div>" + (o.events.length ? '<div class="sc-evs">' + o.events.map(eventRow).join("") + "</div>" : none) + "</div>";
    var blocks = '<div class="card" id="scBlocks"><div class="sc-h"><h3>' + esc(t("sc_blocks")) + '</h3><button class="btn btn-main btn-sm" type="button" data-act="scblock">' + ic("shield") + "<span>" + esc(t("sc_block_btn")) + '</span></button></div><p class="note" style="margin-top:0">' + esc(t("sc_blocks_note")) + '</p><div class="list">' +
      (d.blocks.length ? d.blocks.map(blockRow).join("") : '<p class="note">' + esc(t("sc_no_blocks")) + "</p>") + "</div></div>";
    var settings = '<div class="card"><h3>' + esc(t("sc_settings")) + "</h3>" +
      '<div class="setrow"><span class="grow"><b>' + esc(t("sc_logging")) + '</b><span class="sub" style="display:block">' + esc(t("sc_logging_note")) + "</span></span>" + sw(st.logging, "logging") + "</div>" +
      '<div class="setrow"><span class="grow"><b>' + esc(t("sc_blocking")) + '</b><span class="sub" style="display:block">' + esc(t("sc_blocking_note")) + "</span></span>" + sw(st.blocking, "blocking") + "</div>" +
      '<div class="setrow"><span class="grow"><b>' + esc(t("sc_keep")) + '</b></span><select id="scKeep" aria-label="' + esc(t("sc_keep")) + '">' + [7, 14, 30, 60, 90].map(function (n) { return '<option value="' + n + '"' + (n === st.retention_days ? " selected" : "") + ">" + esc(t("sc_days", { n: n })) + "</option>"; }).join("") + "</select></div></div>";
    return '<div class="sc">' + head + filters + tiles + chart(o) + '<div class="sc-two">' + countries + pages + "</div>" + '<div class="sc-two">' + visitors + events + "</div>" + blocks + settings + "</div>";
  }
  function load() {
    return Promise.all([A.api.get("system/traffic?range=" + range, { cache: false }), A.api.get("system/traffic/blocks", { cache: false })]).then(function (r) { return { data: { o: r[0].data, blocks: r[1].data.blocks } }; });
  }
  function after() {
    var plot = document.getElementById("scPlot");
    if (plot) {
      plot.addEventListener("pointermove", function (e) { var c = e.target.closest && e.target.closest(".sc-col"); if (c) tipShow(c); });
      plot.addEventListener("pointerleave", tipHide);
      plot.addEventListener("focusin", function (e) { if (e.target.classList.contains("sc-col")) tipShow(e.target); });
      plot.addEventListener("focusout", tipHide);
    }
    var keep = document.getElementById("scKeep");
    if (keep) keep.addEventListener("change", function () { save({ retention_days: +keep.value }); });
    clearInterval(timer);   // the overview stays current while the page is open
    timer = setInterval(function () { if (!document.hidden && navigator.onLine !== false && !(A.$("#sheetRoot") && A.$("#sheetRoot").innerHTML)) A.reload(true); }, 30000);
  }
  function save(body) {
    A.api.write("PATCH", "system/traffic/settings", body, { online: true }).then(function () { A.toast(t("sc_saved")); A.reload(true); }, function (e) { A.toast(A.msg(e)); A.reload(true); });
  }

  A.act("scrange", function (el) { range = el.getAttribute("data-v"); try { localStorage.setItem("acf_sc_range", range); } catch (e) {} A.reload(false); });
  A.act("scset", function (el) { var b = {}; b[el.getAttribute("data-k")] = el.getAttribute("aria-checked") !== "true"; el.disabled = true; save(b); });

  /* one address: everything about it, and Block / Unblock */
  A.act("scip", function (el) {
    var ip = el.getAttribute("data-ip");
    A.sheet(ip, '<p class="note">' + esc(t("loading")) + "</p>");
    A.api.get("system/traffic/address?ip=" + encodeURIComponent(ip), { cache: false }).then(function (r) {
      var a = r.data, row = function (k, v) { return v || v === 0 ? '<div class="setrow"><span class="grow">' + esc(t(k)) + '</span><b dir="auto">' + esc(String(v)) + "</b></div>" : ""; };
      var warn = (a.is_me ? '<p class="banner">' + esc(t("sc_is_me")) + "</p>" : "") + (a.cloudflare ? '<p class="banner warn">' + esc(t("sc_cf_addr")) + "</p>" : "") + (a.accounts_7d ? '<p class="banner warn">' + esc(t("sc_accounts", { n: a.accounts_7d })) + "</p>" : "");
      var countries = a.countries.length ? a.countries.map(function (c) { return flag(c) + " " + cname(c); }).join(", ") : t("sc_unknown");
      var html = warn + '<div class="sc-addr">' + row("sc_by_country", countries) + row("sc_first", when(a.first_at)) + row("sc_lastseen", when(a.last_at)) + row("sc_requests", num(a.requests)) + row("sc_signins", num(a.signins)) +
        row("sc_failed", num(a.failed)) + row("sc_blocked", num(a.blocked_requests)) + row("sc_throttled", num(a.throttled)) + "</div>" +
        (a.devices.length ? '<h4 class="optlbl">' + esc(t("sc_devices")) + '</h4><ul class="sc-dev">' + a.devices.map(function (u) { return '<li title="' + esc(u) + '">' + esc(device(u)) + "</li>"; }).join("") + "</ul>" : "") +
        (a.events.length ? '<h4 class="optlbl">' + esc(t("sc_recent")) + '</h4><div class="sc-evs">' + a.events.map(eventRow).join("") + "</div>" : "") +
        '<div class="actions">' + (a.block && a.block.active ? '<button class="btn btn-main" type="button" data-act="scunblock" data-id="' + a.block.id + '" data-ip="' + esc(a.ip) + '">' + esc(t("sc_unblock")) + "</button>"
          : (!a.is_me && !a.cloudflare ? '<button class="btn btn-main" type="button" data-act="scblock" data-ip="' + esc(a.ip) + '">' + ic("shield") + " " + esc(t("sc_block_btn")) + "</button>" : "")) +
        '<button class="btn btn-line" type="button" data-act="close">' + esc(t("cancel")) + "</button></div>";
      A.sheet(a.ip, html);
    }, function (e) { A.sheet(ip, '<p class="err">' + esc(A.msg(e)) + "</p>"); });
  });

  /* block by hand */
  A.act("scblock", function (el) {
    var ip = el.getAttribute("data-ip") || ""; PENDING = null;
    A.sheet(t("sc_block_btn"), '<form id="scBlockForm" novalidate>' + A.field("sc_f_ip", esc(t("sc_ip")), "", "text", 'dir="ltr" autocomplete="off" inputmode="text" value="' + esc(ip) + '" placeholder="203.0.113.7"') +
      A.field("sc_f_reason", esc(t("sc_reason")), "", "text", 'maxlength="200" autocomplete="off"') +
      '<div><label for="sc_f_days">' + esc(t("sc_for")) + '</label><select id="sc_f_days"><option value="1">' + esc(t("sc_d1")) + '</option><option value="7">' + esc(t("sc_d7")) + '</option><option value="30">' + esc(t("sc_d30")) + '</option><option value="90">' + esc(t("sc_d90")) + '</option><option value="" selected>' + esc(t("sc_dforever")) + "</option></select></div>" +
      '<div id="scWarn"></div><div class="err" id="scErr" role="alert"></div><div class="actions"><button class="btn btn-main" type="submit" id="scGo">' + esc(t("sc_block_go")) + '</button><button class="btn btn-line" type="button" data-act="close">' + esc(t("cancel")) + "</button></div></form>");
  });
  A.form("scBlockForm", function () {
    var ip = document.getElementById("sc_f_ip").value.trim(), days = document.getElementById("sc_f_days").value, err = document.getElementById("scErr"), go = document.getElementById("scGo");
    err.textContent = ""; document.getElementById("sc_f_ipErr").textContent = "";
    var body = { ip: ip, reason: document.getElementById("sc_f_reason").value.trim() || null, days: days ? +days : null };
    if (PENDING === ip) body.confirm = true;   // "Block anyway" after the warning
    go.disabled = true;
    A.api.write("POST", "system/traffic/blocks", body, { online: true }).then(function () { PENDING = null; A.closeSheet(); A.toast(t("sc_blocked_ok")); A.reload(true); }, function (e) {
      go.disabled = false;
      if (e.code === "IP_IN_USE") { PENDING = ip; document.getElementById("scWarn").innerHTML = '<p class="banner warn">' + esc(e.message) + "</p>"; go.textContent = t("sc_block_any"); return; }
      if (e.errors && e.errors.ip) { document.getElementById("sc_f_ipErr").textContent = e.code === "OWN_ADDRESS" || e.code === "CLOUDFLARE_ADDRESS" ? e.message : e.errors.ip[0]; return; }
      err.textContent = A.msg(e);
    });
  });
  A.act("scunblock", function (el) {
    if (!confirm(t("sc_unblock_q", { ip: el.getAttribute("data-ip") }))) return;
    el.disabled = true;
    A.api.write("DELETE", "system/traffic/blocks/" + el.getAttribute("data-id"), undefined, { online: true }).then(function () { A.closeSheet(); A.toast(t("sc_unblocked_ok")); A.reload(true); }, function (e) { el.disabled = false; A.toast(A.msg(e)); });
  });

  /* every address, searchable; every event, by kind */
  var ALLQ = "", ALLP = 1, EVK = "", EVP = 1;
  function allAddresses(more) {
    var box = document.getElementById("scAllList"); if (!box) return;
    if (!more) { ALLP = 1; box.innerHTML = '<p class="note">' + esc(t("loading")) + "</p>"; }
    A.api.get("system/traffic/visitors?range=" + range + "&page=" + ALLP + (ALLQ ? "&q=" + encodeURIComponent(ALLQ) : ""), { cache: false }).then(function (r) {
      var html = r.data.map(function (v) { return visitorRow(v, DATA && DATA.o.my_ip); }).join("") || '<p class="note">' + esc(t("none")) + "</p>";
      var last = r.meta && r.meta.last_page > ALLP; var old = document.getElementById("scAllMore"); if (old) old.remove();
      if (more) box.insertAdjacentHTML("beforeend", html); else box.innerHTML = html;
      if (last) box.insertAdjacentHTML("afterend", '<button class="btn btn-line btn-sm" type="button" id="scAllMore" data-act="scallmore">' + esc(t("sc_more")) + "</button>");
    }, function (e) { box.innerHTML = '<p class="err">' + esc(A.msg(e)) + "</p>"; });
  }
  A.act("scall", function () {
    ALLQ = "";
    A.sheet(t("sc_all"), '<div class="search">' + ic("search") + '<input id="scAllQ" type="search" dir="ltr" placeholder="203.0.113 · PH" aria-label="' + esc(t("search")) + '" autocomplete="off"></div><div class="list" id="scAllList"></div>');
    var q = document.getElementById("scAllQ"), tmr = null;
    q.addEventListener("input", function () { clearTimeout(tmr); tmr = setTimeout(function () { ALLQ = q.value.trim(); allAddresses(false); }, 300); });
    allAddresses(false);
  });
  A.act("scallmore", function () { ALLP++; allAddresses(true); });
  function allEvents(more) {
    var box = document.getElementById("scEvList"); if (!box) return;
    if (!more) { EVP = 1; box.innerHTML = '<p class="note">' + esc(t("loading")) + "</p>"; }
    A.api.get("system/traffic/events?range=" + range + "&page=" + EVP + (EVK ? "&kind=" + EVK : ""), { cache: false }).then(function (r) {
      var html = r.data.map(eventRow).join("") || '<p class="note">' + esc(t("none")) + "</p>";
      var old = document.getElementById("scEvMore"); if (old) old.remove();
      if (more) box.insertAdjacentHTML("beforeend", html); else box.innerHTML = html;
      if (r.meta && r.meta.last_page > EVP) box.insertAdjacentHTML("afterend", '<button class="btn btn-line btn-sm" type="button" id="scEvMore" data-act="scevmore">' + esc(t("sc_more")) + "</button>");
    }, function (e) { box.innerHTML = '<p class="err">' + esc(A.msg(e)) + "</p>"; });
  }
  A.act("scevents", function () {
    EVK = "";
    A.sheet(t("sc_all_ev"), '<div class="filters" role="group" id="scEvKinds">' + [""].concat(["blocked", "login", "login_failed", "login_locked", "throttled", "denied", "not_found"]).map(function (k) {
      return '<button class="fbtn" type="button" data-act="scevkind" data-v="' + k + '" aria-pressed="' + (k === EVK) + '">' + esc(k ? t("k_ev_" + k) : t("sc_ev_all")) + "</button>"; }).join("") + '</div><div class="sc-evs" id="scEvList"></div>');
    allEvents(false);
  });
  A.act("scevkind", function (el) { EVK = el.getAttribute("data-v"); Array.prototype.forEach.call(document.querySelectorAll("#scEvKinds .fbtn"), function (b) { b.setAttribute("aria-pressed", b === el ? "true" : "false"); }); allEvents(false); });
  A.act("scevmore", function () { EVP++; allEvents(true); });

  A.start({ page: "security", nav: "security", title: "n_security", needsConn: true, perm: "system.traffic", shell: true, load: load, render: render, after: after });
})();
