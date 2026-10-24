document.addEventListener("DOMContentLoaded", () => {
    const menuButton = document.querySelector(".menu-toggle");
    const navigation = document.querySelector("#mobile-nav");

    if (menuButton && navigation) {
        menuButton.addEventListener("click", () => {
            const open = menuButton.getAttribute("aria-expanded") === "true";
            menuButton.setAttribute("aria-expanded", String(!open));
            menuButton.setAttribute("aria-label", open ? "Open navigation" : "Close navigation");
            navigation.hidden = open;
        });
        navigation.addEventListener("click", (event) => {
            if (!(event.target instanceof Element) || !event.target.closest("a")) return;
            navigation.hidden = true;
            menuButton.setAttribute("aria-expanded", "false");
            menuButton.setAttribute("aria-label", "Open navigation");
        });
    }

    const toast = document.querySelector("[data-toast]");
    if (toast) {
        const dismissToast = () => {
            if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
                toast.remove();
                return;
            }
            toast.classList.add("is-dismissing");
            window.setTimeout(() => toast.remove(), 220);
        };
        toast.querySelector("[data-toast-close]")?.addEventListener("click", dismissToast);
        if (toast.dataset.autoDismiss === "true") window.setTimeout(dismissToast, 2600);
    }

    document.querySelectorAll("[data-social-link]").forEach((link) => {
        link.addEventListener("click", (event) => event.preventDefault());
    });

    const showWishlistToast = (message, isError = false) => {
        document.querySelector("[data-toast]")?.remove();
        const toast = document.createElement("div");
        toast.className = "toast";
        toast.dataset.toast = "";
        toast.dataset.autoDismiss = String(!isError);
        toast.setAttribute("role", isError ? "alert" : "status");
        toast.setAttribute("aria-atomic", "true");
        const text = document.createElement("span");
        text.textContent = message;
        toast.append(text);
        if (isError) {
            const close = document.createElement("button");
            close.type = "button";
            close.setAttribute("aria-label", "Close message");
            close.textContent = "×";
            close.addEventListener("click", () => dismissToast(toast));
            toast.append(close);
        } else {
            window.setTimeout(() => dismissToast(toast), 2600);
        }
        document.body.append(toast);
    };

    const dismissToast = (toast) => {
        if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            toast.remove();
            return;
        }
        toast.classList.add("is-dismissing");
        window.setTimeout(() => toast.remove(), 220);
    };

    const updateWishlistButton = (button, saved) => {
        button.setAttribute("aria-pressed", String(saved));
        const textLabel = saved ? button.dataset.savedLabel : button.dataset.unsavedLabel;
        const ariaLabel = saved
            ? button.dataset.savedAriaLabel || textLabel
            : button.dataset.unsavedAriaLabel || textLabel;
        if (ariaLabel) button.setAttribute("aria-label", ariaLabel);
        if (textLabel) {
            const textNode = [...button.childNodes].find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
            if (textNode) textNode.textContent = ` ${textLabel}`;
        }
    };

    document.querySelectorAll(".wishlist-form, [data-wishlist-form]").forEach((form) => {
        form.addEventListener("submit", async (event) => {
            event.preventDefault();
            const button = form.querySelector("button[type=submit]");
            if (!button || button.disabled) return;
            const savedBeforeRequest = button.getAttribute("aria-pressed") === "true";
            button.disabled = true;
            updateWishlistButton(button, !savedBeforeRequest);
            try {
                const response = await fetch(form.action, {
                    method: "POST",
                    body: new FormData(form),
                    headers: { Accept: "application/json" },
                    credentials: "same-origin",
                });
                if (!response.ok) throw new Error("The wishlist could not be updated. Please try again.");
                const result = await response.json();
                if (typeof result.saved !== "boolean" || typeof result.message !== "string") {
                    throw new Error("The wishlist could not be updated. Please try again.");
                }
                updateWishlistButton(button, result.saved);
                showWishlistToast(result.message);
            } catch {
                updateWishlistButton(button, savedBeforeRequest);
                showWishlistToast("The wishlist could not be updated. Please try again.", true);
            } finally {
                button.disabled = false;
            }
        });
    });

    document.querySelectorAll("[data-auto-submit] select").forEach((select) => {
        select.addEventListener("change", () => select.form.requestSubmit());
    });

    const departureSelect = document.querySelector("[data-package-departure]");
    const bookNow = document.querySelector("[data-book-now]");
    if (departureSelect && bookNow) {
        const updateDeparture = () => {
            const option = departureSelect.selectedOptions[0];
            const date = departureSelect.value;
            bookNow.href = `${departureSelect.dataset.bookingBase}/${encodeURIComponent(date)}`;
            const price = document.querySelector("[data-current-price]");
            const original = document.querySelector("[data-original-price]");
            const fareNote = document.querySelector("[data-fare-note]");
            if (price && option?.dataset.price) price.textContent = option.dataset.price;
            if (original && option?.dataset.original) {
                original.textContent = option.dataset.original;
                original.hidden = option.dataset.price === option.dataset.original;
            }
            if (fareNote) {
                fareNote.textContent = option?.dataset.note ?? "";
                fareNote.hidden = !option?.dataset.note;
            }
        };
        departureSelect.addEventListener("change", updateDeparture);
        updateDeparture();
    }

    const pdfModal = document.querySelector("[data-pdf-modal]");
    const pdfDialog = pdfModal?.querySelector('[role="dialog"]');
    const pdfOpener = document.querySelector("[data-pdf-open]");
    if (pdfModal && pdfDialog && pdfOpener) {
        const closeButton = pdfModal.querySelector("[data-pdf-close]");
        const pdfFrame = pdfModal.querySelector("iframe[data-pdf-src]");
        let previousFocus = null;
        const closePdf = () => {
            pdfModal.hidden = true;
            pdfFrame?.removeAttribute("src");
            previousFocus?.focus();
        };
        pdfOpener.addEventListener("click", () => {
            previousFocus = document.activeElement;
            pdfModal.hidden = false;
            if (pdfFrame && !pdfFrame.hasAttribute("src")) pdfFrame.src = pdfFrame.dataset.pdfSrc;
            closeButton?.focus();
        });
        closeButton?.addEventListener("click", closePdf);
        pdfModal.addEventListener("click", (event) => {
            if (event.target === pdfModal) closePdf();
        });
        pdfModal.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                event.preventDefault();
                closePdf();
                return;
            }
            if (event.key !== "Tab") return;
            const focusable = [...pdfDialog.querySelectorAll('a[href], button:not([disabled]), iframe, [tabindex]:not([tabindex="-1"])')]
                .filter((element) => element.getClientRects().length);
            if (!focusable.length) {
                event.preventDefault();
                pdfDialog.focus();
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
    }

    document.querySelectorAll("[data-cruise-filter-trigger]").forEach((trigger) => {
        const form = trigger.closest("form");
        const shipFilter = form?.querySelector("[data-cruise-filter]");
        const shipSelect = shipFilter?.querySelector("select");
        if (!(trigger instanceof HTMLSelectElement) || !shipFilter || !(shipSelect instanceof HTMLSelectElement)) return;

        const updateShipFilter = (clearSelection = false) => {
            const isCruise = trigger.value.toLowerCase().includes("cruise");
            if (clearSelection && !isCruise) shipSelect.value = "";
            const showShipFilter = isCruise || (!clearSelection && shipSelect.value !== "");
            shipFilter.hidden = !showShipFilter;
            shipSelect.disabled = !showShipFilter;
            form.classList.toggle("has-ship-filter", showShipFilter);
        };

        trigger.addEventListener("change", () => updateShipFilter(true));
        updateShipFilter();
    });

    const accountTabs = [...document.querySelectorAll("[data-account-tab]")];
    const accountPanels = [...document.querySelectorAll("[data-account-panel]")];
    if (accountTabs.length && accountPanels.length) {
        const activateAccountTab = (tab, updateHistory = true) => {
            accountTabs.forEach((link) => {
                const active = link.dataset.accountTab === tab;
                link.classList.toggle("is-active", active);
                link.setAttribute("aria-selected", String(active));
                link.tabIndex = active ? 0 : -1;
            });
            accountPanels.forEach((panel) => {
                panel.hidden = panel.dataset.accountPanel !== tab;
            });

            if (updateHistory) {
                const url = new URL(window.location.href);
                url.searchParams.set("tab", tab);
                window.history.pushState({}, "", url);
            }
        };

        accountTabs.forEach((link, index) => {
            link.addEventListener("click", (event) => {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
                event.preventDefault();
                activateAccountTab(link.dataset.accountTab);
            });
            link.addEventListener("keydown", (event) => {
                let nextIndex = index;
                if (event.key === "ArrowRight") nextIndex = (index + 1) % accountTabs.length;
                else if (event.key === "ArrowLeft") nextIndex = (index - 1 + accountTabs.length) % accountTabs.length;
                else if (event.key === "Home") nextIndex = 0;
                else if (event.key === "End") nextIndex = accountTabs.length - 1;
                else return;

                event.preventDefault();
                accountTabs[nextIndex].focus();
                activateAccountTab(accountTabs[nextIndex].dataset.accountTab);
            });
        });

        const initialTab = accountTabs.find((link) => link.getAttribute("aria-selected") === "true")?.dataset.accountTab;
        if (initialTab) activateAccountTab(initialTab, false);

        window.addEventListener("popstate", () => {
            const tab = new URL(window.location.href).searchParams.get("tab") || "profile";
            if (accountTabs.some((link) => link.dataset.accountTab === tab)) {
                activateAccountTab(tab, false);
            }
        });
    }

    const slides = [...document.querySelectorAll(".hero-slide")];
    const dots = [...document.querySelectorAll("[data-slide-to]")];
    if (!slides.length) return;

    let active = Math.max(0, slides.findIndex((slide) => slide.classList.contains("is-active")));
    let timer;
    let paused = false;
    let hovered = false;
    let focused = false;
    const pauseButton = document.querySelector("[data-carousel-pause]");
    const show = (index) => {
        active = (index + slides.length) % slides.length;
        slides.forEach((slide, position) => {
            const selected = position === active;
            slide.classList.toggle("is-active", selected);
            slide.setAttribute("aria-hidden", String(!selected));
            slide.querySelector(".hero-image")?.classList.toggle("kenburns", selected);
            const video = slide.querySelector(".hero-video");
            if (video) {
                if (selected && !paused) {
                    video.play().catch(() => {});
                } else {
                    video.pause();
                }
            }
            const copy = slide.querySelector(".hero-copy");
            if (selected && copy) {
                copy.classList.remove("fade-up");
                void copy.offsetWidth;
                copy.classList.add("fade-up");
            }
        });
        dots.forEach((dot, position) => dot.setAttribute("aria-pressed", String(position === active)));
        restart();
    };
    const restart = () => {
        window.clearTimeout(timer);
        if (slides.length < 2 || paused || hovered || focused || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
        timer = window.setTimeout(() => show(active + 1), 7000);
    };

    document.querySelector(".hero-prev")?.addEventListener("click", () => show(active - 1));
    document.querySelector(".hero-next")?.addEventListener("click", () => show(active + 1));
    dots.forEach((dot) => dot.addEventListener("click", () => {
        show(Number(dot.dataset.slideTo));
    }));
    pauseButton?.addEventListener("click", () => {
        paused = !paused;
        pauseButton.setAttribute("aria-pressed", String(paused));
        pauseButton.setAttribute("aria-label", paused ? "Play slideshow" : "Pause slideshow");
        pauseButton.textContent = paused ? "Play" : "Pause";
        const activeVideo = slides[active]?.querySelector(".hero-video");
        if (activeVideo) {
            if (paused) {
                activeVideo.pause();
            } else {
                activeVideo.play().catch(() => {});
            }
        }
        restart();
    });
    const carousel = document.querySelector("[data-carousel]");
    carousel?.addEventListener("mouseenter", () => { hovered = true; restart(); });
    carousel?.addEventListener("mouseleave", () => { hovered = false; restart(); });
    carousel?.addEventListener("focusin", (event) => {
        focused = event.target instanceof Element && event.target.matches(":focus-visible");
        restart();
    });
    carousel?.addEventListener("focusout", (event) => {
        if (!carousel.contains(event.relatedTarget)) {
            focused = false;
            restart();
        }
    });
    restart();
});
