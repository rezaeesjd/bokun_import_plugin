jQuery(document).ready(function($) {
    new DataTable('#activities-table', {
        columnDefs: [
            { targets: [0, 1], orderable: false }
        ]
    });

    $('.select_all').change(function() {
        // Check if .check-column is checked
        if ($(this).prop('checked')) {
            // If checked, check all checkboxes with class bokun_post_cb
            $('.bokun_post_cb').prop('checked', true);
        } else {
            // If unchecked, uncheck all checkboxes with class bokun_post_cb
            $('.bokun_post_cb').prop('checked', false);
        }
    });



    function bkncptCollectActivityIds(table) {
        var ids = [];

        if ($('.bokun_post_cb:checked').length > 0) {
            ids = $('.bokun_post_cb:checked').map(function() { return $(this).val(); }).get();
        } else {
            if (table) {
                var originalLength = table.page.len();
                table.page.len(-1).draw();
                $('#activities-table tbody input[type="checkbox"]').each(function() {
                    ids.push($(this).val());
                });
                table.page.len(originalLength).draw();
            }
        }

        return {
            ids: ids
        };
    }

    function single_ajax_call(activityId) {
        var productListId = $("#product_list_id").val();
        var data_only = 0;
        if ($('.bokun_post_cb:checked').length > 0) {
            data_only = 1;
        }
        $('.import-activity-' + activityId).text('Inprogress..');

        return $.ajax({
            type: "POST",
            url: bkncpt_import_script_vars.ajaxurl,
            data: {
                action: "bkncpt_import_single_activity",
                activityId: activityId,
                import_type: 'single',
                nonce: bkncpt_import_script_vars.nonce,
                product_list_id: productListId,
            }
        });
    }

    $(".import-activity").click(function() {
        var activityTitle = $(this).data("activity-title");
        var activityId = $(this).data("activity-id");
        var productListId = $("#product_list_id").val();
        $(this).text('Inprogress...');
        // $(this).attr('disabled', true);
        var this_val = this;
        $.ajax({
            type: "POST",
            url: bkncpt_import_script_vars.ajaxurl,
            dataType: 'json',
            data: {
                action: "bkncpt_import_single_activity",
                title: activityTitle,
                activityId: activityId,
                import_type: 'single',
                nonce: bkncpt_import_script_vars.nonce,
                product_list_id: productListId,
            },
            success: function(response) {
                console.log(response);
                $(this_val).text('Success');
                // $(this_val).attr('disabled', false);
                setTimeout(function() { $(this_val).text('Import'); }, 1000);
                if (response.status) {
                    alert(response.msg);
                }
            }
        });
    });

    function processIdsSequentially(ids) {
        var index = 0;
        var total = ids.length;
        var progressBar = $('.bkncpt-progress-bar');
        var importButton = $('.import-all-activities');

        function callNextAjax() {

            if (index < total) {
                $('.progress-text').text(index + ' completed out of ' + total);
                $('.progress-text').removeClass('bkncpt-hide');
                var id = ids[index];

                single_ajax_call(id)
                    .done(function(response) {
                        index++;
                        $('.progress-text').text(index + ' completed out of ' + total);
                        var percentComplete = Math.round((index / total) * 100);
                        progressBar.val(percentComplete);
                        importButton.text(percentComplete + '% Complete');
                        $('.import-activity-' + id).text('Completed');
                        callNextAjax(); // Call the next AJAX function recursively
                    })
                    .fail(function(jqXHR, textStatus, errorThrown) {
                        console.error('Error for ID:', id, errorThrown);
                        // Handle error if needed
                        callNextAjax(); // Call the next AJAX Even failed function recursively
                    });
            } else {
                importButton.removeClass('bkncpt-button-inprogress');
                importButton.addClass('bkncpt-button-success');
                $('.progress-text').removeClass('bkncpt-hide').text('Import completed');
                setTimeout(function() {
                    importButton.removeClass('bkncpt-button-success');
                    importButton.text('Import Selected / All');
                }, 2500);
            }
        }

        callNextAjax(); // Start the recursive AJAX calls
    }
    $(".import-all-activities").click(function() {
        var table = $('#activities-table').DataTable();
        var selection = bkncptCollectActivityIds(table);
        var selected_boheck = selection.ids;
        $('.import-all-activities').text('Importing...');
        $('.import-all-activities').addClass('bkncpt-button-inprogress');
        $('.bkncpt-progress-bar').removeClass('bkncpt-hide').val(0);
        $('.progress-text').removeClass('bkncpt-hide').text('Preparing import...');
        $.each(selected_boheck, function(index, id) {
            $('.import-activity-' + id).text('Queued');
        });
        processIdsSequentially(selected_boheck);
    });

    /* 
    $(".import-all-activities-old").click(function() {
        // var productListId = $("#product_list_id").val();
        // var data_only = 0;
        // if ($('.bokun_post_cb:checked').length > 0) {
        //     data_only = 1;
        // }
        var selected_boheck = $('.bokun_post_cb:checked').map(function() { return $(this).val(); }).get();

        processIdsSequentially(selected_boheck);
        return false;
        $(this).text('Wait...');
        $(this).attr('disabled', true);
        var this_val = this;
        $.ajax({
            type: "POST",
            url: bkncpt_import_script_vars.ajaxurl,
            data: {
                action: "bkncpt_import_all_activities",
                product_list_id: productListId,
                import_type: 'bulk',
                nonce: bkncpt_import_script_vars.nonce,
                data_only: data_only,
                selected_boheck: selected_boheck,
            },
            success: function(response) {
                $(this_val).text('Success');
                $(this_val).attr('disabled', false);
                setTimeout(function() { $(this_val).text('Import All'); }, 1000);
                alert(response);
            }
        });
    });
    */
});