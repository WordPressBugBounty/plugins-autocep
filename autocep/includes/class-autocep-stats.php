<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Stats
{
    const MAX_STATS_ROWS = 20000;

    const FALLBACK_COOKIE = 'autocep_stats_token';

    public function __construct()
    {
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'record_order'), 20, 3);

        add_action('wp_ajax_autocep_clear_stats', array($this, 'ajax_clear_stats'));
    }

    public static function get_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'autocep_shipping_stats';
    }

    public static function create_table()
    {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_token VARCHAR(64) NOT NULL DEFAULT '',
            evento VARCHAR(20) NOT NULL DEFAULT 'simulation',
            contexto VARCHAR(20) NOT NULL DEFAULT '',
            cep VARCHAR(9) NOT NULL DEFAULT '',
            uf VARCHAR(2) NOT NULL DEFAULT '',
            cidade VARCHAR(100) NOT NULL DEFAULT '',
            valor_frete DECIMAL(10,2) NOT NULL DEFAULT 0,
            shipping_method VARCHAR(120) NOT NULL DEFAULT '',
            order_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
            criado_em DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY session_token (session_token),
            KEY evento (evento),
            KEY cep (cep),
            KEY uf (uf),
            KEY shipping_method (shipping_method),
            KEY criado_em (criado_em)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function is_enabled()
    {
        $options = AutoCEP_Admin::get_options();

        return !empty($options['general']['stats_enabled']);
    }

    public static function get_session_token()
    {
        if (function_exists('WC') && WC()->session && method_exists(WC()->session, 'get_customer_id')) {
            $customer_id = WC()->session->get_customer_id();

            if ($customer_id) {
                return (string) $customer_id;
            }
        }

        if (!empty($_COOKIE[self::FALLBACK_COOKIE])) {
            return sanitize_text_field(wp_unslash($_COOKIE[self::FALLBACK_COOKIE]));
        }

        $token = wp_generate_password(32, false, false);

        if (!headers_sent()) {
            setcookie(self::FALLBACK_COOKIE, $token, time() + 30 * DAY_IN_SECONDS, (defined('COOKIEPATH') && COOKIEPATH) ? COOKIEPATH : '/', defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '');
        }

        return $token;
    }

    public static function record_simulation($contexto, $cep, $uf, $cidade, $valor)
    {
        if (!self::is_enabled() || empty($cep)) {
            return;
        }

        global $wpdb;

        $wpdb->insert(
            self::get_table_name(),
            array(
                'session_token' => self::get_session_token(),
                'evento'        => 'simulation',
                'contexto'      => sanitize_text_field($contexto),
                'cep'           => sanitize_text_field($cep),
                'uf'            => sanitize_text_field(strtoupper($uf)),
                'cidade'        => sanitize_text_field($cidade),
                'valor_frete'   => (float) $valor,
                'order_id'      => null,
                'criado_em'     => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s')
        );

        self::maybe_prune();
    }

    public static function record_order($order_id, $posted_data, $order)
    {
        if (!self::is_enabled() || !($order instanceof WC_Order)) {
            return;
        }

        global $wpdb;

        $wpdb->insert(
            self::get_table_name(),
            array(
                'session_token'   => self::get_session_token(),
                'evento'          => 'order',
                'contexto'        => '',
                'cep'             => sanitize_text_field($order->get_shipping_postcode() ?: $order->get_billing_postcode()),
                'uf'              => sanitize_text_field(strtoupper($order->get_shipping_state() ?: $order->get_billing_state())),
                'cidade'          => sanitize_text_field($order->get_shipping_city() ?: $order->get_billing_city()),
                'valor_frete'     => (float) $order->get_shipping_total(),
                'shipping_method' => sanitize_text_field(implode(',', array_keys($order->get_shipping_methods()))),
                'order_id'        => $order->get_id(),
                'criado_em'       => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%d', '%s')
        );

        self::maybe_prune();
    }

    private static function maybe_prune()
    {
        if (1 !== wp_rand(1, 50)) {
            return;
        }

        global $wpdb;

        $table_name = self::get_table_name();
        $total      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($total <= self::MAX_STATS_ROWS) {
            return;
        }

        $excedente = $total - self::MAX_STATS_ROWS;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} ORDER BY id ASC LIMIT %d",
                $excedente
            )
        );
    }

    private static function period_start($dias)
    {
        return gmdate('Y-m-d H:i:s', time() - (max(1, (int) $dias) * DAY_IN_SECONDS));
    }

    public static function get_top_shipping_methods($dias, $limite = 10)
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $inicio     = self::period_start($dias);

        $tem_coluna = $wpdb->get_var("SHOW COLUMNS FROM {$table_name} LIKE 'shipping_method'");

        if (!$tem_coluna) {
            return self::get_top_methods_from_orders($dias, $limite);
        }

        $resultados = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT shipping_method AS metodo, COUNT(*) AS total
                 FROM {$table_name}
                 WHERE evento = 'order' AND shipping_method != '' AND criado_em >= %s
                 GROUP BY shipping_method
                 ORDER BY total DESC
                 LIMIT %d",
                $inicio,
                $limite
            )
        );

        if (!empty($resultados)) {
            return $resultados;
        }

        return self::get_top_methods_from_orders($dias, $limite);
    }

    private static function get_top_methods_from_orders($dias, $limite)
    {
        global $wpdb;

        $inicio = self::period_start($dias);

        $hpos_ativo = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

        if ($hpos_ativo) {
            $resultados = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT oi.order_item_name AS metodo, COUNT(DISTINCT o.id) AS total
                     FROM {$wpdb->prefix}wc_orders o
                     INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                         ON oi.order_id = o.id AND oi.order_item_type = 'shipping'
                     WHERE o.type = 'shop_order'
                       AND o.status IN ('wc-completed','wc-processing','wc-on-hold','wc-pending')
                       AND o.date_created_gmt >= %s
                     GROUP BY oi.order_item_name
                     ORDER BY total DESC
                     LIMIT %d",
                    $inicio,
                    $limite
                )
            );

            return $resultados ?: array();
        }

        $resultados = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT oi.order_item_name AS metodo, COUNT(DISTINCT o.ID) AS total
                 FROM {$wpdb->posts} o
                 INNER JOIN {$wpdb->prefix}woocommerce_order_items oi
                     ON oi.order_id = o.ID AND oi.order_item_type = 'shipping'
                 WHERE o.post_type IN ('shop_order','wc_order')
                   AND o.post_status IN ('wc-completed','wc-processing','wc-on-hold','wc-pending')
                   AND o.post_date >= %s
                 GROUP BY oi.order_item_name
                 ORDER BY total DESC
                 LIMIT %d",
                $inicio,
                $limite
            )
        );

        return $resultados ?: array();
    }

    public static function get_top_ceps($dias, $limite = 10)
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT cep, cidade, uf, COUNT(*) AS total
                 FROM {$table_name}
                 WHERE evento = 'simulation' AND cep != '' AND criado_em >= %s
                 GROUP BY cep
                 ORDER BY total DESC
                 LIMIT %d",
                self::period_start($dias),
                $limite
            )
        );
    }

    public static function get_top_regions($dias, $limite = 10)
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT uf, COUNT(*) AS total
                 FROM {$table_name}
                 WHERE evento = 'simulation' AND criado_em >= %s
                 GROUP BY uf
                 ORDER BY total DESC
                 LIMIT %d",
                self::period_start($dias),
                $limite
            )
        );
    }

    public static function get_average_costs($dias)
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $inicio     = self::period_start($dias);

        $calculado = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT AVG(valor_frete) AS media, COUNT(*) AS total FROM {$table_name} WHERE evento = 'simulation' AND criado_em >= %s",
                $inicio
            )
        );

        $pago = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT AVG(valor_frete) AS media, COUNT(*) AS total FROM {$table_name} WHERE evento = 'order' AND criado_em >= %s",
                $inicio
            )
        );

        return array(
            'calculado_media' => ($calculado && null !== $calculado->media) ? (float) $calculado->media : null,
            'calculado_total' => $calculado ? (int) $calculado->total : 0,
            'pago_media'      => ($pago && null !== $pago->media) ? (float) $pago->media : null,
            'pago_total'      => $pago ? (int) $pago->total : 0,
        );
    }

    public static function get_abandonment_rate($dias)
    {
        global $wpdb;

        $table_name       = self::get_table_name();
        $inicio           = self::period_start($dias);
        $limite_elegivel  = gmdate('Y-m-d H:i:s', time() - 48 * HOUR_IN_SECONDS);

        $total_elegivel = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT session_token) FROM {$table_name}
                 WHERE evento = 'simulation' AND criado_em >= %s AND criado_em <= %s",
                $inicio,
                $limite_elegivel
            )
        );

        if ($total_elegivel <= 0) {
            return null;
        }

        $convertido = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT s.session_token)
                 FROM {$table_name} s
                 INNER JOIN {$table_name} o
                     ON o.session_token = s.session_token
                     AND o.evento = 'order'
                     AND o.criado_em >= s.criado_em
                     AND o.criado_em <= DATE_ADD(s.criado_em, INTERVAL 48 HOUR)
                 WHERE s.evento = 'simulation' AND s.criado_em >= %s AND s.criado_em <= %s",
                $inicio,
                $limite_elegivel
            )
        );

        return array(
            'taxa'            => round((($total_elegivel - $convertido) / $total_elegivel) * 100, 1),
            'total_simulou'   => $total_elegivel,
            'total_converteu' => $convertido,
        );
    }

    public function ajax_clear_stats()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para executar esta ação.', 'autocep')));
        }

        global $wpdb;
        $table_name = self::get_table_name();

        $wpdb->query("TRUNCATE TABLE {$table_name}");

        wp_send_json_success(array('message' => __('Estatísticas limpas com sucesso.', 'autocep')));
    }
}
