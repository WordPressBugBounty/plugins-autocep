<?php
/**
 * Plugin Name: AutoCEP
 * Plugin URI: https://tesw.com.br/plugins/
 * Description: Autocompletar endereço por CEP e simulador de frete completo para WooCommerce — produto, carrinho e checkout — com múltiplas APIs, cache, fallback automático, compatível com Fluid Checkout, CartFlows, Melhor Envio, Frenet e mais.
 * Version: 2.3.6
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
 *
 * Este plugin, todas as bibliotecas incluídas e quaisquer outros ativos incluídos
 * são licenciados como GPL ou estão sob uma licença compatível com GPL.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Nota interna: os identificadores de código (prefixo de funções/classes
 * "autocep"/"AutoCEP_", constantes AUTOCEP_*, text domain "autocep") foram
 * mantidos intencionalmente ao renomear o nome público do plugin, para não
 * arriscar quebrar hooks, opções salvas ou traduções já existentes.
 */

if (!defined('ABSPATH')) {
    exit; // Impede acesso direto ao arquivo.
}

/*
 * -----------------------------------------------------------------------
 * Constantes globais do plugin
 * -----------------------------------------------------------------------
 */
define('AUTOCEP_VERSION', '2.3.5');
define('AUTOCEP_PLUGIN_FILE', __FILE__);
define('AUTOCEP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUTOCEP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUTOCEP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AUTOCEP_OPTION_KEY', 'autocep_options');
define('AUTOCEP_NONCE_ACTION', 'autocep_nonce');
define('AUTOCEP_ADMIN_NONCE_ACTION', 'autocep_admin_nonce');

/**
 * Classe principal de inicialização do plugin.
 *
 * Responsável por carregar os módulos, registrar hooks globais e manter
 * total compatibilidade com o comportamento legado (autocompletar o
 * endereço no Checkout do WooCommerce), que permanece ativo por padrão.
 */
final class AutoCEP_Plugin
{
    /**
     * Instância única (Singleton).
     *
     * @var AutoCEP_Plugin|null
     */
    private static $instance = null;

    /**
     * Instância do gerenciador de opções/admin.
     *
     * @var AutoCEP_Admin
     */
    public $admin;

    /**
     * Instância do gerenciador de APIs de CEP.
     *
     * @var AutoCEP_Api
     */
    public $api;

    /**
     * Instância do motor de cálculo de frete.
     *
     * @var AutoCEP_Shipping
     */
    public $shipping;

    /**
     * Instância do sistema de logs/diagnóstico.
     *
     * @var AutoCEP_Logger
     */
    public $logger;

    /**
     * Retorna (ou cria) a instância única do plugin.
     *
     * @return AutoCEP_Plugin
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Construtor privado (Singleton).
     */
    private function __construct()
    {
        $this->includes();
        $this->init_modules();
        $this->register_hooks();
    }

    /**
     * Inclui os arquivos das classes do plugin.
     *
     * @return void
     */
    private function includes()
    {
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-logger.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-admin.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-api.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-shipping.php';
    }

    /**
     * Instancia os módulos principais do plugin.
     *
     * @return void
     */
    private function init_modules()
    {
        $this->logger   = new AutoCEP_Logger();
        $this->admin    = new AutoCEP_Admin();
        $this->api      = new AutoCEP_Api($this->logger);
        $this->shipping = new AutoCEP_Shipping($this->logger);
    }

    /**
     * Registra hooks globais do WordPress.
     *
     * @return void
     */
    private function register_hooks()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_filter('plugin_action_links_' . AUTOCEP_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    /**
     * Carrega o textdomain para traduções.
     *
     * @return void
     */
    public function load_textdomain()
    {
        load_plugin_textdomain('autocep', false, dirname(AUTOCEP_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Enfileira scripts e estilos no front-end, apenas nas páginas
     * relevantes (Checkout e/ou página de produto), de acordo com as
     * opções configuradas no painel administrativo.
     *
     * @return void
     */
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

        // Aplica as cores personalizadas definidas em AutoCEP > Aparência
        // sobrescrevendo as variáveis CSS usadas pelo autocep-style.css.
        $aparencia = isset($options['appearance']) ? $options['appearance'] : array();

        if (!empty($aparencia)) {
            $css_variaveis = sprintf(
                ':root{--autocep-field-bg:%1$s;--autocep-field-border:%2$s;--autocep-field-text:%3$s;--autocep-button-bg:%4$s;--autocep-button-text:%5$s;--autocep-button-hover-bg:%6$s;}',
                esc_attr($aparencia['field_bg_color'] ?? '#ffffff'),
                esc_attr($aparencia['field_border_color'] ?? '#cccccc'),
                esc_attr($aparencia['field_text_color'] ?? '#333333'),
                esc_attr($aparencia['button_bg_color'] ?? '#1e1e1e'),
                esc_attr($aparencia['button_text_color'] ?? '#ffffff'),
                esc_attr($aparencia['button_hover_bg_color'] ?? '#333333')
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
            ),
        ));
    }

    /**
     * Adiciona links personalizados na listagem de plugins.
     *
     * @param array $links Links existentes.
     *
     * @return array
     */
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

    /**
     * Rotina executada na ativação do plugin: cria a tabela de logs e
     * grava as opções padrão (somente se ainda não existirem), garantindo
     * que o comportamento legado continue habilitado por padrão.
     *
     * @return void
     */
    public static function activate()
    {
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-logger.php';
        require_once AUTOCEP_PLUGIN_DIR . 'includes/class-autocep-admin.php';

        AutoCEP_Logger::create_table();

        if (false === get_option(AUTOCEP_OPTION_KEY)) {
            add_option(AUTOCEP_OPTION_KEY, AutoCEP_Admin::get_default_options());
        }

        flush_rewrite_rules();
    }

    /**
     * Rotina executada na desativação do plugin.
     *
     * @return void
     */
    public static function deactivate()
    {
        flush_rewrite_rules();
    }
}

register_activation_hook(__FILE__, array('AutoCEP_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('AutoCEP_Plugin', 'deactivate'));

/**
 * Inicializa o plugin somente depois que todos os plugins (incluindo o
 * WooCommerce) tenham sido carregados.
 *
 * @return void
 */
function autocep_init_plugin()
{
    AutoCEP_Plugin::get_instance();
}
add_action('plugins_loaded', 'autocep_init_plugin', 20);
