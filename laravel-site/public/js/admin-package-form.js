document.addEventListener("DOMContentLoaded", () => {
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
        const button = event.target.closest(".admin-remove-row");
        if (!button) return;
        const row = button.closest(".admin-repeat-row, .admin-day-row");
        const list = row?.parentElement;
        row?.remove();
        if (list?.matches('[data-repeat-rows="trip_plan"]')) {
            list.querySelectorAll(".admin-day-row").forEach((day, index) => {
                day.querySelector(".admin-day-row-heading strong").textContent = `Day ${index + 1}`;
                day.querySelectorAll("[name]").forEach((field) => {
                    field.name = field.name.replace(/trip_plan\[\d+\]/, `trip_plan[${index}]`);
                });
            });
        }
    });
});
