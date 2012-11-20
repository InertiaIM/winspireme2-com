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
    
    
    
    /* Home Banner */
    var homeBanner = $('#home-banner');
    
    $('.slide-tabs', homeBanner).tabs('#banner-slides > .banner-slide', {
    	effect: 'fade',
    	fadeOutSpeed: 'slow',
    	rotate: true,
    	autoplay: true,
    	clickable: false,
    	interval: 5000
    }).slideshow();
    var homeBannerAPI = $(".slide-tabs").data("slideshow");
    homeBannerAPI.play();
    
    
    
    /* Home Banner */