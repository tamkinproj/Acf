import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { busy, field, select, toast } from '../../core/ui.js';
import { $, html } from '../../core/util.js';

// Settings that belong to the platform itself. Each foundation has its own settings (currency, language, locking...).
const LABELS = {
  'app.name': ['Platform name', 'Shown on the sign-in page and in the platform header.'],
  'app.timezone': ['Time zone', 'Used for times on platform screens. Records are always stored in UTC.'],
  'app.locale': ['Default language', 'The language new foundations start with.'],
  'security.idle_lock_minutes': ['Lock after (minutes)', 'How long a screen can sit idle before it asks for the password again.'],
};
const LOCALES = [['en', 'English'], ['fil', 'Filipino'], ['ar', 'العربية']];

export default {
  async mount(ctx) {
    const manage = ctx.can('platform.settings.manage');
    let rows = [];
    let failed = null;
    const draw = () => { ctx.root.innerHTML = view(rows, failed, manage).toString(); };
    const load = async () => { try { rows = (await api.get('/platform/settings')).data; failed = null; } catch (e) { failed = api.explain(e); } draw(); };

    ctx.root.addEventListener('click', (e) => { if (e.target.closest('[data-retry]')) load(); });
    ctx.root.addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const btn = $('button[type=submit]', form);
      const settings = {};
      for (const r of rows) {
        const el = form.elements[r.key.replace(/\./g, '_')];
        settings[r.key] = r.key === 'security.idle_lock_minutes' ? Number(el.value) : el.value;
      }
      busy(btn, true, 'Saving…');
      try { rows = (await api.put('/platform/settings', { settings })).data; toast('Saved.'); draw(); } catch (err) {
        const first = Object.values(err.errors ?? {}).flat()[0];
        toast(first ?? api.explain(err), 'bad');
        busy(btn, false);
      }
    });
    await load();
  },
};

function view(rows, failed, manage) {
  const control = (r) => {
    const [label, hint] = LABELS[r.key] ?? [r.key, ''];
    const name = r.key.replace(/\./g, '_');
    if (r.key === 'app.locale') return select({ label, name, value: r.value, options: LOCALES, hint, disabled: !manage });
    if (r.key === 'app.timezone') {
      const zones = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [r.value];
      return select({ label, name, value: r.value, options: zones.includes(r.value) ? zones : [r.value, ...zones], hint, disabled: !manage });
    }
    return field({ label, name, value: r.value, type: r.key === 'security.idle_lock_minutes' ? 'number' : 'text', hint, readonly: !manage, attrs: r.key === 'security.idle_lock_minutes' ? 'min="1" max="480"' : '' });
  };
  return html`
    <div class="page-head"><div><h1>Platform settings</h1><p>Settings for the platform itself.</p></div></div>
    ${failed ? html`<div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>` : ''}
    ${rows.length ? html`<form class="card flush">${rows.map((r) => html`<div class="setting">${control(r)}</div>`)}
      ${manage ? html`<div class="card-head"><span></span><button class="btn" type="submit">Save settings</button></div>` : ''}</form>` : failed ? '' : html`<div class="skeleton row"></div>`}`;
}
