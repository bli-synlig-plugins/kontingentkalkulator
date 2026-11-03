<?php

if (!defined('ABSPATH')) {
    exit;
}

class KK_Settings
{
    public const OPTION = 'kk_settings';
    public const PAGE = 'kontingentkalkulator';

    public static function init(): void
    {
        add_action('admin_menu', array(__CLASS__, 'register_page'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin'));
    }

    /**
     * @return array{sats: float, fradragssats: float, arsgrense: float, sats_percent: float, fradrag_percent: float}
     */
    public static function get(): array
    {
        $stored = get_option(self::OPTION, array());
        $stored = is_array($stored) ? $stored : array();
        $satsPercent = self::clamp_percent($stored['sats_percent'] ?? 1.9, 1.9);
        $fradragPercent = self::clamp_percent($stored['fradrag_percent'] ?? 22, 22);
        $arsgrense = isset($stored['arsgrense']) && is_numeric($stored['arsgrense'])
            ? max(0, (float) $stored['arsgrense'])
            : 8700.0;

        return array(
            'sats_percent' => $satsPercent,
            'fradrag_percent' => $fradragPercent,
            'arsgrense' => $arsgrense,
            'sats' => $satsPercent / 100,
            'fradragssats' => $fradragPercent / 100,
        );
    }

    /**
     * @return list<array{id: string, name: string, description: string, price: float, enabled: bool}>
     */
    public static function insurances(): array
    {
        $stored = get_option(self::OPTION, array());
        $stored = is_array($stored) ? $stored : array();

        return self::sanitize_insurances($stored['insurances'] ?? array());
    }

    /**
     * @return list<array{id: string, name: string, description: string, price: float, enabled: bool}>
     */
    public static function active_insurances(): array
    {
        $active = array();
        foreach (self::insurances() as $item) {
            if ($item['enabled']) {
                $active[] = $item;
            }
        }

        return $active;
    }

    /**
     * @return list<array{name: string, description: string, price: float}>
     */
    public static function active_insurances_public(): array
    {
        $out = array();
        foreach (self::active_insurances() as $item) {
            $out[] = array(
                'name' => $item['name'],
                'description' => $item['description'],
                'price' => $item['price'],
            );
        }

        return $out;
    }

    public static function insurance_sum(): float
    {
        $sum = 0.0;
        foreach (self::active_insurances() as $item) {
            $sum += $item['price'];
        }

        return $sum;
    }

    public static function insurance_info_text(): string
    {
        $lines = array();
        foreach (self::active_insurances() as $item) {
            $lines[] = self::insurance_line($item['name'], $item['price'], $item['description']);
        }

        return implode("\n", $lines);
    }

    public static function insurance_line(string $name, float $price, string $description = ''): string
    {
        $decimals = abs($price - round($price)) < 0.005 ? 0 : 2;
        $line = $name . ': ' . KK_Calc::format($price, $decimals, false) . ' kr/mnd';
        if ($description !== '') {
            $line .= "\n" . $description;
        }

        return $line;
    }

    /**
     * @return array<string, string>
     */
    public static function strings(): array
    {
        $stored = get_option(self::OPTION, array());
        $stored = is_array($stored) ? $stored : array();
        $saved = isset($stored['strings']) && is_array($stored['strings']) ? $stored['strings'] : array();
        $defaults = self::default_strings();
        $out = array();

        foreach ($defaults as $key => $default) {
            $value = isset($saved[$key]) ? trim((string) $saved[$key]) : '';
            $out[$key] = self::interpolate($value !== '' ? $value : $default);
        }

        return $out;
    }

    public static function register_page(): void
    {
        add_menu_page(
            __('Kontingentkalkulator', 'kontingentkalkulator'),
            __('Kontingentkalkulator', 'kontingentkalkulator'),
            'manage_options',
            self::PAGE,
            array(__CLASS__, 'render_page'),
            'dashicons-calculator',
            58
        );
    }

    public static function register_settings(): void
    {
        register_setting(self::PAGE, self::OPTION, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default' => self::defaults(),
        ));
    }

    /**
     * @return array{sats_percent: float, fradrag_percent: float, arsgrense: float, insurances: array, strings: array<string, string>}
     */
    public static function defaults(): array
    {
        return array(
            'sats_percent' => 1.9,
            'fradrag_percent' => 22.0,
            'arsgrense' => 8700.0,
            'insurances' => array(),
            'strings' => self::default_strings(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function default_strings(): array
    {
        return array(
            'input_title' => 'Din lønn',
            'input_help' => 'Fyll inn lønnen din. Svaret oppdateres mens du skriver.',
            'toggle_heading' => 'Jeg vil oppgi',
            'label_monthly_mode' => 'Månedslønn',
            'label_hourly_mode' => 'Timelønn',
            'label_monthly' => 'Månedslønn',
            'label_hourly' => 'Timelønn',
            'label_hours' => 'Timer per uke',
            'suffix_kr' => 'kr',
            'suffix_hours' => 'timer',
            'result_title' => 'Din reelle kostnad',
            'hero_suffix' => 'kr/mnd',
            'caption' => 'etter skattefradrag',
            'label_salary' => 'Månedslønn',
            'label_dues' => 'Kontingent per måned',
            'label_deduction' => 'Skattefradrag per måned',
            'label_insurance' => 'Forsikringer',
            'label_yearly' => 'Reell kostnad per år',
            'row_suffix' => 'kr',
            'note' => 'Hele årskontingenten gir skattefradrag.',
            'info_button' => 'Mer informasjon',
            'info_dues' => 'Kontingenten er {sats} % av lønnen.',
            'info_deduction' => 'Fagforeningskontingent gir skattefradrag. Fradraget er {fradrag} % av kontingenten, begrenset til {arsgrense} kr per år.',
            'info_yearly' => 'Reell kostnad er kontingenten minus skattefradraget og forsikringer.',
        );
    }

    /**
     * @return array<string, string>
     */
    public static function string_labels(): array
    {
        return array(
            'input_title' => __('Overskrift (input)', 'kontingentkalkulator'),
            'input_help' => __('Hjelpetekst', 'kontingentkalkulator'),
            'toggle_heading' => __('Toggle-etikett', 'kontingentkalkulator'),
            'label_monthly_mode' => __('Valg: månedslønn', 'kontingentkalkulator'),
            'label_hourly_mode' => __('Valg: timelønn', 'kontingentkalkulator'),
            'label_monthly' => __('Felt: månedslønn', 'kontingentkalkulator'),
            'label_hourly' => __('Felt: timelønn', 'kontingentkalkulator'),
            'label_hours' => __('Felt: timer per uke', 'kontingentkalkulator'),
            'suffix_kr' => __('Suffiks beløp', 'kontingentkalkulator'),
            'suffix_hours' => __('Suffiks timer', 'kontingentkalkulator'),
            'result_title' => __('Overskrift (resultat)', 'kontingentkalkulator'),
            'hero_suffix' => __('Suffiks hovedtall', 'kontingentkalkulator'),
            'caption' => __('Undertekst', 'kontingentkalkulator'),
            'label_salary' => __('Rad: månedslønn', 'kontingentkalkulator'),
            'label_dues' => __('Rad: kontingent', 'kontingentkalkulator'),
            'label_deduction' => __('Rad: skattefradrag', 'kontingentkalkulator'),
            'label_insurance' => __('Rad: forsikringer', 'kontingentkalkulator'),
            'label_yearly' => __('Rad: reell per år', 'kontingentkalkulator'),
            'row_suffix' => __('Suffiks rader', 'kontingentkalkulator'),
            'note' => __('Bunntekst', 'kontingentkalkulator'),
            'info_button' => __('(i)-knapp, etikett', 'kontingentkalkulator'),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function info_labels(): array
    {
        return array(
            'info_dues' => __('Kontingent', 'kontingentkalkulator'),
            'info_deduction' => __('Skattefradrag', 'kontingentkalkulator'),
            'info_yearly' => __('Reell kostnad per år', 'kontingentkalkulator'),
        );
    }

    /**
     * @param mixed $value
     * @return array{sats_percent: float, fradrag_percent: float, arsgrense: float, insurances: array, strings: array<string, string>}
     */
    public static function sanitize($value): array
    {
        $value = is_array($value) ? $value : array();
        $posted = isset($value['strings']) && is_array($value['strings']) ? $value['strings'] : array();
        $strings = array();

        $long = self::long_keys();
        foreach (self::default_strings() as $key => $default) {
            $raw = isset($posted[$key]) ? (string) $posted[$key] : '';
            $strings[$key] = isset($long[$key])
                ? sanitize_textarea_field($raw)
                : sanitize_text_field($raw);
        }

        return array(
            'sats_percent' => self::clamp_percent($value['sats_percent'] ?? 1.9, 1.9),
            'fradrag_percent' => self::clamp_percent($value['fradrag_percent'] ?? 22, 22),
            'arsgrense' => max(0, (float) (KK_Calc::number($value['arsgrense'] ?? 8700) ?? 8700)),
            'insurances' => self::sanitize_insurances($value['insurances'] ?? array()),
            'strings' => $strings,
        );
    }

    public static function enqueue_admin(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::PAGE) {
            return;
        }

        wp_enqueue_style(
            'kontingentkalkulator-front',
            KK_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            KK_Plugin::asset_ver('assets/css/frontend.css')
        );
        wp_enqueue_style(
            'kontingentkalkulator-admin',
            KK_PLUGIN_URL . 'assets/css/admin.css',
            array('kontingentkalkulator-front'),
            KK_Plugin::asset_ver('assets/css/admin.css')
        );
        wp_enqueue_script(
            'kontingentkalkulator-admin',
            KK_PLUGIN_URL . 'assets/js/admin.js',
            array(),
            KK_Plugin::asset_ver('assets/js/admin.js'),
            true
        );
        wp_localize_script('kontingentkalkulator-admin', 'KKAdmin', array(
            'defaults' => self::default_strings(),
            'i18n' => array(
                'dirty' => __('Du har ulagrede endringer', 'kontingentkalkulator'),
                'saved' => __('Endringene er lagret', 'kontingentkalkulator'),
                'clean' => __('Ingen endringer', 'kontingentkalkulator'),
                'add' => __('Legg til', 'kontingentkalkulator'),
                'saveItem' => __('Lagre forsikring', 'kontingentkalkulator'),
                'nameError' => __('Skriv inn et navn.', 'kontingentkalkulator'),
                'priceError' => __('Skriv inn en pris.', 'kontingentkalkulator'),
                'show' => __('Vis', 'kontingentkalkulator'),
                'hide' => __('Skjul', 'kontingentkalkulator'),
                'edit' => __('Rediger', 'kontingentkalkulator'),
                'delete' => __('Slett', 'kontingentkalkulator'),
                'deleteConfirm' => __('Slette «%s»?', 'kontingentkalkulator'),
                'empty' => __('Ingen forsikringer ennå. Trykk «Legg til forsikring» for å starte.', 'kontingentkalkulator'),
                'perMonth' => __('kr/mnd', 'kontingentkalkulator'),
            ),
        ));
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get();
        $stored = get_option(self::OPTION, array());
        $savedStrings = is_array($stored) && isset($stored['strings']) && is_array($stored['strings'])
            ? $stored['strings']
            : array();
        $defaults = self::default_strings();
        $labels = self::string_labels();
        $insurances = self::insurances();
        $copy = self::strings();
        $option = self::OPTION;
        $fradragDecimals = abs($settings['fradrag_percent'] - round($settings['fradrag_percent'])) < 0.05 ? 0 : 1;
        ?>
        <div class="wrap kk-admin">
            <h1><?php echo esc_html__('Kontingentkalkulator', 'kontingentkalkulator'); ?></h1>
            <p class="kk-admin-lead"><?php echo esc_html__('Satser, tekster og forsikringer gjelder alle kalkulatorer. Farger og standardverdier settes på elementet i UX Builder. Forhåndsvisningen til høyre oppdateres mens du skriver, og feltet du står i lyses opp.', 'kontingentkalkulator'); ?></p>
            <form action="options.php" method="post" id="kk-admin-form">
                <?php settings_fields(self::PAGE); ?>
                <input
                    type="hidden"
                    name="<?php echo esc_attr($option); ?>[insurances]"
                    id="kk-insurances"
                    value="<?php echo esc_attr((string) wp_json_encode($insurances, JSON_UNESCAPED_UNICODE)); ?>"
                >
                <div class="kk-admin-layout">
                    <nav class="kk-admin-tabs" role="tablist" aria-label="<?php echo esc_attr__('Innstillinger', 'kontingentkalkulator'); ?>">
                        <button type="button" role="tab" id="kk-tab-satser" data-kk-tab="satser" aria-selected="true" aria-controls="kk-panel-satser"><?php echo esc_html__('Satser', 'kontingentkalkulator'); ?></button>
                        <button type="button" role="tab" id="kk-tab-forsikringer" data-kk-tab="forsikringer" aria-selected="false" aria-controls="kk-panel-forsikringer"><?php echo esc_html__('Forsikringer', 'kontingentkalkulator'); ?> <span class="kk-admin-badge" data-kk-ins-count><?php echo esc_html((string) count($insurances)); ?></span></button>
                        <button type="button" role="tab" id="kk-tab-tekster" data-kk-tab="tekster" aria-selected="false" aria-controls="kk-panel-tekster"><?php echo esc_html__('Tekster', 'kontingentkalkulator'); ?></button>
                        <button type="button" role="tab" id="kk-tab-forklaringer" data-kk-tab="forklaringer" aria-selected="false" aria-controls="kk-panel-forklaringer"><?php echo esc_html__('Forklaringer', 'kontingentkalkulator'); ?></button>
                    </nav>
                    <div class="kk-admin-main">
                        <section class="kk-admin-panel" id="kk-panel-satser" role="tabpanel" aria-labelledby="kk-tab-satser">
                            <div class="kk-admin-card">
                                <header>
                                    <h2><?php echo esc_html__('Satser', 'kontingentkalkulator'); ?></h2>
                                    <p><?php echo esc_html__('Brukes i alle beregninger.', 'kontingentkalkulator'); ?></p>
                                </header>
                                <div class="kk-admin-card__body">
                                    <div class="kk-admin-row">
                                        <label for="kk-sats-percent"><?php echo esc_html__('Kontingentsats', 'kontingentkalkulator'); ?><small><?php echo esc_html__('For eksempel 1,9 for 1,9 %.', 'kontingentkalkulator'); ?></small></label>
                                        <div class="kk-admin-suffix">
                                            <input id="kk-sats-percent" name="<?php echo esc_attr($option); ?>[sats_percent]" type="text" value="<?php echo esc_attr(KK_Calc::format($settings['sats_percent'], 2, false)); ?>" data-kk-admin="sats_percent">
                                            <span>%</span>
                                        </div>
                                    </div>
                                    <div class="kk-admin-row">
                                        <label for="kk-fradrag-percent"><?php echo esc_html__('Skattefradragssats', 'kontingentkalkulator'); ?><small><?php echo esc_html__('Vanlig sats er 22. I Finnmark og Nord-Troms 18,5.', 'kontingentkalkulator'); ?></small></label>
                                        <div class="kk-admin-suffix">
                                            <input id="kk-fradrag-percent" name="<?php echo esc_attr($option); ?>[fradrag_percent]" type="text" value="<?php echo esc_attr(KK_Calc::format($settings['fradrag_percent'], $fradragDecimals, false)); ?>" data-kk-admin="fradrag_percent">
                                            <span>%</span>
                                        </div>
                                    </div>
                                    <div class="kk-admin-row">
                                        <label for="kk-arsgrense"><?php echo esc_html__('Maks fradragsgrunnlag per år', 'kontingentkalkulator'); ?></label>
                                        <div class="kk-admin-suffix">
                                            <input id="kk-arsgrense" name="<?php echo esc_attr($option); ?>[arsgrense]" type="text" value="<?php echo esc_attr(KK_Calc::format($settings['arsgrense'], 0, false)); ?>" data-kk-admin="arsgrense">
                                            <span>kr</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="kk-admin-panel" id="kk-panel-forsikringer" role="tabpanel" aria-labelledby="kk-tab-forsikringer" hidden>
                            <div class="kk-admin-tools">
                                <button type="button" class="button button-primary" id="kk-ins-add"><?php echo esc_html__('Legg til forsikring', 'kontingentkalkulator'); ?></button>
                            </div>
                            <div class="kk-admin-card">
                                <header>
                                    <h2><?php echo esc_html__('Forsikringer i regnestykket', 'kontingentkalkulator'); ?></h2>
                                    <p><?php echo esc_html__('Aktive forsikringer vises som én rad i resultatet, med prisene bak (i), og trekkes fra reell månedlig kostnad. De gir ikke skattefradrag.', 'kontingentkalkulator'); ?></p>
                                </header>
                                <div class="kk-ins-form" id="kk-ins-form" hidden>
                                    <div class="kk-ins-form__grid">
                                        <div class="kk-ins-form__full">
                                            <label for="kk-ins-name"><?php echo esc_html__('Navn', 'kontingentkalkulator'); ?></label>
                                            <input id="kk-ins-name" type="text" placeholder="<?php echo esc_attr__('For eksempel Innboforsikring', 'kontingentkalkulator'); ?>">
                                            <p class="kk-admin-error" id="kk-ins-name-error" hidden><?php echo esc_html__('Skriv inn et navn.', 'kontingentkalkulator'); ?></p>
                                        </div>
                                        <div class="kk-ins-form__full">
                                            <label for="kk-ins-description"><?php echo esc_html__('Beskrivelse', 'kontingentkalkulator'); ?></label>
                                            <textarea id="kk-ins-description" rows="3" placeholder="<?php echo esc_attr__('Vises når noen trykker på (i) ved Forsikringer', 'kontingentkalkulator'); ?>"></textarea>
                                        </div>
                                        <div>
                                            <label for="kk-ins-price"><?php echo esc_html__('Pris per måned', 'kontingentkalkulator'); ?></label>
                                            <div class="kk-admin-suffix">
                                                <input id="kk-ins-price" type="number" min="0" step="1" placeholder="0">
                                                <span>kr</span>
                                            </div>
                                            <p class="kk-admin-error" id="kk-ins-price-error" hidden><?php echo esc_html__('Skriv inn en pris.', 'kontingentkalkulator'); ?></p>
                                        </div>
                                        <div class="kk-ins-form__actions">
                                            <button type="button" class="button button-primary" id="kk-ins-save"><?php echo esc_html__('Legg til', 'kontingentkalkulator'); ?></button>
                                            <button type="button" class="button" id="kk-ins-cancel"><?php echo esc_html__('Avbryt', 'kontingentkalkulator'); ?></button>
                                        </div>
                                    </div>
                                </div>
                                <div id="kk-ins-list"></div>
                                <div class="kk-ins-sum">
                                    <span><?php echo esc_html__('Sum aktive forsikringer', 'kontingentkalkulator'); ?></span>
                                    <span data-kk-ins-total></span>
                                </div>
                            </div>
                        </section>

                        <section class="kk-admin-panel" id="kk-panel-tekster" role="tabpanel" aria-labelledby="kk-tab-tekster" hidden>
                            <p class="kk-admin-hint"><?php echo esc_html__('La et felt stå tomt for å bruke standardteksten (vist som grå tekst). Gruppene følger oppsettet på nettsiden.', 'kontingentkalkulator'); ?></p>
                            <div class="kk-admin-card">
                                <header>
                                    <h2><?php echo esc_html__('Venstre side: Din lønn', 'kontingentkalkulator'); ?></h2>
                                </header>
                                <div class="kk-admin-card__body">
                                    <?php self::render_string_fields(array(
                                        'input_title', 'input_help', 'toggle_heading', 'label_monthly_mode', 'label_hourly_mode',
                                        'label_monthly', 'label_hourly', 'label_hours', 'suffix_kr', 'suffix_hours',
                                    ), $labels, $savedStrings, $defaults); ?>
                                </div>
                            </div>
                            <div class="kk-admin-card">
                                <header>
                                    <h2><?php echo esc_html__('Høyre side: Resultat', 'kontingentkalkulator'); ?></h2>
                                </header>
                                <div class="kk-admin-card__body">
                                    <?php self::render_string_fields(array(
                                        'result_title', 'hero_suffix', 'caption', 'label_salary', 'label_dues', 'label_deduction',
                                        'label_insurance', 'label_yearly', 'row_suffix', 'note', 'info_button',
                                    ), $labels, $savedStrings, $defaults); ?>
                                </div>
                            </div>
                        </section>

                        <section class="kk-admin-panel" id="kk-panel-forklaringer" role="tabpanel" aria-labelledby="kk-tab-forklaringer" hidden>
                            <div class="kk-admin-card">
                                <header>
                                    <h2><?php echo esc_html__('Forklaringer', 'kontingentkalkulator'); ?></h2>
                                    <p><?php echo esc_html__('Vises under raden når noen trykker på (i). La feltet stå tomt for å skjule (i)-knappen. {sats}, {fradrag} og {arsgrense} erstattes med verdiene under Satser.', 'kontingentkalkulator'); ?></p>
                                </header>
                                <div class="kk-admin-card__body">
                                    <?php self::render_string_fields(array_keys(self::info_labels()), self::info_labels(), $savedStrings, $defaults, true); ?>
                                </div>
                            </div>
                        </section>
                    </div>

                    <aside class="kk-admin-preview" aria-label="<?php echo esc_attr__('Forhåndsvisning', 'kontingentkalkulator'); ?>">
                        <h3><?php echo esc_html__('Forhåndsvisning (slik det ser ut på nettsiden)', 'kontingentkalkulator'); ?></h3>
                        <div class="kk-calculator kk-admin-calc" id="kk-preview">
                            <div class="kk-input" data-kk-preview-input>
                                <h3 class="kk-input__title" data-kk-hl="input_title"><?php echo esc_html($copy['input_title']); ?></h3>
                                <p class="kk-input__help" data-kk-hl="input_help"><?php echo esc_html($copy['input_help']); ?></p>
                                <p class="kk-label" data-kk-hl="toggle_heading"><?php echo esc_html($copy['toggle_heading']); ?></p>
                                <div class="kk-toggle" role="group">
                                    <button type="button" class="kk-toggle__btn" data-kk-preview-mode="monthly" aria-pressed="true" data-kk-hl="label_monthly_mode"><?php echo esc_html($copy['label_monthly_mode']); ?></button>
                                    <button type="button" class="kk-toggle__btn" data-kk-preview-mode="hourly" aria-pressed="false" data-kk-hl="label_hourly_mode"><?php echo esc_html($copy['label_hourly_mode']); ?></button>
                                </div>
                                <div data-kk-preview-fields="monthly">
                                    <label class="kk-label" data-kk-hl="label_monthly"><?php echo esc_html($copy['label_monthly']); ?></label>
                                    <div class="kk-control">
                                        <input class="kk-control__input" id="kk-preview-monthly" type="text" inputmode="decimal" value="29000" autocomplete="off">
                                        <span class="kk-control__suffix" data-kk-hl="suffix_kr"><?php echo esc_html($copy['suffix_kr']); ?></span>
                                    </div>
                                </div>
                                <div data-kk-preview-fields="hourly" hidden>
                                    <label class="kk-label" data-kk-hl="label_hourly"><?php echo esc_html($copy['label_hourly']); ?></label>
                                    <div class="kk-control">
                                        <input class="kk-control__input" id="kk-preview-hourly" type="text" inputmode="decimal" value="180" autocomplete="off">
                                        <span class="kk-control__suffix" data-kk-hl="suffix_kr"><?php echo esc_html($copy['suffix_kr']); ?></span>
                                    </div>
                                    <label class="kk-label" data-kk-hl="label_hours"><?php echo esc_html($copy['label_hours']); ?></label>
                                    <div class="kk-control">
                                        <input class="kk-control__input" id="kk-preview-hours" type="text" inputmode="decimal" value="37,5" autocomplete="off">
                                        <span class="kk-control__suffix" data-kk-hl="suffix_hours"><?php echo esc_html($copy['suffix_hours']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="kk-result" data-kk-preview-result>
                                <h3 class="kk-result__title" data-kk-hl="result_title"><?php echo esc_html($copy['result_title']); ?></h3>
                                <p class="kk-hero">
                                    <span class="kk-hero__value" data-kk-preview-out="reell">–</span>
                                    <span class="kk-hero__suffix" data-kk-hl="hero_suffix"><?php echo esc_html($copy['hero_suffix']); ?></span>
                                </p>
                                <p class="kk-result__caption" data-kk-hl="caption"><?php echo esc_html($copy['caption']); ?></p>
                                <dl class="kk-rows">
                                    <div class="kk-row" data-kk-preview-row="salary">
                                        <dt><span class="kk-row__label" data-kk-hl="label_salary"><?php echo esc_html($copy['label_salary']); ?></span></dt>
                                        <dd><span data-kk-preview-out="salary">–</span> <span data-kk-hl="row_suffix"><?php echo esc_html($copy['row_suffix']); ?></span></dd>
                                    </div>
                                    <div class="kk-row" data-kk-preview-row="dues">
                                        <dt>
                                            <span class="kk-row__label" data-kk-hl="label_dues"><?php echo esc_html($copy['label_dues']); ?></span>
                                            <button type="button" class="kk-info__btn" data-kk-preview-info="info_dues" data-kk-hl="info_button" aria-expanded="false">i</button>
                                        </dt>
                                        <dd><span data-kk-preview-out="dues">–</span> <span data-kk-hl="row_suffix"><?php echo esc_html($copy['row_suffix']); ?></span></dd>
                                        <p class="kk-info__text" data-kk-preview-info-text="info_dues" data-kk-hl="info_dues" hidden></p>
                                    </div>
                                    <div class="kk-row" data-kk-preview-row="deduction">
                                        <dt>
                                            <span class="kk-row__label" data-kk-hl="label_deduction"><?php echo esc_html($copy['label_deduction']); ?></span>
                                            <button type="button" class="kk-info__btn" data-kk-preview-info="info_deduction" data-kk-hl="info_button" aria-expanded="false">i</button>
                                        </dt>
                                        <dd><span data-kk-preview-out="deduction">–</span> <span data-kk-hl="row_suffix"><?php echo esc_html($copy['row_suffix']); ?></span></dd>
                                        <p class="kk-info__text" data-kk-preview-info-text="info_deduction" data-kk-hl="info_deduction" hidden></p>
                                    </div>
                                    <div class="kk-row" data-kk-preview-row="insurance" hidden>
                                        <dt>
                                            <span class="kk-row__label" data-kk-hl="label_insurance"><?php echo esc_html($copy['label_insurance']); ?></span>
                                            <button type="button" class="kk-info__btn" data-kk-preview-info="insurance" data-kk-hl="info_button" aria-expanded="false">i</button>
                                        </dt>
                                        <dd><span data-kk-preview-out="insurance">–</span> <span data-kk-hl="row_suffix"><?php echo esc_html($copy['row_suffix']); ?></span></dd>
                                        <p class="kk-info__text" data-kk-preview-info-text="insurance" hidden></p>
                                    </div>
                                    <div class="kk-row kk-row--total" data-kk-preview-row="yearly">
                                        <dt>
                                            <span class="kk-row__label" data-kk-hl="label_yearly"><?php echo esc_html($copy['label_yearly']); ?></span>
                                            <button type="button" class="kk-info__btn" data-kk-preview-info="info_yearly" data-kk-hl="info_button" aria-expanded="false">i</button>
                                        </dt>
                                        <dd><span data-kk-preview-out="yearly">–</span> <span data-kk-hl="row_suffix"><?php echo esc_html($copy['row_suffix']); ?></span></dd>
                                        <p class="kk-info__text" data-kk-preview-info-text="info_yearly" data-kk-hl="info_yearly" hidden></p>
                                    </div>
                                </dl>
                                <p class="kk-result__note" data-kk-hl="note"><?php echo esc_html($copy['note']); ?></p>
                            </div>
                        </div>
                        <p class="kk-admin-preview__note"><?php echo esc_html__('Eksemplet regner med verdiene du har satt til venstre. Prøv å endre lønn i forhåndsvisningen.', 'kontingentkalkulator'); ?></p>
                    </aside>
                </div>
                <div class="kk-admin-bar">
                    <div class="kk-admin-bar__inner">
                        <span id="kk-admin-status"><?php echo esc_html__('Ingen endringer', 'kontingentkalkulator'); ?></span>
                        <?php submit_button(__('Lagre endringene', 'kontingentkalkulator'), 'primary', 'submit', false); ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * @param list<string> $keys
     * @param array<string, string> $labels
     * @param array<string, string> $saved
     * @param array<string, string> $defaults
     */
    private static function render_string_fields(array $keys, array $labels, array $saved, array $defaults, bool $chips = false): void
    {
        $long = self::long_keys();
        $option = self::OPTION;
        foreach ($keys as $key) {
            $label = $labels[$key] ?? $key;
            $value = isset($saved[$key]) ? (string) $saved[$key] : '';
            $placeholder = $defaults[$key] ?? '';
            $fieldId = 'kk-string-' . $key;
            ?>
            <div class="kk-admin-row">
                <label for="<?php echo esc_attr($fieldId); ?>"><?php echo esc_html($label); ?></label>
                <div>
                    <?php if (isset($long[$key]) || $chips) : ?>
                        <textarea
                            id="<?php echo esc_attr($fieldId); ?>"
                            name="<?php echo esc_attr($option); ?>[strings][<?php echo esc_attr($key); ?>]"
                            rows="3"
                            placeholder="<?php echo esc_attr($placeholder); ?>"
                            data-kk-admin="<?php echo esc_attr($key); ?>"
                        ><?php echo esc_textarea($value); ?></textarea>
                    <?php else : ?>
                        <input
                            id="<?php echo esc_attr($fieldId); ?>"
                            name="<?php echo esc_attr($option); ?>[strings][<?php echo esc_attr($key); ?>]"
                            type="text"
                            value="<?php echo esc_attr($value); ?>"
                            placeholder="<?php echo esc_attr($placeholder); ?>"
                            data-kk-admin="<?php echo esc_attr($key); ?>"
                        >
                    <?php endif; ?>
                    <?php if ($chips) : ?>
                        <div class="kk-admin-chips">
                            <?php foreach (array('{sats}', '{fradrag}', '{arsgrense}') as $token) : ?>
                                <button type="button" class="kk-admin-chip" data-kk-chip="<?php echo esc_attr($token); ?>" data-kk-for="<?php echo esc_attr($fieldId); ?>"><?php echo esc_html($token); ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * @param mixed $raw
     * @return list<array{id: string, name: string, description: string, price: float, enabled: bool}>
     */
    private static function sanitize_insurances($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode(wp_unslash($raw), true);
            $raw = is_array($decoded) ? $decoded : array();
        }
        if (!is_array($raw)) {
            return array();
        }

        $out = array();
        $seen = array();
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = sanitize_text_field((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $id = sanitize_key((string) ($item['id'] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                $id = 'ins_' . wp_generate_password(8, false, false);
            }
            $seen[$id] = true;
            $enabled = $item['enabled'] ?? true;
            $out[] = array(
                'id' => $id,
                'name' => $name,
                'description' => sanitize_textarea_field((string) ($item['description'] ?? '')),
                'price' => max(0, (float) (KK_Calc::number($item['price'] ?? 0) ?? 0)),
                'enabled' => $enabled === true || $enabled === 1 || $enabled === '1' || $enabled === 'true',
            );
        }

        return $out;
    }

    /**
     * @return array<string, true>
     */
    private static function long_keys(): array
    {
        return array(
            'input_help' => true,
            'note' => true,
            'info_dues' => true,
            'info_deduction' => true,
            'info_yearly' => true,
        );
    }

    private static function interpolate(string $text): string
    {
        if ($text === '' || !str_contains($text, '{')) {
            return $text;
        }

        $settings = self::get();

        return strtr($text, array(
            '{sats}' => KK_Calc::format_percent($settings['sats_percent']),
            '{fradrag}' => KK_Calc::format_percent($settings['fradrag_percent']),
            '{arsgrense}' => KK_Calc::format($settings['arsgrense'], 0, false),
        ));
    }

    private static function clamp_percent(mixed $value, float $fallback): float
    {
        $number = KK_Calc::number($value);
        if ($number === null) {
            return $fallback;
        }

        return max(0, min(100, $number));
    }
}
