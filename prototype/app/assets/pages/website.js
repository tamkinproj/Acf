(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  var S = null, dirty = false, openItems = {}, errors = null, saving = false;

  A.strings({
    ws_title: "Website", ws_note: "This edits the public landing page. Changes stay in a draft until you press Publish. Needs a connection.",
    ws_status: "Status", ws_pub: "Published version {n}", ws_never: "Never published", ws_at: "last published {d}", ws_unsaved: "Unsaved changes", ws_draft_changes: "Draft differs from the published page", ws_in_sync: "Draft and published page are the same",
    ws_save: "Save draft", ws_saved: "Draft saved", ws_preview: "Preview", ws_publish: "Publish", ws_publish_q: "Publish the draft to the public page now? Visitors will see it.", ws_published: "Published. The public page now shows it.",
    ws_versions: "Earlier versions", ws_restore: "Restore into draft", ws_restore_q: "Bring version {n} back into the draft? Visitors see no change until you publish.", ws_restored: "Restored into the draft.",
    ws_en: "English", ws_ar: "العربية", ws_numbers: "Numbers on the page", ws_contact: "Contact and links", ws_photos: "Photos", ws_president_photo: "President photo",
    ws_add: "Add", ws_remove: "Remove", ws_up: "Move up", ws_down: "Move down", ws_visible: "Show on the page", ws_photo: "Photo", ws_choose: "Choose photo", ws_uploading: "Uploading…", ws_empty: "Nothing added yet. The page shows its sample items until you add one.",
    ws_fix: "Fix these first:", ws_leave: "You have unsaved changes. Leave without saving?", ws_untitled: "(untitled)",
    ws_projects: "Projects we have completed", ws_team: "Team and staff", ws_news: "News and announcements",
    f_title_en: "Title (English)", f_title_ar: "Title (Arabic)", f_category: "Category", f_location_en: "Place (English)", f_location_ar: "Place (Arabic)", f_year: "Year completed", f_ben: "Beneficiaries", f_sponsor_en: "Sponsored by (English)", f_sponsor_ar: "Sponsored by (Arabic)",
    f_name_en: "Name (English)", f_name_ar: "Name (Arabic)", f_role_en: "Position (English)", f_role_ar: "Position (Arabic)", f_summary_en: "Summary (English)", f_summary_ar: "Summary (Arabic)", f_date: "Date",
    cat_orphan: "Orphan care", cat_edu: "Education", cat_alimni: "Alimni", cat_waqf: "Waqf", cat_water: "Water", cat_mosque: "Mosques", cat_relief: "Relief", cat_train: "Training and events", ws_open: "Open the public page", ws_edit_page: "Edit on the page"
  }, {
    ws_title: "الموقع", ws_note: "هنا تعدّل الصفحة العامة. تبقى التغييرات مسودة حتى تضغط نشر. يحتاج اتصالًا بالإنترنت.",
    ws_status: "الحالة", ws_pub: "الإصدار المنشور {n}", ws_never: "لم يُنشر بعد", ws_at: "آخر نشر {d}", ws_unsaved: "تغييرات غير محفوظة", ws_draft_changes: "المسودة تختلف عن الصفحة المنشورة", ws_in_sync: "المسودة والصفحة المنشورة متطابقتان",
    ws_save: "احفظ المسودة", ws_saved: "تم حفظ المسودة", ws_preview: "معاينة", ws_publish: "نشر", ws_publish_q: "نشر المسودة على الصفحة العامة الآن؟ سيراها الزوار.", ws_published: "تم النشر. الصفحة العامة تعرضه الآن.",
    ws_versions: "إصدارات سابقة", ws_restore: "استعادة إلى المسودة", ws_restore_q: "إرجاع الإصدار {n} إلى المسودة؟ لن يرى الزوار تغييرًا حتى تنشر.", ws_restored: "تمت الاستعادة إلى المسودة.",
    ws_en: "English", ws_ar: "العربية", ws_numbers: "الأرقام في الصفحة", ws_contact: "التواصل والروابط", ws_photos: "الصور", ws_president_photo: "صورة الرئيس",
    ws_add: "إضافة", ws_remove: "حذف", ws_up: "أعلى", ws_down: "أسفل", ws_visible: "إظهار في الصفحة", ws_photo: "صورة", ws_choose: "اختر صورة", ws_uploading: "جارٍ الرفع…", ws_empty: "لم يُضف شيء بعد. تعرض الصفحة عناصرها التجريبية حتى تضيف عنصرًا.",
    ws_fix: "أصلح هذه أولًا:", ws_leave: "لديك تغييرات غير محفوظة. المغادرة دون حفظ؟", ws_untitled: "(بلا عنوان)",
    ws_projects: "المشاريع التي أنجزناها", ws_team: "الفريق والموظفون", ws_news: "الأخبار والإعلانات",
    f_title_en: "العنوان (بالإنجليزية)", f_title_ar: "العنوان (بالعربية)", f_category: "التصنيف", f_location_en: "المكان (بالإنجليزية)", f_location_ar: "المكان (بالعربية)", f_year: "سنة الإنجاز", f_ben: "عدد المستفيدين", f_sponsor_en: "برعاية (بالإنجليزية)", f_sponsor_ar: "برعاية (بالعربية)",
    f_name_en: "الاسم (بالإنجليزية)", f_name_ar: "الاسم (بالعربية)", f_role_en: "المنصب (بالإنجليزية)", f_role_ar: "المنصب (بالعربية)", f_summary_en: "الملخص (بالإنجليزية)", f_summary_ar: "الملخص (بالعربية)", f_date: "التاريخ",
    cat_orphan: "رعاية الأيتام", cat_edu: "التعليم", cat_alimni: "علّمني", cat_waqf: "الوقف", cat_water: "المياه", cat_mosque: "المساجد", cat_relief: "الإغاثة", cat_train: "التدريب والفعاليات", ws_open: "افتح الصفحة العامة", ws_edit_page: "تعديل على الصفحة نفسها"
  });
  // Arabic names of the page's own fields (the server sends English labels)
  var AR = { home: "الصفحة الرئيسية", about: "من نحن", facts: "بيانات المؤسسة", president: "الرئيس", contact: "التواصل والتذييل", testimonial: "شهادة",
    h_t1: "العنوان، الجزء الأول", h_t2: "العنوان، الجزء المميّز", h_sub: "المقدمة تحت العنوان", a_lead: "من نحن، مقدمة قصيرة", a_p: "من نحن، قصتنا", a_mp: "الرسالة", a_vp: "الرؤية", a_valp: "القيم",
    a_f1v: "الصفة القانونية", a_f3v: "الجهة المشرفة", a_f4v: "التأسيس", a_f5v: "المقر", ld_name: "اسم الرئيس", ld_role: "منصب الرئيس", ld_msg: "كلمة الرئيس", c_adv: "العنوان", c_hrv: "ساعات العمل", ft_p: "جملة التذييل", q: "الشهادة", q_by: "صاحب الشهادة",
    phone: "الهاتف", whatsapp: "واتساب", email: "البريد الإلكتروني", license_no: "رقم الترخيص", facebook: "رابط فيسبوك", instagram: "رابط إنستغرام", youtube: "رابط يوتيوب",
    hero_orphans: "أيتام مكفولون", hero_families: "أسر وصلنا إليها", hero_partners: "جهات كافلة شريكة", hero_years: "سنوات من العمل", proj_done: "مشاريع منجزة", proj_types: "أنواع المشاريع", proj_communities: "مجتمعات وصلنا إليها", proj_people: "أشخاص مستفيدون" };
  function lab(key, fallback) { return A.lang() === "ar" && AR[key] ? AR[key] : fallback; }

  /* ---- small helpers ---- */
  function get(path) { return path.split(".").reduce(function (o, k) { return o == null ? undefined : o[k]; }, S.content); }
  function set(path, v) { var p = path.split("."), o = S.content; for (var i = 0; i < p.length - 1; i++) o = o[p[i]]; o[p[p.length - 1]] = v; }
  function val(path) { var v = get(path); return v === null || v === undefined ? "" : v; }
  function input(path, label, o) {
    o = o || {}; var v = esc(val(path)), id = "w_" + path.replace(/\./g, "_"), bad = errors && errors[path];
    var el = o.long ? '<textarea id="' + id + '" data-path="' + path + '" rows="' + (o.rows || 3) + '" maxlength="' + (o.max || 1500) + '">' + v + "</textarea>" :
      '<input id="' + id + '" data-path="' + path + '" type="' + (o.type || "text") + '" value="' + v + '"' + (o.dir ? ' dir="' + o.dir + '"' : "") + (o.max ? ' maxlength="' + o.max + '"' : "") + (o.attrs || "") + ">";
    return '<div><label for="' + id + '">' + esc(label) + "</label>" + el + (bad ? '<div class="err">' + esc(bad) + "</div>" : "") + "</div>";
  }
  function img(id) { var i = id && S.images[id]; return i ? '<img class="wsimg" src="' + esc(i.url) + '" alt="">' : '<span class="wsimg wsnone">' + ic("file") + "</span>"; }
  function photoBox(path, label) {
    var id = val(path);
    return '<div class="phbox">' + img(id) + '<div class="grow"><b>' + esc(label) + '</b><div class="actions"><button class="btn btn-line btn-sm" type="button" data-act="photo" data-path="' + path + '">' + t("ws_choose") + "</button>" +
      (id ? '<button class="btn btn-line btn-sm" type="button" data-act="unphoto" data-path="' + path + '">' + t("ws_remove") + "</button>" : "") + "</div></div></div>";
  }
  function pair(base, labelKeyEn, labelKeyAr, o) { return '<div class="pair">' + input(base + "_en", t(labelKeyEn), o) + input(base + "_ar", t(labelKeyAr), Object.assign({ dir: "rtl" }, o || {})) + "</div>"; }

  /* ---- sections ---- */
  function statusCard() {
    var pub = S.version ? t("ws_pub", { n: S.version }) + (S.published_at ? " · " + t("ws_at", { d: new Date(S.published_at).toLocaleString(A.lang() === "ar" ? "ar" : "en-GB") }) : "") : t("ws_never");
    return '<div class="card"><h3>' + t("ws_status") + '</h3><p id="wsState" style="margin:.2rem 0 .8rem"><b>' + esc(pub) + "</b><br><span class=\"note\" id=\"wsDraft\"></span></p>" +
      '<div class="actions"><button class="btn btn-main" data-act="savedraft">' + ic("check") + t("ws_save") + '</button><button class="btn btn-line" data-act="preview">' + t("ws_preview") + '</button><button class="btn btn-main" data-act="publish">' + ic("globe") + t("ws_publish") + "</button>" +
      '<a class="btn btn-line" href="../index.html?edit=1" target="_blank" rel="noopener">' + t("ws_edit_page") + '</a><a class="btn btn-line" href="../index.html" target="_blank" rel="noopener">' + t("ws_open") + "</a>" + A.shareBtn("site") + "</div></div>";
  }
  function groups() {
    var sc = S.schema, order = Object.keys(sc.groups);
    return order.map(function (g) {
      var fields = Object.keys(sc.texts).filter(function (k) { return sc.texts[k].group === g; });
      return '<div class="card"><h3>' + esc(lab(g, sc.groups[g])) + "</h3>" + fields.map(function (k) {
        var f = sc.texts[k], o = { long: f.long, max: f.max, rows: f.long ? 4 : 1 };
        return '<div class="fieldrow"><b class="flab">' + esc(lab(k, f.label)) + '</b><div class="pair">' + input("texts." + k + ".en", t("ws_en"), o) + input("texts." + k + ".ar", t("ws_ar"), Object.assign({ dir: "rtl" }, o)) + "</div></div>";
      }).join("") + (g === "president" ? photoBox("images.president_photo", t("ws_president_photo")) : "") + "</div>";
    }).join("");
  }
  function numbers() {
    return '<div class="card"><h3>' + t("ws_numbers") + '</h3><div class="pair">' + Object.keys(S.schema.numbers).map(function (k) { return input("numbers." + k, lab(k, S.schema.numbers[k].label), { type: "number", attrs: ' min="0" max="10000000" step="1" inputmode="numeric"' }); }).join("") + "</div></div>";
  }
  function singles() {
    return '<div class="card"><h3>' + t("ws_contact") + '</h3><div class="pair">' + Object.keys(S.schema.singles).map(function (k) { var f = S.schema.singles[k]; return input("singles." + k, lab(k, f.label), { type: f.type === "email" ? "email" : f.type === "url" ? "url" : "text", dir: "ltr", max: 200 }); }).join("") + "</div></div>";
  }
  function sw(path, label) { return '<div class="setrow"><b>' + esc(label) + '</b><button class="switch" type="button" role="switch" aria-checked="' + (val(path) !== false) + '" data-act="vis" data-path="' + path + '"></button></div>'; }
  function listCard(name, title, fieldsFn, summaryFn) {
    var arr = S.content[name] || [];
    return '<div class="card"><h3>' + esc(title) + "</h3>" + (arr.length ? arr.map(function (it, i) {
      var base = name + "." + i, key = base;
      return '<details data-key="' + key + '"' + (openItems[key] ? " open" : "") + "><summary>" + img(it.photo) + "<b>" + esc(summaryFn(it) || t("ws_untitled")) + "</b>" + (it.visible === false ? ' <span class="badge grey">·</span>' : "") + "</summary>" +
        fieldsFn(base) + photoBox(base + ".photo", t("ws_photo")) + sw(base + ".visible", t("ws_visible")) +
        '<div class="actions"><button class="btn btn-line btn-sm" type="button" data-act="move" data-list="' + name + '" data-i="' + i + '" data-d="-1"' + (i === 0 ? " disabled" : "") + ">" + t("ws_up") + '</button><button class="btn btn-line btn-sm" type="button" data-act="move" data-list="' + name + '" data-i="' + i + '" data-d="1"' + (i === arr.length - 1 ? " disabled" : "") + ">" + t("ws_down") +
        '</button><button class="btn btn-line btn-sm" type="button" data-act="del" data-list="' + name + '" data-i="' + i + '">' + t("ws_remove") + "</button></div></details>";
    }).join("") : '<p class="note">' + t("ws_empty") + "</p>") + '<div class="actions" style="margin-top:.8rem"><button class="btn btn-main btn-sm" type="button" data-act="add" data-list="' + name + '">' + ic("plus") + t("ws_add") + "</button></div></div>";
  }
  function projectFields(b) {
    var cats = S.schema.categories.map(function (c) { return '<option value="' + c + '"' + (val(b + ".category") === c ? " selected" : "") + ">" + t("cat_" + c) + "</option>"; }).join("");
    return pair(b + ".title", "f_title_en", "f_title_ar", { max: 150 }) +
      '<div><label>' + t("f_category") + '</label><select data-path="' + b + '.category">' + cats + "</select></div>" +
      pair(b + ".location", "f_location_en", "f_location_ar", { max: 150 }) + '<div class="pair">' + input(b + ".year", t("f_year"), { max: 4, dir: "ltr", attrs: ' inputmode="numeric"' }) + input(b + ".beneficiaries", t("f_ben"), { max: 9, dir: "ltr", attrs: ' inputmode="numeric"' }) + "</div>" + pair(b + ".sponsor", "f_sponsor_en", "f_sponsor_ar", { max: 100 });
  }
  function teamFields(b) { return pair(b + ".name", "f_name_en", "f_name_ar", { max: 100 }) + pair(b + ".role", "f_role_en", "f_role_ar", { max: 100 }); }
  function newsFields(b) { return pair(b + ".title", "f_title_en", "f_title_ar", { max: 150 }) + pair(b + ".summary", "f_summary_en", "f_summary_ar", { long: true, max: 600, rows: 3 }) + input(b + ".date", t("f_date"), { type: "date", dir: "ltr" }); }
  function versions() {
    if (!S.versions.length) return "";
    return '<div class="card"><h3>' + t("ws_versions") + '</h3><div class="list">' + S.versions.map(function (v) {
      return '<div class="item"><span class="grow"><b>#' + v.version + '</b><span class="sub">' + esc(new Date(v.at).toLocaleString(A.lang() === "ar" ? "ar" : "en-GB")) + '</span></span><button class="btn btn-line btn-sm" data-act="restore" data-v="' + v.version + '">' + t("ws_restore") + "</button></div>";
    }).join("") + "</div></div>";
  }
  function errorBox() {
    if (!errors) return "";
    return '<div class="card" role="alert" style="border-color:var(--danger)"><b>' + t("ws_fix") + "</b><ul>" + Object.keys(errors).map(function (k) { return "<li><span dir=\"ltr\">" + esc(k) + "</span>: " + esc(errors[k]) + "</li>"; }).join("") + "</ul></div>";
  }

  function render(d) {
    S = d;
    return '<div class="head"><h1 class="title">' + t("ws_title") + '</h1></div><p class="note">' + t("ws_note") + "</p>" + errorBox() + statusCard() +
      '<div class="grid" style="margin-top:1rem" id="wsBody">' + groups() + numbers() + singles() +
      listCard("projects", t("ws_projects"), projectFields, function (i) { return A.lang() === "ar" && i.title_ar ? i.title_ar : i.title_en || i.title_ar; }) +
      listCard("team", t("ws_team"), teamFields, function (i) { return A.lang() === "ar" && i.name_ar ? i.name_ar : i.name_en || i.name_ar; }) +
      listCard("news", t("ws_news"), newsFields, function (i) { return A.lang() === "ar" && i.title_ar ? i.title_ar : i.title_en || i.title_ar; }) + versions() + "</div>";
  }
  function after() { refreshState(); }
  function refreshState() {
    var el = document.getElementById("wsDraft"); if (!el || !S) return;
    el.textContent = dirty ? t("ws_unsaved") : S.unpublished_changes ? t("ws_draft_changes") : t("ws_in_sync");
    el.style.color = dirty || S.unpublished_changes ? "var(--danger)" : "";
  }

  /* ---- editing ---- */
  function onEdit(e) {
    var el = e.target.closest ? e.target.closest("[data-path]") : null; if (!el || !S || el.getAttribute("data-act")) return;
    set(el.getAttribute("data-path"), el.value); dirty = true; refreshState();
  }
  document.addEventListener("input", onEdit); document.addEventListener("change", onEdit);
  document.addEventListener("toggle", function (e) { var k = e.target.getAttribute && e.target.getAttribute("data-key"); if (k) openItems[k] = e.target.open; }, true);
  window.addEventListener("beforeunload", function (e) { if (dirty) { e.preventDefault(); e.returnValue = ""; } });

  A.act("vis", function (el) { var p = el.getAttribute("data-path"); set(p, val(p) === false); dirty = true; A.paint(); });
  A.act("add", function (el) {
    var n = el.getAttribute("data-list"), arr = S.content[n]; arr.push(n === "projects" ? { visible: true, category: "orphan", photo: null, title_en: "", title_ar: "" } : n === "team" ? { visible: true, photo: null, name_en: "", name_ar: "" } : { visible: true, photo: null, title_en: "", title_ar: "", date: new Date().toISOString().slice(0, 10) });
    openItems[n + "." + (arr.length - 1)] = true; dirty = true; A.paint();
  });
  A.act("del", function (el) { var n = el.getAttribute("data-list"), i = +el.getAttribute("data-i"); S.content[n].splice(i, 1); openItems = {}; dirty = true; A.paint(); });
  A.act("move", function (el) {
    var n = el.getAttribute("data-list"), i = +el.getAttribute("data-i"), d = +el.getAttribute("data-d"), a = S.content[n], j = i + d; if (j < 0 || j >= a.length) return;
    var x = a[i]; a[i] = a[j]; a[j] = x; openItems = {}; openItems[n + "." + j] = true; dirty = true; A.paint();
  });
  A.act("unphoto", function (el) { set(el.getAttribute("data-path"), null); dirty = true; A.paint(); });
  A.act("photo", function (el) {
    var path = el.getAttribute("data-path"), f = document.createElement("input"); f.type = "file"; f.accept = "image/jpeg,image/png,image/webp";
    f.onchange = function () {
      var file = f.files[0]; if (!file) return; el.disabled = true; A.toast(t("ws_uploading"));
      var fd = new FormData(); fd.append("image", file);
      A.api.upload("site/images", fd).then(function (r) { S.images[r.id] = { url: r.url }; set(path, r.id); dirty = true; A.paint(); }, function (e) { el.disabled = false; A.toast(e.offline ? t("need_conn") : A.msg(e)); });
    };
    f.click();
  });

  /* ---- save, preview, publish ---- */
  function save() {
    if (saving) return Promise.reject(new Error("busy")); saving = true; errors = null;
    return A.api.write("PUT", "site", { content: S.content }, { online: true }).then(function (r) {
      saving = false; S.content = r.data.content; S.images = r.data.images; S.unpublished_changes = r.data.unpublished_changes; dirty = false; A.paint(); return r;
    }, function (e) {
      saving = false;
      if (e.errors) { errors = {}; Object.keys(e.errors).forEach(function (k) { errors[k] = e.errors[k][0]; }); A.paint(); window.scrollTo(0, 0); A.toast(A.msg(e)); } else A.toast(e.offline ? t("need_conn") : A.msg(e));
      throw e;
    });
  }
  A.act("savedraft", function () { save().then(function () { A.toast(t("ws_saved")); }, function () {}); });
  A.act("preview", function () {
    var w = window.open("", "_blank"); // opened now so phones do not block it; filled in once saved
    (dirty ? save() : Promise.resolve()).then(function () { var u = "../index.html?preview=1"; if (w) w.location.href = u; else location.href = u; }, function () { if (w) w.close(); });
  });
  A.act("publish", function () {
    if (!confirm(t("ws_publish_q"))) return;
    (dirty ? save() : Promise.resolve()).then(function () { return A.api.write("POST", "site/publish", {}, { online: true }); }).then(function () { A.toast(t("ws_published")); A.reload(); }, function (e) { if (e && e.message !== "busy" && !e.errors) A.toast(e.offline ? t("need_conn") : A.msg(e)); });
  });
  A.act("restore", function (el) {
    var n = el.getAttribute("data-v"); if (!confirm(t("ws_restore_q", { n: n }))) return;
    A.api.write("POST", "site/revert/" + n, {}, { online: true }).then(function () { dirty = false; A.toast(t("ws_restored")); A.reload(); }, function (e) { A.toast(e.offline ? t("need_conn") : A.msg(e)); });
  });

  A.start({ page: "website", nav: "website", title: "ws_title", needsConn: true, perm: "site.edit", shell: true, load: function () { dirty = false; errors = null; return A.api.get("site", { cache: false }); }, render: render, after: after });
})();
