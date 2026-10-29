const root = document.querySelector("[data-booking-root]");
if (root) {
    const config = JSON.parse(root.querySelector("[data-booking-config]").textContent);
    const form = root.querySelector("[data-booking-form]");
    const app = root.querySelector("[data-booking-app]");
    const kinds = config.guestKinds;
    const addons = config.addons;
    const emptyGuest = () => ({ name: "", dob: "", nation: "", passport: "", passportExpiry: "", email: "", phone: "" });
    const zeroCounts = () => Object.fromEntries(kinds.map((kind) => [kind.id, 0]));
    const old = config.oldInput || {};
    const initialCounts = { ...zeroCounts(), ...(old.counts || {}) };
    if (!old.counts) {
        const adultKind = kinds.find((kind) => (kind.role || "adult") === "adult");
        const childKind = kinds.find((kind) => kind.role === "child");
        if (adultKind) initialCounts[adultKind.id] = Number(old.adults ?? 2);
        if (childKind) initialCounts[childKind.id] = Number(old.children ?? 0);
    }
    const countRole = (counts, role) => kinds.reduce((sum, kind) => sum + ((kind.role || "adult") === role ? Number(counts[kind.id] || 0) : 0), 0);
    const state = {
        step: 0,
        showErrors: Object.keys(config.errors || {}).length > 0,
        adults: Number(old.adults ?? 2),
        children: Number(old.children ?? 0),
        rooms: Number(old.rooms ?? 1),
        roomTypes: Array.isArray(old.room_types) ? old.room_types : [],
        counts: initialCounts,
        guests: Array.isArray(old.guests) ? old.guests.map((guest) => ({ ...emptyGuest(), ...guest, passport: config.editing ? guest.passport || "" : "" })) : [],
        sameAsLead: {},
        openGuests: { 0: true },
        selectedAddons: Array.isArray(old.addons) ? old.addons : [],
        sheetOpen: false,
        busy: false,
        submitAction: "",
        tooltips: {},
        errors: config.errors || {},
        dobTouched: {},
        dobAlerts: {},
    };
    const steps = config.cruise
        ? [
            ["guests", "Number of guests", "Guests"],
            ["rooms", "Number of rooms", "Rooms"],
            ["types", "Room type for each room", "Room types"],
            ["details", "Guest information", "Details"],
            ["addons", "Add-ons", "Add-ons"],
            ["summary", "Price summary", "Summary"],
        ]
        : [
            ["guests", "Number of guests", "Guests"],
            ["details", "Guest information", "Details"],
            ["addons", "Add-ons", "Add-ons"],
            ["summary", "Price summary", "Summary"],
        ];

    const esc = (value) => String(value ?? "").replace(/[&<>"']/g, (char) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;",
    })[char]);
    const fmt = (amount) => `RM ${Math.round(Number(amount) || 0).toLocaleString("en-US")}`;
    const fmtDate = (isoDate) => {
        if (!isoDate) return "";
        const [year, month, day] = isoDate.split("-").map(Number);
        return new Date(year, month - 1, day).toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" });
    };
    const plural = (number, singular, pluralForm = `${singular}s`) => `${number} ${number === 1 ? singular : pluralForm}`;
    const errorFor = (key) => state.errors[key]?.[0] || "";
    const adultCount = () => countRole(state.counts, "adult");
    const singleCount = () => Number(state.counts.single || 0);
    const sharingAdultCount = () => kinds.reduce((sum, kind) => sum + ((kind.role || "adult") === "adult" && kind.id !== "single" ? Number(state.counts[kind.id] || 0) : 0), 0);
    const childCount = () => countRole(state.counts, "child");
    const cruiseTotal = () => adultCount() + childCount();
    const landTotal = () => kinds.reduce((sum, kind) => sum + Number(state.counts[kind.id] || 0), 0);
    const totalGuests = () => config.cruise ? cruiseTotal() : landTotal();
    const totalAdults = () => adultCount();
    const guestLabels = () => kinds.flatMap((kind) => Array.from({ length: Number(state.counts[kind.id] || 0) }, (_, index) => `${kind.label} ${index + 1}`));
    const guestAt = (index) => state.guests[index] || emptyGuest();
    const guestKindAt = (index) => {
        let offset = 0;
        for (const kind of kinds) {
            const count = Number(state.counts[kind.id] || 0);
            if (index < offset + count) return kind;
            offset += count;
        }
        return null;
    };
    const minRooms = () => Math.max(1, singleCount() + Math.ceil((sharingAdultCount() + childCount()) / Number(config.maxPerRoom)));
    const allocateRooms = (adults, children, rooms, singles = 0) => {
        const allocated = Array.from({ length: Math.max(0, rooms) }, () => ({ adults: 0, children: 0 }));
        if (rooms < 1) return allocated;
        const privateRooms = Math.min(singles, rooms);
        for (let index = 0; index < privateRooms; index++) allocated[index].singles = 1;
        const sharingRooms = rooms - privateRooms;
        if (sharingRooms < 1) return allocated;
        for (let index = 0; index < adults; index++) allocated[privateRooms + (index % sharingRooms)].adults++;
        for (let index = 0; index < children; index++) {
            let least = privateRooms;
            for (let room = privateRooms + 1; room < allocated.length; room++) {
                if (allocated[room].adults + allocated[room].children < allocated[least].adults + allocated[least].children) least = room;
            }
            allocated[least].children++;
        }
        return allocated;
    };
    const roomError = () => {
        const need = minRooms();
        if (childCount() > 0 && sharingAdultCount() === 0) return "Children must share a room with an Adult; Single guests stay in their own room.";
        if (state.rooms < need) {
            const guests = adultCount() + childCount();
            const singleNote = singleCount() > 0 ? ` (including ${singleCount()} private single room${singleCount() === 1 ? "" : "s"})` : "";
            return `Maximum ${config.maxPerRoom} guests per room. ${guests} guests need at least ${need} rooms${singleNote}.`;
        }
        if (state.rooms > sharingAdultCount() + singleCount()) {
            if (singleCount() > 0) return `Each room needs at least 1 Adult or Single guest. ${sharingAdultCount()} adults and ${singleCount()} single guests can fill at most ${sharingAdultCount() + singleCount()} rooms.`;
            return `Each room needs at least 1 adult. ${adultCount()} adult${adultCount() === 1 ? "" : "s"} can fill at most ${adultCount()} room${adultCount() === 1 ? "" : "s"}.`;
        }
        return "";
    };
    const currentPlan = () => {
        if (!config.cruise || adultCount() < 1) return [];
        const roomCount = Math.min(Math.max(state.rooms, minRooms()), Math.max(sharingAdultCount() + singleCount(), 1));
        return allocateRooms(sharingAdultCount(), childCount(), roomCount, singleCount()).map((room, index) => ({
            ...room,
            type: state.roomTypes[index] || "",
        }));
    };
    const unitPrice = (pax = 1, cabin = "") => {
        let price = Number(config.package.priceFrom);
        if (cabin) {
            const selectedCabin = config.cabins.find((item) => item.type === cabin);
            if (selectedCabin) price = Number(selectedCabin.price);
        } else {
            const fare = (config.package.fares || []).find((item) => item.date === config.date);
            if (fare) price = fare.bogo ? Number(fare.price) / 2 : Number(fare.price);
        }
        if (config.deal && !(config.deal.type === "Group discount" && pax < 6)) {
            price *= 1 - Number(config.deal.percent) / 100;
        }
        return price;
    };
    const paxTierFor = (kindId, paxNumber) => config.paxTiers.find((tier) =>
        paxNumber >= Number(tier.from)
        && paxNumber <= (tier.to === undefined || tier.to === null || tier.to === "" ? Number.MAX_SAFE_INTEGER : Number(tier.to))
        && tier.prices?.[kindId] !== undefined
    );
    const paxRangeLabel = (tier) => tier.to === undefined || tier.to === null || tier.to === ""
        ? `${tier.from}+`
        : `${tier.from}–${tier.to}`;
    const paxOrdinal = (number) => {
        const remainder = number % 100;
        const suffix = remainder >= 11 && remainder <= 13
            ? "th"
            : ({ 1: "st", 2: "nd", 3: "rd" }[number % 10] || "th");
        return `${number}${suffix}`;
    };
    const guestUnitPrice = (kind, pax, paxNumber = null) => {
        const tier = paxNumber === null ? null : paxTierFor(kind.id, paxNumber);
        const configured = tier?.prices?.[kind.id] ?? config.departurePrices[kind.id];
        if (configured === undefined) return unitPrice(pax) * Number(kind.factor ?? 1);
        let price = Number(configured);
        if (config.deal && !(config.deal.type === "Group discount" && pax < 6)) {
            price *= 1 - Number(config.deal.percent) / 100;
        }
        return price;
    };
    const guestError = () => {
        const total = totalGuests();
        if (config.cruise) {
            if (adultCount() < 1) return "At least 1 adult must travel on each booking.";
            if (childCount() > 0 && sharingAdultCount() < 1) return "Children must share a room with an Adult; Single guests stay in their own room.";
            if (total > 16) return "Online bookings are limited to 16 guests. Contact us for larger groups.";
            return "";
        }
        if (total === 0) return "Add at least one guest to continue.";
        if (totalAdults() < 1) return "Children must travel with at least one Adult or Single guest.";
        if (total > 12) return "Online bookings are limited to 12 guests. Contact us for larger groups.";
        return "";
    };
    const earliestExpiry = () => {
        const [year, month, day] = config.date.split("-").map(Number);
        const date = new Date(year, month - 1 + 6, day);
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
    };
    const ageOnDate = (dob, referenceDate) => {
        const birthParts = String(dob || "").split("-").map(Number);
        const referenceParts = String(referenceDate || "").split("-").map(Number);
        if (birthParts.length !== 3 || referenceParts.length !== 3 || [...birthParts, ...referenceParts].some(Number.isNaN)) return null;
        const [birthYear, birthMonth, birthDay] = birthParts;
        const [referenceYear, referenceMonth, referenceDay] = referenceParts;
        const birthCheck = new Date(Date.UTC(birthYear, birthMonth - 1, birthDay));
        const referenceCheck = new Date(Date.UTC(referenceYear, referenceMonth - 1, referenceDay));
        if (birthCheck.getUTCFullYear() !== birthYear || birthCheck.getUTCMonth() !== birthMonth - 1 || birthCheck.getUTCDate() !== birthDay
            || referenceCheck.getUTCFullYear() !== referenceYear || referenceCheck.getUTCMonth() !== referenceMonth - 1 || referenceCheck.getUTCDate() !== referenceDay) return null;
        let age = referenceYear - birthYear;
        if (referenceMonth * 100 + referenceDay < birthMonth * 100 + birthDay) age--;
        return age;
    };
    const guestErrors = (guest, index, lead) => {
        const errors = {};
        const name = String(guest.name || "").trim();
        if (!/[A-Za-z]{2}/.test(name) || name.length < 3) errors.name = "Enter the full name as on the passport";
        const dob = new Date(`${guest.dob || ""}T00:00:00`);
        if (!guest.dob || Number.isNaN(+dob) || dob > new Date() || dob.getFullYear() < 1900) errors.dob = "Enter a valid date of birth";
        else {
            const age = ageOnDate(guest.dob, config.date);
            const kind = guestKindAt(index);
            if (age === null) errors.dob = "Enter a valid date of birth";
            else if (kind?.id === "single" && age < 18) errors.dob = "Single guests must be at least 18 years old.";
            else if ((kind?.role || "adult") === "adult" && age < 12) errors.dob = "Adult guests must be at least 12 years old.";
            else if (kind?.role === "child" && age > 11) errors.dob = "Children must be 11 years old or younger.";
        }
        if (!String(guest.nation || "").trim()) errors.nation = "Required";
        if (!/^[A-Za-z0-9]{6,12}$/.test(String(guest.passport || "").trim())) errors.passport = "6 to 12 letters or digits";
        if (!guest.passportExpiry) errors.passportExpiry = "Passport expiry date is required";
        else if (guest.passportExpiry < earliestExpiry()) errors.passportExpiry = `Passport must be valid for 6 months after departure (on or after ${fmtDate(earliestExpiry())})`;
        if ((lead || String(guest.email || "").trim()) && !/^\S+@\S+\.\S+$/.test(String(guest.email || "").trim())) errors.email = "Enter a valid email address";
        if ((lead || String(guest.phone || "").trim()) && String(guest.phone || "").replace(/\D/g, "").length < 7) errors.phone = "Enter a valid phone number";
        return errors;
    };
    const allGuestErrors = () => guestLabels().map((_, index) => guestErrors(guestAt(index), index, index === 0));
    const plan = () => {
        if (!config.cruise || guestError()) return [];
        return currentPlan();
    };
    const roomTypeError = () => config.cruise && plan().some((room) => !config.cabins.some((cabin) => cabin.type === room.type))
        ? "Choose a room type for every room."
        : "";
    const infoError = () => allGuestErrors().some((errors) => Object.keys(errors).length > 0)
        ? "Please complete the highlighted guest details."
        : "";
    const currentStepError = () => {
        const key = steps[state.step][0];
        if (key === "guests") return guestError();
        if (key === "rooms") return roomError();
        if (key === "types") return roomTypeError();
        if (key === "details") return infoError();
        return "";
    };
    const priceLines = () => {
        const total = totalGuests();
        if (!total || guestError()) return [];
        const lines = [];
        const guestPriceGroups = new Map();
        const addGuestPrice = (kind, price, paxNumber = null) => {
            const tier = paxNumber === null ? null : paxTierFor(kind.id, paxNumber);
            const tierKey = tier ? `${tier.from}:${tier.to ?? ""}` : "";
            const key = `${kind.id}:${tierKey}:${price}`;
            const existing = guestPriceGroups.get(key);
            if (existing) existing.quantity++;
            else guestPriceGroups.set(key, { kind, price, quantity: 1, tier });
        };
        if (config.cruise) {
            const rooms = plan();
            const roomOccupancy = rooms.map((room) => room.singles ? Number.POSITIVE_INFINITY : room.adults);
            let adultIndex = 0;
            let singleIndex = 0;
            for (const kind of kinds) {
                const count = Number(state.counts[kind.id] || 0);
                for (let guestIndex = 0; guestIndex < count; guestIndex++) {
                    let roomIndex;
                    if (kind.id === "single") {
                        roomIndex = singleIndex++;
                    } else if ((kind.role || "adult") === "adult") {
                        const sharingRoomCount = rooms.length - singleCount();
                        roomIndex = singleCount() + (adultIndex++ % sharingRoomCount);
                    } else {
                        const minOccupancy = Math.min(...roomOccupancy);
                        roomIndex = roomOccupancy.indexOf(minOccupancy);
                        roomOccupancy[roomIndex]++;
                    }
                    const roomType = rooms[roomIndex]?.type || [...config.cabins].sort((a, b) => a.price - b.price)[0]?.type || "";
                    const basePrice = unitPrice(total, roomType);
                    const price = kind.id === "single"
                        ? basePrice * Number(kind.factor ?? 1.5)
                        : (kind.role === "child" ? basePrice * 0.75 : basePrice);
                    addGuestPrice(kind, price);
                }
            }
        } else {
            kinds.forEach((kind) => {
                const count = Number(state.counts[kind.id] || 0);
                for (let index = 0; index < count; index++) {
                    const paxNumber = kinds.slice(0, kinds.indexOf(kind)).reduce((sum, priorKind) => sum + Number(state.counts[priorKind.id] || 0), 0) + index + 1;
                    const price = guestUnitPrice(kind, total, paxNumber);
                    addGuestPrice(kind, price, paxNumber);
                }
            });
        }
        for (const { kind, price, quantity, tier } of guestPriceGroups.values()) {
            const tierLabel = tier ? ` (Pax ${paxRangeLabel(tier)})` : "";
            lines.push({
                group: "Guests",
                label: `${kind.label}${tierLabel}`,
                quantity,
                unitPrice: price,
                amount: price * quantity,
            });
        }
        state.selectedAddons.forEach((id) => {
            const addon = addons.find((item) => item.id === id);
            if (addon) lines.push({ group: "Add-ons", label: `${addon.label} × ${total} @ ${fmt(addon.price)}`, amount: Number(addon.price) * total });
        });
        return lines;
    };
    const subtotal = () => priceLines().reduce((sum, line) => sum + line.amount, 0);
    const taxTotal = () => subtotal() * Number(config.taxRate);
    const grandTotal = () => subtotal() + taxTotal();
    const serverMessage = (keys) => {
        for (const key of keys) {
            const message = errorFor(key);
            if (message) return message;
        }
        return "";
    };
    const errorBlock = (message) => `
        <div class="booking-error" role="alert">
            <span aria-hidden="true">!</span>
            <div><p>${esc(message)}</p><small>Fix the issue above to continue.</small></div>
        </div>`;
    const counter = ({ title, sub = "", value, min = 0, max, key, tip = "" }) => `
        <div class="booking-counter">
            <div class="booking-counter-copy">
                <div class="booking-counter-title">${esc(title)}${tip}</div>
                ${sub ? `<p>${esc(sub)}</p>` : ""}
            </div>
            <div class="booking-counter-controls">
                <button type="button" aria-label="Fewer ${esc(title)}" data-counter="${esc(key)}" data-delta="-1" ${value <= min ? "disabled" : ""}>−</button>
                <output aria-live="polite">${value}</output>
                <button type="button" aria-label="More ${esc(title)}" data-counter="${esc(key)}" data-delta="1" ${value >= max ? "disabled" : ""}>+</button>
            </div>
        </div>`;
    const guestSubline = (kind) => kind.bed || kind.age || "";
    const serverGuestError = (index, field) => errorFor(`guests.${index}.${field}`);
    const renderField = (index, guest, field, title, type = "text", extra = "") => {
        const localError = state.showErrors || (field === "dob" && state.dobTouched[index])
            ? guestErrors(guest, index, index === 0)[field]
            : "";
        const error = serverGuestError(index, field) || localError;
        const label = `${title}${error ? `: ${error}` : ""}`;
        const list = field === "nation" ? 'list="booking-nations"' : "";
        return `<label class="booking-field ${error ? "has-error" : ""}">
            <span>${esc(title)}</span>
            <input type="${type}" name="booking-${field}-${index}" aria-label="${esc(label)}" data-guest-index="${index}" data-guest-field="${field}" value="${esc(guest[field] || "")}" ${list} ${extra} autocomplete="off">
            ${error ? `<small>${esc(error)}</small>` : ""}
        </label>`;
    };
    const summaryLines = (compact = false, totalLabel = "Total") => {
        const lines = priceLines();
        const groups = ["Rooms", "Guests", "Add-ons"];
        const grouped = compact ? "" : groups.map((group) => {
            const items = lines.filter((line) => line.group === group);
            if (!items.length) return "";
            return `<div class="booking-total-group"><h3>${group}</h3>${items.map((line) => line.unitPrice === undefined
                ? `<p><span>${esc(line.label)}</span><strong>${fmt(line.amount)}</strong></p>`
                : `<p><span>${esc(line.label)} · ${line.quantity} ${line.quantity === 1 ? "guest" : "guests"} × ${fmt(line.unitPrice)} each</span><strong>${fmt(line.amount)}</strong></p>`).join("")}</div>`;
        }).join("");
        return `${grouped}
            <div class="booking-total-line"><span>Subtotal</span><strong>${fmt(subtotal())}</strong></div>
            <div class="booking-total-line"><span>Taxes / fees (${Math.round(Number(config.taxRate) * 100)}%)</span><strong>${fmt(taxTotal())}</strong></div>
            <div class="booking-total-grand"><span>${totalLabel}</span><strong>${fmt(grandTotal())}</strong></div>`;
    };
    const paxPriceTable = () => {
        const tiers = config.paxTiers.filter((tier) => tier.prices && Object.keys(tier.prices).length);
        if (!tiers.length) return "";
        const pricedKinds = kinds.filter((kind) => tiers.some((tier) => tier.prices[kind.id] !== undefined));
        const tierHeadings = tiers.map((tier) => {
            const range = tier.to
                ? `${paxOrdinal(Number(tier.from))} – ${paxOrdinal(Number(tier.to))} Pax`
                : `${paxOrdinal(Number(tier.from))}+ Pax`;
            return `<th scope="col"><span>${range}</span><small>Normal</small></th>`;
        }).join("");
        const rows = pricedKinds.map((kind) => `<tr>
            <th scope="row">${esc(kind.label)}</th>
            ${tiers.map((tier) => `<td>${tier.prices[kind.id] === undefined
                ? "—"
                : fmt(guestUnitPrice(kind, totalGuests() || 2, Number(tier.from)))}</td>`).join("")}
        </tr>`).join("");
        return `<div class="booking-pax-price-table">
            <h3>Price Details</h3>
            <div class="booking-pax-price-table-scroll"><table>
                <thead><tr><th scope="col"><span class="visually-hidden">Guest type</span></th>${tierHeadings}</tr></thead>
                <tbody>${rows}</tbody>
            </table></div>
            <p>Prices are per person.</p>
        </div>`;
    };
    const stepSummary = (key) => {
        if (key === "guests") {
            return kinds.filter((kind) => state.counts[kind.id]).map((kind) => `${state.counts[kind.id]} ${kind.label}`).join(", ");
        }
        if (key === "rooms") return `${plural(plan().length, "room")} · ${plan().map((room, index) => `R${index + 1}: ${room.adults}A${room.children ? ` ${room.children}C` : ""}`).join(" · ")}`;
        if (key === "types") return plan().map((room, index) => `Room ${index + 1} ${room.type}`).join(" · ");
        if (key === "details") return `${plural(totalGuests(), "guest")} · Lead: ${guestAt(0).name || ""}`;
        if (key === "addons") return state.selectedAddons.length ? state.selectedAddons.map((id) => addons.find((item) => item.id === id)?.label).join(", ") : "No add-ons";
        return "";
    };
    const guestContent = () => {
        const rows = kinds.map((kind) => {
            const current = Number(state.counts[kind.id] || 0);
            const sub = guestSubline(kind);
            const tip = kind.age || kind.bed ? `<span class="booking-tip-wrap"><button type="button" aria-label="About ${esc(kind.label)}" aria-expanded="${!!state.tooltips[kind.id]}" data-tooltip="${esc(kind.id)}">i</button>${state.tooltips[kind.id] ? `<span class="booking-tooltip" role="tooltip">${kind.age ? `<strong>Age</strong><br>${esc(kind.age)}` : ""}${kind.bed ? `<br><strong>Bed</strong><br>${esc(kind.bed)}` : ""}</span>` : ""}</span>` : "";
            return counter({ title: kind.label, sub, value: current, max: 12, key: `count:${kind.id}`, tip });
        }).join("");
        const totalError = guestError();
        return `${rows}
            <p class="booking-total-count">${plural(landTotal(), "guest")} in total</p>
            ${state.showErrors && (errorFor("counts") || errorFor("adults") || errorFor("children")) ? errorBlock(serverMessage(["counts", "adults", "children"])) : ""}
            ${state.showErrors && totalError ? errorBlock(totalError) : ""}
            ${continueControl()}`;
    };
    const roomsContent = () => {
        const validation = roomError();
        const rooms = allocateRooms(sharingAdultCount(), childCount(), state.rooms, singleCount());
        return `${counter({ title: "Rooms", sub: `Maximum ${config.maxPerRoom} guests per shared room · each shared room needs an Adult; Singles have private rooms`, value: state.rooms, min: 1, max: 8, key: "rooms" })}
            <p class="booking-room-need">${plural(totalGuests(), "guest")} need at least <strong>${plural(minRooms(), "room")}</strong>.</p>
            ${state.showErrors && validation ? errorBlock(validation) : ""}
            ${!validation ? `<ul class="booking-room-preview">${rooms.map((room, index) => `<li><span>Room ${index + 1}</span>${room.singles ? "1 single (private room)" : `${plural(room.adults, "adult")}${room.children ? `, ${plural(room.children, "child", "children")}` : ""}`}</li>`).join("")}</ul>` : ""}
            ${continueControl()}`;
    };
    const typesContent = () => {
        const current = plan();
        const validation = roomTypeError();
        return `${current.map((room, roomIndex) => `<fieldset class="booking-room-type-set">
            <legend>Room ${roomIndex + 1}<span> · ${room.singles ? "1 single (private room)" : `${plural(room.adults, "adult")}${room.children ? `, ${plural(room.children, "child", "children")}` : ""}`}</span></legend>
            <div class="booking-cabin-grid">${config.cabins.map((cabin) => {
                const selected = state.roomTypes[roomIndex] === cabin.type;
                return `<label class="booking-cabin ${selected ? "is-selected" : ""}">
                    <input type="radio" name="cabin-choice-${roomIndex}" data-room-type="${roomIndex}" value="${esc(cabin.type)}" ${selected ? "checked" : ""}>
                    <span class="booking-cabin-image"><img src="${esc(cabin.image)}" alt="" loading="lazy">${selected ? '<b aria-hidden="true">✓</b>' : ""}</span>
                    <span class="booking-cabin-copy"><small>${esc(cabin.size)}</small><strong>${esc(cabin.type)}</strong><span>${esc(cabin.note)}</span><em>${fmt(unitPrice(totalGuests(), cabin.type))} <small>per person</small></em></span>
                </label>`;
            }).join("")}</div>
        </fieldset>`).join("")}
            ${state.showErrors && (errorFor("room_types") || validation) ? errorBlock(errorFor("room_types") || validation) : ""}
            ${continueControl()}`;
    };
    const detailsContent = () => {
        const labels = guestLabels();
        const errors = allGuestErrors();
        const invalid = infoError();
        return `<div class="booking-guest-list">${labels.map((label, index) => {
            const guest = guestAt(index);
            const fieldErrors = errors[index];
            const serverHasErrors = ["name", "dob", "nation", "passport", "passportExpiry", "email", "phone"].some((field) => serverGuestError(index, field));
            const bad = Object.keys(fieldErrors).length > 0 || serverHasErrors;
            const expanded = !!state.openGuests[index] || (state.showErrors && bad);
            const badge = state.showErrors && bad ? "Needs attention" : bad ? "Incomplete" : "Complete";
            const sharedFields = ["nation", "email", "phone"];
            const canShare = guestAt(0).nation || guestAt(0).email || guestAt(0).phone;
            return `<article class="booking-guest-card ${state.showErrors && bad ? "needs-attention" : ""}">
                <button type="button" class="booking-guest-heading" aria-expanded="${expanded}" data-guest-toggle="${index}">
                    <span><strong>${index === 0 ? "Lead booker" : `Guest ${index + 1}`}</strong><small>${esc(label)}</small>${guest.name ? `<em>${esc(guest.name)}</em>` : ""}</span>
                    <span class="booking-guest-state ${state.showErrors && bad ? "has-error" : bad ? "incomplete" : "complete"}">${badge}<b aria-hidden="true">${expanded ? "⌃" : "⌄"}</b></span>
                </button>
                ${expanded ? `<div class="booking-guest-fields">
                    ${index > 0 ? `<label class="booking-same-lead"><input type="checkbox" data-same-lead="${index}" ${state.sameAsLead[index] ? "checked" : ""} ${canShare ? "" : "disabled"}> Same as lead booker <small>(nationality, phone, email)</small></label>` : ""}
                    ${renderField(index, guest, "name", "Full name as on passport")}
                    <div class="booking-field-grid">
                        ${renderField(index, guest, "dob", "Date of birth", "date", `max="${esc(config.today)}"`)}
                        ${renderField(index, guest, "nation", "Nationality")}
                        ${renderField(index, guest, "passport", "Passport number")}
                        ${renderField(index, guest, "passportExpiry", "Passport Expiry Date", "date", `min="${esc(earliestExpiry())}"`)}
                        ${renderField(index, guest, "email", index === 0 ? "Email" : "Email (optional)", "email")}
                        ${renderField(index, guest, "phone", index === 0 ? "Phone" : "Phone (optional)", "tel")}
                    </div>
                </div>` : ""}
            </article>`;
        }).join("")}</div>
        <datalist id="booking-nations">${config.nations.map((nation) => `<option value="${esc(nation)}"></option>`).join("")}</datalist>
        ${state.showErrors && (errorFor("guests") || invalid) ? errorBlock(errorFor("guests") || invalid) : ""}
        ${continueControl()}`;
    };
    const addonsContent = () => `<p class="booking-step-lead">Priced per person and applied to all ${plural(totalGuests(), "guest")}. Optional.</p>
        <div class="booking-addon-list">${addons.map((addon) => {
            const selected = state.selectedAddons.includes(addon.id);
            return `<label class="booking-addon"><span><input type="checkbox" data-addon="${esc(addon.id)}" ${selected ? "checked" : ""}><strong>${esc(addon.label)}</strong></span><small>+${fmt(addon.price)} pp · ${fmt(Number(addon.price) * totalGuests())}</small></label>`;
        }).join("")}</div>
        ${continueControl(state.selectedAddons.length ? "Continue" : "Skip add-ons")}`;
    const summaryContent = () => `<div class="booking-summary-lines">${priceLines().length ? summaryLines() : '<p class="booking-nothing">Nothing to price yet. Go back and choose your guests.</p>'}</div>
        ${priceLines().length ? `<div class="booking-pax-mobile">${paxPriceTable()}</div>` : ""}
        <div class="booking-summary-actions">
            <button class="button button-primary" type="submit" name="action" value="proceed" data-submit-action="proceed" ${state.busy ? "disabled" : ""}>${state.busy ? '<span class="booking-spinner" aria-hidden="true"></span> Securing your fare…' : "Proceed to Payment"}</button>
            <button class="button button-ghost" type="submit" name="action" value="cart" data-submit-action="cart" ${state.busy ? "disabled" : ""}>${config.editing ? "Save Changes" : "Add to Cart"}</button>
        </div>
        <p class="booking-step-lead">You can apply a promo code and review everything once more at payment.</p>`;
    const continueControl = (label = "Continue") => `<div class="booking-continue">
        <button type="button" class="button button-primary" data-continue>${label}</button>
        ${state.showErrors && currentStepError() ? '<span>Fix the issue above to continue.</span>' : ""}
    </div>`;
    const stepContent = (key) => {
        if (key === "guests") return guestContent();
        if (key === "rooms") return roomsContent();
        if (key === "types") return typesContent();
        if (key === "details") return detailsContent();
        if (key === "addons") return addonsContent();
        return summaryContent();
    };
    const sectionMarkup = () => steps.map(([key, title], index) => {
        const status = index < state.step ? "done" : index === state.step ? "active" : "locked";
        return `<section class="booking-section is-${status}" id="booking-step-${index + 1}" aria-labelledby="booking-step-title-${index + 1}">
            <div class="booking-section-heading">
                <span class="booking-step-mark">${status === "done" ? "✓" : status === "locked" ? '<span class="booking-lock-icon" aria-hidden="true"></span>' : index + 1}</span>
                <div><h2 id="booking-step-title-${index + 1}">${esc(title)}</h2>
                    ${status === "done" ? `<p>${esc(stepSummary(key))}</p>` : status === "locked" ? "<p>Complete the previous step to unlock.</p>" : ""}
                </div>
                ${status === "done" ? `<button type="button" class="booking-edit" data-edit-step="${index}">Edit</button>` : ""}
            </div>
            ${status === "active" ? `<div class="booking-section-body">${stepContent(key)}</div>` : ""}
        </section>`;
    }).join("");
    const summaryAside = () => {
        const countLabel = `${plural(totalGuests(), "guest")}${config.cruise && plan().length ? ` · ${plural(plan().length, "room")}` : ""}${state.selectedAddons.length ? ` · ${plural(state.selectedAddons.length, "add-on")}` : ""}`;
        return `<aside class="booking-running-total">
            <div class="booking-running-card">
                <div class="booking-running-package"><img src="${esc(config.package.image)}" alt=""><span><strong>${esc(config.package.name)}</strong><small>${fmtDate(config.date)}</small></span></div>
                <div class="booking-running-content"><h2>Running total</h2>
                    ${priceLines().length ? `<p>${esc(countLabel)}</p>${summaryLines(true, "Estimated Total")}${config.cruise && plan().some((room) => !room.type) ? '<small class="booking-estimate-note">Estimated from the lowest room rate until you choose room types.</small>' : ""}` : `<p class="booking-estimate-empty">${esc(guestError() || "Choose your guests to see a price.")}</p>`}
                </div>
            </div>
            ${priceLines().length ? `<div class="booking-pax-desktop">${paxPriceTable()}</div>` : ""}
        </aside>`;
    };
    const mobileTotal = () => `<div class="booking-mobile-total">
        ${state.sheetOpen ? `<div class="booking-mobile-sheet">${priceLines().length ? summaryLines() : `<p>${esc(guestError() || "Choose your guests to see a price.")}</p>`}</div>` : ""}
        <div class="booking-mobile-bar"><div><small>Running total</small><strong>${priceLines().length ? fmt(grandTotal()) : "RM 0"}</strong></div>
            <button type="button" class="button button-ghost" aria-expanded="${state.sheetOpen}" data-breakdown>${state.sheetOpen ? "Hide" : "Breakdown"} <span aria-hidden="true">${state.sheetOpen ? "⌄" : "⌃"}</span></button>
        </div>
    </div>`;
    const activeKey = () => steps[state.step][0];
    const stepper = () => `<nav class="booking-stepper" aria-label="Booking steps">
        <p>Step ${state.step + 1} of ${steps.length} · <strong>${esc(steps[state.step][2])}</strong></p>
        <ol>${steps.map(([, , label], index) => `<li aria-current="${index === state.step ? "step" : "false"}" class="${index < state.step ? "is-done" : index === state.step ? "is-current" : "is-upcoming"}">
            ${index < state.step ? `<button type="button" data-edit-step="${index}">${index + 1}. ${esc(label)}</button>` : `<span>${index + 1}. ${esc(label)}</span>`}
        </li>`).join("")}</ol>
    </nav>`;
    const heading = () => `<div class="booking-heading">
        <a class="booking-back" href="${esc(config.packageUrl)}"><span aria-hidden="true">←</span> Back to package</a>
        <p class="eyebrow accent">${config.cruise ? `Cruise booking · ${esc(config.ship.name)}` : "Flight + hotel booking"}</p>
        <h1>${esc(config.package.name)}</h1>
        <p>Departing ${fmtDate(config.date)} · ${config.package.days} days · from ${fmt(unitPrice(2))} per person</p>
    </div>`;
    const buildHiddenFields = () => {
        const fields = document.createElement("div");
        fields.hidden = true;
        const add = (name, value) => {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = name;
            input.value = value ?? "";
            fields.append(input);
        };
        add("kind", config.cruise ? "cruise" : "fh");
        add("adults", adultCount());
        add("children", childCount());
        add("rooms", state.rooms);
        kinds.forEach((kind) => add(`counts[${kind.id}]`, Number(state.counts[kind.id] || 0)));
        state.roomTypes.forEach((type) => add("room_types[]", type));
        guestLabels().forEach((_, index) => {
            const guest = guestAt(index);
            Object.entries(guest).forEach(([field, value]) => add(`guests[${index}][${field}]`, value));
        });
        state.selectedAddons.forEach((addon) => add("addons[]", addon));
        return fields;
    };
    const render = (focus = null) => {
        const currentKey = activeKey();
        const stepErrors = currentStepError();
        const serverMessageForCurrentStep = currentKey === "guests"
            ? serverMessage(["counts", "adults", "children"])
            : currentKey === "rooms"
                ? serverMessage(["rooms"])
                : currentKey === "types"
                    ? serverMessage(["room_types"])
                    : currentKey === "details"
                        ? serverMessage(["guests"])
                        : "";
        const errorAnnounce = state.showErrors && (stepErrors || serverMessageForCurrentStep);
        app.innerHTML = `${heading()}${stepper()}
            <div class="booking-layout">
                <div class="booking-sections">${sectionMarkup()}</div>
                ${summaryAside()}
            </div>
            ${mobileTotal()}
            <div class="booking-live-region" aria-live="assertive" aria-atomic="true">${errorAnnounce ? esc(serverMessageForCurrentStep || stepErrors) : ""}</div>`;
        if (focus) {
            const target = app.querySelector(`[data-guest-index="${focus.index}"][data-guest-field="${focus.field}"]`);
            if (target) {
                target.focus();
                if (focus.start !== null && target.setSelectionRange) target.setSelectionRange(focus.start, focus.end);
            }
        }
    };
    const moveToStep = (index) => {
        state.step = index;
        state.showErrors = false;
        render();
        const initialDobError = Object.entries(state.errors).find(([key]) => /^guests\.\d+\.dob$/.test(key));
        if (initialDobError) window.alert(initialDobError[1][0]);
        requestAnimationFrame(() => document.getElementById(`booking-step-${index + 1}`)?.scrollIntoView({ behavior: "smooth", block: "start" }));
    };
    const syncLeadDetails = (index, field, value) => {
        const guest = { ...emptyGuest(), ...guestAt(index), [field]: value };
        state.guests[index] = guest;
        if (index === 0 && ["nation", "email", "phone"].includes(field)) {
            state.guests.forEach((other, guestIndex) => {
                if (guestIndex > 0 && state.sameAsLead[guestIndex]) state.guests[guestIndex] = { ...other, [field]: value };
            });
        } else if (index > 0 && state.sameAsLead[index] && ["nation", "email", "phone"].includes(field)) {
            state.sameAsLead[index] = false;
        }
    };
    const clearServerError = (key) => {
        if (state.errors[key]) delete state.errors[key];
    };
    const reportDobError = (index) => {
        const error = guestErrors(guestAt(index), index, index === 0).dob;
        if (!error) {
            delete state.dobAlerts[index];
            return;
        }
        if (state.dobAlerts[index] !== error) {
            state.dobAlerts[index] = error;
            window.alert(error);
        }
    };
    const initialErrors = Object.keys(state.errors);
    if (initialErrors.some((key) => key.startsWith("room_types"))) state.step = config.cruise ? 2 : 0;
    else if (initialErrors.some((key) => key.startsWith("rooms"))) state.step = config.cruise ? 1 : 0;
    else if (initialErrors.some((key) => key.startsWith("guests." ) || key === "guests")) state.step = config.cruise ? 3 : 1;
    render();

    root.addEventListener("click", (event) => {
        const target = event.target.closest("button");
        if (!target) return;
        if (target.hasAttribute("data-counter")) {
            const key = target.dataset.counter;
            const delta = Number(target.dataset.delta);
            if (key === "rooms") {
                state.rooms = Math.max(1, Math.min(8, state.rooms + delta));
                clearServerError("rooms");
            } else if (key.startsWith("count:")) {
                const kind = key.slice(6);
                state.counts[kind] = Math.max(0, Math.min(12, Number(state.counts[kind] || 0) + delta));
                clearServerError("counts");
            }
            render();
            return;
        }
        if (target.hasAttribute("data-continue")) {
            const error = currentStepError();
            if (error) {
                state.showErrors = true;
                if (activeKey() === "details") {
                    allGuestErrors().forEach((guestErrorMap, index) => {
                        if (Object.keys(guestErrorMap).length) state.openGuests[index] = true;
                    });
                }
                render();
                return;
            }
            if (activeKey() === "guests" && config.cruise) state.rooms = Math.max(state.rooms, minRooms());
            moveToStep(Math.min(state.step + 1, steps.length - 1));
            return;
        }
        if (target.hasAttribute("data-edit-step")) {
            moveToStep(Number(target.dataset.editStep));
            return;
        }
        if (target.hasAttribute("data-guest-toggle")) {
            const index = Number(target.dataset.guestToggle);
            state.openGuests[index] = !state.openGuests[index];
            render();
            return;
        }
        if (target.hasAttribute("data-tooltip")) {
            const key = target.dataset.tooltip;
            state.tooltips[key] = !state.tooltips[key];
            render();
            return;
        }
        if (target.hasAttribute("data-breakdown")) {
            state.sheetOpen = !state.sheetOpen;
            render();
        }
    });
    root.addEventListener("input", (event) => {
        const target = event.target;
        if (!target.matches("[data-guest-index][data-guest-field]")) return;
        const index = Number(target.dataset.guestIndex);
        const field = target.dataset.guestField;
        const value = field === "passport" ? target.value.toUpperCase() : target.value;
        const supportsSelection = ["text", "search", "tel", "url", "password"].includes(target.type);
        const focus = {
            index,
            field,
            start: supportsSelection ? target.selectionStart : null,
            end: supportsSelection ? target.selectionEnd : null,
        };
        syncLeadDetails(index, field, value);
        clearServerError(`guests.${index}.${field}`);
        render(focus);
    });
    root.addEventListener("change", (event) => {
        const target = event.target;
        if (target.matches('[data-guest-index][data-guest-field="dob"]')) {
            const index = Number(target.dataset.guestIndex);
            state.dobTouched[index] = true;
            reportDobError(index);
            render();
        } else if (target.matches("[data-room-type]")) {
            state.roomTypes[Number(target.dataset.roomType)] = target.value;
            clearServerError("room_types");
            render();
        } else if (target.matches("[data-addon]")) {
            const id = target.dataset.addon;
            state.selectedAddons = target.checked
                ? [...new Set([...state.selectedAddons, id])]
                : state.selectedAddons.filter((item) => item !== id);
            render();
        } else if (target.matches("[data-same-lead]")) {
            const index = Number(target.dataset.sameLead);
            state.sameAsLead[index] = target.checked;
            if (target.checked) {
                state.guests[index] = { ...emptyGuest(), ...guestAt(index), nation: guestAt(0).nation, email: guestAt(0).email, phone: guestAt(0).phone };
            }
            render();
        }
    });
    root.addEventListener("focusout", (event) => {
        const target = event.target;
        if (!target.matches('[data-guest-index][data-guest-field="dob"]')) return;
        const index = Number(target.dataset.guestIndex);
        state.dobTouched[index] = true;
        reportDobError(index);
        render();
    });
    form.addEventListener("submit", (event) => {
        const submitter = event.submitter;
        const action = submitter?.dataset.submitAction || submitter?.value || "proceed";
        if (state.busy) {
            event.preventDefault();
            return;
        }
        const invalidDob = allGuestErrors().findIndex((errors) => errors.dob);
        if (invalidDob >= 0) {
            event.preventDefault();
            state.showErrors = true;
            state.step = config.cruise ? 3 : 1;
            state.openGuests[invalidDob] = true;
            reportDobError(invalidDob);
            render();
            requestAnimationFrame(() => app.querySelector(`[data-guest-index="${invalidDob}"][data-guest-field="dob"]`)?.focus());
            return;
        }
        event.preventDefault();
        state.busy = true;
        render();
        const previous = form.querySelector("[data-booking-hidden-fields]");
        previous?.remove();
        const hidden = buildHiddenFields();
        hidden.dataset.bookingHiddenFields = "";
        const actionInput = document.createElement("input");
        actionInput.type = "hidden";
        actionInput.name = "action";
        actionInput.value = action;
        hidden.append(actionInput);
        form.append(hidden);
        requestAnimationFrame(() => form.submit());
    });
}
