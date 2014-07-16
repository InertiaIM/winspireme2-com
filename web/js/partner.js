$(function() {
    // Bust browser caching of select fields
    $('.tools form').get(0).reset();
    
    $('.tools form').on('submit', function(e) {
        e.preventDefault();
    });
    
    $('#select-status').selectBoxIt({
        nostyle: false,
        downArrowIcon: 'icon-arrow-down'
    });
    
    var searchBox = $('.search-text', '#suitcase-search');
    
    afterDelayedKeyup(searchBox, filterTable, 500);
    function afterDelayedKeyup(selector, action, delay) {
        jQuery(selector).keyup(function() {
            if(typeof(window['inputTimeout']) != 'undefined') {
                clearTimeout(inputTimeout);
            }
            
            inputTimeout = setTimeout(action, delay);
        });
    }
    
    $(searchBox).on('keyup', function(e) {
        // ESC key to cancel an edit
        if (e.which == 27) {
            $(this).val('');
        }
    });
    
    
    
    
    
    var table = $('#suitcases').stupidtable();
    
    // pre-click to establish our default sort
    $('#suitcases').find('th.cname').click();
    
    table.on('aftertablesort', function (event, data) {
        // data.column - the index of the column sorted after a click
        // data.direction - the sorting direction (either asc or desc)
        // $(this) - this table object
        
        $('#suitcases tbody tr')
            .removeClass('even')
            .filter(':visible')
            .filter(':odd')
            .addClass('even');
        
        $('#suitcases th').removeClass('sort').find('.icon-arrow-up, .icon-arrow-down').remove();
        
        $('#suitcases th').eq(data.column).addClass('sort');
        
        if(data.direction == 'asc') {
            $('#suitcases th').eq(data.column).find('a').append('<span class="icon icon-arrow-up"></span>');
        }
        else {
            $('#suitcases th').eq(data.column).find('a').append('<span class="icon icon-arrow-down"></span>');
        }
    });
    
    
    $(table).find('tbody tr').on('click', function(e) {
        var id = $(this).attr('data-id');
        var url = '/partner/' + id;
        if (typeof env !== 'undefined') {
            url = env + url;
        }
        
        window.location = url;
    });
    
    $(table).find('th a').on('click', function(e) {
        e.preventDefault();
    });
    
    
    $('.tools #select-status').on('change', function(e) {
        filterTable();
    });
    
    
    function filterTable() {
        var searchString = $(searchBox).val().toLowerCase();
        var status = $('#select-status').val();
        
        var query = '';
        
        $('#suitcases tbody tr')
            .removeClass('even')
            .hide()
        ;
        
        if (status != 'all') {
            query += '[data-status="' + status + '"]';
        }
        
        if (searchString != '') {
            query += '[data-search*="' + searchString + '"]';
        }
        
        $('#suitcases tbody tr' + query)
            .show()
            .filter(':visible')
            .filter(':odd')
            .addClass('even')
        ;
    }
});