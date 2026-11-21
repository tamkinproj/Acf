/* Shared by the Orphans list (add), the profile (view and edit) and the application review: the form, reading it back, and showing a record.
   Everything comes from ORPHAN_SCHEMA (orphan-schema.js), so a field added there appears everywhere. */
(function () {
  var A = ACF, t = A.t, S = window.ORPHAN_SCHEMA || [];

  A.strings({
    f_status: "Status", active: "Active", pending: "Pending", ended: "Ended", bank_all_or_none: "Fill in all three bank fields, or none.", o_none: "-", o_yes_note: ""
  }, {
    f_status: "الحالة", active: "نشط", pending: "معلّق", ended: "منتهٍ", bank_all_or_none: "املأ حقول البنك الثلاثة كلها أو اتركها فارغة.", o_none: "-", o_yes_note: ""
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
  function formHtml(c) {
    c = c || {}; var d = c.details || {}, edit = !!c.id, html = "";
    if (edit) html += '<div><label for="f_status">' + t("f_status") + '</label><select id="f_status">' + [["active", "active"], ["pending", "pending"], ["ended", "ended"]].map(function (s) { return '<option value="' + s[0] + '"' + (c.status === s[0] ? " selected" : "") + ">" + t(s[1]) + "</option>"; }).join("") + "</select></div>";
    S.forEach(function (g, i) {
      var fields = g.fields.filter(function (f) { return !(edit && f.create); });                  // the bank account is set when the orphan is added; staff verify it on the profile
      if (!fields.length) return;
      html += '<details class="fsd"' + (i === 0 ? " open" : "") + "><summary>" + esc(lab(g)) + "</summary><div class=\"fsd-body\">" + fields.map(function (f) { return control(f, d[f.k]); }).join("") + "</div></details>";
    });
    return html;
  }
  function wire() {}
  function v(id) { var el = document.getElementById(id); return el ? el.value.trim() : ""; }

  /* Returns the request body, or { error: [fieldId, message] }. */
  function readBody(edit) {
    var body = {}, g = {}, b = {};
    if (!v("f_name_en") && !v("f_name_ar")) return { error: ["f_name_en", t("required")] };
    S.forEach(function (grp) {
      grp.fields.forEach(function (f) {
        var raw = v("f_" + f.k); if (edit && f.create) return;
        var val = raw === "" ? null : (f.t === "num" ? parseInt(raw, 10) : f.t === "dec" ? Number(raw) : raw);
        if (f.k.indexOf("guardian_") === 0) { if (val !== null) g[f.k.slice(9)] = val; return; }
        if (f.k.indexOf("bank_") === 0) { if (val !== null) b[f.k === "bank_name" ? "bank_name" : f.k.slice(5)] = val; return; }
        if (edit) body[f.k] = val; else if (val !== null) body[f.k] = val;
      });
    });
    if (!body.name_en) { if (edit) body.name_en = body.name_ar || v("f_name_ar"); else body.name_en = body.name_ar; }   // the record is listed under the English name; fall back to Arabic
    if (edit) body.status = v("f_status");
    if (g.name) body.guardian = g;
    if (!edit) {
      var filled = ["holder", "bank_name", "last4"].filter(function (k) { return b[k]; }).length;
      if (filled && filled < 3) return { error: ["formErr", t("bank_all_or_none")] };
      body.pay_type = filled === 3 ? "bank" : "cash"; if (filled === 3) body.bank = b;
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
      return '<div class="card"><h3>' + esc(lab(g)) + "</h3>" + A.facts(fields.map(function (f) { return [esc(lab(f)), show(f, d[f.k])]; })) + "</div>";
    }).join("");
  }
  A.orphan = { formHtml: formHtml, wire: wire, readBody: readBody, view: view, show: show, schema: S };
})();
