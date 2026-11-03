<?php

if (!defined('ABSPATH')) {
    exit;
}

class KK_Calculator extends KK_Element
{
    protected string $tag = 'kk_calculator';

    protected function metadata(): array
    {
        return array(
            'name' => __('Kontingentkalkulator', 'kontingentkalkulator'),
            'category' => __('Kontingentkalkulator', 'kontingentkalkulator'),
            'type' => 'container',
            'allow' => array('kk_input', 'kk_result'),
            'info' => '{{ class }}',
            'presets' => array(
                array(
                    'name' => __('Standard', 'kontingentkalkulator'),
                    'content' => '[kk_calculator][kk_input][/kk_input][kk_result][/kk_result][/kk_calculator]',
                ),
            ),
        );
    }

    protected function options(): array
    {
        return array(
            'layout_options' => array(
                'type' => 'group',
                'heading' => __('Layout', 'kontingentkalkulator'),
                'options' => array(
                    'class' => array(
                        'type' => 'textfield',
                        'heading' => __('Ekstra CSS-klasse', 'kontingentkalkulator'),
                        'default' => '',
                    ),
                    'gap' => array(
                        'type' => 'slider',
                        'heading' => __('Mellomrom', 'kontingentkalkulator'),
                        'default' => '0',
                        'min' => '0',
                        'max' => '80',
                        'step' => '1',
                        'unit' => 'px',
                    ),
                    'padding_y' => $this->padding_slider(__('Padding topp/bunn', 'kontingentkalkulator'), '32'),
                    'padding_x' => $this->padding_slider(__('Padding venstre/høyre', 'kontingentkalkulator'), '28'),
                ),
            ),
        );
    }

    public function render($atts, $content = ''): string
    {
        $atts = $this->parse_atts($atts);
        $rates = KK_Plugin::rates();
        $uid = $this->uid();
        $class = trim('kk-calculator ' . $atts['class']);
        $gap = max(0, (int) $atts['gap']);
        $style = $this->inline_vars(array(
            '--kk-gap' => $gap > 0 ? $gap . 'px' : '',
        ));

        ob_start();
        ?>
        <div
            id="<?php echo esc_attr($uid); ?>"
            class="<?php echo esc_attr($class); ?>"
            data-kk-sats="<?php echo esc_attr((string) $rates['sats']); ?>"
            data-kk-fradrag="<?php echo esc_attr((string) $rates['fradragssats']); ?>"
            data-kk-arsgrense="<?php echo esc_attr((string) $rates['arsgrense']); ?>"
            <?php echo $style; ?>
        >
            <?php echo do_shortcode($content); ?>
        </div>
        <?php echo $this->padding_style($uid, $atts); ?>
        <?php

        return (string) ob_get_clean();
    }
}
