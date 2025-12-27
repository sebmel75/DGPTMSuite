/**
 * Zeitschrift Kardiotechnik - Frontend JavaScript
 */

(function($) {
    'use strict';

    // Popup-Positionierung für Desktop
    function positionPopups() {
        $('.zk-card').each(function() {
            var $card = $(this);
            var $popup = $card.find('.zk-card-popup');

            if ($popup.length === 0) return;

            var cardOffset = $card.offset();
            var cardWidth = $card.outerWidth();
            var popupWidth = $popup.outerWidth();
            var windowWidth = $(window).width();

            // Prüfen ob Popup links oder rechts platziert werden soll
            var spaceLeft = cardOffset.left;
            var spaceRight = windowWidth - (cardOffset.left + cardWidth);

            if (spaceLeft < popupWidth + 20 && spaceRight >= popupWidth + 20) {
                // Nicht genug Platz links, zeige rechts
                $popup.css({
                    left: 'calc(100% + 15px)',
                    right: 'auto'
                });
            } else {
                // Zeige links (Standard)
                $popup.css({
                    right: 'calc(100% + 15px)',
                    left: 'auto'
                });
            }
        });
    }

    // Touch-Unterstützung für Mobile
    function initTouchSupport() {
        var touchStarted = false;

        $('.zk-card').on('touchstart', function(e) {
            var $card = $(this);

            // Wenn bereits aktiv, Link folgen
            if ($card.hasClass('zk-card-touched')) {
                return true;
            }

            // Alle anderen Karten deaktivieren
            $('.zk-card').removeClass('zk-card-touched');

            // Diese Karte aktivieren
            $card.addClass('zk-card-touched');
            touchStarted = true;

            // Popup zeigen auf Touch-Geräten
            $card.find('.zk-card-popup').css({
                'opacity': '1',
                'visibility': 'visible',
                'transform': 'translateX(0)'
            });

            e.preventDefault();
        });

        // Bei Klick außerhalb deaktivieren
        $(document).on('touchstart', function(e) {
            if (!$(e.target).closest('.zk-card').length) {
                $('.zk-card').removeClass('zk-card-touched');
                $('.zk-card-popup').css({
                    'opacity': '',
                    'visibility': '',
                    'transform': ''
                });
            }
        });
    }

    // Lazy Loading für Bilder
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        imageObserver.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px 0px'
            });

            document.querySelectorAll('.zk-card-thumbnail img[data-src]').forEach(function(img) {
                imageObserver.observe(img);
            });
        }
    }

    // Smooth Scroll für Zurück-Link
    function initSmoothScroll() {
        $('a[href^="#"]').on('click', function(e) {
            var target = $(this.hash);
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 100
                }, 500);
            }
        });
    }

    // Mobile Layout Detection
    function checkMobileLayout() {
        var isLandscape = window.matchMedia('(orientation: landscape)').matches;
        var isMobile = window.matchMedia('(max-width: 768px)').matches;

        if (isMobile && isLandscape) {
            $('.zk-grid-container').addClass('zk-mobile-landscape');
        } else {
            $('.zk-grid-container').removeClass('zk-mobile-landscape');
        }
    }

    // Initialisierung
    $(document).ready(function() {
        positionPopups();
        initTouchSupport();
        initLazyLoading();
        initSmoothScroll();
        checkMobileLayout();
    });

    // Bei Resize neu positionieren
    $(window).on('resize', function() {
        positionPopups();
        checkMobileLayout();
    });

    // Bei Orientierungsänderung
    $(window).on('orientationchange', function() {
        setTimeout(function() {
            positionPopups();
            checkMobileLayout();
        }, 100);
    });

})(jQuery);
