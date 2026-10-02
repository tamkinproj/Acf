import * as api from '../../core/api.js';
import { BASE } from '../../core/config.js';
import { icon } from '../../core/icons.js';
import { actionSheet, busy, confirmDialog, copyText, field, readForm, searchInput, sheet, showErrors, toast } from '../../core/ui.js';
import { $, $$, ago, debounce, fmtDate, fmtDateTime, html, initials, plural, raw, uuid7 } from '../../core/util.js';

// Registration: the forms people fill in to apply (builder), and the applications that come back (review).
//   #/programs/<id>/registration                      -> Submissions (if you may review), else Forms
//   #/programs/<id>/registration/review               -> list of submissions
//   #/programs/<id>/registration/review/<regId>       -> one submission: check it, then approve or send it back
//   #/programs/<id>/registration/forms                -> list of forms
//   #/programs/<id>/registration/forms/<formId>       -> the form builder
// The applicant-facing form is a server-rendered page; the link a published form shows opens it.

export default {
  async mount(tab) {
    const [area, id] = tab.params;
    const canForms = tab.pcan('forms.view');
    const canReview = tab.pcan('aytam.review');
    if (!canForms && !canReview) {
      tab.root.innerHTML = html`<div class="card"><div class="empty">${icon('lock')}<b>Nothing to show here</b><span>You do not have access to registration in this program.</span></div></div>`.toString();
      return;
    }
    const which = area === 'forms' && canForms ? 'forms' : area === 'review' && canReview ? 'review' : canReview ? 'review' : 'forms';
    if (which === 'forms') return id ? mountBuilder(tab, id) : mountForms(tab);
    return id ? mountReview(tab, id) : mountSubmissions(tab);
  },
};

// =====================================================================================================================
// shared helpers
// =====================================================================================================================
const flag = (on, word) => (on ? raw(word) : '');
/** Event delegation on the tab root (it is a fresh element on every visit, so nothing needs removing). */
const delegate = (root, event, selector, fn) => root.addEventListener(event, (e) => { const t = e.target.closest(selector); if (t && root.contains(t)) fn(e, t); });
const paint = (root, h) => { const y = window.scrollY; root.innerHTML = h.toString(); window.scrollTo(0, y); };
const errorsOf = (e) => (e?.errors && typeof e.errors === 'object' ? e.errors : {});
const sentence = (s) => { const t = String(s ?? '').replace(/_/g, ' ').trim(); return t.charAt(0).toUpperCase() + t.slice(1); };

const skeleton = () => html`<div class="reg" aria-busy="true"><div class="card skeleton-card"><div class="skeleton title"></div><div class="skeleton line"></div><div class="skeleton line short"></div></div>
  <div class="card flush"><div class="skeleton row"></div><div class="skeleton row"></div><div class="skeleton row"></div></div></div>`;
const failure = (message, what = 'This could not be loaded') => html`<div class="reg"><div class="card"><div class="empty">${icon('alert')}<b>${what}</b><span>${message}</span><button class="btn secondary" type="button" data-retry>Try again</button></div></div></div>`;

const FORM_STATUS = { draft: ['grey', 'Draft'], published: ['green', 'Published'], unpublished: ['orange', 'Unpublished'] };
const REG_STATUS = { pending_review: ['orange', 'Waiting for review'], needs_correction: ['blue', 'Needs correction'], approved: ['green', 'Approved'] };
const LEVEL = { exact: ['red', 'Exact match'], probable: ['orange', 'Probable match'], possible: ['grey', 'Possible match'] };
const chip = (map, key) => { const [tone, label] = map[key] ?? ['grey', sentence(key)]; return html`<span class="chip ${tone}">${label}</span>`; };

/** "Submissions | Forms" at the top of the tab (just a heading when only one of them is open to this person). */
function toolbar(tab, active, actions = '') {
  const both = tab.pcan('aytam.review') && tab.pcan('forms.view');
  const nav = both
    ? html`<nav class="segmented" aria-label="Registration">
        <a href="${tab.link('review')}" ${flag(active === 'review', 'aria-current="page"')}>Submissions</a>
        <a href="${tab.link('forms')}" ${flag(active === 'forms', 'aria-current="page"')}>Forms</a></nav>`
    : html`<h2 class="section-title reg-title">${active === 'forms' ? 'Registration forms' : 'Submissions'}</h2>`;
  return html`<div class="reg-toolbar">${nav}<div class="btn-row">${actions}</div></div>`;
}

const backLink = (href, label) => html`<a class="reg-back" href="${href}">${icon('chevron', 'flip')}${label}</a>`;

const docUrl = (tab, docId, inline = false) => `${BASE}/api/programs/${tab.program.id}/documents/${docId}/download${inline ? '?inline=1' : ''}`;
const bytes = (n) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : n >= 1024 ? `${Math.round(n / 1024)} KB` : `${n} B`);
const sayError = (e) => (e?.errors ? Object.values(e.errors).flat().filter((x) => typeof x === 'string')[0] : null) ?? api.explain(e);

// =====================================================================================================================
// FORMS: list
// =====================================================================================================================
async function mountForms(tab) {
  const base = `/programs/${tab.program.id}`;
  const canCreate = tab.pcan('forms.create');
  const canPublish = tab.pcan('forms.publish');
  const canEdit = tab.pcan('forms.update');
  let forms = [];
  let failed = null;

  const card = (f) => html`<article class="card reg-form" data-form="${f.id}">
      <div class="reg-form-head">
        <div class="grow"><h3><a href="${tab.link('forms/' + f.id)}">${f.title}</a></h3>
          <div class="prop">${chip(FORM_STATUS, f.status)}
            ${f.published_version ? html`<span>Version ${f.published_version}</span>` : ''}
            <span>${f.registrations_count ? plural(f.registrations_count, 'registration') : 'No registrations yet'}</span>
            ${f.closes_on ? html`<span>Closes ${fmtDate(f.closes_on)}</span>` : ''}
            <span>Edited ${ago(f.updated_at)}</span></div></div>
        <div class="btn-row">
          <a class="btn secondary sm" href="${tab.link('forms/' + f.id)}">${icon(canEdit ? 'edit' : 'eye')} ${canEdit ? 'Edit' : 'View'}</a>
          <button class="icon-btn" type="button" data-more="${f.id}" aria-label="More actions for ${f.title}">${icon('more')}</button></div></div>
      ${f.link ? html`<div class="secret reg-link"><code>${f.link}</code>
          <button class="btn sm secondary" type="button" data-copy="${f.link}">${icon('copy')} Copy</button>
          <a class="btn sm secondary" href="${f.link}" target="_blank" rel="noopener">Open</a></div>`
        : f.status === 'published' ? '' : html`<p class="hint">${f.status === 'unpublished' ? 'The link is switched off. People who open it are told the form is closed.' : 'Not published yet. Publish it when the questions are ready to get a link to share.'}</p>`}
    </article>`;

  const draw = () => {
    if (failed) return paint(tab.root, failure(failed, 'The forms could not be loaded'));
    paint(tab.root, html`<div class="reg">
      ${toolbar(tab, 'forms', canCreate ? html`<button class="btn" type="button" data-new>${icon('plus')} New form</button>` : '')}
      ${forms.length ? html`<div class="stack">${forms.map(card)}</div>`
        : html`<div class="card"><div class="empty">${icon('list')}<b>No registration forms yet</b><span>A form is the page families fill in to apply. Start from the standard Aytam form and adjust it.</span>
          ${canCreate ? html`<button class="btn" type="button" data-new>${icon('plus')} New form</button>` : ''}</div></div>`}
    </div>`);
  };
  const load = async () => {
    if (!forms.length) paint(tab.root, skeleton());
    try { forms = (await api.get(`${base}/forms`)).data; failed = null; } catch (e) { failed = api.explain(e); }
    draw();
  };

  delegate(tab.root, 'click', '[data-retry]', load);
  delegate(tab.root, 'click', '[data-new]', () => newFormSheet(tab, base));
  delegate(tab.root, 'click', '[data-copy]', (e, b) => copyText(b.dataset.copy));
  delegate(tab.root, 'click', '[data-more]', (e, b) => {
    const f = forms.find((x) => x.id === b.dataset.more);
    if (!f) return;
    const actions = [{ label: 'Open the builder', icon: 'edit', run: () => tab.go('forms/' + f.id) }];
    if (canPublish && f.status === 'published') {
      actions.push({ label: 'Replace the link', icon: 'rotate', run: () => replaceLink(f) });
      actions.push({ label: 'Unpublish', icon: 'x', run: () => unpublish(f) });
    }
    if (canPublish && f.status !== 'published') actions.push({ label: 'Publish', icon: 'check', run: () => publish(f) });
    if (canEdit && !f.registrations_count) actions.push({ label: 'Delete this form', icon: 'trash', danger: true, run: () => remove(f) });
    actionSheet({ title: f.title, actions });
  });

  const act = async (fn, okText) => { try { await fn(); toast(okText); await load(); } catch (e) { toast(sayError(e), 'bad'); } };
  const publish = async (f) => {
    try { await api.post(`${base}/forms/${f.id}/publish`); toast('Published. The link is live.'); await load(); } catch (e) {
      toast(`${sayError(e)} Open the builder to fix it.`, 'bad');
    }
  };
  const unpublish = async (f) => {
    if (!(await confirmDialog({ title: 'Unpublish this form?', text: 'The link stops accepting new registrations straight away. Registrations already received are kept, and you can publish again later.', confirmLabel: 'Unpublish', danger: true }))) return;
    act(() => api.post(`${base}/forms/${f.id}/unpublish`), 'Unpublished.');
  };
  const replaceLink = async (f) => {
    if (!(await confirmDialog({ title: 'Replace the link?', text: 'The old link stops working immediately, for everyone who has it. Applicants who already registered keep their private status link. Share the new link instead.', confirmLabel: 'Replace link', danger: true }))) return;
    act(() => api.post(`${base}/forms/${f.id}/regenerate-link`), 'New link created. The old one no longer works.');
  };
  const remove = async (f) => {
    if (!(await confirmDialog({ title: `Delete "${f.title}"?`, text: 'The form and its questions are deleted. This cannot be undone.', confirmLabel: 'Delete', danger: true }))) return;
    act(() => api.del(`${base}/forms/${f.id}`), 'Form deleted.');
  };

  await load();
}

