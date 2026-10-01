import * as api from '../../core/api.js';
import { icon } from '../../core/icons.js';
import { href } from '../../core/router.js';
import { busy, confirmDialog, readForm, select, sheet, showErrors, textarea, toast } from '../../core/ui.js';
import { $, html } from '../../core/util.js';

// The organizations that work with this program, and on what terms. The organization itself is a foundation-level record
// (see Organizations); what is stored here is only this program's relationship with it.
const RELATIONSHIPS = [['partner', 'Partner'], ['funder', 'Funder'], ['implementing_partner', 'Implementing partner'], ['school', 'School'], ['referral', 'Referral source'], ['other', 'Other']];
const label = (v) => RELATIONSHIPS.find(([k]) => k === v)?.[1] ?? v;

export default {
  async mount(tab) {
    const id = tab.program.id;
    let state = { links: [], can_configure: false };
    let failed = null;

    const draw = () => {
      tab.root.innerHTML = view(state, failed, tab).toString();
    };
    const load = async () => {
      try { state = (await api.get(`/programs/${id}/organizations`)).data; failed = null; } catch (e) { failed = api.explain(e); }
      draw();
    };

    tab.root.addEventListener('click', async (e) => {
      if (e.target.closest('[data-retry]')) return load();
      if (e.target.closest('[data-add]')) return openAdd();
      const edit = e.target.closest('[data-edit]');
      if (edit) return openEdit(state.links.find((l) => l.id === edit.dataset.edit));
      const remove = e.target.closest('[data-unlink]');
      if (remove) {
        const link = state.links.find((l) => l.id === remove.dataset.unlink);
        if (!(await confirmDialog({ title: `Unlink ${link.organization.name}?`, text: 'The organization stays in the foundation\'s list; it just no longer works with this program.', confirmLabel: 'Unlink', danger: true }))) return;
        try { await api.del(`/programs/${id}/organizations/${link.id}`); toast('Unlinked.'); load(); } catch (err) { toast(api.explain(err), 'bad'); }
      }
    });

    async function openAdd() {
      let options = [];
      try { options = (await api.get(`/programs/${id}/organization-options`)).data; } catch (e) { return toast(api.explain(e), 'bad'); }
      if (!options.length) {
        return sheet({ title: 'Add a partner', body: html`<div class="stack"><p>There is no organization left to add. ${tab.can('organizations.create') ? 'Create it first in the foundation\'s Organizations list, then come back.' : 'Ask a foundation administrator to add the organization to the foundation\'s list first.'}</p>
          <div class="btn-row">${tab.can('organizations.view') ? html`<a class="btn" href="${href('organizations')}" data-close>Open Organizations</a>` : ''}<button class="btn secondary" type="button" data-close>Close</button></div></div>` });
      }
      sheet({
        title: 'Add a partner',
        body: html`<form class="form" novalidate>
          ${select({ label: 'Organization', name: 'organization_id', options: [['', 'Choose…'], ...options.map((o) => [o.id, `${o.name}${o.country ? ` · ${o.country}` : ''}`])] })}
          ${select({ label: 'Their role', name: 'relationship', value: 'partner', options: RELATIONSHIPS })}
          ${textarea({ label: 'Notes', name: 'notes', hint: 'For example what they provide, or how often you report to them.' })}
          <div class="btn-row"><button class="btn" type="submit">Add partner</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
        onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
          e.preventDefault();
          const form = e.currentTarget;
          const btn = $('button[type=submit]', form);
          busy(btn, true, 'Adding…');
          try { await api.post(`/programs/${id}/organizations`, readForm(form)); close(); toast('Partner added.'); load(); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
        }),
      });
    }

    function openEdit(link) {
      if (!link) return;
      sheet({
        title: link.organization.name,
        body: html`<form class="form" novalidate>
          ${select({ label: 'Their role', name: 'relationship', value: link.relationship, options: RELATIONSHIPS })}
          ${select({ label: 'Status', name: 'status', value: link.status, options: [['active', 'Active'], ['inactive', 'Paused']] })}
          ${textarea({ label: 'Notes', name: 'notes', value: link.notes })}
          <div class="btn-row"><button class="btn" type="submit">Save</button><button class="btn secondary" type="button" data-close>Cancel</button></div></form>`,
        onMount: (el, close) => $('form', el).addEventListener('submit', async (e) => {
          e.preventDefault();
          const form = e.currentTarget;
          const btn = $('button[type=submit]', form);
          busy(btn, true, 'Saving…');
          try { await api.patch(`/programs/${id}/organizations/${link.id}`, readForm(form)); close(); toast('Saved.'); load(); } catch (err) { if (!showErrors(form, err.errors)) toast(api.explain(err), 'bad'); } finally { busy(btn, false); }
        }),
      });
    }

    await load();
  },
};

function view(state, failed, tab) {
  const canWrite = state.can_configure && tab.program.status !== 'archived';
  return html`
    <div class="page-head"><div><h2 class="section-title">Partners</h2><p>Organizations that fund, refer or work alongside this program.</p></div>
      ${canWrite ? html`<div class="btn-row"><button class="btn" type="button" data-add>${icon('plus')} Add partner</button></div>` : ''}</div>
    ${failed ? html`<div class="banner bad">${icon('alert')}<div class="grow">${failed}</div><button class="btn sm" type="button" data-retry>Retry</button></div>` : ''}
    <div class="card flush">${state.links.length ? html`<ul class="list">${state.links.map((l) => {
      const o = l.organization;
      const contact = o?.contacts?.find((c) => c.is_primary) ?? o?.contacts?.[0];
      return html`<li><span class="avatar sq" aria-hidden="true">${icon('folder')}</span>
        <div class="grow"><div class="t">${o?.name}</div>
          <div class="d">${[o?.type, o?.country].filter(Boolean).join(' · ')}${contact ? html` · ${contact.name}${contact.email ? ` (${contact.email})` : ''}` : ''}</div>
          ${l.notes ? html`<div class="d">${l.notes}</div>` : ''}</div>
        <span class="chip blue">${label(l.relationship)}</span>${l.status === 'inactive' ? html`<span class="chip grey">Paused</span>` : ''}
        ${canWrite ? html`<button class="icon-btn" type="button" data-edit="${l.id}" aria-label="Edit ${o?.name}">${icon('edit')}</button><button class="icon-btn" type="button" data-unlink="${l.id}" aria-label="Unlink ${o?.name}">${icon('trash')}</button>` : ''}</li>`;
    })}</ul>`
      : html`<div class="empty">${icon('folder')}<b>No partners yet</b><span>${canWrite ? 'Add the organizations this program works with.' : 'No organization has been linked to this program.'}</span></div>`}</div>`;
}
