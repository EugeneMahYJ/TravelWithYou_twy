document.addEventListener("DOMContentLoaded", () => {
    const timers = [...document.querySelectorAll("[data-countdown][data-ends-at]")];
    const units = [
        ["days", 86400],
        ["hours", 3600],
        ["minutes", 60],
        ["seconds", 1],
    ];

    const update = () => {
        const now = Date.now();
        timers.forEach((timer) => {
            const end = Date.parse(timer.dataset.endsAt);
            if (!Number.isFinite(end)) {
                timer.hidden = true;
                return;
            }

            const remaining = Math.max(0, Math.floor((end - now) / 1000));
            let seconds = remaining;
            units.forEach(([unit, divisor]) => {
                const value = Math.floor(seconds / divisor);
                seconds %= divisor;
                const output = timer.querySelector(`[data-countdown-value="${unit}"]`);
                if (output) output.textContent = String(value).padStart(2, "0");
            });
            timer.setAttribute("aria-label", remaining ? `Offer ends in ${remaining} seconds` : "Offer has ended");
        });
    };

    update();
    if (timers.length) window.setInterval(update, 1000);
});
