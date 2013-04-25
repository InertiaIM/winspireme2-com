var headerCtx = $('header')[0];
var footerCtx = $('footer')[0];

    /* Header Search */
    /* Context */
    var headerSearchBox = $('.search-text', headerCtx);
    var headerSearchSubmit = $('.search-submit', headerCtx);
    
    headerSearchBox.focus(function(e) {
        $(this).addClass('active');
    });
    
    headerSearchBox.blur(function(e) {
        $(this).removeClass('active');
    });
    
    
    
    var spinTarget = $('#site-search-result .panel-inner').get(0);
    var searchSpinner = new Spinner({
        lines: 13,
        length: 5,
        width: 2,
        radius: 7,
        corners: 1.0,
        color: '#1280d6',
        rotate: 0,
        trail: 60,
        speed: 1.0,
        hwaccel: 'on'
    }).spin(spinTarget).stop();
    
    
    headerSearchBox.keyup(function(e) {
        if($(headerSearchBox).val() == '') {
            $('#site-search-result').slideUp('fast');
        }
        else {
            $('#site-search-result').slideDown('fast');
            searchSpinner.spin(spinTarget);
        }
    });
    
    
    afterDelayedKeyup(headerSearchBox, 'submitQuery2()', 500);
    
    
    function submitQuery2() {
        var url = '/package/search.json';
        if (typeof env !== 'undefined') {
            url = env + url;
        }
        
        var q = 'q=' + $(headerSearchBox).val();
        
        $.ajax({
            beforeSend: function() {
                searchSpinner.stop();
                $('#site-search-result ul').empty();
                searchSpinner.spin(spinTarget);
            },
            url: url,
            data: q,
            dataType: 'json',
            success: function(data, textStatus, jqXHR) {
                if(!$.isEmptyObject(data.packages)) {
                    searchSpinner.stop();
                }
                
                $.each(data.packages, function(index, item) {
                    $('#site-search-result ul').append('<li><a href="/package/' + item.package.slug + '"><img width="50" src="/uploads/packages/' + item.package.image + '"/>' + item.package.title + '</a></li>');
                });
            }
        });
    }
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
    
    
    /* Global Sign Up */
    $(headerSuitcasePanel).find('.pa-su').on('click', function(e) {
        e.preventDefault();
        
        $(headerSuitcasePanel).hide();
        
        if($('#account-modal').length > 0) {
            $('#account-modal')
                .modal({
                    closeText: 'X',
                    overlay: '#fff',
                    opacity: 0.73,
                    zIndex: 2002
                });
        }
        
    });
    /* Global Sign Up */
    
    
    /* Home Banner */
    $(function() {
        $('#home-banner').homeslideshow({
            autoplay: true,
            interval: 10000
        });
        
        $('#home-banner .slide-wrapper .step').on('click', function(e) {
            var url = $(this).find('a.primary').attr('href');
            window.location = url;
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
    
    $('#home-f-loved li').on('mouseleave', function(e) {
        $(this).find('.color').stop(true, true).fadeOut(200);
        $(this).find('.bw').stop(true, true).fadeIn(200);
    });
    
    $('#home-f-loved li').on('mouseenter', function(e) {
        $(this).find('.bw').stop(true, true).fadeOut(200);
        $(this).find('.color').stop(true, true).fadeIn(200);
    });
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
        // If we're checking something in the categories,
        // then we'll clear the search box.
        if($(this).is(':checked')) {
            $(plSearchBox).val('');
        }
        
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
        
        
        // If we're checking something in the US Travel selector,
        // then we'll clear the search box.
        if(!$.isEmptyObject(states)) {
            $(plSearchBox).val('');
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
        if($(plSearchBox).val() != '') {
            submitQuery();
            return;
        }
        
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
    
    
    plSearchBox.focus(function (e) {
        $(this).addClass('active');
    });
    
    
    plSearchBox.blur(function (e) {
        $(this).removeClass('active');
    });
    
    
    plSearchBox.keyup(function (e) {
        // Wipe out all the category selections when we're
        // typing in the search field.
        $('input[name^="category"]').removeAttr('checked').siblings('label').removeClass('active');
        
        if($('#catState').multiselect('getChecked').length > 0) {
            $('#catState').multiselect('uncheckAll');
        }
    });
    
    
    afterDelayedKeyup(plSearchBox, 'submitQuery()', 500);
    function afterDelayedKeyup(selector, action, delay) {
        jQuery(selector).keyup(function() {
            if(typeof(window['inputTimeout']) != 'undefined'){
                clearTimeout(inputTimeout);
            }
            inputTimeout = setTimeout(action, delay);
        });
    }
    
    
    function submitQuery() {
//        if($(plSearchBox).val().length > 3) {
        var url = '/package/search';
        if (typeof env !== 'undefined') {
            url = env + url;
        }
        
        var q = 'q=' + $(plSearchBox).val();
        var filter = 'filter=' + $('.pl-sort ul li a.selected').attr('data-filter');
        var sort = $('select[name="sortOrder"]').serialize();
        
        $.ajax({
            beforeSend: function() {
                $('.pl-items').empty();
                spinner.spin(target);
            },
            url: url,
            data: q + '&' + sort + '&' + filter,
            dataType: 'html',
            success: function(data, textStatus, jqXHR) {
                spinner.stop();
                $('.pl-items').empty();
                $('.pl-items').append($(data));
            }
        });
    }
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
            
            
            $('.srv-value').find('.up-to').remove();
            if($('#variant-holder #v' + newId).data('upto')) {
                $('.srv-value').text(' ' + $('#variant-holder #v' + newId).data('srv'));
                $('.srv-value').prepend('<span class="up-to">Up to</span>');
            }
            else {
                $('.srv-value').text($('#variant-holder #v' + newId).data('srv'));
            }
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
                        
                        $('.pd-a-add[data-id="' + id + '"], .f-add[data-id="' + id + '"]').removeClass('disabled');
                        
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
        
        
        
        $('.pd-a-add, .f-add').on('click', function(e) {
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
    
    
    $(sc).find('.content').on('click', 'h3 > a', function(e) {
        e.stopPropagation();
    });
    
    $(sc).find('.content').on('click', '.actions > .more', function(e) {
        e.stopPropagation();
    });
    
    $(sc).find('.content').on('click', '.actions > .download', function(e) {
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
        
        if($('#sc-area .content .pager-all').text() == 'View All') {
            setupSuitcaseCycle();
        }
        
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
    $('#share-modal').on($.modal.OPEN, function(event, modal) {
        // For viewports that are narrower than our page,
        // change the modal box to position with an appropriate margin.
        if($(window).width() < $(document).width()) {
            $(modal.elm).css({
                marginLeft: Math.floor(($(document).width() - $(modal.elm).outerWidth()) / 2) + 'px',
                left: '0'
            });
        }
        
        // For viewports that are smaller than our modal,
        // change the modal box to position absolute for scrolling.
        if(($(window).height() - $(modal.elm).outerHeight()) < 94) {
            $(modal.elm).css({
                position: 'absolute',
                marginTop: '120px',
                top: '0'
            });
            
            $(window).scrollTop(100);
        }
    });
    
    $('.social .share a').on('click', function(e) {
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
                  $('#share-modal #share-result-holder #share-result-errors').hide();
                  $('#share-modal #share-result-holder #share-result-successes').hide();
                  $('#share-modal #share-result-holder .successes li').remove();
                  $('#share-modal #share-result-holder .errors li').remove();
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
                              $.each(data.successes, function(index, value) {
                                  $('#share-modal #share-result-holder .successes').append('<li><span class="number">' + (index + 1) + '</span><span class="name">' + value.name + '</span><span class="email">'   + value.email + '</span></li>')
                              });
                          }
                          
                          if (!$.isEmptyObject(data.errors)) {
                              $('#share-modal #share-result-holder #share-result-errors').show();
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
    
    $('#comment-form').on('submit', function(e) {
        e.preventDefault();
        
        var data = $(this).serialize();
        
        $.ajax({
            beforeSend: function() {},
            data: data,
            dataType: 'json',
            url: $(this).attr('action'),
            success: function(data, textStatus, jqXHR) {
                if (data.success) {
                    $('#comment-area .commentary').append('<div class="comment"><p><strong>' + data.name + '</strong> <span class="timestamp">' + data.timestamp + '</span></p><p>' + data.message + '</p><hr/></div>');
                    $('#comment-form textarea').val('');
                    
                    var commentCount = $('.comment-count');
                    commentCount = commentCount[0];
                    
                    var count = parseInt($(commentCount).text());
                    count++;
                    
                    $('.comment-count').text(count);
                }
            },
            type: 'POST'
        });
    });
    
    
    
    $('#sc-area .content > ul').on('cycle-after', function(event, opts) {
        $('#sc-area').find('.pager-current').text(opts.slideNum);
        $('#sc-area').find('.pager-total').text(opts.slideCount);
    });
    
    
    $('#reminder-modal').on($.modal.OPEN, function(event, modal) {
        // For viewports that are narrower than our page,
        // change the modal box to position with an appropriate margin.
        if($(window).width() < $(document).width()) {
            $(modal.elm).css({
                marginLeft: Math.floor(($(document).width() - $(modal.elm).outerWidth()) / 2) + 'px',
                left: '0'
            });
        }
        
        // For viewports that are smaller than our modal,
        // change the modal box to position absolute for scrolling.
        if(($(window).height() - $(modal.elm).outerHeight()) < 94) {
            $(modal.elm).css({
                position: 'absolute',
                marginTop: '120px',
                top: '0'
            });
            
            $(window).scrollTop(100);
        }
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
    
    $('#more-modal').on($.modal.OPEN, function(event, modal) {
        // For viewports that are narrower than our page,
        // change the modal box to position with an appropriate margin.
        if($(window).width() < $(document).width()) {
            $(modal.elm).css({
                marginLeft: Math.floor(($(document).width() - $(modal.elm).outerWidth()) / 2) + 'px',
                left: '0'
            });
        }
        
        // For viewports that are smaller than our modal,
        // change the modal box to position absolute for scrolling.
        if(($(window).height() - $(modal.elm).outerHeight()) < 94) {
            $(modal.elm).css({
                position: 'absolute',
                marginTop: '120px',
                top: '0'
            });
            
            $(window).scrollTop(100);
        }
    });
    
    
    $('#account_date').datepicker({
        buttonImage: '/img/calendar.png',
        buttonImageOnly: true,
        constrainInput: true,
        dateFormat: 'mm/dd/y',
        minDate: '+1',
        maxDate: '+1y',
        showOn: 'both'
    });
    
    
    $('#more-modal form').validate({
        errorElement: 'em',
        errorPlacement: function(error, element) {
            switch(element.attr('name')) {
            case 'account[loa]':
                error.insertAfter('label[for="account_loa"]');
                break;
            case 'account[date]':
                error.insertAfter('#account_date + img');
                break;
            default:
                error.insertAfter(element);
            }
        },
        rules: {
            'account[address]': {
                required: true
            },
            'account[city]': {
                required: true
            },
            'account[state]': {
                required: true
            },
            'account[zip]': {
                required: true
            },
            'account[name]': {
                required: true
            },
            'account[loa]': {
                required: true
            }
        },
        messages: {
            'account[address]': 'Address required',
            'account[city]': 'City name required',
            'account[state]': 'Required',
            'account[zip]': 'Zip code required',
            'account[name]': 'Event name required',
            'account[loa]': 'Must agree to the terms in our Letter of Agreement'
        },
        onfocusout: false,
        submitHandler: function(form) {
            var data = $(form).serialize();
            
            $.ajax({
                beforeSend: function() {
                    $('#more-modal .error').removeClass('error');
                },
                data: data,
                dataType: 'json',
                url: $(form).attr('action'),
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
                                
                                $(window).scrollTop(100);
                            }
                        }
                    }
                },
                type: 'POST'
            });
        }
    });
    
    
    $('#sc-area .pager-all').on('click', function(e) {
        e.preventDefault();
        
        if($(this).text() == 'View All') {
            $('#sc-area .content > ul').cycle('destroy');
            
            $('#suitcase-next, #suitcase-prev').hide();
            
            $('#sc-area .content .suitcase-page').each(function() {
                var length = $(this).children('ul').children('li').length;
                if(length > 3) {
                    $(this).css({'height': '622px'});
                }
                else {
                    $(this).css({'height': '311px'});
                }
            });
            
            $('#sc-area .content > ul').css({
                'height': 'auto',
                'overflow': 'visible'
            });
            
            $(this).text('View Less');
            
            $('#sc-area .content .pager-current').text('1');
            $('#sc-area .content .pager-total').text('1');
        }
        else {
            $('#sc-area .content .suitcase-page').each(function() {
                $(this).css({'height': 'auto'});
            });
            
            $('#sc-area .content > ul').css({
                'height': '622px',
                'overflow': 'hidden'
            });
            
            $(this).text('View All');
            
            setupSuitcaseCycle();
        }
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
    
    
    // Suitcase Create Modal
    var currentSid = $('.suitcase-switcher').attr('data-id');
    
    // prevent the browser from "remembering" our selection with back/forward navigation
    $('.suitcase-switcher select').val(currentSid);
    
    
    $('form.suitcase-switcher select[name="sid"]').selectBoxIt({
        nostyle: false,
        downArrowIcon: 'icon-arrow-down'
    });
    
    var selectBoxSuitcase1 = $('form.suitcase-switcher select[name="sid"]').eq(0).data('selectBox-selectBoxIt');
    var selectBoxSuitcase2 = $('form.suitcase-switcher select[name="sid"]').eq(1).data('selectBox-selectBoxIt');
    
    // make sure the non-visible selectbox widget has the correct width
    var width = $('form.suitcase-switcher:visible').find('.selectboxit.selectboxit-btn').css('width');
    $('form.suitcase-switcher').find('.selectboxit.selectboxit-btn').css('width', width);
    
    $('#suitcase-modal').on($.modal.BEFORE_CLOSE, function(event, modal) {
        selectBoxSuitcase1.selectOption(currentSid);
        selectBoxSuitcase2.selectOption(currentSid);
    });
    
    $('.suitcase-switcher select').on('change', function(e) {
        if ($(this).val() == 'new') {
            $('#suitcase-modal').modal({
                closeText: 'X',
                overlay: '#fff',
                opacity: 0.73,
                zIndex: 2002
            });
        }
        else {
            if ($(this).val() != currentSid) {
                $(this).parent('form').submit();
            }
        }
    });
    
    $('#suitcase-modal form').validate({
        errorElement: 'em',
        errorPlacement: function(error, element) {
            switch(element.attr('name')) {
            case 'suitcase[date]':
                error.insertAfter('#suitcase_date + img');
                break;
            default:
                error.insertAfter(element);
            }
        },
        rules: {
            'suitcase[name]': {
                required: true
            },
            'suitcase[date]': {
                required: true
            }
        },
        messages: {
            'suitcase[name]': 'Event name required',
            'suitcase[date]': 'Event date required'
        },
        submitHandler: function(form) {
            form.submit();
            
            
            var data = $(form).serialize();
            
/*          
            $.ajax({
                beforeSend: function() {
                    $('#more-modal .error').removeClass('error');
                },
                data: data,
                dataType: 'json',
                url: $(form).attr('action'),
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
                                
                                $(window).scrollTop(100);
                            }
                        }
                    }
                },
                type: 'POST'
            });
*/
        }
    });
    
    $('#suitcase_date').datepicker({
        buttonImage: '/img/calendar.png',
        buttonImageOnly: true,
        constrainInput: true,
        dateFormat: 'mm/dd/y',
        minDate: '+1',
        maxDate: '+1y',
        onSelect: function(date, dp) {
            $('#suitcase-modal form').validate().element('#suitcase_date');
        },
        showOn: 'both'
    });
});


/* Account Creation Modal */
$(document).ready(function() {
    var previewUrl = '/suitcase/preview';
    if (typeof env !== 'undefined') {
        previewUrl = env + previewUrl;
    }
    
    var validateUrl = '/account/validate-email';
    if (typeof env !== 'undefined') {
        validateUrl = env + validateUrl;
    }
    
    $('.tooltip').tooltipster({
        position: 'right'
    });
    
    
    $('#account-modal').on($.modal.OPEN, function(event, modal) {
        // For viewports that are narrower than our page,
        // change the modal box to position with an appropriate margin.
        if($(window).width() < $(document).width()) {
            $(modal.elm).css({
                marginLeft: Math.floor(($(document).width() - $(modal.elm).outerWidth()) / 2) + 'px',
                left: '0'
            });
        }
        
        // For viewports that are smaller than our modal,
        // change the modal box to position absolute for scrolling.
        if(($(window).height() - $(modal.elm).outerHeight()) < 94) {
            $(modal.elm).css({
                position: 'absolute',
                marginTop: '120px',
                top: '0'
            });
            
            $(window).scrollTop(100);
        }
    });
    
    
    $('#account-modal form').validate({
        errorElement: 'em',
        errorPlacement: function(error, element) {
            if (element.attr('name') == 'fos_user_registration_form[terms]') {
                error.insertAfter('label[for="fos_user_registration_form_terms"]');
            }
            else {
                error.insertAfter(element);
            }
        },
        rules: {
            'fos_user_registration_form[suitcase]': {
                required: true
            },
            'fos_user_registration_form[firstName]': {
                required: true
            },
            'fos_user_registration_form[lastName]': {
                required: true
            },
            'fos_user_registration_form[account][name]': {
                required: true
            },
            'fos_user_registration_form[email]': {
                email: true,
                required: true,
                remote: {
                    url: validateUrl
                }
            },
            'fos_user_registration_form[plainPassword][first]': {
                required: true,
                minlength: 10
            },
            'fos_user_registration_form[plainPassword][second]': {
                equalTo: '#fos_user_registration_form_plainPassword_first'
            },
            'fos_user_registration_form[phone]': {
                required: true
            },
            'fos_user_registration_form[account][state]': {
                required: true
            },
            'fos_user_registration_form[account][zip]': {
                required: true
            },
            'fos_user_registration_form[terms]': {
                required: true
            }
        },
        messages: {
            'fos_user_registration_form[suitcase]': 'A suitcase name is required',
            'fos_user_registration_form[firstName]': 'First name required',
            'fos_user_registration_form[lastName]': 'Last name required',
            'fos_user_registration_form[account][name]': 'Organization name required',
            'fos_user_registration_form[email]': { 
                required: 'Email address is required',
                remote: 'An account with that email is already registered'
            },
            'fos_user_registration_form[plainPassword][second]': 'The chosen passwords do no match',
            'fos_user_registration_form[account][state]': 'Required',
            'fos_user_registration_form[phone]': 'Phone number required',
            'fos_user_registration_form[account][zip]': 'Zip code required',
            'fos_user_registration_form[terms]': 'Must agree to our Terms of Service'
        },
        onfocusout: false,
        submitHandler: function(form) {
            var data = $(form).serialize();
            var id = $(form).parent().attr('data-id');
            
            $.ajax({
                beforeSend: function() {
                    $('#account-modal .error').removeClass('error');
                },
                data: data,
                dataType: 'json',
                url: $(form).attr('action'),
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
                            
                            if(id != 'none') {
                                $('#core-suitcase').replaceWith('<a href="/suitcase" id="core-suitcase-button"><span class="icon icon-suitcase"></span><em>I\'m</em> Packed<span class="count"> (1)</span></a>');
                            }
                            else {
                                $('#core-suitcase').replaceWith('<a href="/suitcase" id="core-suitcase-button"><span class="icon icon-suitcase"></span><em>I\'m</em> Packed<span class="count"></span></a>');
                            }
                            $('.pd-a-add[data-id="' + id + '"], .f-add[data-id="' + id + '"]').addClass('disabled');
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
        }
    });
});



/* Account Edit Modal */
$(function() {
    $('#edit-contact-modal form').validate({
        submitHandler: function(form) {
            var data = $(form).serialize();
            $.ajax({
                beforeSend: function() {
                },
                data: data,
                dataType: 'json',
                url: $(form).attr('action'),
                success: function(data, textStatus, jqXHR) {
                    if (!$.isEmptyObject(data)) {
                        var holder = $('#contact-info');
                        
                        // Address 1
                        if($('#contact-info-address').length == 0 && data.contact.address != '') {
                            $('<span id="contact-info-address"></span><br/>').insertAfter('#contact-info-cname + br');
                        }
                        $(holder).find('#contact-info-address').text(data.contact['address']);
                        
                        // Address 2
                        if($('#contact-info-address2').length == 0 && data.contact.address2 != '') {
                            $('<span id="contact-info-address2"></span><br/>').insertAfter('#contact-info-address + br');
                        }
                        if($('#contact-info-address2').length > 0 && data.contact.address2 == '') {
                            $('#contact-info-address2 + br').remove();
                        }
                        $(holder).find('#contact-info-address2').text(data.contact['address2']);
                        
                        // City + State + Zip
                        if($('#contact-info-city').length == 0 && data.contact.city != '') {
                            $('<span id="contact-info-city"></span>, <span id="contact-info-state"></span> <span id="contact-info-zip"></span><br/>').insertBefore('#contact-info-email');
                        }
                        $(holder).find('#contact-info-city').text(data.contact['city']);
                        $(holder).find('#contact-info-state').text(data.contact['state']);
                        $(holder).find('#contact-info-zip').text(data.contact['zip']);
                        
                        // Phone
                        $(holder).find('#contact-info-phone').text(data.contact['phone']);
                        $.modal.close();
                    }
                },
                type: 'POST'
            });
        }
    });
    
    $('#contact-info .button.edit').on('click', function(e) {
        e.preventDefault();
        
        $('#edit-contact-modal').modal({
            closeText: 'X',
            overlay: '#fff',
            opacity: 0.73,
            zIndex: 2002
        });
        
        $('#edit-contact-modal form input#contact_address').focus();
    });
    
    
    var url = '/account/validate';
    if (typeof env !== 'undefined') {
        url = env + url;
    }
    $('#edit-password-modal form').validate({
        rules: {
            'contact[password][old]': {
                required: true,
                remote: {
                    url: url
                }
            },
            'contact[password][new]': 'required',
            'contact[password][verify]': {
                equalTo: '#contact_password_new'
            }
        },
        messages: {
            'contact[password][old]': 'The password is incorrect.',
            'contact[password][verify]': 'The chosen passwords do no match.'
        },
        onfocusout: false,
        submitHandler: function(form) {
            var data = $(form).serialize();
            $.ajax({
                beforeSend: function() {
                    $('#contact_password_old').val('');
                    $('#contact_password_new').val('');
                    $('#contact_password_verify').val('');
                },
                data: data,
                dataType: 'json',
                url: $(form).attr('action'),
                success: function(data, textStatus, jqXHR) {
                    if (!$.isEmptyObject(data)) {
                        $.modal.close();
                    }
                },
                type: 'POST'
            });
        }
    });
    
    
    $('#password-info .button.edit').on('click', function(e) {
        e.preventDefault();
        
        $('#edit-password-modal').modal({
            closeText: 'X',
            overlay: '#fff',
            opacity: 0.73,
            zIndex: 2002
        });
        
        $('#edit-password-modal form input#contact_password_old').focus();
    });
    
    
    
    $('#edit-suitcase-modal form').validate({
        submitHandler: function(form) {
            var data = $(form).serialize();
            $.ajax({
                beforeSend: function() {
                },
                data: data,
                dataType: 'json',
                url: $(form).attr('action'),
                success: function(data, textStatus, jqXHR) {
                    if (!$.isEmptyObject(data) && data.success) {
                        $('#name-' + data.suitcase.id).find('strong').text(data.suitcase.name);
                        $('#event-name-' + data.suitcase.id).text(data.suitcase.event_name);
                        $('#event-date-' + data.suitcase.id).text(data.suitcase.event_date);
                        
                        $.modal.close();
                    }
                },
                type: 'POST'
            });
        }
    });
    
    
    $('#suitcase-info .button.edit').on('click', function(e) {
        e.preventDefault();
        
        var id = $(this).attr('data-id');
        var name = $('#name-' + id).text();
        var eventName = $('#event-name-' + id).text();
        var eventDate = $('#event-date-' + id).text();
        
        var form = $('#edit-suitcase-modal form');
        $(form).find('#suitcase_name').val(name);
        $(form).find('#suitcase_event_name').val(eventName);
        $(form).find('#suitcase_event_date').val(eventDate);
        $(form).find('#suitcase_id').val(id);
        
        $('#edit-suitcase-modal').modal({
            closeText: 'X',
            overlay: '#fff',
            opacity: 0.73,
            zIndex: 2002
        });
        
        $('#edit-suitcase-modal form input#suitcase_name').focus();
    });
    
    
    var url = '/suitcase/kill';
    if (typeof env !== 'undefined') {
        url = env + url;
    }
    $('#delete-suitcase-modal button').on('click', function(e) {
        e.preventDefault();
        
        var id = $(this).attr('data-id');
        
        $.ajax({
            beforeSend: function() {
            },
            dataType: 'json',
            url: url + '/' + id,
            success: function(data, textStatus, jqXHR) {
                if (!$.isEmptyObject(data) && data.success) {
                    $('#suitcase-info tr[data-id="' + data.suitcase.id + '"]').remove();
                    $.modal.close();
                }
            },
            type: 'GET'
        });
        
    });
    
    
    $('#suitcase-info .button.delete').on('click', function(e) {
        e.preventDefault();
        
        var id = $(this).attr('data-id');
        $('#delete-suitcase-modal').find('button').attr('data-id', id);
        
        $('#delete-suitcase-modal').modal({
            closeText: 'X',
            overlay: '#fff',
            opacity: 0.73,
            zIndex: 2002
        });
    });
});
