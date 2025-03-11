<?php
/**
 * Plugin Name: Clariloop Survey for WooCommerce
 * Plugin URI: https://github.com/safiyo-ai/clariloop-wc-survey
 * Description: Displays customer satisfaction surveys after WooCommerce checkout using Clariloop Survey SDK
 * Version: 0.1.0
 * Author: Richie Nabuk
 * Author URI: https://github.com/richienabuk
 * Text Domain: clariloop-wc-survey
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.2
 * Tested up to: 6.4
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('CLARILOOP_SURVEY_VERSION', '0.1.0');
define('CLARILOOP_SURVEY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLARILOOP_SURVEY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLARILOOP_SURVEY_SDK_URL', 'https://cdn.jsdelivr.net/npm/@clariloop/survey/dist/survey.min.js');

// Main plugin class
class Clariloop_Survey {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));

        // Add settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));

        // Add survey script after successful checkout
        add_action('woocommerce_thankyou', array($this, 'display_survey'), 10, 1);
    }

    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load text domain for translations
        load_plugin_textdomain('clariloop-wc-survey', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('Clariloop Survey requires WooCommerce to be installed and active.', 'clariloop-wc-survey'); ?></p>
        </div>
        <?php
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=clariloop-survey-settings">' . __('Settings', 'clariloop-wc-survey') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Clariloop Survey Settings', 'clariloop-wc-survey'),
            __('Clariloop Survey', 'clariloop-wc-survey'),
            'manage_options',
            'clariloop-survey-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        // Register settings group
        register_setting('clariloop_survey_settings', 'clariloop_survey_options', array(
            'type' => 'array',
            'default' => array(
                'api_key' => '',
                'enabled' => false,
                'display_mode' => 'inline'
            )
        ));

        add_settings_section(
            'clariloop_survey_main_section',
            __('Survey Settings', 'clariloop-woocommerce'),
            null,
            'clariloop-survey-settings'
        );

        add_settings_field(
            'clariloop_survey_api_key',
            __('API Key', 'clariloop-woocommerce'),
            array($this, 'render_api_key_field'),
            'clariloop-survey-settings',
            'clariloop_survey_main_section'
        );

        add_settings_field(
            'clariloop_survey_enabled',
            __('Enable Survey', 'clariloop-woocommerce'),
            array($this, 'render_enabled_field'),
            'clariloop-survey-settings',
            'clariloop_survey_main_section'
        );

        add_settings_field(
            'clariloop_survey_display_mode',
            __('Display Mode', 'clariloop-woocommerce'),
            array($this, 'render_display_mode_field'),
            'clariloop-survey-settings',
            'clariloop_survey_main_section'
        );
    }

    public function render_api_key_field() {
        $options = get_option('clariloop_survey_options', array());
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        ?>
        <input type="text" 
               name="clariloop_survey_options[api_key]" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text"
               required>
        <p class="description">
            <?php _e('Enter your Clariloop API key', 'clariloop-woocommerce'); ?>
        </p>
        <?php
    }

    public function render_enabled_field() {
        $options = get_option('clariloop_survey_options', array());
        $enabled = isset($options['enabled']) ? $options['enabled'] : false;
        ?>
        <label>
            <input type="checkbox" 
                   name="clariloop_survey_options[enabled]" 
                   value="1" 
                   <?php checked($enabled, true); ?>>
            <?php _e('Display survey after successful checkout', 'clariloop-woocommerce'); ?>
        </label>
        <?php
    }

    public function render_display_mode_field() {
        $options = get_option('clariloop_survey_options', array());
        $display_mode = isset($options['display_mode']) ? $options['display_mode'] : 'inline';
        ?>
        <select name="clariloop_survey_options[display_mode]" class="regular-text">
            <option value="inline" <?php selected($display_mode, 'inline'); ?>>
                <?php _e('Inline', 'clariloop-woocommerce'); ?>
            </option>
            <option value="popup" <?php selected($display_mode, 'popup'); ?>>
                <?php _e('Popup', 'clariloop-woocommerce'); ?>
            </option>
            <option value="overlay" <?php selected($display_mode, 'overlay'); ?>>
                <?php _e('Overlay', 'clariloop-woocommerce'); ?>
            </option>
        </select>
        <p class="description">
            <?php _e('Choose how the survey should be displayed', 'clariloop-woocommerce'); ?>
        </p>
        <?php
    }

    public function display_survey($order_id) {
        $options = get_option('clariloop_survey_options', array());
        
        // Check if survey is enabled
        if (empty($options['enabled'])) {
            return;
        }

        if (empty($options['api_key'])) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get customer details
        $customer_email = $order->get_billing_email();
        $customer_id = $order->get_customer_id() ?: 'guest-' . $order_id;

        // Add survey container
        echo '<div class="clariloop-survey-container" id="clariloop-survey-container"></div>';

        // Enqueue Clariloop SDK
        wp_enqueue_script(
            'clariloop-survey-sdk',
            CLARILOOP_SURVEY_SDK_URL,
            array(),
            CLARILOOP_SURVEY_VERSION,
            true
        );

        // Enqueue our custom survey initialization script
        wp_enqueue_script(
            'clariloop-survey-init',
            CLARILOOP_SURVEY_PLUGIN_URL . 'assets/js/clariloop-survey.js',
            array('clariloop-survey-sdk'),
            CLARILOOP_SURVEY_VERSION,
            true
        );

        // Pass configuration to JavaScript
        wp_localize_script('clariloop-survey-init', 'clariloopSurveyConfig', array(
            'apiKey' => $options['api_key'],
            'customerId' => $customer_id,
            'orderId' => $order_id,
            'email' => $customer_email,
            'displayMode' => $options['display_mode'] ?? 'inline'
        ));
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('clariloop_survey_settings');
                do_settings_sections('clariloop-survey-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

// Initialize the plugin
Clariloop_Survey::get_instance();
