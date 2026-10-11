<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Forma de pagamento "Pagamento na Entrega — AutoCEP".
 *
 * Feita para delivery no Brasil: o cliente escolhe COMO vai pagar ao receber
 * (dinheiro, Pix, débito, crédito, vale-refeição/alimentação) e, no dinheiro,
 * informa o troco. A loja escolhe as formas aceitas e para quais fretes o
 * pagamento na entrega aparece (ex.: só Motoboy e Retirada).
 *
 * A escolha fica salva no pedido (compatível com HPOS) e aparece na página de
 * pedido recebido, em Minha Conta, nos e-mails e na tela do pedido no admin.
 */
class AutoCEP_Gateway_Delivery extends WC_Payment_Gateway
{
    const META_FORMA = '_autocep_entrega_forma';
    const META_TROCO = '_autocep_entrega_troco';

    public $formas;
    public $perguntar_troco;
    public $fretes;
    public $status_pedido;
    public $instrucoes;

    public function __construct()
    {
        $this->id                 = 'autocep_entrega';
        $this->icon               = '';
        $this->has_fields         = true;
        $this->method_title       = __('Pagamento na Entrega — AutoCEP', 'autocep');
        $this->method_description = __('O cliente escolhe como vai pagar ao receber o pedido (dinheiro com troco, Pix, cartão de débito/crédito na maquininha ou vale-refeição) — ideal para delivery e retirada.', 'autocep');
        $this->supports           = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->title           = $this->get_option('title');
        $this->description     = $this->get_option('description');
        $this->formas          = (array) $this->get_option('payment_options', array());
        $this->perguntar_troco = 'yes' === $this->get_option('ask_change');
        $this->fretes          = (array) $this->get_option('enable_for_methods', array());
        $this->status_pedido   = $this->get_option('order_status', 'processing');
        $this->instrucoes      = $this->get_option('instructions');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }

    /**
     * Formas de pagar na entrega que a loja pode oferecer.
     */
    public static function get_payment_options()
    {
        return array(
            'dinheiro' => __('Dinheiro', 'autocep'),
            'pix'      => __('Pix na entrega', 'autocep'),
            'debito'   => __('Cartão de débito (maquininha)', 'autocep'),
            'credito'  => __('Cartão de crédito (maquininha)', 'autocep'),
            'vale'     => __('Vale-refeição / alimentação', 'autocep'),
        );
    }

