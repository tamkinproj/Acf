import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { busy, confirmDialog, toast } from '../../core/ui.js';
import { $, ago, fmtDateTime, html, plural, raw } from '../../core/util.js';

// Import of existing data from a CSV or Excel file.
//   #/programs/<id>/import            -> earlier imports + start a new one
//   #/programs/<id>/import/<batchId> -> the steps, driven by the import's status so a person can leave and come back:
//        uploaded -> Match columns | mapped -> Check | validated -> Decide, then Import | imported / cancelled -> result
// Every step is its own request; nothing is created until the last one, and people decide about anything uncertain.

export default {
  async mount(tab) {
    if (!tab.pcan('aytam.import')) {
      tab.root.innerHTML = html`<div class="card"><div class="empty">${icon('lock')}<b>Nothing to show here</b><span>You do not have permission to import records.</span></div></div>`.toString();
      return;
    }
    const [id] = tab.params;
    return id ? mountBatch(tab, id) : mountHistory(tab);
  },
};

// ---- helpers ---------------------------------------------------------------------------------------------------------
const flag = (on, word) => (on ? raw(word) : '');
const delegate = (root, event, selector, fn) => root.addEventListener(event, (e) => { const t = e.target.closest(selector); if (t && root.contains(t)) fn(e, t); });
const paint = (root, h) => { const y = window.scrollY; root.innerHTML = h.toString(); window.scrollTo(0, y); };
const sayError = (e) => (e?.errors ? Object.values(e.errors).flat().filter((x) => typeof x === 'string')[0] : null) ?? api.explain(e);
const sentence = (s) => { const t = String(s ?? '').replace(/_/g, ' ').trim(); return t.charAt(0).toUpperCase() + t.slice(1); };
const skeleton = () => html`<div class="reg" aria-busy="true"><div class="card skeleton-card"><div class="skeleton title"></div><div class="skeleton line"></div><div class="skeleton line short"></div></div>
  <div class="card flush"><div class="skeleton row"></div><div class="skeleton row"></div></div></div>`;
const failure = (message, what) => html`<div class="reg"><div class="card"><div class="empty">${icon('alert')}<b>${what}</b><span>${message}</span><button class="btn secondary" type="button" data-retry>Try again</button></div></div></div>`;

const BATCH_STATUS = {
  uploaded: ['blue', 'Match the columns'], mapped: ['blue', 'Ready to check'], validated: ['orange', 'Checked, ready to import'], imported: ['green', 'Imported'], cancelled: ['grey', 'Cancelled'],
};
const batchChip = (s) => { const [tone, label] = BATCH_STATUS[s] ?? ['grey', sentence(s)]; return html`<span class="chip ${tone}">${label}</span>`; };
const LEVEL = { exact: ['red', 'Exact match'], probable: ['orange', 'Probable match'], possible: ['grey', 'Possible match'] };
const levelChip = (l) => { const [tone, label] = LEVEL[l] ?? ['grey', sentence(l)]; return html`<span class="chip ${tone}">${label}</span>`; };
const STEPS = ['Upload', 'Match columns', 'Check', 'Decide', 'Import'];

const stepper = (current, allDone = false) => html`<ol class="imp-steps" aria-label="Import steps">${STEPS.map((label, i) => {
  const state = allDone || i < current ? 'done' : i === current ? 'current' : 'todo';
  return html`<li class="${state}" ${flag(state === 'current', 'aria-current="step"')}><span class="imp-dot" aria-hidden="true">${state === 'done' ? icon('check') : i + 1}</span><span class="imp-step-label">${label}${state === 'done' ? html`<span class="sr-only"> (done)</span>` : ''}</span></li>`;
})}</ol>`;

