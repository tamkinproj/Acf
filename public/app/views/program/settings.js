import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { busy, confirmDialog, field, readForm, showErrors, textarea, toast } from '../../core/ui.js';
import { $, html } from '../../core/util.js';

// Program settings: its details (managers), its module's own requirements (also the module's supervisor), and its lifecycle.
const DOCS = [['photo', 'Photo'], ['birth_certificate', 'Birth certificate'], ['passport', 'Passport'], ['transcript', 'Transcript / Form 137'], ['diploma', 'Diploma'],
  ['medical_certificate', 'Medical certificate'], ['police_clearance', 'Police / NBI clearance'], ['recommendation_letter', 'Recommendation letter'], ['other', 'Other']];
const ACTIONS = {
  active: { label: 'Activate', text: 'Work in this program can begin.', tone: '' },
  inactive: { label: 'Deactivate', text: 'Everyone can still look, but changes stop and the public registration link closes. You can activate it again.', tone: '' },
  archived: { label: 'Archive', text: 'The program is kept for the record and can no longer be changed. Public registration closes.', tone: 'danger' },
};

export default {
  async mount(tab) {
    const p = tab.program;
    const isAytam = p.module === 'aytam';
    const archived = p.status === 'archived';

    tab.root.innerHTML = html`
      <div class="page-head"><div><h2 class="section-title">Settings</h2><p>How this program is set up.</p></div></div>
      ${p.can.update && !archived ? html`<section class="card"><div class="card-head"><div><h3>Details</h3></div></div>
        <form class="form" data-details novalidate>
          ${field({ label: 'Name', name: 'name', value: p.name, required: true })}
          ${textarea({ label: 'Description', name: 'description', value: p.description })}
          <div class="row-2">${field({ label: 'Starts', name: 'start_date', type: 'date', value: p.start_date })}${field({ label: 'Ends', name: 'end_date', type: 'date', value: p.end_date })}</div>
          <div class="btn-row"><button class="btn" type="submit">Save details</button></div></form></section>` : ''}
      ${isAytam && p.can.configure && !archived ? html`<section class="card"><div class="card-head"><div><h3>Aytam requirements</h3><p>What every child's record should have.</p></div></div>
        <form class="form" data-config novalidate>
          ${field({ label: 'ID prefix', name: 'code_prefix', value: p.config?.code_prefix ?? 'AYT', hint: 'Capital letters, 2 to 6. Used for new IDs such as AYT-000123; existing IDs never change.', attrs: 'maxlength="6" autocapitalize="characters"' })}
          <fieldset class="field"><span class="lbl">Documents every child needs</span>
            ${DOCS.map(([key, text]) => html`<label class="check"><span>${text}</span><input type="checkbox" name="doc" value="${key}" ${(p.config?.required_documents ?? []).includes(key) ? 'checked' : ''}></label>`)}
            <span class="err" data-err="required_documents" hidden></span></fieldset>
          <div class="btn-row"><button class="btn" type="submit">Save requirements</button></div></form></section>` : ''}
      <section class="card"><div class="card-head"><div><h3>Status</h3><p>This program is <b>${p.status}</b>.</p></div></div>
        ${p.can.activate && p.transitions?.length ? html`<ul class="list">${p.transitions.map((to) => html`<li><div class="grow"><div class="t">${ACTIONS[to]?.label ?? to}</div><div class="d">${ACTIONS[to]?.text ?? ''}</div></div>
          <button class="btn sm ${ACTIONS[to]?.tone ?? 'secondary'}" type="button" data-status="${to}">${ACTIONS[to]?.label ?? to}</button></li>`)}</ul>`
          : html`<p class="muted">${p.can.activate ? 'There is nothing more to change.' : 'Only a foundation administrator can change a program\'s status.'}</p>`}</section>`.toString();

    $('[data-details]', tab.root)?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const btn = $('button[type=submit]', form);
      busy(btn, true, 'Saving…');
      try { await api.patch(`/programs/${p.id}`, readForm(form)); toast('Saved.'); await tab.reload(); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); busy(btn, false); }
    });

    $('[data-config]', tab.root)?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.currentTarget;
      const docs = [...form.querySelectorAll('input[name=doc]:checked')].map((c) => c.value);
      const btn = $('button[type=submit]', form);
      busy(btn, true, 'Saving…');
      try {
        await api.patch(`/programs/${p.id}/config`, { config: { code_prefix: form.elements.code_prefix.value.trim().toUpperCase(), required_documents: docs } });
        toast('Saved.');
        await tab.reload();
      } catch (err) {
        const errors = { ...err.errors };
        for (const k of Object.keys(errors)) if (k.startsWith('config.')) errors[k.replace('config.', '').replace(/\..*$/, '')] = errors[k];
        if (!showErrors(form, errors)) toast(api.explain(err), 'bad');
        busy(btn, false);
      }
    });

    tab.root.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-status]');
      if (!btn) return;
      const to = btn.dataset.status;
      if (!(await confirmDialog({ title: `${ACTIONS[to]?.label ?? to} this program?`, text: ACTIONS[to]?.text ?? '', confirmLabel: ACTIONS[to]?.label ?? 'Confirm', danger: to === 'archived' }))) return;
      busy(btn, true);
      try { await api.post(`/programs/${p.id}/status`, { status: to }); toast('Status changed.'); await tab.reload(); } catch (err) { toast(api.explain(err), 'bad'); busy(btn, false); }
    });
  },
};
