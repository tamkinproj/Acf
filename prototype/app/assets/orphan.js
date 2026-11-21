/* Shared by the Orphans list (add), the profile (view and edit) and the application review: the form, reading it back, and showing a record.
   Everything comes from ORPHAN_SCHEMA (orphan-schema.js), so a field added there appears everywhere. */
(function () {
  var A = ACF, t = A.t, S = window.ORPHAN_SCHEMA || [];

  A.strings({
    f_status: "Status", o_edit: "Edit", active: "Active", pending: "Pending", ended: "Ended", bank_all_or_none: "Fill in all three bank fields, or none.", bank_reverify: "A changed bank account must be verified again.", o_none: "-", o_yes_note: ""
  }, {
    f_status: "الحالة", o_edit: "تعديل", active: "نشط", pending: "معلّق", ended: "منتهٍ", bank_all_or_none: "املأ حقول البنك الثلاثة كلها أو اتركها فارغة.", bank_reverify: "يجب توثيق الحساب البنكي من جديد بعد تغييره.", o_none: "-", o_yes_note: ""
  });

  function ar() { return A.lang() === "ar"; }
  function lab(x) { return ar() ? x.ar : x.en; }
  function esc(s) { return A.esc(s === null || s === undefined ? "" : String(s)); }
  function optLabel(o) { return ar() ? o[2] : o[1]; }

  function control(f, val) {
    var id = "f_" + f.k, v = val === null || val === undefined ? "" : String(val), dir = f.dir ? ' dir="' + f.dir + '"' : "";
    var inp;
    if (f.t === "sel") {
      var has = false, opts = f.o.map(function (o) { var on = o[0] === v; if (on) has = true; return '<option value="' + esc(o[0]) + '"' + (on ? " selected" : "") + ">" + esc(optLabel(o)) + "</option>"; });
      if (v && !has) opts.push('<option value="' + esc(v) + '" selected>' + esc(v) + "</option>");   // an older free-text value is kept, never lost
      inp = '<select id="' + id + '">' + opts.join("") + "</select>";
    } else if (f.t === "long") inp = '<textarea id="' + id + '" rows="3"' + dir + ">" + esc(v) + "</textarea>";
    else {
      var type = f.t === "date" ? "date" : f.t === "num" || f.t === "dec" ? "number" : f.t === "tel" ? "tel" : "text";
      inp = '<input id="' + id + '" type="' + type + '"' + dir + (f.t === "num" ? ' min="0" max="30" inputmode="numeric"' : "") + (f.t === "dec" ? ' min="0" step="0.01" inputmode="decimal"' : "") + (f.t === "tel" ? ' inputmode="tel"' : "") + (f.max ? ' maxlength="' + f.max + '"' : "") + (f.k === "bank_last4" ? ' inputmode="numeric"' : "") + ' value="' + esc(v) + '">';
    }
    return '<div><label for="' + id + '">' + esc(lab(f)) + "</label>" + inp + '<div class="err" id="' + id + 'Err"></div></div>';
  }

  /* c = the profile being edited (nothing = a new orphan). */
  /* c = the profile being edited (nothing = a new orphan). only = one group id: just that section (editing one part of a profile). */
  function formHtml(c, only, flat) {
    c = c || {}; var d = c.details || {}, edit = !!c.id, html = "";
    if (edit && (!only || only === "info")) html += '<div><label for="f_status">' + t("f_status") + '</label><select id="f_status">' + [["active", "active"], ["pending", "pending"], ["ended", "ended"]].map(function (s) { return '<option value="' + s[0] + '"' + (c.status === s[0] ? " selected" : "") + ">" + t(s[1]) + "</option>"; }).join("") + "</select></div>";
    S.forEach(function (g, i) {
      if (only && g.id !== only) return;
      var fields = g.fields;
      if (only) { html += fields.map(function (f) { return control(f, d[f.k]); }).join(""); return; }
      if (flat) { html += '<section class="fpage"><h3>' + esc(lab(g)) + '</h3><div class="fpage-body">' + fields.map(function (f) { return control(f, d[f.k]); }).join("") + "</div></section>"; return; }
      html += '<details class="fsd"' + (i === 0 ? " open" : "") + "><summary>" + esc(lab(g)) + "</summary><div class=\"fsd-body\">" + fields.map(function (f) { return control(f, d[f.k]); }).join("") + "</div></details>";
    });
    if (only === "bank") html += '<p class="note" style="margin:0">' + t("bank_reverify") + "</p>";
    return html;
  }
  function wire() {}
  function v(id) { var el = document.getElementById(id); return el ? el.value.trim() : ""; }

  /* Returns the request body, or { error: [fieldId, message] }. */
  function here(id) { return !!document.getElementById(id); }
  function readBody(edit) {
    var body = {}, g = {}, b = {}, bankShown = here("f_bank_holder");
    if (here("f_name_en") && !v("f_name_en") && !v("f_name_ar")) return { error: ["f_name_en", t("required")] };
    S.forEach(function (grp) {
      grp.fields.forEach(function (f) {
        if (!here("f_" + f.k)) return;                                       // a field not on screen is never changed
        var raw = v("f_" + f.k);
        var val = raw === "" ? null : (f.t === "num" ? parseInt(raw, 10) : f.t === "dec" ? Number(raw) : raw);
        if (f.k.indexOf("guardian_") === 0) { if (val !== null) g[f.k.slice(9)] = val; return; }
        if (f.k.indexOf("bank_") === 0) { if (val !== null) b[f.k === "bank_name" ? "bank_name" : f.k.slice(5)] = val; return; }
        if (edit) body[f.k] = val; else if (val !== null) body[f.k] = val;
      });
    });
    if (here("f_name_en") && !body.name_en) body.name_en = body.name_ar || v("f_name_ar");   // the record is listed under the English name; fall back to Arabic
    if (edit && here("f_status")) body.status = v("f_status");
    if (g.name) body.guardian = g;
    if (bankShown) {
      var filled = ["holder", "bank_name", "last4"].filter(function (k) { return b[k]; }).length;
      if (filled && filled < 3) return { error: ["formErr", t("bank_all_or_none")] };
      if (filled === 3) body.bank = b;
      if (!edit) body.pay_type = filled === 3 ? "bank" : "cash";
    }
    return body;
  }

  /* ---- showing a record: one card per group, only with what the viewer may see ---- */
  function dateText(x) { try { return new Date(x + "T00:00:00").toLocaleDateString(ar() ? "ar" : "en-GB", { day: "numeric", month: "long", year: "numeric" }); } catch (e) { return esc(x); } }
  function show(f, val) {
    if (val === null || val === undefined || val === "") return "-";
    if (f.t === "sel") { for (var i = 0; i < f.o.length; i++) if (f.o[i][0] === String(val)) return esc(optLabel(f.o[i])); return esc(val); }
    if (f.t === "date") return esc(dateText(val));
    if (f.t === "tel") return '<a dir="ltr" href="tel:' + esc(String(val).replace(/[^0-9+]/g, "")) + '">' + esc(val) + "</a>";
    if (f.k === "bank_last4") return '<span dir="ltr">•••• ' + esc(val) + "</span>";
    if (f.t === "dec") return esc(Number(val).toLocaleString("en-US", { maximumFractionDigits: 2 }));
    if (f.t === "long") return '<span style="white-space:pre-line">' + esc(val) + "</span>";
    return f.dir === "ltr" || f.t === "num" ? '<span dir="ltr">' + esc(val) + "</span>" : esc(val);
  }
  /* d = flat details. opts.bank = also show the bank group (the profile shows it in its own card, with the Verified switch). */
  function view(d, opts) {
    opts = opts || {}; d = d || {};
    return S.filter(function (g) { return opts.bank || g.id !== "bank"; }).map(function (g) {
      var fields = g.fields.filter(function (f) { return !(opts.skipStaff && f.staff); });
      if (!fields.length) return "";
      return '<div class="card osec" id="sec-' + g.id + '"><div class="osec-head"><h3>' + esc(lab(g)) + "</h3>" + (opts.edit ? '<button class="osec-edit" type="button" data-act="editsec" data-g="' + g.id + '" aria-label="' + esc(t("o_edit") + ": " + lab(g)) + '">' + A.ic("edit") + "<span>" + t("o_edit") + "</span></button>" : "") + "</div>" + A.facts(fields.map(function (f) { return [esc(lab(f)), show(f, d[f.k])]; })) + "</div>";
    }).join("");
  }
  A.orphan = { control: control, lab: lab, formHtml: formHtml, wire: wire, readBody: readBody, view: view, show: show, schema: S };
})();