// =====================================================================================================================
// history + new import
// =====================================================================================================================
async function mountHistory(tab) {
  const base = `/programs/${tab.program.id}`;
  let batches = [];
  let failed = null;
  let uploadError = null;
  let uploading = false;

  const row = (b) => html`<li><a class="row-link" href="${tab.link(b.id)}">
      <span class="avatar sq" aria-hidden="true">${icon('upload')}</span>
      <span class="grow"><span class="t">${b.file_name}</span>
        <span class="d block">${plural(b.row_count, 'row')} · ${b.status === 'imported' && b.imported_at ? `finished ${ago(b.imported_at)}` : `started ${ago(b.created_at)}`}${b.status === 'imported' && b.summary ? ` · ${b.summary.imported ?? 0} imported` : ''}</span></span>
      ${batchChip(b.status)}${icon('chevron', 'chev')}</a></li>`;

  const draw = () => paint(tab.root, html`<div class="reg imp">
    <div class="reg-toolbar"><h2 class="section-title reg-title">Import existing records</h2></div>
    <section class="card" aria-label="New import">
      <div class="card-head"><div><h3>New import</h3><p>Bring in records you already keep in a spreadsheet. You will match the columns, see any problems, and decide about possible duplicates before anything is created.</p></div></div>
      <label class="imp-drop" data-drop for="imp-file">${icon('upload')}<b>${uploading ? 'Uploading…' : 'Drop a file here, or choose one'}</b>
        <span class="muted">CSV or Excel (.xlsx), first row = column titles, up to 5,000 rows.</span>
        <input class="sr-only" id="imp-file" type="file" accept=".csv,.xlsx" ${flag(uploading, 'disabled')}></label>
      ${uploadError ? html`<div class="banner bad" role="alert">${icon('alert')}<div class="grow">${uploadError}</div></div>` : ''}
      <p class="hint">The uploaded file and the personal data copied from it are deleted when the import finishes or is cancelled.</p>
    </section>
    <section class="card flush" aria-label="Earlier imports"><div class="card-head"><h3>Earlier imports</h3></div>
      ${failed ? html`<div class="empty">${icon('alert')}<b>Could not load the imports</b><span>${failed}</span><button class="btn secondary" type="button" data-retry>Try again</button></div>`
        : batches.length ? html`<ul class="list">${batches.map(row)}</ul>` : html`<div class="empty">${icon('upload')}<b>No imports yet</b><span>Imports you start will be listed here, so you can come back to one later.</span></div>`}</section></div>`);

  const load = async () => {
    try { batches = (await api.get(`${base}/imports`)).data; failed = null; } catch (e) { failed = api.explain(e); }
    draw();
  };
  const upload = async (file) => {
    if (!file || uploading) return;
    uploading = true; uploadError = null; draw();
    const body = new FormData();
    body.append('file', file);
    try {
      const { data } = await api.post(`${base}/imports`, body, { timeout: 120000 });
      toast('File uploaded. Next, match its columns.');
      tab.go(data.id);
    } catch (e) {
      uploading = false;
      uploadError = sayError(e);
      draw();
    }
  };

  delegate(tab.root, 'click', '[data-retry]', load);
  tab.root.addEventListener('change', (e) => { if (e.target.id === 'imp-file') upload(e.target.files[0]); });
  tab.root.addEventListener('dragover', (e) => { if (e.target.closest('[data-drop]')) { e.preventDefault(); e.target.closest('[data-drop]').classList.add('over'); } });
  tab.root.addEventListener('dragleave', (e) => { e.target.closest?.('[data-drop]')?.classList.remove('over'); });
  tab.root.addEventListener('drop', (e) => { if (e.target.closest('[data-drop]')) { e.preventDefault(); upload(e.dataTransfer.files[0]); } });

  paint(tab.root, skeleton());
  await load();
}

