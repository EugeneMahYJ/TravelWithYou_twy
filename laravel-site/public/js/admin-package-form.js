document.addEventListener("DOMContentLoaded", () => {
    const guestTypes = document.querySelector("[data-guest-types]");
    const departureRows = document.querySelector("[data-departure-prices]");
    const packageCabins = document.querySelector("[data-package-cabins]");
    const packageAddons = document.querySelector("[data-package-addons]");
    const packageType = document.querySelector("[data-package-type]");
    const packageShip = document.querySelector("[data-package-ship]");
    const cruiseSection = document.querySelector("[data-cruise-only]");
    const slug = (value) => String(value).toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
    const guestRows = () => [...(guestTypes?.querySelectorAll("[data-guest-type-row]") ?? [])];
    const paxTierTemplate = document.querySelector("[data-pax-tier-template]");
    const reindexPaxTiers = (row) => {
        const departureIndex = row.querySelector('input[name^="departures["]')?.name.match(/departures\[(\d+)\]/)?.[1] ?? "0";
        row.querySelectorAll("[data-pax-tier-row]").forEach((tier, tierIndex) => {
            tier.querySelector(".admin-day-row-heading strong").textContent = `Band ${tierIndex + 1}`;
            tier.querySelectorAll("[name]").forEach((field) => {
                field.name = field.name.replace(
                    /departure_pax_tiers\[\d+\]\[\d+\]/,
                    `departure_pax_tiers[${departureIndex}][${tierIndex}]`
                );
            });
        });
    };
    const addPaxTierPriceField = (row, departureIndex, tierIndex, kind) => {
        const grid = row.querySelector(".form-grid");
        if (!grid || grid.querySelector(`[data-pax-price-type="${kind.id}"]`)) return;
        const label = document.createElement("label");
        label.dataset.paxPriceType = kind.id;
        const title = document.createElement("span");
        title.textContent = `${kind.label} price (RM)`;
        const input = document.createElement("input");
        input.type = "number";
        input.min = "1";
        input.name = `departure_pax_tiers[${departureIndex}][${tierIndex}][prices][${kind.id}]`;
        input.required = document.querySelector('[name="status"]')?.value !== "Draft";
        label.append(title, input);
        grid.append(label);
    };
    const addPaxTier = (departure) => {
        if (!paxTierTemplate) return;
        const list = departure.querySelector("[data-pax-tier-rows]");
        if (!list) return;
        const departureIndex = departure.querySelector('input[name^="departures["]')?.name.match(/departures\[(\d+)\]/)?.[1] ?? "0";
        const tierIndex = list.querySelectorAll("[data-pax-tier-row]").length;
        const fragment = paxTierTemplate.content.cloneNode(true);
        fragment.querySelectorAll("[name]").forEach((field) => {
            field.name = field.name
                .replaceAll("__DEPARTURE__", departureIndex)
                .replaceAll("__TIER__", tierIndex);
        });
        const tier = fragment.querySelector("[data-pax-tier-row]");
        guestRows().forEach((guestRow) => addPaxTierPriceField(tier, departureIndex, tierIndex, {
            id: guestRow.querySelector("[data-guest-id]").value,
            label: guestRow.querySelector('[name$="[label]"]').value,
        }));
        list.append(fragment);
        reindexPaxTiers(departure);
    };
    document.addEventListener("click", (event) => {
        const addButton = event.target.closest("[data-add-pax-tier]");
        if (addButton) {
            const departure = addButton.closest("[data-departure-row]");
            if (departure) addPaxTier(departure);
        }
    });
    const reindexDepartures = () => {
        departureRows?.querySelectorAll("[data-departure-row]").forEach((row, index) => {
            row.querySelector(".admin-day-row-heading strong").textContent = `Departure ${index + 1}`;
            row.querySelectorAll("[name]").forEach((field) => {
                field.name = field.name.replace(/departures\[\d+\]/, `departures[${index}]`)
                    .replace(/departure_prices\[\d+\]/, `departure_prices[${index}]`)
                    .replace(/departure_pax_tiers\[\d+\]/, `departure_pax_tiers[${index}]`);
            });
            reindexPaxTiers(row);
        });
    };
    const addGuestPriceField = (row, kind) => {
        const fields = row.querySelector("[data-departure-price-fields]");
        if (!fields || fields.querySelector(`[data-price-type="${kind.id}"]`)) return;
        const label = document.createElement("label");
        label.dataset.priceType = kind.id;
        const title = document.createElement("span");
        title.textContent = `${kind.label} price (RM)`;
        const input = document.createElement("input");
        input.type = "number";
        input.min = "1";
        const departureIndex = row.querySelector('input[name^="departures["]')?.name.match(/departures\[(\d+)\]/)?.[1] ?? "0";
        input.name = `departure_prices[${departureIndex}][${kind.id}]`;
        if (packageType.value !== "Cruise" && document.querySelector('[name="status"]')?.value !== "Draft") input.required = true;
        label.append(title, input);
        fields.append(label);
    };
    const refreshCruiseFields = () => {
        const isCruise = packageType?.value === "Cruise";
        if (cruiseSection) cruiseSection.hidden = !isCruise || !packageShip?.value;
        departureRows?.querySelectorAll("[data-price-type] input").forEach((input) => {
            input.required = !isCruise && document.querySelector('[name="status"]')?.value !== "Draft";
        });
        packageCabins?.querySelectorAll('input[name$="[price]"]').forEach((input) => {
            input.required = isCruise && document.querySelector('[name="status"]')?.value !== "Draft";
        });
    };
    const refreshPaxTierFields = () => {
        const isFlightPackage = packageType?.value === "Flight + hotel";
        const published = document.querySelector('[name="status"]')?.value !== "Draft";
        departureRows?.querySelectorAll("[data-flight-pax-pricing]").forEach((section) => {
            section.hidden = !isFlightPackage;
            section.querySelectorAll('input[type="number"]').forEach((input) => {
                input.required = isFlightPackage && published;
            });
        });
    };
    const renderCabins = () => {
        if (!packageCabins || !packageShip) return;
        const selected = packageShip.selectedOptions[0];
        const cabinData = selected?.dataset.cabins ? JSON.parse(selected.dataset.cabins) : [];
        const existing = new Map([...packageCabins.querySelectorAll("[data-package-cabin]")].map((row) => [
            row.querySelector('input[name$="[type]"]').value,
            row.querySelector('input[name$="[price]"]').value,
        ]));
        packageCabins.replaceChildren();
        cabinData.forEach((cabin, index) => {
            const row = document.createElement("article");
            row.className = "admin-day-row";
            row.dataset.packageCabin = "";
            const hidden = document.createElement("input");
            hidden.type = "hidden";
            hidden.name = `cabin_prices[${index}][type]`;
            hidden.value = cabin.type;
            const grid = document.createElement("div");
            grid.className = "form-grid";
            const description = document.createElement("div");
            const type = document.createElement("strong");
            type.textContent = cabin.type;
            const size = document.createElement("small");
            size.className = "muted";
            size.textContent = cabin.size || "";
            const note = document.createElement("p");
            note.textContent = cabin.note || "";
            description.append(type, size, note);
            const label = document.createElement("label");
            const title = document.createElement("span");
            title.textContent = "Package price (RM per person)";
            const input = document.createElement("input");
            input.type = "number";
            input.min = "1";
            input.name = `cabin_prices[${index}][price]`;
            input.value = existing.get(cabin.type) || (Number(cabin.legacyPrice) > 1 ? cabin.legacyPrice : "");
            input.required = packageType.value === "Cruise" && document.querySelector('[name="status"]')?.value !== "Draft";
            label.append(title, input);
            grid.append(description, label);
            row.append(hidden, grid);
            packageCabins.append(row);
        });
        refreshCruiseFields();
    };

    document.querySelectorAll("[data-repeat-add]").forEach((button) => {
        button.addEventListener("click", () => {
            const key = button.dataset.repeatAdd;
            const list = document.querySelector(`[data-repeat-rows="${key}"]`);
            const template = document.querySelector(`[data-repeat-template="${key}"]`);
            if (!list || !template) return;
            const fragment = template.content.cloneNode(true);
            if (key === "trip_plan") {
                const index = list.querySelectorAll(".admin-day-row").length;
                fragment.querySelectorAll("[name]").forEach((field) => {
                    field.name = field.name.replaceAll("__INDEX__", String(index));
                });
                const heading = fragment.querySelector(".admin-day-row-heading strong");
                if (heading) heading.textContent = `Day ${index + 1}`;
            }
            list.append(fragment);
        });
    });

    document.addEventListener("click", (event) => {
        const target = event.target.closest("button");
        if (!target) return;

        if (target.matches("[data-add-guest-type]")) {
            const template = document.querySelector("[data-guest-type-template]");
            if (!template || !guestTypes) return;
            const fragment = template.content.cloneNode(true);
            const index = guestRows().length;
            fragment.querySelectorAll("[name]").forEach((field) => {
                field.name = field.name.replaceAll("__INDEX__", String(index));
            });
            const row = fragment.querySelector("[data-guest-type-row]");
            let id = `guest-${Date.now()}`;
            row.querySelector("[data-guest-id]").value = id;
            row.querySelector(".admin-day-row-heading strong").textContent = `Guest type ${index + 1}`;
            const labelInput = row.querySelector('[name$="[label]"]');
            labelInput.addEventListener("input", (inputEvent) => {
                const nextId = `${slug(inputEvent.target.value) || `guest-${Date.now()}`}-${index + 1}`;
                departureRows?.querySelectorAll("[data-departure-row]").forEach((departure) => {
                    const basePrice = departure.querySelector(`[data-price-type="${id}"]`);
                    if (basePrice) {
                        basePrice.dataset.priceType = nextId;
                        basePrice.querySelector("span").textContent = `${inputEvent.target.value} price (RM)`;
                        basePrice.querySelector("input").name = basePrice.querySelector("input").name.replace(`[${id}]`, `[${nextId}]`);
                    }
                    departure.querySelectorAll(`[data-pax-price-type="${id}"]`).forEach((tierPrice) => {
                        tierPrice.dataset.paxPriceType = nextId;
                        tierPrice.querySelector("span").textContent = `${inputEvent.target.value} price (RM)`;
                        tierPrice.querySelector("input").name = tierPrice.querySelector("input").name.replace(`[${id}]`, `[${nextId}]`);
                    });
                });
                row.querySelector("[data-guest-id]").value = nextId;
                id = nextId;
            });
            guestTypes.append(fragment);
            const kind = { id, label: labelInput.value || "Guest" };
            departureRows?.querySelectorAll("[data-departure-row]").forEach((departure) => {
                addGuestPriceField(departure, kind);
                const departureIndex = departure.querySelector('input[name^="departures["]')?.name.match(/departures\[(\d+)\]/)?.[1] ?? "0";
                departure.querySelectorAll("[data-pax-tier-row]").forEach((tier) => {
                    const tierIndex = [...departure.querySelectorAll("[data-pax-tier-row]")].indexOf(tier);
                    addPaxTierPriceField(tier, departureIndex, tierIndex, kind);
                });
            });
            return;
        }

        if (target.matches("[data-add-departure]")) {
            const template = document.querySelector("[data-departure-template]");
            if (!template || !departureRows) return;
            const fragment = template.content.cloneNode(true);
            const index = departureRows.querySelectorAll("[data-departure-row]").length;
            fragment.querySelectorAll("[name]").forEach((field) => {
                field.name = field.name.replaceAll("__INDEX__", String(index));
            });
            const row = fragment.querySelector("[data-departure-row]");
            guestRows().forEach((guestRow) => addGuestPriceField(row, {
                id: guestRow.querySelector("[data-guest-id]").value,
                label: guestRow.querySelector('[name$="[label]"]').value,
            }));
            departureRows.append(fragment);
            reindexDepartures();
            refreshCruiseFields();
            refreshPaxTierFields();
            return;
        }

        if (target.matches("[data-add-package-addon]")) {
            const template = document.querySelector("[data-package-addon-template]");
            if (!template || !packageAddons) return;
            const fragment = template.content.cloneNode(true);
            const index = packageAddons.querySelectorAll("[data-package-addon]").length;
            fragment.querySelectorAll("[name]").forEach((field) => {
                field.name = field.name.replaceAll("__INDEX__", String(index));
            });
            fragment.querySelector("[data-addon-id]").value = `addon-${Date.now()}`;
            packageAddons.append(fragment);
            return;
        }

        const removeButton = target.closest(".admin-remove-row");
        if (!removeButton) return;
        const row = removeButton.closest("[data-pax-tier-row], [data-guest-type-row], [data-departure-row], [data-package-addon], .admin-repeat-row, .admin-day-row");
        if (!row) return;

        const departure = row.closest("[data-departure-row]");
        if (row.matches("[data-pax-tier-row]")) {
            row.remove();
            if (departure) reindexPaxTiers(departure);
        } else if (row.matches("[data-guest-type-row]")) {
            const id = row.querySelector("[data-guest-id]").value;
            departureRows?.querySelectorAll(`[data-price-type="${id}"], [data-pax-price-type="${id}"]`).forEach((field) => field.remove());
            row.remove();
            guestRows().forEach((guestRow, index) => {
                guestRow.querySelectorAll("[name]").forEach((field) => {
                    field.name = field.name.replace(/guest_types\[\d+\]/, `guest_types[${index}]`);
                });
                guestRow.querySelector(".admin-day-row-heading strong").textContent = `Guest type ${index + 1}`;
            });
        } else if (row.matches("[data-departure-row]")) {
            row.remove();
            reindexDepartures();
        } else if (row.matches("[data-package-addon]")) {
            row.remove();
            packageAddons?.querySelectorAll("[data-package-addon]").forEach((addonRow, index) => {
                addonRow.querySelectorAll("[name]").forEach((field) => {
                    field.name = field.name.replace(/addons\[\d+\]/, `addons[${index}]`);
                });
            });
        } else {
            const list = row.parentElement;
            row.remove();
            if (list?.matches('[data-repeat-rows="trip_plan"]')) {
                list.querySelectorAll(".admin-day-row").forEach((day, index) => {
                    day.querySelector(".admin-day-row-heading strong").textContent = `Day ${index + 1}`;
                    day.querySelectorAll("[name]").forEach((field) => {
                        field.name = field.name.replace(/trip_plan\[\d+\]/, `trip_plan[${index}]`);
                    });
                });
            }
        }
    });

    packageType?.addEventListener("change", () => {
        refreshCruiseFields();
        refreshPaxTierFields();
    });
    packageShip?.addEventListener("change", renderCabins);
    document.querySelector('[name="status"]')?.addEventListener("change", () => {
        refreshCruiseFields();
        refreshPaxTierFields();
    });
    refreshCruiseFields();
    refreshPaxTierFields();
    if (packageCabins?.children.length === 0 && packageShip?.value) renderCabins();
});
