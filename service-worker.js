"use strict";

self.addEventListener("push", function (event) {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch (error) {
    payload = { body: event.data ? event.data.text() : "" };
  }
  const title = payload.title || "Pembaruan Kalender Akademik";
  const options = {
    body: payload.body || "Ada informasi terbaru pada kalender akademik.",
    icon: "assets/app-icon-192.png",
    badge: "assets/app-icon-192.png",
    data: { url: payload.url || "kalender-akademik.html" },
    tag: "academic-calendar-update",
    renotify: true,
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", function (event) {
  event.notification.close();
  const targetUrl = new URL(event.notification.data.url || "kalender-akademik.html", self.registration.scope).href;
  event.waitUntil(
    clients.matchAll({ type: "window", includeUncontrolled: true }).then(function (windows) {
      const existing = windows.find(function (client) {
        return client.url === targetUrl && "focus" in client;
      });
      return existing ? existing.focus() : clients.openWindow(targetUrl);
    })
  );
});