function newFormSheet(tab, base) {
  sheet({
    title: 'New registration form',
    body: html`<form class="form" novalidate>
      ${field({ label: 'Title', name: 'title', required: true, value: 'Aytam registration', hint: 'Applicants see this at the top of the form.' })}
      <div class="field"><label for="f-description">Introduction (optional)</label><textarea class="textarea" id="f-description" name="description"></textarea><span class="err" data-err="description" hidden></span></div>
      <div class="field"><label for="f-template">Start from</label><select class="select" id="f-template" name="template">
        <option value="standard">Standard Aytam form (recommended)</option><option value="blank">Blank</option></select>
        <span class="hint">The standard form already asks what an Aytam record needs, each question connected to the right field.</span></div>
      <div class="btn-row"><button class="btn" type="submit">Create form</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const btn = $('button[type=submit]', form);
      busy(btn, true, 'Creating…');
      try {
        const { data } = await api.post(`${base}/forms`, readForm(form));
        close();
        toast('Form created.');
        tab.go('forms/' + data.id);
      } catch (err) {
        if (!showErrors(form, errorsOf(err))) toast(api.explain(err), 'bad');
        busy(btn, false);
      }
    }),
  });
}

// =====================================================================================================================
// FORMS: builder
// =====================================================================================================================
async function mountBuilder(tab, formId) {
  const base = `/programs/${tab.program.id}`;
  const canEdit = tab.pcan('forms.update');
  const canPublish = tab.pcan('forms.publish');
  let form = null;
  let cat = null;
  let draft = [];          // the working copy: [{ uid, id?, title, description, fields: [{ uid, id?, key?, label, type, ... }] }]
  let savedJson = '';
  let problems = [];
  let errs = {};           // uid -> [messages] from the last failed save
  let topErrors = [];
  let mode = 'edit';
  let busyPublishing = false;
  let announce = '';

  // ---- structure <-> draft -------------------------------------------------------------------------------------
  const toDraft = (structure) => structure.sections.map((s) => ({
    uid: uuid7(), id: s.id, title: s.title, description: s.description ?? '',
    fields: s.fields.map((f) => ({ uid: uuid7(), id: f.id, key: f.key, label: f.label, type: f.type, required: !!f.required, help_text: f.help_text ?? '', options: [...(f.options ?? [])], maps_to: f.maps_to ?? null, document_type: f.document_type ?? null })),
  }));
  const typeInfo = (type) => cat.types.find((t) => t.type === type) ?? { type, label: sentence(type), options: false, file: false };
  const canon = (key) => cat.canonical.find((c) => c.key === key);
  const payload = () => ({
    sections: draft.map((s) => ({
      ...(s.id ? { id: s.id } : {}), title: s.title, description: s.description || null,
      fields: s.fields.map((f) => ({
        ...(f.id ? { id: f.id } : {}), ...(f.key ? { key: f.key } : {}), label: f.label, type: f.type, required: !!f.required, help_text: f.help_text || null,
        options: typeInfo(f.type).options ? f.options : [], maps_to: f.maps_to || null, document_type: typeInfo(f.type).file ? f.document_type : null,
      })),
    })),
  });
  const dirty = () => JSON.stringify(payload()) !== savedJson;
  const adopt = (detail) => { form = detail; draft = toDraft(detail.structure); savedJson = JSON.stringify(payload()); problems = detail.problems ?? []; errs = {}; topErrors = []; };
  const locate = (uid) => { for (const [si, s] of draft.entries()) { if (s.uid === uid) return { si, s }; const fi = s.fields.findIndex((f) => f.uid === uid); if (fi >= 0) return { si, fi, s, f: s.fields[fi] }; } return null; };

  // ---- drawing -------------------------------------------------------------------------------------------------
  const questionRow = (s, si, f, fi) => {
    const last = si === draft.length - 1 && fi === s.fields.length - 1;
    const first = si === 0 && fi === 0;
    const def = f.maps_to ? canon(f.maps_to) : null;
    const bad = errs[f.uid];
    return html`<li class="reg-q ${bad ? 'has-error' : ''}" data-uid="${f.uid}">
      <button class="reg-q-main" type="button" data-edit-q="${f.uid}" ${flag(!canEdit, 'disabled')}>
        <span class="t">${f.label || 'Untitled question'}${f.required ? html` <span class="chip blue">Required</span>` : ''}</span>
        <span class="d">${typeInfo(f.type).label}${typeInfo(f.type).options && f.options.length ? ` · ${plural(f.options.length, 'option')}` : ''}${def ? html` · <span class="reg-map">→ ${def.label}</span>` : ''}</span>
        ${bad ? html`<span class="reg-err">${bad.join(' ')}</span>` : ''}
      </button>
      ${canEdit ? html`<div class="reg-tools" role="group" aria-label="Actions for ${f.label}">
        <button class="icon-btn" type="button" data-move="up" data-uid="${f.uid}" aria-label="Move up" ${flag(first, 'disabled')}>${icon('arrowUp')}</button>
        <button class="icon-btn" type="button" data-move="down" data-uid="${f.uid}" aria-label="Move down" ${flag(last, 'disabled')}>${icon('arrowDown')}</button>
        <button class="icon-btn" type="button" data-del-q="${f.uid}" aria-label="Delete question">${icon('trash')}</button></div>` : ''}
    </li>`;
  };

  const sectionCard = (s, si) => html`<section class="card reg-section ${errs[s.uid] ? 'has-error' : ''}" data-uid="${s.uid}" aria-label="${s.title}">
      <div class="reg-section-head">
        <div class="grow"><span class="reg-step">Section ${si + 1}</span><h3>${s.title || 'Untitled section'}</h3>${s.description ? html`<p class="muted">${s.description}</p>` : ''}${errs[s.uid] ? html`<p class="reg-err">${errs[s.uid].join(' ')}</p>` : ''}</div>
        ${canEdit ? html`<div class="reg-tools" role="group" aria-label="Actions for section ${s.title}">
          <button class="icon-btn" type="button" data-smove="up" data-uid="${s.uid}" aria-label="Move section up" ${flag(si === 0, 'disabled')}>${icon('arrowUp')}</button>
          <button class="icon-btn" type="button" data-smove="down" data-uid="${s.uid}" aria-label="Move section down" ${flag(si === draft.length - 1, 'disabled')}>${icon('arrowDown')}</button>
          <button class="icon-btn" type="button" data-edit-s="${s.uid}" aria-label="Rename section">${icon('edit')}</button>
          <button class="icon-btn" type="button" data-del-s="${s.uid}" aria-label="Delete section">${icon('trash')}</button></div>` : ''}
      </div>
      ${s.fields.length ? html`<ul class="list reg-qs">${s.fields.map((f, fi) => questionRow(s, si, f, fi))}</ul>`
        : html`<p class="reg-empty-section">No questions in this section yet.</p>`}
      ${canEdit ? html`<div class="btn-row"><button class="btn secondary sm" type="button" data-add-q="${s.uid}">${icon('plus')} Add question</button></div>` : ''}
    </section>`;

  const publishCard = () => {
    const isDirty = dirty();
    const live = form.status === 'published';
    const body = [];
    if (isDirty) body.push(html`<p class="muted">Save your changes to check whether the form is ready to publish.</p>`);
    else if (problems.length) {
      body.push(html`<div class="banner warn">${icon('alert')}<div class="grow"><b>${plural(problems.length, 'thing')} to fix before publishing</b>
        <ul class="reg-problems">${problems.map((p) => html`<li>${p.message}${p.field && draft.some((s) => s.fields.some((f) => f.key === p.field)) ? html` <button class="link" type="button" data-fix="${p.field}">Go to question</button>` : ''}</li>`)}</ul></div></div>`);
    } else body.push(html`<p class="reg-ready">${icon('check')} Everything needed is there.</p>`);

    if (live && form.unpublished_changes && !isDirty) {
      body.push(html`<div class="banner info">${icon('info')}<div class="grow">You have changes that are not published yet. The live link still shows version ${form.published_version}.</div></div>`);
    }
    if (live && form.link) {
      body.push(html`<div class="field"><span class="lbl">Link to share</span><div class="secret reg-link"><code>${form.link}</code></div>
        <div class="btn-row"><button class="btn sm secondary" type="button" data-copy="${form.link}">${icon('copy')} Copy</button><a class="btn sm secondary" href="${form.link}" target="_blank" rel="noopener">Open</a></div></div>`);
    }
    if (form.settings?.closes_on) body.push(html`<p class="hint">Closes on ${fmtDate(form.settings.closes_on)}.</p>`);

    const actions = [];
    if (canPublish) {
      const blocked = isDirty || problems.length > 0;
      const label = live ? (form.unpublished_changes ? 'Publish changes' : 'Publish again') : form.status === 'unpublished' ? 'Publish again' : 'Publish form';
      if (!live || form.unpublished_changes) actions.push(html`<button class="btn" type="button" data-publish ${flag(blocked || busyPublishing, 'disabled')}>${label}</button>`);
      if (live) {
        actions.push(html`<button class="btn secondary sm" type="button" data-replace>${icon('rotate')} Replace link</button>`);
        actions.push(html`<button class="btn danger sm" type="button" data-unpublish>Unpublish</button>`);
      }
    }
    return html`<section class="card reg-publish" aria-label="Ready to publish?">
      <div class="card-head"><div><h3>Ready to publish?</h3></div>${chip(FORM_STATUS, form.status)}</div>
      <div class="stack">${body}${actions.length ? html`<div class="btn-row">${actions}</div>` : ''}
        ${!isDirty ? html`<button class="link reg-recheck" type="button" data-check>Check again</button>` : ''}</div></section>`;
  };

  const previewField = (f) => {
    const ro = raw('disabled');
    const label = html`<span class="lbl">${f.label}${f.required ? raw(' <span aria-hidden="true">*</span>') : ''}</span>`;
    const hint = f.help_text ? html`<span class="hint">${f.help_text}</span>` : '';
    const opts = f.options ?? [];
    switch (f.type) {
      case 'long_text': return html`<div class="field"><label>${label}</label><textarea class="textarea" ${ro}></textarea>${hint}</div>`;
      case 'dropdown': return html`<div class="field"><label>${label}</label><select class="select" ${ro}><option>Choose…</option>${opts.map((o) => html`<option>${o}</option>`)}</select>${hint}</div>`;
      case 'multiple_choice': case 'checkbox': case 'yes_no': {
        const items = f.type === 'yes_no' ? ['Yes', 'No'] : opts;
        return html`<fieldset class="field reg-choices"><legend class="lbl">${label}</legend>${items.map((o) => html`<label><input type="${f.type === 'checkbox' ? 'checkbox' : 'radio'}" ${ro}> ${o}</label>`)}${hint}</fieldset>`;
      }
      case 'file_upload': case 'photo': return html`<div class="field"><span class="lbl">${label}</span><span class="btn secondary sm reg-fake">${icon('upload')} ${f.type === 'photo' ? 'Take or choose a photo' : 'Choose a file'}</span>${hint}</div>`;
      case 'address': return html`<fieldset class="field"><legend class="lbl">${label}</legend><div class="row-2">${['Country', 'Region', 'Province', 'City / municipality', 'Barangay', 'Street / details'].map((p) => html`<input class="input" placeholder="${p}" ${ro}>`)}</div>${hint}</fieldset>`;
      default: return html`<div class="field"><label>${label}</label><input class="input" type="${{ number: 'number', date: 'date', email: 'email', phone: 'tel' }[f.type] ?? 'text'}" ${ro}>${hint}</div>`;
    }
  };
  const preview = () => html`<div class="card reg-preview">
      <div class="banner info">${icon('eye')}<div class="grow">This is how the form reads to an applicant. It shows what is in the builder now, even if you have not saved or published it yet.</div></div>
      <div class="reg-preview-body" inert>
        <h3>${form.title}</h3>${form.description ? html`<p class="muted">${form.description}</p>` : ''}
        ${draft.map((s) => html`<div class="reg-preview-sec"><h4>${s.title}</h4>${s.description ? html`<p class="muted">${s.description}</p>` : ''}${s.fields.map(previewField)}</div>`)}
      </div></div>`;

  const draw = (focus) => {
    const isDirty = dirty();
    paint(tab.root, html`<div class="reg">
      ${backLink(tab.link('forms'), 'Forms')}
      <div class="page-head reg-head"><div><h2 class="reg-h">${form.title}</h2>${form.description ? html`<p>${form.description}</p>` : ''}</div>
        <div class="btn-row">${canEdit ? html`<button class="btn secondary" type="button" data-details>${icon('edit')} Form details</button>` : ''}</div></div>
      ${topErrors.length ? html`<div class="banner bad" role="alert">${icon('alert')}<div class="grow">${topErrors.map((m) => html`<div>${m}</div>`)}</div></div>` : ''}
      ${Object.keys(errs).length ? html`<div class="banner bad" role="alert">${icon('alert')}<div class="grow">Some questions need attention. They are marked in red below.</div></div>` : ''}
      <div class="reg-builder">
        <aside class="reg-aside">${publishCard()}</aside>
        <div class="reg-main">
          <div class="reg-modes"><nav class="segmented" aria-label="View"><button type="button" data-mode="edit" class="${mode === 'edit' ? 'on' : ''}" aria-pressed="${String(mode === 'edit')}">${canEdit ? 'Edit' : 'Questions'}</button><button type="button" data-mode="preview" class="${mode === 'preview' ? 'on' : ''}" aria-pressed="${String(mode === 'preview')}">Preview</button></nav></div>
          ${mode === 'preview' ? preview()
            : html`${draft.length ? draft.map(sectionCard) : html`<div class="card"><div class="empty">${icon('list')}<b>No sections yet</b><span>Add a section, then the questions applicants should answer.</span></div></div>`}
              ${canEdit ? html`<div class="btn-row"><button class="btn secondary" type="button" data-add-s>${icon('plus')} Add section</button></div>` : ''}`}
        </div>
      </div>
      <div class="sr-only" role="status" aria-live="polite" data-live>${announce}</div>
      ${isDirty && canEdit ? html`<div class="reg-savebar" role="region" aria-label="Unsaved changes"><span>${icon('info')} You have unsaved changes</span>
        <div class="btn-row"><button class="btn secondary sm" type="button" data-discard>Discard</button><button class="btn sm" type="button" data-save>Save changes</button></div></div>` : ''}
    </div>`);
    announce = '';
    if (focus) $(focus, tab.root)?.focus();
  };

  // ---- editing in memory ---------------------------------------------------------------------------------------
  const takenMappings = (exceptUid) => new Set(draft.flatMap((s) => s.fields).filter((f) => f.uid !== exceptUid && f.maps_to).map((f) => f.maps_to));
  const move = (uid, dir) => {
    const at = locate(uid);
    if (!at?.f) return;
    const { si, fi, s, f } = at;
    const step = dir === 'up' ? -1 : 1;
    const target = fi + step;
    if (target >= 0 && target < s.fields.length) [s.fields[fi], s.fields[target]] = [s.fields[target], s.fields[fi]];
    else {
      const neighbour = draft[si + step];
      if (!neighbour) return;
      s.fields.splice(fi, 1);
      if (step < 0) neighbour.fields.push(f); else neighbour.fields.unshift(f);
    }
    const now = locate(uid);
    announce = `${f.label} moved ${dir}. Now question ${now.fi + 1} of ${now.s.fields.length} in ${now.s.title}.`;
    draw(`[data-move="${dir}"][data-uid="${uid}"]:not([disabled])`);
    if (!$(`[data-move="${dir}"][data-uid="${uid}"]:not([disabled])`, tab.root)) $(`[data-move][data-uid="${uid}"]:not([disabled])`, tab.root)?.focus();
  };
  const moveSection = (uid, dir) => {
    const at = locate(uid);
    const to = at.si + (dir === 'up' ? -1 : 1);
    if (to < 0 || to >= draft.length) return;
    [draft[at.si], draft[to]] = [draft[to], draft[at.si]];
    announce = `Section ${at.s.title} moved ${dir}. Now section ${to + 1} of ${draft.length}.`;
    draw(`[data-smove="${dir}"][data-uid="${uid}"]:not([disabled])`);
    if (!$(`[data-smove="${dir}"][data-uid="${uid}"]:not([disabled])`, tab.root)) $(`[data-smove][data-uid="${uid}"]:not([disabled])`, tab.root)?.focus();
  };

  const openQuestion = (uid, sectionUid) => {
    const at = uid ? locate(uid) : null;
    const target = at?.f ?? null;
    const section = at?.s ?? locate(sectionUid)?.s;
    questionSheet({
      cat, value: target, taken: takenMappings(uid),
      onApply: (v) => {
        if (target) { Object.assign(target, v); delete errs[target.uid]; announce = 'Question updated.'; } else { section.fields.push({ uid: uuid7(), ...v }); announce = 'Question added.'; }
        draw();
      },
    });
  };
  const openSection = (uid) => {
    const at = uid ? locate(uid) : null;
    sheet({
      title: at ? 'Rename section' : 'New section',
      body: html`<form class="form" novalidate>
        ${field({ label: 'Section title', name: 'title', value: at?.s.title ?? '', required: true, hint: 'For example "About the child".' })}
        <div class="field"><label for="f-description">Short explanation (optional)</label><textarea class="textarea" id="f-description" name="description">${at?.s.description ?? ''}</textarea></div>
        <div class="btn-row"><button class="btn" type="submit">${at ? 'Done' : 'Add section'}</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
      onMount: (el, close) => $('form', el).addEventListener('submit', (e) => {
        e.preventDefault();
        const v = readForm(e.currentTarget);
        if (!v.title) { showErrors(e.currentTarget, { title: 'Give the section a title.' }); return; }
        if (at) { at.s.title = v.title; at.s.description = v.description ?? ''; delete errs[at.s.uid]; } else draft.push({ uid: uuid7(), title: v.title, description: v.description ?? '', fields: [] });
        close();
        draw();
      }),
    });
  };
  const deleteQuestion = async (uid) => {
    const at = locate(uid);
    if (!(await confirmDialog({ title: 'Delete this question?', text: `"${at.f.label}" is removed from the form when you save. Answers already received are kept.`, confirmLabel: 'Delete question', danger: true }))) return;
    at.s.fields.splice(at.fi, 1);
    announce = 'Question deleted.';
    draw();
  };
  const deleteSection = async (uid) => {
    const at = locate(uid);
    if (!(await confirmDialog({ title: 'Delete this section?', text: at.s.fields.length ? `"${at.s.title}" and its ${plural(at.s.fields.length, 'question')} are removed from the form when you save.` : `"${at.s.title}" is removed from the form when you save.`, confirmLabel: 'Delete section', danger: true }))) return;
    draft.splice(at.si, 1);
    announce = 'Section deleted.';
    draw();
  };

  const detailsSheet = () => sheet({
    title: 'Form details',
    body: html`<form class="form" novalidate>
      ${field({ label: 'Title', name: 'title', value: form.title, required: true })}
      <div class="field"><label for="f-description">Introduction</label><textarea class="textarea" id="f-description" name="description">${form.description ?? ''}</textarea><span class="err" data-err="description" hidden></span></div>
      ${field({ label: 'Closing date (optional)', name: 'closes_on', type: 'date', value: form.settings?.closes_on ?? '', hint: 'After this day the link stops accepting registrations. Leave empty to keep it open.' })}
      <div class="field"><label for="f-success_message">Message after sending (optional)</label><textarea class="textarea" id="f-success_message" name="success_message">${form.settings?.success_message ?? ''}</textarea><span class="hint">Shown to the applicant once the registration is received.</span><span class="err" data-err="success_message" hidden></span></div>
      <div class="btn-row"><button class="btn" type="submit">Save details</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
      e.preventDefault();
      const f = e.currentTarget;
      const v = readForm(f);
      const btn = $('button[type=submit]', f);
      busy(btn, true, 'Saving…');
      try {
        const { data } = await api.patch(`${base}/forms/${form.id}`, { title: v.title, description: v.description, settings: { closes_on: v.closes_on, success_message: v.success_message } });
        form = { ...form, title: data.title, description: data.description, settings: data.settings, unpublished_changes: data.unpublished_changes, status: data.status };
        close();
        toast('Details saved.');
        tab.setTitle(form.title);
        draw();
      } catch (err) {
        const er = errorsOf(err);
        if (!showErrors(f, { ...er, closes_on: er['settings.closes_on'], success_message: er['settings.success_message'] })) toast(sayError(err), 'bad');
        busy(btn, false);
      }
    }),
  });

  // ---- talking to the server -----------------------------------------------------------------------------------
  const place = (errors) => {
    errs = {}; topErrors = [];
    for (const [path, msgs] of Object.entries(errors)) {
      const list = [].concat(msgs);
      const m = path.match(/^sections\.(\d+)(?:\.fields\.(\d+))?/);
      const s = m ? draft[+m[1]] : null;
      const target = s && m[2] !== undefined ? s.fields[+m[2]] : s;
      if (target) (errs[target.uid] ||= []).push(...list); else topErrors.push(...list);
    }
  };
  const save = async (btn) => {
    busy(btn, true, 'Saving…');
    try {
      const { data } = await api.put(`${base}/forms/${form.id}/structure`, payload());
      adopt(data);
      toast('Saved.');
      draw();
      refreshCheck();
    } catch (e) {
      busy(btn, false);
      const er = errorsOf(e);
      if (e.status === 422 && Object.keys(er).length) {
        place(er);
        draw();
        toast(Object.keys(errs).length ? 'Some questions need attention.' : (topErrors[0] ?? 'The form could not be saved.'), 'bad');
        $('.has-error', tab.root)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
      } else toast(sayError(e), 'bad');
    }
  };
  const refreshCheck = async () => {
    try { problems = (await api.get(`${base}/forms/${form.id}/check`)).data.problems; draw(); } catch { /* the panel keeps its last answer */ }
  };
  const publish = async (btn) => {
    busyPublishing = true;
    busy(btn, true, 'Publishing…');
    try {
      const { data } = await api.post(`${base}/forms/${form.id}/publish`);
      adopt(data);
      toast('Published. The link is live.');
    } catch (e) {
      const er = errorsOf(e);
      toast(sayError(e), 'bad');
      if (er.form) problems = er.form.map((message) => ({ field: null, message }));
    }
    busyPublishing = false;
    draw();
  };
  const unpublish = async () => {
    if (!(await confirmDialog({ title: 'Unpublish this form?', text: 'The link stops accepting new registrations straight away. Registrations already received are kept, and publishing again brings the same link back.', confirmLabel: 'Unpublish', danger: true }))) return;
    try { adopt((await api.post(`${base}/forms/${form.id}/unpublish`)).data); toast('Unpublished.'); draw(); } catch (e) { toast(sayError(e), 'bad'); }
  };
  const replaceLink = async () => {
    if (!(await confirmDialog({ title: 'Replace the link?', text: 'The old link stops working immediately, for everyone who has it. Applicants who already registered keep their private status link. Share the new link instead.', confirmLabel: 'Replace link', danger: true }))) return;
    try { adopt((await api.post(`${base}/forms/${form.id}/regenerate-link`)).data); toast('New link created. The old one no longer works.'); draw(); } catch (e) { toast(sayError(e), 'bad'); }
  };

  // ---- events --------------------------------------------------------------------------------------------------
  const r = tab.root;
  delegate(r, 'click', '[data-retry]', () => start());
  delegate(r, 'click', '[data-mode]', (e, b) => { mode = b.dataset.mode; draw(`[data-mode="${mode}"]`); });
  delegate(r, 'click', '[data-move]', (e, b) => move(b.dataset.uid, b.dataset.move));
  delegate(r, 'click', '[data-smove]', (e, b) => moveSection(b.dataset.uid, b.dataset.smove));
  delegate(r, 'click', '[data-edit-q]', (e, b) => openQuestion(b.dataset.editQ));
  delegate(r, 'click', '[data-add-q]', (e, b) => openQuestion(null, b.dataset.addQ));
  delegate(r, 'click', '[data-del-q]', (e, b) => deleteQuestion(b.dataset.delQ));
  delegate(r, 'click', '[data-edit-s]', (e, b) => openSection(b.dataset.editS));
  delegate(r, 'click', '[data-add-s]', () => openSection(null));
  delegate(r, 'click', '[data-del-s]', (e, b) => deleteSection(b.dataset.delS));
  delegate(r, 'click', '[data-details]', detailsSheet);
  delegate(r, 'click', '[data-copy]', (e, b) => copyText(b.dataset.copy));
  delegate(r, 'click', '[data-save]', (e, b) => save(b));
  delegate(r, 'click', '[data-discard]', async () => {
    if (!(await confirmDialog({ title: 'Discard your changes?', text: 'Everything since the last save is lost.', confirmLabel: 'Discard changes', danger: true }))) return;
    draft = toDraft({ sections: JSON.parse(savedJson).sections.map((s) => ({ ...s, fields: s.fields })) });
    errs = {}; topErrors = [];
    draw();
  });
  delegate(r, 'click', '[data-publish]', (e, b) => publish(b));
  delegate(r, 'click', '[data-unpublish]', unpublish);
  delegate(r, 'click', '[data-replace]', replaceLink);
  delegate(r, 'click', '[data-check]', refreshCheck);
  delegate(r, 'click', '[data-fix]', (e, b) => {
    const f = draft.flatMap((s) => s.fields).find((x) => x.key === b.dataset.fix);
    if (f) openQuestion(f.uid);
  });

  // Leaving with unsaved changes: the browser asks for tab closes, and we ask for in-app links.
  const beforeUnload = (e) => { if (dirty()) { e.preventDefault(); e.returnValue = ''; } };
  const guardLinks = async (e) => {
    const a = e.target.closest?.('a[href]');
    if (!a || a.target === '_blank' || !dirty()) return;
    e.preventDefault();
    e.stopPropagation();
    if (await confirmDialog({ title: 'Leave without saving?', text: 'You have changes in the builder that are not saved. They will be lost.', confirmLabel: 'Leave', cancelLabel: 'Stay here', danger: true })) {
      savedJson = JSON.stringify(payload());
      location.href = a.href;
    }
  };
  window.addEventListener('beforeunload', beforeUnload);
  document.addEventListener('click', guardLinks, true);

  const start = async () => {
    paint(tab.root, skeleton());
    try {
      const [detail, catalogue] = await Promise.all([api.get(`${base}/forms/${formId}`), cat ? { data: cat } : api.get(`${base}/forms-catalogue`)]);
      cat = catalogue.data;
      adopt(detail.data);
      tab.setTitle(form.title);
      draw();
    } catch (e) {
      paint(tab.root, e.status === 404
        ? html`<div class="reg">${backLink(tab.link('forms'), 'Forms')}<div class="card"><div class="empty">${icon('alert')}<b>Form not found</b><span>It may have been deleted.</span><a class="btn secondary" href="${tab.link('forms')}">Back to forms</a></div></div></div>`
        : failure(api.explain(e), 'The form could not be opened'));
    }
  };
  await start();
  return () => { window.removeEventListener('beforeunload', beforeUnload); document.removeEventListener('click', guardLinks, true); };
}

/** The question editor. `value` is the question being changed (null for a new one); onApply gets the new settings. */
function questionSheet({ cat, value, taken, onApply }) {
  const v = value ?? { type: 'short_text', label: '', help_text: '', required: false, options: [], maps_to: null, document_type: null };
  const info = (t) => cat.types.find((x) => x.type === t) ?? {};
  const docLabel = (t) => sentence(t);
  const mapOptions = (type, current) => {
    const groups = {};
    for (const d of cat.canonical) if (d.types.includes(type)) (groups[d.group] ||= []).push(d);
    return html`<option value="">Nothing - just keep the answer</option>${Object.entries(groups).map(([g, defs]) => html`<optgroup label="${g}">${defs.map((d) => {
      const used = taken.has(d.key) && d.key !== current;
      return html`<option value="${d.key}" ${flag(d.key === current, 'selected')} ${flag(used, 'disabled')}>${d.label}${used ? ' (already used)' : ''}</option>`;
    })}</optgroup>`)}`;
  };

  sheet({
    title: value ? 'Edit question' : 'New question',
    body: html`<form class="form" novalidate>
      <div class="field"><label for="q-type">Type of answer</label><select class="select" id="q-type" name="type">${cat.types.map((t) => html`<option value="${t.type}" ${flag(t.type === v.type, 'selected')}>${t.label}</option>`)}</select></div>
      <div class="field"><label for="q-label">Question</label><input class="input" id="q-label" name="label" value="${v.label}" maxlength="255" autocomplete="off" autofocus><span class="err" data-err="label" hidden></span></div>
      <div class="field"><label for="q-help">Help text (optional)</label><input class="input" id="q-help" name="help_text" value="${v.help_text ?? ''}" maxlength="1000" autocomplete="off"><span class="hint">A short line under the question, for example "As written on the birth certificate".</span></div>
      <label class="check"><input type="checkbox" name="required" ${flag(v.required, 'checked')}> <span>Applicants must answer this</span></label>
      <div class="field" data-opts ${flag(!info(v.type).options, 'hidden')}><label for="q-options">Choices (one per line)</label><textarea class="textarea" id="q-options" name="options" rows="5">${(v.options ?? []).join('\n')}</textarea><span class="hint" data-opts-hint></span><span class="err" data-err="options" hidden></span></div>
      <div class="field" data-doc ${flag(!info(v.type).file, 'hidden')}><label for="q-doc">Kind of document</label><select class="select" id="q-doc" name="document_type">${cat.document_types.map((t) => html`<option value="${t}" ${flag(t === (v.document_type ?? (v.type === 'photo' ? 'photo' : 'other')), 'selected')}>${docLabel(t)}</option>`)}</select><span class="hint">Where the file is filed on the child's record once approved.</span></div>
      <div class="field"><label for="q-map">Fill this field of the child's record</label><select class="select" id="q-map" name="maps_to">${mapOptions(v.type, v.maps_to)}</select><span class="hint" data-map-hint></span><span class="err" data-err="maps_to" hidden></span></div>
      <div class="btn-row"><button class="btn" type="submit">${value ? 'Done' : 'Add question'}</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => {
      const form = $('form', el);
      const typeSel = $('[name=type]', form);
      const mapSel = $('[name=maps_to]', form);
      const optsBox = $('[name=options]', form);
      let lastEnum = '';
      const enumOf = () => cat.canonical.find((c) => c.key === mapSel.value)?.enum ?? null;
      const refresh = () => {
        const t = info(typeSel.value);
        $('[data-opts]', form).hidden = !t.options;
        $('[data-doc]', form).hidden = !t.file;
        const en = enumOf();
        $('[data-map-hint]', form).textContent = en ? `This field only accepts: ${en.join(', ')}.` : mapSel.value ? '' : 'Pick one to have the answer copied into the record automatically when you approve a registration.';
        $('[data-opts-hint]', form).textContent = en && t.options ? `The mapped field only accepts: ${en.join(', ')}.` : 'Applicants pick from these. Each choice must be different.';
        if (typeSel.value === 'photo' && !$('[name=document_type]', form).dataset.touched) $('[name=document_type]', form).value = 'photo';
      };
      typeSel.addEventListener('change', () => {
        mapSel.innerHTML = mapOptions(typeSel.value, mapSel.value).toString();
        refresh();
      });
      mapSel.addEventListener('change', () => {
        const en = enumOf();
        // Prefill the choices with the values the record accepts, unless the person already wrote their own.
        if (en && info(typeSel.value).options && (optsBox.value.trim() === '' || optsBox.value.trim() === lastEnum)) { optsBox.value = en.join('\n'); lastEnum = en.join('\n'); }
        refresh();
      });
      $('[name=document_type]', form).addEventListener('change', (e) => { e.target.dataset.touched = '1'; });
      refresh();

      form.addEventListener('submit', (e) => {
        e.preventDefault();
        const f = new FormData(form);
        const type = f.get('type');
        const label = String(f.get('label') ?? '').trim();
        const options = String(f.get('options') ?? '').split('\n').map((x) => x.trim()).filter(Boolean);
        const problems = {};
        if (!label) problems.label = 'Write the question.';
        if (info(type).options) {
          if (!options.length) problems.options = 'Give at least one choice.';
          else if (new Set(options).size !== options.length) problems.options = 'Each choice must be different.';
          else { const en = enumOf(); const bad = en ? options.filter((o) => !en.includes(o)) : []; if (bad.length) problems.options = `The mapped field only accepts: ${en.join(', ')}.`; }
        }
        if (Object.keys(problems).length) { showErrors(form, problems); $('.err:not([hidden])', form)?.closest('.field')?.querySelector('input,textarea')?.focus(); return; }
        close();
        onApply({
          type, label, help_text: String(f.get('help_text') ?? '').trim(), required: f.get('required') === 'on', options: info(type).options ? options : [],
          maps_to: f.get('maps_to') || null, document_type: info(type).file ? f.get('document_type') : null,
        });
      });
    },
  });
}

