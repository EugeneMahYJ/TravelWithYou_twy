document.addEventListener("DOMContentLoaded", () => {
    const tabs = [...document.querySelectorAll("[data-ship-tab]")];
    const panels = [...document.querySelectorAll("[data-ship-panel]")];
    if (!tabs.length || !panels.length) return;

    const activate = (name, updateHistory = true) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.shipTab === name;
            tab.classList.toggle("is-active", active);
            tab.setAttribute("aria-selected", String(active));
            tab.tabIndex = active ? 0 : -1;
        });
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.shipPanel !== name;
        });
        if (updateHistory) {
            const url = new URL(window.location.href);
            url.searchParams.set("tab", name);
            window.history.pushState({}, "", url);
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener("click", (event) => {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
            event.preventDefault();
            activate(tab.dataset.shipTab);
        });
        tab.addEventListener("keydown", (event) => {
            let next = index;
            if (event.key === "ArrowRight") next = (index + 1) % tabs.length;
            else if (event.key === "ArrowLeft") next = (index - 1 + tabs.length) % tabs.length;
            else if (event.key === "Home") next = 0;
            else if (event.key === "End") next = tabs.length - 1;
            else return;
            event.preventDefault();
            tabs[next].focus();
            activate(tabs[next].dataset.shipTab);
        });
    });

    window.addEventListener("popstate", () => {
        const name = new URL(window.location.href).searchParams.get("tab") || "Overview";
        if (tabs.some((tab) => tab.dataset.shipTab === name)) activate(name, false);
    });
});
