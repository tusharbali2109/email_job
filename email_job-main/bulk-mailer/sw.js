/* ============================================================
   ReachOut — Service Worker v3
   Network-first for pages, cache-first for assets
   ============================================================ */

const CACHE   = 'reachout-v3';
const OFFLINE  = '/index.html';

const PRECACHE = [
  '/index.html',
  '/send.html',
  '/jobs.html',
  '/whatsapp.html',
  '/whatsapp_logs.html',
  '/template.html',
  '/profile.html',
  '/login.html',
  '/app.css',
  '/app.js',
  '/manifest.json',
];

/* ── Install ─────────────────────────────────────────────── */
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE)
      .then(cache => cache.addAll(PRECACHE).catch(() => {}))
      .then(() => self.skipWaiting())
  );
});

/* ── Activate ────────────────────────────────────────────── */
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

/* ── Fetch ───────────────────────────────────────────────── */
self.addEventListener('fetch', event => {
  const { request } = event;

  // Skip non-GET, cross-origin, Supabase API, and WA service calls
  if (request.method !== 'GET') return;
  if (!request.url.startsWith(self.location.origin)) return;
  if (request.url.includes('supabase.co')) return;
  if (request.url.includes('localhost:3001')) return;
  if (request.url.includes('fonts.googleapis.com')) return;
  if (request.url.includes('fonts.gstatic.com')) return;
  if (request.url.includes('cdn.jsdelivr.net')) return;

  // HTML pages → network-first
  if (request.mode === 'navigate' || request.headers.get('accept')?.includes('text/html')) {
    event.respondWith(
      fetch(request)
        .then(response => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(CACHE).then(c => c.put(request, clone));
          }
          return response;
        })
        .catch(() => caches.match(request).then(r => r || caches.match(OFFLINE)))
    );
    return;
  }

  // CSS / JS / other assets → cache-first
  event.respondWith(
    caches.match(request).then(cached => {
      if (cached) return cached;
      return fetch(request).then(response => {
        if (!response || response.status !== 200) return response;
        const clone = response.clone();
        caches.open(CACHE).then(c => c.put(request, clone));
        return response;
      });
    }).catch(() => new Response('Offline', { status: 503 }))
  );
});

/* ── Background Sync ─────────────────────────────────────── */
self.addEventListener('sync', event => {
  if (event.tag === 'sync-emails') {
    event.waitUntil(
      fetch('/get_pending.php').catch(() => {})
    );
  }
});

/* ── Push Notifications ──────────────────────────────────── */
self.addEventListener('push', event => {
  const data = event.data ? event.data.json() : {};
  const options = {
    body:  data.body  || 'ReachOut notification',
    icon:  data.icon  || 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192"><rect fill="%230a0d12" width="192" height="192" rx="48"/><rect fill="%234fffb0" x="24" y="24" width="144" height="144" rx="36"/><text x="96" y="110" font-size="72" fill="%230a0d12" text-anchor="middle" font-family="Arial" font-weight="bold">R</text></svg>',
    badge: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96"><rect fill="%234fffb0" width="96" height="96" rx="22"/><text x="48" y="62" font-size="52" fill="%230a0d12" text-anchor="middle" font-family="Arial" font-weight="bold">R</text></svg>',
    tag:   data.tag   || 'reachout',
    data:  { url: data.url || '/index.html' },
    actions: [
      { action: 'open',    title: 'Open App' },
      { action: 'dismiss', title: 'Dismiss'  },
    ],
    requireInteraction: false,
    vibrate: [100, 50, 100],
  };
  event.waitUntil(self.registration.showNotification(data.title || 'ReachOut', options));
});

/* ── Notification Click ──────────────────────────────────── */
self.addEventListener('notificationclick', event => {
  event.notification.close();
  if (event.action === 'dismiss') return;
  const url = event.notification.data?.url || '/index.html';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      const existing = list.find(c => c.url.includes(url));
      if (existing && 'focus' in existing) return existing.focus();
      return clients.openWindow(url);
    })
  );
});
