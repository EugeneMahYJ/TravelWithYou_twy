document.addEventListener("DOMContentLoaded", () => {
    const modal = document.querySelector("#idle-modal");
    const countdown = document.querySelector("#idle-countdown");
    const stayButton = document.querySelector(".admin-stay");
    const logoutForm = document.querySelector("#admin-logout-form");
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const activityUrl = document.body.dataset.activityUrl;
    const loginUrl = document.body.dataset.loginUrl;
    const idleLoginUrl = document.body.dataset.idleLoginUrl;
    const warningAfter = 14 * 60 * 1000;
    const timeoutAfter = 15 * 60 * 1000;
    let lastActivity = Date.now();
    let lastPing = 0;
    let warningOpen = false;
    let logoutStarted = false;
    const sidebar = document.querySelector("#admin-sidebar");
    const overlay = document.querySelector("#admin-drawer-overlay");
    const menuButton = document.querySelector(".admin-menu-button");
    const mobileQuery = window.matchMedia("(max-width: 1023px)");
    let drawerOpen = false;
    const notificationToggle = document.querySelector(".admin-notifications-toggle");
    const notificationPanel = document.querySelector("#admin-notifications-panel");
    const notificationFocusable = () => [...(notificationPanel?.querySelectorAll('a[href], button:not([disabled])') ?? [])];
    let notificationsOpen = false;
    const notificationHeading = document.querySelector("#admin-notification-panel-heading");
    const notificationBadge = () => notificationToggle?.querySelector(".admin-notification-badge");

    const redirectToLogin = (idle = false) => {
        window.location.assign(idle ? idleLoginUrl : loginUrl);
    };
    const logoutAdmin = async (idle = false, form = logoutForm) => {
        if (logoutStarted) return;
        logoutStarted = true;
        if (form) {
            try {
                await fetch(form.action, {
                    method: "POST",
                    body: new FormData(form),
                    credentials: "same-origin",
                    keepalive: true,
                    headers: { Accept: "application/json" },
                });
            } catch {
                redirectToLogin(idle);
                return;
            }
        }
        redirectToLogin(idle);
    };

    const closeNotifications = (restoreFocus = false) => {
        if (!notificationsOpen) return;
        notificationsOpen = false;
        if (notificationPanel) notificationPanel.hidden = true;
        notificationToggle?.setAttribute("aria-expanded", "false");
        if (restoreFocus) notificationToggle?.focus();
    };
    const openNotifications = () => {
        if (!notificationPanel || !notificationToggle) return;
        notificationsOpen = true;
        notificationPanel.hidden = false;
        notificationToggle.setAttribute("aria-expanded", "true");
        (notificationFocusable()[0] ?? notificationPanel).focus();
    };
    notificationToggle?.addEventListener("click", () => {
        if (notificationsOpen) closeNotifications();
        else openNotifications();
    });
    document.addEventListener("click", (event) => {
        if (notificationsOpen && event.target instanceof Element && !event.target.closest(".admin-notifications")) closeNotifications();
    });
    window.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && notificationsOpen) {
            closeNotifications(true);
            return;
        }
        if (event.key !== "Tab" || !notificationsOpen) return;
        const focusable = notificationFocusable();
        if (!focusable.length) {
            event.preventDefault();
            notificationPanel?.focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    notificationPanel?.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => closeNotifications()));
    const showNotificationError = () => {
        let error = document.querySelector("#admin-notification-error");
        if (!error) {
            error = document.createElement("div");
            error.id = "admin-notification-error";
            error.className = "notice notice-error admin-error";
            error.setAttribute("role", "alert");
            document.querySelector(".admin-content")?.prepend(error);
        }
        if (error) error.textContent = "Could not update notifications. Please try again.";
    };
    const renderNotificationRows = (items, unreadBadge) => {
        if (!notificationPanel) return;
        const itemStates = new Map(items.map((item) => [item.key, item.is_read]));
        const rows = [...notificationPanel.querySelectorAll("[data-notification-row]")];
        rows.forEach((row) => {
            if (!itemStates.has(row.dataset.key)) {
                row.remove();
                return;
            }
            const isRead = itemStates.get(row.dataset.key) === true;
            row.dataset.read = String(isRead);
            row.classList.toggle("is-read", isRead);
            row.classList.toggle("is-unread", !isRead);
            const unreadLabel = row.querySelector("[data-unread-label]");
            if (unreadLabel) unreadLabel.hidden = isRead;
            const action = row.querySelector("[data-notification-action]");
            if (!action) return;
            if (isRead) {
                action.innerHTML = '<span class="admin-notification-read-label">Read</span>';
            } else if (!action.querySelector("[data-notification-read]")) {
                const form = document.createElement("form");
                form.method = "post";
                form.action = row.dataset.readUrl;
                form.dataset.notificationRead = "";
                const token = document.createElement("input");
                token.type = "hidden";
                token.name = "_token";
                token.value = csrf || "";
                const fingerprint = document.createElement("input");
                fingerprint.type = "hidden";
                fingerprint.name = "fingerprint";
                fingerprint.value = row.dataset.fingerprint;
                const button = document.createElement("button");
                button.type = "submit";
                button.className = "admin-notification-action";
                button.setAttribute("aria-label", `Mark as read: ${row.dataset.title}`);
                button.textContent = "Mark as read";
                form.append(token, fingerprint, button);
                action.replaceChildren(form);
            }
            const readButton = action.querySelector("[data-notification-read] button");
            if (readButton) readButton.disabled = false;
        });
        const rowsContainer = notificationPanel.querySelector(".admin-notification-rows");
        const sortedRows = [...notificationPanel.querySelectorAll("[data-notification-row]")].sort((a, b) =>
            Number(a.dataset.read === "true") - Number(b.dataset.read === "true")
            || Number(a.dataset.order) - Number(b.dataset.order));
        sortedRows.forEach((row) => rowsContainer?.append(row));
        if (rowsContainer && !sortedRows.length && !rowsContainer.querySelector(".admin-notification-empty")) {
            const empty = document.createElement("p");
            empty.className = "admin-notification-empty";
            empty.textContent = "You're all caught up.";
            rowsContainer.append(empty);
        }
        const hasUnread = items.some((item) => item.is_read === false);
        const unreadItemCount = items.filter((item) => item.is_read === false).length;
        const unreadAttention = [...notificationPanel.querySelectorAll("[data-notification-row][data-read='false']")]
            .filter((row) => ["critical", "warning"].includes(row.dataset.severity))
            .reduce((total, row) => total + Number(row.dataset.count || 0), 0);
        const allButton = notificationPanel.querySelector("[data-notifications-read-all] button");
        if (allButton) allButton.disabled = !hasUnread;
        if (notificationToggle) {
            notificationToggle.setAttribute("aria-label", unreadBadge
                ? `Notifications, ${unreadBadge} need attention`
                : (unreadItemCount ? `Notifications, ${unreadItemCount} unread` : "Notifications, none unread"));
            let badge = notificationBadge();
            if (unreadBadge && !badge) {
                badge = document.createElement("span");
                badge.className = "admin-notification-badge";
                badge.setAttribute("aria-hidden", "true");
                notificationToggle.append(badge);
            }
            if (badge) {
                if (unreadBadge) badge.textContent = unreadBadge > 99 ? "99+" : String(unreadBadge);
                else badge.remove();
            }
        }
        const summary = notificationPanel.querySelector("[data-notification-summary]");
        if (summary) summary.textContent = unreadAttention
            ? `${unreadAttention} need attention`
            : (hasUnread ? "Unread notifications" : "No unread notifications");
    };
    const submitNotificationRead = async (form, markAll) => {
        const button = form.querySelector('button[type="submit"]');
        const currentOrder = Number(form.closest("[data-notification-row]")?.dataset.order);
        if (button) button.disabled = true;
        let payload;
        if (markAll) {
            const items = new Map();
            new FormData(form).forEach((value, name) => {
                const match = name.match(/^items\[(\d+)\]\[(key|fingerprint)\]$/);
                if (!match) return;
                const index = match[1];
                items.set(index, { ...items.get(index), [match[2]]: value });
            });
            payload = { items: [...items.values()] };
        } else {
            payload = { fingerprint: new FormData(form).get("fingerprint") };
        }
        try {
            const response = await fetch(form.action, {
                method: "POST",
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrf || "",
                },
                body: JSON.stringify(payload),
            });
            if (response.status === 401 || response.status === 419
                || (response.redirected && new URL(response.url).pathname === new URL(loginUrl, window.location.href).pathname)) {
                redirectToLogin();
                return;
            }
            if (!response.ok) throw new Error("Notification update failed.");
            const result = await response.json();
            if (!Array.isArray(result.items) || !Number.isInteger(result.unread_badge)) {
                throw new Error("Invalid notification response.");
            }
            renderNotificationRows(result.items, result.unread_badge);
            const announcement = notificationPanel?.querySelector("[data-notification-announcement]");
            if (announcement) announcement.textContent = markAll ? "All notifications marked as read." : "Marked as read.";
            if (markAll) {
                notificationHeading?.focus();
            } else {
                const buttons = [...(notificationPanel?.querySelectorAll("[data-notification-read] button:not([disabled])") ?? [])];
                const nextButton = buttons.find((candidate) =>
                    Number(candidate.closest("[data-notification-row]")?.dataset.order) > currentOrder) ?? buttons[0];
                (nextButton ?? notificationHeading)?.focus();
            }
        } catch {
            if (button?.isConnected) button.disabled = false;
            showNotificationError();
        }
    };
    notificationPanel?.addEventListener("submit", (event) => {
        const form = event.target instanceof HTMLFormElement ? event.target : null;
        if (!form || (!form.matches("[data-notification-read]") && !form.matches("[data-notifications-read-all]"))) return;
        event.preventDefault();
        submitNotificationRead(form, form.matches("[data-notifications-read-all]"));
    });

    document.querySelectorAll("[data-admin-logout-form]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            event.preventDefault();
            logoutAdmin(false, form);
        });
    });
    logoutForm?.addEventListener("submit", (event) => {
        event.preventDefault();
        logoutAdmin();
    });
    document.querySelectorAll("[data-admin-code]").forEach((input) => {
        input.addEventListener("input", () => {
            input.value = input.value.replace(/\D/g, "").slice(0, 6);
        });
    });
    document.querySelectorAll("[data-admin-code-form]").forEach((form) => {
        form.addEventListener("submit", () => {
            const button = form.querySelector('button[type="submit"]');
            if (!button) return;
            button.disabled = true;
            button.textContent = button.dataset.adminSubmittingLabel || "Verifying…";
        });
    });
    document.querySelectorAll("[data-character-count]").forEach((input) => {
        const counter = input.parentElement.querySelector("[data-character-count-value]");
        if (!counter) return;
        const updateCount = () => {
            counter.textContent = String(Array.from(input.value).length);
        };
        input.addEventListener("input", updateCount);
        updateCount();
    });
    document.querySelectorAll("[data-copy-secret]").forEach((button) => {
        button.addEventListener("click", () => {
            const status = button.parentElement.querySelector("[data-copy-status]");
            if (!navigator.clipboard?.writeText) {
                if (status) status.textContent = "Copy unavailable. Select and copy the key.";
                return;
            }
            navigator.clipboard.writeText(button.dataset.copySecret).then(() => {
                if (status) status.textContent = "Copied.";
            }).catch(() => {
                if (status) status.textContent = "Copy failed. Select and copy the key.";
            });
        });
    });

    const drawerFocusable = () => [...(sidebar?.querySelectorAll('a[href], button:not([disabled])') ?? [])];
    const closeDrawer = (restoreFocus = true) => {
        if (!drawerOpen) return;
        drawerOpen = false;
        sidebar?.classList.remove("is-open");
        if (overlay) overlay.hidden = true;
        menuButton?.setAttribute("aria-expanded", "false");
        menuButton?.setAttribute("aria-label", "Open navigation");
        if (restoreFocus) menuButton?.focus();
    };
    const openDrawer = () => {
        drawerOpen = true;
        sidebar?.classList.add("is-open");
        if (overlay) overlay.hidden = false;
        menuButton?.setAttribute("aria-expanded", "true");
        menuButton?.setAttribute("aria-label", "Close navigation");
        drawerFocusable()[0]?.focus();
    };

    menuButton?.addEventListener("click", () => drawerOpen ? closeDrawer() : openDrawer());
    overlay?.addEventListener("click", () => closeDrawer());
    sidebar?.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => closeDrawer(false)));
    window.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && drawerOpen) {
            closeDrawer();
            return;
        }
        if (event.key !== "Tab" || !drawerOpen) return;
        const focusable = drawerFocusable();
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    mobileQuery.addEventListener("change", (event) => {
        if (!event.matches) closeDrawer(false);
    });

    const touch = () => {
        if (!warningOpen) lastActivity = Date.now();
    };
    ["pointerdown", "keydown", "scroll", "touchstart"].forEach((eventName) => {
        window.addEventListener(eventName, touch, { passive: true });
    });

    stayButton?.addEventListener("click", async () => {
        warningOpen = false;
        lastActivity = Date.now();
        if (modal) modal.hidden = true;
        if (activityUrl && csrf) {
            try {
                const response = await fetch(activityUrl, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" },
                });
                if (!response.ok) logoutAdmin();
            } catch {
                logoutAdmin();
            }
        }
    });

    if (!modal || !logoutForm) return;
    window.setInterval(() => {
        const idleFor = Date.now() - lastActivity;
        const secondsLeft = Math.max(0, Math.ceil((timeoutAfter - idleFor) / 1000));
        if (idleFor >= timeoutAfter) {
            logoutAdmin(true);
            return;
        }
        if (idleFor >= warningAfter) {
            warningOpen = true;
            modal.hidden = false;
            if (countdown) countdown.textContent = String(secondsLeft);
        }
        if (idleFor < 15000 && Date.now() - lastPing > 30000 && activityUrl && csrf) {
            lastPing = Date.now();
            fetch(activityUrl, {
                method: "POST",
                credentials: "same-origin",
                headers: { "X-CSRF-TOKEN": csrf, Accept: "application/json" },
            }).then((response) => {
                if (!response.ok) logoutAdmin();
            }).catch(() => logoutAdmin());
        }
    }, 1000);
});
