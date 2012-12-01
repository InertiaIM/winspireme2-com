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
    
    
    /* Client Icons */
    $('#loved-by li').each(function() {
        // Replace the images with background layers
        // to allow for crossfades between b/w and color
        
        var image = $(this).find('img');
        var imageSrc = $(image).attr('src');
        
        $(image).replaceWith('<div class="bw" style="background-image:url(' + imageSrc  + ');">&nbsp;</div><div class="color" style="background-image:url(' + imageSrc + ');">&nbsp;</div>');
        
        $(this).on('mouseenter', function(e) {
            $(this).find('.bw').fadeOut(200);
            $(this).find('.color').fadeIn(200);
        });
        
        $(this).on('mouseleave', function(e) {
            $(this).find('.color').fadeOut(200);
            $(this).find('.bw').fadeIn(200);
        });
    });
    
    var loved = new Carousel($('#loved-by'), {
        behavior: {
            circular: true,
            autoplay: 0,
            keyboardNav: false
        },
        elements: {
            prevNext: false,
            handles: false,
            counter: false
        },
        visibleSlides: 5
    });
    loved.init();
    
    // TODO clean these up
    // globals are evil
    var speed = 0;
    var forward = true;
    var mode = 'stop';
    var interval = 0;
    var mousePosition = [-1, -1];
    
    $('#home-f-loved .carousel-container').on('mousemove', function(e) {
        mousePosition[0] = Math.ceil(e.pageX - $(this).offset().left);
        mousePosition[1] = Math.ceil(e.pageY - $(this).offset().top);
        
        var location = Math.ceil(e.pageX - $(this).offset().left);
        if(location < 0) location = 0;
        if(location > 750) location = 750;
        
        var newSpeed = 0;
        
        if(location <= 200) {
            forward = false;
            newSpeed = Math.ceil((1 - (location/200)) * 10);
        }
        
        if(location >= 550) {
            forward = true;
            newSpeed = Math.ceil(((location - 550) / 200) * 10);
        }
        
        if(speed != newSpeed) {
            speed = newSpeed;
            interval = Math.ceil(((-500 / 9) * (speed - 1)) + 900);
            
            if(forward && speed != 0) {
                if(mode != 'forward') {
                    mode = 'forward';
                    goForward();
                }
            }
            
            if(!forward && speed != 0) {
                if(mode != 'back') {
                    mode = 'back';
                    goBack();
                }
            }
            
            if(speed == 0) {
                goStop();
            }
        }
    });
    
    $('#home-f-loved .carousel-container').on('mouseleave', function(e) {
        mousePosition = [-1, -1];
        goStop();
    });
    
    function colorTrigger() {
        var index = Math.floor(mousePosition[0] / 150);
        $('#loved-by li').each(function(i) {
            if(index == i) {
                $(this).find('.bw').delay(interval / 2).fadeOut(200);
                $(this).find('.color').delay(interval / 2).fadeIn(200);
            }
            else {
                $(this).find('.color').delay(interval /2).fadeOut(200);
                $(this).find('.bw').delay(interval / 2).fadeIn(200);
            }
        });
    }
    
    function goBack() {
        if(speed != 0) {
            $('#loved-by').css({left: '-150px'});
            $('#loved-by').prepend($('#loved-by li:last-child').detach());
            
            colorTrigger();
            
            $('#loved-by').animate({left: '0'}, interval, 'linear', function() {
                goBack();
            });
        }
    }
    
    function goForward() {
        if(speed != 0) {
            $('#loved-by').css({left: '150px'});
            $('#loved-by').append($('#loved-by li:first-child').detach());
            
            colorTrigger();
            
            $('#loved-by').animate({left: '0'}, interval, 'linear', function() {
                goForward();
            });
        }
    }
    
    function goStop() {
        $('#loved-by li').each(function(i) {
            $(this).find('.color').stop(true, true).fadeOut(0);
            $(this).find('.bw').stop(true, true).fadeIn(0);
        });
        
        var remainder = (150 - Math.abs(Math.ceil($('#loved-by').position().left))) / 150;
        
        $('#loved-by').stop(true, false).animate({left: '0'}, Math.ceil(interval * remainder), 'linear');
        speed = 0;
        interval = 0;
        mode = 'stop';
    }
    /* Client Icons */
    
    
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
    
    
    
    
    
    
    
    
    /* Package List Show More */
    $('.f-g-sm').click(function() {
    	if ($(this).parent().find('ul').hasClass('more-shown')) {
    		$(this).find('.sm-label').html("Show More");
    		$(this).parent().find('ul').removeClass('more-shown');
    	} else {
    		$(this).find('.sm-label').html("Show Less");
    		$(this).parent().find('ul').addClass('more-shown');
    	}
    });
    
    
    /* Package List Show More */
    
    
    /* Package List Waypoints */
    $(document).ready(function() {
    	
    	if ($(".pl-items").length > 0) {
	    	var window = $(window), footer = $('footer'), opts = {offset: '100%'};
	    	footer.waypoint(function(event, direction) {
	    		/* Buffer Current Scroll Coordinates */
	    		var winleft = window.scrollLeft();
	            var wintop = window.scrollTop();
	    		
	            /* Reset Current Scroll Coordinates */
	            window.scrollLeft(0);
	            window.scrollTop(0);
	            
	            /* Remove Waypoint */
	            footer.waypoint('remove');

	            /* Do Things */
    			var plia = 3;
        		for (var i=0;i<plia;i++) { 
        			$('.pl-items').find('li:first').clone(true).appendTo('.pl-items');
        		}

        		/* Restore Current Scroll Coordinates */
                window.scrollLeft(winleft);
                window.scrollTop(wintop);
                
                /* Reset Waypoint */
        		footer.waypoint(opts);
	    	}, opts);
    	}
    });
    /* Package List Waypoints */
    

    /* Package List Search */
	    /* Context */
    	var plFilter = $('.pl-filter');
	    var plSearchBox = $('.search-text', plFilter);
	    var plSearchSubmit = $('.search-submit', plFilter);
	    /* Params */
	    var plBDefaultText = plSearchBox.attr("value");
	    /* Cursor goes in the Box */
	    plSearchBox.focus(function (e) {
	        $(this).addClass("active");
	        if ($(this).attr("value") == plBDefaultText) { $(this).attr("value", ""); }
	    });
	    plSearchBox.blur(function (e) {
	        $(this).removeClass("active");
	        if ($(this).attr("value") == "") { $(this).attr("value", plBDefaultText); }
	    });
	    /* search button gets clicked */
	    plSearchSubmit.click(function () {
	        if (plSearchBox.attr("value") == plBDefaultText) {
	            alert('Search clicked but value is default');
	        } else {
	            alert('Search for ' + plSearchBox.attr("value"));
	        }
	    });
	/* Package List Search */
    
    
    
    
    
    
    
    
    
    
    
    
    
    
