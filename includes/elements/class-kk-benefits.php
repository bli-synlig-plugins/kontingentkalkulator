<?php

if (!defined('ABSPATH')) {
    exit;
}

class KK_Benefits extends KK_Element
{
    protected string $tag = 'kk_benefits';

    protected function metadata(): array
    {
        return array(
            'name' => __('Fordelskalkulator', 'kontingentkalkulator'),
            'category' => __('Kontingentkalkulator', 'kontingentkalkulator'),
            'wrap' => false,
            'info' => '{{ class }}',
            'presets' => array(
                array(
                    'name' => __('Standard', 'kontingentkalkulator'),
                    'content' => '[kk_benefits][/kk_benefits]',
                ),
            ),
        );
    }

    protected function options(): array
    {
        return array(
            'style_options' => array(
                'type' => 'group',
                'heading' => __('Utseende', 'kontingentkalkulator'),
                'options' => array(
                    'header_background' => $this->colorpicker(__('Header bakgrunn', 'kontingentkalkulator')),
                    'header_color' => $this->colorpicker(__('Header tekst', 'kontingentkalkulator')),
                    'background' => $this->colorpicker(__('Bakgrunnsfarge', 'kontingentkalkulator')),
                    'switch_color' => $this->colorpicker(__('Bryterfarge', 'kontingentkalkulator')),
                    'title_tag' => $this->heading_tag(),
                    'class' => array(
                        'type' => 'textfield',
                        'heading' => __('Ekstra CSS-klasse', 'kontingentkalkulator'),
                        'default' => '',
                    ),
                    'padding_y' => $this->padding_slider(__('Padding topp/bunn', 'kontingentkalkulator'), '28'),
                    'padding_x' => $this->padding_slider(__('Padding venstre/høyre', 'kontingentkalkulator'), '28'),
                ),
            ),
        );
    }

    public function render($atts, $content = ''): string
    {
        $atts = $this->parse_atts($atts);
        $copy = KK_Settings::strings();
        $categories = KK_Settings::active_benefit_categories();
        $uid = $this->uid();
        $class = trim('kk-benefits ' . $atts['class']);
        $currency = $copy['benefits_currency'] !== '' ? $copy['benefits_currency'] : 'kr';
        $style = $this->css_vars(array(
            '--kk-ben-header-bg' => $atts['header_background'],
            '--kk-ben-header-color' => $atts['header_color'],
            '--kk-ben-bg' => $atts['background'],
            '--kk-ben-switch' => $atts['switch_color'],
        ));
        $total = $this->initial_total($categories);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($uid); ?>" class="<?php echo esc_attr($class); ?>" data-kk-benefits<?php echo $style; ?>>
            <header class="kk-benefits__header">
                <?php echo $this->title_html($atts['title_tag'], 'kk-benefits__kicker', $copy['benefits_title']); ?>
                <p class="kk-benefits__total">
                    <span class="kk-benefits__currency"><?php echo esc_html($currency); ?></span>
                    <span data-kk-ben-total><?php echo esc_html(KK_Calc::format($total, 0, false)); ?></span>
                </p>
            </header>
            <div class="kk-benefits__body">
                <?php if ($categories === []) : ?>
                    <p class="kk-benefits__empty"><?php echo esc_html($this->empty_message()); ?></p>
                <?php else : ?>
                    <?php foreach ($categories as $category) : ?>
                        <?php $this->render_category($category, $currency, $copy['info_button']); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php echo $this->padding_style($uid, $atts); ?>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param list<array{id: string, name: string, description: string, link_text: string, link_url: string, items: list<array{id: string, name: string, description: string, amount: float, default_on: bool, exclusive_group: string}>}> $categories
     */
    private function initial_total(array $categories): float
    {
        $total = 0.0;
        foreach ($categories as $category) {
            $total += $this->category_sum($category['items']);
        }

        return $total;
    }

    /**
     * @param list<array{amount: float, default_on: bool}> $items
     */
    private function category_sum(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            if (!empty($item['default_on'])) {
                $sum += (float) $item['amount'];
            }
        }

        return $sum;
    }

