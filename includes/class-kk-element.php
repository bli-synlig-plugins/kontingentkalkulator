<?php

if (!defined('ABSPATH')) {
    exit;
}

abstract class KK_Element
{
    protected string $tag = '';

    private static array $instances = array();

    public static function instance(): static
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    protected function __construct()
    {
        add_action('ux_builder_setup', array($this, 'register'));
        add_shortcode($this->tag, array($this, 'render'));
    }

    abstract protected function metadata(): array;

    abstract protected function options(): array;

    abstract public function render($atts, $content = ''): string;

    public function register(): void
    {
        if (!function_exists('add_ux_builder_shortcode')) {
            return;
        }

        $args = $this->metadata();
        $args['options'] = $this->options();
        add_ux_builder_shortcode($this->tag, $args);
    }

    protected function parse_atts($user_atts): array
    {
        $defaults = array();
        $this->collect_defaults($this->options(), $defaults);

        return shortcode_atts($defaults, is_array($user_atts) ? $user_atts : array(), $this->tag);
    }

    protected function collect_defaults(array $options, array &$defaults): void
    {
        foreach ($options as $key => $option) {
            if (isset($option['type']) && $option['type'] === 'group' && isset($option['options']) && is_array($option['options'])) {
                $this->collect_defaults($option['options'], $defaults);
            } else {
                if (isset($option['default'])) {
                    $defaults[$key] = $option['default'];
                }
                if (!empty($option['responsive'])) {
                    $defaults[$key . '__md'] = '';
                    $defaults[$key . '__sm'] = '';
                }
            }
        }
    }

    protected function uid(): string
    {
        static $counts = array();
        $counts[$this->tag] = ($counts[$this->tag] ?? 0) + 1;

        return $this->tag . '-' . $counts[$this->tag] . '-' . substr(uniqid('', true), -5);
    }

    /**
     * @return list<array{title: string, value: string}>
     */
    protected function color_helpers(): array
    {
        $file = get_template_directory() . '/inc/builder/shortcodes/helpers/colors.php';
        if (is_readable($file)) {
            $helpers = require $file;
            if (is_array($helpers) && $helpers !== []) {
                return $helpers;
            }
        }

        return array(
            array('title' => 'Dark', 'value' => 'rgb(0,0,0)'),
            array('title' => 'White', 'value' => 'rgb(255,255,255)'),
            array('title' => 'Primary', 'value' => ''),
            array('title' => 'Secondary', 'value' => ''),
            array('title' => 'Success', 'value' => ''),
        );
    }

    /**
     * @param array<string, string> $vars
     */
    protected function css_vars(array $vars): string
    {
        $parts = array();
        foreach ($vars as $name => $value) {
            $color = $this->sanitize_color((string) $value);
            if ($color === '') {
                continue;
            }
            $parts[] = $name . ':' . $color;
        }

        return $parts === [] ? '' : ' style="' . esc_attr(implode(';', $parts)) . '"';
    }

    protected function padding_slider(string $heading, string $default): array
    {
        return array(
            'type' => 'slider',
            'heading' => $heading,
            'default' => $default,
            'min' => '0',
            'max' => '80',
            'unit' => 'px',
            'step' => '2',
            'responsive' => true,
        );
    }

    /**
     * @param array<string, string> $vars
     */
    protected function inline_vars(array $vars): string
    {
        $parts = array();
        foreach ($vars as $name => $value) {
            if ($value === '') {
                continue;
            }
            $parts[] = $name . ':' . $value;
        }

        return $parts === [] ? '' : ' style="' . esc_attr(implode(';', $parts)) . '"';
    }

    protected function padding_style(string $id, array $atts): string
    {
        $clean = array();
        foreach (array('padding_y', 'padding_x') as $key) {
            foreach (array($key, $key . '__md', $key . '__sm') as $attr) {
                if (!isset($atts[$attr]) || $atts[$attr] === '') {
                    continue;
                }
                $clean[$attr] = (string) max(0, (int) $atts[$attr]);
            }
        }

        if ($clean === []) {
            return '';
        }

        if (function_exists('ux_builder_element_style_tag')) {
            return ux_builder_element_style_tag($id, array(
                'padding_y' => array(
                    'selector' => '',
                    'property' => '--kk-py',
                    'unit' => 'px',
                ),
                'padding_x' => array(
                    'selector' => '',
                    'property' => '--kk-px',
                    'unit' => 'px',
                ),
            ), $clean);
        }

        $selector = '#' . preg_replace('/[^A-Za-z0-9_-]/', '', $id);
        if ($selector === '#') {
            return '';
        }

        $y = isset($clean['padding_y']) ? $clean['padding_y'] : '32';
        $x = isset($clean['padding_x']) ? $clean['padding_x'] : '28';

        return '<style>' . $selector . '{--kk-py:' . esc_html($y) . 'px;--kk-px:' . esc_html($x) . 'px;}</style>';
    }

    protected function colorpicker(string $heading, string $default = ''): array
    {
        return array(
            'type' => 'colorpicker',
            'heading' => $heading,
            'default' => $default,
            'format' => 'rgb',
            'helpers' => $this->color_helpers(),
        );
    }

    protected function heading_tag(string $heading = '', string $default = 'h3'): array
    {
        return array(
            'type' => 'select',
            'heading' => $heading !== '' ? $heading : __('Overskriftsnivå', 'kontingentkalkulator'),
            'default' => $default,
            'options' => array(
                'h1' => 'H1',
                'h2' => 'H2',
                'h3' => 'H3',
                'h4' => 'H4',
                'h5' => 'H5',
                'h6' => 'H6',
            ),
        );
    }

    protected function title_html(string $tag, string $class, string $text): string
    {
        if ($text === '') {
            return '';
        }

        $tag = $this->sanitize_heading_tag($tag);

        return '<' . $tag . ' class="' . esc_attr($class) . '">' . esc_html($text) . '</' . $tag . '>';
    }

    protected function sanitize_heading_tag(string $tag): string
    {
        $tag = strtolower(trim($tag));

        return in_array($tag, array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'), true) ? $tag : 'h3';
    }

    protected function info_icon(): string
    {
        if (shortcode_exists('wp_icon')) {
            $svg = trim(do_shortcode('[wp_icon library="lucide" name="info" size="16" color="currentColor" class="kk-info__icon"]'));
            if ($svg !== '' && str_contains($svg, '<svg')) {
                return $svg;
            }
        }

        return '<svg class="kk-info__icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';
    }

    protected function sanitize_color(string $color): string
    {
        $color = trim($color);
        if ($color === '') {
            return '';
        }

        if (strcasecmp($color, 'currentColor') === 0 || $color === 'inherit' || $color === 'transparent') {
            return $color === 'currentColor' ? 'currentColor' : $color;
        }

        if (preg_match('/^var\(\s*(--[a-zA-Z0-9_-]+)\s*\)$/', $color, $matches)) {
            return 'var(' . $matches[1] . ')';
        }

        if (function_exists('sanitize_hex_color')) {
            $hex = sanitize_hex_color($color);
            if (is_string($hex) && $hex !== '') {
                return $hex;
            }
        } elseif (preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color)) {
            return $color;
        }

        if (preg_match('/^(?:rgb|hsl)a?\([^)]+\)$/i', $color)) {
            return $color;
        }

        if (preg_match('/^[a-z]{3,20}$/i', $color)) {
            return strtolower($color);
        }

        return '';
    }
}
