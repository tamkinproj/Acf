import { html, raw, $, esc } from './util.js';
import { icon } from './icons.js';

// UI primitives: toasts, sheets (modals), confirmations, form helpers. No inline handlers or styles (strict CSP).

let toastBox;
export function toast(text, kind = 'ok', ms = 4200) {
  toastBox ||= Object.assign(document.body.appendChild(document.createElement('div')), { className: 'toasts', role: 'status' });
  toastBox.setAttribute('aria-live', 'polite');
  const el = Object.assign(document.createElement('div'), { className: `toast ${kind === 'bad' ? 'bad' : ''}`, textContent: text });
  toastBox.appendChild(el);
  setTimeout(() => el.remove(), ms);
}

/** Open a bottom sheet (centered dialog on wide screens). Returns { el, close }. */
export function sheet({ title, body, onMount, onClose }) {
  const prevFocus = document.activeElement;
  const scrim = document.createElement('div');
  scrim.className = 'scrim';
  scrim.innerHTML = html`<div class="sheet" role="dialog" aria-modal="true" aria-label="${title}">
      <div class="sheet-head"><h3>${title}</h3><button class="icon-btn" type="button" data-close aria-label="Close">${icon('x')}</button></div>
      <div class="sheet-body">${body}</div></div>`.toString();
  document.body.appendChild(scrim);
  const el = $('.sheet-body', scrim);
  const close = (result) => {
    document.removeEventListener('keydown', onKey);
    scrim.remove();
    prevFocus?.focus?.();
    onClose?.(result);
  };
  const onKey = (e) => { if (e.key === 'Escape') close(); };
  document.addEventListener('keydown', onKey);
  scrim.addEventListener('mousedown', (e) => { if (e.target === scrim) close(); });
  scrim.addEventListener('click', (e) => { if (e.target.closest('[data-close]')) close(); });
  onMount?.(el, close);
  ($('[autofocus], input:not([type=hidden]), select, textarea', el) || $('[data-close]', scrim))?.focus();
  return { el, close };
}

export function confirmDialog({ title, text, confirmLabel = 'Confirm', danger = false, cancelLabel = 'Cancel' }) {
  return new Promise((resolve) => {
    let done = false;
    sheet({
      title,
      body: html`<div class="stack"><p>${text}</p>
        <div class="btn-row"><button class="btn ${danger ? 'danger' : ''}" data-yes type="button">${confirmLabel}</button><button class="btn ghost" data-close type="button">${cancelLabel}</button></div></div>`,
      onMount: (el, close) => el.querySelector('[data-yes]').addEventListener('click', () => { done = true; close(true); }),
      onClose: () => resolve(done),
    });
  });
}

// ---- form helpers --------------------------------------------------------------------------------------------
export const field = ({ label, name, type = 'text', value = '', hint = '', required = false, autocomplete = 'off', attrs = '', readonly = false }) => html`
  <div class="field"><label for="f-${name}">${label}${required ? raw(' <span aria-hidden="true">*</span>') : ''}</label>
  <input class="input" id="f-${name}" name="${name}" type="${type}" value="${value ?? ''}" autocomplete="${autocomplete}" ${raw(required ? 'required' : '')} ${raw(readonly ? 'readonly' : '')} ${raw(attrs)}>
  ${hint ? html`<span class="hint">${hint}</span>` : ''}<span class="err" data-err="${name}" hidden></span></div>`;

export const textarea = ({ label, name, value = '', hint = '' }) => html`
  <div class="field"><label for="f-${name}">${label}</label><textarea class="textarea" id="f-${name}" name="${name}">${value ?? ''}</textarea>
  ${hint ? html`<span class="hint">${hint}</span>` : ''}</div>`;

export const select = ({ label, name, value = '', options = [], hint = '', disabled = false }) => html`
  <div class="field"><label for="f-${name}">${label}</label>
  <select class="select" id="f-${name}" name="${name}" ${raw(disabled ? 'disabled' : '')}>${options.map((o) => { const [v, t] = Array.isArray(o) ? o : [o, o]; return html`<option value="${v}" ${raw(String(v) === String(value ?? '') ? 'selected' : '')}>${t}</option>`; })}</select>
  ${hint ? html`<span class="hint">${hint}</span>` : ''}<span class="err" data-err="${name}" hidden></span></div>`;

/** Read a <form> into a plain object. Empty strings become null so "cleared" fields clear on the server too. */
export function readForm(form) {
  const out = {};
  for (const [k, v] of new FormData(form)) out[k] = typeof v === 'string' ? (v.trim() === '' ? null : v.trim()) : v;
  for (const cb of form.querySelectorAll('input[type=checkbox][name]')) out[cb.name] = cb.checked;
  return out;
}

/** Show server-side validation messages next to their fields. */
export function showErrors(form, errors = {}) {
  for (const el of form.querySelectorAll('[data-err]')) { el.hidden = true; el.textContent = ''; }
  let shown = false;
  for (const [name, msgs] of Object.entries(errors)) {
    const el = form.querySelector(`[data-err="${CSS.escape(name)}"]`);
    if (el) { el.hidden = false; el.textContent = [].concat(msgs)[0]; shown = true; }
  }
  return shown;
}

export async function copyText(text) {
  try { await navigator.clipboard.writeText(text); toast('Copied'); } catch { toast('Copy failed: select the text and copy it manually.', 'bad'); }
}

export const busy = (btn, on, label) => { if (!btn) return; btn.disabled = on; if (label !== undefined) btn.dataset.label ||= btn.textContent; btn.textContent = on ? (label ?? btn.textContent) : (btn.dataset.label ?? btn.textContent); };
export { esc };
