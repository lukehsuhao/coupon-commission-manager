/**
 * Coupon Commission Manager - Admin JS
 */
(function ($) {
    'use strict';

    function initAutocomplete($el) {
        var $input = $el;
        var type = $input.data('type');
        var $hidden = $input.next('input[type="hidden"]');

        $input.autocomplete({
            source: function (request, response) {
                $.ajax({
                    url: ccmAdmin.ajaxUrl,
                    data: {
                        action: 'ccm_search_' + type,
                        term: request.term,
                        nonce: ccmAdmin.nonce,
                    },
                    success: function (data) {
                        response(data);
                    },
                });
            },
            minLength: 2,
            select: function (event, ui) {
                // Handle "create new coupon" option
                if (ui.item.is_new && ui.item.new_code) {
                    var newCode = ui.item.new_code;
                    $input.val(newCode);

                    // Create the coupon via AJAX
                    $.ajax({
                        url: ccmAdmin.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'ccm_create_coupon',
                            code: newCode,
                            nonce: ccmAdmin.nonce,
                        },
                        success: function (res) {
                            if (res.success) {
                                $hidden.val(res.data.id);
                                $input.val(res.data.code);
                            } else {
                                alert('建立折扣碼失敗');
                                $hidden.val('');
                            }
                        },
                    });
                    return false;
                }

                $input.val(ui.item.label);
                $hidden.val(ui.item.id);
                return false;
            },
        });

        // Custom rendering to highlight the "create new" option
        $input.autocomplete('instance')._renderItem = function (ul, item) {
            var label = item.label;
            if (item.is_new) {
                label = '<strong style="color:#2271b1;">' + $('<span>').text(item.label).html() + '</strong>';
            } else {
                label = $('<span>').text(item.label).html();
            }
            return $('<li>').append('<div>' + label + '</div>').appendTo(ul);
        };

        $input.on('input', function () {
            if ($(this).val() === '') {
                $hidden.val('');
            }
        });
    }

    $(document).ready(function () {

        // Datepicker
        $('.ccm-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
        });

        // Init autocomplete on existing elements
        $('.ccm-autocomplete').each(function () {
            initAutocomplete($(this));
        });

        // Add product row
        $('#ccm-add-product-row').on('click', function () {
            var template = document.getElementById('ccm-product-row-template');
            if (!template) return;

            var clone = template.content.cloneNode(true);
            $('#ccm-rules-body').append(clone);

            var $newRow = $('#ccm-rules-body tr:last');
            $newRow.find('.ccm-autocomplete').each(function () {
                initAutocomplete($(this));
            });
        });

        // Remove product row
        $(document).on('click', '.ccm-remove-row', function () {
            $(this).closest('tr').remove();
        });
    });

})(jQuery);
