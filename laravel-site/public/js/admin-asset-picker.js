document.addEventListener("DOMContentLoaded", () => {
    const picker = document.querySelector("[data-asset-picker]");
    if (!picker) return;

    const dialog = picker.querySelector(".admin-asset-picker-dialog");
    const grid = picker.querySelector("[data-asset-picker-grid]");
    const search = picker.querySelector("[data-asset-picker-search]");
    const upload = picker.querySelector("[data-asset-picker-upload]");
    const status = picker.querySelector("[data-asset-picker-status]");
    const empty = picker.querySelector("[data-asset-picker-empty]");
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let target = null;
    let trigger = null;
    let kindFilter = "all";

    const items = () => [...grid.querySelectorAll("[data-asset-select]")];
    const close = () => {
        picker.hidden = true;
        trigger?.focus();
    };
    const filterItems = () => {
        const term = (search.value || "").trim().toLowerCase();
        let visible = 0;
        items().forEach((item) => {
            const matchesKind = kindFilter === "all" || item.dataset.assetKind === kindFilter;
            const matchesSearch = !term
                || item.dataset.assetName.toLowerCase().includes(term)
                || item.dataset.assetPath.toLowerCase().includes(term);
            item.hidden = !(matchesKind && matchesSearch);
            if (!item.hidden) visible += 1;
        });
        empty.hidden = items().length > 0 && visible > 0;
        if (items().length > 0 && visible === 0) empty.textContent = "No assets match your search.";
        else empty.textContent = "No media files yet. Upload an image or video to get started.";
    };
    const choose = (asset) => {
        if (!target) return;
        target.value = asset.kind === "video" ? `video:${asset.path}` : asset.path;
        target.dispatchEvent(new Event("input", { bubbles: true }));
        target.dispatchEvent(new Event("change", { bubbles: true }));
        close();
    };
    const createItem = (asset) => {
        const item = document.createElement("button");
        item.className = "admin-asset-picker-item";
        item.type = "button";
        item.dataset.assetSelect = "";
        item.dataset.assetKind = asset.kind;
        item.dataset.assetName = asset.name;
        item.dataset.assetPath = asset.path;
        item.dataset.assetUrl = asset.url;

        const preview = document.createElement("span");
        preview.className = "admin-asset-picker-preview";
        if (asset.kind === "video") {
            const video = document.createElement("video");
            video.src = asset.url;
            video.muted = true;
            video.playsInline = true;
            video.preload = "metadata";
            preview.append(video);
            const play = document.createElement("span");
            play.setAttribute("aria-hidden", "true");
            play.textContent = "▶";
            preview.append(play);
        } else {
            const image = document.createElement("img");
            image.src = asset.url;
            image.alt = "";
            image.loading = "lazy";
            preview.append(image);
        }

        const name = document.createElement("strong");
        name.textContent = asset.name;
        const details = document.createElement("small");
        details.textContent = `${asset.kind} · ${Math.round(asset.size / 1024)} KB`;
        item.append(preview, name, details);

        return item;
    };
    const uploadAsset = (file) => {
        if (!file) return;
        if (!csrf) {
            status.textContent = "Upload could not start because the security token is missing. Refresh the page and try again.";
            return;
        }

        const formData = new FormData();
        formData.append("file", file);
        formData.append("_token", csrf);
        const xhr = new XMLHttpRequest();
        xhr.open("POST", picker.dataset.uploadUrl);
        xhr.setRequestHeader("Accept", "application/json");
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        status.textContent = `Uploading ${file.name}…`;
        upload.disabled = true;

        xhr.addEventListener("load", () => {
            let payload;
            try {
                payload = JSON.parse(xhr.responseText);
            } catch {
                payload = null;
            }

            if (xhr.status >= 200 && xhr.status < 300 && payload?.asset) {
                const item = createItem(payload.asset);
                grid.prepend(item);
                empty.textContent = "No assets match your search.";
                filterItems();
                status.textContent = `${payload.asset.name} uploaded to public/assets/uploads.`;
                upload.value = "";
                choose(payload.asset);
                return;
            }

            status.textContent = payload?.message
                || (payload?.errors ? Object.values(payload.errors).flat()[0] : null)
                || "Upload failed. Please try again.";
        });
        xhr.addEventListener("error", () => {
            status.textContent = "Upload failed. Check your connection and try again.";
        });
        xhr.addEventListener("loadend", () => { upload.disabled = false; });
        xhr.send(formData);
    };

    document.addEventListener("click", (event) => {
        const button = event.target.closest("[data-asset-picker-trigger]");
        if (button) {
            trigger = button;
            const selector = button.dataset.assetPickerTarget;
            target = selector
                ? document.querySelector(selector)
                : button.closest(".admin-repeat-row")?.querySelector('input[name="gallery[]"]');
            if (!target) return;
            kindFilter = button.dataset.assetPickerKind || "all";
            search.value = "";
            status.textContent = "";
            filterItems();
            picker.hidden = false;
            dialog.focus();
            return;
        }

        const item = event.target.closest("[data-asset-select]");
        if (item && picker.contains(item)) {
            choose({
                kind: item.dataset.assetKind,
                path: item.dataset.assetPath,
            });
            return;
        }

        if (event.target.closest("[data-asset-picker-close]") || event.target === picker) close();
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && !picker.hidden) close();
    });
    search.addEventListener("input", filterItems);
    upload.addEventListener("change", () => uploadAsset(upload.files?.[0]));
});
