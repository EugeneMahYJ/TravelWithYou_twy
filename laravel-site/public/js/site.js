document.addEventListener("DOMContentLoaded", () => {
    const clearScrollPathKey = "clear-filters-scroll-path";
    const clearScrollYKey = "clear-filters-scroll-y";
    try {
        const savedPath = window.sessionStorage.getItem(clearScrollPathKey);
        const savedScrollYValue = window.sessionStorage.getItem(clearScrollYKey);
        window.sessionStorage.removeItem(clearScrollPathKey);
        window.sessionStorage.removeItem(clearScrollYKey);
        const savedScrollY = Number(savedScrollYValue);

        if (savedPath === window.location.pathname && savedScrollYValue !== null
            && Number.isFinite(savedScrollY) && savedScrollY >= 0) {
            window.requestAnimationFrame(() => window.scrollTo(0, savedScrollY));
        }
    } catch (error) {
        console.error("Unable to restore the trip page scroll position after clearing filters.", error);
    }

    document.querySelectorAll("[data-preserve-scroll-on-clear]").forEach((link) => {
        link.addEventListener("click", (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey
                || event.shiftKey || event.altKey || (link.target && link.target !== "_self")) return;

            const destination = new URL(link.href, window.location.href);
            if (destination.origin !== window.location.origin) return;

            try {
                window.sessionStorage.setItem(clearScrollPathKey, destination.pathname);
                window.sessionStorage.setItem(clearScrollYKey, String(window.scrollY));
            } catch (error) {
                console.error("Unable to save the trip page scroll position before clearing filters.", error);
            }
        });
    });

    document.querySelectorAll("[data-filter-select]").forEach((wrapper, index) => {
        const select = wrapper.querySelector("select");
        const trigger = wrapper.querySelector("[data-filter-select-trigger]");
        const valueLabel = wrapper.querySelector("[data-filter-select-value]");
        const menu = wrapper.querySelector("[data-filter-select-menu]");
        const listbox = wrapper.querySelector("[data-filter-select-options]");
        if (!(select instanceof HTMLSelectElement) || !(trigger instanceof HTMLButtonElement)
            || !(valueLabel instanceof HTMLElement) || !(menu instanceof HTMLElement)
            || !(listbox instanceof HTMLUListElement)) return;

        listbox.id = `filter-select-options-${index}`;
        trigger.setAttribute("aria-controls", listbox.id);
        const options = [...select.options].map((option, optionIndex) => {
            const item = document.createElement("li");
            item.setAttribute("role", "option");
            item.tabIndex = -1;
            item.textContent = option.textContent;
            item.dataset.value = option.value;
            item.setAttribute("aria-selected", String(option.selected));
            item.addEventListener("click", () => {
                select.selectedIndex = optionIndex;
                select.dispatchEvent(new Event("change", { bubbles: true }));
                closeMenu(true);
            });
            listbox.append(item);
            return item;
        });

        const syncSelection = () => {
            valueLabel.textContent = select.selectedOptions[0]?.textContent ?? "";
            options.forEach((option, optionIndex) => {
                option.setAttribute("aria-selected", String(optionIndex === select.selectedIndex));
            });
        };
        const closeMenu = (restoreFocus = false) => {
            menu.hidden = true;
            wrapper.classList.remove("is-open");
            trigger.setAttribute("aria-expanded", "false");
            if (restoreFocus) trigger.focus();
        };
        const openMenu = () => {
            menu.hidden = false;
            wrapper.classList.add("is-open");
            trigger.setAttribute("aria-expanded", "true");
            (options[select.selectedIndex] ?? options[0])?.focus();
        };
        const moveFocus = (currentIndex, direction) => {
            const nextIndex = (currentIndex + direction + options.length) % options.length;
            options[nextIndex]?.focus();
        };
        let typeAheadBuffer = "";
        let typeAheadTimer;

        trigger.addEventListener("click", () => {
            if (menu.hidden) openMenu();
            else closeMenu();
        });
        trigger.addEventListener("keydown", (event) => {
            if (!["ArrowDown", "ArrowUp", "Enter", " "].includes(event.key)) return;
            event.preventDefault();
            if (menu.hidden) openMenu();
        });
        listbox.addEventListener("keydown", (event) => {
            const currentIndex = options.indexOf(document.activeElement);
            if (event.key === "Escape") {
                event.preventDefault();
                closeMenu(true);
            } else if (event.key === "ArrowDown") {
                event.preventDefault();
                moveFocus(currentIndex, 1);
            } else if (event.key === "ArrowUp") {
                event.preventDefault();
                moveFocus(currentIndex, -1);
            } else if (event.key === "Home" || event.key === "End") {
                event.preventDefault();
                options[event.key === "Home" ? 0 : options.length - 1]?.focus();
            } else if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                const optionIndex = options.indexOf(document.activeElement);
                if (optionIndex >= 0) {
                    select.selectedIndex = optionIndex;
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                    closeMenu(true);
                }
            } else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                event.preventDefault();
                const typedCharacter = event.key.toLocaleLowerCase();
                typeAheadBuffer = typeAheadBuffer !== ""
                    && typedCharacter === typeAheadBuffer[0]
                    && [...typeAheadBuffer].every((character) => character === typeAheadBuffer[0])
                    ? typedCharacter
                    : typeAheadBuffer + typedCharacter;
                window.clearTimeout(typeAheadTimer);
                typeAheadTimer = window.setTimeout(() => {
                    typeAheadBuffer = "";
                }, 700);
                const startIndex = Math.max(currentIndex, select.selectedIndex);
                for (let offset = 1; offset <= options.length; offset += 1) {
                    const optionIndex = (startIndex + offset) % options.length;
                    if (options[optionIndex].textContent.trim().toLocaleLowerCase().startsWith(typeAheadBuffer)) {
                        options[optionIndex].focus();
                        break;
                    }
                }
            }
        });
        select.addEventListener("change", syncSelection);
        select.form?.addEventListener("reset", () => window.setTimeout(syncSelection));
        document.addEventListener("click", (event) => {
            if (!wrapper.contains(event.target)) closeMenu();
        });

        syncSelection();
        select.tabIndex = -1;
        wrapper.classList.add("is-enhanced");
        trigger.hidden = false;
    });

    const mobileSearch = document.querySelector("[data-mobile-search]");
    if (mobileSearch) {
        const searchForm = mobileSearch.querySelector(".search-panel");
        const inlineContainer = mobileSearch.querySelector("[data-mobile-search-inline]");
        const trigger = mobileSearch.querySelector("[data-mobile-search-trigger]");
        const dialog = mobileSearch.querySelector("[data-mobile-search-dialog]");
        const dialogContent = mobileSearch.querySelector("[data-mobile-search-content]");
        const closeButton = mobileSearch.querySelector("[data-mobile-search-close]");
        const mobileViewport = window.matchMedia("(max-width: 760px)");

        if (searchForm && inlineContainer && trigger instanceof HTMLButtonElement
            && dialog instanceof HTMLDialogElement && dialogContent && closeButton instanceof HTMLButtonElement
            && typeof dialog.showModal === "function") {
            const restoreForm = () => {
                inlineContainer.append(searchForm);
                trigger.setAttribute("aria-expanded", "false");
            };
            const closeDialog = () => {
                if (dialog.open) dialog.close();
                restoreForm();
                if (mobileViewport.matches) trigger.focus();
            };
            const syncViewport = () => {
                if (mobileViewport.matches) {
                    mobileSearch.classList.add("is-mobile-enhanced");
                    trigger.hidden = false;
                } else {
                    mobileSearch.classList.remove("is-mobile-enhanced");
                    trigger.hidden = true;
                    if (dialog.open) dialog.close();
                    restoreForm();
                }
            };

            trigger.addEventListener("click", () => {
                dialogContent.append(searchForm);
                dialog.showModal();
                trigger.setAttribute("aria-expanded", "true");
                closeButton.focus();
            });
            closeButton.addEventListener("click", closeDialog);
            dialog.addEventListener("cancel", (event) => {
                event.preventDefault();
                closeDialog();
            });
            dialog.addEventListener("close", () => {
                restoreForm();
                if (mobileViewport.matches) trigger.focus();
            });
            dialog.addEventListener("click", (event) => {
                if (event.target === dialog) closeDialog();
            });
            mobileViewport.addEventListener("change", syncViewport);
            syncViewport();
        }
    }

    document.querySelectorAll("[data-clear-filters-in-place]").forEach((link) => {
        link.addEventListener("click", (event) => {
            event.preventDefault();
            const form = link.closest("form");

            form?.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((field) => {
                if (field instanceof HTMLInputElement && ["checkbox", "radio"].includes(field.type)) {
                    field.checked = false;
                } else if (field instanceof HTMLSelectElement) {
                    field.selectedIndex = 0;
                    if (field.closest("[data-filter-select]")) {
                        field.dispatchEvent(new Event("change", { bubbles: true }));
                    }
                } else {
                    field.value = "";
                }
            });
            form?.querySelectorAll('input[type="hidden"]').forEach((field) => field.remove());

            window.history.replaceState(
                window.history.state,
                "",
                window.location.pathname + window.location.hash,
            );
        });
    });

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

    const userNotificationMenu = document.querySelector("[data-user-notification-menu]");
    const userNotificationToggle = userNotificationMenu?.querySelector("[data-user-notification-toggle]");
    const userNotificationPanel = userNotificationMenu?.querySelector("[data-user-notification-panel]");
    if (userNotificationMenu && userNotificationToggle && userNotificationPanel) {
        const closeNotifications = (restoreFocus = false) => {
            userNotificationMenu.open = false;
            if (restoreFocus) userNotificationToggle.focus();
        };
        userNotificationMenu.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && userNotificationMenu.open) {
                event.preventDefault();
                closeNotifications(true);
            }
        });
        document.addEventListener("click", (event) => {
            if (!(event.target instanceof Node) || !userNotificationMenu.contains(event.target)) {
                closeNotifications();
            }
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

    let active = Math.max(0, slides.findIndex((slide) => slide.classList.contains("is-active")));
    let timer;
    let paused = false;
    let hovered = false;
    let focused = false;
    const pauseButton = document.querySelector("[data-carousel-pause]");
    const carouselStatus = document.querySelector("[data-carousel-status]");
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    const syncPauseButton = () => {
        if (!pauseButton) return;
        const motionReduced = reducedMotion.matches;
        pauseButton.disabled = motionReduced;
        pauseButton.setAttribute("aria-pressed", String(paused || motionReduced));
        pauseButton.setAttribute("aria-label", motionReduced ? "Slideshow paused for reduced motion" : "Automatic slide changes");
        pauseButton.textContent = motionReduced ? "Reduced motion" : paused ? "Play" : "Pause";
    };
    const syncActiveVideo = () => {
        const shouldPlay = !paused && !hovered && !focused && !reducedMotion.matches;
        slides.forEach((slide, position) => {
            const video = slide.querySelector(".hero-video");
            if (!video) return;
            if (position === active && shouldPlay) video.play().catch(() => {});
            else video.pause();
        });
    };
    const show = (index, announce = false) => {
        active = (index + slides.length) % slides.length;
        slides.forEach((slide, position) => {
            const selected = position === active;
            slide.classList.toggle("is-active", selected);
            slide.setAttribute("aria-hidden", String(!selected));
            slide.inert = !selected;
            slide.querySelector(".hero-image")?.classList.toggle("kenburns", selected);
            const copy = slide.querySelector(".hero-copy");
            if (selected && copy) {
                copy.classList.remove("fade-up");
                void copy.offsetWidth;
                copy.classList.add("fade-up");
            }
        });
        dots.forEach((dot, position) => dot.setAttribute("aria-pressed", String(position === active)));
        if (announce && carouselStatus) {
            const heading = slides[active].querySelector("h1")?.textContent?.trim() || "";
            carouselStatus.textContent = `Slide ${active + 1} of ${slides.length}: ${heading}`;
        }
        syncActiveVideo();
        restart();
    };
    const restart = () => {
        window.clearTimeout(timer);
        if (slides.length < 2 || paused || hovered || focused || reducedMotion.matches) return;
        timer = window.setTimeout(() => show(active + 1), 7000);
    };

    document.querySelector(".hero-prev")?.addEventListener("click", () => show(active - 1, true));
    document.querySelector(".hero-next")?.addEventListener("click", () => show(active + 1, true));
    dots.forEach((dot) => dot.addEventListener("click", () => {
        show(Number(dot.dataset.slideTo), true);
    }));
    pauseButton?.addEventListener("click", () => {
        paused = !paused;
        syncPauseButton();
        syncActiveVideo();
        restart();
    });

    const feedbackFor = (input) => {
        let feedback = input.parentElement.querySelector("[data-credential-feedback]");
        if (!feedback) {
            feedback = document.createElement("ul");
            feedback.className = "credential-validation-messages";
            feedback.dataset.credentialFeedback = "";
            feedback.id = `credential-errors-${input.name}`;
            feedback.setAttribute("role", "alert");
            input.insertAdjacentElement("afterend", feedback);
        }
        return feedback;
    };
    const messagesFor = (input) => {
        const value = input.value;
        if (input.hasAttribute("data-credential-username")) {
            const messages = [];
            if (/\s/.test(value)) messages.push("Username cannot contain spaces.");
            if (value.length < 3 || value.length > 20) messages.push("Username must be 3-20 characters.");
            if (!/^[A-Za-z0-9._-]*$/.test(value)) messages.push("Use only letters, numbers, dots, dashes, or underscores.");
            return messages;
        }

        const messages = [];
        if (value.length < 10) messages.push("Password must be at least 10 characters.");
        if (value.length > 128) messages.push("Password must be 128 characters or fewer.");
        if (!/[A-Za-z]/.test(value)) messages.push("Password must contain at least one letter.");
        if (!/\d/.test(value)) messages.push("Password must contain at least one number.");
        return messages;
    };
    const validate = (input) => {
        const messages = messagesFor(input);
        const feedback = feedbackFor(input);
        feedback.replaceChildren(...messages.map((message) => {
            const item = document.createElement("li");
            item.textContent = message;
            return item;
        }));
        feedback.hidden = messages.length === 0;
        input.setAttribute("aria-invalid", String(messages.length > 0));
        if (messages.length) input.setAttribute("aria-describedby", feedback.id);
        else input.removeAttribute("aria-describedby");
        return messages.length === 0;
    };

    document.querySelectorAll("form").forEach((form) => {
        const inputs = [...form.querySelectorAll("[data-credential-password], [data-credential-username]")];
        if (!inputs.length) return;
        inputs.forEach((input) => {
            input.addEventListener("input", () => validate(input));
            input.addEventListener("blur", () => validate(input));
            input.addEventListener("invalid", () => validate(input));
        });
        form.addEventListener("submit", (event) => {
            const invalidInputs = inputs.filter((input) => !validate(input));
            if (!invalidInputs.length) return;
            event.preventDefault();
            invalidInputs[0].focus();
        });
    });
    const carousel = document.querySelector("[data-carousel]");
    carousel?.addEventListener("mouseenter", () => {
        hovered = true;
        syncActiveVideo();
        restart();
    });
    carousel?.addEventListener("mouseleave", () => {
        hovered = false;
        syncActiveVideo();
        restart();
    });
    carousel?.addEventListener("focusin", () => {
        focused = true;
        syncActiveVideo();
        restart();
    });
    carousel?.addEventListener("focusout", (event) => {
        if (!carousel.contains(event.relatedTarget)) {
            focused = false;
            syncActiveVideo();
            restart();
        }
    });
    reducedMotion.addEventListener("change", () => {
        syncPauseButton();
        syncActiveVideo();
        restart();
    });
    slides.forEach((slide, position) => {
        slide.inert = position !== active;
        slide.setAttribute("aria-hidden", String(position !== active));
    });
    syncPauseButton();
    syncActiveVideo();
    restart();
});
