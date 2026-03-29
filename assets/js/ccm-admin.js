/**
 * Coupon Commission Manager - Admin JS
 */
(function ($) {
    'use strict';

    // ========== Autocomplete ==========

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
            minLength: 0,
            select: function (event, ui) {
                if (ui.item.is_new && ui.item.new_code) {
                    var newCode = ui.item.new_code;
                    $input.val(newCode);
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

        $input.autocomplete('instance')._renderItem = function (ul, item) {
            var label;
            if (item.is_new) {
                label = '<strong style="color:#2271b1;">' + $('<span>').text(item.label).html() + '</strong>';
            } else {
                label = $('<span>').text(item.label).html();
                if (label.indexOf(' — ') > -1) {
                    label = '<span style="padding-left:12px;color:#555;">' + label + '</span>';
                }
            }
            return $('<li>').append('<div>' + label + '</div>').appendTo(ul);
        };

        if (type === 'products') {
            $input.on('focus', function () {
                if ($input.val() === '') {
                    $input.autocomplete('search', '');
                }
            });
        }

        $input.on('input', function () {
            if ($(this).val() === '') {
                $hidden.val('');
            }
        });
    }

    // ========== Commission Email Modal ==========

    var pendingLogIds = [];

    function openEmailModal(logIds) {
        pendingLogIds = logIds;
        var $modal = $('#ccm-email-modal');
        var $body = $('#ccm-email-modal-body');

        $body.html('<p class="ccm-modal-loading">載入中...</p>');
        $modal.show();
        $('#ccm-modal-send').prop('disabled', false).text('確認寄出並標記已付');

        // Fetch preview
        $.ajax({
            url: ccmAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'ccm_preview_commission_email',
                nonce: ccmAdmin.nonce,
                log_ids: logIds,
            },
            success: function (res) {
                if (!res.success) {
                    $body.html('<p style="color:red;">' + (res.data.message || '載入失敗') + '</p>');
                    return;
                }

                var partners = res.data.partners;
                var html = '';

                $.each(partners, function (pid, data) {
                    html += '<div class="ccm-partner-block" data-partner-id="' + pid + '">';
                    html += '<h3>' + $('<span>').text(data.partner_name).html();
                    html += ' <span class="ccm-badge">' + data.log_count + ' 筆分潤，共 NT$ ' + numberFormat(data.total) + '</span>';
                    html += '</h3>';
                    html += '<label>收件人</label>';
                    html += '<input type="email" class="ccm-email-to" value="' + escAttr(data.partner_email) + '" readonly style="background:#f5f5f5;">';
                    html += '<label>主旨</label>';
                    html += '<input type="text" class="ccm-email-subject" value="' + escAttr(data.subject) + '">';
                    html += '<label>信件預覽</label>';
                    html += '<div class="ccm-email-preview" style="border:1px solid #ddd;border-radius:4px;padding:16px;background:#fff;font-size:14px;line-height:1.6;max-height:300px;overflow-y:auto;">';
                    html += data.body_html;
                    html += '</div>';
                    // Store template for sending
                    html += '<input type="hidden" class="ccm-email-body-template" value="' + escAttr(data.body_template) + '">';
                    html += '</div>';
                });

                if (!html) {
                    html = '<p>找不到對應的夥伴資料。</p>';
                }

                $body.html(html);
            },
            error: function () {
                $body.html('<p style="color:red;">請求失敗，請重試。</p>');
            }
        });
    }

    function closeEmailModal() {
        $('#ccm-email-modal').hide();
        pendingLogIds = [];
    }

    function sendCommissionEmails() {
        var $btn = $('#ccm-modal-send');
        $btn.prop('disabled', true).text('寄送中...');

        // Collect overrides from the form
        var overrides = {};
        $('#ccm-email-modal-body .ccm-partner-block').each(function () {
            var pid = $(this).data('partner-id');
            overrides[pid] = {
                subject: $(this).find('.ccm-email-subject').val(),
                body_text: $(this).find('.ccm-email-body-template').val(),
            };
        });

        $.ajax({
            url: ccmAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'ccm_send_commission_email',
                nonce: ccmAdmin.nonce,
                log_ids: pendingLogIds,
                overrides: overrides,
            },
            success: function (res) {
                if (!res.success) {
                    alert(res.data.message || '發送失敗');
                    $btn.prop('disabled', false).text('確認寄出並標記已付');
                    return;
                }

                // Show results
                var $body = $('#ccm-email-modal-body');
                var html = '<h3>寄送結果</h3>';
                $.each(res.data.results, function (pid, r) {
                    if (r.sent) {
                        html += '<div class="ccm-send-result success">✓ ' + escHtml(r.partner_name)
                              + '（' + escHtml(r.email) + '）— ' + r.log_count + ' 筆，NT$ '
                              + numberFormat(r.total) + ' — 已寄出</div>';
                    } else {
                        html += '<div class="ccm-send-result error">✗ '
                              + escHtml(r.partner_name || '未知') + ' — '
                              + escHtml(r.error || '寄送失敗') + '</div>';
                    }
                });
                $body.html(html);
                $btn.text('完成').off('click').on('click', function () {
                    closeEmailModal();
                    location.reload();
                });
                $btn.prop('disabled', false);
                $('#ccm-modal-cancel').hide();
            },
            error: function () {
                alert('請求失敗，請重試。');
                $btn.prop('disabled', false).text('確認寄出並標記已付');
            }
        });
    }

    function escAttr(s) {
        return $('<span>').text(s || '').html().replace(/"/g, '&quot;');
    }

    function escHtml(s) {
        return $('<span>').text(s || '').html();
    }

    function numberFormat(n) {
        return Number(n).toLocaleString('zh-TW');
    }

    // ========== Document Ready ==========

    $(document).ready(function () {

        // Datepicker
        $('.ccm-datepicker').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
        });

        // Init autocomplete
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
            $newRow.find('.ccm-autocomplete').first().focus();
        });

        // Remove product row
        $(document).on('click', '.ccm-remove-row', function () {
            $(this).closest('tr').remove();
        });

        // ===== Commission Email Modal: Intercept batch "標記已付" =====

        // Intercept the logs form submission for batch action
        $('#ccm-logs-form').on('submit', function (e) {
            var action = $(this).find('select[name="action"]').val() || $(this).find('select[name="action2"]').val();
            if (action !== 'mark_paid') return true; // Let other actions pass through

            e.preventDefault();
            var logIds = [];
            $(this).find('input[name="log_ids[]"]:checked').each(function () {
                logIds.push($(this).val());
            });
            if (logIds.length === 0) {
                alert('請先勾選要標記的分潤紀錄');
                return false;
            }
            openEmailModal(logIds);
            return false;
        });

        // Intercept single "付款" link clicks
        $(document).on('click', 'a[href*="mark_paid="]', function (e) {
            e.preventDefault();
            var href = $(this).attr('href');
            var match = href.match(/mark_paid=(\d+)/);
            if (match) {
                openEmailModal([match[1]]);
            }
        });

        // Modal buttons
        $('#ccm-modal-cancel, .ccm-modal-close, .ccm-modal-overlay').on('click', function () {
            closeEmailModal();
        });
        $('#ccm-modal-send').on('click', function () {
            sendCommissionEmails();
        });
    });

})(jQuery);