// =====================================================================================================================
// SUBMISSIONS: list
// =====================================================================================================================
async function mountSubmissions(tab) {
  const base = `/programs/${tab.program.id}`;
  const canForms = tab.pcan('forms.view');
  const store = { get: () => { try { return JSON.parse(sessionStorage.getItem('reg-filter') ?? '{}'); } catch { return {}; } }, set: (v) => { try { sessionStorage.setItem('reg-filter', JSON.stringify(v)); } catch { /* private mode */ } } };
  let status = store.get().status ?? 'all';
  let q = '';
  let rows = [];
  let meta = null;
  let page = 1;
  let failed = null;
  let loadingMore = false;
  let req = 0;

  const SEG = [['all', 'All'], ['pending_review', 'Waiting'], ['needs_correction', 'Needs correction'], ['approved', 'Approved']];
  const count = (key) => (key === 'all' ? Object.values(meta?.counts ?? {}).reduce((a, b) => a + b, 0) : meta?.counts?.[key] ?? 0);

  const row = (r) => {
    const main = html`<span class="avatar" aria-hidden="true">${initials(r.applicant_name)}</span>
      <span class="grow"><span class="t">${r.applicant_name || 'Name not given'}</span>
        <span class="d block"><span class="mono">${r.reference}</span>${r.form ? ` · ${r.form.title}` : ''} · ${r.submission_count > 1 ? `sent ${r.submission_count} times, last ` : ''}${ago(r.last_submitted_at)}</span></span>
      ${chip(REG_STATUS, r.status)}`;
    return r.status === 'approved' && r.aytam_id
      ? html`<li><a class="row-link" href="#/programs/${tab.program.id}/children/${r.aytam_id}" aria-label="${r.applicant_name || r.reference}, approved. Open the child's record">${main}${icon('chevron', 'chev')}</a>
          <a class="btn sm plain reg-side" href="${tab.link('review/' + r.id)}">Submission</a></li>`
      : html`<li><a class="row-link" href="${tab.link('review/' + r.id)}">${main}${icon('chevron', 'chev')}</a></li>`;
  };
  const seg = () => html`<nav class="segmented reg-seg" aria-label="Filter by status">${SEG.map(([k, label]) => html`<button type="button" data-status="${k}" class="${status === k ? 'on' : ''}" aria-pressed="${String(status === k)}">${label} <span class="reg-n">${count(k)}</span></button>`)}</nav>`;
  const list = () => {
    if (failed) return html`<div class="empty">${icon('alert')}<b>Could not load submissions</b><span>${failed}</span><button class="btn secondary" type="button" data-retry>Try again</button></div>`;
    if (!rows.length) {
      const none = count('all') === 0;
      return html`<div class="empty">${icon('list')}<b>${none ? 'No registrations yet' : q ? 'No one matches that search' : 'Nothing here'}</b>
        <span>${none ? 'When someone sends in a registration form, it appears here for you to review.' : q ? 'Try a reference or a different spelling.' : 'No registrations have this status.'}</span>
        ${none && canForms ? html`<a class="btn secondary" href="${tab.link('forms')}">Open the forms</a>` : ''}</div>`;
    }
    return html`<ul class="list">${rows.map(row)}</ul>${meta && page < meta.last_page ? html`<div class="btn-row center"><button class="btn secondary" type="button" data-more ${flag(loadingMore, 'disabled')}>Show more (${meta.total - rows.length} left)</button></div>` : ''}`;
  };
  const drawParts = () => { const s = $('[data-seg]', tab.root); if (s) s.innerHTML = seg().toString(); const l = $('[data-list]', tab.root); if (l) l.innerHTML = list().toString(); };

  const fetchRows = async (append = false) => {
    const mine = ++req;
    const params = new URLSearchParams({ page: String(page), per_page: '25' });
    if (status !== 'all') params.set('status', status);
    if (q) params.set('q', q);
    try {
      const res = await api.get(`${base}/registrations?${params}`);
      if (mine !== req) return;
      rows = append ? rows.concat(res.data) : res.data;
      meta = res.meta;
      failed = null;
    } catch (e) { if (mine !== req) return; failed = api.explain(e); }
    loadingMore = false;
    drawParts();
  };
  const first = async () => {
    paint(tab.root, skeleton());
    await fetchRows();
    paint(tab.root, html`<div class="reg">
      ${toolbar(tab, 'review')}
      <div data-seg>${seg()}</div>
      <div class="card flush"><div class="filters">${searchInput({ id: 'reg-q', value: q, placeholder: 'Search by reference or name', label: 'Search registrations' })}</div><div data-list>${list()}</div></div></div>`);
  };

  delegate(tab.root, 'click', '[data-status]', (e, b) => { status = b.dataset.status; store.set({ status }); page = 1; drawParts(); fetchRows(); });
  delegate(tab.root, 'click', '[data-more]', () => { loadingMore = true; page += 1; drawParts(); fetchRows(true); });
  delegate(tab.root, 'click', '[data-retry]', () => { page = 1; fetchRows(); });
  const search = debounce(() => { page = 1; fetchRows(); }, 250);
  delegate(tab.root, 'input', '#reg-q', (e, i) => { q = i.value.trim(); search(); });

  await first();
}

