/* Turns the text read from a document (OCR) into answers for the application form.
   Works with labelled documents: "Label: value", "Label value" or the label on one line and the value on the next,
   in English or Arabic (for example the foundation's own orphan information sheet).
   Nothing is guessed silently: every answer found is shown to the applicant, who checks and corrects it. */
(function (root) {
  var EXTRA = {
    name_en: ["full name", "name", "child name", "orphan name", "child's name", "الاسم الكامل", "اسم اليتيم", "اسم الطفل", "الاسم"],
    name_ar: ["orphan name (arabic)", "name (arabic)", "arabic name", "الاسم بالعربية"],
    gender: ["gender", "sex", "الجنس", "النوع"],
    birth_date: ["date of birth", "birth date", "birthdate", "dob", "date of birth (dob)", "تاريخ الميلاد", "تاريخ الولادة"],
    nationality: ["nationality", "الجنسية"],
    health_status: ["health status", "health", "الحالة الصحية", "الصحة"],
    health_details: ["health details", "تفاصيل الحالة الصحية", "تفاصيل الصحة"],
    study_level: ["study level", "education level", "school level", "المرحلة الدراسية", "المستوى الدراسي", "المرحلة"],
    grade: ["grade", "class", "الصف", "الفصل"],
    madrasa_grade: ["madrasa grade", "madrasah grade", "صف المدرسة الدينية", "الصف في المدرسة الدينية", "صف المدرسة"],
    reason_not_studying: ["reason for not studying", "سبب عدم الدراسة"],
    quran_memorization: ["quran memorization", "quran", "qur'an memorization", "حفظ القرآن", "مقدار الحفظ", "الحفظ"],
    birth_country: ["birth country", "country of birth", "place of birth", "بلد الميلاد", "مكان الميلاد"],
    residence_country: ["residence country", "country of residence", "بلد الإقامة", "بلد السكن"],
    city: ["city", "town", "المدينة"],
    address_ar: ["address (arabic)", "العنوان (بالعربية)", "العنوان بالعربية"],
    address_en: ["address (english)", "address", "home address"],
    mother_name_ar: ["mother name (arabic)", "اسم الأم (بالعربية)", "اسم الأم"],
    mother_name: ["mother name (english)", "mother name", "mother's name", "name of mother"],
    mother_marital_status: ["mother marital status", "mother's marital status", "الحالة الاجتماعية للأم"],
    mother_works: ["mother works", "mother's work", "mother employed", "عمل الأم", "هل تعمل الأم"],
    mother_salary: ["mother salary", "mother's salary", "mother income", "راتب الأم", "دخل الأم"],
    father_death_date: ["father death date", "date of father's death", "father's death date", "تاريخ وفاة الأب", "تاريخ الوفاة"],
    brothers: ["number of brothers", "brothers", "عدد الإخوة", "عدد الاخوة", "الإخوة"],
    sisters: ["number of sisters", "sisters", "عدد الأخوات", "الأخوات"],
    aided_siblings: ["aided brothers and sisters", "aided siblings", "sponsored siblings", "الإخوة المكفولون", "الإخوة والأخوات المكفولون"],
    other_aid: ["other aid", "other assistance", "مساعدات أخرى"],
    guardian_name: ["guardian", "guardian name", "name of guardian", "الوصي", "اسم الوصي", "ولي الأمر"],
    guardian_relation: ["relation to orphan", "relationship", "relation", "صلة القرابة", "صلة القرابة باليتيم", "القرابة"],
    guardian_phone: ["guardian phone", "phone", "phone number", "mobile", "contact number", "هاتف الوصي", "رقم الهاتف", "الهاتف", "الجوال"],
    responsible_name_ar: ["responsible person (arabic)", "المسؤول (بالعربية)"],
    responsible_name_en: ["responsible person (english)", "المسؤول (بالإنجليزية)"],
    responsible_relation: ["responsible person relation", "صلة المسؤول"],
    responsible_since: ["since", "منذ"],
    bank_holder: ["account name", "account holder", "account name (aub / hello money)", "اسم صاحب الحساب", "اسم الحساب"],
    bank_name: ["bank", "bank name", "البنك", "اسم البنك"],
    bank_last4: ["account number", "account number (last 4 digits)", "account no", "رقم الحساب"]
  };
  // labels that belong to the foundation's part of a sheet: recognised (so they are not taken as answers) but not used
  var IGNORE = ["reference", "age", "status", "channel", "source", "sponsor name", "sponsor file no", "sponsor file no.", "performance", "commitment to islamic teachings",
    "responsible person", "notable changes this year", "organization comment", "message", "delay reason", "expected send date", "form submitted", "photos uploaded", "information", "المعلومات", "الرقم المرجعي", "العمر", "الحالة"];

  function digits(s) { return String(s).replace(/[٠-٩]/g, function (d) { return "٠١٢٣٤٥٦٧٨٩".indexOf(d); }).replace(/[۰-۹]/g, function (d) { return "۰۱۲۳۴۵۶۷۸۹".indexOf(d); }); }
  function norm(s) {
    return digits(String(s || "")).toLowerCase().replace(/[ً-ْـ]/g, "").replace(/[أإآ]/g, "ا").replace(/ى/g, "ي").replace(/ة/g, "ه")
      .replace(/[:：*•·|_]+/g, " ").replace(/[()\[\]]/g, " ").replace(/\s+/g, " ").trim();
  }
  function hasArabic(s) { return /[؀-ۿ]/.test(s); }
  function pad(n) { return (n < 10 ? "0" : "") + n; }
  function toDate(v) {
    var s = digits(v).trim(), m, M = { jan: 1, feb: 2, mar: 3, apr: 4, may: 5, jun: 6, jul: 7, aug: 8, sep: 9, oct: 10, nov: 11, dec: 12 };
    if ((m = s.match(/(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})/))) return ok(+m[1], +m[2], +m[3]);
    if ((m = s.match(/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/))) return +m[1] > 12 ? ok(+m[3], +m[2], +m[1]) : ok(+m[3], +m[2], +m[1]);   // day first (as written in the Philippines and the Gulf)
    if ((m = s.match(/(\d{1,2})\s+([a-z]{3})[a-z]*\.?\s+(\d{4})/i)) && M[m[2].toLowerCase()]) return ok(+m[3], M[m[2].toLowerCase()], +m[1]);
    if ((m = s.match(/([a-z]{3})[a-z]*\.?\s+(\d{1,2}),?\s+(\d{4})/i)) && M[m[1].toLowerCase()]) return ok(+m[3], M[m[1].toLowerCase()], +m[2]);
    return null;
    function ok(y, mo, d) { return y > 1900 && y < 2100 && mo >= 1 && mo <= 12 && d >= 1 && d <= 31 ? y + "-" + pad(mo) + "-" + pad(d) : null; }
  }
  var CHOICE = {
    gender: [["f", ["female", "girl", "أنثى", "انثى", "بنت", "f"]], ["m", ["male", "boy", "ذكر", "ولد", "m"]]],
    health_status: [["needs_followup", ["follow", "متابعه", "مريض", "sick", "ill"]], ["good", ["good", "جيد", "سليم", "healthy", "well"]]],
    study_level: [["none", ["not studying", "لا يدرس", "none"]], ["kindergarten", ["kinder", "روضه", "الروضه"]], ["primary", ["primary", "elementary", "ابتدائ"]], ["intermediate", ["intermediate", "middle", "متوسط"]], ["secondary", ["secondary", "high school", "ثانوي"]], ["vocational", ["vocational", "مهني"]], ["university", ["university", "college", "جامع"]]],
    mother_marital_status: [["Widow", ["widow", "ارمله"]], ["Married", ["married", "متزوجه"]], ["Divorced", ["divorced", "مطلقه"]]],
    mother_works: [["no", ["لا تعمل", "not working", "no", "unemployed", "does not", "housewife", "ربه منزل", "لا"]], ["yes", ["تعمل", "yes", "working", "employed", "works"]]]
  };

  function convert(k, raw, fieldType) {
    var v = String(raw || "").trim(); if (!v || /^[-–—.]+$/.test(v)) return null;
    if (CHOICE[k]) { var nv = norm(v); for (var i = 0; i < CHOICE[k].length; i++) for (var j = 0; j < CHOICE[k][i][1].length; j++) { var w = norm(CHOICE[k][i][1][j]); if (w.length <= 2 ? nv === w : nv.indexOf(w) > -1) return CHOICE[k][i][0]; } return null; }
    if (fieldType === "date" || /date|since/.test(k)) return toDate(v);
    if (fieldType === "num") { var n = digits(v).match(/\d+/); return n ? String(Math.min(30, +n[0])) : null; }
    if (fieldType === "dec") { var d = digits(v).replace(/,/g, "").match(/\d+(\.\d+)?/); return d ? d[0] : null; }
    if (k === "bank_last4") { var dg = digits(v).replace(/\D/g, ""); return dg.length >= 4 ? dg.slice(-4) : null; }
    if (k === "guardian_phone") { var ph = digits(v).replace(/[^0-9+]/g, ""); return ph.length >= 7 ? ph : null; }
    return v.replace(/\s+/g, " ").slice(0, 300);
  }

  /* schema = ORPHAN_SCHEMA. Returns { values: {key: value}, found: [keys] } */
  function parse(text, schema) {
    var types = {}, labels = [];
    (schema || []).forEach(function (g) { g.fields.forEach(function (f) { types[f.k] = f; if (!f.staff) { labels.push([norm(f.en), f.k]); labels.push([norm(f.ar), f.k]); } }); });
    Object.keys(EXTRA).forEach(function (k) { EXTRA[k].forEach(function (l) { labels.push([norm(l), k]); }); });
    IGNORE.forEach(function (l) { labels.push([norm(l), null]); });
    labels = labels.filter(function (l) { return l[0]; }).sort(function (a, b) { return b[0].length - a[0].length; });
    function labelOf(line) {   // [key|null, rest of the line] when the line starts with a known label
      var n = norm(line);
      for (var i = 0; i < labels.length; i++) {
        var l = labels[i][0];
        if (n === l || n.indexOf(l + " ") === 0) {
          var words = l.split(" ").length, orig = line.indexOf(":") > -1 || line.indexOf("：") > -1 ? line.split(/[:：]/).slice(1).join(":") : line.trim().split(/\s+/).slice(words).join(" ");
          return [labels[i][1], n === l ? "" : orig.trim()];
        }
      }
      return null;
    }
    function isLabelLine(line) {   // a line that is only a label ("Gender", "Gender:"), so it cannot be the answer to the line above
      if (/[:：]\s*$/.test(line)) return !!labelOf(line);
      if (/[:：]/.test(line)) return !!labelOf(line);
      var n = norm(line); for (var i = 0; i < labels.length; i++) if (labels[i][0] === n) return true; return false;
    }
    // The reader wraps words in invisible direction marks when a page mixes Arabic and English ("\u200EDate of birth\u200F");
    // they would stop every label and date from matching, so they go first.
    text = String(text || "").replace(/[\u061C\u200B-\u200F\u202A-\u202E\u2066-\u2069\uFEFF]/g, "");
    var lines = text.split(/\r?\n/).map(function (l) { return l.trim(); }).filter(Boolean), out = {};
    for (var i = 0; i < lines.length; i++) {
      var hit = labelOf(lines[i]); if (!hit) continue;
      var k = hit[0], val = hit[1];
      if (!val && i + 1 < lines.length && !isLabelLine(lines[i + 1])) { val = lines[i + 1]; i++; }
      if (!k || !val) continue;
      if (/^(name_en|name_ar)$/.test(k)) k = hasArabic(val) ? "name_ar" : "name_en";                 // the script tells which name it is
      if (k === "mother_name" && hasArabic(val)) k = "mother_name_ar"; else if (k === "mother_name_ar" && !hasArabic(val)) k = "mother_name";
      if (k === "address_en" && hasArabic(val)) k = "address_ar";
      if (out[k] !== undefined) continue;                                                                // the first answer wins
      var c = convert(k, val, types[k] && types[k].t); if (c !== null) out[k] = c;
    }
    if (out.guardian_phone === undefined) { var m = digits(text || "").match(/(\+?63|0)9\d{2}[\s-]?\d{3}[\s-]?\d{4}/); if (m) out.guardian_phone = m[0].replace(/[\s-]/g, ""); }
    return { values: out, found: Object.keys(out) };
  }
  root.OCR_PARSE = parse;
  if (typeof module !== "undefined") module.exports = parse;
})(typeof window !== "undefined" ? window : this);
