const CACHE_NAME = "mysantriqu-shell-v1";
const APP_ROOT = new URL(self.registration.scope);
const APP_PATH = APP_ROOT.pathname;
const APP_SHELL = [
  new URL("index.html", APP_ROOT).href,
  new URL("manifest.webmanifest", APP_ROOT).href,
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(APP_SHELL))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((key) => key.startsWith("mysantriqu-shell-") && key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);

  if (request.method !== "GET" || url.origin !== APP_ROOT.origin || !url.pathname.startsWith(APP_PATH)) {
    return;
  }

  if (url.pathname.startsWith(`${APP_PATH}api/`)) {
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request).catch(async () => {
        const cachedHome = await caches.match(new URL("index.html", APP_ROOT).href);
        return cachedHome || new Response("Koneksi internet diperlukan untuk membuka aplikasi.", {
          status: 503,
          headers: { "Content-Type": "text/plain; charset=utf-8" },
        });
      })
    );
    return;
  }

  if (!["script", "style", "image", "font"].includes(request.destination)) {
    return;
  }

  event.respondWith(
    fetch(request).then((response) => {
      if (response.ok && response.type === "basic") {
        event.waitUntil(
          caches.open(CACHE_NAME)
            .then((cache) => cache.put(request, response.clone()))
            .catch((error) => console.error("Aset aplikasi tidak dapat disimpan:", error))
        );
      }
      return response;
    }).catch(async () => {
      const cached = await caches.match(request);
      if (cached) return cached;
      return new Response("", { status: 504, statusText: "Offline" });
    })
  );
});