// =====================================================================================================================
// SUBMISSIONS: review one
// =====================================================================================================================
const CHILD_FIELDS = [
  ['first_name', 'First name'], ['middle_name', 'Middle name'], ['last_name', 'Last name'], ['arabic_name', 'Name in Arabic'], ['date_of_birth', 'Date of birth', 'date'],
  ['gender', 'Gender', 'gender'], ['nationality', 'Nationality'], ['country', 'Country'], ['region', 'Region'], ['province', 'Province'], ['city', 'City / municipality'],
  ['barangay', 'Barangay'], ['address_detail', 'Street / details'], ['education_level', 'Education level'], ['school', 'School'], ['grade', 'Grade / year'], ['phone', 'Phone'], ['email', 'Email', 'email'],
];
const LABELS = { address_detail: 'Street / details', arabic_name: 'Name in Arabic', date_of_birth: 'Date of birth', full_name: 'Full name', father_status: "Father's status", mother_status: "Mother's status", father_name: "Father's name", mother_name: "Mother's name", city: 'City / municipality' };
const labelOf = (k) => LABELS[k] ?? sentence(k);

async function mountReview(tab, regId) {
  const base = `/programs/${tab.program.id}`;
  const canDocs = tab.pcan('documents.view');
  let reg = null;
  let overrides = {};     // reviewer corrections, applied when approving ("aytam.first_name": "...")

  const answer = (f) => {
    if (f.document) {
      const d = f.document;
      const image = (d.mime ?? '').startsWith('image/');
      return html`<div class="reg-file">${image && canDocs ? html`<a href="${docUrl(tab, d.id, true)}" target="_blank" rel="noopener"><img class="reg-thumb" src="${docUrl(tab, d.id, true)}" alt="${f.label}: ${d.original_name}"></a>` : ''}
        <div class="reg-file-meta">${icon('folder')}<span class="grow"><span class="t">${d.original_name}</span> <span class="muted">${bytes(d.size)}</span></span>
        ${canDocs ? html`<a class="btn sm secondary" href="${docUrl(tab, d.id)}">Download</a>` : ''}</div></div>`;
    }
    return f.answer ? html`<span class="reg-ans">${f.answer}</span>` : html`<span class="muted">Not answered</span>`;
  };

  const canonicalBlock = (title, obj, entity) => {
    const entries = Object.entries(obj ?? {}).filter(([, v]) => v !== null && v !== '' && v !== undefined);
    return entries.length ? html`<div class="reg-canon"><h4>${title}</h4><dl class="kv">${entries.map(([k, v]) => html`<dt>${labelOf(k)}</dt><dd>${k === 'date_of_birth' ? fmtDate(v) : v}${entity === 'aytam' && `aytam.${k}` in overrides ? html` <span class="chip blue">Corrected by you</span>` : ''}</dd>`)}</dl></div>` : '';
  };
  const effective = () => {
    const c = JSON.parse(JSON.stringify(reg.canonical));
    for (const [k, val] of Object.entries(overrides)) { const [ent, fld] = k.split('.'); if (c[ent]) c[ent][fld] = val; }
    return c;
  };

  const header = () => {
    const [tone, label] = REG_STATUS[reg.status] ?? ['grey', reg.status];
    const approvedEvent = [...reg.history].reverse().find((h) => h.type === 'approved');
    const code = approvedEvent?.data?.aytam_code;
    return html`<div class="card reg-review-head">
      <div class="reg-review-title"><span class="avatar lg-av" aria-hidden="true">${initials(reg.applicant?.name)}</span>
        <div class="grow"><h2 class="reg-h">${reg.canonical?.aytam?.first_name ? [reg.canonical.aytam.first_name, reg.canonical.aytam.middle_name, reg.canonical.aytam.last_name].filter(Boolean).join(' ') : reg.applicant_name || 'Name not given'}</h2>
          <div class="prop"><span class="chip grey mono">${reg.reference}</span><span class="chip ${tone}">${label}</span>
            <span>Submitted ${fmtDateTime(reg.submitted_at)}</span>${reg.submission_count > 1 ? html`<span>Sent ${reg.submission_count} times · last ${ago(reg.last_submitted_at)}</span>` : ''}${reg.form ? html`<span>${reg.form.title}</span>` : ''}</div></div></div>
      <dl class="kv reg-contact"><dt>Applicant</dt><dd>${reg.applicant?.name || html`<span class="muted">Not given</span>`}</dd>
        <dt>Phone</dt><dd>${reg.applicant?.phone ? html`<a href="tel:${reg.applicant.phone}">${reg.applicant.phone}</a>` : html`<span class="muted">Not given</span>`}</dd>
        <dt>Email</dt><dd>${reg.applicant?.email ? html`<a href="mailto:${reg.applicant.email}">${reg.applicant.email}</a>` : html`<span class="muted">Not given</span>`}</dd></dl>
      ${reg.status === 'pending_review' ? actions() : ''}
      ${reg.status === 'needs_correction' ? html`<div class="banner info">${icon('info')}<div class="grow"><b>Sent back for correction${reg.reviewed_at ? ` on ${fmtDate(reg.reviewed_at)}` : ''}.</b> Waiting for the applicant to fix it and send it again.${reg.review_note ? html`<div class="reg-note">${reg.review_note}</div>` : ''}</div></div>` : ''}
      ${reg.status === 'approved' ? html`<div class="banner">${icon('check')}<div class="grow"><b>Approved${reg.reviewed_at ? ` on ${fmtDate(reg.reviewed_at)}` : ''}${code ? html` as <span class="mono">${code}</span>` : ''}.</b>
          ${reg.duplicate_decision === 'use_existing' ? ' It was linked to an existing record.' : ''}</div>
          ${reg.aytam_id ? html`<a class="btn sm" href="#/programs/${tab.program.id}/children/${reg.aytam_id}">Open the child's record</a>` : ''}</div>` : ''}
    </div>`;
  };

  const actions = () => {
    const hasProblems = Object.keys(reg.problems ?? {}).length > 0 && !Object.keys(overrides).length;
    return html`<div class="reg-actions">
      <div class="btn-row"><button class="btn" type="button" data-approve ${flag(hasProblems, 'disabled')}>${Object.keys(overrides).length ? 'Approve with corrections' : 'Approve'}</button>
        <button class="btn secondary" type="button" data-correct>${icon('edit')} Correct details first</button>
        <button class="btn danger" type="button" data-sendback>Send back for correction</button></div>
      ${hasProblems ? html`<p class="hint">Fix the problems below with "Correct details first", or send the registration back to the applicant.</p>` : ''}
      ${Object.keys(overrides).length ? html`<p class="hint">Your corrections are applied when you approve. The applicant's own answers stay exactly as submitted. <button class="link" type="button" data-undo>Undo corrections</button></p>` : ''}
    </div>`;
  };

  const attention = () => {
    const probs = Object.entries(reg.problems ?? {});
    const out = [];
    if (probs.length) {
      out.push(html`<div class="banner ${Object.keys(overrides).length ? 'info' : 'bad'}" role="alert">${icon('alert')}<div class="grow">
        <b>${Object.keys(overrides).length ? 'These problems will be re-checked with your corrections when you approve:' : 'These must be fixed before this registration can be approved:'}</b>
        <ul class="reg-problems">${probs.map(([k, msgs]) => html`<li><b>${labelOf(k)}:</b> ${[].concat(msgs).join(' ')}</li>`)}</ul></div></div>`);
    }
    if (reg.status === 'pending_review' && (reg.missing_documents ?? []).length) {
      out.push(html`<div class="banner warn">${icon('info')}<div class="grow"><b>Documents the program asks for that were not provided:</b> ${reg.missing_documents.map(sentence).join(', ')}. You can still approve and collect them later.</div></div>`);
    }
    return out;
  };

  const duplicates = () => (reg.duplicates ?? []).length ? html`<section class="card reg-dups" aria-label="Possible duplicates">
      <div class="card-head"><div><h3>Possible duplicates</h3><p>These children are already on file. Nothing is merged automatically: you decide when you approve.</p></div></div>
      <ul class="list">${reg.duplicates.map((d) => html`<li class="reg-dup"><div class="grow"><span class="t">${d.name}</span> <span class="chip grey mono">${d.aytam_code}</span>
          <div class="d">${d.date_of_birth ? `Born ${fmtDate(d.date_of_birth)}` : 'Date of birth not recorded'}</div>
          <ul class="reg-reasons">${(d.reasons ?? []).map((r) => html`<li>${r}</li>`)}</ul></div>
          ${chip(LEVEL, d.level)}<a class="btn sm secondary" href="#/programs/${tab.program.id}/children/${d.aytam_id}" target="_blank" rel="noopener">Open record</a></li>`)}</ul></section>` : '';

  const history = () => html`<section class="card" aria-label="History"><div class="card-head"><h3>History</h3></div>
      <ul class="list timeline">${[...reg.history].reverse().map((h) => {
        const text = { submitted: 'Applicant submitted the form', resubmitted: 'Applicant corrected it and sent it again', returned: 'Sent back for correction', approved: 'Approved' }[h.type] ?? sentence(h.type);
        return html`<li><span class="dot ${h.type === 'approved' ? 'green' : h.type === 'returned' ? '' : 'grey'}"></span><div class="grow"><div class="t">${text}${h.data?.aytam_code ? html` · <span class="mono">${h.data.aytam_code}</span>` : ''}</div>
          ${h.note ? html`<div class="reg-note">${h.note}</div>` : ''}<div class="d">${h.actor_name ? `${h.actor_name} · ` : ''}${fmtDateTime(h.at)}</div></div></li>`;
      })}</ul></section>`;

  const draw = () => {
    const c = effective();
    paint(tab.root, html`<div class="reg">
      ${backLink(tab.link('review'), 'Submissions')}
      ${header()}
      ${attention()}
      ${duplicates()}
      <div class="two-col">
        <div class="stack">${reg.sections.map((s) => html`<section class="card" aria-label="${s.title}"><div class="card-head"><div><h3>${s.title}</h3>${s.description ? html`<p>${s.description}</p>` : ''}</div></div>
          <dl class="kv">${s.fields.map((f) => html`<dt>${f.label}</dt><dd>${answer(f)}</dd>`)}</dl></section>`)}</div>
        <div class="stack">
          <section class="card" aria-label="What will be created"><div class="card-head"><div><h3>${reg.status === 'approved' ? 'What was created' : 'What will be created'}</h3><p>Taken from the answers, as the record will hold them.</p></div></div>
            ${canonicalBlock('Child', c.aytam, 'aytam') || html`<p class="muted">No child details were mapped from this form.</p>`}
            ${canonicalBlock('Family', c.family) || ''}${canonicalBlock('Guardian', c.guardian) || ''}</section>
          ${history()}</div></div>
    </div>`);
  };

  const start = async () => {
    paint(tab.root, skeleton());
    try {
      reg = (await api.get(`${base}/registrations/${regId}`)).data;
      tab.setTitle(`Registration ${reg.reference}`);
      draw();
    } catch (e) {
      paint(tab.root, e.status === 404
        ? html`<div class="reg">${backLink(tab.link('review'), 'Submissions')}<div class="card"><div class="empty">${icon('alert')}<b>Registration not found</b><span>It may belong to another program.</span></div></div></div>`
        : failure(api.explain(e), 'The registration could not be opened'));
    }
  };

  // ---- actions ---------------------------------------------------------------------------------------------------
  const approve = async (btn, extra = {}) => {
    busy(btn, true, 'Approving…');
    try {
      const body = { ...extra };
      if (Object.keys(overrides).length) body.overrides = overrides;
      const { data } = await api.post(`${base}/registrations/${reg.id}/approve`, body);
      overrides = {};
      await approvedSheet(data, extra.duplicate_decision === 'use_existing');
      await start();
    } catch (e) {
      busy(btn, false);
      if (e.status === 409 && e.code === 'DUPLICATES_FOUND') { decisionSheet(e.errors?.matches ?? [], (extraNow) => approve(null, extraNow)); return; }
      toast(sayError(e), 'bad');
      if (e.status === 422) { await start(); }
    }
  };
  const approvedSheet = (data, linked) => new Promise((resolve) => {
    sheet({
      title: linked ? 'Linked to the existing record' : 'Registration approved',
      body: html`<div class="stack reg-done">
        <p>${linked ? 'This registration now belongs to the existing record:' : 'The child now has a permanent ID:'}</p>
        <div class="reg-code" aria-label="Permanent ID">${data.aytam.aytam_code}</div>
        <p class="muted">${data.aytam.name}</p>
        <div class="btn-row"><a class="btn" href="#/programs/${tab.program.id}/children/${data.aytam.id}" data-close>Open the child's record</a><button class="btn secondary" type="button" data-close>Stay here</button></div></div>`,
      onClose: () => resolve(),
    });
  });

  delegate(tab.root, 'click', '[data-retry]', start);
  delegate(tab.root, 'click', '[data-approve]', (e, b) => approve(b));
  delegate(tab.root, 'click', '[data-undo]', () => { overrides = {}; draw(); });
  delegate(tab.root, 'click', '[data-correct]', () => correctSheet(effective().aytam, reg.canonical.aytam, (o) => { overrides = o; draw(); }));
  delegate(tab.root, 'click', '[data-sendback]', () => sheet({
    title: 'Send back for correction',
    body: html`<form class="form" novalidate>
      <p class="muted">The applicant sees this note and can fix their answers using their private status link. Say clearly what to correct.</p>
      <div class="field"><label for="f-note">What needs to be corrected?</label><textarea class="textarea" id="f-note" name="note" maxlength="500" required autofocus></textarea><span class="hint"><span data-count>0</span> / 500</span><span class="err" data-err="note" hidden></span></div>
      <div class="btn-row"><button class="btn danger" type="submit">Send back</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => {
      const form = $('form', el);
      $('textarea', form).addEventListener('input', (e) => { $('[data-count]', form).textContent = e.target.value.length; });
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const note = $('textarea', form).value.trim();
        if (!note) { showErrors(form, { note: 'Say what the applicant needs to correct.' }); return; }
        const btn = $('button[type=submit]', form);
        busy(btn, true, 'Sending…');
        try {
          await api.post(`${base}/registrations/${reg.id}/send-back`, { note });
          close();
          toast('Sent back. The applicant can now correct it.');
          overrides = {};
          await start();
        } catch (err) {
          if (!showErrors(form, errorsOf(err))) toast(sayError(err), 'bad');
          busy(btn, false);
        }
      });
    },
  }));

  await start();
}

/** Choose between "different child" and "same child as ..." when approval finds possible duplicates. */
function decisionSheet(matches, send) {
  sheet({
    title: 'This child may already be on file',
    body: html`<form class="form" novalidate>
      <p class="muted">Check the records below before you decide. Nothing is merged automatically.</p>
      <fieldset class="reg-decide"><legend class="sr-only">Decision</legend>
        <label class="reg-choice"><input type="radio" name="choice" value="new"><span><b>This is a different child</b><span class="d block">Create a new record.</span></span></label>
        ${matches.map((m) => html`<label class="reg-choice"><input type="radio" name="choice" value="${m.aytam_id}"><span><b>This is the same child as ${m.aytam_code} · ${m.name}</b>
          <span class="d block">${m.date_of_birth ? `Born ${fmtDate(m.date_of_birth)} · ` : ''}${LEVEL[m.level]?.[1] ?? ''}${(m.reasons ?? []).length ? ` · ${m.reasons.join(', ')}` : ''}</span>
          <span class="d block">Use the existing record instead of creating a new one.</span></span></label>`)}
      </fieldset>
      <span class="err" data-err="choice" hidden></span>
      <div class="btn-row"><button class="btn" type="submit">Approve</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('form', el).addEventListener('submit', (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const choice = new FormData(form).get('choice');
      if (!choice) { showErrors(form, { choice: 'Choose one of the options.' }); return; }
      close();
      send(choice === 'new' ? { duplicate_decision: 'create_new' } : { duplicate_decision: 'use_existing', existing_aytam_id: choice });
    }),
  });
}

