import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { busy, confirmDialog, select, sheet, showErrors, toast } from '../../core/ui.js';
import { $, html, initials } from '../../core/util.js';

// Who works in this program and in which program role. A person's role here is separate from their role in the foundation:
// someone can be an ordinary member of the foundation and the Mushrif of exactly one program.
export default {
  async mount(tab) {
    const id = tab.program.id;
    let state = { members: [], roles: [], can_manage: false, candidates: [] };
    let failed = null;
    const draw = () => { tab.root.innerHTML = view(state, failed, tab).toString(); };
    const load = async () => {
      try { state = (await api.get(`/programs/${id}/team`)).data; failed = null; } catch (e) { failed = api.explain(e); }
      draw();
    };

    tab.root.addEventListener('click', async (e) => {
      if (e.target.closest('[data-retry]')) return load();
      if (e.target.closest('[data-add]')) return openAssign(null);
      const change = e.target.closest('[data-change]');
      if (change) return openAssign(state.members.find((m) => m.user_id === change.dataset.change));
      const remove = e.target.closest('[data-remove]');
      if (remove) {
        const m = state.members.find((x) => x.user_id === remove.dataset.remove);
        if (!(await confirmDialog({ title: `Remove ${m.name}?`, text: 'They will no longer be able to open this program. Their past work stays on record.', confirmLabel: 'Remove', danger: true }))) return;
        try { await api.del(`/programs/${id}/team/${m.user_id}`); toast('Removed from the team.'); load(); } catch (err) { toast(api.explain(err), 'bad'); }
      }
    });

    function openAssign(member) {
      const people = member ? [[member.user_id, `${member.name} (${member.email})`]] : [['', 'Choose a person…'], ...state.candidates.map((c) => [c.id, `${c.name} (${c.email})`])];
      if (!member && !state.candidates.length) return toast('Everyone in the foundation is already on this team.');
      sheet({
        title: member ? `Change role: ${member.name}` : 'Add to the team',
        body: html`<form class="form" novalidate>
          ${select({ label: 'Person', name: 'user_id', value: member?.user_id ?? '', options: people, disabled: !!member })}
          ${select({ label: 'Role in this program', name: 'role_id', value: member?.role?.id ?? state.roles[0]?.id, options: state.roles.map((r) => [r.id, r.name]) })}
          <ul class="list">${state.roles.map((r) => html`<li><div class="grow"><div class="t">${r.name}</div><div class="d">${r.description ?? ''}</div></div></li>`)}</ul>
          <div class="btn-row"><button class="btn" type="submit">${member ? 'Save' : 'Add'}</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
        onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
          e.preventDefault();
          const form = e.currentTarget;
          const user = member?.user_id ?? form.elements.user_id.value;
          if (!user) return showErrors(form, { user_id: 'Choose a person.' });
          const btn = $('button[type=submit]', form);
          busy(btn, true, 'Saving…');
          try { await api.put(`/programs/${id}/team/${user}`, { role_id: form.elements.role_id.value }); close(); toast(member ? 'Role changed.' : 'Added to the team.'); load(); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
        }),
      });
    }

    await load();
  },
};

function view(state, failed, tab) {
  const canWrite = state.can_manage && tab.program.status !== 'archived';
  return html`
    <div class="page-head"><div><h2 class="section-title">Team</h2><p>The people who work in this program. Foundation administrators can always open every program.</p></div>
      ${canWrite ? html`<div class="btn-row"><button class="btn" type="button" data-add>${icon('plus')} Add person</button></div>` : ''}</div>
    ${failed ? html`<div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>` : ''}
    <div class="card flush">${state.members.length ? html`<ul class="list">${state.members.map((m) => html`<li><span class="avatar" aria-hidden="true">${initials(m.name)}</span>
      <div class="grow"><div class="t">${m.name}</div><div class="d">${m.email}</div></div>
      ${m.user_status === 'disabled' ? html`<span class="chip red">Disabled</span>` : ''}<span class="chip blue">${m.role?.name ?? 'No role'}</span>
      ${canWrite ? html`<button class="icon-btn" type="button" data-change="${m.user_id}" aria-label="Change role of ${m.name}">${icon('edit')}</button><button class="icon-btn" type="button" data-remove="${m.user_id}" aria-label="Remove ${m.name}">${icon('trash')}</button>` : ''}</li>`)}</ul>`
      : html`<div class="empty">${icon('users')}<b>No team yet</b><span>${canWrite ? 'Add the people who will work in this program, such as its Mushrif and field workers.' : 'No one has been added to this program.'}</span></div>`}</div>`;
}
