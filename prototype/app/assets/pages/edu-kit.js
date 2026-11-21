/* The Education department's shared pieces: the field lists (one place), rows you tap to edit in place, and the add form. */
(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic, K = window.EDU = { editing: null, dyn: { institutions: [], curricula: [] } };

  A.strings({
    edu_title: "Education", edu_dash: "-", edu_none_opt: "None", edu_saved: "Saved", edu_conn: "This needs a connection.",
    f_name_en: "Name (English)", f_name_ar: "Name (Arabic)", f_gender: "Gender", f_phone: "Phone", f_email: "Email", f_city: "City", f_qual: "Qualification", f_spec: "Specialization", f_hired: "Hired on",
    f_status: "Status", f_notes: "Notes", f_inst: "Teaches at", f_curr: "Curriculum", f_male: "Male", f_female: "Female", s_active: "Active", s_inactive: "Inactive", s_ended: "Ended", s_archived: "Archived",
    f_kind: "Type", f_madrasa: "Madrasa", f_markaz: "Markaz", f_part: "Partnership", f_own: "Our own", f_partner: "Partner", f_addr: "Address", f_contact: "Contact person", f_since: "Since",
    f_title_en: "Title (English)", f_title_ar: "Title (Arabic)", f_source: "Source", f_level: "Level", f_desc: "Description", f_units_lines: "Units (one per line)",
    f_start: "Contract starts", f_months: "Months", f_salary: "Monthly salary (sweldo)", p_save: "Save", p_cancel: "Cancel"
  }, {
    edu_title: "التعليم", edu_dash: "-", edu_none_opt: "بدون", edu_saved: "تم الحفظ", edu_conn: "هذا يحتاج اتصالًا.",
    f_name_en: "الاسم (بالإنجليزية)", f_name_ar: "الاسم (بالعربية)", f_gender: "الجنس", f_phone: "الهاتف", f_email: "البريد", f_city: "المدينة", f_qual: "المؤهل", f_spec: "التخصص", f_hired: "تاريخ التعيين",
    f_status: "الحالة", f_notes: "ملاحظات", f_inst: "يدرّس في", f_curr: "المنهج", f_male: "ذكر", f_female: "أنثى", s_active: "نشط", s_inactive: "غير نشط", s_ended: "منتهٍ", s_archived: "مؤرشف",
    f_kind: "النوع", f_madrasa: "مدرسة", f_markaz: "مركز", f_part: "الشراكة", f_own: "تابعة لنا", f_partner: "شريكة", f_addr: "العنوان", f_contact: "الشخص المسؤول", f_since: "منذ",
    f_title_en: "العنوان (بالإنجليزية)", f_title_ar: "العنوان (بالعربية)", f_source: "المصدر", f_level: "المستوى", f_desc: "الوصف", f_units_lines: "الوحدات (واحدة في كل سطر)",
    f_start: "بداية العقد", f_months: "الأشهر", f_salary: "الراتب الشهري (سويلدو)", p_save: "حفظ", p_cancel: "إلغاء"
  });

  function ar() { return A.lang() === "ar"; }
  function o(v, k) { return [v, t(k), t(k)]; }
  var SEL = {
    gender: [["m", "f_male", "f_male"], ["f", "f_female", "f_female"]], tstatus: [["active", "s_active"], ["inactive", "s_inactive"]], istatus: [["active", "s_active"], ["ended", "s_ended"]], cstatus: [["active", "s_active"], ["archived", "s_archived"]],
    kind: [["madrasa", "f_madrasa"], ["markaz", "f_markaz"]], part: [["own", "f_own"], ["partner", "f_partner"]]
  };
  function f(k, key, type, extra) { var d = { k: k, key: key, t: type || "text" }; for (var x in extra || {}) d[x] = extra[x]; return d; }

  /* field lists: the same ones draw the profile rows and the add form */
  K.schema = {
    teacher: [
      f("name_en", "f_name_en"), f("name_ar", "f_name_ar", "text", { dir: "rtl" }), f("gender", "f_gender", "sel", { sel: "gender" }), f("phone", "f_phone", "tel"), f("email", "f_email", "email", { dir: "ltr" }), f("city", "f_city"),
      f("qualification", "f_qual"), f("specialization", "f_spec"), f("hired_on", "f_hired", "date"), f("status", "f_status", "sel", { sel: "tstatus", edit: true }), f("notes", "f_notes", "long")
    ],
    assign: [f("institution_id", "f_inst", "sel", { dyn: "institutions" }), f("curriculum_id", "f_curr", "sel", { dyn: "curricula" })],
    institution: [
      f("kind", "f_kind", "sel", { sel: "kind" }), f("partnership", "f_part", "sel", { sel: "part" }), f("name_en", "f_name_en"), f("name_ar", "f_name_ar", "text", { dir: "rtl" }), f("city", "f_city"), f("address", "f_addr"),
      f("contact_name", "f_contact"), f("phone", "f_phone", "tel"), f("since", "f_since", "date"), f("status", "f_status", "sel", { sel: "istatus", edit: true }), f("notes", "f_notes", "long")
    ],
    curriculum: [f("title_en", "f_title_en"), f("title_ar", "f_title_ar", "text", { dir: "rtl" }), f("source", "f_source"), f("level", "f_level"), f("description", "f_desc", "long"), f("status", "f_status", "sel", { sel: "cstatus", edit: true })]
  };
  K.field = function (list, k) { var out = null; list.forEach(function (x) { if (x.k === k) out = x; }); return out; };
  function label(d) { return t(d.key); }
  function options(d) {
    if (d.dyn) return [["", t("edu_none_opt")]].concat((K.dyn[d.dyn] || []).map(function (x) { return [String(x.id), ar() && x.name_ar ? x.name_ar : x.name_en]; }));
    return (SEL[d.sel] || []).map(function (x) { return [x[0], t(x[1])]; });
  }
  K.show = function (d, v) {
    if (v === null || v === undefined || v === "") return "-";
    if (d.t === "sel") { var hit = options(d).filter(function (x) { return x[0] === String(v); })[0]; return esc(hit ? hit[1] : v); }
    if (d.t === "date") return esc(A.dateOnly(v + "T00:00:00"));
    if (d.t === "tel") return '<span dir="ltr">' + esc(v) + "</span>";
    if (d.t === "email") return '<span dir="ltr">' + esc(v) + "</span>";
    if (d.t === "long") return '<span style="white-space:pre-line">' + esc(v) + "</span>";
    return d.dir === "rtl" ? '<span dir="auto">' + esc(v) + "</span>" : esc(v);
  };
  K.control = function (d, val) {
    var id = "f_" + d.k, v = val === null || val === undefined ? "" : String(val), dir = d.dir ? ' dir="' + d.dir + '"' : "", inp;
    if (d.t === "sel") inp = '<select id="' + id + '">' + options(d).map(function (x) { return '<option value="' + esc(x[0]) + '"' + (x[0] === v ? " selected" : "") + ">" + esc(x[1]) + "</option>"; }).join("") + "</select>";
    else if (d.t === "long") inp = '<textarea id="' + id + '" rows="3"' + dir + ">" + esc(v) + "</textarea>";
    else inp = '<input id="' + id + '" type="' + (d.t === "date" ? "date" : d.t === "email" ? "email" : d.t === "tel" ? "tel" : d.t === "num" ? "number" : "text") + '"' + dir + ' value="' + esc(v) + '"' + (d.t === "num" ? ' min="0" inputmode="numeric"' : "") + (d.t === "tel" ? ' inputmode="tel"' : "") + ">";
    return '<div><label for="' + id + '">' + esc(label(d)) + "</label>" + inp + '<div class="err" id="' + id + 'Err"></div></div>';
  };
  K.read = function (d) { var el = document.getElementById("f_" + d.k); if (!el) return undefined; var v = String(el.value).trim(); return v === "" ? null : (d.t === "sel" && d.dyn ? parseInt(v, 10) : v); };

  /* one line of a grouped list; tap it to change the value in place */
  K.row = function (d, val, canEdit) {
    if (K.editing === d.k) return '<div class="dp-inrow">' + K.control(d, val) + '<div class="dp-inbtns"><button class="btn btn-line btn-sm" type="button" data-act="kcancel">' + t("p_cancel") + '</button><button class="btn btn-main btn-sm" type="button" data-act="ksave">' + t("p_save") + "</button></div></div>";
    return '<div class="ios-row no-tile dp-row' + (canEdit ? " dp-tap" : "") + '"' + (canEdit ? ' role="button" tabindex="0" data-act="kedit" data-k="' + d.k + '"' : "") + '><span class="ios-lbl">' + esc(label(d)) + '</span><span class="ios-val">' + K.show(d, val) + "</span></div>";
  };
  K.sec = function (title, inner, extra) { return '<section class="dp-sec"><div class="ios-head"><h3>' + esc(title) + "</h3>" + (extra || "") + '</div><div class="ios-group dp-rows">' + inner + "</div></section>"; };
  K.bar = function (back, title, hrefBack) { return '<div class="dp-bar"><a class="dp-back" href="' + back + '" aria-label="' + t("back") + '">' + ic("back", "flip") + '</a><span class="dp-title">' + esc(title) + '</span><span class="dp-act ghost"></span></div>'; };

  /* wiring: tap to edit, Enter or Save keeps it, Escape or Cancel drops it. cfg: { path(): "edu/teachers/3", fields(): [defs], reload(), body?(d, v) } */
  K.wire = function (cfg) {
    A.act("kedit", function (el) { K.editing = el.getAttribute("data-k"); A.paint(); });
    A.act("kcancel", function () { K.editing = null; A.paint(); });
    function save(btn) {
      var d = K.field(cfg.fields(), K.editing); if (!d) return;
      var err = document.getElementById("f_" + d.k + "Err"); if (err) err.textContent = "";
      var v = K.read(d), body = cfg.body ? cfg.body(d, v) : {}; if (!cfg.body) body[d.k] = v;
      if (d.k === "name_en" && !v) { err.textContent = t("required"); return; }
      if (btn) btn.disabled = true;
      A.api.write("PATCH", cfg.path(), body, { online: true }).then(function () { K.editing = null; A.toast(t("edu_saved")); cfg.reload(); }, function (e) {
        if (btn) btn.disabled = false;
        if (!(e.errors && A.showErrors(e.errors, "f_"))) { if (err) err.textContent = e.offline ? t("edu_conn") : A.msg(e); }
      });
    }
    A.act("ksave", function (el) { save(el); });
    document.addEventListener("keydown", function (e) {
      if (!K.editing || !e.target || !e.target.closest || !e.target.closest(".dp-inrow")) return;
      if (e.key === "Escape") { K.editing = null; A.paint(); } else if (e.key === "Enter" && e.target.tagName !== "TEXTAREA") { e.preventDefault(); save(document.querySelector(".dp-inrow [data-act=ksave]")); }
    });
  };
  K.loadLists = function () {
    return Promise.all([A.api.get("edu/institutions", { cache: false }), A.api.get("edu/curricula", { cache: false })]).then(function (r) { K.dyn.institutions = r[0].data; K.dyn.curricula = r[1].data.map(function (c) { return { id: c.id, name_en: c.title_en, name_ar: c.title_ar }; }); }, function () {});
  };
  /* teachers are paid in Philippine pesos; the person's own "show amounts" setting still hides the figures */
  K.CUR = "PHP";
  K.money = function (n) { var u = A.user() || {}, pr = u.preferences || {}; return pr.show_amounts === false ? "••••" : K.CUR + " " + Number(n || 0).toLocaleString("en-US"); };
  K.stateText = function (s) { return t("cs_" + s); };
  A.strings({ cs_active: "Active", cs_ending_soon: "Ending soon", cs_expired: "Expired", cs_none: "No contract" }, { cs_active: "ساري", cs_ending_soon: "ينتهي قريبًا", cs_expired: "منتهٍ", cs_none: "بدون عقد" });
  K.chip = function (s, days) { return '<span class="cchip ' + s + '">' + esc(K.stateText(s)) + (days !== null && days !== undefined && (s === "ending_soon") ? " · " + t("cs_days", { n: days }) : "") + "</span>"; };
  A.strings({ cs_days: "{n} days left" }, { cs_days: "باقي {n} يومًا" });
})();
