import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { busy, confirmDialog, field, readForm, select, sheet, showErrors, showSecret, toast } from '../../core/ui.js';
import { $, ago, html, initials } from '../../core/util.js';

// Platform administrators: the few people who run the service itself.
export default {
  async mount(ctx) {
    const me = ctx.session.get().user.id;
    let users = [];
    let failed = null;
    const draw = () => { ctx.root.innerHTML = view(users, failed, me).toString(); };
    const load = async () => { try { users = (await api.get('/platform/users')).data; failed = null; } catch (e) { failed = api.explain(e); } draw(); };

    ctx.root.addEventListener('click', async (e) => {
      if (e.target.closest('[data-retry]')) return load();
      if (e.target.closest('[data-add]')) return openCreate();
      const edit = e.target.closest('[data-edit]');
      if (edit) return openEdit(users.find((u) => u.id === edit.dataset.edit));
    });

    function openCreate() {
      sheet({
        title: 'Add a platform administrator',
        body: html`<form class="form" novalidate>
          <p class="muted">Platform administrators create and manage foundations. They cannot see a foundation's records.</p>
          ${field({ label: 'Full name', name: 'name', required: true })}${field({ label: 'Email', name: 'email', type: 'email', required: true })}${field({ label: 'Phone', name: 'phone', type: 'tel' })}
          <div class="btn-row"><button class="btn" type="submit">Create account</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
        onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
          e.preventDefault();
          const form = e.currentTarget;
          const btn = $('button[type=submit]', form);
          busy(btn, true, 'Creating…');
          try { const { data } = await api.post('/platform/users', readForm(form)); close(); showSecret({ title: 'Account created', who: data.name, password: data.temporary_password }); load(); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
        }),
      });
    }

    function openEdit(u) {
      if (!u) return;
      const self = u.id === me;
      sheet({
        title: u.name,
        body: html`<form class="form" novalidate>
          ${field({ label: 'Full name', name: 'name', value: u.name, required: true })}${field({ label: 'Email', name: 'email', type: 'email', value: u.email, required: true })}${field({ label: 'Phone', name: 'phone', type: 'tel', value: u.phone })}
          ${select({ label: 'Status', name: 'status', value: u.status, options: [['active', 'Active'], ['disabled', 'Disabled — cannot sign in']], disabled: self, hint: self ? 'You cannot disable yourself.' : '' })}
          <div class="btn-row"><button class="btn" type="submit">Save</button><button class="btn secondary" type="button" data-close>Cancel</button></div>
          <hr><div class="btn-row"><button class="btn sm secondary" type="button" data-reset>${icon('key')} Reset password</button>${self ? '' : html`<button class="btn sm danger" type="button" data-remove>${icon('trash')} Remove</button>`}</div></form>`,
        onMount: (el, close) => {
          const form = $('form', el);
          form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const v = readForm(form);
            if (self) delete v.status;
            const btn = $('button[type=submit]', form);
            busy(btn, true, 'Saving…');
            try { await api.patch(`/platform/users/${u.id}`, v); close(); toast('Saved.'); load(); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
          });
          $('[data-reset]', el).addEventListener('click', async () => {
            if (!(await confirmDialog({ title: 'Reset password?', text: `${u.name} is signed out everywhere and must use a new temporary password.`, confirmLabel: 'Reset password' }))) return;
            try { const { data } = await api.post(`/platform/users/${u.id}/reset-password`); close(); showSecret({ title: 'Password reset', who: u.name, password: data.temporary_password }); } catch (err) { toast(api.explain(err), 'bad'); }
          });
          $('[data-remove]', el)?.addEventListener('click', async () => {
            if (!(await confirmDialog({ title: `Remove ${u.name}?`, text: 'They can no longer sign in.', confirmLabel: 'Remove', danger: true }))) return;
            try { await api.del(`/platform/users/${u.id}`); close(); toast('Removed.'); load(); } catch (err) { toast(api.explain(err), 'bad'); }
          });
        },
      });
    }
    await load();
  },
};

function view(users, failed, me) {
  return html`
    <div class="page-head"><div><h1>Administrators</h1><p>The people who run the platform.</p></div><div class="btn-row"><button class="btn" type="button" data-add>${icon('plus')} Add administrator</button></div></div>
    ${failed ? html`<div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>` : ''}
    <div class="card flush">${users.length ? html`<table class="table"><thead><tr><th scope="col">Name</th><th scope="col">Status</th><th scope="col">Last sign-in</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
      <tbody>${users.map((u) => html`<tr data-edit="${u.id}"><td class="c-name"><div class="cell"><span class="avatar" aria-hidden="true">${initials(u.name)}</span><div><div class="t">${u.name}${u.id === me ? html` <span class="chip blue">You</span>` : ''}</div><div class="d muted">${u.email}</div></div></div></td>
        <td class="c-status">${u.status === 'disabled' ? html`<span class="chip red">Disabled</span>` : html`<span class="chip green">Active</span>`}</td>
        <td class="c-role muted">${u.last_login_at ? ago(u.last_login_at) : 'never'}</td><td class="c-act"><button class="icon-btn" type="button" data-edit="${u.id}" aria-label="Edit ${u.name}">${icon('edit')}</button></td></tr>`)}</tbody></table>`
      : html`<div class="empty">${icon('shield')}<b>No administrators</b></div>`}</div>`;
}