    /**
     * @param array{id: string, name: string, description: string, link_text: string, link_url: string, items: list<array{id: string, name: string, description: string, amount: float, default_on: bool, exclusive_group: string}>} $category
     */
    private function render_category(array $category, string $currency, string $info_label): void
    {
        $sum = $this->category_sum($category['items']);
        $linkUrl = (string) ($category['link_url'] ?? '');
        $linkText = (string) ($category['link_text'] ?? '');
        ?>
        <section class="kk-benefits__cat" data-kk-ben-cat="<?php echo esc_attr($category['id']); ?>">
            <div class="kk-benefits__cat-head">
                <p class="kk-benefits__cat-title"><?php echo esc_html($category['name']); ?></p>
                <span class="kk-benefits__cat-sum" data-kk-ben-cat-sum>
                    <?php echo esc_html($this->money($sum, $currency)); ?>
                </span>
            </div>
            <?php if ($category['description'] !== '' || $linkUrl !== '') : ?>
                <div class="kk-benefits__cat-copy">
                    <?php echo $this->paragraphs($category['description']); ?>
                    <?php if ($linkUrl !== '') : ?>
                        <p class="kk-benefits__link">
                            <a href="<?php echo esc_url($linkUrl); ?>"><?php echo esc_html($linkText !== '' ? $linkText : $linkUrl); ?><?php echo $this->chevron_icon(); ?></a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="kk-benefits__rows">
                <?php foreach ($category['items'] as $item) : ?>
                    <?php $this->render_item($item, $currency, $info_label); ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    /**
     * @param array{id: string, name: string, description: string, amount: float, default_on: bool, exclusive_group: string} $item
     */
    private function render_item(array $item, string $currency, string $info_label): void
    {
        $on = !empty($item['default_on']);
        $info = (string) ($item['description'] ?? '');
        $infoId = $info !== '' ? $this->uid() . '-info' : '';
        $aria = $info_label !== '' ? $info_label : __('Mer informasjon', 'kontingentkalkulator');
        $aria .= ' — ' . $item['name'];
        $group = trim((string) ($item['exclusive_group'] ?? ''));
        $amount = (float) $item['amount'];
        ?>
        <div
            class="kk-ben-row"
            data-kk-ben-row
            data-kk-ben-id="<?php echo esc_attr($item['id']); ?>"
            data-kk-amount="<?php echo esc_attr((string) $amount); ?>"
            data-kk-group="<?php echo esc_attr($group); ?>"
        >
            <div class="kk-ben-row__main">
                <div class="kk-ben-row__label">
                    <span class="kk-ben-row__name"><?php echo esc_html($item['name']); ?></span>
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
                </div>
                <span class="kk-ben-row__amount" data-kk-ben-amount<?php echo $on ? '' : ' hidden'; ?>>
                    <?php echo esc_html($this->money($amount, $currency)); ?>
                </span>
                <button
                    type="button"
                    class="kk-switch"
                    role="switch"
                    aria-checked="<?php echo $on ? 'true' : 'false'; ?>"
                    data-kk-ben-switch
                    aria-label="<?php echo esc_attr($item['name']); ?>"
                ></button>
            </div>
            <?php if ($infoId !== '') : ?>
                <p id="<?php echo esc_attr($infoId); ?>" class="kk-info__text" hidden><?php echo nl2br(esc_html($info), false); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function money(float $amount, string $currency): string
    {
        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;

        return trim($currency . ' ' . KK_Calc::format($amount, $decimals, false));
    }

    private function paragraphs(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $parts = preg_split("/\n\s*\n/", $text);
        if (!is_array($parts) || $parts === []) {
            $parts = array($text);
        }

        $html = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $html .= '<p>' . nl2br(esc_html($part), false) . '</p>';
        }

        return $html;
    }

    private function empty_message(): string
    {
        if ($this->is_builder_preview()) {
            return __('Legg til kategorier og fordeler under Kontingentkalkulator i wp-admin.', 'kontingentkalkulator');
        }

        return __('Ingen fordeler er satt opp ennå.', 'kontingentkalkulator');
    }

    private function is_builder_preview(): bool
    {
        if (defined('UX_BUILDER_IFRAME') && UX_BUILDER_IFRAME) {
            return true;
        }

        return function_exists('ux_builder_is_iframe') && ux_builder_is_iframe();
    }
}
