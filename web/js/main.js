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
    var headerSiteNavTabs = $('.has-sub');
    
    // Find the widest column in the Experiences menu,
    // and force all columns to match.
    var maxWidth = 0;
    $('#nav-experiences').find('.panel-inner > ul > li').each(function() {
        var width = $(this).innerWidth();
        if(maxWidth < width) {
            maxWidth = width;
        }
    }).find('ul.sub-set').css('width', maxWidth + 'px');
    
    $('a.nav-tab').click(function(e) {
        e.preventDefault();
    });
    
    headerSiteNavTabs.parent().hover(
        function() { 
            $(this).find('.nav-sub').css('visibility', 'visible');
        },
        function() {
            $(this).find('.nav-sub').css('visibility', 'hidden');
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
    $(function() {
        $('#home-banner').homeslideshow({
            autoplay: true,
            interval: 10000
        });
    });
    /* Home Banner */
    
    /* Client Icons */
    $('#loved-by li').each(function() {
        // Replace the images with background layers
        // to allow for crossfades between b/w and color
        var image = $(this).find('img');
        var imageSrc = $(image).attr('src');
        
        $(image).replaceWith('<div class="bw" style="background-image:url(' + imageSrc  + ');">&nbsp;</div><div class="color" style="background-image:url(' + imageSrc + ');">&nbsp;</div>');
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
    
    $('#home-f-loved .carousel-container').on('mousemove', function(e) {
        var location = Math.ceil(e.pageX - $(this).offset().left);
        if(location < 0) location = 0;
        if(location > 750) location = 750;
        
        var newSpeed = 0;
        
        if(location <= 300) {
            forward = false;
            newSpeed = Math.ceil((1 - (location/300)) * 10);
        }
        
        if(location >= 450) {
            forward = true;
            newSpeed = Math.ceil(((location - 450) / 300) * 10);
        }
        
        if(speed != newSpeed) {
            speed = newSpeed;
            interval = Math.ceil(((-1000 / 9) * (speed - 1)) + 1800);
            
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
        goStop();
        colorTrigger('off');
    });
    
    $('#home-f-loved .carousel-container').on('mouseenter', function(e) {
        colorTrigger('on');
    });
    
    function colorTrigger(type) {
        var index = 2;
        var delay = (interval / 2);
        
        if (mode == 'back') {
            delay = 0;
        }
        
        if(type == 'on') {
        $('#loved-by li').each(function(i) {
            if(index == i) {
                $(this).find('.bw').delay(delay).fadeOut(200);
                $(this).find('.color').delay(delay).fadeIn(200);
            }
            else {
                $(this).find('.color').delay(delay).fadeOut(200);
                $(this).find('.bw').delay(delay).fadeIn(200);
            }
        });
        }
        else {
            $('#loved-by li').each(function(i) {
                $(this).find('.color').fadeOut(200);
                $(this).find('.bw').fadeIn(200);
            });
        }
    }
    
    function goBack() {
        if(speed != 0) {
            $('#loved-by').css({left: '-150px'});
            $('#loved-by').prepend($('#loved-by li:last-child').detach());
            
            colorTrigger('on');
            
            $('#loved-by').animate({left: '0'}, interval, 'linear', function() {
                goBack();
            });
        }
    }
    
    function goForward() {
        if(speed != 0) {
            $('#loved-by').css({left: '150px'});
            $('#loved-by').append($('#loved-by li:first-child').detach());
            
            colorTrigger('on');
            
            $('#loved-by').animate({left: '0'}, interval, 'linear', function() {
                goForward();
            });
        }
    }
    
    function goStop() {
        var remainder = (150 - Math.abs(Math.ceil($('#loved-by').position().left))) / 150;
        
        $('#loved-by').stop(true, false).animate({left: '0'}, Math.ceil(interval * remainder), 'linear');
        speed = 0;
        interval = 0;
        mode = 'stop';
    }
    /* Client Icons */
    
    
    /* Stats Banner */
    var stats = new Carousel($('#stat-slides'), {
        animation: {
            duration: 1500
        },
        behavior: {
            circular: true,
            autoplay: 7500,
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
    
    /* Misc */
    $('#home-f-testimonial').click(function(e) {
        window.location = $(this).find('a').attr('href');
    });
    
    $('#home-f-latestnews #latest-news li').hover(
        function(e) {
            $(this).find('a').addClass('hover');
        },
        function(e) {
            $(this).find('a').removeClass('hover');
        }
    );
    /* Misc */
    
    
    /* Package List Show More */
    $('.f-g-sm').click(function() {
        if ($(this).parent().find('ul').hasClass('more-shown')) {
            $(this).find('.sm-label').html('Show More');
            $(this).find('.sm-label').siblings('span.icon-arrow-up').removeClass('icon-arrow-up').addClass('icon-arrow-right');
            $(this).parent().find('ul').removeClass('more-shown');
        }
        else {
            $(this).find('.sm-label').html('Show Less');
            $(this).find('.sm-label').siblings('span.icon-arrow-right').removeClass('icon-arrow-right').addClass('icon-arrow-up');
            $(this).parent().find('ul').addClass('more-shown');
        }
    });
    /* Package List Show More */
    
    
    /* Package List Spinner */
    var target = $('.pl-results .row').get(1);
    var spinner = new Spinner({
        lines: 13,
        length: 11,
        width: 4,
        radius: 14,
        corners: 1.0,
        color: '#1280d6',
        rotate: 0,
        trail: 60,
        speed: 1.0,
        hwaccel: 'on',
        top: '0px',
    }).spin(target).stop();
    /* Package List Spinner */
    
    
    /* Package List Categories */
    $('input[name^="category"]').change(function(e) {
        // If this is a parent category, deselect all children.
        // Otherwise, if this is a child category, deselect the parent.
        var children = $(this).siblings('ul');
        if(children.size()) {
            $(children).find('input[name^="category"]').removeAttr('checked');
        }
        else {
            $(this).parent('li').parent('ul').siblings('input[name^="category"]').removeAttr('checked');
        }
        
        $('input[name^="category"]').siblings('label').removeClass('active');
        $('input[name^="category"]:checked').siblings('label').addClass('active');
        
        // Special condition for the "Domestic Travel" select interface
        var states = $(this).siblings('select').val();
        if(!$.isEmptyObject(states)) {
            $('#catState').multiselect('uncheckAll');
        }
        
        updatePackages();
    });
    
    $('select[name^="category"]').change(function(e) {
        // If selecting any of the states unders "US Travel,
        // the parent checkbox becomes inactive.
        var states = $(this).val();
        if(!$.isEmptyObject(states)) {
            $(this).siblings('input[name^="category"]').removeAttr('checked')
                .siblings('label').removeClass('active');
        }
        
        updatePackages();
    });
    
    $('select[name="sortOrder"]').change(function(e) {
        updatePackages();
    });
    
    
    $('.pl-sort ul li a').click(function(e) {
        e.preventDefault();
        
        $('.pl-sort ul li a').removeClass('selected');
        $(this).addClass('selected');
        
        updatePackages();
    });
    
    function updatePackages() {
        var url = '/packages.json';
        if (typeof env !== 'undefined') {
            url = env + url;
        }
        
        var categories = $('input[name^="category"]:checked').serialize();
        var categories2 = $('select[name^="category"]').serialize();
        if(categories == '' && categories2 == '') {
            $('#pl-link-all').addClass('active');
        }
        else {
            $('#pl-link-all').removeClass('active');
        }
        
        categories = categories + '&' + categories2;
        
        var filter = 'filter=' + $('.pl-sort ul li a.selected').attr('data-filter');
        var sort = $('select[name="sortOrder"]').serialize();
        $.ajax({
            beforeSend: function() {
                $('.pl-items').empty();
                spinner.spin(target);
            },
            url: url,
            data: categories + '&' + sort + '&' + filter,
            dataType: 'html',
            success: function(data, textStatus, jqXHR) {
                spinner.stop();
                $('.pl-items').append($(data));
            }
        });
    }
    
    
    /* Create custom select boxes */
    $(function() {
        var selectBox1 = $('select#sortOrder')
            .selectBoxIt({
                nostyle: false,
                downArrowIcon: 'icon-arrow-down'
            })
            .data('selectBoxIt');
        
        $('#catState').multiselect({
            classes: 'states',
            header: false,
            minWidth: '176',
            noneSelectedText: 'Select Destinations'
        });
    });
    
    /* Package List Category Filters */
    
    
    /* Package List Waypoints */
    $(document).ready(function() {
        
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
    
    
    /* Package Detail More Info */
    $('.d-i-mi', '.pd-detail').click(function() {
        if ($('.detail-info').hasClass('active')) {
            $(this).find('.d-i-ml').html('More');
            $('.detail-info').removeClass('active');
        }
        else {
            $(this).find('.d-i-ml').html('Less');
            $('.detail-info').addClass('active');
        }
    });
    /* Package Detail More Info */
    
    
    /* Package Detail Option Selected */
    $('input[name="variant"]').change(function(e) {
        var currentId = $('#pd-header').data('id');
        var newId = $(this).val();
        
        if (currentId != newId) {
            $('.srv-value').text($('#variant-holder #v' + newId).data('srv'));
            $('.npc-value').text($('#variant-holder #v' + newId).data('cost'));
            $('h3.name').text($('#variant-holder #v' + newId).data('name'));
            $('.pd-d-utilbar .pd-c-nightcount').text($('#variant-holder #v' + newId).data('accommodations'));
            $('.pd-d-utilbar .pd-c-airfare').text($('#variant-holder #v' + newId).data('airfares'));
            $('.pd-d-utilbar .pd-c-usercount').text($('#variant-holder #v' + newId).data('persons'));
            
            if ($('#variant-holder #v' + newId).find('.more-details').html() == '') {
                $('.d-i-mi').hide();
                $('.pd-details .detail-info').html('');
            }
            else {
                $('.d-i-mi').show();
                $('.pd-details .detail-info').html($('#variant-holder #v' + newId).find('.more-details').html());
            }
        }
        
        $('#pd-header').data('id', newId);
    });
    /* Package Detail Option Selected */
    
    
    /* Suitcase Preview */
    $('#suitcase-preview-header .toggle a').click(function(e) {
        e.preventDefault();
        
        if ($(this).find('span').hasClass('icon-double-up')) {
            $('#suitcase-preview').css('height', '226px');
            $('#suitcase-preview-content').css('top', '162px');
            $('#suitcase-preview-content').animate({top:'0px'}, function() {
                $('#suitcase-preview-header .toggle a').find('span').removeClass('icon-double-up').addClass('icon-double-down');
            });
        }
        else {
            $('#suitcase-preview-content').animate({top:'162px'}, function() {
                $('#suitcase-preview').css('height', '64px');
                $('#suitcase-preview-content').css('top', '0px');
                $('#suitcase-preview-header .toggle a').find('span').removeClass('icon-double-down').addClass('icon-double-up');
            });
        }
    });
    
    $(document).ready(function() {
        setupSuitcaseCycle();
        
        $('#suitcase-preview-items a').hover(function(e) {
            $(this).siblings('a').addClass('hover');
        }, function(e) {
            $(this).siblings('a').removeClass('hover');
        });
        
        $('.pl-items').on('click', '.i-a-add', function(e) {
            var button = $(this);
            var id = $(this).data('id');
            var url = '/suitcase/add/' + id;
            if (typeof env !== 'undefined') {
                url = env + url;
            }
            
            $.ajax({
                dataType: 'json',
                url: url,
                success: function(data, textStatus, jqXHR) {
                    if (!$.isEmptyObject(data)) {
                        $(button).attr('disabled', 'disabled');
                        
                        // Check whether we have a Cycle already running
                        if ($('.cycle-carousel-wrap')) { 
                            $('#suitcase-preview-items').cycle('destroy');
                        }
                        $('#suitcase-preview-items').prepend('<span><a href="#"><img src="/uploads/packages/' + data.thumb + '" alt="" width="129" height="85"></a><a href="#">' + data.title + '</a></span>');
                        $('#suitcase-preview-count').text('(' + data.count + ')');
                        
                        setupSuitcaseCycle();
                    }
                }
            });
        });
        
        function setupSuitcaseCycle() {
            if($('#suitcase-preview-items > span').length > 6) {
                $('#suitcase-preview-items').cycle({
                    autoHeight: -1,
                    allowWrap: false,
                    carouselVisible: 6,
                    fx: 'carousel',
                    next: '#suitcase-preview-next',
                    prev: '#suitcase-preview-prev',
                    slides: '> span',
                    timeout: 0
                });
            }
        }
    });
    /* Suitcase Preview */