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
        top: '0px'
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
        
        var url = '/search';
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

        $('.pl-items').empty();
        spinner.spin(target);
        $.ajax({
            url: url,
            data: categories + '&' + sort + '&' + filter,
            dataType: 'html',
            success: function(data, textStatus, jqXHR) {
                spinner.stop();
                $('.pl-items').empty().append($(data));
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
        
        if (e.keyCode == 27) {
            $('.pl-items').empty();
            $(plSearchBox).val('');
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
        var url = '/search';
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