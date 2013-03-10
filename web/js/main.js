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
    
    headerSuitcasePanel.find('form').submit(function(e) {
//        e.preventDefault();
//        e.stopPropagation();
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
        if ($('.more-detail').hasClass('active')) {
            $(this).find('.d-i-ml').html('More');
            $('.more-detail').removeClass('active');
        }
        else {
            $(this).find('.d-i-ml').html('Less');
            $('.more-detail').addClass('active');
        }
    });
    /* Package Detail More Info */
    
    
    /* Package Detail Option Selected */
    $('input[name="variant"]').change(function(e) {
        var currentId = $('#pd-header').data('id');
        var newId = $(this).val();
        
        if (currentId != newId) {
            $('.pd-a-add').attr('data-id', newId);
            
            if($('#suitcase-preview-items').find('.suitcase-preview-item[data-id="' + newId + '"]').length > 0) {
                $('.pd-a-add').addClass('disabled');
            }
            else {
                $('.pd-a-add').removeClass('disabled');
            }
            
            $('.srv-value').text($('#variant-holder #v' + newId).data('srv'));
            $('.npc-value').text($('#variant-holder #v' + newId).data('cost'));
            $('h3.name').text($('#variant-holder #v' + newId).data('name'));
            $('.pd-d-utilbar .pd-c-nightcount').text($('#variant-holder #v' + newId).data('accommodations'));
            $('.pd-d-utilbar .pd-c-airfare').text($('#variant-holder #v' + newId).data('airfares'));
            $('.pd-d-utilbar .pd-c-usercount').text($('#variant-holder #v' + newId).data('persons'));
            $('.pd-details .detail').html($('#variant-holder #v' + newId).find('.detail').html());
            
            if ($('#variant-holder #v' + newId).find('.more-detail').html() == '') {
                $('.d-i-mi').hide();
                $('.pd-details .more-detail').html('');
            }
            else {
                $('.d-i-mi').show();
                $('.pd-details .more-detail').html($('#variant-holder #v' + newId).find('.more-detail').html());
            }
        }
        
        $('#pd-header').data('id', newId);
    });
    /* Package Detail Option Selected */
    
    
    /* Suitcase Preview */
    $('body').on('click', '#suitcase-preview #suitcase-preview-header', function(e) {
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
    
    $('body').on('click', '#suitcase-preview-header .header-nav li.share a, #suitcase-preview-header .header-nav li.comments a, #suitcase-preview-header .header-nav li.button a', function(e) {
        e.stopPropagation();
    });
    
    $('body').on('click', '#suitcase-preview-header .header-nav li.toggle a', function(e) {
        e.preventDefault();
    });
    
    $(document).ready(function() {
        setupSuitcaseCycle();
        
        $('body')
        .on('mouseenter mouseleave', '#suitcase-preview-items a', function(e) {
            $(this).siblings('a').toggleClass('hover');
        });
        
        $('body')
        .on('mouseenter mouseleave', '#suitcase-preview-items a.preview-item-image', function(e) {
            $(this).find('.preview-item-delete').fadeToggle('fast');
            $(this).find('.preview-item-info').slideToggle('fast');
        });
        
        $('body')
        .on('click', '#suitcase-preview-items .preview-item-delete', function(e) {
            e.preventDefault();
            
            var item = $(this).parents('.suitcase-preview-item');
            var id = $(item).data('id');
            var url = '/suitcase/delete/' + id;
            if (typeof env !== 'undefined') {
                url = env + url;
            }
            
            $.ajax({
                dataType: 'json',
                url: url,
                success: function(data, textStatus, jqXHR) {
                    if (!$.isEmptyObject(data) && data.deleted) {
                        var button = $('button[data-id="' + id + '"]');
                        
                        $(button).removeAttr('disabled');
                        
                        // Check whether we have a Cycle already running
                        if ($('.cycle-carousel-wrap')) { 
                            $('#suitcase-preview-items').cycle('destroy');
                        }
                        
                        $(item).remove();
                        
                        $('.pd-a-add[data-id="' + id + '"]').removeClass('disabled');
                        
                        $('#suitcase-preview-count').text('(' + data.count + ')');
                        $('#core-suitcase-button').find('.count').text('(' + data.count + ')');
                        
                        setupSuitcaseCycle();
                    }
                }
            });
        });
        
        
        function addToSuitcase(id, el) {
            // The existence of the account modal means that
            // the user is currently not authenticated
            if($('#account-modal').length > 0) {
                // Pass the desired package id into the hidden form field
                // and open our modal form window to create a new account
                $('#fos_user_registration_form_package').val(id);
                $("#account-modal").
                    attr('data-id', id).
                    modal({
                        closeText: 'X',
                        overlay: '#fff',
                        opacity: 0.73,
                        zIndex: 2002
                    });
            }
            else {
                var url = '/suitcase/add/' + id;
                if (typeof env !== 'undefined') {
                    url = env + url;
                }
                
                $.ajax({
                    dataType: 'json',
                    url: url,
                    success: function(data, textStatus, jqXHR) {
                        if (!$.isEmptyObject(data)) {
                            $(el).addClass('disabled');
                            
                            // Check whether we have a Cycle already running
                            if ($('.cycle-carousel-wrap')) { 
                                $('#suitcase-preview-items').cycle('destroy');
                            }
                            
                            $('#suitcase-preview-header .button a')
                                .removeClass('locked')
                                .find('span.icon')
                                .removeClass('icon-suitcase-locked')
                                .addClass('icon-suitcase');
                            
                            $('#core-suitcase-button')
                                .removeClass('locked')
                                .find('span.icon')
                                .removeClass('icon-suitcase-locked')
                                .addClass('icon-suitcase');
                            
                            $('#core-suitcase-button').find('.count').text('(' + data.count + ')');
                            $('#suitcase-preview-count').text('(' + data.count + ')');
                            
                            $('#suitcase-preview-items').prepend(Twig.render(previewItem, {item: data.item}));
                            
                            setupSuitcaseCycle();
                        }
                    }
                });
            }
        }
        
        
        
        $('.pd-a-add').on('click', function(e) {
            e.preventDefault();
            var id = $(this).attr('data-id');
            var self = this;
            
            addToSuitcase(id, self);
        });
        
        
        $('.pl-items').on('click', '.i-a-add', function(e) {
            var button = $(this);
            var id = $(this).attr('data-id');
            
            // The existence of the account modal means that
            // the user is currently not authenticated
            if($('#account-modal').length > 0) {
                // Pass the desired package id into the hidden form field
                // and open our modal form window to create a new account
                $('#fos_user_registration_form_package').val(id);
                $("#account-modal").
                    attr('data-id', id).
                    modal({
                        closeText: 'X',
                        overlay: '#fff',
                        opacity: 0.73,
                        zIndex: 2002
                    });
            }
            else {
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
                            
                            $('#suitcase-preview-header .button a')
                                .removeClass('locked')
                                .find('span.icon')
                                .removeClass('icon-suitcase-locked')
                                .addClass('icon-suitcase');
                            
                            $('#core-suitcase-button')
                                .removeClass('locked')
                                .find('span.icon')
                                .removeClass('icon-suitcase-locked')
                                .addClass('icon-suitcase');
                            
                            $('#core-suitcase-button').find('.count').text('(' + data.count + ')');
                            $('#suitcase-preview-count').text('(' + data.count + ')');
                            
                            $('#suitcase-preview-items').prepend(Twig.render(previewItem, {item: data.item}));
                            
                            setupSuitcaseCycle();
                        }
                    }
                });
            }
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
            else {
                $('#suitcase-preview-prev').addClass('disabled');
                $('#suitcase-preview-next').addClass('disabled');
            }
        }
    });
    /* Suitcase Preview */
    
    
    
