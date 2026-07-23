<?php
defined('ABSPATH') || exit;

/* ===== הגדרות התוסף — option אחד עם ברירות מחדל ===== */
class CO2B_Settings
{
    const OPTION_KEY      = 'canopt2buy_settings';
    const DEFAULT_NOTICE  = 'נמכר בחנות או בהובלות סיטונאיות בלבד';
    const DEFAULT_CONFIRM = 'תודה! הבקשה התקבלה וניצור קשר בהקדם.';

    /** @var array|null קאש לבקשה */
    private static $cache = null;

    public static function defaults(): array
    {
        return [
            /* ===== חסימת רכישה (v1.0) ===== */
            'product_ids'  => [], // מזהי מוצרים חסומים מהעמוד המרכזי
            'category_ids' => [], // מזהי קטגוריות (term_id) חסומות
            'notice_text'  => self::DEFAULT_NOTICE,
            'github_token' => '', // טוקן לעדכונים אוטומטיים מריפו פרטי

            /* ===== הזמנה סיטונאית (v1.1) ===== */
            'wholesale_enabled'         => true,
            'wholesale_product_ids'     => [],   // בחירה סיטונאית נפרדת (בנוסף לחסומים)
            'wholesale_category_ids'    => [],
            'wholesale_vat'             => 18,   // אחוז מע"מ (ניתן לשינוי)
            'wholesale_min'             => 1000, // מינימום להזמנה, כולל מע"מ
            'wholesale_free_ship'       => 4500, // מעל זה — הובלה חינם, כולל מע"מ
            'wholesale_ship_net'        => 195,  // עלות הובלה נטו (אם אין מוצר מקושר)
            'wholesale_ship_product_id' => 0,    // מוצר הובלה מקושר (מחירו gross)
            'wholesale_shipping_terms'  => '',   // נהלי הובלה (טקסט לאישור)
            'wholesale_unloading_terms' => '',   // נהלי פריקה (טקסט לאישור)
            'wholesale_alert_email'     => '',   // ריק → admin_email
            'wholesale_catalog_page_id' => 0,
            'wholesale_cart_page_id'    => 0,
            'wholesale_confirm_text'    => self::DEFAULT_CONFIRM,
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
        if (class_exists('CO2B_Wholesale')) {
            CO2B_Wholesale::flush_cache();
        }
    }

    /* טקסט החיווי — נפילה לברירת מחדל אם ריק */
    public static function notice_text(): string
    {
        $text = trim((string) self::get('notice_text'));
        return $text !== '' ? $text : self::DEFAULT_NOTICE;
    }

    /* טקסט אישור ההזמנה הסיטונאית — נפילה לברירת מחדל אם ריק */
    public static function confirm_text(): string
    {
        $text = trim((string) self::get('wholesale_confirm_text'));
        return $text !== '' ? $text : self::DEFAULT_CONFIRM;
    }

    /* יעד המייל להתראות סיטונאות — נפילה למייל המנהל */
    public static function alert_email(): string
    {
        $email = trim((string) self::get('wholesale_alert_email'));
        return $email !== '' ? $email : (string) get_option('admin_email');
    }
}
