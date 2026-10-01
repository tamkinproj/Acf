import * as api from '../core/api.js';
import { assetUrl } from '../core/config.js';
import { db, setMeta } from '../core/db.js';
import { icon, mark } from '../core/icons.js';
import { busy, field, readForm, sheet, showErrors, textarea, toast } from '../core/ui.js';
import { $, html } from '../core/util.js';
import { session } from '../auth/session.js';
import { updateRecord } from '../sync/outbox.js';
import { kit } from './kit.js';

// The foundation's identity. Text fields are edited offline like everything else; the logo is a file, so it needs a connection.
const ROWS = [['Name', 'name'], ['Short name', 'short_name'], ['About', 'description'], ['Address', 'address'], ['Phone', 'phone'], ['Email', 'email'], ['Website', 'website'], ['Registration number', 'registration_number'], ['Registration details', 'registration_info']];

export default {
  async mount(ctx) {
    const k = kit(ctx);
    const manage = ctx.can('foundation.manage');
    let f = null;
    let places = [];
    const logoHash = () => session.get().branding?.logo_hash;
    const draw = () => k.render(view(f, places, manage, logoHash()));

    k.live(async () => ({ f: await db.foundations.toCollection().first(), places: await db.locations.filter((l) => !l.deleted_at).toArray() }), (d) => { f = d.f; places = d.places; draw(); });
    const off = session.subscribe(draw);

    k.on('click', '[data-edit]', () => edit(f, places));
    k.on('change', '[data-logo]', async (e, t) => {
      const file = t.files[0];
      if (!file) return;
      const body = new FormData();
      body.append('logo', file);
      try { await api.post('/foundation/logo', body); await refreshBranding(); toast('Logo updated'); } catch (err) { toast(api.explain(err), 'bad'); }
      t.value = '';
    });
    k.on('click', '[data-rmlogo]', async () => {
      try { await api.del('/foundation/logo'); await refreshBranding(); toast('Logo removed'); } catch (err) { toast(api.explain(err), 'bad'); }
    });
    return () => { off(); k.cleanup(); };
  },
};

async function refreshBranding() {
  const { data } = await api.get('/system/status');
  await setMeta('branding', data);
  session.set({ branding: data });
}

function edit(f, places) {
  sheet({
    title: 'Edit foundation profile',
    body: html`<form class="form" novalidate>
      ${field({ label: 'Name', name: 'name', value: f.name, required: true })}
      ${field({ label: 'Short name', name: 'short_name', value: f.short_name, hint: 'Used where space is tight.' })}
      ${textarea({ label: 'About', name: 'description', value: f.description })}
      ${textarea({ label: 'Address', name: 'address', value: f.address })}
      ${field({ label: 'Phone', name: 'phone', type: 'tel', value: f.phone })}
      ${field({ label: 'Email', name: 'email', type: 'email', value: f.email })}
      ${field({ label: 'Website', name: 'website', type: 'url', value: f.website, hint: 'Starting with https://' })}
      ${field({ label: 'Registration number', name: 'registration_number', value: f.registration_number })}
      ${textarea({ label: 'Registration details', name: 'registration_info', value: f.registration_info })}
      <div class="btn-row"><button class="btn" type="submit">Save</button><button class="btn ghost" type="button" data-close>Cancel</button></div></form>`,
    onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
      e.preventDefault();
      const v = readForm(e.currentTarget);
      if (!v.name) { showErrors(e.currentTarget, { name: 'The foundation needs a name.' }); return; }
      const changed = Object.fromEntries(Object.entries(v).filter(([key, val]) => (f[key] ?? null) !== val));
      if (Object.keys(changed).length) await updateRecord('foundations', f.id, changed);
      close();
      toast(Object.keys(changed).length ? 'Saved on this device. It will sync when connected.' : 'Nothing changed');
    }),
  });
}

function view(f, places, manage, logo) {
  if (!f) return html`<div class="empty">${icon('building')}<b>Profile not loaded yet</b><span>It arrives with the first sync.</span></div>`;
  const home = places.find((p) => p.id === f.default_location_id);
  return html`
    <div class="page-head"><div><h2>Foundation</h2><p>How the foundation is identified on every screen and report.</p></div>
      ${manage ? html`<div class="btn-row"><button class="btn" type="button" data-edit>${icon('edit')} Edit profile</button></div>` : ''}</div>
    <div class="card letterhead">
      <div class="logo-box">${logo ? html`<img src="${assetUrl('assets/logo')}?v=${logo}" alt="Logo of ${f.name}">` : mark()}</div>
      <div class="grow"><h3>${f.name}</h3>${f.short_name ? html`<p class="muted">${f.short_name}</p>` : ''}
        ${manage ? html`<div class="btn-row"><label class="btn sm ghost">${icon('upload')} ${logo ? 'Change logo' : 'Upload logo'}<input type="file" accept="image/png,image/jpeg,image/webp" data-logo hidden></label>
          ${logo ? html`<button class="btn sm ghost" type="button" data-rmlogo>Remove</button>` : ''}</div><p class="hint">Logos need a connection to upload. PNG, JPEG or WebP.</p>` : ''}</div></div>
    <div class="card"><dl class="kv">
      ${ROWS.slice(2).map(([label, key]) => html`<dt>${label}</dt><dd>${f[key] || html`<span class="muted">Not set</span>`}</dd>`)}
      <dt>Main place</dt><dd>${home?.name || html`<span class="muted">Not set</span>`}</dd></dl></div>`;
}
