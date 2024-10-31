; (function ($) {

    $('a#r4me_lookup').on('click', function (e) {
        e.preventDefault();
        var $this = $(this);
        $this.next('.r4me_list_spinner').fadeIn();
        if($this.is('[disabled]')){
            return false;
        }
        $this.attr('disabled', 'disabled');
        var data = {
            action: 'r4mwoo_lookup_action',
            r4me_order_id: $this.data('order_id'),
            lookup_nonce: $this.data('nonce')
        };
        $.post(ajaxurl, data,
            function (response) {
                $('.r4me_list_spinner').hide();
                $('#r4me_lookup_show').html(response);
                $this.removeAttr('disabled');
                tb_show('Order #' + $this.data('order_id') + ' details', '#TB_inline?inlineId=r4me_lookup_show&height=300');
            });
    });


    $('#bulk-action-selector-top').append('<option value="mark_completed">Mark complete</option>');


    // Single Checkbox
    $(".wp-list-table input[type='checkbox']").on("change", function () {
        var $this = $(this);
        if (this.checked) {
            $this.parents('tr').addClass('selected');
        } else {
            $this.parents('tr').removeClass('selected');
        }
    });

    // Checkbox toggle
    $("input#cb-select-all-1, input#cb-select-all-2").on("change", function () {
        if (this.checked) {
            $('#the-list input[type="checkbox"]').each(function () {
                $(this).parents('tr').addClass('selected');
            });
        } else {
            $('#the-list input[type="checkbox"]').each(function () {
                $(this).parents('tr').removeClass('selected');
            });
        }
    });


    $('#posts-filter').on("submit", function (e) {

        var bulkSelect = $(this).find('select.r4me_bulk_select');

        if (bulkSelect.val() == 'r4me_export') {
            e.preventDefault();
            $('#r4me_preloader').html('<div class="progress"><div class="indeterminate"></div></div>');

            var arr_order = [];
            $('.wp-list-table #the-list tr.selected').each(function () {
                var id = $(this).find('input[type="checkbox"]').val();
                var exported = false;
                var lookupLink = $(this).find('.column-route4me_column>a');
                if (lookupLink.length) {
                    exported = true;
                }
                arr_order.push({
                    'id': id,
                    'exported': exported
                });
            });
            //alert(JSON.stringify(arr_order));
            var data = {
                orders: arr_order,
                action: 'r4mwoo_bulk_order_action',
                bulk_geo_nonce: bulkSelect.data('nonce')
            };
            $.post(ajaxurl, data)
                .done(function (response) {
                    $('#r4me_preloader').html('');
                    $('#r4me_lookup_show').html(response);
                    tb_show('Bulk Export', '#TB_inline?inlineId=r4me_lookup_show&height=200');
                    $('.r4meDatepicker').datepicker({
                        dateFormat: 'mm/dd/yy'
                    });

                    // Bulk Export Final  
                    $('#r4me_final_bulk_export').on('click', function (e) {
                        e.preventDefault();
                        var $this = $(this);
                        $this.addClass('disabled');
                        //$("#TB_window").remove();
                        //$("body").append('<div id="TB_window"></div>');
                        tb_remove();

                        var export_arr = [];
                        $('.r4me-hidden-inputs>input').each(function () {
                            export_arr.push({
                                'address': $(this).val(),
                                'lat': $(this).data('lat'),
                                'lng': $(this).data('lng'),
                                'order': $(this).data('order_id')
                            });
                        });
                        var bulk_date = $('#r4me_bulk_date').val();

                        $('#r4me_preloader').html('<div class="progress"><div class="indeterminate"></div></div>');
                        var data = {
                            order_data: export_arr,
                            order_date: bulk_date,
                            action: 'r4mwoo_add_bulk_action',
                            bulk_nonce: $this.data('nonce')
                        };
                        $.post(ajaxurl, data,
                            function (response) {
                                $('#r4me_preloader').html('');
                                $('#r4me_lookup_show').html(response);
                                tb_show('Bulk Export', '#TB_inline?inlineId=r4me_lookup_show');
                                $this.removeClass('disabled');
                            });

                    }); // end bulk export    
                
                }); // end bulk geocode
        }
    });

}(jQuery));

//Single Export
; (function ($) {
    $('.r4meDatepicker').datepicker({
        dateFormat: 'mm/dd/yy',
        yearRange: '2020:2030'
    });

    $('#r4me_order_btn').on('click', function (e) {
        e.preventDefault();
        var $this = $(this);
        $scheduleDateVal = $.trim($('input#r4me_schedule_date').val());
        if ($scheduleDateVal == "") {
            $('#r4me_schedule_date_required').removeClass('hidden');
            return;
        }
        $this.addClass('disabled');
        $('#r4me_response').html('<div class="r4me_spinner"></div>');
        var data = {
            action: 'r4mwoo_add_order_action',
            postID: $this.data('id'),
            scheduledDate: $scheduleDateVal,
            export_nonce: $this.data('nonce')
        };
        $.post(ajaxurl, data,
            function (response) {
                $this.hide();
                $('#r4me_geocoded_status, .r4me-schedule').hide();
                $('#r4me_response').html(response);
            });
    });
}(jQuery));