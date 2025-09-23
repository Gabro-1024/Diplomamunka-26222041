$(function () {

    // Header Scroll
    $(window).scroll(function () {
        if ($(window).scrollTop() >= 60) {
            $("header").addClass("fixed-header");
        } else {
            $("header").removeClass("fixed-header");
        }
    });


    // Featured Owl Carousel
    $('.featured-projects-slider .owl-carousel').owlCarousel({
        center: true,
        loop: true,
        margin: 30,
        nav: false,
        dots: false,
        autoplay: true,
        autoplayTimeout: 5000,
        autoplayHoverPause: false,
        responsive: {
            0: {
                items: 1
            },
            600: {
                items: 2
            },
            1000: {
                items: 3
            },
            1200: {
                items: 4
            }
        }
    })


    // Count
    $('.count').each(function (index) {
        const $counter = $(this);
        const initialText = $counter.text();
        const targetValue = $counter.attr('data-target') || initialText;
        
        // console.log(`[Counter #${index}] Initial setup:`, {
        //     element: $counter[0],
        //     initialText,
        //     targetValue,
        //     parsedValue: parseFloat(targetValue)
        // });
        
        $counter.prop('Counter', 0).animate({
            Counter: parseFloat(targetValue)
        }, {
            duration: 2000,
            easing: 'swing',
            step: function(now) {
                const current = Math.ceil(now);
                // console.log(`[Counter #${index}] Step:`, {
                //     now,
                //     current,
                //     elementText: $counter.text(),
                //     element: this
                // });
                $counter.text(current);
            },
            complete: function() {
                // console.log(`[Counter #${index}] Complete:`, {
                //     finalText: $counter.text(),
                //     expectedFinal: targetValue,
                //     element: this
                // });
            }
        });
    });


    // ScrollToTop
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    const btn = document.getElementById("scrollToTopBtn");
    btn.addEventListener("click", scrollToTop);

    window.onscroll = function () {
        const btn = document.getElementById("scrollToTopBtn");
        if (document.documentElement.scrollTop > 100 || document.body.scrollTop > 100) {
            btn.style.display = "flex";
        } else {
            btn.style.display = "none";
        }
    };


    // AOS (Animate On Scroll) Initialization
	AOS.init({
		once: true,
	});

});

