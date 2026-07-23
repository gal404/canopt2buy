<?php
defined('ABSPATH') || exit;

/* ===== הגדרות התוסף — option אחד עם ברירות מחדל ===== */
class CO2B_Settings
{
    const OPTION_KEY     = 'canopt2buy_settings';
    const DEFAULT_NOTICE = 'נמכר בחנות או בהובלות סיטונאיות בלבד';

    /** @var array|null קאש לבקשה */
    private static $cache = null;

    public static function defaults(): array
    {
        return [
            'product_ids'  => [], // מזהי מוצרים חסומים מהעמוד המרכזי
            'category_ids' => [], // מזהי קטגוריות (term_id) חסומות
            'notice_text'  => self::DEFAULT_NOTICE,
            'github_token' => '', // טוקן לעדכונים אוטומטיים מריפו פרטי
        ];
    }

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = wp_parse_args(get_option(self::OPTION_KEY, []), self::defaults());
        }
        return self::$cache;
    }

    public static function get(string $key)
    {
        $all = self::all();
        return $all[$key] ?? null;
    }

    public static function update(array $changes): void
    {
        $new = array_merge(self::all(), $changes);
        update_option(self::OPTION_KEY, $new);
        self::$cache = $new;
        CO2B_Blocker::flush_cache();
    }

    /* טקסט החיווי — נפילה לברירת מחדל אם ריק */
    public static function notice_text(): string
    {
        $text = trim((string) self::get('notice_text'));
        return $text !== '' ? $text : self::DEFAULT_NOTICE;
    }
}
