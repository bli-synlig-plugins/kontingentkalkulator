<?php

if (!defined('ABSPATH')) {
    exit;
}

class KK_Input extends KK_Element
{
    protected string $tag = 'kk_input';

    protected function metadata(): array
    {
        return array(
            'name' => __('Kalkulator: input', 'kontingentkalkulator'),
            'category' => __('Kontingentkalkulator', 'kontingentkalkulator'),
            'require' => array('kk_calculator'),
            'wrap' => false,
            'info' => '{{ default_mode }}',
        );
    }

    protected function options(): array
    {
        return array(
            'style_options' => array(
                'type' => 'group',
                'heading' => __('Utseende', 'kontingentkalkulator'),
                'options' => array(
                    'primary_color' => $this->colorpicker(__('Primærfarge', 'kontingentkalkulator')),
                    'background' => $this->colorpicker(__('Bakgrunnsfarge', 'kontingentkalkulator')),
                    'title_tag' => $this->heading_tag(),
                    'class' => array(
                        'type' => 'textfield',
                        'heading' => __('Ekstra CSS-klasse', 'kontingentkalkulator'),
                        'default' => '',
                    ),
                ),
            ),
            'value_options' => array(
                'type' => 'group',
                'heading' => __('Standardverdier', 'kontingentkalkulator'),
                'options' => array(
                    'default_mode' => array(
                        'type' => 'radio-buttons',
                        'heading' => __('Standardvalg', 'kontingentkalkulator'),
                        'default' => 'monthly',
                        'full_width' => true,
                        'options' => array(
                            'monthly' => array('title' => __('Månedslønn', 'kontingentkalkulator')),
                            'hourly' => array('title' => __('Timelønn', 'kontingentkalkulator')),
                        ),
                    ),
                    'default_monthly' => array(
                        'type' => 'textfield',
                        'heading' => __('Månedslønn', 'kontingentkalkulator'),
                        'default' => '0',
                    ),
                    'default_hourly' => array(
                        'type' => 'textfield',
                        'heading' => __('Timelønn', 'kontingentkalkulator'),
                        'default' => '0',
                    ),
                    'default_hours' => array(
                        'type' => 'textfield',
                        'heading' => __('Timer per uke', 'kontingentkalkulator'),
                        'default' => '37,5',
                    ),
                ),
            ),
        );
    }

    public function render($atts, $content = ''): string
    {
        $atts = $this->parse_atts($atts);
        $copy = KK_Settings::strings();
        $uid = $this->uid();
        $mode = $atts['default_mode'] === 'hourly' ? 'hourly' : 'monthly';
        $class = trim('kk-input ' . $atts['class']);
        $style = $this->css_vars(array(
            '--kk-color' => $atts['primary_color'],
            '--kk-bg' => $atts['background'],
        ));
        $monthlyId = $uid . '-monthly';
        $hourlyId = $uid . '-hourly';
        $hoursId = $uid . '-hours';

        ob_start();
        ?>
        <div id="<?php echo esc_attr($uid); ?>" class="<?php echo esc_attr($class); ?>" data-kk-input data-kk-mode="<?php echo esc_attr($mode); ?>"<?php echo $style; ?>>
            <?php echo $this->title_html($atts['title_tag'], 'kk-input__title', $copy['input_title']); ?>
            <?php if ($copy['input_help'] !== '') : ?>
                <p class="kk-input__help"><?php echo esc_html($copy['input_help']); ?></p>
            <?php endif; ?>

            <?php if ($copy['toggle_heading'] !== '') : ?>
                <p class="kk-label"><?php echo esc_html($copy['toggle_heading']); ?></p>
            <?php endif; ?>
            <div class="kk-toggle" role="group" aria-label="<?php echo esc_attr($copy['toggle_heading']); ?>">
                <button
                    type="button"
                    class="kk-toggle__btn"
                    data-kk-set-mode="monthly"
                    aria-pressed="<?php echo $mode === 'monthly' ? 'true' : 'false'; ?>"
                ><?php echo esc_html($copy['label_monthly_mode']); ?></button>
                <button
                    type="button"
                    class="kk-toggle__btn"
                    data-kk-set-mode="hourly"
                    aria-pressed="<?php echo $mode === 'hourly' ? 'true' : 'false'; ?>"
                ><?php echo esc_html($copy['label_hourly_mode']); ?></button>
            </div>

            <div class="kk-fields" data-kk-fields="monthly"<?php echo $mode === 'hourly' ? ' hidden' : ''; ?>>
                <label class="kk-label" for="<?php echo esc_attr($monthlyId); ?>"><?php echo esc_html($copy['label_monthly']); ?></label>
                <div class="kk-control">
                    <input
                        id="<?php echo esc_attr($monthlyId); ?>"
                        class="kk-control__input"
                        type="text"
                        inputmode="decimal"
                        autocomplete="off"
                        data-kk-field="monthly"
                        value="<?php echo esc_attr($atts['default_monthly']); ?>"
                    >
                    <?php if ($copy['suffix_kr'] !== '') : ?>
                        <span class="kk-control__suffix"><?php echo esc_html($copy['suffix_kr']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="kk-fields" data-kk-fields="hourly"<?php echo $mode === 'monthly' ? ' hidden' : ''; ?>>
                <label class="kk-label" for="<?php echo esc_attr($hourlyId); ?>"><?php echo esc_html($copy['label_hourly']); ?></label>
                <div class="kk-control">
                    <input
                        id="<?php echo esc_attr($hourlyId); ?>"
                        class="kk-control__input"
                        type="text"
                        inputmode="decimal"
                        autocomplete="off"
                        data-kk-field="hourly"
                        value="<?php echo esc_attr($atts['default_hourly']); ?>"
                    >
                    <?php if ($copy['suffix_kr'] !== '') : ?>
                        <span class="kk-control__suffix"><?php echo esc_html($copy['suffix_kr']); ?></span>
                    <?php endif; ?>
                </div>
                <label class="kk-label" for="<?php echo esc_attr($hoursId); ?>"><?php echo esc_html($copy['label_hours']); ?></label>
                <div class="kk-control">
                    <input
                        id="<?php echo esc_attr($hoursId); ?>"
                        class="kk-control__input"
                        type="text"
                        inputmode="decimal"
                        autocomplete="off"
                        data-kk-field="hours"
                        value="<?php echo esc_attr($atts['default_hours']); ?>"
                    >
                    <?php if ($copy['suffix_hours'] !== '') : ?>
                        <span class="kk-control__suffix"><?php echo esc_html($copy['suffix_hours']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}
