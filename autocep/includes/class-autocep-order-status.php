<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Status do pedido em tempo real + página de obrigado (redirecionamento).
 *
 * Na página de pedido recebido e na página do pedido em Minha Conta, o
 * navegador consulta o status do pedido de tempos em tempos (ex.: enquanto
 * o cliente paga o Pix). Quando o status muda, aparece uma mensagem; se o
 * pagamento foi aprovado, o cliente pode ser levado a uma página da loja.
 *
 * Segurança: a consulta exige o número do pedido E a chave secreta dele
 * (order key), que só o dono do pedido tem — nenhum visitante consegue ver
 * o status de pedidos de outras pessoas. Compatível com HPOS (usa só a API
 * de pedidos do WooCommerce, sem post meta).
 */
class AutoCEP_Order_Status
{
    // Status em que o pagamento está aprovado.
    const PAID = array('processing', 'completed');

    // Status finais: depois deles não há mais o que acompanhar.
    const FINAL_STATUSES = array('processing', 'completed', 'failed', 'cancelled', 'refunded');

    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 20);
        add_action('wp_ajax_autocep_order_status', array($this, 'ajax_status'));
        add_action('wp_ajax_nopriv_autocep_order_status', array($this, 'ajax_status'));
    }

    /**
     * Status que podem ter mensagem própria, com o rótulo do painel.
     */
    public static function get_statuses()
    {
        return array(
            'processing' => __('Pagamento aprovado (Processando)', 'autocep'),
            'completed'  => __('Pedido concluído', 'autocep'),
            'on-hold'    => __('Aguardando confirmação (Em espera)', 'autocep'),
            'failed'     => __('Pagamento falhou', 'autocep'),
            'cancelled'  => __('Pedido cancelado', 'autocep'),
            'refunded'   => __('Pedido reembolsado', 'autocep'),
        );
    }

    private static function get_config()
    {
        $options = AutoCEP_Admin::get_options();

        return isset($options['order_status']) ? $options['order_status'] : array();
    }

    /**
     * Pedido exibido na página atual, se o visitante for o dono dele.
     *
     * - Pedido recebido: precisa da chave do pedido na URL (?key=...), que o
     *   WooCommerce só entrega a quem fez o pedido.
     * - Minha Conta > Ver pedido: o pedido precisa ser do cliente logado.
     *
     * @return WC_Order|null
     */
    private static function current_order()
    {
        global $wp;

        if (function_exists('is_order_received_page') && is_order_received_page()) {
            $id    = isset($wp->query_vars['order-received']) ? absint($wp->query_vars['order-received']) : 0;
            $chave = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
            $order = $id ? wc_get_order($id) : null;

            return ($order && '' !== $chave && hash_equals($order->get_order_key(), $chave)) ? $order : null;
        }

        if (function_exists('is_view_order_page') && is_view_order_page() && is_user_logged_in()) {
            $id    = isset($wp->query_vars['view-order']) ? absint($wp->query_vars['view-order']) : 0;
            $order = $id ? wc_get_order($id) : null;

            return ($order && (int) $order->get_customer_id() === get_current_user_id()) ? $order : null;
        }

        return null;
    }

    public function enqueue_assets()
    {
        $config = self::get_config();

        if (empty($config['enabled'])) {
            return;
        }

        $order = self::current_order();

        if (!$order) {
            return;
        }

        $padroes  = AutoCEP_Admin::get_default_options();
        $padroes  = $padroes['order_status'];
        $mensagens = array();

        foreach (array_keys(self::get_statuses()) as $status) {
            $mensagens[$status] = isset($config['msg_' . $status]) ? $config['msg_' . $status] : $padroes['msg_' . $status];
        }

        $url = isset($config['redirect_url']) ? trim($config['redirect_url']) : '';

        wp_enqueue_style(
            'autocep-order-status',
            AUTOCEP_PLUGIN_URL . 'assets/css/autocep-order-status.css',
            array(),
            autocep_asset_version('assets/css/autocep-order-status.css')
        );

        wp_enqueue_script(
            'autocep-order-status',
            AUTOCEP_PLUGIN_URL . 'assets/js/autocep-order-status.js',
            array('jquery'),
            autocep_asset_version('assets/js/autocep-order-status.js'),
            true
        );

        wp_localize_script('autocep-order-status', 'autocep_os_params', array(
            'ajax_url'       => admin_url('admin-ajax.php'),
            'order_id'       => $order->get_id(),
            'order_number'   => $order->get_order_number(),
            'key'            => $order->get_order_key(),
            'status'         => $order->get_status(),
            'first_name'     => $order->get_billing_first_name(),
            'messages'       => $mensagens,
            'paid'           => self::PAID,
            'final'          => self::FINAL_STATUSES,
            'paid_on_load'   => !empty($config['paid_on_load']),
            'redirect_url'   => '' === $url ? '' : str_replace(array('{pedido}', '%7Bpedido%7D'), rawurlencode($order->get_order_number()), $url),
            'redirect_delay' => isset($config['redirect_delay']) ? (int) $config['redirect_delay'] : 4,
            'interval'       => 5000,
            'max_minutes'    => 15,
            'i18n'           => array(
                'ok'           => __('OK', 'autocep'),
                'go_now'       => __('Continuar agora', 'autocep'),
                'redirecting'  => __('Redirecionando em %d s...', 'autocep'),
                'close'        => __('Fechar', 'autocep'),
            ),
        ));
    }

    /**
     * Status atual de um pedido — só para quem tem a chave do pedido.
     */
    public function ajax_status()
    {
        $config = self::get_config();
        $id     = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $chave  = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';

        if (empty($config['enabled']) || !$id || '' === $chave) {
            wp_send_json_error(null, 400);
        }

        // Sem nonce de propósito: a página de pedido recebido costuma ficar em
        // cache (um nonce guardado expiraria). A autorização é a chave secreta
        // do pedido (order key), que só o dono tem; a resposta é só leitura e
        // o limite por IP evita tentativas de adivinhar chaves.
        if (!AutoCEP_Api::throttle('order_status', 60)) {
            wp_send_json_error(null, 429);
        }

        $order = wc_get_order($id);

        // Mesma resposta para "não existe" e "chave errada": não revela pedidos.
        if (!$order || !hash_equals($order->get_order_key(), $chave)) {
            wp_send_json_error(null, 403);
        }

        wp_send_json_success(array('status' => $order->get_status()));
    }
}
