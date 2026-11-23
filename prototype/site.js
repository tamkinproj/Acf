/* Fills the public landing page with the content the President Admin published in the staff app.
   - Visitors get the PUBLISHED content only. The page's own text stays in place until something is published,
     and also if the server cannot be reached (the last published copy is kept in this browser).
   - Everything is shown as plain text (textContent), never as HTML. Photo and link addresses are checked first.
   - ?preview=1 shows the DRAFT to a signed-in staff member (the staff app's own sign-in is used). */
(function () {
  "use strict";
  var API = new URL("server/api/", location.href).href, CK = "site_content_v1";
  var root = document.documentElement, preview = /[?&]preview=1(&|$)/.test(location.search);
  var data = null, orig = null;                                   // data = {content, images}
  var texts = [], dyn = [];                                       // nodes whose text depends on the language

  function ready() { root.classList.remove("cms-loading"); }
  function lang() { return root.lang === "ar" ? "ar" : "en"; }
  function AR() { return window.__AR || {}; }
  function $(q, r) { return (r || document).querySelector(q); }
  function $$(q, r) { return Array.prototype.slice.call((r || document).querySelectorAll(q)); }
  function safeUrl(u) { return typeof u === "string" && /^(https?:\/\/[^\s"'<>]+|\/[^\s"'<>]*)$/.test(u) ? u : null; }
  function imgUrl(id) { var i = id && data && data.images && data.images[id]; return i ? safeUrl(i.url) : null; }
  function rt(en, ar) { return lang() === "ar" ? (ar || en) : (en || ar); }

  /* ---- first look at the page: remember what it says now (the fallback) ---- */
  function remember() {
    if (orig) return; orig = { lists: {}, text: [] };
    $$("[data-i18n]").forEach(function (n) { n.setAttribute("data-cms-key", n.getAttribute("data-i18n")); });
    [["projects", ".projgrid", ".proj"], ["team", ".team", ".member"], ["news", "#news .grid.g3", ".news"]].forEach(function (d) {
      var box = $(d[1]), first = box && $(d[2], box);
      if (box && first) orig.lists[d[0]] = { box: box, tpl: first.cloneNode(true), kids: Array.prototype.slice.call(box.children) };
    });
  }

  /* ---- plain texts, numbers, single values, links, photo ---- */
  function applyTexts(c) {
    texts = [];
    Object.keys(c.texts || {}).forEach(function (k) {
      $$('[data-cms-key="' + k + '"]').forEach(function (n) {
        var defEn = n.getAttribute("data-def-en"); if (defEn === null) { defEn = n.getAttribute("data-en") || n.textContent; n.setAttribute("data-def-en", defEn); }
        var v = c.texts[k] || {}, en = v.en || defEn, ar = v.ar || AR()[k] || en;
        n.removeAttribute("data-i18n"); n.setAttribute("data-en", en);       // the page's own language switch now leaves this node alone
        texts.push({ n: n, en: en, ar: ar, key: k });
      });
    });
    $$("[data-cms]").forEach(function (n) { var v = (c.singles || {})[n.getAttribute("data-cms")]; if (typeof v === "string" && v !== "") n.textContent = v; });
    $$("[data-cms-num]").forEach(function (n) {
      var v = (c.numbers || {})[n.getAttribute("data-cms-num")]; if (typeof v !== "number") return;
      n.setAttribute("data-count", String(v));
    });
    $$("[data-cms-href]").forEach(function (a) {
      var u = ((c.singles || {})[a.getAttribute("data-cms-href")] || ""); if (/^https:\/\/[^\s"'<>]+$/i.test(u)) { a.href = u; a.target = "_blank"; a.rel = "noopener noreferrer"; }
    });
    $$("[data-cms-img]").forEach(function (box) {
      var u = imgUrl(((c.images || {})[box.getAttribute("data-cms-img")])); if (!u) return;
      var old = $("img", box), im = old || document.createElement("img"); im.src = u; im.alt = ((c.texts.ld_name || {}).en || ""); im.loading = "lazy";
      if (!old) { box.textContent = ""; box.appendChild(im); }
    });
  }

  /* ---- lists: projects, team, news ---- */
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }
  function fillPhoto(box, id, fallbackKeep) {
    var u = imgUrl(id); if (!u || !box) return;
    $$("svg", box).forEach(function (s) { s.remove(); });
    var im = el("img"); im.src = u; im.alt = ""; im.loading = "lazy"; im.style.cssText = "position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit";
    box.style.position = "relative"; box.style.overflow = "hidden"; box.appendChild(im);
  }
  function dynText(n, en, ar) { dyn.push({ n: n, en: en, ar: ar }); n.removeAttribute("data-i18n"); n.removeAttribute("data-cms-key"); }
  function freeKeys(card) { $$("[data-i18n],[data-cms-key]", card).forEach(function (n) { var k = n.getAttribute("data-cms-key") || n.getAttribute("data-i18n"); n.removeAttribute("data-i18n"); n.removeAttribute("data-cms-key"); dyn.push({ n: n, en: n.getAttribute("data-en") || n.textContent, ar: AR()[k] || n.textContent }); }); }

  function setList(name, items, build) {
    var o = orig.lists[name]; if (!o) return;
    var shown = (items || []).filter(function (i) { return i.visible !== false; });
    if (!shown.length) { o.box.textContent = ""; o.kids.forEach(function (k) { o.box.appendChild(k); }); return; }   // nothing added: keep the page's samples
    o.box.textContent = "";
    shown.forEach(function (it) { var card = o.tpl.cloneNode(true); freeKeys(card); build(card, it); o.box.appendChild(card); });
  }
  function catLabel(cat) { var b = $('.fbtn[data-f="' + cat + '"]'); return { en: b ? (b.getAttribute("data-en") || b.textContent) : cat, ar: AR()["cat_" + cat] || cat }; }
  function setMeta(card, selIcon, en, ar) {
    var li = $$(".meta li", card).filter(function (l) { return $("use", l) && $("use", l).getAttribute("href") === selIcon; })[0]; if (!li) return;
    if (!en && !ar) { li.remove(); return; } var s = $("span", li); dynText(s, en, ar);
  }
  function buildProject(card, p) {
    card.setAttribute("data-cat", p.category || "orphan"); card.hidden = false;
    var cl = catLabel(p.category || "orphan"), badge = $(".photo .badge", card); if (badge) { dyn = dyn.filter(function (d) { return d.n !== badge; }); dynText(badge, cl.en, cl.ar); }
    fillPhoto($(".photo", card), p.photo);
    var h = $("h3", card); dynText(h, p.title_en || p.title_ar, p.title_ar || p.title_en);
    setMeta(card, "#i-pin", p.location_en || p.location_ar, p.location_ar || p.location_en);
    setMeta(card, "#i-clock", p.year ? "Completed " + p.year : "", p.year ? "اكتمل عام " + p.year : "");
    setMeta(card, "#i-users", p.beneficiaries ? p.beneficiaries + " beneficiaries" : "", p.beneficiaries ? p.beneficiaries + " مستفيد" : "");
    setMeta(card, "#i-heart", p.sponsor_en ? "Sponsored by " + p.sponsor_en : "", p.sponsor_ar || p.sponsor_en ? "برعاية " + (p.sponsor_ar || p.sponsor_en) : "");
  }
  function buildMember(card, m) {
    fillPhoto($(".avatar", card), m.photo); var av = $(".avatar img", card); if (av) av.style.position = "static";
    dynText($("b", card), m.name_en || m.name_ar, m.name_ar || m.name_en);
    var sp = $("span", card); if (m.role_en || m.role_ar) dynText(sp, m.role_en || m.role_ar, m.role_ar || m.role_en); else sp.remove();
    if (m.bio_en || m.bio_ar) { var bio = document.createElement("p"); bio.className = "bio"; card.appendChild(bio); dynText(bio, m.bio_en || m.bio_ar, m.bio_ar || m.bio_en); }   // a few words about the person
  }
  function buildNews(card, n) {
    fillPhoto($(".photo", card), n.photo); var ph = $(".photo span", card); if (ph && imgUrl(n.photo)) ph.remove();
    var tm = $("time", card);
    if (n.date && /^\d{4}-\d{2}-\d{2}$/.test(n.date)) { var d = new Date(n.date + "T00:00:00"); dyn.push({ n: tm, en: d.toLocaleDateString("en-GB", { day: "numeric", month: "long", year: "numeric" }), ar: d.toLocaleDateString("ar", { day: "numeric", month: "long", year: "numeric" }) }); } else tm.remove();
    dynText($("h3", card), n.title_en || n.title_ar, n.title_ar || n.title_en);
    var p = $("p", card); if (n.summary_en || n.summary_ar) dynText(p, n.summary_en || n.summary_ar, n.summary_ar || n.summary_en); else p.remove();
    var link = $("a.link", card); if (link) link.remove();
  }

  /* ---- show it ---- */
  function showLang() {
    texts.concat(dyn).forEach(function (t) { t.n.textContent = rt(t.en, t.ar); });
  }
  /* Live figures counted by the server (bare counts, nothing about any child). A figure with no real data behind it
     is not sent, and the page keeps its temporary number. Every figure then counts up from 0 when it comes into view. */
  function applyStats() {
    if (editing) return;
    var st = data && data.stats; if (!st || typeof st !== "object") return;
    Object.keys(st).forEach(function (k) {
      var n = st[k]; if (typeof n !== "number" || n < 1) return;
      $$('[data-cms-num="' + k + '"]').forEach(function (b) {
        b.setAttribute("data-count", String(n)); b.setAttribute("data-live", "1");
        var plus = b.nextElementSibling; if (plus && plus.tagName === "SPAN") plus.hidden = true;   // an exact figure needs no "+"
      });
    });
  }
  function countAll() {
    if (editing) return;
    $$("[data-cms-num]").forEach(function (b) {
      var v = b.getAttribute("data-count"); if (b._shown === v) return; b._shown = v;
      if (window.__countUp) window.__countUp(b); else b.textContent = v;
    });
  }
  function render() {
    applyStats(); countAll();
    if (!data || !data.content) return; remember(); dyn = [];
    var c = data.content; applyTexts(c);
    setList("projects", c.projects, buildProject); setList("team", c.team, buildMember); setList("news", c.news, buildNews);
    // the builder's reminders ("sample figures, replace them...") do not belong on a page that has real content
    ["i_note", "pj_note", "tm_note"].forEach(function (k) { $$('[data-cms-key="' + k + '"]').forEach(function (n) { n.hidden = true; }); });
    applySlots(c);
    applyStats(); countAll();
    showLang();
  }
  // logos and the main photo are CSS pictures of the page; swapping the variable swaps them everywhere they are used
  var SLOTS = { brand_logo: "--logo", partner_dar: "--logo-dar", partner_dub: "--logo-dub", hero_photo: "--hero-photo" };
  function applySlots(c) {
    Object.keys(SLOTS).forEach(function (k) { var u = imgUrl((c.images || {})[k]); if (u) root.style.setProperty(SLOTS[k], 'url("' + u + '")'); else root.style.removeProperty(SLOTS[k]); });
  }
  new MutationObserver(function () { if (data && data.content) showLang(); if (bar && bar.relabel) bar.relabel(); }).observe(root, { attributes: true, attributeFilter: ["lang"] });

  // the project filter buttons of the page only know the sample cards; this keeps them working for the new ones
  document.addEventListener("click", function (e) {
    var b = e.target.closest && e.target.closest(".fbtn[data-f]"); if (!b) return; var f = b.getAttribute("data-f");
    $$(".projgrid [data-cat]").forEach(function (c) { c.hidden = !(f === "all" || c.getAttribute("data-cat") === f); });
  });

  function ribbon(msg) {
    var r = el("div", "", msg); r.setAttribute("role", "status");
    r.style.cssText = "position:fixed;z-index:200;inset-inline:0;bottom:0;padding:.7rem 1rem;background:#17300d;color:#fff;font:600 14px system-ui;text-align:center";
    document.body.appendChild(r);
  }
  function load(url, headers) { return fetch(url, { headers: headers || {}, credentials: "omit", cache: "no-store" }).then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.json(); }); }

  /* ---- editing on the page itself (President Admin only) ----
     A signed-in staff member with the right to edit sees "Edit this page". Texts, contact details, numbers and logos
     become editable where they stand. Changes go to the DRAFT; nothing is public until Publish. The server checks
     the right to edit on every save, so this button is only a convenience. */
  var session = null; try { var ss = JSON.parse(localStorage.getItem("acf_session")); if (ss && ss.token && ss.user && (ss.user.permissions || []).indexOf("site.edit") > -1) session = ss; } catch (e) {}
  var editing = false, dirty = false, bar = null, statusEl = null;
  var T = { edit: ["Edit this page", "تعديل هذه الصفحة"], save: ["Save draft", "حفظ المسودة"], publish: ["Publish", "نشر"], exit: ["Exit", "خروج"], hero: ["Main photo", "الصورة الرئيسية"], lists: ["Projects, team, news", "المشاريع والفريق والأخبار"],
    unsaved: ["Unsaved changes", "تغييرات غير محفوظة"], saved: ["Draft saved. Not public yet.", "حُفظت المسودة. لم تُنشر بعد."], pubd: ["Published. Visitors see it now.", "تم النشر. يراه الزوار الآن."],
    hint: ["Click any text or logo to change it. You are editing English; switch language to edit Arabic.", "اضغط على أي نص أو شعار لتغييره. أنت تعدّل العربية؛ بدّل اللغة لتعديل الإنجليزية."], confirm: ["Publish the draft to the public page now?", "نشر المسودة على الصفحة العامة الآن؟"], leave: ["You have unsaved changes. Exit without saving?", "لديك تغييرات غير محفوظة. الخروج دون حفظ؟"] };
  function tr(k) { return T[k][lang() === "ar" ? 1 : 0]; }
  function inList(n) { return n.closest && n.closest(".projgrid,.team,#news .grid.g3,.filters,.cms-bar"); }
  function entryFor(n) {
    for (var i = 0; i < texts.length; i++) if (texts[i].n === n) return texts[i];
    var key = n.getAttribute("data-cms-key"), en = n.getAttribute("data-def-en");
    if (en === null) { en = n.getAttribute("data-en") || n.textContent; n.setAttribute("data-def-en", en); }
    var e = { n: n, en: en, ar: AR()[key] || en, key: key }; n.removeAttribute("data-i18n"); texts.push(e); return e;
  }
  function mark() { dirty = true; if (statusEl) statusEl.textContent = tr("unsaved"); }
  function api(path, opt) {
    opt = opt || {}; opt.headers = Object.assign({ Accept: "application/json", Authorization: "Bearer " + session.token }, opt.headers || {}); opt.credentials = "omit";
    return fetch(API + path, opt).then(function (r) { return r.json().then(function (j) { if (!r.ok) { var e = new Error((j && j.message) || "Error"); e.errors = j && j.errors; throw e; } return j.data; }); });
  }
  function save() {
    return api("site", { method: "PUT", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ content: data.content }) }).then(function (r) { data.images = r.images; data.content = r.content; dirty = false; statusEl.textContent = tr("saved"); });
  }
  function fail(e) { var m = e.message; if (e.errors) m += ": " + Object.keys(e.errors).map(function (k) { return k + " " + e.errors[k][0]; }).join("; "); statusEl.textContent = m; statusEl.style.color = "#fca5a5"; setTimeout(function () { statusEl.style.color = ""; }, 5000); }
  function pickImage(slot) {
    var f = document.createElement("input"); f.type = "file"; f.accept = "image/jpeg,image/png,image/webp";
    f.onchange = function () {
      if (!f.files[0]) return; var fd = new FormData(); fd.append("image", f.files[0]);
      api("site/images", { method: "POST", body: fd }).then(function (r) { data.images[r.id] = { url: r.url }; data.content.images[slot] = r.id; applySlots(data.content); $$("[data-cms-img]").forEach(function (b) { if (b.getAttribute("data-cms-img") === slot) { var im = $("img", b) || el("img"); im.src = r.url; if (!im.parentNode) { b.textContent = ""; b.appendChild(im); } } }); mark(); }, fail);
    };
    f.click();
  }
  var SLOTSEL = [[".logo", "brand_logo"], [".plogo.l-dar", "partner_dar"], [".plogo.l-dub", "partner_dub"], ["[data-cms-img=president_photo]", "president_photo"]];
  function startEdit() {
    if (editing) return; editing = true;
    api("site").then(function (d) {
      data = { content: d.content, images: d.images }; remember(); dyn = []; applyTexts(d.content); applySlots(d.content); showLang();
      var css = el("style"); css.textContent = ".cms-ed{outline:2px dashed rgba(154,205,50,.9);outline-offset:2px;cursor:text;min-width:1ch}.cms-ed:hover,.cms-ed:focus{outline-color:#f6e91b;background:rgba(246,233,27,.18)}.cms-img{cursor:pointer}.cms-bar{position:fixed;z-index:400;bottom:5.8rem;inset-inline:.6rem;display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;padding:.5rem .7rem;border-radius:1.2rem;background:#17300d;color:#fff;font:600 13px system-ui;box-shadow:0 10px 30px rgba(0,0,0,.3)}.cms-bar button,.cms-bar a{font:inherit;border:0;border-radius:999px;padding:.45rem .9rem;background:#fff;color:#17300d;cursor:pointer;text-decoration:none}.cms-bar .go{background:#9acd32}.cms-bar span{flex:1 1 12rem;opacity:.9}@media(min-width:1120px){.cms-bar{bottom:1rem;max-width:60rem;margin-inline:auto}}";
      document.head.appendChild(css);
      bar = el("div", "cms-bar"); bar.setAttribute("role", "toolbar"); statusEl = el("span", "", tr("hint"));
      var labelled = []; function btn(label, cls, fn, key) { var b = el("button", cls, label); b.type = "button"; b.onclick = fn; if (key) labelled.push([b, key]); return b; }
      bar.relabel = function () { labelled.forEach(function (x) { x[0].textContent = tr(x[1]); }); if (!dirty) statusEl.textContent = tr("hint"); };
      var pub = btn(tr("publish"), "go", function () { if (!confirm(tr("confirm"))) return; save().then(function () { return api("site/publish", { method: "POST" }); }).then(function () { statusEl.textContent = tr("pubd"); }, fail); }, "publish");
      var lists = el("a", "", tr("lists")); lists.href = "app/website.html"; lists.target = "_blank";
      bar.appendChild(statusEl); bar.appendChild(btn(tr("save"), "", function () { save().catch(fail); }, "save")); bar.appendChild(pub); bar.appendChild(btn(tr("hero"), "", function () { pickImage("hero_photo"); }, "hero")); bar.appendChild(lists);
      bar.appendChild(btn(tr("exit"), "", function () { if (dirty && !confirm(tr("leave"))) return; location.href = location.pathname; }, "exit"));
      document.body.appendChild(bar);
      $$("[data-cms-key],[data-cms],[data-cms-num]").forEach(function (n) { if (inList(n)) return; n.classList.add("cms-ed"); n.setAttribute("contenteditable", "plaintext-only"); if (n.contentEditable !== "plaintext-only") n.setAttribute("contenteditable", "true"); });
      SLOTSEL.forEach(function (s) { $$(s[0]).forEach(function (n) { if (!inList(n)) { n.classList.add("cms-ed", "cms-img"); n.setAttribute("data-slot", s[1]); } }); });
      statusEl.textContent = tr("hint");
    }, function (e) { editing = false; alert(e.message); });
  }
  document.addEventListener("click", function (e) {
    if (!editing) return; var t = e.target.closest && e.target.closest(".cms-ed"); if (!t) return;
    var link = t.closest("a,button,summary,label"); if (link || t.hasAttribute("data-slot")) e.preventDefault();
    if (t.hasAttribute("data-slot")) pickImage(t.getAttribute("data-slot"));
  }, true);
  document.addEventListener("input", function (e) {
    if (!editing) return; var n = e.target.closest && e.target.closest(".cms-ed"); if (!n || n.hasAttribute("data-slot")) return; var c = data.content;
    if (n.hasAttribute("data-cms-key")) { var en = entryFor(n), l = lang(); en[l] = n.textContent; c.texts[en.key] = c.texts[en.key] || { en: en.en, ar: en.ar }; c.texts[en.key][l] = n.textContent; }
    else if (n.hasAttribute("data-cms")) c.singles[n.getAttribute("data-cms")] = n.textContent;
    else if (n.hasAttribute("data-cms-num")) { var v = parseInt(n.textContent.replace(/[^0-9]/g, ""), 10); if (!isNaN(v)) { c.numbers[n.getAttribute("data-cms-num")] = v; n.setAttribute("data-count", String(v)); } }
    mark();
  });
  document.addEventListener("paste", function (e) { if (!editing || !(e.target.closest && e.target.closest(".cms-ed"))) return; e.preventDefault(); document.execCommand("insertText", false, (e.clipboardData || window.clipboardData).getData("text/plain").replace(/\s+/g, " ")); });
  document.addEventListener("keydown", function (e) { if (editing && e.key === "Enter" && e.target.closest && e.target.closest(".cms-ed")) e.preventDefault(); });
  window.addEventListener("beforeunload", function (e) { if (editing && dirty) { e.preventDefault(); e.returnValue = ""; } });
  function editButton() {
    var b = el("button", "", tr("edit")); b.type = "button"; b.style.cssText = "position:fixed;z-index:300;inset-inline-start:.8rem;bottom:6.2rem;padding:.6rem 1.1rem;border:0;border-radius:999px;background:#17300d;color:#fff;font:700 14px system-ui;box-shadow:0 8px 24px rgba(0,0,0,.25);cursor:pointer";
    b.onclick = function () { b.remove(); startEdit(); }; document.body.appendChild(b);
  }

  /* ---- start ---- */
  if (session && !preview) { if (/[?&]edit=1(&|$)/.test(location.search)) startEdit(); else editButton(); }
  if (preview) {
    var tok = null; try { tok = JSON.parse(localStorage.getItem("acf_session")).token; } catch (e) {}
    ribbon(rt("Preview of the draft. Visitors do not see this until it is published.", "معاينة المسودة. لا يراها الزوار حتى يتم النشر."));
    if (tok) load(API + "site", { Accept: "application/json", Authorization: "Bearer " + tok }).then(function (j) { data = { content: j.data.content, images: j.data.images }; render(); ready(); }, function () { ribbon("Could not load the draft. Sign in to the staff app first."); });
  } else {
    try { var cached = JSON.parse(localStorage.getItem(CK)); if (cached && cached.content) { data = cached; render(); } } catch (e) {}   // last published copy first, no waiting
    load(API + "public/site?_=" + Date.now(), { Accept: "application/json" }).then(function (j) {
      var d = j.data;
      ready();
      if (editing) return;                                              // the editor is showing the draft
      if (d && !d.content && d.stats) { data = { content: null, images: {}, stats: d.stats }; render(); }
      if (d && d.content) { data = { content: d.content, images: d.images || {}, version: d.version, stats: d.stats || null }; try { localStorage.setItem(CK, JSON.stringify(data)); } catch (e) {} render(); }
      else { try { localStorage.removeItem(CK); } catch (e) {} if (data && data.content) location.reload(); }        // was unpublished: back to the page's own text
    }, function () { ready(); /* offline or server down: whatever is shown stays */ });
  }
})();
