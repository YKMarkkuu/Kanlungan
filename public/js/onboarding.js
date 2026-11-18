(function () {
    'use strict';

    var onboarding = document.querySelector('[data-onboarding]');

    if (!onboarding) {
        return;
    }

    var slidesContainer = onboarding.querySelector('[data-onboarding-slides]');
    var slides = Array.prototype.slice.call(onboarding.querySelectorAll('[data-onboarding-slide]'));
    var dots = Array.prototype.slice.call(onboarding.querySelectorAll('[data-onboarding-dot]'));
    var status = onboarding.querySelector('[data-onboarding-status]');
    var nextButton = onboarding.querySelector('[data-onboarding-next]');
    var completeButton = onboarding.querySelector('[data-onboarding-complete]');
    var activeIndex = 0;

    if (!slidesContainer || !status || !nextButton || !completeButton ||
        slides.length < 2 || dots.length !== slides.length) {
        throw new Error('Onboarding requires at least two slides and one progress dot per slide.');
    }

    function showSlide(index) {
        slides.forEach(function (slide, slideIndex) {
            var active = slideIndex === index;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', String(!active));
            slide.inert = !active;
            dots[slideIndex].classList.toggle('is-active', active);
        });

        activeIndex = index;
        status.textContent = 'Step ' + (index + 1) + ' of ' + slides.length;
        nextButton.hidden = index === slides.length - 1;
        completeButton.hidden = index !== slides.length - 1;
    }

    nextButton.addEventListener('click', function (event) {
        if (event.target.closest && event.target.closest('.cm-onboarding-demo')) {
            return;
        }

        if (activeIndex < slides.length - 1) {
            slidesContainer.scrollBy({
                left: slidesContainer.offsetWidth,
                behavior: 'smooth'
            });
        }
    });

    slidesContainer.addEventListener('scroll', function () {
        var index = Math.round(slidesContainer.scrollLeft / slidesContainer.offsetWidth);
        showSlide(Math.max(0, Math.min(index, slides.length - 1)));
    }, { passive: true });

    showSlide(activeIndex);
})();
