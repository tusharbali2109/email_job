/* ============================================================
   ReachOut — Shared App Utilities v2
   ============================================================ */

import { createClient } from 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/+esm';

export const sb = createClient(
  'https://wcsckcyxbixcgjrrjoyo.supabase.co',
  'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Indjc2NrY3l4Yml4Y2dqcnJqb3lvIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODIyNzA4MTgsImV4cCI6MjA5Nzg0NjgxOH0.YQ_iaVv0HwE1jdQFPN1uYXeqan36TsIs7AY11CPmjTM'
);

/* ── Auth Guard ──────────────────────────────────────────── */
export async function requireAuth() {
  const { data: { session } } = await sb.auth.getSession();
  if (!session) { window.location.href = 'login.html'; return null; }
  return session;
}

/* ── Toast System ────────────────────────────────────────── */
let toastContainer = null;

export function toast(msg, type = 'success', duration = 3500) {
  if (!toastContainer) {
    toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
      toastContainer = document.createElement('div');
      toastContainer.id = 'toast-container';
      document.body.appendChild(toastContainer);
    }
  }
  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️', progress: '⏳' };
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span class="toast-icon">${icons[type] || 'ℹ️'}</span><span class="toast-text">${msg}</span>`;
  toastContainer.appendChild(el);
  setTimeout(() => {
    el.classList.add('out');
    el.addEventListener('animationend', () => el.remove(), { once: true });
  }, duration);
  return el;
}

/* ── HTML Escape ─────────────────────────────────────────── */
export function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ── Theme ───────────────────────────────────────────────── */
export function applyTheme() {
  const root = document.documentElement;
  const saved = JSON.parse(localStorage.getItem('reachout_theme') || '{}');
  const map = {
    accent: '--accent', accent2: '--accent2', bg: '--bg',
    surface: '--surface', surface2: '--surface2',
  };
  for (const [k, v] of Object.entries(map)) {
    if (saved[k]) root.style.setProperty(v, saved[k]);
  }
}

export function saveTheme(key, value) {
  const saved = JSON.parse(localStorage.getItem('reachout_theme') || '{}');
  saved[key] = value;
  localStorage.setItem('reachout_theme', JSON.stringify(saved));
  document.documentElement.style.setProperty('--' + key, value);
}

export function resetTheme() {
  localStorage.removeItem('reachout_theme');
  location.reload();
}

/* ── Sidebar User ────────────────────────────────────────── */
export function setupSidebarUser(session) {
  const u    = session.user;
  const name = u.user_metadata?.name || u.email.split('@')[0];
  const $ = id => document.getElementById(id);
  const set = (id, val) => { const el = $(id); if (el) el.textContent = val; };
  set('sidebar-avatar', name[0].toUpperCase());
  set('sidebar-name',   name);
  set('sidebar-email',  u.email);
  // Mobile header avatar
  set('header-avatar', name[0].toUpperCase());

  const logoutFn = async () => { await sb.auth.signOut(); window.location.href = 'login.html'; };
  document.querySelectorAll('.logout-btn, #logout-btn, #sidebar-logout').forEach(b => {
    b?.addEventListener('click', logoutFn);
  });
}

/* ── Bottom Nav Active ───────────────────────────────────── */
export function setupNav(activeHref) {
  document.querySelectorAll('.bn-item').forEach(el => {
    const href = el.getAttribute('href') || el.dataset.href || '';
    if (href && (href === activeHref || href.endsWith(activeHref))) {
      el.classList.add('active');
    }
  });
}

/* ── Sidebar Toggle (desktop) ────────────────────────────── */
export function setupSidebarToggle() {
  const body   = document.body;
  const toggle = document.getElementById('sidebar-toggle');
  const mini   = localStorage.getItem('sidebar_mini') === '1';
  if (mini) body.classList.add('sidebar-mini');

  toggle?.addEventListener('click', () => {
    body.classList.toggle('sidebar-mini');
    localStorage.setItem('sidebar_mini', body.classList.contains('sidebar-mini') ? '1' : '0');
    // Update toggle icon
    if (toggle) toggle.textContent = body.classList.contains('sidebar-mini') ? '›' : '‹';
  });
  if (toggle) toggle.textContent = mini ? '›' : '‹';
}

/* ── Command Palette ─────────────────────────────────────── */
const CMD_ITEMS = [
  { icon:'📋', label:'Dashboard',        href:'index.html',         keys:['d'] },
  { icon:'📤', label:'Send Emails',       href:'send.html',          keys:['e'] },
  { icon:'💬', label:'WhatsApp',          href:'whatsapp.html',      keys:['w'] },
  { icon:'🔍', label:'Job Hunt',          href:'jobs.html',          keys:['j'] },
  { icon:'📊', label:'Pipeline',          href:'pipeline.html',      keys:['p'] },
  { icon:'🤖', label:'AI Tailor',         href:'ai_tailor.html',     keys:['a'] },
  { icon:'🦾', label:'Auto Apply Agent',  href:'agent.html',         keys:[] },
  { icon:'🔁', label:'Follow-ups',        href:'followup.html',      keys:[] },
  { icon:'✏️', label:'Templates',         href:'template.html',      keys:['t'] },
  { icon:'🚫', label:'Blacklist',         href:'blacklist.html',     keys:[] },
  { icon:'👤', label:'Profile',           href:'profile.html',       keys:[] },
  { icon:'🎨', label:'Settings',          href:'settings.html',      keys:['s'] },
];

export function setupCommandPalette() {
  const overlay = document.getElementById('cmd-overlay');
  if (!overlay) return;

  const input   = overlay.querySelector('.cmd-input');
  const results = overlay.querySelector('.cmd-results');
  let sel = 0;

  const open  = () => { overlay.classList.add('open'); input.value = ''; render(''); input.focus(); sel = 0; };
  const close = () => overlay.classList.remove('open');

  document.addEventListener('keydown', e => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); overlay.classList.contains('open') ? close() : open(); return; }
    if (!overlay.classList.contains('open')) return;
    if (e.key === 'Escape') { close(); return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, results.querySelectorAll('.cmd-item').length - 1); highlight(); }
    if (e.key === 'ArrowUp')   { e.preventDefault(); sel = Math.max(sel - 1, 0); highlight(); }
    if (e.key === 'Enter') {
      const items = results.querySelectorAll('.cmd-item');
      if (items[sel]) { window.location.href = items[sel].dataset.href; close(); }
    }
  });

  overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
  input?.addEventListener('input', () => { sel = 0; render(input.value); });

  document.querySelectorAll('[data-cmd]').forEach(el => el.addEventListener('click', open));

  function render(q) {
    const filtered = CMD_ITEMS.filter(i =>
      !q || i.label.toLowerCase().includes(q.toLowerCase())
    );
    if (!filtered.length) {
      results.innerHTML = '<div style="text-align:center;padding:24px;color:var(--muted);font-size:13px;">No results</div>';
      return;
    }
    results.innerHTML = `<div class="cmd-section-title">Navigation</div>` +
      filtered.map((item, i) => `
        <div class="cmd-item${i === sel ? ' selected' : ''}" data-href="${item.href}" onclick="location.href='${item.href}'">
          <span class="cmd-item-icon">${item.icon}</span>
          <span>${esc(item.label)}</span>
          ${item.keys.length ? `<div class="cmd-item-shortcut"><kbd>G</kbd><kbd>${item.keys[0].toUpperCase()}</kbd></div>` : ''}
        </div>`).join('');
  }

  function highlight() {
    results.querySelectorAll('.cmd-item').forEach((el, i) => {
      el.classList.toggle('selected', i === sel);
      if (i === sel) el.scrollIntoView({ block: 'nearest' });
    });
  }
}

/* ── G + key shortcuts ───────────────────────────────────── */
function setupGotoShortcuts() {
  let gPressed = false, gTimer;
  document.addEventListener('keydown', e => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'g') {
      gPressed = true;
      clearTimeout(gTimer);
      gTimer = setTimeout(() => { gPressed = false; }, 1000);
      return;
    }
    if (!gPressed) return;
    const item = CMD_ITEMS.find(i => i.keys.includes(e.key));
    if (item) { gPressed = false; window.location.href = item.href; }
  });
}

/* ── Setup Page ──────────────────────────────────────────── */
export async function setupPage(activeHref) {
  applyTheme();
  const session = await requireAuth();
  if (!session) return null;
  setupSidebarUser(session);
  setupNav(activeHref);
  setupSidebarToggle();
  setupCommandPalette();
  setupGotoShortcuts();
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
  return session;
}
