<?php

namespace App\Models;

use Config;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Setting
 *
 * Key-value store for application configuration, supporting public and private scopes,
 * secret masking, categorized retrieval, and runtime cache synchronization.
 *
 * @package App\Models
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Setting extends Model
{
    /**
     * @var string
     */
    protected $table = 'settings';

    /**
     * @var array
     */
    protected $fillable = ['key', 'value'];

    /**
     * Keys containing sensitive secrets that must be masked before output.
     */
    public const SENSITIVE_KEYS = [
        'sms_api_key',
        'stripe_secret_key',
        'stripe_key',
        'paypal_secret_id',
        'paypal_client_id',
        'bkash_app_key',
        'bkash_app_secret',
        'bkash_password',
        'mail_password',
        'aws_secret_access_key',
        'pusher_app_secret',
    ];

    /**
     * Keys allowed to be served publicly without authentication.
     */
    public const PUBLIC_KEYS = [
        'site_name',
        'site_title',
        'default_email_address',
        'currency_code',
        'currency_symbol',
        'site_logo',
        'site_favicon',
        'footer_copyright_text',
        'seo_meta_title',
        'seo_meta_description',
        'short_about',
        'social_facebook',
        'social_twitter',
        'social_instagram',
        'social_linkedin',
        'social_youtube',
        'contact_phone',
        'contact_address',
        'office_hours',
        'admin_theme_color',
        'admin_accent_color',
        'admin_font_family',
        'admin_border_radius',
        'admin_glass_effect',
        'admin_shadow_effect',
        'admin_animation',
        'admin_card_style',
        'admin_custom_css',
    ];

    /**
     * Permitted setting keys that can be updated via the API.
     */
    public const PERMITTED_KEYS = [
        // General & Contact
        'site_name',
        'site_title',
        'default_email_address',
        'currency_code',
        'currency_symbol',
        'contact_phone',
        'contact_address',
        'office_hours',
        'short_about',

        // Branding
        'site_logo',
        'site_favicon',

        // Footer & SEO
        'footer_copyright_text',
        'seo_meta_title',
        'seo_meta_description',

        // Social
        'social_facebook',
        'social_twitter',
        'social_instagram',
        'social_linkedin',
        'social_youtube',

        // Analytics
        'google_analytics',
        'facebook_pixels',

        // Payments
        'stripe_payment_method',
        'stripe_key',
        'stripe_secret_key',
        'paypal_payment_method',
        'paypal_client_id',
        'paypal_secret_id',
        'bkash_payment_method',
        'bkash_app_key',
        'bkash_app_secret',
        'bkash_user_name',
        'bkash_password',

        // SMS Gateway
        'sms_enabled',
        'sms_provider',
        'sms_url',
        'sms_api_key',
        'sms_sender_id',
        'sms_rate_limit',

        // UI / Theme
        'admin_theme_color',
        'admin_accent_color',
        'admin_font_family',
        'admin_border_radius',
        'admin_glass_effect',
        'admin_shadow_effect',
        'admin_animation',
        'admin_card_style',
        'admin_custom_css',
    ];

    /**
     * Retrieve a setting value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $setting = new self();
        $entry = $setting->where('key', $key)->first();
        if (!$entry) {
            return $default;
        }
        return $entry->value;
    }

    /**
     * Set a setting value by key.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function set($key, $value = null)
    {
        $setting = new self();
        $entry = $setting->where('key', $key)->first();
        if (!$entry) {
            $entry = new self();
            $entry->key = $key;
        }
        $entry->value = $value;
        $entry->saveOrFail();

        Config::set($key, $value);
        Config::set('settings.' . $key, $value);

        return true;
    }

    /**
     * Check if a key holds sensitive data.
     *
     * @param string $key
     * @return bool
     */
    public static function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        if (in_array($lower, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return (bool) preg_match('/(secret|api_key|password|client_id|app_secret)/i', $key);
    }

    /**
     * Check if a key is permitted for API updates.
     *
     * @param string $key
     * @return bool
     */
    public static function isPermittedKey(string $key): bool
    {
        if (in_array($key, self::PERMITTED_KEYS, true)) {
            return true;
        }

        // Allow any SMS template override key
        if (str_starts_with($key, 'sms_template_')) {
            return true;
        }

        return false;
    }

    /**
     * Mask a sensitive secret value.
     *
     * @param string|null $value
     * @return string
     */
    public static function maskSecret(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $length = strlen($value);
        if ($length <= 6) {
            return str_repeat('•', 8);
        }

        if ($length <= 12) {
            return substr($value, 0, 2) . '••••' . substr($value, -2);
        }

        return substr($value, 0, 4) . '••••••••' . substr($value, -4);
    }

    /**
     * Determine if a submitted value is a masked placeholder.
     *
     * @param string|null $value
     * @return bool
     */
    public static function isMaskedValue(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return str_contains($value, '•') || str_contains($value, '***') || preg_match('/^[*•]{4,}$/', $value);
    }

    /**
     * Get all public settings.
     *
     * @return array
     */
    public static function getPublicSettings(): array
    {
        $results = [];
        $settings = self::whereIn('key', self::PUBLIC_KEYS)->get()->keyBy('key');

        foreach (self::PUBLIC_KEYS as $key) {
            $results[$key] = $settings->has($key) ? $settings[$key]->value : (config('settings.' . $key) ?? null);
        }

        return $results;
    }

    /**
     * Get all settings with sensitive keys masked.
     *
     * @return array
     */
    public static function getAllSettingsWithMasking(): array
    {
        $settings = self::all();
        $results = [];

        foreach ($settings as $setting) {
            if (self::isSensitiveKey($setting->key)) {
                $results[$setting->key] = self::maskSecret($setting->value);
            } else {
                $results[$setting->key] = $setting->value;
            }
        }

        return $results;
    }

    /**
     * Get categorized settings with sensitive values masked.
     *
     * @return array
     */
    public static function getGroupedSettingsWithMasking(): array
    {
        $all = self::getAllSettingsWithMasking();

        return [
            'general' => [
                'site_name'             => $all['site_name'] ?? config('settings.site_name'),
                'site_title'            => $all['site_title'] ?? config('settings.site_title'),
                'default_email_address' => $all['default_email_address'] ?? config('settings.default_email_address'),
                'currency_code'         => $all['currency_code'] ?? config('settings.currency_code', 'BDT'),
                'currency_symbol'       => $all['currency_symbol'] ?? config('settings.currency_symbol', '৳'),
                'contact_phone'         => $all['contact_phone'] ?? config('settings.contact_phone'),
                'contact_address'       => $all['contact_address'] ?? config('settings.contact_address'),
                'office_hours'          => $all['office_hours'] ?? config('settings.office_hours'),
            ],
            'branding' => [
                'site_logo'    => $all['site_logo'] ?? config('settings.site_logo'),
                'site_favicon' => $all['site_favicon'] ?? config('settings.site_favicon'),
            ],
            'footer_seo' => [
                'footer_copyright_text' => $all['footer_copyright_text'] ?? config('settings.footer_copyright_text'),
                'seo_meta_title'        => $all['seo_meta_title'] ?? config('settings.seo_meta_title'),
                'seo_meta_description'  => $all['seo_meta_description'] ?? config('settings.seo_meta_description'),
                'short_about'           => $all['short_about'] ?? config('settings.short_about'),
            ],
            'social' => [
                'social_facebook'  => $all['social_facebook'] ?? config('settings.social_facebook'),
                'social_twitter'   => $all['social_twitter'] ?? config('settings.social_twitter'),
                'social_instagram' => $all['social_instagram'] ?? config('settings.social_instagram'),
                'social_linkedin'  => $all['social_linkedin'] ?? config('settings.social_linkedin'),
                'social_youtube'   => $all['social_youtube'] ?? config('settings.social_youtube'),
            ],
            'analytics' => [
                'google_analytics' => $all['google_analytics'] ?? config('settings.google_analytics'),
                'facebook_pixels'  => $all['facebook_pixels'] ?? config('settings.facebook_pixels'),
            ],
            'payments' => [
                'stripe_payment_method' => $all['stripe_payment_method'] ?? config('settings.stripe_payment_method', '0'),
                'stripe_key'            => $all['stripe_key'] ?? self::maskSecret(config('settings.stripe_key')),
                'stripe_secret_key'     => $all['stripe_secret_key'] ?? self::maskSecret(config('settings.stripe_secret_key')),
                'paypal_payment_method' => $all['paypal_payment_method'] ?? config('settings.paypal_payment_method', '0'),
                'paypal_client_id'      => $all['paypal_client_id'] ?? self::maskSecret(config('settings.paypal_client_id')),
                'paypal_secret_id'      => $all['paypal_secret_id'] ?? self::maskSecret(config('settings.paypal_secret_id')),
                'bkash_payment_method'  => $all['bkash_payment_method'] ?? config('settings.bkash_payment_method', '1'),
                'bkash_app_key'         => $all['bkash_app_key'] ?? self::maskSecret(config('services.bkash.app_key')),
                'bkash_app_secret'      => $all['bkash_app_secret'] ?? self::maskSecret(config('services.bkash.app_secret')),
                'bkash_user_name'       => $all['bkash_user_name'] ?? config('services.bkash.user_name'),
                'bkash_password'        => $all['bkash_password'] ?? self::maskSecret(config('services.bkash.password')),
            ],
            'sms' => [
                'sms_enabled'   => $all['sms_enabled'] ?? config('settings.sms_enabled', '1'),
                'sms_provider'  => $all['sms_provider'] ?? config('settings.sms_provider', 'elitbuzz'),
                'sms_url'       => $all['sms_url'] ?? config('services.sms.url', env('SMS_URL', 'https://api.elitbuzz-bd.com/smsapi')),
                'sms_sender_id' => $all['sms_sender_id'] ?? config('services.sms.sender_id', env('SMS_SENDER_ID')),
                'sms_api_key'   => $all['sms_api_key'] ?? self::maskSecret(config('services.sms.api_key', env('SMS_API_KEY'))),
            ],
            'ui_theme' => [
                'admin_theme_color'   => $all['admin_theme_color'] ?? config('settings.admin_theme_color', 'gradient-purple'),
                'admin_accent_color'  => $all['admin_accent_color'] ?? config('settings.admin_accent_color', '#667eea'),
                'admin_font_family'   => $all['admin_font_family'] ?? config('settings.admin_font_family', 'default'),
                'admin_border_radius' => $all['admin_border_radius'] ?? config('settings.admin_border_radius', '8'),
                'admin_glass_effect'  => $all['admin_glass_effect'] ?? config('settings.admin_glass_effect', '0'),
                'admin_shadow_effect' => $all['admin_shadow_effect'] ?? config('settings.admin_shadow_effect', '1'),
                'admin_animation'     => $all['admin_animation'] ?? config('settings.admin_animation', '1'),
                'admin_card_style'    => $all['admin_card_style'] ?? config('settings.admin_card_style', 'default'),
                'admin_custom_css'    => $all['admin_custom_css'] ?? config('settings.admin_custom_css', ''),
            ],
        ];
    }
}