/** Reviewer corrections: only the fields that differ from what the answers produced are kept as overrides. */
function correctSheet(current, original, onSave) {
  sheet({
    title: 'Correct details before approving',
    body: html`<form class="form" novalidate>
      <div class="banner info">${icon('info')}<div class="grow">Your changes go into the new record when you approve. The applicant's original answers stay exactly as they were submitted.</div></div>
      <div class="row-2">${CHILD_FIELDS.map(([key, label, kind]) => (kind === 'gender'
        ? html`<div class="field"><label for="c-${key}">${label}</label><select class="select" id="c-${key}" name="aytam.${key}"><option value="">Not given</option>${['male', 'female'].map((g) => html`<option value="${g}" ${flag(current[key] === g, 'selected')}>${sentence(g)}</option>`)}</select></div>`
        : html`<div class="field"><label for="c-${key}">${label}</label><input class="input" id="c-${key}" name="aytam.${key}" type="${kind ?? 'text'}" value="${current[key] ?? ''}" autocomplete="off"></div>`))}</div>
      <div class="btn-row"><button class="btn" type="submit">Use these details</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('form', el).addEventListener('submit', (e) => {
      e.preventDefault();
      const out = {};
      for (const [name, val] of new FormData(e.currentTarget)) {
        const key = name.replace('aytam.', '');
        const now = String(val).trim();
        if (now !== String(original[key] ?? '')) out[name] = now;
      }
      close();
      onSave(out);
      if (Object.keys(out).length) toast('Corrections noted. They are applied when you approve.');
    }),
  });
}
