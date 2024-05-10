self.addEventListener("push", function (e) {
    var notification = e.data.json();
    e.waitUntil(
        self.registration.showNotification(
            notification.title || "",
            notification
        )
    );
});

self.addEventListener("notificationclose", function (e) {
    var notification = e.notification;
});

self.addEventListener("notificationclick", function (e) {
    e.notification.close();
    var notification = e.notification;
    var actions = notification.data.actions;
    var redirect_url = "/";
    if (actions) {
        if (e.action && actions[e.action]) {
            redirect_url = actions[e.action];
        } else if (actions["default"]) {
            redirect_url = actions["default"];
        }
    } else {
        redirect_url = "/";
    }
    clients.openWindow(redirect_url);
});
