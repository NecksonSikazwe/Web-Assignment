const slides = Array.from(document.querySelectorAll(".slide"));
const dots = Array.from(document.querySelectorAll(".dot"));
const previousButton = document.querySelector(".slider-control.prev");
const nextButton = document.querySelector(".slider-control.next");
let currentSlide = 0;

function showSlide(index) {
    currentSlide = (index + slides.length) % slides.length;

    slides.forEach((slide, slideIndex) => {
        const isActive = slideIndex === currentSlide;
        slide.hidden = !isActive;
        slide.setAttribute("aria-hidden", String(!isActive));
    });

    dots.forEach((dot, dotIndex) => {
        const isActive = dotIndex === currentSlide;
        dot.classList.toggle("active", isActive);
        dot.setAttribute("aria-current", isActive ? "true" : "false");
    });
}

previousButton.addEventListener("click", () => showSlide(currentSlide - 1));
nextButton.addEventListener("click", () => showSlide(currentSlide + 1));

dots.forEach((dot, index) => {
    dot.addEventListener("click", () => showSlide(index));
});

showSlide(0);
setInterval(() => showSlide(currentSlide + 1), 5000);
