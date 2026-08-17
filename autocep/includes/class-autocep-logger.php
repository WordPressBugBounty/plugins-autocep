<?php
/**
 * Classe responsável pelo sistema de diagnóstico e logs do AutoCEP.
 *
 * @package AutoCEP
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra falhas/timeouts das APIs externas em uma tabela própria e
 * fornece um verificador de status (online/offline) para o painel de
 * diagnóstico.
 */
class AutoCEP_Logger
{
    /**
     * Quantidade máxima de registros mantidos na tabela de logs.
     *
     * @var int
     */
    const MAX_LOG_ROWS = 500;

    /**
     * Construtor: registra a rota AJAX de limpeza de logs.
     */
    public function __construct()
    {
        add_action('wp_ajax_autocep_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_autocep_check_api_status', array($this, 'ajax_check_api_status'));
    }

    /**
     * Retorna o nome completo (com prefixo) da tabela de logs.
     *
     * @return string
     */
    public static function get_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'autocep_logs';
    }

    /**
     * Cria a tabela de logs no banco de dados (executado na ativação).
     *
     * @return void
     */
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

    /**
     * Insere um registro na tabela de logs.
     *
     * @param string $api      Identificador da API (ex.: viacep, brasilapi).
     * @param string $cep      CEP relacionado ao evento (pode ser vazio).
     * @param string $mensagem Mensagem descritiva do evento.
     * @param string $tipo     Tipo do evento: error, warning ou info.
     *
     * @return void
     */
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

        // Registra também no log padrão do PHP/WordPress para depuração.
        if (function_exists('error_log')) {
            error_log(sprintf('[AutoCEP][%s][%s] %s (CEP: %s)', $tipo, $api, $mensagem, $cep));
        }

        $this->prune_old_logs();
    }

    /**
     * Remove registros antigos para manter a tabela dentro do limite
     * máximo definido em self::MAX_LOG_ROWS.
     *
     * @return void
     */
    private function prune_old_logs()
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $total      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($total <= self::MAX_LOG_ROWS) {
            return;
        }

        $excedente = $total - self::MAX_LOG_ROWS;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- valor interno já validado como inteiro.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} ORDER BY id ASC LIMIT %d",
                $excedente
            )
        );
    }

    /**
     * Retorna os últimos registros de log.
     *
     * @param int $limit Quantidade máxima de registros.
     *
     * @return array
     */
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

    /**
     * Limpa todos os registros da tabela de logs via requisição AJAX
     * (restrito a administradores).
     *
     * @return void
     */
    public function ajax_clear_logs()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para executar esta ação.', 'autocep')));
        }

        global $wpdb;
        $table_name = self::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- TRUNCATE não aceita placeholders e o nome da tabela é fixo/interno.
        $wpdb->query("TRUNCATE TABLE {$table_name}");

        wp_send_json_success(array('message' => __('Logs limpos com sucesso.', 'autocep')));
    }

    /**
     * Verifica em tempo real o status (online/offline) das APIs de CEP
     * habilitadas, realizando uma consulta de teste em cada uma.
     *
     * @return void
     */
    public function ajax_check_api_status()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para executar esta ação.', 'autocep')));
        }

        $cep_teste = '01310930'; // CEP de teste válido (Av. Paulista, SP).
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
