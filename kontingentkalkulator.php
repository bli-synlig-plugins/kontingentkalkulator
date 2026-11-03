<?php
/**
 * Plugin Name:         Kontingentkalkulator
 * Description:         Flatsome UX Builder-element som regner ut reell kontingent ut fra månedslønn eller timelønn.
 * Version:             1.0.0
 * Requires at least:   6.6
 * Requires PHP:        8.1
 * Author:              Benjamin
 * License:             GPLv2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         kontingentkalkulator
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KK_VERSION', '1.0.0');
define('KK_PLUGIN_FILE', __FILE__);
define('KK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KK_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once KK_PLUGIN_DIR . 'includes/class-kk-calc.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-settings.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-element.php';
require_once KK_PLUGIN_DIR . 'includes/class-kk-plugin.php';
require_once KK_PLUGIN_DIR . 'includes/elements/class-kk-calculator.php';
require_once KK_PLUGIN_DIR . 'includes/elements/class-kk-input.php';
require_once KK_PLUGIN_DIR . 'includes/elements/class-kk-result.php';

add_action('plugins_loaded', array('KK_Plugin', 'init'));
