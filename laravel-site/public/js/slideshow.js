document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-slideshow]").forEach((slideshow) => {
        const track = slideshow.querySelector(".slideshow-track");
        const controls = slideshow.querySelector(".slideshow-controls");
        const dots = slideshow.querySelector(".slideshow-dots");
        const pauseButton = slideshow.querySelector("[data-slideshow-pause]");
        let slides = [...slideshow.querySelectorAll("[data-slideshow-slide]")];
        let active = Math.max(0, slides.findIndex((slide) => slide.classList.contains("is-active")));
        let timer;
        let paused = false;
        let hovered = false;
        let focused = false;
        const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

        const restart = () => {
            window.clearTimeout(timer);
            if (slides.length < 2 || paused || hovered || focused || reduceMotion.matches) return;
            timer = window.setTimeout(() => show(active + 1), 7000);
        };

        const show = (requestedIndex) => {
            if (slides.length === 0) return;
            active = (requestedIndex + slides.length) % slides.length;
            slides.forEach((slide, index) => {
                const selected = index === active;
                slide.classList.toggle("is-active", selected);
                slide.setAttribute("aria-hidden", String(!selected));
                const image = slide.querySelector("[data-slideshow-image]");
                if (image) image.alt = selected ? slide.querySelector("figcaption")?.textContent ?? "" : "";
            });
            slideshow.querySelectorAll("[data-slideshow-dot]").forEach((dot, index) => {
                dot.setAttribute("aria-current", String(index === active));
            });
            restart();
        };

        const updateControls = () => {
            const visible = slides.length > 1;
            if (controls) controls.hidden = !visible;
            slideshow.querySelectorAll("[data-slideshow-prev], [data-slideshow-next]").forEach((button) => {
                button.hidden = !visible;
            });
        };

        slideshow.querySelectorAll("[data-slideshow-image]").forEach((image) => {
            image.addEventListener("error", () => {
                const slide = image.closest("[data-slideshow-slide]");
                if (!slide) return;
                const removedIndex = slides.indexOf(slide);
                slide.remove();
                slides = slides.filter((item) => item !== slide);
                slideshow.querySelector(`[data-slideshow-dot][data-slide-index="${slide.dataset.slideIndex}"]`)?.remove();
                if (slides.length === 0) {
                    track?.remove();
                    slideshow.classList.add("slideshow-empty");
                } else {
                    if (removedIndex <= active) active = Math.max(0, active - 1);
                    show(active);
                }
                updateControls();
            }, { once: true });
        });

        slideshow.querySelector("[data-slideshow-prev]")?.addEventListener("click", () => show(active - 1));
        slideshow.querySelector("[data-slideshow-next]")?.addEventListener("click", () => show(active + 1));
        slideshow.querySelectorAll("[data-slideshow-dot]").forEach((dot) => {
            dot.addEventListener("click", () => show(Number(dot.dataset.slideIndex)));
        });
        pauseButton?.addEventListener("click", () => {
            paused = !paused;
            pauseButton.textContent = paused ? "Play" : "Pause";
            pauseButton.setAttribute("aria-label", paused ? "Play slideshow" : "Pause slideshow");
            pauseButton.setAttribute("aria-pressed", String(paused));
            restart();
        });
        slideshow.addEventListener("mouseenter", () => { hovered = true; restart(); });
        slideshow.addEventListener("mouseleave", () => { hovered = false; restart(); });
        slideshow.addEventListener("focusin", () => { focused = true; restart(); });
        slideshow.addEventListener("focusout", (event) => {
            if (!slideshow.contains(event.relatedTarget)) {
                focused = false;
                restart();
            }
        });
        reduceMotion.addEventListener("change", restart);
        slideshow.querySelectorAll("[data-slideshow-dot]").forEach((dot, index) => {
            dot.dataset.slideIndex = String(index);
        });
        updateControls();
        show(active);
    });
});
