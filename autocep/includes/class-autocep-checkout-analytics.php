<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics do Checkout Moderno: funil de conversão.
 *
 * O navegador do cliente informa cada passo do checkout (entrou, chegou à
 * etapa 2, à etapa 3, clicou em "Finalizar", campos com erro) e o servidor
 * registra o pedido concluído. Tudo é ligado por um código de sessão
 * anônimo — nenhum nome, e-mail, CPF ou endereço é gravado.
 *
 * Cada passo é contado uma vez por sessão por dia, então os números são
 * "clientes" e não "cliques". A coleta segue a opção "Coletar Estatísticas"
 * (aba Frete e Busca de CEP).
 */
class AutoCEP_Checkout_Analytics
{
    const DB_VERSION = '1';

    const NONCE_ACTION = 'autocep_mc_track';

    // Passos que o navegador pode informar, na ordem do funil.
    const FUNNEL_EVENTS = array('view', 'step2', 'step3', 'submit');

    // Limite de linhas por sessão por dia (proteção contra abuso do endpoint público).
    const MAX_EVENTS_PER_SESSION_DAY = 40;

    const MAX_ROWS = 60000;

    public function __construct()
    {
        add_action('wp_ajax_autocep_mc_track', array($this, 'ajax_track'));
        add_action('wp_ajax_nopriv_autocep_mc_track', array($this, 'ajax_track'));
        add_action('woocommerce_checkout_order_processed', array($this, 'record_order'), 25, 3);

        // O botão "Limpar" da aba Analytics também zera o funil (roda antes
        // do handler original, que encerra a requisição).
        add_action('wp_ajax_autocep_clear_stats', array($this, 'clear_on_stats_reset'), 5);

        add_action('admin_init', array(__CLASS__, 'maybe_create_table'));
    }

