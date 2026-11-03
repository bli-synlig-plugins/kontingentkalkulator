<?php

if (!defined('ABSPATH')) {
    exit;
}

class KK_Plugin
{
    public static function init(): void
    {
        add_action('admin_notices', array(__CLASS__, 'admin_notices'));
        KK_Settings::init();
        KK_Calculator::instance();
        KK_Input::instance();
        KK_Result::instance();

        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_front'));
        add_action('ux_builder_enqueue_scripts', array(__CLASS__, 'enqueue_builder'));
    }

    public static function has_flatsome(): bool
    {
        return class_exists('Flatsome', false)
            || class_exists('Flatsome_Default', false)
            || function_exists('add_ux_builder_shortcode')
            || function_exists('flatsome_option');
    }

    public static function admin_notices(): void
    {
        if (!current_user_can('activate_plugins') || self::has_flatsome()) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo '<strong>Kontingentkalkulator:</strong> ';
        echo esc_html__('Krever at temaet Flatsome er aktivert.', 'kontingentkalkulator');
        echo '</p></div>';
    }

    public static function enqueue_front(): void
    {
        self::enqueue_assets();
    }

    public static function enqueue_builder(string $context): void
    {
        if ($context !== 'content') {
            return;
        }

        self::enqueue_assets();
    }

    /**
     * @return array{sats: float, fradragssats: float, arsgrense: float}
     */
    public static function rates(): array
    {
        $settings = KK_Settings::get();

        return array(
            'sats' => $settings['sats'],
            'fradragssats' => $settings['fradragssats'],
            'arsgrense' => $settings['arsgrense'],
        );
    }

    /**
     * @return array{sats: float, fradragssats: float, arsgrense: float, insurances: list<array{name: string, description: string, price: float}>}
     */
    public static function front_config(): array
    {
        $rates = self::rates();
        $rates['insurances'] = KK_Settings::active_insurances_public();

        return $rates;
    }

    private static function enqueue_assets(): void
    {
        wp_enqueue_style(
            'kontingentkalkulator',
            KK_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            self::asset_ver('assets/css/frontend.css')
        );

        wp_enqueue_script(
            'kontingentkalkulator',
            KK_PLUGIN_URL . 'assets/js/calculator.js',
            array(),
            self::asset_ver('assets/js/calculator.js'),
            true
        );

        wp_localize_script('kontingentkalkulator', 'KKSettings', self::front_config());
    }

    public static function asset_ver(string $relative): string
    {
        $path = KK_PLUGIN_DIR . $relative;
        $mtime = is_readable($path) ? (string) filemtime($path) : '';

        return KK_VERSION . ($mtime !== '' ? '.' . $mtime : '');
    }
}