    /**
     * Fretes que podem liberar o pagamento na entrega (tipos de método).
     */
    private function get_shipping_choices()
    {
        $opcoes = array();

        if (function_exists('WC') && WC()->shipping()) {
            foreach (WC()->shipping()->get_shipping_methods() as $id => $metodo) {
                $opcoes[$id] = $metodo->get_method_title();
            }
        }

        // Frete por Km do AutoCEP não é um método de zona: entra à parte.
        $opcoes['autocep_km_delivery'] = __('Entrega Local / Motoboy (Frete por Km — AutoCEP)', 'autocep');

        return $opcoes;
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Ativar/Desativar', 'autocep'),
                'type'    => 'checkbox',
                'label'   => __('Ativar Pagamento na Entrega', 'autocep'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Título', 'autocep'),
                'type'        => 'text',
                'default'     => __('Pagar na entrega', 'autocep'),
                'desc_tip'    => true,
                'description' => __('Nome que o cliente vê no checkout.', 'autocep'),
            ),
            'description' => array(
                'title'   => __('Descrição', 'autocep'),
                'type'    => 'textarea',
                'default' => __('Pague ao receber o pedido. Escolha abaixo como vai pagar.', 'autocep'),
            ),
            'payment_options' => array(
                'title'       => __('Formas aceitas', 'autocep'),
                'type'        => 'multiselect',
                'class'       => 'wc-enhanced-select',
                'options'     => self::get_payment_options(),
                'default'     => array('dinheiro', 'pix', 'debito', 'credito'),
                'description' => __('O cliente escolhe uma delas no checkout.', 'autocep'),
            ),
            'ask_change' => array(
                'title'   => __('Troco', 'autocep'),
                'type'    => 'checkbox',
                'label'   => __('No dinheiro, perguntar "Troco para quanto?"', 'autocep'),
                'default' => 'yes',
            ),
            'enable_for_methods' => array(
                'title'             => __('Somente para estes fretes', 'autocep'),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 400px;',
                'default'           => array(),
                'options'           => $this->get_shipping_choices(),
                'description'       => __('Ex.: Motoboy e Retirada em Loja. Deixe vazio para aceitar com qualquer frete. Correios e transportadoras normalmente não cobram na entrega.', 'autocep'),
                'custom_attributes' => array('data-placeholder' => __('Qualquer frete', 'autocep')),
            ),
            'order_status' => array(
                'title'   => __('Status do pedido', 'autocep'),
                'type'    => 'select',
                'default' => 'processing',
                'options' => array(
                    'processing' => __('Processando (vai para a separação/entrega)', 'autocep'),
                    'on-hold'    => __('Aguardando (confirmar manualmente antes de separar)', 'autocep'),
                ),
            ),
            'instructions' => array(
                'title'       => __('Instruções', 'autocep'),
                'type'        => 'textarea',
                'default'     => __('O pagamento será feito na entrega, com a forma escolhida.', 'autocep'),
                'description' => __('Mostradas na página de pedido recebido e nos e-mails.', 'autocep'),
            ),
        );
    }

    /**
     * Só aparece se houver formas aceitas e, se a loja restringiu os fretes,
     * se o frete escolhido estiver na lista.
     */
    public function is_available()
    {
        if (!parent::is_available() || empty($this->formas)) {
            return false;
        }

        if (empty($this->fretes) || !function_exists('WC') || !WC()->session) {
            return true;
        }

        // No "Pagar pedido" (order-pay) o frete vem do próprio pedido.
        $escolhidos = array();
        $pedido_id  = absint(get_query_var('order-pay'));

        if (is_wc_endpoint_url('order-pay') && $pedido_id && ($pedido = wc_get_order($pedido_id))) {
            foreach ($pedido->get_shipping_methods() as $item) {
                $escolhidos[] = $item->get_method_id();
            }
        } else {
            foreach ((array) WC()->session->get('chosen_shipping_methods', array()) as $rate_id) {
                // "flat_rate:3" → "flat_rate"; "autocep_store_pickup:5:loja" → "autocep_store_pickup".
                $escolhidos[] = current(explode(':', (string) $rate_id));
            }
        }

        if (empty($escolhidos)) {
            return false;
        }

        foreach ($escolhidos as $metodo) {
            if (!in_array($metodo, $this->fretes, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Opções no checkout: forma de pagamento + troco (só no dinheiro).
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        $opcoes = array_intersect_key(self::get_payment_options(), array_flip($this->formas));
        $total  = function_exists('WC') && WC()->cart ? (float) WC()->cart->get_total('edit') : 0;
        ?>
        <fieldset class="autocep-entrega" data-total="<?php echo esc_attr($total); ?>">
            <legend class="screen-reader-text"><?php esc_html_e('Como você vai pagar na entrega?', 'autocep'); ?></legend>
            <div class="autocep-entrega__opcoes" role="radiogroup">
                <?php foreach ($opcoes as $valor => $rotulo) : ?>
                    <label class="autocep-entrega__opcao">
                        <input type="radio" name="autocep_entrega_forma" value="<?php echo esc_attr($valor); ?>" <?php checked(1 === count($opcoes)); ?> />
                        <span><?php echo esc_html($rotulo); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if ($this->perguntar_troco && isset($opcoes['dinheiro'])) : ?>
                <div class="autocep-entrega__troco" hidden>
                    <label for="autocep_entrega_troco"><?php esc_html_e('Troco para quanto?', 'autocep'); ?></label>
                    <input type="text" inputmode="decimal" id="autocep_entrega_troco" name="autocep_entrega_troco" placeholder="<?php esc_attr_e('Ex.: 100,00', 'autocep'); ?>" autocomplete="off" />
                    <label class="autocep-entrega__sem-troco">
                        <input type="checkbox" name="autocep_entrega_sem_troco" value="1" />
                        <?php esc_html_e('Não preciso de troco', 'autocep'); ?>
                    </label>
                </div>
            <?php endif; ?>
        </fieldset>
        <?php
    }

    /**
     * Valor em reais digitado pelo cliente ("100", "100,50", "R$ 1.000,00").
     */
    public static function parse_money($texto)
    {
        $texto = preg_replace('/[^\d,\.]/', '', (string) $texto);

        if ('' === $texto) {
            return null;
        }

        if (false !== strpos($texto, ',')) {
            $texto = str_replace(array('.', ','), array('', '.'), $texto);
        } elseif (substr_count($texto, '.') > 1 || preg_match('/\.\d{3}$/', $texto)) {
            // "1.000" ou "1.000.000": ponto como separador de milhar.
            $texto = str_replace('.', '', $texto);
        }

        return is_numeric($texto) ? round((float) $texto, 2) : null;
    }

    public function validate_fields()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- o checkout do WooCommerce já verificou o nonce.
        $forma = isset($_POST['autocep_entrega_forma']) ? sanitize_key(wp_unslash($_POST['autocep_entrega_forma'])) : '';

        if (!in_array($forma, $this->formas, true)) {
            wc_add_notice(__('Escolha como você vai pagar na entrega.', 'autocep'), 'error');
            return false;
        }

        if ('dinheiro' === $forma && $this->perguntar_troco && empty($_POST['autocep_entrega_sem_troco'])) {
            $troco = self::parse_money(isset($_POST['autocep_entrega_troco']) ? wp_unslash($_POST['autocep_entrega_troco']) : '');
            $total = WC()->cart ? (float) WC()->cart->get_total('edit') : 0;

            if (null === $troco) {
                wc_add_notice(__('Informe para quanto você precisa de troco ou marque "Não preciso de troco".', 'autocep'), 'error');
                return false;
            }

            if ($troco < $total) {
                wc_add_notice(sprintf(__('O valor para troco (%1$s) é menor que o total do pedido (%2$s).', 'autocep'), wc_price($troco), wc_price($total)), 'error');
                return false;
            }
        }
        // phpcs:enable

        return true;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- o checkout do WooCommerce já verificou o nonce.
        $forma = isset($_POST['autocep_entrega_forma']) ? sanitize_key(wp_unslash($_POST['autocep_entrega_forma'])) : '';
        $troco = null;

        if ('dinheiro' === $forma && $this->perguntar_troco && empty($_POST['autocep_entrega_sem_troco'])) {
            $troco = self::parse_money(isset($_POST['autocep_entrega_troco']) ? wp_unslash($_POST['autocep_entrega_troco']) : '');
        }
        // phpcs:enable

        $opcoes = self::get_payment_options();
        $order->update_meta_data(self::META_FORMA, $forma);

        if (null !== $troco) {
            $order->update_meta_data(self::META_TROCO, $troco);
        }

        $order->save();

        $nota = sprintf(__('Pagamento na entrega: %s.', 'autocep'), isset($opcoes[$forma]) ? $opcoes[$forma] : $forma);

        if (null !== $troco) {
            $nota .= ' ' . sprintf(__('Levar troco para %1$s (troco de %2$s).', 'autocep'), wp_strip_all_tags(wc_price($troco)), wp_strip_all_tags(wc_price($troco - (float) $order->get_total())));
        } elseif ('dinheiro' === $forma) {
            $nota .= ' ' . __('Cliente não precisa de troco.', 'autocep');
        }

        if ('on-hold' === $this->status_pedido) {
            $order->update_status('on-hold', $nota);
        } else {
            $order->add_order_note($nota);
            // Igual ao "Pagamento na entrega" do WooCommerce: o pedido segue
            // para separação; o pagamento é registrado ao entregar.
            $order->update_status('processing');
        }

        wc_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Linha "Pagamento na entrega" com a forma e o troco — usada na página
     * de pedido recebido, em Minha Conta e nos e-mails.
     */
    public static function describe_order($order)
    {
        $forma = $order->get_meta(self::META_FORMA);

        if (!$forma) {
            return '';
        }

        $opcoes = self::get_payment_options();
        $texto  = isset($opcoes[$forma]) ? $opcoes[$forma] : $forma;
        $troco  = $order->get_meta(self::META_TROCO);

        if ('' !== $troco && null !== $troco) {
            $texto .= ' — ' . sprintf(__('troco para %1$s (levar %2$s de troco)', 'autocep'), wp_strip_all_tags(wc_price($troco)), wp_strip_all_tags(wc_price((float) $troco - (float) $order->get_total())));
        } elseif ('dinheiro' === $forma) {
            $texto .= ' — ' . __('sem troco', 'autocep');
        }

        return $texto;
    }

    /**
     * Instruções na página de pedido recebido.
     */
    public function thankyou_page()
    {
        if ($this->instrucoes) {
            echo wp_kses_post(wpautop(wptexturize($this->instrucoes)));
        }
    }

    /**
     * Instruções nos e-mails do cliente.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($sent_to_admin || $this->id !== $order->get_payment_method() || !$this->instrucoes) {
            return;
        }

        echo $plain_text ? esc_html(wptexturize($this->instrucoes)) . PHP_EOL : wp_kses_post(wpautop(wptexturize($this->instrucoes)));
    }
}