/* Suitcase */
$(document).ready(function() {
    var sc = $('#sc-area');
    
//    $(sc).find('.content').on('click', '.item-close', function(e) {
//        e.preventDefault();
//        e.stopPropagation();
//        
//        var item = $(this).parent().parent('.package');
//        var container = $(item).parent('li');
//        var all = $(sc).find('.content > ul').find('li > ul > li');
//        
//        var i = $(all).index(container);
//        var r = Math.floor(i / 3);
//        var c = (i % 3);
//        var left = ((300 * c) + 30);
//        
//        var row = $(all).slice((r * 3), (r * 3) + 3);
//        var not = $(row).not(container);
//        
//        if($(item).hasClass('open')) {
//            $(not).find('.flag').show();
//            $(not).find('.package').addClass('drop-shadow');
//            $(item).removeClass('drop-shadow expanded open');
//            
//            $(item).find('.expanded').fadeOut(0, function() {
//                $(item).animate({width: 206 + 'px'}, 'fast');
//                $(container).animate({left: left + 'px'}, 'fast', function() {
//                    $(container).find('h4').show();
////                    $(container).find('.item-open').show();
//                    $(item).addClass('drop-shadow');
//                });
//            });
//        }
//    });
    
    $(sc).find('.content').on('mouseenter', '.package', function(e) {
        var item = $(this);
        var open = $(this).find('.item-open');
        
        if(!$(item).hasClass('open')) {
            $(open).fadeIn('fast');
        }
    });
    $(sc).find('.content').on('mouseleave', '.package', function(e) {
        var item = $(this);
        var open = $(this).find('.item-open');
        
        $(open).fadeOut('fast');
    });
    
    $(sc).find('.content').on('click', '.package', function(e) {
        
//      var item = $(this).parent().parent('.package');
//      var container = $(item).parent('li');
//      var all = $(sc).find('.content > ul').find('li > ul > li');
//      
//      var i = $(all).index(container);
//      var r = Math.floor(i / 3);
//      var c = (i % 3);
//      var left = ((300 * c) + 30);
//      
//      var row = $(all).slice((r * 3), (r * 3) + 3);
//      var not = $(row).not(container);
//      
//      if($(item).hasClass('open')) {
//          $(not).find('.flag').show();
//          $(not).find('.package').addClass('drop-shadow');
//          $(item).removeClass('drop-shadow expanded open');
//          
//          $(item).find('.expanded').fadeOut(0, function() {
//              $(item).animate({width: 206 + 'px'}, 'fast');
//              $(container).animate({left: left + 'px'}, 'fast', function() {
//                  $(container).find('h4').show();
////                  $(container).find('.item-open').show();
//                  $(item).addClass('drop-shadow');
//              });
//          });
//      }
        
        
        
        
        
        
        
        
        var item = $(this);
        var container = $(this).parent('li');
        var all = $(sc).find('.content > ul').find('li > ul > li');
        
        var i = $(all).index(container);
        var r = Math.floor(i / 3);
        var c = (i % 3);
        var left = ((300 * c) + 30);
        
        var row = $(all).slice((r * 3), (r * 3) + 3);
        var not = $(row).not(container);
        
        if(!$(item).hasClass('open')) {
            $(not).css({zIndex:9}).find('.flag').hide();
            $(row).find('.package').removeClass('drop-shadow');
            $(container).css({zIndex:10});
            
            $(container).find('h4').hide();
            $(container).find('.item-open').hide();
            
            $(item).animate({width: 806}, 'fast', function() {
                $(this).addClass('drop-shadow expanded open');
                $(this).find('.expanded').fadeIn('fast');
            });
            $(container).animate({left: 30}, 'fast');
        }
        else {
            $(not).find('.flag').show();
            $(not).find('.package').addClass('drop-shadow');
            $(item).removeClass('drop-shadow expanded open');
            
            $(item).find('.expanded').fadeOut(0, function() {
                $(item).animate({width: 206 + 'px'}, 'fast');
                $(container).animate({left: left + 'px'}, 'fast', function() {
                    $(container).find('h4').show();
                    $(item).addClass('drop-shadow');
                });
            });
        }
    });
    
    
    $(sc).find('.content').on('click', '.actions > .more', function(e) {
        e.stopPropagation();
    });
    
    $(sc).find('.content').on('click', '.actions > .download', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });
    
    $(sc).find('.content').on('click', '.actions > .delete', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var id = $(this).parent('.actions').data('id');
        
        // Delete a package from the session
        var container = $('li[data-id="' + id + '"]');
        var url = '/suitcase/delete/' + id;
        if (typeof env !== 'undefined') {
            url = env + url;
        }
        
        // bring back the hidden elements from the common row
        if ($('.cycle-carousel-wrap')) { 
            $('#sc-area .content > ul').cycle('destroy');
        }
        
        var all = $(sc).find('.content > ul').find('li > ul > li');
        var i = $(all).index(container);
        var r = Math.floor(i / 3);
        var c = (i % 3);
        
        var row = $(all).slice((r * 3), (r * 3) + 3);
        var not = $(row).not(container);
        
        $(not).find('.flag').show();
        $(not).find('.package').addClass('drop-shadow');
        
        
        // gather all the remaining elements to reorder
        all = $(sc).find('.content > ul').find('li > ul > li');
        
        $(all).unwrap().unwrap();
        
        // remove the deleted element
        container.remove();
        
        all = $(sc).find('.content > ul > li');
        $(all).removeClass().removeAttr('style');
        
        for(var i = 0; i <= all.length; i = i + 6) {
            var slice = $(all).slice(i, i + 6);
            $(slice).wrapAll('<li class="clearfix suitcase-page"/>').wrapAll('<ul/>');
        }
        
        var count = 1;
        $(all).each(function(i, e) {
            $(e).addClass('p' + count);
            
            count++;
            if(count > 6) {
                count = 1;
            }
        });
        
        setupSuitcaseCycle();
        
        $.ajax({
            dataType: 'json',
            url: url,
            success: function(data, textStatus, jqXHR) {
                if (!$.isEmptyObject(data) && data.deleted) {
                    $('.key').find('.definitely').text(data.counts['D'] + data.counts['E']);
                    $('.key').find('.maybe').text(data.counts['M']);
                    $('.key').find('.recommended').text(data.counts['R']);
                    var items = ' item';
                    if(data.count != 1) {
                        items = items + 's';
                    }
                    $('.key').find('.suitcase-count').text(data.count + items);
                    
                    $('.unpacked').show();
                    $('.packed').hide();
                    
                    $('#core-suitcase-button')
                        .removeClass('locked')
                        .find('span.icon')
                        .removeClass('icon-suitcase-locked')
                        .addClass('icon-suitcase');
                    $('#core-suitcase-button').find('.count').text('(' + data.count + ')');
                    $('#more-modal').find('.suitcase-count').text(data.count);
                    $('#ready').find('.suitcase-count').text(data.count);
                }
            }
        });
    });
    
    $(sc).find('.content').on('click', 'li .flag', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if($(this).hasClass('active')) {
            var flag = $(this);
            var id = $(flag).data('id');
            var url = '/suitcase/flag/' + id;
            if (typeof env !== 'undefined') {
                url = env + url;
            }
            
            $.ajax({
                beforeSend: function() {
                    var status = $(flag).attr('data-status');
                    var newStatus = false;
                    var newClass = false;
                    switch(status) {
                    case 'M':
                        newStatus = 'D';
                        newClass = 'definitely';
                        break;
                    case 'R':
                        newStatus = 'E';
                        newClass = 'definitely';
                        break;
                    case 'D':
                        newStatus = 'M';
                        newClass = 'maybe';
                        break;
                    case 'E':
                        newStatus = 'R';
                        newClass = 'recommended';
                        break;
                    }
                    
                    $(flag)
                        .removeClass('definitely maybe recommended')
                        .addClass(newClass)
                        .attr('data-status', newStatus);
                },
                dataType: 'json',
                url: url,
                success: function(data, textStatus, jqXHR) {
                    if (!$.isEmptyObject(data)) {
                        $('.key').find('.definitely').text(data.counts['D'] + data.counts['E']);
                        $('.key').find('.maybe').text(data.counts['M']);
                        $('.key').find('.recommended').text(data.counts['R']);
                    }
                }
            });
        }
    });
    
    
    // Suitcase social
    $(sc).find('.share a').on('click', function(e) {
        e.preventDefault();
        
        $('#share-modal').modal({
            closeText: 'X',
            overlay: '#fff',
            opacity: 0.73,
            zIndex: 2002
        });
        
        $('#share-modal form input#share_name_1').focus();
    });
    
    
    $('#share-form').validate({
        rules: {
            'share[name][1]': {
                required: '#share_email_1:filled'
            },
            'share[name][2]': {
                required: '#share_email_2:filled'
            },
            'share[name][3]': {
                required: '#share_email_3:filled'
            },
            'share[name][4]': {
                required: '#share_email_4:filled'
            },
            'share[email][1]': {
                email: true,
                required: '#share_name_1:filled'
            },
            'share[email][2]': {
                email: true,
                required: '#share_name_2:filled'
            },
            'share[email][3]': {
                email: true,
                required: '#share_name_3:filled'
            },
            'share[email][4]': {
                email: true,
                required: '#share_name_4:filled'
            }
        },
        messages: {
            'share[email][1]': 'Please enter a valid email address',
            'share[name][1]': 'Please enter a name',
            'share[email][2]': 'Please enter a valid email address',
            'share[name][2]': 'Please enter a name',
            'share[email][3]': 'Please enter a valid email address',
            'share[name][3]': 'Please enter a name',
            'share[email][4]': 'Please enter a valid email address',
            'share[name][4]': 'Please enter a name'
        },
        submitHandler: function(form) {
          var data = $(form).serialize();
          $.ajax({
              beforeSend: function() {
                  $('#share-modal #share-result #share-result-successes').hide();
                  $('#share-modal #share-result #share-result-errors').hide();
              },
              data: data,
              dataType: 'json',
              url: $(form).attr('action'),
              success: function(data, textStatus, jqXHR) {
                  if (!$.isEmptyObject(data)) {
                      if ($.isEmptyObject(data.formerror)) {
                          
                          $('#share-modal #share-form-holder').hide();
                          $('#share-modal #share-result-holder').show();
                          
                          if (!$.isEmptyObject(data.successes)) {
                              $('#share-modal #share-result-holder #share-result-successes').show();
                              $('#share-modal #share-result-holder .successes li').remove();
                              $.each(data.successes, function(index, value) {
                                  $('#share-modal #share-result-holder .successes').append('<li><span class="number">' + (index + 1) + '</span><span class="name">' + value.name + '</span><span class="email">'   + value.email + '</span></li>')
                              });
                          }
                          
                          if (!$.isEmptyObject(data.errors)) {
                              $('#share-modal #share-result-holder #share-result-errors').show();
                              $('#share-modal #share-result-holder #share-result-errors .errors li').remove();
                              $.each(data.errors, function(index, value) {
                                  $('#share-modal #share-result-holder #share-result-errors .errors').append('<li>' + value.email + ' &gt;  This person has already been invited. &nbsp;<a href="#" data-id="' + value.id + '">Resend invite.</a></li>')
                              });
                          }
                      }
                      else {
                          // Server-side form errors
                      }
                  }
              },
              type: 'POST'
          });
        }
    });
    
    $('#share-modal #share-result-errors').on('click', 'a', function(e) {
        e.preventDefault();
        
        var self = $(this);
        var parent = $(this).parent();
        var id = $(self).data('id');
        
        var url = '/suitcase/reshare/' + id;
        if (typeof env !== 'undefined') {
            url = env + url;
        }
        
        $.ajax({
            beforeSend: function() {
                $(self).replaceWith('...');
            },
            dataType: 'json',
            url: url,
            success: function(data, textStatus, jqXHR) {
                if (!$.isEmptyObject(data)) {
                    $(parent).append('Resent!');
                }
            }
        });
    });
    
    $('#share-modal #share-more').on('click', function(e) {
        e.preventDefault();
        
        $('#share-modal form input[type=text]').val('');
        
        $('#share-modal #share-form-holder').show();
        $('#share-modal form input#share_name_1').focus();
        
        $('#share-modal #share-result-holder').hide();
    });
    
    
    
    
    
    $('#sc-area .content > ul').on('cycle-after', function(event, opts) {
        $('#sc-area').find('.pager-current').text(opts.slideNum);
        $('#sc-area').find('.pager-total').text(opts.slideCount);
    });
    
    $('button#ready').on('click', function(e) {
        var maybeCount = $('a[data-status="M"], a[data-status="R"]').length;
        
        if(maybeCount > 0) {
            $('#reminder-modal').modal({
                closeText: 'X',
                overlay: '#fff',
                opacity: 0.73,
                zIndex: 2002
            });
        }
        else {
            $('#more-modal').modal({
                closeText: 'X',
                overlay: '#fff',
                opacity: 0.73,
                zIndex: 2002
            });
        }
    });
    
    $('button#approve-all').on('click', function(e) {
        var maybes = $('a[data-status="M"], a[data-status="R"]');
        
        var ids = [];
        
        $(maybes).each(function() {
            $(this).removeClass('maybe recommended').addClass('definitely');
            
            var id = $(this).attr('data-id');
            var status = $(this).attr('data-status');
            var newStatus;
            switch(status) {
            case 'M':
                newStatus = 'D';
                break;
            case 'R':
                newStatus = 'E';
                break;
            }
            $(this).attr('data-status', newStatus);
            
            ids.push({ status: newStatus, id: id });
        });
        
        var url = '/suitcase/flags';
        if (typeof env !== 'undefined') {
            url = env + url;
        }
        
        $.ajax({
            data: {ids: ids},
            dataType: 'json',
            url: url,
            success: function(data, textStatus, jqXHR) {
                if (!$.isEmptyObject(data)) {
                    $('.key').find('.definitely').text(data.counts['D'] + data.counts['E']);
                    $('.key').find('.maybe').text(data.counts['M']);
                    $('.key').find('.recommended').text(data.counts['R']);
                }
            }
        });
        
        $.modal.close();
        
        $('#more-modal').modal({
            closeText: 'X',
            overlay: '#fff',
            opacity: 0.73,
            zIndex: 2002
        });
    });
    
    
    
    
    /* More Modal handler */
    $('#more-modal form').on('submit', function(e) {
        e.preventDefault();
        
        var data = $(this).serialize();
        
        $.ajax({
            beforeSend: function() {
                $('#more-modal .error').removeClass('error');
            },
            data: data,
            dataType: 'json',
            url: $(this).attr('action'),
            success: function(data, textStatus, jqXHR) {
                if (!$.isEmptyObject(data)) {
                    if (!$.isEmptyObject(data.errors)) {
                        $.each(data.errors, function(index, value) {
                            $('#' + index).addClass('error');
                            $('#more-modal label[for="' + index + '"]').addClass('error');
                        });
                    }
                    else {
                        if(data.packed) {
                            $.modal.close();
//                            $('#more-info').remove();
                            
                            $('.unpacked').hide();
                            $('.packed').show();
                            
                            $('#core-suitcase-button')
                                .addClass('locked')
                                .find('span.icon')
                                .removeClass('icon-suitcase')
                                .addClass('icon-suitcase-locked');
                            
                            $('#thanks-modal').modal({
                                closeText: 'X',
                                overlay: '#fff',
                                opacity: 0.73,
                                zIndex: 2002
                            });
                        }
                    }
                }
            },
            type: 'POST'
        });
    });
    
    
    
    setupSuitcaseCycle();
    
    function setupSuitcaseCycle() {
        if($('#sc-area .content > ul > li').length > 1) {
            $('#suitcase-prev').show();
            $('#suitcase-next').show();
            $('#sc-area .content > ul').cycle({
                autoHeight: -1,
                allowWrap: false,
                carouselVisible: 1,
                fx: 'carousel',
                next: '#suitcase-next, .pager-next',
                prev: '#suitcase-prev, .pager-prev',
                slides: '> li',
                timeout: 0
            });
            
            var count = $('#sc-area .content > ul').data('cycle.opts').slideCount;
            $('#sc-area').find('.pager-current').text('1');
            $('#sc-area').find('.pager-total').text(count);
        }
        else {
            $('#suitcase-prev').hide();
            $('#suitcase-next').hide();
            $('#sc-area').find('.pager-current').text('1');
            $('#sc-area').find('.pager-total').text('1');
        }
    }
});


