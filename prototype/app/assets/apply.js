/* The public application form (no sign-in), in the dashboard's look.
   Two ways: (1) fill out a step-by-step form; (2) upload photos of the papers: they are read on this phone (OCR, nothing is sent
   anywhere to be read), the answers found are filled in, and the applicant checks every step before sending. */
(function () {
  var S = window.ORPHAN_SCHEMA || [], root = document.getElementById("apply"), API = new URL("../server/api/", location.href).href;
  /* each partner has its own link: apply.html?partner=dar_al_ber (a link without one is Dar Al Ber's, as before) */
  var PARTNER = (location.search.match(/[?&]partner=([a-z0-9_]{2,40})/) || [])[1] || "";
  var OCR_BASE = new URL("ocr/", location.href).href, DRAFT = "acf_apply_draft" + (PARTNER && PARTNER !== "dar_al_ber" ? ":" + PARTNER : "");
  var lang = "en"; try { if (localStorage.getItem("acf_lang") === "ar") lang = "ar"; } catch (e) {}
  var st = { view: "choose", step: 0, values: {}, fromDoc: {}, photos: [], docs: [], method: "form", maxP: 2, maxD: 10, open: true, ocrText: "" };
  /* The papers of the Dar Al Ber form (step 4), each under its own name. "photos" is the orphan's personal photo. */
  var SLOTS = [["photos", "Orphan's personal photo", "صورة شخصية لليتيم"], ["s_birth_certificate", "Orphan's birth certificate", "صورة شهادة ميلاد اليتيم"], ["s_father_death_certificate", "Father's death certificate", "صورة شهادة وفاة الاب"],
    ["s_guardian_id", "Direct responsible person's ID", "صورة هوية المسؤول المباشر"], ["s_school_record", "School certificate", "صورة الشهادة الدراسية"], ["s_case_study", "Case study", "صورة بحث الحالة"]];
  SLOTS.forEach(function (x) { if (x[0] !== "photos") st[x[0]] = []; });
  var slotFiles = function () { return SLOTS.reduce(function (n, x) { return n + st[x[0]].length; }, 0); };
  try { var dr = JSON.parse(sessionStorage.getItem(DRAFT) || "null"); if (dr && dr.values) { st.values = dr.values; st.fromDoc = dr.fromDoc || {}; st.method = dr.method || "form"; } } catch (e) {}

  var T = {
    title: ["Apply for orphan support", "طلب كفالة يتيم"], org: ["Al Insania Charity Foundation Inc. · Pagadian City", "مؤسسة الإنسانية الخيرية · باغاديان"],
    intro: ["Choose how you want to apply. Your answers and photos are private: only the foundation's Aytam team sees them. Sending the form does not by itself mean support is approved.", "اختر طريقة التقديم. إجاباتك وصورك خاصة: لا يراها إلا فريق الأيتام في المؤسسة. إرسال الطلب لا يعني بحد ذاته الموافقة على الكفالة."],
    o1: ["Fill out the form", "تعبئة النموذج"], o1s: ["Answer step by step. About 10 minutes.", "أجب خطوة بخطوة. نحو 10 دقائق."],
    o2: ["Upload documents", "رفع المستندات"], o2s: ["Take photos of the papers. We read them for you, then you check every answer.", "صوّر الأوراق، نقرؤها لك، ثم تراجع كل إجابة."],
    need: ["You will need: the child's name, gender and date of birth, and the guardian's name and phone.", "ستحتاج إلى: اسم الطفل وجنسه وتاريخ ميلاده، واسم الوصي ورقم هاتفه."],
    lang: ["العربية", "English"], back: ["Back", "رجوع"], next: ["Next", "التالي"], review: ["Review", "مراجعة"], step: ["Step {n} of {m}", "الخطوة {n} من {m}"],
    up_title: ["Upload documents", "رفع المستندات"], up_txt: ["Add clear photos of the papers, up to {n}: the orphan information sheet, birth certificate, father's death certificate, school record.", "أضف صورًا واضحة للأوراق، حتى {n}: ورقة معلومات اليتيم، شهادة الميلاد، شهادة وفاة الأب، السجل المدرسي."],
    att_txt: ["The papers of the Dar Al Ber form. Add the ones you have (clear photos); the foundation will ask for any that are missing.", "أوراق نموذج دار البر. أضف ما لديك (صور واضحة)، وستطلب المؤسسة ما ينقص."],
    up_tip: ["Tip: lay the page flat in good light and let it fill the photo.", "نصيحة: ضع الورقة مستوية في إضاءة جيدة واجعلها تملأ الصورة."], up_add: ["Add a page", "أضف صفحة"],
    up_read: ["Read the documents", "اقرأ المستندات"], up_skip: ["I will type the answers", "سأكتب الإجابات بنفسي"], up_reading: ["Reading page {n} of {m}…", "جارٍ قراءة الصفحة {n} من {m}…"], up_loading: ["Preparing the reader (first time only)…", "جارٍ تجهيز القارئ (أول مرة فقط)…"],
    up_found: ["We found {n} answers", "وجدنا {n} إجابة"], up_found_txt: ["They are filled in for you. Go through each step and correct anything that is wrong or missing.", "تمت تعبئتها لك. راجع كل خطوة وصحّح أي خطأ أو نقص."], up_check: ["Check the answers", "راجع الإجابات"],
    up_none: ["We could not read answers from these photos", "لم نتمكن من قراءة إجابات من هذه الصور"], up_none_txt: ["You can still send them: fill in the form and the photos go with your application.", "يمكنك إرسالها مع ذلك: عبئ النموذج وتُرسل الصور مع طلبك."], up_cont: ["Continue to the form", "تابع إلى النموذج"],
    too_big: ["The pictures are too large to send together. Remove a page or a photo here, then go on and send again.", "الصور أكبر من أن تُرسل معًا. احذف صفحة أو صورة هنا، ثم تابع وأرسل مرة أخرى."],
    up_old: ["This phone's browser is too old to read documents (it needs iOS 16.4 or newer, or an updated Chrome). Please type the answers; your photos of the papers are still sent with the application.", "متصفح هذا الهاتف قديم ولا يستطيع قراءة المستندات (يلزم iOS 16.4 أو أحدث، أو Chrome محدث). اكتب الإجابات من فضلك، وتُرسل صور الأوراق مع الطلب."],
    up_fail: ["This phone could not run the reader. Fill in the form; your photos still go with the application.", "تعذر تشغيل القارئ على هذا الهاتف. عبئ النموذج، وتُرسل صورك مع الطلب."],
    from_doc: ["From your document — please check", "من مستندك — يرجى التأكد"], photos: ["Photos of the child", "صور اليتيم"], photos_txt: ["Up to {n} recent photos of the child (optional).", "حتى {n} صور حديثة لليتيم (اختياري)."], add_photo: ["Add a photo", "أضف صورة"],
    rv_title: ["Review and send", "المراجعة والإرسال"], rv_txt: ["Check your answers. Tap Edit to change a part.", "تحقق من إجاباتك. اضغط تعديل لتغيير أي جزء."], edit: ["Edit", "تعديل"], rv_none: ["Nothing filled in", "لم تُعبأ"],
    rv_docs: ["Documents", "المستندات"], rv_photos: ["Photos", "الصور"], n_files: ["{n} attached", "{n} مرفقة"], confirm: ["I confirm this information is correct to the best of my knowledge.", "أؤكد أن هذه المعلومات صحيحة حسب علمي."],
    send: ["Send application", "إرسال الطلب"], sending: ["Sending…", "جارٍ الإرسال…"], must_confirm: ["Please tick the confirmation.", "يرجى تأكيد صحة المعلومات."],
    req: ["This field is required.", "هذا الحقل إلزامي."], name_need: ["Enter the child's name in English or Arabic.", "أدخل اسم الطفل بالإنجليزية أو بالعربية."], fix: ["Please check the highlighted fields.", "يرجى مراجعة الحقول المظللة."],
    bad_phone: ["Enter a valid phone number.", "أدخل رقم هاتف صحيحًا."], bad_last4: ["Enter exactly 4 digits.", "أدخل 4 أرقام بالضبط."], bank_all: ["Fill in all three bank fields, or none.", "املأ حقول البنك الثلاثة كلها أو اتركها فارغة."], bad_date: ["Enter a real date in the past.", "أدخل تاريخًا صحيحًا في الماضي."],
    thanks: ["Thank you. Your application was received.", "شكرًا لك. تم استلام طلبك."], ref: ["Your reference number", "رقمك المرجعي"], keep: ["Please keep this number. The foundation will contact the guardian on the phone number you gave.", "يرجى الاحتفاظ بهذا الرقم. ستتواصل المؤسسة مع الوصي على الرقم الذي أدخلته."],
    another: ["Send another application", "إرسال طلب آخر"], closed: ["The application form is closed for now.", "نموذج التقديم مغلق حاليًا."],
    notopen: ["This application form is not open yet.", "نموذج التقديم هذا غير مفتوح بعد."], notopen_sub: ["Please use the link the foundation sent you.", "يرجى استخدام الرابط الذي أرسلته لك المؤسسة."], for_partner: ["Partner: {p}", "الشريك: {p}"], net: ["Could not reach the server. Check your connection and try again.", "تعذر الوصول إلى الخادم. تحقق من الاتصال وحاول مرة أخرى."],
    busy: ["Too many applications were sent from this connection. Please try again later.", "أُرسل عدد كبير من الطلبات من هذا الاتصال. يرجى المحاولة لاحقًا."], remove: ["Remove", "إزالة"], optional: ["optional", "اختياري"],
    privacy: ["Only the last 4 digits of a bank account are asked for. Never send full card or account numbers, or passwords.", "يُطلب آخر 4 أرقام فقط من الحساب البنكي. لا ترسل أرقام البطاقات أو الحسابات كاملة ولا كلمات المرور."]
  };
  function tx(k, o) { var s = T[k][lang === "ar" ? 1 : 0]; for (var x in o) s = s.replace("{" + x + "}", o[x]); return s; }
  function lab(x) { return lang === "ar" ? x.ar : x.en; }
  function esc(s) { return String(s === null || s === undefined ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function svg(d) { return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">' + d + "</svg>"; }
  var I = {
    edit: svg('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>'), up: svg('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>'),
    chev: svg('<polyline points="9 6 15 12 9 18"/>'), back: svg('<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>'), plus: svg('<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'),
    globe: svg('<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'), check: svg('<polyline points="20 6 9 17 4 12"/>')
  };

  /* ---- the steps: the public parts of the schema, then photos, then review ---- */
  function steps() {
    var out = [];
    S.forEach(function (g) { var fl = g.fields.filter(function (f) { return !f.staff; }); if (fl.length) out.push({ id: g.id, g: g, fields: fl }); if (g.id === "responsible") out.push({ id: "attach" }); });   // the four steps of the Dar Al Ber form, then the extras
    return out;
  }
  var ATT = { ar: "المرفقات", en: "Attachments" };
  function attachHtml() {
    return '<p class="ap-sub">' + tx("att_txt") + "</p>" + SLOTS.map(function (x) {
      return '<div class="ap-slot"><div class="ap-slotlbl">' + esc(lang === "ar" ? x[2] : x[1]) + "</div>" + tiles(st[x[0]], x[0], x[0] === "photos" ? st.maxP : 1) + "</div>";
    }).join("");
  }
  var NAMEMAP = { child: ["name_ar", "name_en"], mother: ["mother_name_ar", "mother_name"], resp: ["responsible_name_ar", "responsible_name_en"] };
  var REL = ["Mother", "Father", "Grandfather", "Grandmother", "Uncle", "Aunt", "Brother", "Sister", "Other"];
  /* Answers read from a document that hold a whole name become the four parts of the form (the first three words, then the family name). */
  function splitFull(full) { var w = String(full).trim().split(/\s+/); return [w[0] || "", w[1] || "", w[2] || "", w.slice(3).join(" ")]; }
  function partsFromFull() {
    var v = st.values, P = ["first", "father", "grand", "family"];
    if (v.guardian_name && !v.resp_first_ar && !v.resp_first_en) { v[/[\u0600-\u06FF]/.test(v.guardian_name) ? "responsible_name_ar" : "responsible_name_en"] = v.guardian_name; st.fromDoc.resp_first_ar = st.fromDoc.resp_first_en = 1; }
    if (v.guardian_relation && !v.responsible_relation) REL.forEach(function (r) { if (r.toLowerCase() === String(v.guardian_relation).trim().toLowerCase()) v.responsible_relation = r; });
    Object.keys(NAMEMAP).forEach(function (who) {
      [0, 1].forEach(function (li) {
        var lang2 = li === 0 ? "ar" : "en", k = NAMEMAP[who][li], keys = P.map(function (p) { return who + "_" + p + "_" + lang2; });
        if (v[k] && !keys.some(function (x) { return v[x]; })) { splitFull(v[k]).forEach(function (w, i) { if (w) { v[keys[i]] = w; st.fromDoc[keys[i]] = 1; } }); }
        delete v[k];
      });
    });
    delete v.guardian_name; delete v.guardian_relation;
  }
  // a new step opens at its top at once (the site scrolls smoothly elsewhere, which would slide each step up from the bottom)
  function toTop() { var h = document.documentElement, b = h.style.scrollBehavior; h.style.scrollBehavior = "auto"; void getComputedStyle(h).scrollBehavior; window.scrollTo(0, 0); h.style.scrollBehavior = b; }
  function save() { try { sessionStorage.setItem(DRAFT, JSON.stringify({ values: st.values, fromDoc: st.fromDoc, method: st.method })); } catch (e) {} }
  function collect() { Array.prototype.forEach.call(root.querySelectorAll("[data-k]"), function (el) { var v = el.value.trim(); if (v === "") delete st.values[el.getAttribute("data-k")]; else st.values[el.getAttribute("data-k")] = v; }); save(); }

  function header(backTo) {
    return '<header class="ap-top">' + (backTo ? '<button class="ap-round" type="button" data-go="' + backTo + '" aria-label="' + tx("back") + '">' + I.back + "</button>" : '<span class="ap-logo" role="img" aria-label="Al Insania"></span>') +
      '<div class="ap-brand"><b>' + tx("title") + "</b><small>" + tx("org") + "</small>" + (st.partner ? '<small class="ap-partner">' + (st.partner.logo ? '<img src="' + esc(st.partner.logo) + '" alt="">' : "") + esc(tx("for_partner", { p: lang === "ar" && st.partner.name_ar ? st.partner.name_ar : st.partner.name_en })) + "</small>" : "") +
      '</div><button class="ap-round" type="button" id="aplang" aria-label="Language">' + I.globe + "</button></header>";
  }
  function control(f) {
    var id = "a_" + f.k, v = st.values[f.k] || "", dir = f.dir ? ' dir="' + f.dir + '"' : "", inp;
    if (f.t === "sel") inp = '<select id="' + id + '" data-k="' + f.k + '">' + f.o.map(function (o) { return '<option value="' + esc(o[0]) + '"' + (o[0] === v ? " selected" : "") + ">" + esc(lang === "ar" ? o[2] : o[1]) + "</option>"; }).join("") + "</select>";
    else if (f.t === "long") inp = '<textarea id="' + id + '" data-k="' + f.k + '" rows="3" maxlength="1000"' + dir + ">" + esc(v) + "</textarea>";
    else inp = '<input id="' + id + '" data-k="' + f.k + '" type="' + (f.t === "date" ? "date" : f.t === "num" || f.t === "dec" ? "number" : f.t === "tel" ? "tel" : "text") + '"' + dir + (f.t === "num" ? ' min="0" max="30" inputmode="numeric"' : "") + (f.t === "dec" ? ' min="0" step="0.01" inputmode="decimal"' : "") + (f.t === "tel" ? ' inputmode="tel" autocomplete="tel"' : "") + ' maxlength="' + (f.max || 150) + '"' + (f.k === "bank_last4" ? ' inputmode="numeric" pattern="[0-9]*"' : "") + ' value="' + esc(v) + '">';
    return '<div class="ap-field' + (st.fromDoc[f.k] ? " doc" : "") + '"><label for="' + id + '">' + esc(lab(f)) + (f.req ? ' <span class="star">*</span>' : "") + "</label>" + inp + (st.fromDoc[f.k] ? '<span class="ap-fromdoc">' + tx("from_doc") + "</span>" : "") + '<div class="err" id="' + id + 'Err"></div></div>';
  }
  function progress(i, m) { return '<div class="ap-prog" role="progressbar" aria-valuemin="1" aria-valuemax="' + m + '" aria-valuenow="' + (i + 1) + '">' + Array.apply(null, Array(m)).map(function (_, k) { return '<i class="' + (k < i ? "done" : k === i ? "on" : "") + '"></i>'; }).join("") + "</div>"; }
  function tiles(list, kind, max) {
    return '<div class="ap-files">' + list.map(function (f, i) { return '<div class="ap-file"><img alt="" src="' + f.url + '"><button type="button" data-rm="' + kind + '" data-i="' + i + '" aria-label="' + tx("remove") + '">&times;</button></div>'; }).join("") +
      (list.length < max ? '<button class="ap-file add" type="button" data-pick="' + kind + '">' + I.plus + "<span>" + tx(kind === "docs" ? "up_add" : "add_photo") + "</span></button>" : "") + '</div><input type="file" accept="image/*" id="pick_' + kind + '" hidden>';
  }

  /* ---- views ---- */
  function render() {
    var v = st.view, html = "";
    if (!st.open) html = header() + '<section class="ap-card ap-center"><h1>' + tx(st.reason === "not_setup" || st.reason === "unknown" ? "notopen" : "closed") + "</h1>" + (st.reason === "unknown" ? '<p class="ap-sub">' + tx("notopen_sub") + "</p>" : "") + "</section>";
    else if (v === "choose") {
      html = header() + '<section class="ap-hero"><h1>' + tx("title") + "</h1><p>" + tx("intro") + "</p></section>" +
        '<button class="ap-option" type="button" data-start="form"><span class="ap-oi">' + I.edit + '</span><span class="grow"><b>' + tx("o1") + "</b><small>" + tx("o1s") + '</small></span><span class="ap-chev">' + I.chev + "</span></button>" +
        '<button class="ap-option" type="button" data-start="docs"><span class="ap-oi">' + I.up + '</span><span class="grow"><b>' + tx("o2") + "</b><small>" + tx("o2s") + '</small></span><span class="ap-chev">' + I.chev + "</span></button>" +
        '<p class="ap-note">' + tx("need") + "</p>";
    } else if (v === "upload") {
      html = header("choose") + '<section class="ap-card"><h2>' + tx("up_title") + "</h2><p class=\"ap-sub\">" + tx("up_txt", { n: st.maxD }) + "</p>" + tiles(st.docs, "docs", st.maxD) + '<p class="ap-note">' + tx("up_tip") + '</p><div id="ocrBox"></div></section>' +
        '<div class="ap-bar"><button class="ap-btn white" type="button" data-skip="1">' + tx("up_skip") + '</button><button class="ap-btn lime" type="button" id="readBtn"' + (st.docs.length ? "" : " disabled") + ">" + tx("up_read") + "</button></div>";
    } else if (v === "wizard") {
      var all = steps(), s = all[st.step], m = all.length;
      html = header(st.step === 0 ? (st.method === "documents" ? "upload" : "choose") : "prev") +
        '<div class="ap-stephead"><span>' + tx("step", { n: st.step + 1, m: m + 1 }) + "</span><b>" + (s.g ? esc(lab(s.g)) : esc(ATT[lang])) + "</b>" + progress(st.step, m + 1) + "</div>" +
        '<section class="ap-card">' + (s.g ? (s.g.note ? '<p class="ap-sub">' + esc(lab(s.g.note)) + "</p>" : "") + s.fields.map(control).join("") : attachHtml()) + "</section>" +
        (s.id === "bank" ? '<p class="ap-note">' + tx("privacy") + "</p>" : "") + '<div class="err ap-err" id="a_stepErr" role="alert"></div>' +
        '<div class="ap-bar"><button class="ap-btn white" type="button" data-go="prev">' + tx("back") + '</button><button class="ap-btn lime" type="button" data-go="next">' + (st.step === m - 1 ? tx("review") : tx("next")) + "</button></div>";
    } else if (v === "review") {
      var allS = steps();
      html = header("prev") + '<div class="ap-stephead"><span>' + tx("step", { n: allS.length + 1, m: allS.length + 1 }) + "</span><b>" + tx("rv_title") + "</b>" + progress(allS.length, allS.length + 1) + "</div>" + '<p class="ap-sub" style="margin:0 .2rem">' + tx("rv_txt") + "</p>" +
        allS.map(function (s, i) {
          var rows = s.g ? s.fields.filter(function (f) { return st.values[f.k]; }).map(function (f) { return "<div><dt>" + esc(lab(f)) + "</dt><dd>" + esc(show(f, st.values[f.k])) + "</dd></div>"; }).join("") : "";
          if (!s.g) rows = SLOTS.filter(function (x) { return st[x[0]].length; }).map(function (x) { return "<div><dt>" + esc(lang === "ar" ? x[2] : x[1]) + "</dt><dd>" + tx("n_files", { n: st[x[0]].length }) + "</dd></div>"; }).join("") + (st.docs.length ? "<div><dt>" + tx("rv_docs") + "</dt><dd>" + tx("n_files", { n: st.docs.length }) + "</dd></div>" : "");
          return '<section class="ap-card"><div class="ap-rvhead"><h3>' + (s.g ? esc(lab(s.g)) : esc(ATT[lang])) + '</h3><button type="button" class="ap-edit" data-edit="' + i + '">' + I.edit + "<span>" + tx("edit") + "</span></button></div>" + (rows ? '<dl class="ap-dl">' + rows + "</dl>" : '<p class="ap-sub">' + tx("rv_none") + "</p>") + "</section>";
        }).join("") +
        '<label class="ap-confirm"><input type="checkbox" id="a_confirm"><span>' + tx("confirm") + '</span></label><div class="hp" aria-hidden="true"><label>Website<input type="text" id="a_website" tabindex="-1" autocomplete="off"></label></div>' +
        '<div class="err ap-err" id="a_formErr" role="alert"></div><div class="ap-bar"><button class="ap-btn white" type="button" data-go="prev">' + tx("back") + '</button><button class="ap-btn lime" type="button" id="sendBtn">' + tx("send") + "</button></div>";
    } else if (v === "done") {
      html = header() + '<section class="ap-card ap-center"><span class="ap-ok">' + I.check + "</span><h1>" + tx("thanks") + "</h1><p>" + tx("ref") + '</p><div class="ap-ref" dir="ltr">' + esc(st.ref) + '</div><p class="ap-sub">' + tx("keep") + '</p><button class="ap-btn white" type="button" id="anew">' + tx("another") + "</button></section>";
    }
    root.innerHTML = html;
    document.documentElement.lang = lang; document.documentElement.dir = lang === "ar" ? "rtl" : "ltr"; document.title = tx("title") + " | Al Insania";
    wire();
  }
  function show(f, v) { if (f.t === "sel") { for (var i = 0; i < f.o.length; i++) if (f.o[i][0] === v) return lang === "ar" ? f.o[i][2] : f.o[i][1]; } if (f.k === "bank_last4") return "•••• " + v; return v; }

  function wire() {
    var l = document.getElementById("aplang"); if (l) l.onclick = function () { collect(); lang = lang === "ar" ? "en" : "ar"; try { localStorage.setItem("acf_lang", lang); } catch (e) {} render(); };
    Array.prototype.forEach.call(root.querySelectorAll("[data-start]"), function (b) { b.onclick = function () { st.method = b.getAttribute("data-start") === "docs" ? "documents" : "form"; st.view = st.method === "documents" ? "upload" : "wizard"; st.step = 0; save(); render(); toTop(); }; });
    Array.prototype.forEach.call(root.querySelectorAll("[data-go]"), function (b) { b.onclick = function () { go(b.getAttribute("data-go")); }; });
    Array.prototype.forEach.call(root.querySelectorAll("[data-edit]"), function (b) { b.onclick = function () { st.view = "wizard"; st.step = +b.getAttribute("data-edit"); render(); toTop(); }; });
    Array.prototype.forEach.call(root.querySelectorAll("[data-pick]"), function (b) { b.onclick = function () { var k = b.getAttribute("data-pick"), inp = document.getElementById("pick_" + k); inp.onchange = function () { var f = inp.files[0]; inp.value = ""; if (!f) return; collect(); shrink(f).then(function (sm) { st[k].push({ file: sm, url: URL.createObjectURL(sm) }); render(); }); }; inp.click(); }; });
    Array.prototype.forEach.call(root.querySelectorAll("[data-rm]"), function (b) { b.onclick = function () { collect(); st[b.getAttribute("data-rm")].splice(+b.getAttribute("data-i"), 1); render(); }; });
    var sk = root.querySelector("[data-skip]"); if (sk) sk.onclick = function () { st.view = "wizard"; st.step = 0; render(); toTop(); };
    var rb = document.getElementById("readBtn"); if (rb) rb.onclick = readDocs;
    var sb = document.getElementById("sendBtn"); if (sb) sb.onclick = submit;
    var an = document.getElementById("anew"); if (an) an.onclick = function () { st.values = {}; st.fromDoc = {}; st.photos = []; st.docs = []; SLOTS.forEach(function (x) { st[x[0]] = []; }); st.view = "choose"; save(); render(); };
    Array.prototype.forEach.call(root.querySelectorAll("[data-k]"), function (el) {
      el.addEventListener("change", function () { delete st.fromDoc[el.getAttribute("data-k")]; collect(); });
      if (el.id) el.setAttribute("aria-describedby", el.id + "Err");
      el.addEventListener("input", function () { clearErr(el.getAttribute("data-k")); });
      el.addEventListener("change", function () { clearErr(el.getAttribute("data-k")); });
    });
  }
  // a message goes away as soon as its answer is changed (the name pair and the three bank fields share one message)
  function clearErr(k) {
    var keys = /^child_first_(en|ar)$/.test(k) ? ["child_first_en", "child_first_ar"] : /^bank_/.test(k) ? ["bank_holder", "bank_name", "bank_last4"] : [k];
    keys.forEach(function (x) { var e = document.getElementById("a_" + x + "Err"), i = document.getElementById("a_" + x); if (e) e.textContent = ""; if (i) i.removeAttribute("aria-invalid"); });
    var se = document.getElementById("a_stepErr"); if (se && !root.querySelector("[aria-invalid=true]")) se.textContent = "";
  }
  function clearErrs() {
    Array.prototype.forEach.call(root.querySelectorAll(".ap-card [id$=Err]"), function (e) { e.textContent = ""; });
    Array.prototype.forEach.call(root.querySelectorAll("[aria-invalid]"), function (i) { i.removeAttribute("aria-invalid"); });
    var se = document.getElementById("a_stepErr"); if (se) se.textContent = "";
  }

  /* ---- moving between steps, with checks ---- */
  function check(s) {
    var bad = [], v = st.values, today = new Date().toISOString().slice(0, 10);
    if (!s.g) return bad;
    var keys = s.fields.map(function (f) { return f.k; });
    if (keys.indexOf("child_first_en") > -1 && !v.child_first_en && !v.child_first_ar) bad.push(["child_first_en", tx("name_need")]);
    s.fields.forEach(function (f) {
      if (f.req && !v[f.k]) bad.push([f.k, tx("req")]);
      if (v[f.k] && f.t === "date" && !(/^\d{4}-\d{2}-\d{2}$/.test(v[f.k]) && v[f.k] <= today && v[f.k] > "1900")) bad.push([f.k, tx("bad_date")]);
    });
    if (v.guardian_phone && keys.indexOf("guardian_phone") > -1 && !/^[0-9+()\s-]{6,40}$/.test(v.guardian_phone)) bad.push(["guardian_phone", tx("bad_phone")]);
    if (keys.indexOf("bank_last4") > -1) {
      if (v.bank_last4 && !/^\d{4}$/.test(v.bank_last4)) bad.push(["bank_last4", tx("bad_last4")]);
      var n = ["bank_holder", "bank_name", "bank_last4"].filter(function (k) { return v[k]; }).length; if (n && n < 3) bad.push(["bank_holder", tx("bank_all")]);
    }
    return bad;
  }
  function go(where) {
    collect(); var all = steps();
    if (where === "choose" || where === "upload") { st.view = where; render(); return; }
    if (where === "prev") { if (st.view === "review") { st.view = "wizard"; st.step = all.length - 1; } else if (st.step > 0) st.step--; else st.view = st.method === "documents" ? "upload" : "choose"; render(); toTop(); return; }
    var bad = check(all[st.step]);
    clearErrs();
    if (bad.length) { bad.forEach(function (b) { var e = document.getElementById("a_" + b[0] + "Err"), i = document.getElementById("a_" + b[0]); if (e) e.textContent = b[1]; if (i) i.setAttribute("aria-invalid", "true"); }); document.getElementById("a_stepErr").textContent = tx("fix"); var f = document.getElementById("a_" + bad[0][0]); if (f) { f.focus(); f.scrollIntoView({ block: "center" }); } return; }
    if (st.step < all.length - 1) st.step++; else st.view = "review";
    render(); toTop();
  }

  /* ---- photos are made smaller on the phone before anything else (faster to read and to send) ---- */
  function shrink(file) {
    return new Promise(function (res) {
      var img = new Image(), u = URL.createObjectURL(file);
      img.onload = function () {
        var max = 2000, w = img.naturalWidth, h = img.naturalHeight, r = Math.min(1, max / Math.max(w, h));
        if (r === 1 && file.size < 2500000) { URL.revokeObjectURL(u); return res(file); }
        var c = document.createElement("canvas"); c.width = Math.round(w * r); c.height = Math.round(h * r); c.getContext("2d").drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(u);
        c.toBlob(function (b) { res(b ? new File([b], (file.name || "page").replace(/\.[^.]+$/, "") + ".jpg", { type: "image/jpeg" }) : file); }, "image/jpeg", 0.88);
      };
      img.onerror = function () { URL.revokeObjectURL(u); res(file); };
      img.src = u;
    });
  }

  /* ---- reading the documents (OCR on this phone) ---- */
  // The reader needs WebAssembly with SIMD: every Chrome, Brave or Edge since 2021 and iPhones on iOS 16.4 or newer.
  // An older phone is told so at once; its pages are still sent with the application and the foundation reads them.
  function canRead() {
    try { return typeof WebAssembly === "object" && WebAssembly.validate(new Uint8Array([0, 97, 115, 109, 1, 0, 0, 0, 1, 5, 1, 96, 0, 1, 123, 3, 2, 1, 0, 10, 10, 1, 8, 0, 65, 0, 253, 15, 253, 98, 11])); } catch (e) { return false; }
  }
  function loadEngine() {
    if (!canRead()) return Promise.reject(new Error("old"));
    if (window.Tesseract) return Promise.resolve();
    return new Promise(function (res, rej) { var sc = document.createElement("script"); sc.src = OCR_BASE + "tesseract.min.js"; sc.onload = res; sc.onerror = rej; document.head.appendChild(sc); });
  }
  function ocrStatus(html) { var b = document.getElementById("ocrBox"); if (b) b.innerHTML = html; }
  function readDocs() {
    collect(); var btn = document.getElementById("readBtn"), docs = st.docs.slice(), texts = [], worker = null;
    btn.disabled = true; var sk = root.querySelector("[data-skip]"); if (sk) sk.disabled = true;
    var bar = function (txt, pct) { ocrStatus('<div class="ap-ocr"><span>' + txt + '</span><div class="ap-ocrbar"><i style="width:' + Math.round(pct * 100) + '%"></i></div></div>'); };
    bar(tx("up_loading"), 0.03);
    var cur = 0;
    loadEngine().then(function () {
      return window.Tesseract.createWorker(["eng", "ara"], 1, {
        workerPath: OCR_BASE + "worker.min.js", corePath: OCR_BASE + "core/tesseract-core-simd-lstm.wasm.js", langPath: OCR_BASE + "lang", workerBlobURL: false, gzip: true,
        logger: function (m) { if (m.status === "recognizing text") bar(tx("up_reading", { n: cur + 1, m: docs.length }), (cur + (m.progress || 0)) / docs.length); else if (cur === 0) bar(tx("up_loading"), 0.05 + 0.1 * (m.progress || 0)); }
      });
    }).then(function (w) {
      worker = w;
      return docs.reduce(function (p, d, i) { return p.then(function () { cur = i; return worker.recognize(d.file).then(function (r) { texts.push(r.data.text || ""); }); }); }, Promise.resolve());
    }).then(function () {
      if (worker) worker.terminate();
      st.ocrText = texts.join("\n");
      var added = 0;
      // each page is read on its own, so a label at the bottom of one page never takes the top line of the next as its answer
      texts.forEach(function (t) {
        var got = window.OCR_PARSE ? window.OCR_PARSE(t, S) : { values: {} };
        Object.keys(got.values).forEach(function (k) { if (!st.values[k]) { st.values[k] = got.values[k]; st.fromDoc[k] = 1; added++; } });
      });
      partsFromFull(); added = Object.keys(st.fromDoc).length;
      save();
      ocrStatus(added ? '<div class="ap-result"><b>' + tx("up_found", { n: added }) + "</b><p>" + tx("up_found_txt") + '</p><button class="ap-btn lime wide" type="button" data-skip="1">' + tx("up_check") + "</button></div>"
        : '<div class="ap-result"><b>' + tx("up_none") + "</b><p>" + tx("up_none_txt") + '</p><button class="ap-btn lime wide" type="button" data-skip="1">' + tx("up_cont") + "</button></div>");
      var b2 = root.querySelector("#ocrBox [data-skip]"); if (b2) b2.onclick = function () { st.view = "wizard"; st.step = 0; render(); toTop(); };
      btn.disabled = false; if (sk) sk.disabled = false;
    }).catch(function (err) {
      if (worker) try { worker.terminate(); } catch (e) {}
      ocrStatus('<div class="ap-result"><b>' + tx(err && err.message === "old" ? "up_old" : "up_fail") + '</b><button class="ap-btn lime wide" type="button" data-skip="1">' + tx("up_cont") + "</button></div>");
      var b3 = root.querySelector("#ocrBox [data-skip]"); if (b3) b3.onclick = function () { st.view = "wizard"; st.step = 0; render(); toTop(); };
      btn.disabled = false; if (sk) sk.disabled = false;
    });
  }

  /* ---- sending ---- */
  function submit() {
    var er = document.getElementById("a_formErr"), btn = document.getElementById("sendBtn"); er.textContent = "";
    var all = steps();
    for (var i = 0; i < all.length; i++) { if (check(all[i]).length) { st.view = "wizard"; st.step = i; render(); go("next"); return; } }
    if (!document.getElementById("a_confirm").checked) { er.textContent = tx("must_confirm"); return; }
    var fd = new FormData(), pub = {};
    S.forEach(function (g) { g.fields.forEach(function (f) { if (!f.staff) pub[f.k] = 1; }); });
    Object.keys(st.values).forEach(function (k) { if (pub[k] && st.values[k] !== "") fd.append(k, st.values[k]); });
    fd.append("website", document.getElementById("a_website").value); fd.append("method", st.method);
    st.photos.forEach(function (p) { fd.append("photos[]", p.file); });
    SLOTS.forEach(function (x) { if (x[0] !== "photos") st[x[0]].forEach(function (p) { fd.append("documents[]", p.file); fd.append("document_types[]", x[0].slice(2)); }); });   // each paper with its name
    st.docs.forEach(function (p) { fd.append("documents[]", p.file); fd.append("document_types[]", "other"); });
    btn.disabled = true; btn.textContent = tx("sending");
    if (PARTNER) fd.append("partner", PARTNER);
    fetch(API + "public/apply", { method: "POST", headers: { Accept: "application/json" }, body: fd, credentials: "omit" }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { r: r, j: j }; }); }).then(function (x) {
      btn.disabled = false; btn.textContent = tx("send");
      if (x.r.ok) { st.ref = (x.j.data && x.j.data.reference) || ""; st.values = {}; st.fromDoc = {}; st.photos = []; st.docs = []; SLOTS.forEach(function (x) { st[x[0]] = []; }); try { sessionStorage.removeItem(DRAFT); } catch (e) {} st.view = "done"; render(); toTop(); return; }
      if (x.r.status === 429) { er.textContent = tx("busy"); return; }
      if (x.r.status === 503) { er.textContent = tx("closed"); return; }
      if (x.r.status === 404 && x.j && x.j.code === "NOT_OPEN") { st.open = false; st.reason = "not_setup"; render(); toTop(); return; }
      if (x.r.status === 413) {   // too large for the server: take them where a picture can be removed
        if (st.docs.length) { st.view = "upload"; render(); toTop(); ocrStatus('<p class="err" role="alert">' + tx("too_big") + "</p>"); }
        else { st.view = "wizard"; st.step = all.length - 1; render(); toTop(); var se = document.getElementById("a_stepErr"); if (se) se.textContent = tx("too_big"); }
        return;
      }
      if (x.j && x.j.errors) {   // send the applicant to the step that holds the first problem
        var k = Object.keys(x.j.errors)[0].replace(/\.\d+$/, ""), msg = x.j.errors[Object.keys(x.j.errors)[0]][0];
        for (var s = 0; s < all.length; s++) { var has = all[s].fields ? all[s].fields.some(function (f) { return f.k === k; }) : (k === "photos" || (k === "documents" && !st.docs.length)); if (has) { st.view = "wizard"; st.step = s; render(); var d = document.getElementById("a_" + k + "Err") || document.getElementById("a_stepErr"); if (d) d.textContent = msg; return; } }
        if (k === "documents") { st.view = "upload"; render(); ocrStatus('<p class="err">' + esc(msg) + "</p>"); return; }
        er.textContent = msg; return;
      }
      er.textContent = (x.j && x.j.message) || tx("net");
    }, function () { btn.disabled = false; btn.textContent = tx("send"); er.textContent = tx("net"); });
  }

  /* ---- start ---- */
  fetch(API + "public/apply" + (PARTNER ? "?partner=" + PARTNER : ""), { headers: { Accept: "application/json" }, credentials: "omit" }).then(function (r) { return r.json(); }).then(function (j) {
    if (j.data) { st.open = j.data.open !== false; st.reason = j.data.reason || ""; st.partner = j.data.partner || null; st.maxP = j.data.max_photos || 2; st.maxD = (j.data.max_documents || 10) - 5; }
    render();
  }, function () { render(); });
})();
