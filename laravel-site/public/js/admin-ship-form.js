document.addEventListener("DOMContentLoaded", () => {
    const rowsIn = (list) => [...list.querySelectorAll("[data-repeat-item]")];

    const renumber = (list) => {
        const key = list.dataset.repeatIndexed;
        if (!key) return;
        const label = key === "cabins" ? "Cabin" : "Deck";
        rowsIn(list).forEach((row, index) => {
            row.querySelectorAll("[name]").forEach((field) => {
                field.name = field.name.replace(new RegExp(`^${key}\\[\\d+\\]`), `${key}[${index}]`);
            });
            const heading = row.querySelector(".admin-day-row-heading strong");
            if (heading) heading.textContent = `${label} ${index + 1}`;
        });
    };

    document.querySelectorAll("[data-repeat-add]").forEach((button) => {
        button.addEventListener("click", () => {
            const key = button.dataset.repeatAdd;
            const list = document.querySelector(`[data-repeat-rows="${key}"]`);
            const template = document.querySelector(`[data-repeat-template="${key}"]`);
            if (!list || !template) return;
            const fragment = template.content.cloneNode(true);
            if (list.dataset.repeatIndexed === key) {
                const index = rowsIn(list).length;
                fragment.querySelectorAll("[name]").forEach((field) => {
                    field.name = field.name.replaceAll("__INDEX__", String(index));
                });
            }
            list.append(fragment);
            renumber(list);
        });
    });

    document.addEventListener("click", (event) => {
        const button = event.target.closest(".admin-remove-row");
        if (!button) return;
        const row = button.closest("[data-repeat-item]");
        if (!row) return;
        const list = row.closest("[data-repeat-rows]");
        row.remove();
        if (list) renumber(list);
    });
});
