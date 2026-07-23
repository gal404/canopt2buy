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

    /* טאבים */
    $('.co2b-tab').on('click', function () {
        var tab = $(this).data('tab');
        $('.co2b-tab').removeClass('is-active');
        $(this).addClass('is-active');
        $('.co2b-panel').removeClass('is-active');
        $('.co2b-panel[data-panel="' + tab + '"]').addClass('is-active');
    });
});
