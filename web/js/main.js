var headerCtx = $('header')[0];
var footerCtx = $('footer')[0];


	/* Header Search */
	    /* Context */
	    var headerSearchBox = $('.search-text', headerCtx);
	    var headerSearchSubmit = $('.search-submit', headerCtx);
	    /* Params */
	    var hSBDefaultText = headerSearchBox.attr("value");
	    /* Cursor goes in the Box */
	    headerSearchBox.focus(function (e) {
	        $(this).addClass("active");
	        if ($(this).attr("value") == hSBDefaultText) { $(this).attr("value", ""); }
	    });
	    headerSearchBox.blur(function (e) {
	        $(this).removeClass("active");
	        if ($(this).attr("value") == "") { $(this).attr("value", hSBDefaultText); }
	    });
	    /* search button gets clicked */
	    headerSearchSubmit.click(function () {
	        if (headerSearchBox.attr("value") == hSBDefaultText) {
	            alert('Search clicked but value is default');
	        } else {
	            alert('Search for ' + headerSearchBox.attr("value"));
	        }
	    });
	/* Header Search */
    
    /* Header Navigation */
    var headerSiteNav = $('#site-nav', headerCtx);
    var headerSiteNavTabs = $('.has-sub');
    $('.nav-sub', headerSiteNav).hide();
    headerSiteNavTabs.parent().hover(
		function() { 
			$(this).find('.nav-sub').show();
	    },
	    function() {
	    	$(this).find('.nav-sub').hide();
	    }
    );    
    /* Header Navigation */
    
    /* Header Suitcase Panel */
    var headerSuitcasePanel = $('#suitcase-panel', headerCtx);
    var headerSuitcaseToggle = $('#suitcase-toggle', headerCtx);
    $(headerSuitcasePanel).hide();
    
    headerSuitcaseToggle.click( function() {
    	$(headerSuitcasePanel).show();
    });
    
    $('.the-tab', headerSuitcasePanel).click( function() {
    	$(headerSuitcasePanel).hide();
    });
    /* Header Suitcase Panel */
    
    
    
    
    /* Home Banner */    
    $('#home-banner .slide-tabs').tabs('#banner-slides > .banner-slide', {
    	effect: 'fade',
    	fadeOutSpeed: 'slow',
    	rotate: true,
    	autoplay: true,
    	clickable: false,
    	interval: 5000
    }).slideshow();
    /* Home Banner */
    
    /* Stats Banner */
    var stats = new Carousel($('#stat-slides'), {
        behavior: {
            circular: true,
            autoplay: 5000,
            keyboardNav: false
        },
        elements: {
            prevNext: false,
            handles: false,
            counter: false
        },
        events: {
            transition: function(index) {
                // TODO fix this ugly hack
                // Working around an issue with the carousel plugin
                // not returning the correct slide index
                var left = $('#stat-slides').css('left');
                var current;
                if (left == '0px') {
                    current = $('#stat-slides li:nth-child(1)').attr('id');
                }
                
                if (left == '-1040px') {
                    current = $('#stat-slides li:nth-child(2)').attr('id');
                }
                
                if (left == '-2080px') {
                    current = $('#stat-slides li:nth-child(3)').attr('id');
                }
                
                $('#stat-prev').removeClass('slide1 slide2 slide3').addClass(current);
                $('#stat-next').removeClass('slide1 slide2 slide3').addClass(current);
            }
        }
    });
    stats.init();
    
    $('#stats-panels .carousel-container').append('<div class="carousel-nav"><span id="stat-prev" class="carousel-prev slide1"></span><span id="stat-next" class="carousel-next slide1"></span></div>');
    
    $('#stat-prev').on('click', function(){
        stats.prev();
        stats.disable();
    });
    $('#stat-next').on('click', function(){
        stats.next();
        stats.disable();
    });
    /* Stats Banner */