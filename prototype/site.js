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
        texts.push({ n: n, en: en, ar: ar });
      });
    });
    $$("[data-cms]").forEach(function (n) { var v = (c.singles || {})[n.getAttribute("data-cms")]; if (typeof v === "string" && v !== "") n.textContent = v; });
    $$("[data-cms-num]").forEach(function (n) {
      var v = (c.numbers || {})[n.getAttribute("data-cms-num")]; if (typeof v !== "number") return;
      n.setAttribute("data-count", String(v)); n.textContent = String(v);
      setTimeout(function () { n.textContent = String(v); }, 2200);          // after the page's count-up has finished
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
  function render() {
    if (!data || !data.content) return; remember(); dyn = [];
    var c = data.content; applyTexts(c);
    setList("projects", c.projects, buildProject); setList("team", c.team, buildMember); setList("news", c.news, buildNews);
    // the builder's reminders ("sample figures, replace them...") do not belong on a page that has real content
    ["i_note", "pj_note", "tm_note"].forEach(function (k) { $$('[data-cms-key="' + k + '"]').forEach(function (n) { n.hidden = true; }); });
    showLang();
  }
  new MutationObserver(function () { if (data && data.content) showLang(); }).observe(root, { attributes: true, attributeFilter: ["lang"] });

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
  function load(url, headers) { return fetch(url, { headers: headers || {}, credentials: "omit" }).then(function (r) { if (!r.ok) throw new Error(String(r.status)); return r.json(); }); }

  /* ---- start ---- */
  if (preview) {
    var tok = null; try { tok = JSON.parse(localStorage.getItem("acf_session")).token; } catch (e) {}
    ribbon(rt("Preview of the draft. Visitors do not see this until it is published.", "معاينة المسودة. لا يراها الزوار حتى يتم النشر."));
    if (tok) load(API + "site", { Accept: "application/json", Authorization: "Bearer " + tok }).then(function (j) { data = { content: j.data.content, images: j.data.images }; render(); }, function () { ribbon("Could not load the draft. Sign in to the staff app first."); });
  } else {
    try { var cached = JSON.parse(localStorage.getItem(CK)); if (cached && cached.content) { data = cached; render(); } } catch (e) {}   // last published copy first, no waiting
    load(API + "public/site", { Accept: "application/json" }).then(function (j) {
      var d = j.data;
      if (d && d.content) { data = { content: d.content, images: d.images || {}, version: d.version }; try { localStorage.setItem(CK, JSON.stringify(data)); } catch (e) {} render(); }
      else { try { localStorage.removeItem(CK); } catch (e) {} if (data) location.reload(); }        // was unpublished: back to the page's own text
    }, function () { /* offline or server down: whatever is shown stays */ });
  }
})();
