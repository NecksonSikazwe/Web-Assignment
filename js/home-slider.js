/* Homepage service slider: controls, auto-advance, and accessible slide state. */
(function () {
  'use strict';

  // Select only the homepage slider and its controls; other pages need no slider markup.
  var slider = document.querySelector('.home-slider');
  if (!slider) return;

  // The hero sections are slides; each dot and arrow controls this same collection.
  var slides = Array.from(slider.querySelectorAll(':scope > .home-hero'));
  var controls = slider.querySelector('.home-slider-controls');
  var dots = Array.from(slider.querySelectorAll('[data-slide-to]'));
  var status = slider.querySelector('.home-slider-status');
  var previousButton = slider.querySelector('[data-slide-previous]');
  var nextButton = slider.querySelector('[data-slide-next]');

  // Leave the original hero visible if the HTML controls do not match the slide count.
  if (slides.length < 2 || !controls || dots.length !== slides.length) return;

  // Store the active slide, sole autoplay timer, motion preference, and touch start point.
  var activeIndex = 0;
  var timerId = null;
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var touchStart = null;

  // Keep the slide and its matching dot in sync; the modulo wraps at both ends.
  function showSlide(index, announce) {
    activeIndex = (index + slides.length) % slides.length;

    // Hide inactive slides from display and assistive technology and mark the active dot.
    slides.forEach(function (slide, slideIndex) {
      var isActive = slideIndex === activeIndex;
      slide.hidden = !isActive;

      if (isActive) {
        slide.removeAttribute('aria-hidden');
      } else {
        slide.setAttribute('aria-hidden', 'true');
      }

      dots[slideIndex].setAttribute('aria-current', isActive ? 'true' : 'false');
    });

    if (announce && status) {
      var heading = slides[activeIndex].querySelector('h1, h2');
      status.textContent = 'Slide ' + (activeIndex + 1) + ' of ' + slides.length +
        (heading ? ': ' + heading.textContent.replace(/\s+/g, ' ').trim() : '');
    }
  }

  // Clear the existing interval before resuming so there can never be duplicate timers.
  function stopAutoplay() {
    if (timerId !== null) {
      window.clearInterval(timerId);
      timerId = null;
    }
  }

  // Store one interval ID so repeated resume events cannot create extra timers.
  function startAutoplay() {
    if (timerId !== null || reduceMotion.matches || document.hidden ||
        slider.matches(':hover') || slides[activeIndex].contains(document.activeElement)) return;
    timerId = window.setInterval(function () {
      showSlide(activeIndex + 1, false);
    }, 5000);
  }

  function selectSlide(index) {
    // Manual selection announces the new slide and restarts the single autoplay interval.
    showSlide(index, true);
    stopAutoplay();
    startAutoplay();
  }

  // Reveal controls only after initialization, leaving the original hero as fallback.
  controls.hidden = false;
  if (status) status.hidden = false;
  showSlide(0, false);
  startAutoplay();

  // Arrow buttons move one slide in either direction, including across the loop boundary.
  previousButton.addEventListener('click', function () {
    selectSlide(activeIndex - 1);
  });

  nextButton.addEventListener('click', function () {
    selectSlide(activeIndex + 1);
  });

  // Each dot selects its matching slide and updates the current indicator.
  dots.forEach(function (dot) {
    dot.addEventListener('click', function () {
      selectSlide(Number(dot.getAttribute('data-slide-to')));
    });
  });

  // Pause motion while the pointer is over the slider or focus is inside a slide link.
  slider.addEventListener('pointerenter', stopAutoplay);
  slider.addEventListener('pointerleave', startAutoplay);
  slider.addEventListener('focusout', function (event) {
    var focusedSlide = slides.find(function (slide) {
      return slide.contains(event.target);
    });

    if (focusedSlide && !focusedSlide.contains(event.relatedTarget)) startAutoplay();
  });
  slider.addEventListener('focusin', function (event) {
    if (slides.some(function (slide) {
      return slide.contains(event.target);
    })) {
      stopAutoplay();
    }
  });

  // Arrow keys work while the slider has focus; modifier shortcuts are left untouched.
  slider.addEventListener('keydown', function (event) {
    if (event.altKey || event.ctrlKey || event.metaKey) return;

    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      selectSlide(activeIndex - 1);
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      selectSlide(activeIndex + 1);
    }
  });

  // Detect horizontal touch swipes without blocking normal vertical page scrolling.
  slides.forEach(function (slide) {
    slide.addEventListener('pointerdown', function (event) {
      if (event.pointerType !== 'touch' || event.target.closest('a, button')) return;
      touchStart = { x: event.clientX, y: event.clientY };
    });
  });

  slider.addEventListener('pointerup', function (event) {
    if (!touchStart || event.pointerType !== 'touch') return;

    var distanceX = event.clientX - touchStart.x;
    var distanceY = event.clientY - touchStart.y;
    touchStart = null;

    if (Math.abs(distanceX) < 50 || Math.abs(distanceX) <= Math.abs(distanceY)) return;
    selectSlide(activeIndex + (distanceX < 0 ? 1 : -1));
  });

  slider.addEventListener('pointercancel', function () {
    touchStart = null;
  });

  // Avoid advancing slides in a background tab, then resume when the page is visible.
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      stopAutoplay();
    } else {
      startAutoplay();
    }
  });

  // Reduced-motion users can still change slides manually with the controls.
  reduceMotion.addEventListener('change', function () {
    if (reduceMotion.matches) {
      stopAutoplay();
    } else {
      startAutoplay();
    }
  });
})();