    public static function get_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'autocep_checkout_events';
    }

    /**
     * Cria a tabela na primeira vez (inclusive em lojas que já estavam na
     * versão 2.48 antes deste recurso existir).
     */
    public static function maybe_create_table()
    {
        if (self::DB_VERSION === get_option('autocep_checkout_events_db')) {
            return;
        }

        global $wpdb;

        $tabela  = self::get_table_name();
        $collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$tabela} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_token VARCHAR(64) NOT NULL DEFAULT '',
            evento VARCHAR(20) NOT NULL DEFAULT '',
            detalhe VARCHAR(60) NOT NULL DEFAULT '',
            dispositivo VARCHAR(10) NOT NULL DEFAULT '',
            valor DECIMAL(12,2) NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY sessao_evento (session_token, evento),
            KEY evento_data (evento, criado_em)
        ) {$collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('autocep_checkout_events_db', self::DB_VERSION, false);
    }

    public static function is_enabled()
    {
        return class_exists('AutoCEP_Stats') && AutoCEP_Stats::is_enabled();
    }

    /**
     * Mesmo código de sessão usado nas estatísticas de frete: o ID de
     * cliente da sessão do WooCommerce (ou um cookie anônimo).
     */
    private static function session_token()
    {
        return AutoCEP_Stats::get_session_token();
    }

    /**
     * Data no fuso do site. wp_date() só existe no WordPress 5.3+; o plugin
     * aceita a partir do 5.0, então há um plano B equivalente.
     */
    private static function local_date($formato, $timestamp)
    {
        if (function_exists('wp_date')) {
            return wp_date($formato, $timestamp);
        }

        return gmdate($formato, $timestamp + (int) round((float) get_option('gmt_offset', 0) * HOUR_IN_SECONDS));
    }

    private static function today_start()
    {
        return self::local_date('Y-m-d 00:00:00', time());
    }

    private static function period_start($dias)
    {
        return self::local_date('Y-m-d H:i:s', time() - max(1, (int) $dias) * DAY_IN_SECONDS);
    }

    /**
     * Grava um evento, uma vez por sessão/evento/detalhe por dia.
     */
    private static function insert($sessao, $evento, $detalhe = '', $dispositivo = '', $valor = 0)
    {
        global $wpdb;

        self::maybe_create_table();

        $tabela = self::get_table_name();
        $hoje   = self::today_start();

        // Pedidos sempre contam (dois pedidos no mesmo dia são dois pedidos).
        $ja_existe = 'order' === $evento ? null : $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tabela} WHERE session_token = %s AND evento = %s AND detalhe = %s AND criado_em >= %s LIMIT 1",
            $sessao,
            $evento,
            $detalhe,
            $hoje
        ));

        if ($ja_existe) {
            return;
        }

        $total_hoje = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tabela} WHERE session_token = %s AND criado_em >= %s",
            $sessao,
            $hoje
        ));

        if ($total_hoje >= self::MAX_EVENTS_PER_SESSION_DAY) {
            return;
        }

        $wpdb->insert(
            $tabela,
            array(
                'session_token' => $sessao,
                'evento'        => $evento,
                'detalhe'       => $detalhe,
                'dispositivo'   => $dispositivo,
                'valor'         => (float) $valor,
                'criado_em'     => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%f', '%s')
        );

        if (1 === wp_rand(1, 200)) {
            self::prune();
        }
    }

    private static function prune()
    {
        global $wpdb;

        $tabela = self::get_table_name();

        $wpdb->query($wpdb->prepare("DELETE FROM {$tabela} WHERE criado_em < %s", self::period_start(180)));

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tabela}");

        if ($total > self::MAX_ROWS) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$tabela} ORDER BY id ASC LIMIT %d", $total - self::MAX_ROWS));
        }
    }

    /**
     * Endpoint público chamado pelo Checkout Moderno a cada passo.
     */
    public function ajax_track()
    {
        if (!self::is_enabled() || !check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(null, 403);
        }

        if (!AutoCEP_Api::throttle('checkout_track', 120)) {
            wp_send_json_error(null, 429);
        }

        $evento = isset($_POST['evento']) ? sanitize_key(wp_unslash($_POST['evento'])) : '';
        $detalhe = isset($_POST['detalhe']) ? sanitize_key(wp_unslash($_POST['detalhe'])) : '';
        $dispositivo = isset($_POST['dispositivo']) && 'mobile' === $_POST['dispositivo'] ? 'mobile' : 'desktop';

        if (in_array($evento, self::FUNNEL_EVENTS, true)) {
            // No "Finalizar", o detalhe é o tipo de pagamento escolhido.
            if ('submit' === $evento) {
                $detalhe = in_array($detalhe, array('pix', 'card', 'boleto'), true) ? $detalhe : 'other';
            } else {
                $detalhe = '';
            }
        } elseif ('error' === $evento) {
            // Campo com erro (ID do campo, ex.: billing_phone).
            $detalhe = substr($detalhe, 0, 60);

            if ('' === $detalhe) {
                wp_send_json_error(null, 400);
            }
        } else {
            wp_send_json_error(null, 400);
        }

        self::insert(self::session_token(), $evento, $detalhe, $dispositivo);

        wp_send_json_success();
    }

    /**
     * Pedido concluído — só conta se a sessão passou pelo Checkout Moderno
     * (tem "view" nos últimos 2 dias), para o funil não misturar pedidos
     * feitos por outros caminhos.
     */
    public function record_order($order_id, $posted_data, $order)
    {
        if (!self::is_enabled() || !($order instanceof WC_Order)) {
            return;
        }

        global $wpdb;

        self::maybe_create_table();

        $tabela = self::get_table_name();
        $sessao = self::session_token();

        $dispositivo = $wpdb->get_var($wpdb->prepare(
            "SELECT dispositivo FROM {$tabela} WHERE session_token = %s AND evento = 'view' AND criado_em >= %s ORDER BY id DESC LIMIT 1",
            $sessao,
            self::period_start(2)
        ));

        if (null === $dispositivo) {
            return;
        }

        $tipos = array();

        if (class_exists('AutoCEP_Modern_Checkout')) {
            $tipos = AutoCEP_Modern_Checkout::get_gateway_types_public();
        }

        $metodo = $order->get_payment_method();
        $tipo   = isset($tipos[$metodo]) ? $tipos[$metodo] : 'other';

        self::insert($sessao, 'order', $tipo, $dispositivo, $order->get_total());
    }

    public function clear_on_stats_reset()
    {
        if (!current_user_can('manage_options') || !check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce', false)) {
            return;
        }

        global $wpdb;

        self::maybe_create_table();
        $wpdb->query('TRUNCATE TABLE ' . self::get_table_name());
    }

    /* -----------------------------------------------------------------
     * Consultas do painel
     * ------------------------------------------------------------- */

    /**
     * Número de sessões distintas por evento no período.
     *
     * @return array<string, int>
     */
    public static function get_funnel($dias)
    {
        global $wpdb;

        self::maybe_create_table();

        $linhas = $wpdb->get_results($wpdb->prepare(
            'SELECT evento, COUNT(DISTINCT session_token) AS total FROM ' . self::get_table_name() . ' WHERE criado_em >= %s GROUP BY evento',
            self::period_start($dias)
        ));

        $funil = array('view' => 0, 'step2' => 0, 'step3' => 0, 'submit' => 0, 'order' => 0);

        foreach ($linhas as $linha) {
            if (isset($funil[$linha->evento])) {
                $funil[$linha->evento] = (int) $linha->total;
            }
        }

        return $funil;
    }

    /**
     * Receita e ticket médio dos pedidos do funil.
     */
    public static function get_revenue($dias)
    {
        global $wpdb;

        $linha = $wpdb->get_row($wpdb->prepare(
            'SELECT COUNT(*) AS pedidos, COALESCE(SUM(valor), 0) AS receita FROM ' . self::get_table_name() . " WHERE evento = 'order' AND criado_em >= %s",
            self::period_start($dias)
        ));

        $pedidos = $linha ? (int) $linha->pedidos : 0;
        $receita = $linha ? (float) $linha->receita : 0.0;

        return array(
            'pedidos' => $pedidos,
            'receita' => $receita,
            'ticket'  => $pedidos ? $receita / $pedidos : null,
        );
    }

    /**
     * Tempo médio (em segundos) entre entrar no checkout e concluir o pedido.
     */
    public static function get_average_time($dias)
    {
        global $wpdb;

        $tabela = self::get_table_name();

        $media = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, v.inicio, o.criado_em))
             FROM {$tabela} o
             INNER JOIN (
                 SELECT session_token, MIN(criado_em) AS inicio
                 FROM {$tabela}
                 WHERE evento = 'view' AND criado_em >= %s
                 GROUP BY session_token
             ) v ON v.session_token = o.session_token
             WHERE o.evento = 'order' AND o.criado_em >= %s AND o.criado_em >= v.inicio",
            self::period_start($dias + 2),
            self::period_start($dias)
        ));

        return null === $media ? null : (int) round((float) $media);
    }

    /**
     * Clicaram em "Finalizar" x pedidos concluídos, por tipo de pagamento.
     *
     * @return array<string, array{submit: int, order: int}>
     */
    public static function get_by_payment($dias)
    {
        global $wpdb;

        $linhas = $wpdb->get_results($wpdb->prepare(
            'SELECT evento, detalhe, COUNT(DISTINCT session_token) AS total FROM ' . self::get_table_name() . " WHERE evento IN ('submit', 'order') AND criado_em >= %s GROUP BY evento, detalhe",
            self::period_start($dias)
        ));

        $dados = array();

        foreach ($linhas as $linha) {
            $tipo = $linha->detalhe ? $linha->detalhe : 'other';

            if (!isset($dados[$tipo])) {
                $dados[$tipo] = array('submit' => 0, 'order' => 0);
            }

            $dados[$tipo][$linha->evento] = (int) $linha->total;
        }

        return $dados;
    }

    /**
     * Entradas no checkout x pedidos, por aparelho.
     *
     * @return array<string, array{view: int, order: int}>
     */
    public static function get_by_device($dias)
    {
        global $wpdb;

        $linhas = $wpdb->get_results($wpdb->prepare(
            'SELECT evento, dispositivo, COUNT(DISTINCT session_token) AS total FROM ' . self::get_table_name() . " WHERE evento IN ('view', 'order') AND criado_em >= %s GROUP BY evento, dispositivo",
            self::period_start($dias)
        ));

        $dados = array(
            'mobile'  => array('view' => 0, 'order' => 0),
            'desktop' => array('view' => 0, 'order' => 0),
        );

        foreach ($linhas as $linha) {
            $aparelho = 'mobile' === $linha->dispositivo ? 'mobile' : 'desktop';
            $dados[$aparelho][$linha->evento] += (int) $linha->total;
        }

        return $dados;
    }

    /**
     * Campos que mais travaram os clientes (sessões distintas com erro).
     */
    public static function get_top_errors($dias, $limite = 8)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            'SELECT detalhe AS campo, COUNT(DISTINCT session_token) AS total FROM ' . self::get_table_name() . " WHERE evento = 'error' AND criado_em >= %s GROUP BY detalhe ORDER BY total DESC LIMIT %d",
            self::period_start($dias),
            $limite
        ));
    }
}
