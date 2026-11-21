#!/usr/bin/env node
/* Makes the "old browser" fallback stylesheet.
 *
 *   node deploy/legacy-css.cjs path/to/style.css            > legacy.css
 *   node deploy/legacy-css.cjs --html path/to/index.html     > legacy-site.css     (reads every <style> block of a page)
 *
 * Browsers older than about 2021 (Chrome / Android WebView before 87-89) ignore newer spacing and positioning properties
 * (padding-inline, margin-inline, inset, inset-inline-start, border-start-start-radius ...). The page then loses its side margins
 * and fixed bars shrink and jump to the left. For every rule that uses such a property this writes the classic equivalent
 * (padding-left, right, top ...) inside an @supports block that only a browser WITHOUT the property uses, so modern browsers
 * never see any change. Both directions are written (html[dir=ltr] and html[dir=rtl]).
 * Needs the "postcss" package (npm install postcss).
 */
const fs = require('fs'), path = require('path');
let postcss; try { postcss = require('postcss'); } catch (e) { postcss = require(path.join(process.env.POSTCSS_DIR || '', 'postcss')); }

const args = process.argv.slice(2), isHtml = args[0] === '--html', file = isHtml ? args[1] : args[0];
let css = fs.readFileSync(file, 'utf8');
if (isHtml) css = [...css.matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g)].map(m => m[1]).join('\n');

/* split a value on top-level spaces (not inside parentheses) */
function parts(v) { const out = []; let d = 0, cur = ''; for (const ch of v.trim()) { if (ch === '(') d++; if (ch === ')') d--; if (/\s/.test(ch) && d === 0) { if (cur) out.push(cur); cur = ''; } else cur += ch; } if (cur) out.push(cur); return out; }
const box = v => { const p = parts(v); return p.length === 1 ? [p[0], p[0], p[0], p[0]] : p.length === 2 ? [p[0], p[1], p[0], p[1]] : p.length === 3 ? [p[0], p[1], p[2], p[1]] : p.slice(0, 4); };   // top right bottom left
const pair = v => { const p = parts(v); return [p[0], p[1] || p[0]]; };                                                                              // start end

const IN = ['padding', 'margin'];
/* returns { common: [[prop,val]], ltr: [...], rtl: [...], kind: 'a'|'b'|'c' } or null when the declaration needs nothing */
function expand(d) {
  const p = d.prop.toLowerCase(), v = d.value; let m;
  const R = { common: [], ltr: [], rtl: [] };
  if (p === 'inset') { const [t, r, b, l] = box(v); R.common = [['top', t], ['right', r], ['bottom', b], ['left', l]]; return { ...R, kind: 'a' }; }
  if ((m = p.match(/^inset-block(-start|-end)?$/))) { const [s, e] = pair(v); R.common = m[1] === '-start' ? [['top', v]] : m[1] === '-end' ? [['bottom', v]] : [['top', s], ['bottom', e]]; return { ...R, kind: 'a' }; }
  if (p === 'inset-inline') { const [s, e] = pair(v); if (s === e) { R.common = [['left', s], ['right', e]]; return { ...R, kind: 'a' }; } R.ltr = [['left', s], ['right', e]]; R.rtl = [['right', s], ['left', e]]; return { ...R, kind: 'a' }; }
  if (p === 'inset-inline-start') { R.ltr = [['left', v]]; R.rtl = [['right', v]]; return { ...R, kind: 'a' }; }
  if (p === 'inset-inline-end') { R.ltr = [['right', v]]; R.rtl = [['left', v]]; return { ...R, kind: 'a' }; }
  for (const k of IN) {
    if (p === k + '-inline') { const [s, e] = pair(v); if (s === e) { R.common = [[k + '-left', s], [k + '-right', e]]; return { ...R, kind: 'a' }; } R.ltr = [[k + '-left', s], [k + '-right', e]]; R.rtl = [[k + '-right', s], [k + '-left', e]]; return { ...R, kind: 'a' }; }
    if (p === k + '-inline-start') { R.ltr = [[k + '-left', v]]; R.rtl = [[k + '-right', v]]; return { ...R, kind: 'a' }; }
    if (p === k + '-inline-end') { R.ltr = [[k + '-right', v]]; R.rtl = [[k + '-left', v]]; return { ...R, kind: 'a' }; }
    if (p === k + '-block') { const [s, e] = pair(v); R.common = [[k + '-top', s], [k + '-bottom', e]]; return { ...R, kind: 'a' }; }
    if (p === k + '-block-start') { R.common = [[k + '-top', v]]; return { ...R, kind: 'a' }; }
    if (p === k + '-block-end') { R.common = [[k + '-bottom', v]]; return { ...R, kind: 'a' }; }
  }
  if ((m = p.match(/^border-inline(-start|-end)?(-width|-color|-style)?$/))) {
    const sfx = m[2] || '', both = !m[1];
    if (both) { R.common = [['border-left' + sfx, v], ['border-right' + sfx, v]]; }
    else if (m[1] === '-start') { R.ltr = [['border-left' + sfx, v]]; R.rtl = [['border-right' + sfx, v]]; }
    else { R.ltr = [['border-right' + sfx, v]]; R.rtl = [['border-left' + sfx, v]]; }
    return { ...R, kind: 'a' };
  }
  if ((m = p.match(/^border-block(-start|-end)?(-width|-color|-style)?$/))) {
    const sfx = m[2] || ''; R.common = m[1] === '-start' ? [['border-top' + sfx, v]] : m[1] === '-end' ? [['border-bottom' + sfx, v]] : [['border-top' + sfx, v], ['border-bottom' + sfx, v]]; return { ...R, kind: 'a' };
  }
  if ((m = p.match(/^border-(start|end)-(start|end)-radius$/))) {
    const k = m[1] + m[2];   // block-side then inline-side
    const L = { startstart: 'top-left', startend: 'top-right', endstart: 'bottom-left', endend: 'bottom-right' }, Rt = { startstart: 'top-right', startend: 'top-left', endstart: 'bottom-right', endend: 'bottom-left' };
    R.ltr = [['border-' + L[k] + '-radius', v]]; R.rtl = [['border-' + Rt[k] + '-radius', v]]; return { ...R, kind: 'b' };
  }
  return null;
}

