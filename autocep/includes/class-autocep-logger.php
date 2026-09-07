<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Logger
{
    const MAX_LOG_ROWS = 500;

    public function __construct()
    {
        add_action('wp_ajax_autocep_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_autocep_check_api_status', array($this, 'ajax_check_api_status'));
    }

    public static function get_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'autocep_logs';
    }

    public static function create_table()
    {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            api VARCHAR(50) NOT NULL DEFAULT '',
            cep VARCHAR(9) NOT NULL DEFAULT '',
            tipo VARCHAR(20) NOT NULL DEFAULT 'error',
            mensagem TEXT NOT NULL,
            criado_em DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY api (api),
            KEY criado_em (criado_em)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function log($api, $cep, $mensagem, $tipo = 'error')
    {
        global $wpdb;

        $wpdb->insert(
            self::get_table_name(),
            array(
                'api'       => sanitize_text_field($api),
                'cep'       => sanitize_text_field($cep),
                'tipo'      => sanitize_text_field($tipo),
                'mensagem'  => sanitize_textarea_field($mensagem),
                'criado_em' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if (function_exists('error_log')) {
            error_log(sprintf('[AutoCEP][%s][%s] %s (CEP: %s)', $tipo, $api, $mensagem, $cep));
        }

        $this->prune_old_logs();
    }

    private function prune_old_logs()
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $total      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($total <= self::MAX_LOG_ROWS) {
            return;
        }

        $excedente = $total - self::MAX_LOG_ROWS;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} ORDER BY id ASC LIMIT %d",
                $excedente
            )
        );
    }

    public static function get_logs($limit = 50)
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d",
                $limit
            )
        );
    }

    public function ajax_clear_logs()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para executar esta ação.', 'autocep')));
        }

        global $wpdb;
        $table_name = self::get_table_name();

        $wpdb->query("TRUNCATE TABLE {$table_name}");

        wp_send_json_success(array('message' => __('Logs limpos com sucesso.', 'autocep')));
    }

    public function ajax_check_api_status()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para executar esta ação.', 'autocep')));
        }

        $cep_teste = '01310930'; 
        $resultado = array();

        $apis = AutoCEP_Api::get_supported_apis();

        foreach ($apis as $slug => $dados_api) {
            $inicio   = microtime(true);
            $response = AutoCEP_Api::request_provider($slug, $cep_teste);
            $tempo_ms = round((microtime(true) - $inicio) * 1000);

            $online = !is_wp_error($response) && !empty($response);

            $resultado[$slug] = array(
                'nome'    => $dados_api['label'],
                'online'  => $online,
                'tempo'   => $tempo_ms,
                'mensagem' => $online
                    ? sprintf(__('Respondeu em %d ms', 'autocep'), $tempo_ms)
                    : (is_wp_error($response) ? $response->get_error_message() : __('Resposta inválida', 'autocep')),
            );

            if (!$online) {
                $this->log($slug, $cep_teste, __('Falha na verificação de diagnóstico.', 'autocep'), 'warning');
            }
        }

        wp_send_json_success($resultado);
    }
}
