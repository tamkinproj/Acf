/* Dar Al Ber's periodic orphan report ("التقرير الدوري لليتيم").
   On the orphan's profile: the year's report (person responsible, the form's choices, the three texts the system writes), approve, download the PDF.
   On the Orphans list: download many reports at once (ZIP files of up to 20).
   The PDF is Dar Al Ber's own form: the server only fills its fields. */
(function () {
  var A = ACF, t = A.t, esc = A.esc, ic = A.ic;
  var KINDS = ["changes", "comment", "message"], CHOICES = ["health", "stage", "grade", "level", "commitment"];

  A.strings({
    rp_title: "Dar Al Ber report", rp_year: "Year", rp_s_new: "Not saved yet", rp_s_draft: "Draft", rp_s_approved: "Approved", rp_by: "Approved by {n}, {d}",
    rp_noform: "The Dar Al Ber report form has not been added yet. The President adds it on the Partners page.",
    rp_missing: "Not on the orphan's record yet (the report shows these empty): {l}", m_name: "Arabic name", m_birth_date: "date of birth", m_gender: "gender", m_sponsor_name: "sponsor's name", m_responsible_name: "person responsible", m_photo: "photo", m_file_no: "Dar Al Ber orphan file number",
    rp_resp: "Person responsible this year", rp_resp_note: "Change it when someone else looks after the orphan this year (for example, the mother works abroad and her family looks after the child).",
    rp_rname: "Name of the person responsible", rp_rrel: "Relation to the orphan", rp_form_choices: "Choices on the form", rp_from_rec: "From the record: {v}", rp_none_rec: "From the record: not filled in",
    rp_c_health: "Health", rp_c_stage: "Study stage", rp_c_grade: "Grade", rp_c_level: "Study level", rp_c_commitment: "Commitment to Islamic teachings",
    rp_t_changes: "Most notable changes this year", rp_t_comment: "The organization's comment on the effect of the sponsorship", rp_t_message: "Message from the orphan / the person responsible",
    rp_texts: "Written by the system (each orphan gets a different text). Change them freely.", rp_another: "Write another", rp_count: "{n} of {m} letters", rp_long: "{n} letters: longer than the space on the form, so the letters will be printed smaller",
    rp_same: "Another orphan's report this year has the same text in: {l}", rp_save: "Save", rp_saved: "Report saved", rp_approve: "Approve", rp_unapprove: "Remove approval", rp_approved: "Report approved", rp_unapproved: "Approval removed",
    rp_pdf: "Download PDF", rp_making: "Making the PDF…", rp_conn: "The report needs a connection.", rp_ro: "You can see and download this report. Changing it needs the right to edit orphans.",
    rb_btn: "Dar Al Ber reports", rb_title: "Download Dar Al Ber reports", rb_counts: "{a} approved · {d} draft · {n} not saved yet · {all} orphans", rb_approved: "Approved reports only ({n})", rb_all: "Every orphan ({n}): any text not written yet is written by the system",
    rb_go: "Download", rb_parts: "The reports come in ZIP files of up to {n}.", rb_part: "Saving part {p} of {q}…", rb_done: "Downloaded {n} reports", rb_none: "Nothing to download for this choice."
  }, {
    rp_title: "تقرير دار البر", rp_year: "السنة", rp_s_new: "لم يُحفظ بعد", rp_s_draft: "مسودة", rp_s_approved: "معتمد", rp_by: "اعتمده {n}، {d}",
    rp_noform: "لم يُضف نموذج تقرير دار البر بعد. يضيفه الرئيس من صفحة الشركاء.",
    rp_missing: "غير موجود في سجل اليتيم بعد (يظهر فارغًا في التقرير): {l}", m_name: "الاسم بالعربية", m_birth_date: "تاريخ الميلاد", m_gender: "الجنس", m_sponsor_name: "اسم الكافل", m_responsible_name: "المسؤول المباشر", m_photo: "الصورة", m_file_no: "رقم ملف اليتيم لدى دار البر",
    rp_resp: "المسؤول المباشر هذا العام", rp_resp_note: "غيّره عندما يرعى اليتيمَ شخصٌ آخر هذا العام (مثلًا: الأم تعمل في الخارج وأهلها يرعون الطفل).",
    rp_rname: "اسم المسؤول المباشر", rp_rrel: "صلته باليتيم", rp_form_choices: "الاختيارات في النموذج", rp_from_rec: "من السجل: {v}", rp_none_rec: "من السجل: غير معبأ",
    rp_c_health: "الحالة الصحية", rp_c_stage: "المرحلة الدراسية", rp_c_grade: "الصف", rp_c_level: "المستوى الدراسي", rp_c_commitment: "الالتزام بتعاليم الإسلام",
    rp_t_changes: "أبرز التغيرات التي طرأت على اليتيم في هذه السنة", rp_t_comment: "تعليق الهيئة على أثر الكفالة", rp_t_message: "رسالة اليتيم / المسؤول المباشر عن اليتيم",
    rp_texts: "يكتبها النظام (لكل يتيم نص مختلف). يمكنك تغييرها كما تشاء.", rp_another: "اكتب نصًا آخر", rp_count: "{n} من {m} حرفًا", rp_long: "{n} حرفًا: أطول من المساحة في النموذج، فيُطبع بخط أصغر",
    rp_same: "تقرير يتيم آخر هذا العام فيه النص نفسه في: {l}", rp_save: "حفظ", rp_saved: "تم حفظ التقرير", rp_approve: "اعتماد", rp_unapprove: "إلغاء الاعتماد", rp_approved: "تم اعتماد التقرير", rp_unapproved: "أُلغي الاعتماد",
    rp_pdf: "تنزيل PDF", rp_making: "جارٍ إعداد ملف PDF…", rp_conn: "التقرير يحتاج اتصالًا.", rp_ro: "يمكنك الاطلاع على هذا التقرير وتنزيله. تغييره يحتاج صلاحية تعديل الأيتام.",
    rb_btn: "تقارير دار البر", rb_title: "تنزيل تقارير دار البر", rb_counts: "{a} معتمد · {d} مسودة · {n} لم يُحفظ بعد · {all} يتيمًا", rb_approved: "التقارير المعتمدة فقط ({n})", rb_all: "كل الأيتام ({n}): ما لم يُكتب من النصوص يكتبه النظام",
    rb_go: "تنزيل", rb_parts: "تأتي التقارير في ملفات ZIP، في كل ملف {n} تقريرًا على الأكثر.", rb_part: "جارٍ حفظ الجزء {p} من {q}…", rb_done: "تم تنزيل {n} تقريرًا", rb_none: "لا يوجد ما يُنزّل لهذا الاختيار."
  });

  /** Is the app working in the partner whose report the system knows? */
  function here() { return (A.api.partner() || "dar_al_ber") === "dar_al_ber"; }
  function years() { var y = new Date().getFullYear(); return [y + 1, y, y - 1, y - 2]; }
  function save(blob, headers, fallback) {
    // the server sends the name with the Arabic in it (filename*=UTF-8''...), and a plain one for old programs
    var cd = (headers && headers.get("Content-Disposition")) || "", u = cd.match(/filename\*\s*=\s*UTF-8''([^;]+)/i), m = cd.match(/filename\s*=\s*"?([^";]+)"?/i), name = fallback;
    if (u) { try { name = decodeURIComponent(u[1].trim()); } catch (e) { name = m ? m[1] : fallback; } } else if (m) name = m[1];
    var u = URL.createObjectURL(blob), a = document.createElement("a"); a.href = u; a.download = name; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(u); }, 60000);
  }
  function optLabel(o) { return A.lang() === "ar" ? o[1] : o[2] + " · " + o[1]; }

  /* ---------------- one orphan (profile page) ---------------- */
  var R = { id: 0, year: new Date().getFullYear(), data: null, draft: null, codes: {}, attempts: {}, dirty: false, busy: false };

  function card() { return '<section class="dp-sec" id="sec-report"><div class="ios-head"><h3>' + t("rp_title") + '</h3></div><div class="ios-group dp-pad rep" id="repBox"><p class="note" style="margin:0">…</p></div></section>'; }

  function choiceSelect(k, d, canEdit) {
    var cur = ((R.draft || d.values).choices || {})[k] || "", auto = (d.auto.choices || {})[k], list = d.options[k] || [];
    var autoLab = auto ? optLabel(list.filter(function (o) { return o[0] === auto; })[0] || [auto, auto, auto]) : null;
    var html = '<option value="">' + esc(autoLab ? t("rp_from_rec", { v: autoLab }) : t("rp_none_rec")) + "</option>" + list.map(function (o) { return '<option value="' + esc(o[0]) + '"' + (cur === o[0] ? " selected" : "") + ">" + esc(optLabel(o)) + "</option>"; }).join("");
    return '<div><label for="rep_c_' + k + '">' + t("rp_c_" + k) + '</label><select id="rep_c_' + k + '"' + (canEdit ? "" : " disabled") + ">" + html + "</select></div>";
  }
  function counter(k, v) {
    var n = (v || "").length, m = R.data.limits[k];
    return n > m ? '<span class="rep-n warn">' + esc(t("rp_long", { n: n })) + "</span>" : '<span class="rep-n">' + esc(t("rp_count", { n: n, m: m })) + "</span>";
  }
  function paint() {
    var box = document.getElementById("repBox"); if (!box) return;
    var d = R.data, canEdit = A.can("children.update"), v = R.draft || d.values;
    var top = '<div class="rep-top"><label class="rep-y">' + t("rp_year") + ' <select id="repYear">' + years().map(function (y) { return '<option value="' + y + '"' + (y === d.year ? " selected" : "") + ">" + y + "</option>"; }).join("") + "</select></label>" +
      '<span class="badge ' + (d.status === "approved" ? "" : d.status === "draft" ? "warn" : "grey") + '">' + t("rp_s_" + d.status) + "</span>" +
      (d.status === "approved" && d.approved_by ? '<span class="note" style="margin:0">' + esc(t("rp_by", { n: d.approved_by, d: A.dateTime(d.approved_at) })) + "</span>" : "") + "</div>";
    var notes = (d.form.available ? "" : '<p class="note rep-warn">' + t("rp_noform") + "</p>") +
      (d.missing.length ? '<p class="note">' + esc(t("rp_missing", { l: d.missing.map(function (m) { return t("m_" + m); }).join(A.lang() === "ar" ? "، " : ", ") })) + "</p>" : "") +
      (d.same_as_other.length ? '<p class="note rep-warn">' + esc(t("rp_same", { l: d.same_as_other.map(function (k) { return t("rp_t_" + k); }).join(", ") })) + "</p>" : "") +
      (canEdit ? "" : '<p class="note">' + t("rp_ro") + "</p>");
    var resp = '<h4 class="optlbl">' + t("rp_resp") + '</h4><p class="note" style="margin-top:0">' + t("rp_resp_note") + '</p><div class="rep-grid">' +
      '<div><label for="rep_rname">' + t("rp_rname") + '</label><input id="rep_rname" dir="auto" maxlength="150" value="' + esc(v.responsible_name || "") + '" placeholder="' + esc(d.auto.responsible_name || "") + '"' + (canEdit ? "" : " disabled") + '><div class="err" id="rep_responsible_nameErr"></div></div>' +
      '<div><label for="rep_rrel">' + t("rp_rrel") + '</label><input id="rep_rrel" dir="auto" maxlength="60" value="' + esc(v.responsible_relation || "") + '" placeholder="' + esc(d.auto.responsible_relation || "") + '"' + (canEdit ? "" : " disabled") + '><div class="err" id="rep_responsible_relationErr"></div></div></div>';
    var choices = '<h4 class="optlbl">' + t("rp_form_choices") + '</h4><div class="rep-grid rep-choices">' + CHOICES.map(function (k) { return choiceSelect(k, d, canEdit); }).join("") + "</div>";
    var texts = '<p class="note">' + t("rp_texts") + "</p>" + KINDS.map(function (k) {
      return '<div class="rep-text"><div class="rep-lab"><label for="rep_' + k + '">' + t("rp_t_" + k) + "</label>" + (canEdit ? '<button class="btn btn-line btn-sm" type="button" data-act="repwrite" data-k="' + k + '">' + ic("refresh") + t("rp_another") + "</button>" : "") + "</div>" +
        '<textarea id="rep_' + k + '" dir="rtl" rows="' + (k === "changes" ? 2 : 4) + '"' + (canEdit ? "" : " readonly") + ">" + esc(v[k] || "") + '</textarea><div class="rep-foot" id="rep_' + k + 'N">' + counter(k, v[k]) + '</div><div class="err" id="rep_' + k + 'Err"></div></div>';
    }).join("");
    var btns = '<div class="err" id="repErr" role="alert"></div><div class="actions rep-actions">' +
      (canEdit ? '<button class="btn btn-main" type="button" data-act="repsave">' + t("rp_save") + "</button>" + (d.status === "approved" ? '<button class="btn btn-line" type="button" data-act="repapprove" data-v="0">' + t("rp_unapprove") + "</button>" : '<button class="btn btn-line" type="button" data-act="repapprove" data-v="1">' + ic("check") + t("rp_approve") + "</button>") : "") +
      '<button class="btn btn-line" type="button" data-act="reppdf"' + (d.form.available ? "" : " disabled") + ">" + ic("download") + t("rp_pdf") + "</button></div>";
    box.innerHTML = top + notes + resp + choices + texts + btns;
    R.dirty = !!R.draft;
  }
  function load() {
    var box = document.getElementById("repBox"); if (!box) return;
    if (navigator.onLine === false) { box.innerHTML = '<p class="note" style="margin:0">' + t("rp_conn") + "</p>"; return; }
    A.api.get("children/" + R.id + "/report?year=" + R.year, { cache: false }).then(function (r) { got(r.data); }, function (e) { box.innerHTML = '<p class="note" style="margin:0">' + esc(e.offline ? t("rp_conn") : A.msg(e)) + "</p>"; });
  }
  function got(d) {
    R.data = d; R.year = d.year; R.codes = {}; R.attempts = {}; R.draft = null;
    String(d.values.codes || "").split("|").forEach(function (c) { if (c) R.codes[c.charAt(0)] = c; });
    paint();
  }
  function body() {
    var ch = {};
    CHOICES.forEach(function (k) { var el = document.getElementById("rep_c_" + k); if (el && el.value) ch[k] = el.value; });
    var b = { year: R.year, responsible_name: document.getElementById("rep_rname").value.trim(), responsible_relation: document.getElementById("rep_rrel").value.trim(), choices: ch, codes: Object.keys(R.codes).map(function (k) { return R.codes[k]; }).join("|") };
    KINDS.forEach(function (k) { b[k] = document.getElementById("rep_" + k).value.trim(); });
    return b;
  }
  function errs(e) {
    Array.prototype.forEach.call(document.querySelectorAll("#repBox .err"), function (x) { x.textContent = ""; });
    if (e.errors) { var hit = false; Object.keys(e.errors).forEach(function (k) { var el = document.getElementById("rep_" + k.split(".")[0] + "Err"); if (el) { el.textContent = e.errors[k][0]; hit = true; } }); if (hit) return; }
    var b = document.getElementById("repErr"); if (b) b.textContent = e.offline ? t("rp_conn") : A.msg(e);
  }
  function saveNow() {
    return A.api.write("PUT", "children/" + R.id + "/report", body(), { online: true }).then(function (r) { got(r.data); return r.data; });
  }
  function busy(el, on) { R.busy = on; if (el) el.disabled = on; }

  A.act("repsave", function (el) { if (R.busy) return; busy(el, true); saveNow().then(function () { busy(el, false); A.toast(t("rp_saved")); }, function (e) { busy(el, false); errs(e); }); });
  A.act("repapprove", function (el) {
    if (R.busy) return; var on = el.getAttribute("data-v") === "1"; busy(el, true);
    (on ? saveNow() : Promise.resolve()).then(function () {
      return A.api.write("POST", "children/" + R.id + "/report/approve", { year: R.year, approved: on }, { online: true });
    }).then(function (r) { busy(el, false); got(r.data); A.toast(t(on ? "rp_approved" : "rp_unapproved")); }, function (e) { busy(el, false); errs(e); });
  });
  A.act("repwrite", function (el) {
    var k = el.getAttribute("data-k"), ta = document.getElementById("rep_" + k), ch = {};
    CHOICES.forEach(function (c) { var s = document.getElementById("rep_c_" + c); if (s && s.value) ch[c] = s.value; });
    el.disabled = true;
    A.api.write("POST", "children/" + R.id + "/report/write", { year: R.year, kind: k, attempt: R.attempts[k] || 0, avoid: ta.value, choices: ch }, { online: true }).then(function (r) {
      el.disabled = false; var w = r.data; ta.value = w.text; R.attempts[k] = w.attempt; R.codes[w.code.charAt(0)] = w.code; R.dirty = true; R.draft = body();
      document.getElementById("rep_" + k + "N").innerHTML = counter(k, w.text);
    }, function (e) { el.disabled = false; errs(e); });
  });
  A.act("reppdf", function (el) {
    if (R.busy) return; busy(el, true); A.toast(t("rp_making"));
    var first = R.dirty && A.can("children.update") ? saveNow() : Promise.resolve();   // the PDF shows what is on the screen
    first.then(function () { return A.api.file("children/" + R.id + "/report/pdf?year=" + R.year); }).then(function (f) {
      busy(el, false); save(f.blob, f.headers, "Dar Al Ber report " + R.year + ".pdf");
    }, function (e) { busy(el, false); errs(e); });
  });
  document.addEventListener("input", function (e) {
    var id = e.target && e.target.id || ""; if (id.indexOf("rep_") !== 0 && id !== "repYear") return;
    R.dirty = true; if (id !== "repYear") R.draft = body();
    var k = id.slice(4); if (KINDS.indexOf(k) > -1) { delete R.codes[k === "changes" ? "x" : k.charAt(0)]; document.getElementById(id + "N").innerHTML = counter(k, e.target.value); }
  });
  document.addEventListener("change", function (e) {
    if (e.target && e.target.id === "repYear") { R.year = parseInt(e.target.value, 10); document.getElementById("repBox").innerHTML = '<p class="note" style="margin:0">…</p>'; load(); }
    else if (e.target && /^rep_c_/.test(e.target.id || "")) { R.dirty = true; R.draft = body(); }
  });

  /* ---------------- many orphans (Orphans list) ---------------- */
  var B = { year: new Date().getFullYear(), data: null };
  function batchHtml() {
    var d = B.data, c = d ? d.counts : null;
    return '<div class="rep-batch"><label for="rbYear">' + t("rp_year") + '</label><select id="rbYear">' + years().map(function (y) { return '<option value="' + y + '"' + (y === B.year ? " selected" : "") + ">" + y + "</option>"; }).join("") + "</select>" +
      (!d ? '<p class="note">…</p>' : (d.form.available ? "" : '<p class="note rep-warn">' + t("rp_noform") + "</p>") +
        '<p class="note">' + esc(t("rb_counts", { a: c.approved, d: c.draft, n: c["new"], all: c.all })) + "</p>" +
        '<div class="opts"><label class="opt rb-opt"><input type="radio" name="rbScope" value="approved" checked> <span>' + esc(t("rb_approved", { n: c.approved })) + '</span></label><label class="opt rb-opt"><input type="radio" name="rbScope" value="all"> <span>' + esc(t("rb_all", { n: c.all })) + "</span></label></div>" +
        '<p class="note">' + esc(t("rb_parts", { n: d.per_part })) + '</p><div class="err" id="rbErr" role="alert"></div><p class="note" id="rbProg" aria-live="polite"></p>' +
        '<div class="actions"><button class="btn btn-main" type="button" data-act="rbgo"' + (d.form.available ? "" : " disabled") + ">" + ic("download") + t("rb_go") + "</button></div>") + "</div>";
  }
  function batchLoad() {
    A.api.get("partner-reports?year=" + B.year, { cache: false }).then(function (r) { B.data = r.data; var s = document.querySelector(".rep-batch"); if (s) s.outerHTML = batchHtml(); },
      function (e) { var s = document.querySelector(".rep-batch"); if (s) s.innerHTML = '<p class="note">' + esc(e.offline ? t("rp_conn") : A.msg(e)) + "</p>"; });
  }
  A.act("rbopen", function () { B.data = null; A.sheet(t("rb_title"), batchHtml()); batchLoad(); });
  document.addEventListener("change", function (e) { if (e.target && e.target.id === "rbYear") { B.year = parseInt(e.target.value, 10); B.data = null; var s = document.querySelector(".rep-batch"); if (s) s.outerHTML = batchHtml(); batchLoad(); } });
  A.act("rbgo", function (el) {
    var scope = (document.querySelector("input[name=rbScope]:checked") || {}).value || "approved", got = 0, prog = document.getElementById("rbProg"), err = document.getElementById("rbErr");
    err.textContent = ""; el.disabled = true;
    function part(p, total) {
      if (total && p > total) { el.disabled = false; prog.textContent = t("rb_done", { n: got }); A.toast(t("rb_done", { n: got })); return; }
      prog.textContent = t("rb_part", { p: p, q: total || "…" });
      A.api.file("partner-reports/zip?year=" + B.year + "&scope=" + scope + "&part=" + p).then(function (f) {
        got += parseInt(f.headers.get("X-Count") || "0", 10);
        save(f.blob, f.headers, "Dar Al Ber reports " + B.year + ".zip");
        part(p + 1, parseInt(f.headers.get("X-Parts") || "1", 10));
      }, function (e) { el.disabled = false; prog.textContent = ""; err.textContent = e.status === 404 ? t("rb_none") : e.offline ? t("rp_conn") : A.msg(e); });
    }
    part(1, 0);
  });

  A.report = {
    here: here,
    card: card,
    mount: function (childId) {
      if (R.id !== childId) { R.id = childId; R.year = new Date().getFullYear(); R.data = null; R.draft = null; }
      if (!document.getElementById("repBox")) return;
      if (R.data && R.draft) paint(); else load();   // the page was drawn again while something was being typed: keep it
    },
    batchButton: function () { return here() && A.can("children.view") ? '<button class="btn btn-line btn-sm" type="button" data-act="rbopen">' + ic("download") + t("rb_btn") + "</button>" : ""; }
  };
})();
