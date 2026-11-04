<!-- Universal Scroll-Magic, Video Playback & Interactive Scroll-Scrub Engine -->
<style>
    [data-scroll-magic] {
        opacity: 0;
        transform: translateY(30px);
        transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        will-change: opacity, transform;
    }
    [data-scroll-magic].is-revealed {
        opacity: 1;
        transform: translateY(0);
    }
    /* Disable transitions for continuous scrub elements */
    [data-scroll-scrub="rotate"] .cms-bg-video,
    [data-scroll-scrub="rotate"] img,
    [data-scroll-scrub="parallax"] .cms-bg-video,
    [data-scroll-scrub="parallax"] img {
        transition: transform 0.1s linear;
        will-change: transform;
    }
</style>

<script nonce="{{ $cspNonce ?? '' }}">
(function() {
    'use strict';

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll('[data-scroll-magic]').forEach(function(el) {
            el.classList.add('is-revealed');
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
        return;
    }

    var ticking = false;
    var scrubElements = [];

    function initScrollMagic() {
        // 1. Intersection Observer for Scroll-Magic entrance animations & video viewport play/pause
        var revealObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                var el = entry.target;
                if (entry.isIntersecting) {
                    var effect = el.getAttribute('data-scroll-effect') || 'fadeInUp';
                    var delay = el.getAttribute('data-scroll-delay') || '0s';
                    var duration = el.getAttribute('data-scroll-duration') || '1s';

                    if (effect !== 'none') {
                        el.style.animationDelay = delay;
                        el.style.animationDuration = duration;
                        el.classList.add('animate__animated', 'animate__' + effect);
                    }
                    el.classList.add('is-revealed');

                    // Play background video on enter
                    var video = el.querySelector('video.cms-bg-video') || (el.tagName === 'VIDEO' ? el : null);
                    if (video && typeof video.play === 'function') {
                        var playPromise = video.play();
                        if (playPromise !== undefined) {
                            playPromise.catch(function() { /* Autoplay restriction fallback */ });
                        }
                    }
                } else {
                    // Pause video when out of viewport to optimize GPU & power
                    var video = el.querySelector('video.cms-bg-video') || (el.tagName === 'VIDEO' ? el : null);
                    if (video && typeof video.pause === 'function') {
                        video.pause();
                    }
                }
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -40px 0px'
        });

        document.querySelectorAll('[data-scroll-magic], [data-scroll-video="playback"]').forEach(function(el) {
            revealObserver.observe(el);
        });

        // 2. Collect scrub elements (rotate, parallax, scrub video)
        scrubElements = Array.prototype.slice.call(
            document.querySelectorAll('[data-scroll-scrub="rotate"], [data-scroll-scrub="parallax"], [data-scroll-scrub="playback"]')
        );

        if (scrubElements.length > 0) {
            updateScrubElements();
            window.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', onScroll, { passive: true });
        }
    }

    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(function() {
                updateScrubElements();
                ticking = false;
            });
            ticking = true;
        }
    }

    function updateScrubElements() {
        var vh = window.innerHeight || document.documentElement.clientHeight;

        for (var i = 0; i < scrubElements.length; i++) {
            var el = scrubElements[i];
            var rect = el.getBoundingClientRect();

            // Check if element is in or near viewport
            if (rect.bottom < -100 || rect.top > vh + 100) {
                continue;
            }

            // Calculate progress: 0 when top enters bottom of viewport, 1 when bottom leaves top of viewport
            var totalDistance = vh + rect.height;
            var currentPos = vh - rect.top;
            var progress = Math.max(0, Math.min(1, currentPos / totalDistance));

            var scrubMode = el.getAttribute('data-scroll-scrub');

            if (scrubMode === 'rotate') {
                // Rotate target (video, image or media container) from -180deg to +180deg (full 360deg spin)
                var angle = ((progress - 0.5) * 360).toFixed(2);
                var mediaTarget = el.querySelector('.cms-bg-video') || el.querySelector('img') || el.querySelector('.scroll-rotate-target') || el;
                if (mediaTarget) {
                    mediaTarget.style.transform = 'rotate(' + angle + 'deg) scale(1.2)';
                }
            } else if (scrubMode === 'parallax') {
                // Parallax shift from -40px to +40px
                var shift = ((progress - 0.5) * 80).toFixed(2);
                var mediaTarget = el.querySelector('.cms-bg-video') || el.querySelector('img') || el;
                if (mediaTarget) {
                    mediaTarget.style.transform = 'translate3d(0, ' + shift + 'px, 0) scale(1.08)';
                }
            } else if (scrubMode === 'playback') {
                // Link video playback directly to scroll position
                var video = el.querySelector('video.cms-bg-video') || (el.tagName === 'VIDEO' ? el : null);
                if (video && video.duration && !isNaN(video.duration)) {
                    video.currentTime = progress * video.duration;
                }
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initScrollMagic);
    } else {
        initScrollMagic();
    }
})();
</script>
