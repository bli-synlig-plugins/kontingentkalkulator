<?php

if (!defined('ABSPATH')) {
    exit;
}

class KK_Result extends KK_Element
{
    protected string $tag = 'kk_result';

    protected function metadata(): array
    {
        return array(
            'name' => __('Kalkulator: resultat', 'kontingentkalkulator'),
            'category' => __('Kontingentkalkulator', 'kontingentkalkulator'),
            'require' => array('kk_calculator'),
            'wrap' => false,
            'info' => '{{ background }}',
        );
    }

    protected function options(): array
    {
        return array(
            'style_options' => array(
                'type' => 'group',
                'heading' => __('Utseende', 'kontingentkalkulator'),
                'options' => array(
                    'background' => $this->colorpicker(__('Bakgrunnsfarge', 'kontingentkalkulator')),
                    'title_tag' => $this->heading_tag(),
                    'class' => array(
                        'type' => 'textfield',
                        'heading' => __('Ekstra CSS-klasse', 'kontingentkalkulator'),
                        'default' => '',
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
        $class = trim('kk-result ' . $atts['class']);
        $style = $this->css_vars(array(
            '--kk-bg' => $atts['background'],
        ));
        $values = $this->initial_values();
        $suffix = $copy['row_suffix'];

        ob_start();
        ?>
        <div id="<?php echo esc_attr($uid); ?>" class="<?php echo esc_attr($class); ?>" data-kk-result<?php echo $style; ?>>
            <?php echo $this->title_html($atts['title_tag'], 'kk-result__title', $copy['result_title']); ?>
            <p class="kk-hero">
                <span class="kk-hero__value" data-kk-out="reell"><?php echo esc_html($values['reell']); ?></span>
                <?php if ($copy['hero_suffix'] !== '') : ?>
                    <span class="kk-hero__suffix"><?php echo esc_html($copy['hero_suffix']); ?></span>
                <?php endif; ?>
            </p>
            <?php if ($copy['caption'] !== '') : ?>
                <p class="kk-result__caption"><?php echo esc_html($copy['caption']); ?></p>
            <?php endif; ?>
            <dl class="kk-rows">
                <?php $this->row('salary', $copy['label_salary'], $values['salary'], $suffix); ?>
                <?php $this->row('dues', $copy['label_dues'], $values['dues'], $suffix, 'kk-row', $copy['info_dues'], $copy['info_button']); ?>
                <?php $this->row('deduction', $copy['label_deduction'], $values['deduction'], $suffix, 'kk-row', $copy['info_deduction'], $copy['info_button']); ?>
                <?php
                $insuranceInfo = KK_Settings::insurance_info_text();
                if ($insuranceInfo !== '') {
                    $this->row(
                        'insurance',
                        $copy['label_insurance'],
                        $values['insurance'],
                        $suffix,
                        'kk-row',
                        $insuranceInfo,
                        $copy['info_button']
                    );
                }
                ?>
                <?php $this->row('yearly', $copy['label_yearly'], $values['yearly'], $suffix, 'kk-row kk-row--total', $copy['info_yearly'], $copy['info_button']); ?>
            </dl>
            <?php if ($copy['note'] !== '') : ?>
                <p class="kk-result__note"><?php echo esc_html($copy['note']); ?></p>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @return array{salary: string, dues: string, deduction: string, insurance: string, reell: string, yearly: string}
     */
    private function initial_values(): array
    {
        $empty = array(
            'salary' => '–',
            'dues' => '–',
            'deduction' => '–',
            'insurance' => '–',
            'reell' => '–',
            'yearly' => '–',
        );

        $result = KK_Calc::compute(array_merge(KK_Plugin::rates(), array(
            'mode' => 'monthly',
            'monthly' => 0,
            'insurance' => KK_Settings::insurance_sum(),
        )));

        if ($result === null) {
            return $empty;
        }

        return array(
            'salary' => KK_Calc::format($result['salary'], 0),
            'dues' => KK_Calc::format($result['dues'], 2),
            'deduction' => KK_Calc::format_signed($result['deduction'], 2),
            'insurance' => KK_Calc::format_signed($result['insurance'], 2),
            'reell' => KK_Calc::format(round($result['reell']), 0),
            'yearly' => KK_Calc::format(round($result['yearly']), 0),
        );
    }

    private function row(
        string $key,
        string $label,
        string $value,
        string $suffix,
        string $class = 'kk-row',
        string $info = '',
        string $info_label = ''
    ): void {
        if ($label === '') {
            return;
        }

        $infoId = $info !== '' ? $this->uid() . '-info' : '';
        $aria = $info_label !== '' ? $info_label : __('Mer informasjon', 'kontingentkalkulator');
        $aria .= ' — ' . $label;
        ?>
        <div class="<?php echo esc_attr($class); ?>" data-kk-row="<?php echo esc_attr($key); ?>">
            <dt>
                <span class="kk-row__label"><?php echo esc_html($label); ?></span>
                <?php if ($infoId !== '') : ?>
                    <button
                        type="button"
                        class="kk-info__btn"
                        data-kk-info
                        aria-expanded="false"
                        aria-controls="<?php echo esc_attr($infoId); ?>"
                        aria-label="<?php echo esc_attr($aria); ?>"
                    ><?php echo $this->info_icon(); ?></button>
                <?php endif; ?>
            </dt>
            <dd>
                <span data-kk-out="<?php echo esc_attr($key); ?>"><?php echo esc_html($value); ?></span><?php
                echo $suffix !== '' ? ' ' . esc_html($suffix) : '';
                ?>
            </dd>
            <?php if ($infoId !== '') : ?>
                <p id="<?php echo esc_attr($infoId); ?>" class="kk-info__text" hidden><?php echo nl2br(esc_html($info), false); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
