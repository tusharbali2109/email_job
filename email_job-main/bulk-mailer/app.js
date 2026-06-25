/* ============================================================
   ReachOut — Shared App Utilities
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
  el.innerHTML = `<span class="toast-icon">${icons[type] || '✅'}</span><span class="toast-text">${msg}</span>`;
  toastContainer.appendChild(el);

  setTimeout(() => {
    el.classList.add('out');
    el.addEventListener('animationend', () => el.remove());
  }, duration);

  return el;
}

/* ── HTML Escape ─────────────────────────────────────────── */
export function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ── Setup Bottom Navigation ─────────────────────────────── */
export function setupNav(activeHref) {
  // Highlight active bottom nav item
  document.querySelectorAll('.bn-item').forEach(el => {
    const href = el.getAttribute('href') || el.dataset.href;
    if (href && href.includes(activeHref)) el.classList.add('active');
  });
}

/* ── Setup Sidebar User ──────────────────────────────────── */
export function setupSidebarUser(session) {
  const u = session.user;
  const name = u.user_metadata?.name || u.email.split('@')[0];

  const el = (id) => document.getElementById(id);
  const av = el('sidebar-avatar'); if (av) av.textContent = name[0].toUpperCase();
  const nm = el('sidebar-name');   if (nm) nm.textContent = name;
  const em = el('sidebar-email');  if (em) em.textContent = u.email;

  const logoutBtn = el('sidebar-logout') || el('logout-btn');
  if (logoutBtn) logoutBtn.addEventListener('click', async () => {
    await sb.auth.signOut();
    window.location.href = 'login.html';
  });
}

/* ── Setup Page ──────────────────────────────────────────── */
export async function setupPage(activeHref) {
  const session = await requireAuth();
  if (!session) return null;
  setupSidebarUser(session);
  setupNav(activeHref);

  // Register SW
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  }

  return session;
}
