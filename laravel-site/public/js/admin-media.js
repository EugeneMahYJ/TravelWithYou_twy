document.addEventListener("DOMContentLoaded", () => {
    const grid = document.querySelector("[data-media-grid]");
    if (!grid) return;

    const uploadForm = document.querySelector("[data-media-upload]");
    const fileInput = document.querySelector("[data-media-file]");
    const status = document.querySelector("[data-media-status]");
    const progress = document.querySelector("[data-media-progress]");
    const progressBar = progress?.querySelector("span");
    const search = document.querySelector("[data-media-search]");
    const filters = [...document.querySelectorAll("[data-media-filter]")];
    const emptyFilter = document.querySelector("[data-media-empty-filter]");
    const modal = document.querySelector("[data-media-modal]");
    const modalDialog = modal?.querySelector(".admin-media-modal-dialog");
    const modalBody = document.querySelector("[data-media-modal-body]");
    const modalTitle = document.querySelector("[data-media-modal-title]");
    const modalKind = document.querySelector("[data-media-modal-kind]");
    const modalPath = document.querySelector("[data-media-modal-path]");
    const modalCopy = document.querySelector("[data-media-modal-copy]");
    const template = document.querySelector("[data-media-card-template]");
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let activeFilter = "all";
    let lastFocused = null;

    const cards = () => [...grid.querySelectorAll(".admin-media-card")];

    const applyFilters = () => {
        const term = (search?.value || "").trim().toLowerCase();
        let visible = 0;

        cards().forEach((card) => {
            const kindOk = activeFilter === "all" || card.dataset.mediaKind === activeFilter;
            const termOk = term === "" || card.dataset.mediaName.includes(term) || card.dataset.mediaPath.toLowerCase().includes(term);
            const show = kindOk && termOk;
            card.hidden = !show;
            if (show) visible += 1;
        });

        if (emptyFilter) emptyFilter.hidden = visible !== 0 || cards().length === 0;
    };

    filters.forEach((button) => {
        button.addEventListener("click", () => {
            activeFilter = button.dataset.mediaFilter;
            filters.forEach((item) => item.classList.toggle("is-active", item === button));
            applyFilters();
        });
    });
    search?.addEventListener("input", applyFilters);

    const copyText = async (text, trigger) => {
        if (!text) return;

        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const input = document.createElement("textarea");
            input.value = text;
            input.style.position = "fixed";
            input.style.opacity = "0";
            document.body.appendChild(input);
            input.select();
            try {
                document.execCommand("copy");
            } catch {
                // Clipboard access is unavailable.
            }
            input.remove();
        }

        if (trigger) {
            const original = trigger.textContent;
            trigger.textContent = "Copied";
            window.setTimeout(() => { trigger.textContent = original; }, 1200);
        }
    };

    document.addEventListener("click", (event) => {
        const copy = event.target.closest("[data-copy-asset]");
        if (!copy) return;
        event.preventDefault();
        copyText(copy.dataset.copyAsset || "", copy);
        copy.closest("details")?.removeAttribute("open");
    });

    document.addEventListener("click", (event) => {
        const activeMenu = event.target.closest(".admin-media-folder-create, .admin-media-folder-options, .admin-media-file-options");
        document.querySelectorAll(".admin-media-folder-create[open], .admin-media-folder-options[open], .admin-media-file-options[open]")
            .forEach((menu) => {
                if (menu !== activeMenu && !menu.contains(event.target)) menu.removeAttribute("open");
            });
    });

    const closeModal = () => {
        if (!modal) return;
        modal.hidden = true;
        modalBody?.querySelectorAll("video").forEach((video) => video.pause());
        if (modalBody) modalBody.innerHTML = "";
        lastFocused?.focus();
    };

    const openPreview = (button) => {
        if (!modal || !modalBody) return;

        lastFocused = button;
        const type = button.dataset.mediaType;
        const url = button.dataset.mediaUrl;
        const name = button.dataset.mediaName;
        const path = button.dataset.mediaPath;

        modalTitle.textContent = name;
        modalKind.textContent = type === "video" ? "Video" : "Image";
        modalPath.textContent = path;
        modalCopy.dataset.copyAsset = path;
        modalBody.innerHTML = "";

        if (type === "video") {
            const video = document.createElement("video");
            video.src = url;
            video.controls = true;
            video.autoplay = true;
            video.muted = true;
            video.playsInline = true;
            modalBody.appendChild(video);
        } else {
            const image = document.createElement("img");
            image.src = url;
            image.alt = name;
            modalBody.appendChild(image);
        }

        modal.hidden = false;
        modalDialog?.focus();
    };

    grid.addEventListener("click", (event) => {
        const preview = event.target.closest("[data-media-preview]");
        if (preview) openPreview(preview);
    });
    modal?.addEventListener("click", (event) => { if (event.target === modal) closeModal(); });
    modal?.querySelector("[data-media-close]")?.addEventListener("click", closeModal);
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && modal && !modal.hidden) closeModal();
    });

    const setStatus = (text) => { if (status) status.textContent = text; };
    const setProgress = (percent) => {
        if (!progress || !progressBar) return;
        progress.hidden = percent === null;
        progressBar.style.width = percent === null ? "0" : `${percent}%`;
    };

    const refreshCounts = (asset) => {
        const count = document.querySelector("[data-media-count]");
        const images = document.querySelector("[data-media-image-count]");
        const videos = document.querySelector("[data-media-video-count]");
        const bytes = document.querySelector("[data-media-total-bytes]");
        const bytesLabel = document.querySelector("[data-media-bytes]");
        const allTab = document.querySelector('[data-media-filter="all"] span');
        const imageTab = document.querySelector('[data-media-filter="image"] span');
        const videoTab = document.querySelector('[data-media-filter="video"] span');

        if (count) count.textContent = String(cards().length);
        if (allTab) allTab.textContent = String(Number(allTab.textContent) + 1);
        if (asset.kind === "image") {
            if (images) images.textContent = String(Number(images.textContent) + 1);
            if (imageTab) imageTab.textContent = String(Number(imageTab.textContent) + 1);
        }
        if (asset.kind === "video") {
            if (videos) videos.textContent = String(Number(videos.textContent) + 1);
            if (videoTab) videoTab.textContent = String(Number(videoTab.textContent) + 1);
        }
        if (bytes && bytesLabel) {
            const total = Number(bytes.dataset.mediaTotalBytes || bytes.textContent || 0) + Number(asset.size || 0);
            bytes.dataset.mediaTotalBytes = String(total);
            bytesLabel.textContent = (total / 1024 / 1024).toFixed(2);
        }
    };

    const removeCounts = (card) => {
        const count = document.querySelector("[data-media-count]");
        const images = document.querySelector("[data-media-image-count]");
        const videos = document.querySelector("[data-media-video-count]");
        const bytes = document.querySelector("[data-media-total-bytes]");
        const bytesLabel = document.querySelector("[data-media-bytes]");
        const allTab = document.querySelector('[data-media-filter="all"] span');
        const imageTab = document.querySelector('[data-media-filter="image"] span');
        const videoTab = document.querySelector('[data-media-filter="video"] span');
        const kind = card.dataset.mediaKind;
        const size = Number(card.dataset.mediaSize || 0);

        if (count) count.textContent = String(cards().length - 1);
        if (allTab) allTab.textContent = String(Math.max(0, Number(allTab.textContent) - 1));
        if (kind === "image") {
            if (images) images.textContent = String(Math.max(0, Number(images.textContent) - 1));
            if (imageTab) imageTab.textContent = String(Math.max(0, Number(imageTab.textContent) - 1));
        }
        if (kind === "video") {
            if (videos) videos.textContent = String(Math.max(0, Number(videos.textContent) - 1));
            if (videoTab) videoTab.textContent = String(Math.max(0, Number(videoTab.textContent) - 1));
        }
        if (bytes && bytesLabel) {
            const total = Math.max(0, Number(bytes.dataset.mediaTotalBytes || 0) - size);
            bytes.dataset.mediaTotalBytes = String(total);
            bytesLabel.textContent = (total / 1024 / 1024).toFixed(2);
        }

        if (cards().length === 1) {
            const empty = document.createElement("p");
            empty.className = "muted admin-media-empty";
            empty.textContent = "No media files yet. Upload an image or video to get started.";
            grid.appendChild(empty);
        }
    };

    const buildCard = (asset) => {
        if (!template) return null;

        const card = template.content.firstElementChild.cloneNode(true);
        card.dataset.mediaKind = asset.kind;
        card.dataset.mediaName = asset.name.toLowerCase();
        card.dataset.mediaPath = asset.path;
        card.dataset.mediaSize = String(asset.size);

        const preview = card.querySelector("[data-media-preview]");
        preview.dataset.mediaType = asset.kind;
        preview.dataset.mediaUrl = asset.url;
        preview.dataset.mediaName = asset.name;
        preview.dataset.mediaPath = asset.path;
        preview.setAttribute("aria-label", "Preview " + asset.name);

        const video = card.querySelector("video");
        const image = card.querySelector("img");
        const play = card.querySelector(".admin-media-play");
        const kind = card.querySelector(".admin-media-kind");
        kind.textContent = asset.kind;

        if (asset.kind === "video") {
            video.hidden = false;
            video.src = asset.url;
            play.hidden = false;
        } else {
            image.hidden = false;
            image.src = asset.url;
            image.alt = "";
        }

        const name = card.querySelector("strong");
        name.textContent = asset.name;
        name.title = asset.name;
        card.querySelector("small").textContent = Math.round(asset.size / 1024) + " KB";
        card.querySelector("[data-copy-asset]").dataset.copyAsset = asset.path;
        card.querySelector("a").href = asset.url;
        card.querySelector('[data-media-delete] input[name="path"]').value = asset.path;
        card.querySelector('[data-media-delete] button').setAttribute("aria-label", "Delete " + asset.name);
        card.querySelector('[data-media-rename] input[name="path"]').value = asset.path;
        card.querySelector('[data-media-rename-trigger]').setAttribute("aria-label", "Rename " + asset.name);
        const moveForm = card.querySelector(".admin-media-move-form");
        moveForm.querySelector('input[name="path"]').value = asset.path;
        const assetFolder = asset.path.split("/").slice(0, -1).join("/");
        moveForm.querySelector('select[name="folder"]').value = assetFolder;

        return card;
    };

    const updateCardAsset = (card, asset) => {
        const previousPath = card.dataset.mediaPath;
        card.dataset.mediaName = asset.name.toLowerCase();
        card.dataset.mediaPath = asset.path;
        card.dataset.mediaSize = String(asset.size);

        const preview = card.querySelector("[data-media-preview]");
        preview.dataset.mediaUrl = asset.url;
        preview.dataset.mediaName = asset.name;
        preview.dataset.mediaPath = asset.path;
        preview.setAttribute("aria-label", "Preview " + asset.name);

        const media = card.querySelector("img, video");
        if (media) media.src = asset.url;

        const name = card.querySelector("strong");
        name.textContent = asset.name;
        name.title = asset.name;
        card.querySelector("[data-copy-asset]").dataset.copyAsset = asset.path;
        card.querySelector("a").href = asset.url;
        card.querySelector('[data-media-delete] input[name="path"]').value = asset.path;
        card.querySelector('[data-media-delete] button').setAttribute("aria-label", "Delete " + asset.name);
        card.querySelector('[data-media-rename] input[name="path"]').value = asset.path;
        card.querySelector('[data-media-rename-trigger]').setAttribute("aria-label", "Rename " + asset.name);

        if (modal && !modal.hidden && modalPath.textContent === previousPath) {
            modalTitle.textContent = asset.name;
            modalPath.textContent = asset.path;
            modalCopy.dataset.copyAsset = asset.path;
            const modalMedia = modalBody.querySelector("img, video");
            if (modalMedia) modalMedia.src = asset.url;
        }
    };

    grid.addEventListener("click", async (event) => {
        const trigger = event.target.closest("[data-media-rename-trigger]");
        if (!trigger) return;

        const form = trigger.closest("[data-media-rename]");
        const card = trigger.closest(".admin-media-card");
        trigger.closest("details")?.removeAttribute("open");
        const pathInput = form?.querySelector('input[name="path"]');
        const path = pathInput?.value || "";
        const currentName = card?.querySelector("strong")?.textContent || "";
        const name = window.prompt("Enter the new file name (keep the current extension):", currentName);
        if (name === null || name.trim() === "" || !form || !card) return;

        trigger.disabled = true;
        try {
            const formData = new FormData(form);
            formData.set("name", name.trim());
            const response = await fetch(form.action, {
                method: "POST",
                headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                body: formData,
            });
            const payload = await response.json();

            if (!response.ok || !payload.asset) {
                throw new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || "Unable to rename this media file.");
            }

            updateCardAsset(card, payload.asset);
            setStatus(`${currentName} renamed to ${payload.asset.name}`);
            applyFilters();
        } catch (error) {
            setStatus(error instanceof Error ? error.message : "Unable to rename this media file.");
        } finally {
            trigger.disabled = false;
        }
    });

    grid.addEventListener("submit", async (event) => {
        const form = event.target.closest("[data-media-delete]");
        if (!form) return;
        event.preventDefault();

        const path = form.querySelector('input[name="path"]')?.value || "";
        if (!window.confirm(`Delete ${path}? This cannot be undone.`)) return;
        form.closest("details")?.removeAttribute("open");

        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: "POST",
                headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                body: new FormData(form),
            });
            const payload = await response.json();

            if (!response.ok) throw new Error(payload.message || "Unable to delete this media file.");

            const card = form.closest(".admin-media-card");
            if (card) {
                removeCounts(card);
                card.remove();
            }
            setStatus(`${path.split("/").pop()} deleted`);
            applyFilters();
        } catch (error) {
            setStatus(error instanceof Error ? error.message : "Unable to delete this media file.");
            if (button) button.disabled = false;
        }
    });

    const uploadFile = (file) => {
        if (!uploadForm || !file || !csrf) return;

        const formData = new FormData();
        formData.append("file", file);
        formData.append("_token", csrf);
        const uploadFolder = uploadForm.querySelector('input[name="folder"]')?.value || "uploads";
        formData.append("folder", uploadFolder);

        const xhr = new XMLHttpRequest();
        xhr.open("POST", uploadForm.action);
        xhr.setRequestHeader("Accept", "application/json");
        xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

        xhr.upload.addEventListener("progress", (event) => {
            if (!event.lengthComputable) return;
            const percent = Math.round((event.loaded / event.total) * 100);
            setStatus(`Uploading ${file.name}… ${percent}%`);
            setProgress(percent);
        });

        xhr.addEventListener("load", () => {
            let payload = {};
            try {
                payload = JSON.parse(xhr.responseText);
            } catch {
                payload = {};
            }

            if (xhr.status >= 200 && xhr.status < 300 && payload.asset) {
                const card = buildCard(payload.asset);
                if (card) {
                    grid.querySelector(".admin-media-empty")?.remove();
                    grid.prepend(card);
                    refreshCounts(payload.asset);
                    applyFilters();
                }
                setStatus(`${payload.asset.name} uploaded`);
                setProgress(null);
                fileInput.value = "";
            } else {
                const message = payload.message
                    || (payload.errors ? Object.values(payload.errors).flat()[0] : null)
                    || "Upload failed.";
                setStatus(message);
                setProgress(null);
            }
        });

        xhr.addEventListener("error", () => {
            setStatus("Upload failed. Check your connection.");
            setProgress(null);
        });

        xhr.send(formData);
    };

    uploadForm?.addEventListener("submit", (event) => event.preventDefault());
    fileInput?.addEventListener("change", () => {
        if (fileInput.files?.[0]) uploadFile(fileInput.files[0]);
    });

    setProgress(null);
});
