const ajaxUrl       = br_admin.ajax_url;
        const security      = br_admin.nonce;
        const $selectAll    = $('#br-select-all');
        const $checkboxes   = $('.br-checkbox');
        const $previewBtn   = $('#br-preview-button');
        const $applyBtn     = $('#br-apply-button');
        const $templateInput= $('#br-filename-template');
        const $noticeArea   = $('#br-notice-area');

        function getSelectedIds() {
            return $checkboxes.filter(':checked').map(function() {
                return $(this).data('id');
            }).get();
        }

        function showNotice(type, message) {
            const $notice = $('<div>', {
                class: 'notice notice-' + type + ' is-dismissible'
            }).append($('<p>').text(message));
            $noticeArea.empty().append($notice);
        }

        $selectAll.on('change', function() {
            const checked = $(this).prop('checked');
            $checkboxes.prop('checked', checked).trigger('change');
        });

        $checkboxes.on('change', function() {
            const hasSelection = getSelectedIds().length > 0;
            $previewBtn.prop('disabled', !hasSelection);
            $applyBtn.prop('disabled', !hasSelection);
        });

        $previewBtn.on('click', function() {
            const ids      = getSelectedIds();
            const template = $templateInput.val();
            $previewBtn.prop('disabled', true);
            $noticeArea.empty();

            $.post(ajaxUrl, {
                action:   'bulk_media_renamer_preview',
                ids:      ids,
                template: template,
                security: security
            }, function(response) {
                if (response.success) {
                    $.each(response.data, function(id, newName) {
                        $('#br-new-name-' + id).text(newName);
                    });
                } else {
                    showNotice('error', response.data);
                }
                $previewBtn.prop('disabled', false);
            }, 'json').fail(function() {
                showNotice('error', 'AJAX request failed.');
                $previewBtn.prop('disabled', false);
            });
        });

        $applyBtn.on('click', function() {
            const ids = getSelectedIds();
            $applyBtn.prop('disabled', true);
            $noticeArea.empty();

            $.post(ajaxUrl, {
                action:   'bulk_media_renamer_apply',
                ids:      ids,
                security: security
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    showNotice('error', response.data);
                    $applyBtn.prop('disabled', false);
                }
            }, 'json').fail(function() {
                showNotice('error', 'AJAX request failed.');
                $applyBtn.prop('disabled', false);
            });
        });
    });
})(jQuery);