/* put a direction in front of a selector: html[dir=ltr] .a   /   html[dir=ltr].bare .a (when the selector starts with html) */
function dirSel(sel, dir) {
  return sel.split(',').map(s => {
    s = s.trim();
    if (/^html(?![\w-])/.test(s)) return s.replace(/^html/, 'html[dir=' + dir + ']');
    if (/^:root(?![\w-])/.test(s)) return s.replace(/^:root/, ':root[dir=' + dir + ']');
    return 'html[dir=' + dir + '] ' + s;
  }).join(',');
}
const COND = { a: '(padding-inline:1px)', b: '(border-start-start-radius:1px)' };

const root = postcss.parse(css), out = postcss.root();
/* which family of properties a declaration belongs to (a later rule that sets the same family overrides the fallback) */
function family(p) {
  p = p.toLowerCase();
  if (p.startsWith('padding')) return 'padding';
  if (p.startsWith('margin')) return 'margin';
  if (/^(top|right|bottom|left|inset)/.test(p)) return 'pos';
  if (/radius$/.test(p)) return 'radius';
  if (/^border(-(top|bottom|left|right|inline|block))?(-(width|color|style))?$/.test(p)) return 'border';
  return null;
}
/* later rules (same selector) that set a plain physical property of the same family: in a modern browser they win over an earlier
   logical declaration, so they are repeated right after the fallback (with the same direction prefix) to win here too. */
const flat = []; (function f(node) { node.each(c => { if (c.type === 'rule') flat.push(c); else if (c.type === 'atrule' && c.nodes && /^(media|supports|layer)$/.test(c.name)) f(c); }); })(root);
function laterOverrides(rule, fam) {
  const res = [], idx = flat.indexOf(rule);
  for (let i = idx + 1; i < flat.length; i++) {
    const r = flat[i]; if (r.selector !== rule.selector) continue;
    const ds = []; r.each(d => { if (d.type === 'decl' && !expand(d) && family(d.prop) === fam) ds.push(d); });
    if (ds.length) res.push({ rule: r, decls: ds });
  }
  return res;
}
function emit(rule, target) {
  if (rule.parent && rule.parent.type === 'atrule' && /keyframes|font-face/.test(rule.parent.name)) return;
  const groups = { a: { common: [], ltr: [], rtl: [] }, b: { common: [], ltr: [], rtl: [] } };
  rule.each(n => {
    if (n.type !== 'decl') return;
    const e = expand(n); if (!e) return;
    const imp = n.important ? ' !important' : '';
    for (const k of ['common', 'ltr', 'rtl']) e[k].forEach(([p, v]) => groups[e.kind][k].push([p, v + imp]));
  });
  for (const kind of ['a', 'b']) {
    const g = groups[kind]; if (!g.common.length && !g.ltr.length && !g.rtl.length) continue;
    const sup = postcss.atRule({ name: 'supports', params: 'not ' + COND[kind] });
    const mk = (sel, list) => { if (!list.length) return; const r = postcss.rule({ selector: sel }); list.forEach(([p, v]) => r.append(postcss.decl({ prop: p, value: v.replace(/\s*!important$/, ''), important: / !important$/.test(v) }))); sup.append(r); };
    mk(rule.selector, g.common); mk(dirSel(rule.selector, 'ltr'), g.ltr); mk(dirSel(rule.selector, 'rtl'), g.rtl);
    target.append(sup);
    // repeat later plain overrides of the same property families
    const fams = new Set(); for (const k of ['common', 'ltr', 'rtl']) g[k].forEach(([p]) => { const f = family(p); if (f) fams.add(f); });
    const done = new Set();
    for (const f of fams) for (const o of laterOverrides(rule, f)) {
      const key = flat.indexOf(o.rule) + ':' + f; if (done.has(key)) continue; done.add(key);
      const dirs = (g.ltr.length || g.rtl.length) ? [dirSel(rule.selector, 'ltr'), dirSel(rule.selector, 'rtl')] : [rule.selector];
      for (const sel of dirs) {
        const r = postcss.rule({ selector: sel }); o.decls.forEach(d => r.append(d.clone({ raws: {} })));
        let cur = r, par = o.rule.parent;
        while (par && par.type === 'atrule') { const w = postcss.atRule({ name: par.name, params: par.params }); w.append(cur); cur = w; par = par.parent; }
        const s2 = postcss.atRule({ name: 'supports', params: 'not ' + COND[kind] }); s2.append(cur); target.append(s2);
      }
    }
  }
}
function walk(node, target) {
  node.each(n => {
    if (n.type === 'rule') emit(n, target);
    else if (n.type === 'atrule' && /^(media|supports|layer)$/.test(n.name) && n.nodes) {
      const wrap = postcss.atRule({ name: n.name, params: n.params }); walk(n, wrap); if (wrap.nodes && wrap.nodes.length) target.append(wrap);
    }
  });
}
walk(root, out);
process.stdout.write('/* Generated by deploy/legacy-css.cjs from ' + path.basename(file) + '. Do not edit: run the script again after changing the styles.\n   Only browsers that lack padding-inline / inset / logical radii (before about 2021) use anything in here. */\n' + out.toString().replace(/\n\s*\n/g, '\n') + '\n');
