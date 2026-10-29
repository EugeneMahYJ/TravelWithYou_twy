document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-promo-discount-type]").forEach((select) => {
        const discountValue = select.form?.querySelector("[data-promo-discount-value]");
        const discountUnit = select.form?.querySelector("[data-promo-discount-unit]");
        if (!(select instanceof HTMLSelectElement) || !(discountValue instanceof HTMLInputElement)
            || !(discountUnit instanceof HTMLElement)) return;

        const syncLimit = () => {
            const fixed = select.value === "fixed";
            discountValue.min = fixed ? "1" : "0.01";
            discountValue.max = fixed ? "1000000" : "90";
            discountValue.step = fixed ? "1" : "0.01";
            discountUnit.textContent = fixed ? "(RM)" : "(%)";
        };
        select.addEventListener("change", syncLimit);
        syncLimit();
    });

    document.querySelectorAll("[data-promo-eligibility]").forEach((eligibility) => {
        const allPackagesMode = eligibility.querySelector('input[name="all_packages"][value="1"]');
        const selectedPackagesMode = eligibility.querySelector('input[name="all_packages"][value="0"]');
        const packageList = eligibility.querySelector("[data-promo-package-list]");
        const selectAllButton = eligibility.querySelector("[data-promo-select-all]");
        if (!(allPackagesMode instanceof HTMLInputElement)
            || !(selectedPackagesMode instanceof HTMLInputElement)
            || !(packageList instanceof HTMLElement)
            || !(selectAllButton instanceof HTMLButtonElement)) return;

        const syncMode = () => {
            packageList.hidden = allPackagesMode.checked;
        };
        allPackagesMode.addEventListener("change", syncMode);
        selectedPackagesMode.addEventListener("change", syncMode);
        selectAllButton.addEventListener("click", () => {
            selectedPackagesMode.checked = true;
            packageList.querySelectorAll('input[name="package_ids[]"]').forEach((checkbox) => {
                if (checkbox instanceof HTMLInputElement) checkbox.checked = true;
            });
            syncMode();
        });
        syncMode();
    });
});
