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
    var activeIndex = 0;

    if (slides.length !== 3 || dots.length !== slides.length) {
        throw new Error('Onboarding requires exactly three slides and progress indicators.');
    }

    slidesContainer.classList.add('is-enhanced');

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
    }

    onboarding.querySelectorAll('[data-onboarding-next]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (activeIndex < slides.length - 1) {
                showSlide(activeIndex + 1);
            }
        });
    });

    showSlide(activeIndex);
})();
