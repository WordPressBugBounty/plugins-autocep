<?php
/**
 * Plugin Name: AutoCEP
 * Plugin URI: https://tesw.com.br/plugins/
 * Description: Autocompletar endereço por CEP e simulador de frete completo para WooCommerce — produto, carrinho e checkout — com múltiplas APIs, cache, fallback automático, previsão de entrega com data estimada, barra de progresso de frete grátis, cache de cotações, retirada em loja física por proximidade via Google Maps e testes de conexão em tempo real com as APIs do Melhor Envio e da Frenet, compatível com Fluid Checkout, CartFlows e mais.
 * Version: 2.46
 * Author: TESW | Dev Wanderson Cesar
 * Author URI: https://tesw.com.br
 * Text Domain: autocep
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 9.0
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AUTOCEP_VERSION', '2.46');
define('AUTOCEP_PLUGIN_FILE', __FILE__);
define('AUTOCEP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUTOCEP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUTOCEP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AUTOCEP_OPTION_KEY', 'autocep_options');
define('AUTOCEP_NONCE_ACTION', 'autocep_nonce');
define('AUTOCEP_ADMIN_NONCE_ACTION', 'autocep_admin_nonce');

final class AutoCEP_Plugin
{
    private static $instance = null;

    public $admin;

    public $api;

    public $shipping;

    public $logger;

    public $stats;

    public $store_pickup;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->includes();
        $this->init_modules();
        $this->register_hooks();
    }

    private function includes()
    {
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-logger.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-stats.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-tickets.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-admin.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-api.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-shipping.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-maps-service.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-store-pickup.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-km-delivery.php';
    }

    private function init_modules()
    {
        $this->logger       = new AutoCEP_Logger();
        $this->stats        = new AutoCEP_Stats();
        $this->admin        = new AutoCEP_Admin();
        $this->api          = new AutoCEP_Api($this->logger);
        $this->shipping     = new AutoCEP_Shipping($this->logger);
        $this->store_pickup = new AutoCEP_Store_Pickup($this->logger);
    }

    private function register_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_filter('plugin_action_links_' . AUTOCEP_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('plugins_loaded', array($this, 'maybe_upgrade'));
        add_filter('woocommerce_shipping_methods', array($this, 'register_shipping_methods'));
    }

    public function register_shipping_methods($methods)
    {
        $options = AutoCEP_Admin::get_options();
        $config  = isset($options['distance_shipping']) ? $options['distance_shipping'] : array();

        if (!empty($config['pickup_enabled'])) {
            require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-shipping-pickup.php';
            $methods['autocep_store_pickup'] = 'AutoCEP_Shipping_Store_Pickup';
        }

        return $methods;
    }

    public function maybe_upgrade()
    {
        $versao_instalada = get_option('autocep_db_version');

        if (AUTOCEP_VERSION === $versao_instalada) {
            return;
        }

        if (class_exists('AutoCEP_Stats')) {
            AutoCEP_Stats::create_table();
        }

        if (class_exists('AutoCEP_Tickets')) {
            AutoCEP_Tickets::create_table();
        }

        update_option('autocep_db_version', AUTOCEP_VERSION);
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('autocep', false, dirname(AUTOCEP_PLUGIN_BASENAME) . '/languages');
    }

    public function enqueue_frontend_assets()
    {
        if (!function_exists('is_checkout') || !class_exists('WooCommerce')) {
            return;
        }

        $options = AutoCEP_Admin::get_options();

        $is_checkout_page = is_checkout();
        $is_product_page  = function_exists('is_product') && is_product();
        $is_cart_page     = function_exists('is_cart') && is_cart();

        $needs_checkout_assets = $is_checkout_page && (
            !empty($options['general']['enable_billing'])
            || !empty($options['general']['enable_shipping'])
            || !empty($options['checkout_shipping']['enabled'])
        );

        $needs_product_assets = $is_product_page && !empty($options['product_shipping']['enabled']);
        $needs_cart_assets    = $is_cart_page && !empty($options['cart_shipping']['enabled']);

        if (!$needs_checkout_assets && !$needs_product_assets && !$needs_cart_assets) {
            return;
        }

        wp_enqueue_style(
            'autocep-style',
            AUTOCEP_PLUGIN_URL . 'assets/css/autocep-style.css',
            array(),
            AUTOCEP_VERSION
        );

        $aparencia = isset($options['appearance']) ? $options['appearance'] : array();

        if (!empty($aparencia)) {
            $mapa_radius = array('nenhum' => '0px', 'pequeno' => '4px', 'medio' => '8px', 'grande' => '16px');
            $mapa_fonte  = array('pequeno' => '11px', 'medio' => '12px', 'grande' => '14px');
            $mapa_espaco = array(
                'compacto' => array('padding' => '8px 10px', 'gap' => '4px'),
                'normal'   => array('padding' => '10px 12px', 'gap' => '6px'),
                'espacoso' => array('padding' => '16px 18px', 'gap' => '10px'),
            );
            $mapa_peso   = array('normal' => '600', 'negrito' => '800');

            $radius_key  = $aparencia['border_radius'] ?? 'medio';
            $fonte_key   = $aparencia['font_size'] ?? 'medio';
            $espaco_key  = $aparencia['spacing'] ?? 'normal';
            $peso_key    = $aparencia['button_font_weight'] ?? 'normal';

            $radius = $mapa_radius[$radius_key] ?? $mapa_radius['medio'];
            $fonte  = $mapa_fonte[$fonte_key] ?? $mapa_fonte['medio'];
            $espaco = $mapa_espaco[$espaco_key] ?? $mapa_espaco['normal'];
            $peso   = $mapa_peso[$peso_key] ?? $mapa_peso['normal'];

            $css_variaveis = sprintf(
                ':root{--autocep-field-bg:%1$s;--autocep-field-border:%2$s;--autocep-field-text:%3$s;--autocep-button-bg:%4$s;--autocep-button-text:%5$s;--autocep-button-hover-bg:%6$s;--autocep-box-bg:%7$s;--autocep-box-border:%8$s;--autocep-radius:%9$s;--autocep-font-size:%10$s;--autocep-box-padding:%11$s;--autocep-gap:%12$s;--autocep-button-weight:%13$s;--autocep-freeship-bar-color:%14$s;--autocep-freeship-achieved-color:%15$s;}',
                esc_attr($aparencia['field_bg_color'] ?? '#ffffff'),
                esc_attr($aparencia['field_border_color'] ?? '#cccccc'),
                esc_attr($aparencia['field_text_color'] ?? '#333333'),
                esc_attr($aparencia['button_bg_color'] ?? '#1e1e1e'),
                esc_attr($aparencia['button_text_color'] ?? '#ffffff'),
                esc_attr($aparencia['button_hover_bg_color'] ?? '#333333'),
                esc_attr($aparencia['box_bg_color'] ?? '#ffffff'),
                esc_attr($aparencia['box_border_color'] ?? '#e5e5e5'),
                esc_attr($radius),
                esc_attr($fonte),
                esc_attr($espaco['padding']),
                esc_attr($espaco['gap']),
                esc_attr($peso),
                esc_attr($aparencia['free_shipping_bar_color'] ?? '#1e1e1e'),
                esc_attr($aparencia['free_shipping_achieved_color'] ?? '#1a7d36')
            );

            wp_add_inline_style('autocep-style', $css_variaveis);
        }

        wp_enqueue_script(
            'autocep-frontend',
            AUTOCEP_PLUGIN_URL . 'assets/js/autocep-frontend.js',
            array('jquery'),
            AUTOCEP_VERSION,
            true
        );

        wp_localize_script('autocep-frontend', 'autocep_params', array(
            'ajax_url'        => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce(AUTOCEP_NONCE_ACTION),
            'is_checkout'     => $is_checkout_page,
            'is_product'      => $is_product_page,
            'is_cart'         => $is_cart_page,
            'product_id'      => $is_product_page ? get_the_ID() : 0,
            'general'         => array(
                'enable_billing'       => !empty($options['general']['enable_billing']),
                'enable_shipping'      => !empty($options['general']['enable_shipping']),
                'neighborhood_field'   => $options['general']['neighborhood_field'],
                'auto_focus'           => !empty($options['general']['auto_focus']),
                'browser_autocomplete' => !empty($options['general']['browser_autocomplete']),
            ),
            'product_shipping' => array(
                'enabled'            => !empty($options['product_shipping']['enabled']),
                'consider_quantity'  => !empty($options['product_shipping']['consider_quantity']),
                'title'              => $options['product_shipping']['title'],
            ),
            'cart_shipping' => array(
                'enabled' => !empty($options['cart_shipping']['enabled']),
            ),
            'checkout_shipping' => array(
                'enabled' => !empty($options['checkout_shipping']['enabled']),
            ),
            'messages' => array(
                'searching'             => $options['messages']['searching'],
                'not_found'             => $options['messages']['not_found'],
                'calculating'           => $options['messages']['calculating'],
                'invalid_cep'           => $options['messages']['invalid_cep'],
                'generic_error'         => $options['messages']['generic_error'],
                'select_variation'      => $options['messages']['select_variation'],
                'no_street'             => $options['messages']['no_street'],
                'select_rate'           => $options['messages']['select_rate'],
                'selecting_rate'        => $options['messages']['selecting_rate'],
                'rate_selected'         => $options['messages']['rate_selected'],
                'block_checkout_button' => !empty($options['messages']['block_checkout_button']),
                'select_to_apply'       => __('Selecione uma opção acima para aplicar o frete ao seu pedido.', 'autocep'),
                'view_instructions'     => __('Ver instruções', 'autocep'),
                'hide_instructions'     => __('Ocultar instruções', 'autocep'),
            ),
        ));
    }

    public function plugin_action_links($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=autocep')),
            esc_html__('Configurações', 'autocep')
        );

        $pro_link = sprintf(
            '<a href="%s" target="_blank" style="color:#2271b1;font-weight:600;">%s</a>',
            esc_url('https://tesw.com.br/plugins/'),
            esc_html__('Conheça Nossos Plugins', 'autocep')
        );

        array_unshift($links, $settings_link, $pro_link);

        return $links;
    }

    public function plugin_row_meta($plugin_meta, $plugin_file)
    {
        if (AUTOCEP_PLUGIN_BASENAME !== $plugin_file) {
            return $plugin_meta;
        }

        foreach ($plugin_meta as $indice => $item) {
            if (false !== strpos($item, 'tesw.com.br')) {
                $plugin_meta[$indice] = sprintf(
                    __('Por %1$s — Dev. %2$s', 'autocep'),
                    '<a href="' . esc_url('https://tesw.com.br') . '" target="_blank" rel="noopener noreferrer">TESW</a>',
                    '<a href="' . esc_url('https://www.linkedin.com/in/wanderson-cesar-2710a621b') . '" target="_blank" rel="noopener noreferrer">Wanderson Cesar</a>'
                );
                break;
            }
        }

        return $plugin_meta;
    }

    public static function activate()
    {
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-logger.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-stats.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-tickets.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-admin.php';

        AutoCEP_Logger::create_table();
        AutoCEP_Stats::create_table();
        AutoCEP_Tickets::create_table();

        if (false === get_option(AUTOCEP_OPTION_KEY)) {
            add_option(AUTOCEP_OPTION_KEY, AutoCEP_Admin::get_default_options());
        }

        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        flush_rewrite_rules();
    }
}

register_activation_hook(__FILE__, array('AutoCEP_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('AutoCEP_Plugin', 'deactivate'));

function autocep_init_plugin()
{
    AutoCEP_Plugin::get_instance();
}
add_action('plugins_loaded', 'autocep_init_plugin', 20);
