$(function() {
    // Bust browser caching of select fields
    $('.tools form').get(0).reset();
    
    $('.tools form').on('submit', function(e) {
        e.preventDefault();
    });
    
    $('#select-consultant').selectBoxIt({
        nostyle: false,
        downArrowIcon: 'icon-arrow-down'
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
    
    $(table).find('tbody .delete a').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var id = $(this).attr('data-id');
        var name = $(this).attr('data-name');
        $('#delete-suitcase-modal').find('button').attr('data-id', id);
        $('#delete-suitcase-modal').find('#suitcase-name').text(name);
        $('#delete-suitcase-modal').modal({
            closeText: 'X',
            overlay: '#fff',
            opacity: 0.73,
            zIndex: 2002
        });
    });
    
    $('#delete-suitcase-modal button').on('click', function(e) {
        e.preventDefault();
        
        var url = '/suitcase/kill';
        if (typeof env !== 'undefined') {
            url = env + url;
        }
        
        var id = $(this).attr('data-id');
        
        $.ajax({
            beforeSend: function() {},
            dataType: 'json',
            url: url + '/' + id,
            success: function(data, textStatus, jqXHR) {
                if (!$.isEmptyObject(data) && data.success) {
                    $('#suitcases tbody tr[data-id="' + data.suitcase.id + '"]').remove();
                    $('#suitcases tbody tr')
                        .removeClass('even')
                        .filter(':visible')
                        .filter(':odd')
                        .addClass('even')
                    ;
                    
                    $.modal.close();
                }
            },
            type: 'GET'
        });
    });
    
    
    $(table).find('tbody .sfid').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });
    
    $(table).find('tbody .sfid .value').not('.disabled').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close all open text input fields
        var forms = $(table).find('tbody .sfid .form:visible');
        $(forms).each(function () {
            $(this).find('input').val($(this).prev('.value').text());
            $(this).hide().prev('.value').show();
        });
        
        var form = $(this).next('.form');
        $(this).hide();
        $(form).show();
        $(form).find('input').focus().select();
    });
    
    $(table).find('tbody .sfid .form input').on('keyup', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // RETURN key submits the change
        if (e.which == 13) {
            var holder = $(this).parent().prev('.value');
            var value = $(this).val();
            
            if (($(holder).text() != value)) {
                if (value != '') {
                    var oldValue = $(holder).text();
                    var url = '/admin/sf?id=' + $(this).attr('data-id');
                    if (typeof env !== 'undefined') {
                        url = env + url;
                    }
                    $.ajax({
                        beforeSend: function() {
                            $(holder).text(value);
                        },
                        data: $(this).serialize(),
                        dataType: 'json',
                        url: url,
                        success: function(data, textStatus, jqXHR) {
                            if (data.success) {
                                $(holder).text(data.success);
                                $(this).val(data.success);
                            }
                            else {
                                alert('There was a problem with the ID# provided: ' + value);
                                $(holder).text(oldValue);
                                $(this).val(oldValue);
                            }
                        },
                        type: 'POST'
                    });
                }
                else {
                    // If the value is empty, return to our previous Id
                    if ($(holder).html() != '&nbsp;') {
                        $(this).val($(holder).text());
                    }
                }
            }
            
            $(this).parent().hide();
            $(holder).show();
        }
        
        // ESC key to cancel an edit
        if (e.which == 27) {
            var holder = $(this).parent().prev('.value');
            var value = $(this).val();
            
            if ($(holder).html() != '&nbsp;') {
                $(this).val($(holder).text());
            }
            else {
                $(this).val('');
            }
            
            $(this).parent().hide();
            $(holder).show();
        }
    });
    
    $(table).find('tbody tr').on('click', function(e) {
        var id = $(this).attr('data-id');
        var url = '/admin/' + id;
        if (typeof env !== 'undefined') {
            url = env + url;
        }
        
        window.location = url;
    });
    
    $(table).find('th a').on('click', function(e) {
        e.preventDefault();
    });
    
    $('.tools #select-consultant').on('change', function(e) {
        $(searchBox).val('');
        filterTable();
    });
    
    
    $('.tools #select-status').on('change', function(e) {
        filterTable();
    });
    
    
    function filterTable() {
        var searchString = $(searchBox).val().toLowerCase();
        var status = $('#select-status').val();
        var userId = $('#select-consultant').val();
        
        var query = '';
        
        $('#suitcases tbody tr')
            .removeClass('even')
            .hide()
        ;
        
        if (userId != 'all') {
            query += '[data-user-id="' + userId + '"]';
        }
        
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