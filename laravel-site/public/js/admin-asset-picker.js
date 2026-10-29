document.addEventListener("DOMContentLoaded", () => {
    const picker = document.querySelector("[data-asset-picker]");
    if (!picker) return;

    const dialog = picker.querySelector(".admin-asset-picker-dialog");
    const grid = picker.querySelector("[data-asset-picker-grid]");
    const search = picker.querySelector("[data-asset-picker-search]");
    const status = picker.querySelector("[data-asset-picker-status]");
    const empty = picker.querySelector("[data-asset-picker-empty]");
    const folderLinks = [...picker.querySelectorAll("[data-asset-folder]")];
    const kindButtons = [...picker.querySelectorAll("[data-asset-kind-filter]")];
    const breadcrumb = picker.querySelector("[data-asset-picker-breadcrumb]");
    const countLabel = picker.querySelector("[data-asset-picker-count]");
    const selectionLabel = picker.querySelector("[data-asset-picker-selection]");
    const confirmButton = picker.querySelector("[data-asset-picker-confirm]");
    let target = null;
    let trigger = null;
    let kindFilter = "all";
    let activeFolder = "";
    let selectedItem = null;

    const items = () => [...grid.querySelectorAll("[data-asset-select]")];
    const close = () => {
        picker.hidden = true;
        selectedItem = null;
        trigger?.focus();
    };
    const setSelectedItem = (item) => {
        selectedItem = item;
        items().forEach((asset) => {
            const selected = asset === item;
            asset.classList.toggle("is-selected", selected);
            asset.setAttribute("aria-pressed", selected ? "true" : "false");
        });

        if (selectionLabel) {
            selectionLabel.textContent = item ? `Selected: ${item.dataset.assetName}` : "No media selected";
        }
        confirmButton.disabled = !item;
    };
    const filterItems = () => {
        const term = (search.value || "").trim().toLowerCase();
        let visible = 0;
        let folderItems = 0;

        items().forEach((item) => {
            const inFolder = activeFolder === "" || item.dataset.assetFolder === activeFolder;
            const matchesKind = kindFilter === "all" || item.dataset.assetKind === kindFilter;
            const matchesSearch = !term
                || item.dataset.assetName.toLowerCase().includes(term)
                || item.dataset.assetPath.toLowerCase().includes(term);
            const show = inFolder && matchesKind && matchesSearch;
            item.hidden = !show;
            if (inFolder && matchesKind) folderItems += 1;
            if (show) visible += 1;
        });

        empty.hidden = visible > 0;
        empty.textContent = folderItems > 0 ? "No media matches your search or filter." : "No media files in this folder.";
        countLabel.textContent = `${visible} ${visible === 1 ? "item" : "items"}${term ? " found" : " in this folder"}`;
    };
    const choose = () => {
        if (!target || !selectedItem) return;
        const kind = selectedItem.dataset.assetKind;
        const path = selectedItem.dataset.assetPath;
        target.value = kind === "video" ? `video:${path}` : path;
        target.dispatchEvent(new Event("input", { bubbles: true }));
        target.dispatchEvent(new Event("change", { bubbles: true }));
        close();
    };
    const open = (button) => {
        trigger = button;
        const selector = button.dataset.assetPickerTarget;
        target = selector
            ? document.querySelector(selector)
            : button.closest(".admin-repeat-row")?.querySelector('input[name="gallery[]"]');
        if (!target) return;

        kindFilter = button.dataset.assetPickerKind || "all";
        activeFolder = "";
        search.value = "";
        status.textContent = "";
        const currentValue = target.value.replace(/^video:/, "");
        const currentItem = items().find((item) => item.dataset.assetPath === currentValue);
        setSelectedItem(currentItem && (kindFilter === "all" || currentItem.dataset.assetKind === kindFilter) ? currentItem : null);
        folderLinks.forEach((link) => {
            link.classList.toggle("is-active", link.dataset.assetFolder === "");
        });
        kindButtons.forEach((filterButton) => {
            const visible = kindFilter === "all" || filterButton.dataset.assetKindFilter === kindFilter;
            filterButton.hidden = !visible;
            filterButton.classList.toggle("is-active", filterButton.dataset.assetKindFilter === kindFilter);
        });
        picker.querySelector("[data-asset-picker-description]").textContent = kindFilter === "image"
            ? "Browse folders and select an image for this item."
            : "Browse folders and select media for this item.";
        breadcrumb.textContent = "All media";
        filterItems();
        picker.hidden = false;
        dialog.focus();
    };

    document.addEventListener("click", (event) => {
        const button = event.target.closest("[data-asset-picker-trigger]");
        if (button) {
            open(button);
            return;
        }

        const folderLink = event.target.closest("[data-asset-folder]");
        if (folderLink && picker.contains(folderLink)) {
            event.preventDefault();
            activeFolder = folderLink.dataset.assetFolder;
            folderLinks.forEach((link) => link.classList.toggle("is-active", link === folderLink));
            breadcrumb.textContent = activeFolder || "All media";
            setSelectedItem(null);
            filterItems();
            return;
        }

        const kindButton = event.target.closest("[data-asset-kind-filter]");
        if (kindButton && picker.contains(kindButton)) {
            kindFilter = kindButton.dataset.assetKindFilter;
            kindButtons.forEach((filter) => filter.classList.toggle("is-active", filter === kindButton));
            if (selectedItem && kindFilter !== "all" && selectedItem.dataset.assetKind !== kindFilter) {
                setSelectedItem(null);
            }
            filterItems();
            return;
        }

        const item = event.target.closest("[data-asset-select]");
        if (item && picker.contains(item)) {
            setSelectedItem(item);
            return;
        }

        if (event.target.closest("[data-asset-picker-confirm]")) {
            choose();
            return;
        }

        if (event.target.closest("[data-asset-picker-close]") || event.target === picker) close();
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !picker.hidden) close();
    });
    search.addEventListener("input", filterItems);
});
