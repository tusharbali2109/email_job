const CACHE_NAME = 'reachout-v1';
const urlsToCache = [
  '/email_job/bulk-mailer/',
  '/email_job/bulk-mailer/index.php',
  '/email_job/bulk-mailer/send.php',
  '/email_job/bulk-mailer/profile.php',
  '/email_job/bulk-mailer/template.php',
  '/email_job/bulk-mailer/manifest.json'
];

// Install event - cache essential files
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(urlsToCache))
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if(cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - Network first, fallback to cache
self.addEventListener('fetch', event => {
  // Skip cross-origin requests
  if(!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  // For navigation requests, use network-first strategy
  if(event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Cache successful responses
          if(response.ok) {
            const cache = caches.open(CACHE_NAME);
            cache.then(c => c.put(event.request, response.clone()));
          }
          return response;
        })
        .catch(() => {
          // Fall back to cache if offline
          return caches.match(event.request)
            .then(response => response || new Response('Offline - Page not cached'));
        })
    );
  } else {
    // For other requests (API, assets), use cache-first strategy
    event.respondWith(
      caches.match(event.request)
        .then(response => response || fetch(event.request)
          .then(response => {
            if(!response || response.status !== 200 || response.type === 'error') {
              return response;
            }
            // Clone and cache the response
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, responseToCache);
            });
            return response;
          })
        )
        .catch(() => new Response('Offline'))
    );
  }
});

// Handle background sync for email sending
self.addEventListener('sync', event => {
  if(event.tag === 'sync-emails') {
    event.waitUntil(
      // Attempt to sync emails when connection is restored
      fetch('/email_job/bulk-mailer/get_pending.php')
        .catch(() => console.log('Background sync failed'))
    );
  }
});

// Handle push notifications
self.addEventListener('push', event => {
  const data = event.data ? event.data.json() : {};
  const options = {
    body: data.body || 'ReachOut notification',
    icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192"><rect fill="%234fffb0" width="192" height="192" rx="45"/><text x="50%" y="50%" font-size="100" fill="%230c0f14" text-anchor="middle" dy=".35em" font-family="Arial" font-weight="bold">📧</text></svg>',
    badge: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96"><rect fill="%234fffb0" width="96" height="96"/><text x="50%" y="50%" font-size="48" fill="%230c0f14" text-anchor="middle" dy=".35em">📧</text></svg>',
    tag: 'reachout-notification',
    requireInteraction: false
  };

  event.waitUntil(self.registration.showNotification('ReachOut', options));
});

// Handle notification click
self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({type: 'window'}).then(clientList => {
      for(let i = 0; i < clientList.length; i++) {
        const client = clientList[i];
        if(client.url === '/email_job/bulk-mailer/index.php' && 'focus' in client) {
          return client.focus();
        }
      }
      if(clients.openWindow) {
        return clients.openWindow('/email_job/bulk-mailer/index.php');
      }
    })
  );
});
/* ================================
   ReachOut / UserPortal Service Worker
   ================================ */

const CACHE_NAME = 'portal-cache-v1';

const URLS_TO_CACHE = [
  '/email_job/bulk-mailer/',
  '/email_job/bulk-mailer/index.php',
  '/email_job/bulk-mailer/send.php',
  '/email_job/bulk-mailer/profile.php',
  '/email_job/bulk-mailer/template.php',
  '/email_job/bulk-mailer/manifest.json'
];

/* ================= INSTALL ================= */
self.addEventListener('install', event => {

  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(URLS_TO_CACHE);
      })
  );

  self.skipWaiting();

});


/* ================= ACTIVATE ================= */
self.addEventListener('activate', event => {

  event.waitUntil(

    caches.keys().then(cacheNames => {

      return Promise.all(

        cacheNames.map(cacheName => {

          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }

        })

      );

    })

  );

  self.clients.claim();

});


/* ================= FETCH ================= */
self.addEventListener('fetch', event => {

  const request = event.request;

  /* Ignore non-GET requests */
  if (request.method !== 'GET') return;

  /* Ignore cross-origin requests */
  if (!request.url.startsWith(self.location.origin)) return;

  /* Navigation requests (pages) → network first */
  if (request.mode === 'navigate') {

    event.respondWith(

      fetch(request)
        .then(response => {

          if (response && response.status === 200) {

            const clone = response.clone();

            caches.open(CACHE_NAME)
              .then(cache => cache.put(request, clone));

          }

          return response;

        })
        .catch(() => {

          return caches.match(request)
            .then(resp => resp || new Response('Offline - Page not cached'));

        })

    );

  }

  /* Assets → cache first */
  else {

    event.respondWith(

      caches.match(request)
        .then(cached => {

          if (cached) return cached;

          return fetch(request)
            .then(response => {

              if (!response || response.status !== 200) {
                return response;
              }

              const clone = response.clone();

              caches.open(CACHE_NAME)
                .then(cache => cache.put(request, clone));

              return response;

            });

        })
        .catch(() => new Response('Offline'))

    );

  }

});


/* ================= BACKGROUND SYNC ================= */
self.addEventListener('sync', event => {

  if (event.tag === 'sync-emails') {

    event.waitUntil(

      fetch('/email_job/bulk-mailer/get_pending.php')
        .catch(() => console.log('Background sync failed'))

    );

  }

});


/* ================= PUSH NOTIFICATION ================= */
self.addEventListener('push', event => {

  let data = {};

  if (event.data) {
    data = event.data.json();
  }

  const options = {

    body: data.body || 'ReachOut notification',

    icon:
      'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192"><rect fill="%234fffb0" width="192" height="192" rx="45"/><text x="50%" y="50%" font-size="100" fill="%230c0f14" text-anchor="middle" dy=".35em">📧</text></svg>',

    badge:
      'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96"><rect fill="%234fffb0" width="96" height="96"/><text x="50%" y="50%" font-size="48" fill="%230c0f14" text-anchor="middle" dy=".35em">📧</text></svg>',

    tag: 'reachout-notification',
    requireInteraction: false

  };

  event.waitUntil(

    self.registration.showNotification('ReachOut', options)

  );

});


/* ================= NOTIFICATION CLICK ================= */
self.addEventListener('notificationclick', event => {

  event.notification.close();

  event.waitUntil(

    clients.matchAll({ type: 'window' })
      .then(clientList => {

        for (let client of clientList) {

          if (
            client.url.includes('/email_job/bulk-mailer/index.php') &&
            'focus' in client
          ) {
            return client.focus();
          }

        }

        if (clients.openWindow) {
          return clients.openWindow('/email_job/bulk-mailer/index.php');
        }

      })

  );

});