// =====================================================================================================================
// one import
// =====================================================================================================================
async function mountBatch(tab, batchId) {
  const base = `/programs/${tab.program.id}/imports/${batchId}`;
  const root = tab.root;
  let b = null;                    // the batch (headers, mapping, status, summary ...)
  let ref = null;                  // targets, date formats, sample rows: only sent while the import is open
  let editing = false;             // going back to the column matching after it was done
  let mapErrors = { cols: {}, top: null };
  let invalid = { rows: [], page: 1, last: 1, total: 0, loading: false, error: null };
  let dups = { rows: [], page: 1, last: 1, total: 0, undecided: 0, loading: false, error: null };
  let run = { state: 'idle', totals: { created: 0, linked: 0, skipped: 0 }, done: 0, total: 0, error: null, stop: false };
  let status = 'active';
  let working = null;              // label while a step is running
  let draftMapping = null;         // {colIndex: target} while the person is choosing
  let draftDate = '';

  const targetLabel = (key) => ref?.targets.find((t) => t.key === key)?.label ?? sentence(String(key).split('.').pop());
  const headerName = (i) => (b.headers[i] && String(b.headers[i]).trim()) || `Column ${i + 1}`;
  const open = () => b && !['imported', 'cancelled'].includes(b.status);
  const merge = (next) => { b = { ...b, ...next }; };

  // ---- views -------------------------------------------------------------------------------------------------------
  const head = (title) => html`<a class="reg-back" href="${tab.link()}">${icon('chevron', 'flip')}All imports</a>
    <div class="page-head reg-head"><div><h2 class="reg-h">${title}</h2><div class="prop">${batchChip(b.status)}<span>${plural(b.row_count, 'row')}</span><span>Uploaded ${ago(b.created_at)}</span></div></div>
      ${open() ? html`<div class="btn-row"><button class="btn danger sm" type="button" data-cancel>Cancel this import</button></div>` : ''}</div>`;

  const mappingStep = () => {
    const mapping = draftMapping;
    const used = new Set(Object.values(mapping));
    const groups = {};
    for (const t of ref.targets) (groups[t.group] ||= []).push(t);
    const dobMapped = Object.values(mapping).includes('aytam.date_of_birth');
    const samples = (i) => ref.sample.map((r) => r[i]).filter((v) => v !== undefined && String(v).trim() !== '').slice(0, 3);
    return html`<section class="card" aria-label="Match columns">
      <div class="card-head"><div><h3>1. Match the columns</h3><p>Tell us what each column of your file holds. Columns you leave as "Do not import" are ignored. Every child needs at least a first name and a last name.</p></div></div>
      ${mapErrors.top ? html`<div class="banner bad" role="alert">${icon('alert')}<div class="grow"><b>${mapErrors.top}</b>${/first|last|name/i.test(mapErrors.top) ? html`<div>A child needs a first name and a last name, so two of your columns must be matched to "First name" and "Last name".</div>` : ''}</div></div>` : ''}
      <form class="form" data-map-form novalidate>
        <ul class="list imp-cols">${b.headers.map((h, i) => html`<li>
          <div class="grow"><span class="t">${headerName(i)}</span>
            <span class="d block imp-sample">${samples(i).length ? samples(i).map((v) => html`<span class="imp-val">${v}</span>`) : html`<span class="muted">No values in the first rows</span>`}</span></div>
          <div class="field imp-pick"><label class="sr-only" for="col-${i}">Matches for ${headerName(i)}</label>
            <select class="select" id="col-${i}" data-col="${i}">
              <option value="">Do not import</option>
              ${Object.entries(groups).map(([g, ts]) => html`<optgroup label="${g}">${ts.map((t) => html`<option value="${t.key}" ${flag(mapping[i] === t.key, 'selected')} ${flag(used.has(t.key) && mapping[i] !== t.key, 'disabled')}>${t.label}${used.has(t.key) && mapping[i] !== t.key ? ' (used)' : ''}</option>`)}</optgroup>`)}
            </select>${mapErrors.cols[i] ? html`<span class="err">${mapErrors.cols[i]}</span>` : ''}</div></li>`)}</ul>
        <div class="field imp-date"><label for="date-format">How are dates written in your file?${dobMapped ? raw(' <span aria-hidden="true">*</span>') : ''}</label>
          <select class="select" id="date-format" data-date-format><option value="">Choose…</option>${Object.entries(ref.date_formats).map(([k, v]) => html`<option value="${k}" ${flag(draftDate === k, 'selected')}>${v}</option>`)}</select>
          <span class="hint">Dates are never guessed: 04/05/2012 is 4 May in one country and 5 April in another, so you tell us. A date like 2012-04-17 is always understood.</span>
          ${mapErrors.date ? html`<span class="err">${mapErrors.date}</span>` : ''}</div>
        <div class="btn-row"><button class="btn" type="submit" data-save-map>Save and check the file</button>
          ${b.status !== 'uploaded' ? html`<button class="btn secondary" type="button" data-stop-edit>Keep the current matching</button>` : ''}</div></form></section>`;
  };

  const loadMore = (label, state, n) => (state.page < state.last ? html`<div class="btn-row center"><button class="btn secondary" type="button" data-more="${n}" ${flag(state.loading, 'disabled')}>Show more (${state.total - state.rows.length} left)</button></div>` : '');

  const cells = (r) => {
    const items = b.headers.map((h, i) => [headerName(i), r.cells?.[i]]).filter(([, v]) => v !== undefined && v !== null && String(v).trim() !== '');
    return html`<dl class="imp-cells">${items.map(([h, v]) => html`<div><dt>${h}</dt><dd>${v}</dd></div>`)}</dl>`;
  };

  const summaryCards = () => {
    const s = b.summary ?? {};
    return html`<div class="overview" aria-label="Result of the check">
      <div class="metric"><span class="k">Ready</span><span class="v">${s.valid ?? 0}</span><span class="s">no problems found</span></div>
      <div class="metric"><span class="k">Has errors</span><span class="v">${s.invalid ?? 0}</span><span class="s">will not be imported</span></div>
      <div class="metric"><span class="k">Possible duplicates</span><span class="v">${s.duplicate ?? 0}</span><span class="s">need your decision</span></div>
      <div class="metric"><span class="k">Total rows</span><span class="v">${b.row_count}</span><span class="s">in the file</span></div></div>`;
  };

  const checkStep = () => {
    const s = b.summary ?? {};
    return html`<section class="card" aria-label="Check">
      <div class="card-head"><div><h3>2. Check the file</h3><p>Every row is checked against the rules for a child's record, and compared with the children already on file. Nothing is created yet.</p></div></div>
      ${b.status === 'mapped' ? html`<div class="btn-row"><button class="btn" type="button" data-validate>Check the file</button><button class="btn secondary" type="button" data-edit-map>Change the column matching</button></div>`
        : html`${summaryCards()}
          <div class="btn-row"><button class="btn secondary sm" type="button" data-edit-map>Change the column matching</button><button class="btn secondary sm" type="button" data-validate>Check again</button></div>
          ${(s.invalid ?? 0) > 0 ? invalidList() : ''}`}</section>`;
  };

  const invalidList = () => html`<div class="imp-sub"><h4>Rows with errors</h4>
      <div class="banner warn">${icon('alert')}<div class="grow">These rows have problems and <b>will not be imported</b>. The best fix is to correct them in your file and upload it again. Or carry on below and import only the rows that are fine.</div>
        <button class="btn sm secondary" type="button" data-startover>Fix the file and upload again</button></div>
      ${invalid.error ? html`<div class="banner bad">${icon('alert')}<div class="grow">${invalid.error}</div><button class="btn sm" type="button" data-retry-invalid>Try again</button></div>` : ''}
      <ul class="list imp-rows">${invalid.rows.map((r) => html`<li class="imp-row"><div class="grow"><div class="t">Row ${r.row_number}</div>
          <ul class="imp-msgs">${Object.entries(r.errors).map(([k, msgs]) => html`<li><b>${k === 'row' ? 'This row' : targetLabel(k)}:</b> ${[].concat(msgs).join(' ')}</li>`)}</ul>${cells(r)}</div></li>`)}</ul>
      ${loadMore('errors', invalid, 'invalid')}</div>`;

  const decideStep = () => {
    const s = b.summary ?? {};
    if (!(s.duplicate > 0)) return '';
    return html`<section class="card" aria-label="Decide about possible duplicates">
      <div class="card-head"><div><h3>3. Decide about possible duplicates</h3><p>These rows look like children who are already on file, or appear twice in the file. Nothing is merged automatically: you decide for each one.</p></div></div>
      <div class="banner ${dups.undecided ? 'warn' : 'info'}">${icon(dups.undecided ? 'alert' : 'check')}<div class="grow">${dups.undecided
        ? html`<b>${plural(dups.undecided, 'row')} still undecided.</b> Undecided rows are held back and will <b>not</b> be imported.`
        : 'Every possible duplicate has a decision.'}</div></div>
      ${dups.undecided ? html`<div class="btn-row"><button class="btn secondary sm" type="button" data-bulk="skip">Skip all undecided</button><button class="btn secondary sm" type="button" data-bulk="create_new">Create new for all undecided</button></div>` : ''}
      ${dups.error ? html`<div class="banner bad">${icon('alert')}<div class="grow">${dups.error}</div><button class="btn sm" type="button" data-retry-dups>Try again</button></div>` : ''}
      <ul class="list imp-rows">${dups.rows.map(dupRow)}</ul>
      ${loadMore('duplicates', dups, 'dups')}</section>`;
  };

  const decisionText = (r) => {
    if (r.decision === 'create_new') return ['green', 'Will create a new record'];
    if (r.decision === 'skip') return ['grey', 'Will be skipped'];
    if (r.decision === 'use_existing') return ['blue', 'Will use an existing record'];
    return ['orange', 'Undecided: held back'];
  };
  const dupRow = (r) => {
    const [tone, label] = decisionText(r);
    const existing = (r.matches ?? []).filter((m) => m.type === 'existing');
    return html`<li class="imp-row" data-row="${r.id}"><div class="grow">
        <div class="imp-row-head"><span class="t">Row ${r.row_number}</span><span class="chip ${tone}">${label}</span></div>
        ${cells(r)}
        <ul class="imp-matches">${(r.matches ?? []).map((m) => m.type === 'existing'
          ? html`<li class="${r.existing_aytam_id === m.aytam_id ? 'chosen' : ''}"><div><b>${m.name}</b> <span class="chip grey mono">${m.aytam_code}</span> ${levelChip(m.level)}</div>
              <div class="d">${m.date_of_birth ? `Born ${m.date_of_birth}` : 'Date of birth not recorded'}${(m.reasons ?? []).length ? ` · ${m.reasons.join(', ')}` : ''}</div></li>`
          : html`<li><div><b>${m.name}</b> ${levelChip(m.level)}</div><div class="d">Also on row ${m.row_number} of this file</div></li>`)}</ul>
        <div class="btn-row imp-decide" role="group" aria-label="Decision for row ${r.row_number}">
          <button class="btn sm ${r.decision === 'create_new' ? '' : 'secondary'}" type="button" data-decide="create_new" aria-pressed="${String(r.decision === 'create_new')}">Create new</button>
          ${existing.map((m) => html`<button class="btn sm ${r.decision === 'use_existing' && r.existing_aytam_id === m.aytam_id ? '' : 'secondary'}" type="button" data-decide="use_existing" data-existing="${m.aytam_id}" aria-pressed="${String(r.decision === 'use_existing' && r.existing_aytam_id === m.aytam_id)}">Use ${m.aytam_code}</button>`)}
          <button class="btn sm ${r.decision === 'skip' ? '' : 'secondary'}" type="button" data-decide="skip" aria-pressed="${String(r.decision === 'skip')}">Skip</button>
          ${r.decision ? html`<button class="btn sm plain" type="button" data-decide="later">Decide later</button>` : ''}</div></div></li>`;
  };

  const importStep = () => {
    const s = b.summary ?? {};
    const hasDup = (s.duplicate ?? 0) > 0;
    const held = hasDup ? dups.undecided : 0;
    const ready = (s.valid ?? 0) + (s.duplicate ?? 0) - held;
    const running = run.state === 'running';
    return html`<section class="card" aria-label="Import">
      <div class="card-head"><div><h3>${hasDup ? '4. Import' : '3. Import'}</h3><p>Create the records. Large files are imported in small batches, and you can stop and continue.</p></div></div>
      <ul class="imp-plan"><li>${icon('check')}<span><b>${ready}</b> ${ready === 1 ? 'row' : 'rows'} will be processed</span></li>
        ${held ? html`<li class="warn">${icon('alert')}<span><b>${held}</b> undecided ${held === 1 ? 'row is' : 'rows are'} held back and will not be imported</span></li>` : ''}
        ${(s.invalid ?? 0) ? html`<li class="warn">${icon('alert')}<span><b>${s.invalid}</b> ${s.invalid === 1 ? 'row has' : 'rows have'} errors and will not be imported</span></li>` : ''}</ul>
      ${run.state === 'idle' || run.state === 'paused' || run.state === 'error' ? html`
        <div class="field imp-status"><label for="start-status">New records start as</label>
          <select class="select" id="start-status" data-start-status>${[['draft', 'Draft - still being completed'], ['approved', 'Approved - checked, not yet active'], ['active', 'Active - in the program']].map(([v, t]) => html`<option value="${v}" ${flag(status === v, 'selected')}>${t}</option>`)}</select>
          <span class="hint">Children linked to an existing record are not changed.</span></div>` : ''}
      ${run.state !== 'idle' ? html`<div class="imp-progress" role="status"><progress max="${run.total || 1}" value="${run.done}" aria-label="Import progress"></progress>
        <span>${run.done} of ${run.total} ${run.state === 'paused' ? '(stopped)' : ''}</span></div>` : ''}
      ${run.error ? html`<div class="banner bad" role="alert">${icon('alert')}<div class="grow">${run.error}</div></div>` : ''}
      <div class="btn-row">
        ${running ? html`<button class="btn secondary" type="button" data-stop>Stop</button>`
          : html`<button class="btn" type="button" data-import ${flag(ready < 1 && run.state === 'idle', 'disabled')}>${run.state === 'idle' ? 'Import now' : 'Continue importing'}</button>`}
      </div>
      ${ready < 1 && run.state === 'idle' ? html`<p class="hint">There is nothing to import yet. ${hasDup && held ? 'Decide about the possible duplicates above, or ' : ''}Cancel this import and upload a corrected file.</p>` : ''}
      <p class="hint imp-lock">${icon('lock')} The uploaded file and the personal data copied from it are deleted when the import finishes or is cancelled.</p></section>`;
  };

  const resultCard = () => {
    const s = b.summary ?? {};
    const created = Math.max(0, (s.imported ?? 0) - (s.linked_to_existing ?? 0));
    return html`<section class="card" aria-label="Result"><div class="card-head"><div><h3>Import finished</h3><p>${b.imported_at ? fmtDateTime(b.imported_at) : ''}</p></div></div>
      <div class="overview">
        <div class="metric"><span class="k">Created</span><span class="v">${created}</span><span class="s">new records</span></div>
        <div class="metric"><span class="k">Linked</span><span class="v">${s.linked_to_existing ?? 0}</span><span class="s">to existing records</span></div>
        <div class="metric"><span class="k">Skipped</span><span class="v">${s.skipped ?? 0}</span><span class="s">by your decision</span></div>
        <div class="metric"><span class="k">Held back</span><span class="v">${s.held ?? 0}</span><span class="s">undecided</span></div>
        <div class="metric"><span class="k">Had errors</span><span class="v">${s.invalid ?? 0}</span><span class="s">not imported</span></div></div>
      <p class="hint imp-lock">${icon('lock')} The uploaded file and the personal data copied from it have been deleted.</p>
      <div class="btn-row"><a class="btn" href="#/programs/${tab.program.id}/children">Go to the children</a><a class="btn secondary" href="${tab.link()}">All imports</a></div></section>`;
  };

  const draw = (focus) => {
    if (!b) return;
    let body;
    let current;
    if (b.status === 'imported') { body = resultCard(); current = 4; } else if (b.status === 'cancelled') {
      body = html`<div class="card"><div class="empty">${icon('x')}<b>This import was cancelled</b><span>The uploaded file and the data copied from it were deleted. Nothing was created.</span><a class="btn secondary" href="${tab.link()}">Start a new import</a></div></div>`; current = 0;
    } else if (b.status === 'uploaded' || editing) { body = mappingStep(); current = 1; } else if (b.status === 'mapped') { body = checkStep(); current = 2; } else {
      const hasDup = (b.summary?.duplicate ?? 0) > 0;
      body = html`${checkStep()}${decideStep()}${importStep()}`;
      current = hasDup && dups.undecided > 0 ? 3 : 4;
    }
    paint(root, html`<div class="reg imp">${head(b.file_name)}${b.status === 'cancelled' ? '' : stepper(current, b.status === 'imported')}${working ? html`<div class="banner info" role="status">${icon('info')}<div class="grow">${working}</div></div>` : ''}${body}</div>`);
    if (focus) $(focus, root)?.focus();
  };

  // ---- loading -------------------------------------------------------------------------------------------------------
  const loadInvalid = async (append = false) => {
    invalid.loading = true; invalid.error = null;
    try {
      const res = await api.get(`${base}/rows?status=invalid&per_page=10&page=${invalid.page}`);
      invalid = { ...invalid, rows: append ? invalid.rows.concat(res.data) : res.data, last: res.meta.last_page, total: res.meta.total, loading: false };
    } catch (e) { invalid.loading = false; invalid.error = api.explain(e); }
  };
  const loadDups = async (append = false) => {
    dups.loading = true; dups.error = null;
    try {
      const res = await api.get(`${base}/rows?status=duplicate&per_page=10&page=${dups.page}`);
      const undecided = (await api.get(`${base}/rows?status=duplicate&undecided=1&per_page=1`)).meta.total;
      dups = { ...dups, rows: append ? dups.rows.concat(res.data) : res.data, last: res.meta.last_page, total: res.meta.total, undecided, loading: false };
    } catch (e) { dups.loading = false; dups.error = api.explain(e); }
  };
  /** Bring the lists in line with the batch's state. */
  const refreshLists = async () => {
    if (b.status !== 'validated') return;
    invalid = { rows: [], page: 1, last: 1, total: 0, loading: false, error: null };
    dups = { rows: [], page: 1, last: 1, total: 0, undecided: 0, loading: false, error: null };
    await Promise.all([(b.summary?.invalid ?? 0) > 0 ? loadInvalid() : null, (b.summary?.duplicate ?? 0) > 0 ? loadDups() : null]);
  };
  const initMapping = () => {
    const have = b.mapping && Object.keys(b.mapping).length ? b.mapping : ref?.suggested_mapping ?? {};
    draftMapping = {};
    for (const [k, v] of Object.entries(have)) draftMapping[k] = v;
    draftDate = b.date_format ?? '';
    mapErrors = { cols: {}, top: null };
  };
  const start = async () => {
    paint(root, skeleton());
    try {
      const { data } = await api.get(base);
      b = data;
      ref = data.targets ? { targets: data.targets, date_formats: data.date_formats, sample: data.sample ?? [], suggested_mapping: data.suggested_mapping } : ref;
      tab.setTitle(`Import: ${b.file_name}`);
      if (open()) initMapping();
      await refreshLists();
      draw();
    } catch (e) {
      paint(root, e.status === 404
        ? html`<div class="reg"><a class="reg-back" href="${tab.link()}">${icon('chevron', 'flip')}All imports</a><div class="card"><div class="empty">${icon('alert')}<b>Import not found</b><span>It may belong to another program.</span></div></div></div>`
        : failure(api.explain(e), 'The import could not be opened'));
    }
  };

  // ---- actions -------------------------------------------------------------------------------------------------------
  const step = async (label, fn) => { working = label; draw(); try { return await fn(); } finally { working = null; } };

  const validate = async () => {
    try {
      await step('Checking every row…', async () => {
        const { data } = await api.post(`${base}/validate`, {}, { timeout: 180000 });
        merge(data.batch);
        editing = false;
        await refreshLists();
      });
    } catch (e) { toast(sayError(e), 'bad'); }
    draw();
  };

  const saveMapping = async (btn) => {
    mapErrors = { cols: {}, top: null };
    const picked = Object.entries(draftMapping).filter(([, t]) => t);
    const dobMapped = picked.some(([, t]) => t === 'aytam.date_of_birth');
    if (dobMapped && !draftDate) mapErrors.date = 'Choose how dates are written, so none is misread.';
    const seen = {};
    for (const [i, t] of picked) { if (seen[t] !== undefined) mapErrors.cols[i] = 'Two columns use the same field.'; seen[t] = i; }
    if (mapErrors.date || Object.keys(mapErrors.cols).length) { draw(); $('.err', root)?.scrollIntoView({ block: 'center' }); return; }
    busy(btn, true, 'Saving…');
    try {
      const { data } = await api.put(`${base}/mapping`, { mapping: Object.fromEntries(picked), date_format: draftDate || 'iso' });
      merge(data);
      editing = false;
      await validate();
    } catch (e) {
      busy(btn, false);
      mapErrors.top = e.status === 422 ? e.message : sayError(e);
      draw();
      $('.banner.bad', root)?.scrollIntoView({ block: 'center' });
    }
  };

  const decide = async (rowId, decision, existingId, focusSel) => {
    const row = dups.rows.find((r) => r.id === rowId);
    if (!row) return;
    try {
      const { data } = await api.post(`${base}/rows/${rowId}/decision`, { decision, existing_aytam_id: existingId ?? null });
      const wasOpen = !row.decision;
      Object.assign(row, data);
      const isOpen = !row.decision;
      dups.undecided += (isOpen ? 1 : 0) - (wasOpen ? 1 : 0);
      draw(focusSel);
    } catch (e) { toast(sayError(e), 'bad'); }
  };
  const bulk = async (decision) => {
    const text = decision === 'skip' ? `The ${plural(dups.undecided, 'undecided row')} will be skipped and not imported.` : `A new record will be created for each of the ${plural(dups.undecided, 'undecided row')}, even though they look like children already on file.`;
    if (!(await confirmDialog({ title: decision === 'skip' ? 'Skip all undecided?' : 'Create new for all undecided?', text: `${text} You can still change any single row afterwards.`, confirmLabel: decision === 'skip' ? 'Skip them' : 'Create new for all' }))) return;
    try {
      const { data } = await api.post(`${base}/decisions`, { decision });
      toast(`${plural(data.updated, 'row')} updated.`);
      dups.page = 1;
      await loadDups();
      draw();
    } catch (e) { toast(sayError(e), 'bad'); }
  };

  const runImport = async () => {
    run.stop = false; run.error = null;
    run.state = 'running';
    draw();
    try {
      for (;;) {
        const { data: res } = await api.post(`${base}/commit`, { status, limit: 100 }, { timeout: 180000 });
        run.totals.created += res.created; run.totals.linked += res.linked; run.totals.skipped += res.skipped;
        if (!run.total) run.total = res.processed + res.remaining;
        run.done = Math.min(run.total, run.done + res.processed);
        if (res.finished) { merge(res.batch); run = { ...run, state: 'done', done: run.total }; break; }
        if (run.stop) { run.state = 'paused'; break; }
        draw();
      }
    } catch (e) {
      run.state = 'error';
      run.error = `${sayError(e)} Nothing is lost: press "Continue importing" to carry on where it stopped.`;
    }
    draw();
    if (run.state === 'done') toast('Import finished.');
  };

  // ---- events --------------------------------------------------------------------------------------------------------
  delegate(root, 'click', '[data-retry]', start);
  delegate(root, 'change', '[data-col]', (e, sel) => { if (sel.value) draftMapping[sel.dataset.col] = sel.value; else delete draftMapping[sel.dataset.col]; mapErrors = { cols: {}, top: mapErrors.top }; draw(`#col-${sel.dataset.col}`); });
  delegate(root, 'change', '[data-date-format]', (e, sel) => { draftDate = sel.value; mapErrors.date = null; });
  delegate(root, 'change', '[data-start-status]', (e, sel) => { status = sel.value; });
  delegate(root, 'submit', '[data-map-form]', (e) => { e.preventDefault(); saveMapping($('[data-save-map]', root)); });
  delegate(root, 'click', '[data-edit-map]', () => { editing = true; initMapping(); draw(); window.scrollTo(0, 0); });
  delegate(root, 'click', '[data-stop-edit]', () => { editing = false; draw(); });
  delegate(root, 'click', '[data-validate]', validate);
  delegate(root, 'click', '[data-more]', async (e, btn) => {
    const state = btn.dataset.more === 'invalid' ? invalid : dups;
    state.page += 1;
    await (btn.dataset.more === 'invalid' ? loadInvalid(true) : loadDups(true));
    draw();
  });
  delegate(root, 'click', '[data-retry-invalid]', async () => { await loadInvalid(); draw(); });
  delegate(root, 'click', '[data-retry-dups]', async () => { await loadDups(); draw(); });
  delegate(root, 'click', '[data-decide]', (e, btn) => {
    const rowId = btn.closest('[data-row]').dataset.row;
    const d = btn.dataset.decide;
    const sel = `[data-row="${rowId}"] [data-decide="${d === 'later' ? 'create_new' : d}"]${btn.dataset.existing ? `[data-existing="${btn.dataset.existing}"]` : ''}`;
    decide(rowId, d === 'later' ? null : d, btn.dataset.existing, sel);
  });
  delegate(root, 'click', '[data-bulk]', (e, btn) => bulk(btn.dataset.bulk));
  delegate(root, 'click', '[data-import]', runImport);
  delegate(root, 'click', '[data-stop]', (e, btn) => { run.stop = true; btn.disabled = true; btn.textContent = 'Stopping after this batch…'; });

  const cancel = async (title, text, confirmLabel, then) => {
    if (!(await confirmDialog({ title, text, confirmLabel, danger: true }))) return;
    try { await api.del(base); toast('Import cancelled. The file was deleted.'); then(); } catch (e) { toast(sayError(e), 'bad'); }
  };
  delegate(root, 'click', '[data-cancel]', () => cancel('Cancel this import?', `The uploaded file and the data copied from it are deleted. ${run.done ? 'Records this import already created stay.' : 'Nothing has been created from it.'}`, 'Cancel import', () => tab.go()));
  delegate(root, 'click', '[data-startover]', () => cancel('Start over with a fixed file?', 'This import is cancelled and its file deleted. Then upload the corrected file.', 'Cancel and start over', () => tab.go()));

  await start();
}
