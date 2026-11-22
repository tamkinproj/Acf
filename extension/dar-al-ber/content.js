/* Dar Al Ber orphan update form (4 steps): fills each step from the orphan chosen in the ACF extension.
 * Fields are found by their Arabic label on screen, so it keeps working if the portal changes its field names.
 * It never presses Next or Save: you check each step and continue yourself. */
(function () {
  "use strict";
  if (window.top !== window || window.__acfDarAlBer) return;
  window.__acfDarAlBer = true;

  /* ---------- reading the page ---------- */
  function norm(s) {
    return String(s == null ? "" : s).replace(/[ً-ٰٟـ]/g, "").replace(/[أإآٱ]/g, "ا").replace(/ى/g, "ي").replace(/ة/g, "ه").replace(/ؤ/g, "و").replace(/ئ/g, "ي")
      .toLowerCase().replace(/[*:：]/g, " ").replace(/\s+/g, " ").trim();
  }
  function visible(el) { return !!el && el.getClientRects().length > 0; }

  var CONTROLS = 'input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=checkbox]):not([type=radio]):not([type=file]), select, textarea';
  function labelEl(text, prefix) {
    var t = norm(text), w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null), n, best = null;
    while ((n = w.nextNode())) {
      var v = norm(n.nodeValue); if (!v) continue;
      if (v === t || (prefix && v.indexOf(t) === 0)) { var el = n.parentElement; if (el && visible(el) && !/^(script|style|option)$/i.test(el.tagName)) { best = el; break; } }
    }
    return best;
  }
  function controlFor(lab, file) {
    var sel = file ? "input[type=file]" : CONTROLS;
    if (lab.tagName === "LABEL") {
      if (lab.control && lab.control.matches(sel)) return lab.control;
      var f = lab.getAttribute("for"); if (f) { var c = document.getElementById(f); if (c && c.matches(sel)) return c; }
    }
    var el = lab;
    for (var i = 0; i < 6 && el.parentElement; i++) {
      el = el.parentElement;
      var all = el.querySelectorAll(sel), list = [];
      for (var k = 0; k < all.length; k++) if (!all[k].disabled || file) list.push(all[k]);
      if (!list.length) continue;
      for (var j = 0; j < list.length; j++) if (lab.compareDocumentPosition(list[j]) & Node.DOCUMENT_POSITION_FOLLOWING) return list[j];
      return list[0];
    }
    return null;
  }

  /* ---------- writing to the page ---------- */
  function fire(el, names) { names.forEach(function (n) { el.dispatchEvent(new Event(n, { bubbles: true })); }); }
  function setText(el, v) {
    var proto = el.tagName === "TEXTAREA" ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    var d = Object.getOwnPropertyDescriptor(proto, "value");
    el.focus && el.focus(); if (d && d.set) d.set.call(el, v); else el.value = v;
    fire(el, ["input", "change", "keyup"]); el.blur && el.blur();
  }
  function setSelect(el, cands) {
    var opts = Array.prototype.slice.call(el.options), want = cands.map(norm).filter(Boolean), hit = null, i, j;
    for (i = 0; i < want.length && !hit; i++) for (j = 0; j < opts.length; j++) if (norm(opts[j].text) === want[i] || String(opts[j].value) === cands[i]) { hit = opts[j]; break; }
    for (i = 0; i < want.length && !hit; i++) for (j = 0; j < opts.length; j++) { var o = norm(opts[j].text); if (o && (o.indexOf(want[i]) >= 0 || (o.length > 2 && want[i].indexOf(o) >= 0))) { hit = opts[j]; break; } }
    if (!hit) return false;
    el.value = hit.value; fire(el, ["input", "change"]); return true;
  }

  /* ---------- the orphan's data, shaped for the portal ---------- */
  function words(full) {
    var p = String(full || "").trim().split(/\s+/).filter(Boolean);
    return [p[0] || "", p[1] || "", p[2] || "", p.slice(3).join(" ")];
  }
  var hasAr = function (s) { return /[؀-ۿ]/.test(s || ""); };
  var COUNTRY = {
    philippines: ["الفلبين", "الفيليبين"], yemen: ["اليمن"], somalia: ["الصومال"], sudan: ["السودان"], syria: ["سوريا", "سورية"], palestine: ["فلسطين"], pakistan: ["باكستان"],
    india: ["الهند"], bangladesh: ["بنجلاديش", "بنغلاديش"], indonesia: ["اندونيسيا"], afghanistan: ["افغانستان"], egypt: ["مصر"], jordan: ["الاردن"], lebanon: ["لبنان"], iraq: ["العراق"],
    turkey: ["تركيا"], kenya: ["كينيا"], nigeria: ["نيجيريا"], ethiopia: ["اثيوبيا"], myanmar: ["ميانمار", "بورما"], thailand: ["تايلاند"], nepal: ["نيبال"], morocco: ["المغرب"],
    "united arab emirates": ["الامارات"], uae: ["الامارات"], "saudi arabia": ["السعودية"], malaysia: ["ماليزيا"], tanzania: ["تنزانيا"], uganda: ["اوغندا"], mali: ["مالي"], niger: ["النيجر"],
  };
  function country(v) { if (!v) return []; if (hasAr(v)) return [v]; var k = String(v).trim().toLowerCase(); return (COUNTRY[k] || []).concat([v]); }
  function relationOf(v) {
    var s = norm(v); if (!s) return [];
    var map = [[/grandmother|جده/, ["الجده"]], [/grandfather|جد/, ["الجد"]], [/mother|(^|\s)ام/, ["الام"]], [/father|(^|\s)اب/, ["الاب"]],
      [/maternal uncle|خال(?!ه)/, ["الخال", "العم"]], [/uncle|عم(?!ه)/, ["العم", "الخال"]], [/aunt|عمه|خاله/, ["العمه", "الخاله"]], [/brother|اخ(?!ت)/, ["الاخ"]], [/sister|اخت/, ["الاخت"]]];
    for (var i = 0; i < map.length; i++) if (map[i][0].test(s)) return map[i][1].concat(["اخرى", "غير ذلك"]);
    return [v, "اخرى", "غير ذلك"];
  }
  function marital(v) {
    var s = norm(v); if (!s) return [];
    if (/widow|ارمل/.test(s)) return ["ارمله"]; if (/divorc|مطلق/.test(s)) return ["مطلقه"]; if (/married|متزوج/.test(s)) return ["متزوجه"]; if (/single|عزب/.test(s)) return ["عازبه", "غير متزوجه"];
    return [v];
  }
  var STUDY = { none: ["لا يدرس", "غير ملتحق", "بدون"], kindergarten: ["الروضه"], primary: ["الابتدائيه", "ابتدائي"], intermediate: ["المتوسطه", "الاعداديه", "متوسط"], secondary: ["الثانويه", "ثانوي"], university: ["الجامعيه", "جامعي"], vocational: ["مهني", "المهنيه", "فني"] };
  var HEALTH = { good: ["جيده", "جيد", "سليم"], needs_followup: ["تحتاج", "متابع", "مريض", "مرض", "سيئه"] };
  var slash = function (d) { return d ? String(d).replace(/-/g, "/") : ""; };
  var has = function (v) { return v !== null && v !== undefined && String(v).trim() !== ""; };

  /* The four parts the system keeps (child_first_ar ...); an older record without them is split from its full name. */
  function partsOf(d, who, lang, full) {
    var p = ["first", "father", "grand", "family"].map(function (x) { return d[who + "_" + x + "_" + lang] || ""; });
    return p.some(Boolean) ? p : words(full);
  }
  function nameRows(lang, w, base) {
    var sfx = lang === "ar" ? " (بالعربية)" : " (بالانجليزية)";
    return [[base[0] + sfx, w[0]], [base[1] + sfx, w[1]], [base[2] + sfx, w[2]], [base[3] + sfx, w[3]]].map(function (r, i) { return { label: r[0], kind: "text", value: r[1], key: (lang === "ar" ? "Arabic" : "English") + " name part " + (i + 1) }; });
  }

  function steps(p) {
    var d = p.details || {};
    var addressAr = has(d.address_ar) ? d.address_ar + (has(d.guardian_phone) ? " ـ رقم الهاتف " + d.guardian_phone : "") : "";
    var relation = d.responsible_relation || d.guardian_relation || "";
    var motherIsResp = /mother|^ام|الام/.test(norm(relation));
    var respAr = d.responsible_name_ar || (hasAr(d.guardian_name) ? d.guardian_name : "") || (motherIsResp ? d.mother_name_ar : "");
    var respEn = d.responsible_name_en || (has(d.guardian_name) && !hasAr(d.guardian_name) ? d.guardian_name : "") || (motherIsResp ? d.mother_name : "");
    var respPartsAr = partsOf(d, "resp", "ar", respAr), respPartsEn = partsOf(d, "resp", "en", respEn);
    if (motherIsResp && !respPartsAr.some(Boolean) && !respPartsEn.some(Boolean)) { respPartsAr = partsOf(d, "mother", "ar", d.mother_name_ar); respPartsEn = partsOf(d, "mother", "en", d.mother_name); }
    var none = d.study_level === "none";
    var works = d.mother_works === "yes" ? ["تعمل"] : d.mother_works === "no" ? ["لا تعمل"] : [];
    var s1 = [].concat(
      nameRows("ar", partsOf(d, "child", "ar", d.name_ar), ["الاسم الاول لليتيم", "اسم والد اليتيم", "اسم جد اليتيم", "اسم جد اليتيم/العائلة"]),
      nameRows("en", partsOf(d, "child", "en", d.name_en), ["الاسم الاول لليتيم", "اسم والد اليتيم", "اسم جد اليتيم", "اسم جد اليتيم/العائلة"]),
      [
        { label: "النوع", kind: "select", cands: d.gender === "m" ? ["ذكر"] : d.gender === "f" ? ["انثى"] : [], key: "Gender" },
        { label: "محل الميلاد", kind: "select", cands: country(d.birth_country || d.nationality || d.residence_country), key: "Birth country" },
        { label: "دولة الاقامة", kind: "select", cands: country(d.residence_country || d.birth_country), key: "Residence country" },
        { label: "مدينة الاقامة", kind: "select", cands: has(d.city) ? [d.city, "غير مبين"] : [], key: "Residence city" },
        { label: "تاريخ الميلاد", kind: "text", value: slash(d.birth_date), key: "Birth date" },
        { label: "العنوان (بالعربية)", kind: "text", value: addressAr, key: "Address (Arabic)" },
        { label: "العنوان (بالانجليزية)", kind: "text", value: d.address_en, key: "Address (English)" },
        { label: "الحالة الصحية", kind: "select", cands: HEALTH[d.health_status] || [], key: "Health status" },
        { label: "المرحلة الدراسية", kind: "select", cands: STUDY[d.study_level] || [], key: "Study stage" },
        { label: "سبب عدم الدراسة ان وجد", kind: "text", value: none ? d.reason_not_studying : "", key: "Reason not studying", optional: !none },
        { label: "الصف الدراسي", kind: "text", value: d.grade || d.madrasa_grade, key: "School grade", optional: none },
      ]);
    var mBase = ["الاسم الاول للأم", "اسم والد الأم", "اسم جد الأم", "اسم جد الأم/العائلة"];
    var s2 = [].concat(nameRows("ar", partsOf(d, "mother", "ar", d.mother_name_ar), mBase), nameRows("en", partsOf(d, "mother", "en", d.mother_name), mBase), [
      { label: "الحالة الاجتماعية للأم", kind: "select", cands: marital(d.mother_marital_status), key: "Mother's status" },
      { label: "عمل الأم", kind: "select", cands: works, key: "Mother works" },
      { label: "راتب الام ان كانت تعمل", kind: "text", value: has(d.mother_salary) ? String(parseFloat(d.mother_salary)) : d.mother_works === "no" ? "0" : "", key: "Mother's salary" },
      { label: "تاريخ وفاة الأب", kind: "text", value: slash(d.father_death_date), key: "Father's death date" },
      { label: "عدد الأشقاء الذكور", kind: "text", value: has(d.brothers) ? String(d.brothers) : "", key: "Brothers" },
      { label: "عدد الأشقاء الاناث", kind: "text", value: has(d.sisters) ? String(d.sisters) : "", key: "Sisters" },
      { label: "اجمالي عدد الاشقاء الذين يتلقون المساعدات", prefix: true, kind: "text", value: has(d.aided_siblings) ? String(d.aided_siblings) : "", key: "Siblings receiving aid" },
      { label: "مصادر اخرى للمساعدات", kind: "text", value: has(d.other_aid) ? d.other_aid : "لا يوجد", key: "Other aid" },
    ]);
    var rBase = ["الاسم الاول للمسؤول المباشر", "اسم والد المسؤول المباشر", "اسم جد المسؤول المباشر", "اسم جد المسؤول المباشر/العائلة"];
    var s3 = [].concat(nameRows("ar", respPartsAr, rBase), nameRows("en", respPartsEn, rBase), [
      { label: "علاقة المسؤول المباشر باليتيم", kind: "select", cands: relationOf(relation), key: "Relation to the orphan" },
    ]);
    var latest = (p.photos && p.photos.length) ? p.photos[p.photos.length - 1] : null, docs = {}, files = p.files || null;
    (p.documents || []).forEach(function (x) { if (x.on_file) docs[x.type] = true; });
    var doc = function (t) { return files ? (files[t] || "") : (docs[t] ? "children/" + p.id + "/documents/" + t + "/download" : ""); };
    var s4 = [
      { label: "صورة شخصية لليتيم", kind: "file", path: files ? (files.photo || "") : (latest ? "children/" + p.id + "/photos/" + latest : ""), key: "Photo of the orphan", name: "photo" },
      { label: "صورة شهادة ميلاد اليتيم", kind: "file", path: doc("birth_certificate"), key: "Birth certificate", name: "birth-certificate" },
      { label: "صورة شهادة وفاة الاب", kind: "file", path: doc("father_death_certificate"), key: "Father's death certificate", name: "death-certificate" },
      { label: "صورة هوية المسؤول", prefix: true, kind: "file", path: doc("guardian_id"), key: "Guardian ID", name: "guardian-id" },
      { label: "صورة الشهادة الدراسية", kind: "file", path: doc("school_record"), key: "School certificate", name: "school-certificate" },
      { label: "صورة بحث الحالة", kind: "file", path: doc("case_study"), key: "Case study", name: "case-study" },
    ];
    return { 1: s1, 2: s2, 3: s3, 4: s4 };
  }

  /* ---------- which step is open ---------- */
  var SIGN = { 1: ["الاسم الاول لليتيم (بالعربية)", "النوع", "تاريخ الميلاد"], 2: ["الاسم الاول للأم (بالعربية)", "عمل الأم"], 3: ["الاسم الاول للمسؤول المباشر (بالعربية)"], 4: ["صورة شخصية لليتيم", "صورة شهادة ميلاد اليتيم"] };
  function currentStep() {
    for (var s = 1; s <= 4; s++) for (var i = 0; i < SIGN[s].length; i++) if (labelEl(SIGN[s][i])) return s;
    return 0;
  }

  /* ---------- filling ---------- */
  function mark(el, ok) { try { el.style.outline = ok ? "2px solid #8fbf2a" : "2px solid #e0a020"; el.style.outlineOffset = "1px"; } catch (e) {} }
  function fillStep(step, p, overwrite) {
    var res = { done: 0, kept: 0, missing: [], notFound: [], noOption: [] };
    steps(p)[step].forEach(function (f) {
      if (f.kind === "file") return;
      var lab = labelEl(f.label, f.prefix), ctrl = lab && controlFor(lab, false);
      if (!ctrl) { res.notFound.push(f.key); return; }
      var empty = f.kind === "select" ? !f.cands.length : !has(f.value);
      if (empty) { if (!f.optional) res.missing.push(f.key); return; }
      if (!overwrite && ((ctrl.tagName === "SELECT" ? ctrl.selectedIndex > 0 : ctrl.value.trim() !== ""))) { res.kept++; return; }
      if (f.kind === "select") { if (setSelect(ctrl, f.cands)) { res.done++; mark(ctrl, true); } else { res.noOption.push(f.key + " (" + f.cands[0] + ")"); mark(ctrl, false); } }
      else { setText(ctrl, String(f.value)); res.done++; mark(ctrl, true); }
    });
    return res;
  }

  function ask(m) { return new Promise(function (r) { try { chrome.runtime.sendMessage(m, function (x) { r(x || { ok: false, error: "No answer from the extension." }); }); } catch (e) { r({ ok: false, error: "The extension was updated. Reload this page." }); } }); }
  function toFile(b64, type, name) {
    var bin = atob(b64), u = new Uint8Array(bin.length); for (var i = 0; i < bin.length; i++) u[i] = bin.charCodeAt(i);
    var blob = new Blob([u], { type: type });
    if (/^image\/(jpeg|png|gif)$/.test(type)) return Promise.resolve(new File([blob], name + "." + type.split("/")[1].replace("jpeg", "jpg"), { type: type }));
    if (/^image\//.test(type) && window.createImageBitmap) {
      return createImageBitmap(blob).then(function (bm) {
        var c = document.createElement("canvas"); c.width = bm.width; c.height = bm.height; c.getContext("2d").drawImage(bm, 0, 0);
        return new Promise(function (ok) { c.toBlob(function (b) { ok(new File([b], name + ".jpg", { type: "image/jpeg" })); }, "image/jpeg", 0.92); });
      });
    }
    return Promise.resolve(null);
  }
  async function attachFiles(p, overwrite) {
    var res = { done: 0, kept: 0, missing: [], notFound: [], noOption: [] };
    for (var f of steps(p)[4]) {
      var lab = labelEl(f.label, f.prefix), ctrl = lab && controlFor(lab, true);
      if (!ctrl) { res.notFound.push(f.key); continue; }
      if (!f.path) { res.missing.push(f.key); continue; }
      if (!overwrite && ctrl.files && ctrl.files.length) { res.kept++; continue; }
      var r = await ask({ type: "file", path: f.path });
      if (!r.ok) { res.noOption.push(f.key + ": " + r.error); continue; }
      var file = await toFile(r.data.b64, r.data.type, f.name);
      if (!file) { res.noOption.push(f.key + ": the file is a " + r.data.type.split("/")[1].toUpperCase() + ", the portal takes images only"); continue; }
      try { var dt = new DataTransfer(); dt.items.add(file); ctrl.files = dt.files; fire(ctrl, ["input", "change"]); res.done++; mark(ctrl, true); }
      catch (e) { res.noOption.push(f.key + ": the browser would not attach it"); }
    }
    return res;
  }

  /* ---------- the panel ---------- */
  var host = null, root = null, active = null, shownStep = 0, autoDone = {}, auto = true, busy = false;
  try { auto = localStorage.getItem("acfAuto") !== "0"; } catch (e) {}
  var CSS = ":host{all:initial}.p{position:fixed;left:16px;bottom:16px;z-index:2147483647;width:300px;font:13px/1.4 system-ui,Segoe UI,Arial,sans-serif;color:#111;background:#fff;border:1px solid #cfe58a;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,.18);direction:ltr;text-align:left}" +
    ".h{display:flex;align-items:center;gap:8px;padding:10px 12px;background:#e9f6c2;border-radius:16px 16px 0 0;font-weight:700;cursor:pointer}.h span{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}" +
    ".b{padding:10px 12px}.b[hidden]{display:none}.who{font-weight:600}.sm{color:#666;font-size:12px}button{font:inherit;font-weight:600;border-radius:999px;border:1px solid #111;background:#fff;color:#111;padding:8px 12px;cursor:pointer;margin:6px 6px 0 0}" +
    "button.m{background:#b6e04a;border-color:#b6e04a}button:disabled{opacity:.5}label{display:flex;gap:6px;align-items:center;margin-top:8px;font-size:12px}ul{margin:6px 0 0;padding-left:18px;font-size:12px}.ok{color:#2c6b00}.w{color:#9a6200}.e{color:#b3261e}";
  function build() {
    host = document.createElement("div"); host.id = "acf-dar-al-ber"; root = host.attachShadow({ mode: "open" });
    root.innerHTML = "<style>" + CSS + '</style><div class="p"><div class="h" id="hd"><span>ACF → Dar Al Ber</span><b id="tg">−</b></div><div class="b" id="bd">' +
      '<div class="who" id="who"></div><div class="sm" id="step"></div><div id="btns"></div>' +
      '<label><input type="checkbox" id="au"> Fill a step automatically when it opens</label><div id="out" class="sm"></div></div></div>';
    document.documentElement.appendChild(host);
    root.getElementById("hd").onclick = function () { var b = root.getElementById("bd"); b.hidden = !b.hidden; root.getElementById("tg").textContent = b.hidden ? "+" : "−"; };
    var au = root.getElementById("au"); au.checked = auto; au.onchange = function () { auto = au.checked; try { localStorage.setItem("acfAuto", auto ? "1" : "0"); } catch (e) {} };
  }
  function line(cls, t) { var li = document.createElement("li"); li.className = cls; li.textContent = t; return li; }
  function report(res, what) {
    var out = root.getElementById("out"); out.textContent = "";
    var head = document.createElement("div"); head.className = "ok"; head.textContent = what + ": " + res.done + " filled" + (res.kept ? ", " + res.kept + " already had a value" : "") + "."; out.appendChild(head);
    var ul = document.createElement("ul");
    if (res.missing.length) ul.appendChild(line("w", "Not in the system: " + res.missing.join(", ")));
    if (res.noOption.length) ul.appendChild(line("w", "Check by hand: " + res.noOption.join("; ")));
    if (res.notFound.length) ul.appendChild(line("e", "Not found on this page: " + res.notFound.join(", ")));
    out.appendChild(ul);
  }
  function btn(text, cls, fn) { var b = document.createElement("button"); b.type = "button"; b.textContent = text; if (cls) b.className = cls; b.onclick = fn; return b; }
  async function run(step, overwrite) {
    if (busy || !active) return; busy = true; paint();
    try { var res = step === 4 ? await attachFiles(active, overwrite) : fillStep(step, active, overwrite); report(res, step === 4 ? "Attachments" : "Step " + step); }
    finally { busy = false; paint(); }
  }
  function paint() {
    if (!root) return;
    var who = root.getElementById("who"), st = root.getElementById("step"), bt = root.getElementById("btns"); bt.textContent = "";
    if (!active) { who.textContent = "No orphan chosen"; st.textContent = "Click the extension icon, sign in and choose the orphan."; return; }
    who.textContent = (active.name_ar || active.name_en) + " · " + (active.external_no || active.ref);
    st.textContent = shownStep ? "Step " + shownStep + " of 4 is open." : "";
    var b1 = btn(shownStep === 4 ? "Attach files" : "Fill this step", "m", function () { run(shownStep, true); }); b1.disabled = busy || !shownStep; bt.appendChild(b1);
    var b2 = btn("Refresh from system", "", async function () { b2.disabled = true; var r = await ask({ type: "sync" }); b2.disabled = false; if (r.ok && r.data) { active = r.data; autoDone = {}; paint(); } else if (!r.ok) report({ done: 0, kept: 0, missing: [], notFound: [], noOption: [r.error] }, "Refresh"); }); bt.appendChild(b2);
  }

  function tick() {
    if (document.hidden) return;
    var step = currentStep();
    if (!step) { if (host) { host.remove(); host = root = null; shownStep = 0; } return; }
    if (!host) build();
    var changed = step !== shownStep; shownStep = step; if (changed) { root.getElementById("out").textContent = ""; paint(); }
    if (auto && active && step < 4 && !autoDone[step + ":" + active.id] && !busy) { autoDone[step + ":" + active.id] = true; run(step, false); }
  }
  var timer = 0;
  function later() { clearTimeout(timer); timer = setTimeout(tick, 600); }

  function setActive(a) { active = a || null; autoDone = {}; if (host) paint(); later(); }
  try {
    chrome.storage.local.get("active", function (s) { setActive(s && s.active); });
    chrome.storage.onChanged.addListener(function (c) { if (c.active) setActive(c.active.newValue); });
  } catch (e) {}
  new MutationObserver(later).observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ["class", "style", "hidden"] });
  setInterval(tick, 3000);
  later();
})();
