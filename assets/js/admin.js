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

    /* טאבים — מופעלים רק אחרי שה-JS נטען (אחרת כל הפאנלים מוצגים) */
    $('.co2b-wrap').addClass('co2b-tabs-ready');

    $('.co2b-tab').on('click', function () {
        var tab = $(this).data('tab');
        $('.co2b-tab').removeClass('is-active');
        $(this).addClass('is-active');
        $('.co2b-panel').removeClass('is-active');
        $('.co2b-panel[data-panel="' + tab + '"]').addClass('is-active');
    });

    /* משיכת המוצרים והקטגוריות החסומים אל הבחירה הסיטונאית */
    function co2bCopyOptions(srcSel, dstSel) {
        var $src = $(srcSel), $dst = $(dstSel), added = 0;
        if (!$src.length || !$dst.length) { return 0; }
        $src.find('option').each(function () {
            var val = this.value, text = this.text;
            if (!val) { return; }
            var $existing = $dst.find('option[value="' + val + '"]');
            if ($existing.length) {
                $existing.prop('selected', true);
            } else {
                $dst.append(new Option(text, val, true, true));
                added++;
            }
        });
        $dst.trigger('change'); // רענון selectWoo
        return added;
    }

    $('#co2b-pull-blocked').on('click', function () {
        var $btn = $(this);
        co2bCopyOptions('#co2b_block_products', '#co2b_ws_products');
        co2bCopyOptions('#co2b_block_categories', '#co2b_ws_categories');
        var label = $btn.text();
        $btn.text('✓ נמשכו — לחצו "שמור הגדרות"').prop('disabled', true);
        setTimeout(function () { $btn.text(label).prop('disabled', false); }, 2600);
    });
});
