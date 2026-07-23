/* ===== Cancel Option 2 Buy — עמוד הגדרות ===== */
jQuery(function ($) {
    'use strict';

    var $preview = $('#co2b_preview_text');
    var defaultText = $preview.data('default') || '';

    /* תצוגה מקדימה חיה של טקסט החיווי */
    $('#co2b_notice_text').on('input', function () {
        var text = $.trim($(this).val());
        $preview.text(text !== '' ? text : defaultText);
    });
});