/* Account Creation Modal */
$(document).ready(function() {
    var previewUrl = '/suitcase/preview';
    if (typeof env !== 'undefined') {
        previewUrl = env + previewUrl;
    }
    
    $('.tooltip').tooltipster({
//        animation: 'grow',
        position: 'right'
    });
    
    $('#account-modal form').on('submit', function(e) {
        e.preventDefault();
        
        var data = $(this).serialize();
        var id = $(this).parent().attr('data-id');
        
        $.ajax({
            beforeSend: function() {
                $('#account-modal .error').removeClass('error');
            },
            data: data,
            dataType: 'json',
            url: $(this).attr('action'),
            success: function(data, textStatus, jqXHR) {
                if (!$.isEmptyObject(data)) {
                    if (!$.isEmptyObject(data.errors)) {
                        $.each(data.errors, function(index, value) {
                            $('#' + index).addClass('error');
                            $('#account-modal label[for="' + index + '"]').addClass('error');
                        });
                    }
                    else {
                        $('#core-nav').find('.inline-nav').append('<li><a href="#">My Account</a></li>');
                        $('#core-nav').find('.inline-nav').append('<li><a href="/logout">Logout</a></li>');
                        
                        $('#core-suitcase').replaceWith('<a href="/suitcase" id="core-suitcase-button"><span class="icon icon-suitcase"></span><em>I\'m</em> Packed<span class="count"> (1)</span></a>');
                        $('button[data-id="' + id + '"]').attr('disabled', 'disabled');
                        $('.pd-a-add[data-id="' + id + '"]').addClass('disabled');
                        $('button[data-id="' + id + '"]').attr('disabled', 'disabled');
                        $.modal.close();
                        $.get(previewUrl, function(data) {
                            $('#account-modal').replaceWith(data);
                        });
                    }
                }
            },
            type: 'POST'
        });
    });
});