<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Shipping
{
    const CACHE_PREFIX = 'autocep_ship_';

    private $logger;

    public function __construct(AutoCEP_Logger $logger)
    {
        $this->logger = $logger;

        add_action('wp_ajax_autocep_calc_shipping_product', array($this, 'ajax_calc_shipping_product'));
        add_action('wp_ajax_nopriv_autocep_calc_shipping_product', array($this, 'ajax_calc_shipping_product'));

        add_action('wp_ajax_autocep_calc_shipping_cart', array($this, 'ajax_calc_shipping_cart'));
        add_action('wp_ajax_nopriv_autocep_calc_shipping_cart', array($this, 'ajax_calc_shipping_cart'));

        add_action('wp_ajax_autocep_calc_shipping_checkout', array($this, 'ajax_calc_shipping_checkout'));
        add_action('wp_ajax_nopriv_autocep_calc_shipping_checkout', array($this, 'ajax_calc_shipping_checkout'));

        add_action('wp_ajax_autocep_select_shipping_rate', array($this, 'ajax_select_shipping_rate'));
        add_action('wp_ajax_nopriv_autocep_select_shipping_rate', array($this, 'ajax_select_shipping_rate'));

        add_action('wp_ajax_autocep_clear_selected_rate', array($this, 'ajax_clear_selected_rate'));
        add_action('wp_ajax_nopriv_autocep_clear_selected_rate', array($this, 'ajax_clear_selected_rate'));

        add_action('wp_ajax_autocep_update_shipping_cep', array($this, 'ajax_update_shipping_cep'));
        add_action('wp_ajax_nopriv_autocep_update_shipping_cep', array($this, 'ajax_update_shipping_cep'));

        add_action('init', array($this, 'register_display_hooks'));

        add_filter('woocommerce_package_rates', array($this, 'inject_autonomous_rates'), 20, 2);

        // Estado sempre coerente com o CEP (zona de entrega e "Entrega para").
        add_filter('woocommerce_cart_shipping_packages', array($this, 'fix_package_state'), 20);
        add_filter('woocommerce_cart_calculate_shipping_address', array($this, 'fix_calculator_address'), 20);

        add_shortcode('shipping_calculator_on_product_page', array($this, 'render_shipping_calculator_shortcode'));
    }

    public function inject_autonomous_rates($rates, $package)
    {
        $cep_destino = isset($package['destination']['postcode'])
            ? preg_replace('/\D/', '', $package['destination']['postcode'])
            : '';

        if (empty($cep_destino)) {
            return $rates;
        }

        if (class_exists('AutoCEP_Km_Delivery')) {
            $id_km = 'autocep_km_delivery';

            if (!isset($rates[$id_km])) {
                $taxa_km = AutoCEP_Km_Delivery::calculate($cep_destino);

                if ($taxa_km !== null) {
                    $rates[$id_km] = new WC_Shipping_Rate(
                        $id_km,
                        $taxa_km['label'],
                        $taxa_km['cost'],
                        array(),
                        'autocep_km_delivery'
                    );
                }
            }
        }

        if (class_exists('AutoCEP_Store_Pickup')) {
            $lojas_proximas = AutoCEP_Store_Pickup::get_nearby_stores($cep_destino);

            foreach ($lojas_proximas as $loja) {
                $rate_id = 'autocep_store_pickup_fallback_' . $loja['id'];

                $ja_tem = false;
                foreach ($rates as $rate) {
                    if (strpos($rate->get_id(), 'autocep_store_pickup') === 0) {
                        $ja_tem = true;
                        break;
                    }
                }
                if ($ja_tem) {
                    break;
                }

                if (!isset($rates[$rate_id])) {
                    $gratis = empty($loja['pickup_fee']) || (float) $loja['pickup_fee'] <= 0;

                    if (null === $loja['distance_km']) {
                        $label = $gratis
                            ? sprintf(__('Retire Grátis na %s', 'autocep'), $loja['name'])
                            : sprintf(__('Retire na %s', 'autocep'), $loja['name']);
                    } else {
                        $dist = number_format((float) $loja['distance_km'], 1, ',', '.');
                        $label = $gratis
                            ? sprintf(__('Retire Grátis na %1$s (a %2$s km de você)', 'autocep'), $loja['name'], $dist)
                            : sprintf(__('Retire na %1$s (a %2$s km de você)', 'autocep'), $loja['name'], $dist);
                    }

                    $rates[$rate_id] = new WC_Shipping_Rate(
                        $rate_id,
                        $label,
                        (float) $loja['pickup_fee'],
                        array(),
                        'autocep_store_pickup'
                    );
                }
            }
        }

        return $rates;
    }

    public function register_display_hooks()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $options = AutoCEP_Admin::get_options();

        $this->sync_third_party_product_calculators(!empty($options['product_shipping']['enabled']));

        if (!empty($options['product_shipping']['enabled'])) {
            $hook = !empty($options['product_shipping']['hook'])
                ? $options['product_shipping']['hook']
                : 'woocommerce_after_add_to_cart_form';

            if ('shortcode' !== $hook) {
                add_action($hook, array($this, 'render_product_shipping_box'));
            }
        }

        if (!empty($options['cart_shipping']['enabled'])) {
            $hook = !empty($options['cart_shipping']['position'])
                ? $options['cart_shipping']['position']
                : 'woocommerce_before_cart_totals';

            add_action($hook, array($this, 'render_cart_shipping_box'));
        }

        if (!empty($options['checkout_shipping']['enabled'])) {
            $hook = !empty($options['checkout_shipping']['position'])
                ? $options['checkout_shipping']['position']
                : 'woocommerce_before_order_notes';

            add_action($hook, array($this, 'render_checkout_shipping_box'));
        }
    }

    public function render_shipping_calculator_shortcode()
    {
        if (!function_exists('is_product') || !is_product()) {
            return '';
        }

        $options = AutoCEP_Admin::get_options();

        if (empty($options['product_shipping']['enabled'])) {
            return '';
        }

        ob_start();
        $this->render_product_shipping_box();

        return ob_get_clean();
    }

    private function sync_third_party_product_calculators($autocep_habilitado)
    {
        if (defined('MELHORENVIO_ASSETS') || class_exists('Melhor_Envio_Plugin')) {
            $opcao = 'melhorenvio_hide_calculator_product';

            if ($autocep_habilitado) {
                if ('1' !== get_option($opcao)) {
                    update_option($opcao, '1');
                }
            } elseif (false !== get_option($opcao)) {
                delete_option($opcao);
            }
        }
    }

    public function render_product_shipping_box()
    {
        global $product;

        $produto_atual = $product instanceof WC_Product ? $product : null;

        if (!$produto_atual && function_exists('get_the_ID')) {
            $produto_atual = wc_get_product(get_the_ID());
        }

        if (!$produto_atual instanceof WC_Product) {
            return;
        }

        $options = AutoCEP_Admin::get_options();
        $titulo  = !empty($options['product_shipping']['title'])
            ? $options['product_shipping']['title']
            : __('Calcular Frete e Prazo', 'autocep');

        $barra_frete_gratis = $this->render_free_shipping_bar_html((float) $produto_atual->get_price());

        ?>
        <div class="autocep-shipping-box autocep-shipping-box--product" data-product-id="<?php echo esc_attr($produto_atual->get_id()); ?>">
            <p class="autocep-shipping-title"><strong><?php echo esc_html($titulo); ?></strong></p>
            <?php if ($barra_frete_gratis) : ?>
                <div class="autocep-free-shipping-bar-wrap"><?php echo $barra_frete_gratis; ?></div>
            <?php endif; ?>
            <div class="autocep-shipping-form">
                <input
                    type="text"
                    class="autocep-shipping-cep-input"
                    placeholder="<?php esc_attr_e('Digite seu CEP', 'autocep'); ?>"
                    maxlength="9"
                    inputmode="numeric"
                    autocomplete="postal-code"
                />
                <button type="button" class="autocep-shipping-calc-btn"><?php esc_html_e('Calcular', 'autocep'); ?></button>
            </div>
            <div class="autocep-shipping-feedback" aria-live="polite"></div>
            <div class="autocep-shipping-results"></div>
            <p class="autocep-shipping-cep-link">
                <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Não sei meu CEP', 'autocep'); ?></a>
            </p>
        </div>
        <?php
    }

    public function render_cart_shipping_box()
    {
        $options = AutoCEP_Admin::get_options();
        $titulo  = !empty($options['cart_shipping']['title'])
            ? $options['cart_shipping']['title']
            : __('Simular Frete', 'autocep');

        $this->print_cart_or_checkout_box('cart', $titulo);
    }

    public function render_checkout_shipping_box()
    {
        $this->print_cart_or_checkout_box('checkout', __('Simular Frete', 'autocep'));
    }

    private function print_cart_or_checkout_box($contexto, $titulo)
    {
        $subtotal_atual     = (function_exists('WC') && WC()->cart) ? (float) WC()->cart->get_subtotal() : 0.0;
        $barra_frete_gratis = $this->render_free_shipping_bar_html($subtotal_atual);

        ?>
        <div class="autocep-shipping-box autocep-shipping-box--<?php echo esc_attr($contexto); ?>">
            <p class="autocep-shipping-title"><strong><?php echo esc_html($titulo); ?></strong></p>
            <?php if ($barra_frete_gratis) : ?>
                <div class="autocep-free-shipping-bar-wrap"><?php echo $barra_frete_gratis; ?></div>
            <?php endif; ?>
            <div class="autocep-shipping-form">
                <input
                    type="text"
                    class="autocep-shipping-cep-input"
                    placeholder="<?php esc_attr_e('Digite seu CEP', 'autocep'); ?>"
                    maxlength="9"
                    inputmode="numeric"
                    autocomplete="postal-code"
                />
                <button type="button" class="autocep-shipping-calc-btn"><?php esc_html_e('Calcular', 'autocep'); ?></button>
            </div>
            <div class="autocep-shipping-feedback" aria-live="polite"></div>
            <div class="autocep-shipping-results"></div>
        </div>
        <?php
    }

    private function ensure_wc_cart_session()
    {
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }
    }

    public function ajax_calc_shipping_product()
    {
        check_ajax_referer(AUTOCEP_NONCE_ACTION, 'nonce');

        $this->ensure_wc_cart_session();

        $options = AutoCEP_Admin::get_options();

        if (empty($options['product_shipping']['enabled'])) {
            wp_send_json_error(array('message' => __('Simulador de frete desabilitado.', 'autocep')));
        }

        if ('no' === get_option('woocommerce_calc_shipping')) {
            wp_send_json_error(array('message' => __('O cálculo de frete está desabilitado nesta loja.', 'autocep')));
        }

        $cep           = $this->sanitize_cep($_POST['cep'] ?? '');
        $product_id    = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id  = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $quantidade    = isset($_POST['quantity']) ? max(1, absint($_POST['quantity'])) : 1;

        if (!$cep) {
            wp_send_json_error(array('message' => __('CEP inválido.', 'autocep')));
        }

        $pais_base = WC()->countries ? WC()->countries->get_base_country() : 'BR';

        if (class_exists('WC_Validation') && !WC_Validation::is_postcode($cep, $pais_base)) {
            wp_send_json_error(array('message' => __('Por favor, insira um CEP válido.', 'autocep')));
        }

        $product = $this->resolve_requested_product($product_id, $variation_id);

        if (!$product) {
            wp_send_json_error(array('message' => __('Produto inválido.', 'autocep')));
        }

        if (!$product->needs_shipping()) {
            wp_send_json_error(array('message' => __('Não foi possível calcular a entrega deste produto.', 'autocep')));
        }

        if (!$product->is_in_stock()) {
            wp_send_json_error(array('message' => __('Não foi possível calcular a entrega deste produto, pois o mesmo não está disponível.', 'autocep')));
        }

        if (empty($options['product_shipping']['consider_quantity'])) {
            $quantidade = 1;
        }

        $nao_existe = $this->cep_not_found_message($cep);

        if ($nao_existe) {
            wp_send_json_error(array('message' => $nao_existe));
        }

        $package = $this->build_package_for_product($product, $quantidade, $cep);
        $rates   = $this->calculate_rates($package);

        if (is_wp_error($rates)) {
            $this->logger->log('shipping-product', $cep, $rates->get_error_message(), 'error');
            wp_send_json_error(array('message' => $rates->get_error_message()));
        }

        if (empty($rates)) {
            wp_send_json_error(array('message' => $this->resolve_empty_rates_message($cep)));
        }

        $this->apply_customer_location($cep, false);

        $subtotal_produto = (float) $product->get_price() * $quantidade;

        $this->record_shipping_stats('product', $cep, $package, $rates);

        wp_send_json_success(array(
            'rates'                   => $rates,
            'free_shipping_bar_html'  => $this->render_free_shipping_bar_html($subtotal_produto, $package, true),
        ));
    }

    private function resolve_requested_product($product_id, $variation_id)
    {
        if ($variation_id > 0) {
            $variacao = wc_get_product($variation_id);

            if ($variacao instanceof WC_Product_Variation && (int) $variacao->get_parent_id() === $product_id) {
                return $variacao;
            }

            return false;
        }

        $product = wc_get_product($product_id);

        return $product instanceof WC_Product ? $product : false;
    }

    public function ajax_calc_shipping_checkout()
    {
        $this->handle_cart_based_shipping_ajax('checkout_shipping', 'shipping-checkout');
    }

    public function ajax_calc_shipping_cart()
    {
        $this->handle_cart_based_shipping_ajax('cart_shipping', 'shipping-cart');
    }

    private function handle_cart_based_shipping_ajax($chave_opcao, $rotulo_log)
    {
        check_ajax_referer(AUTOCEP_NONCE_ACTION, 'nonce');

        $this->ensure_wc_cart_session();

        $options = AutoCEP_Admin::get_options();

        if (empty($options[$chave_opcao]['enabled'])) {
            wp_send_json_error(array('message' => __('Simulador de frete desabilitado.', 'autocep')));
        }

        $cep = $this->sanitize_cep($_POST['cep'] ?? '');

        if (!$cep) {
            wp_send_json_error(array('message' => __('CEP inválido.', 'autocep')));
        }

        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            wp_send_json_error(array('message' => __('Seu carrinho está vazio.', 'autocep')));
        }

        $nao_existe = $this->cep_not_found_message($cep);

        if ($nao_existe) {
            wp_send_json_error(array('message' => $nao_existe));
        }

        $package = $this->build_package_for_cart($cep);
        $rates   = $this->calculate_rates($package);

        if (is_wp_error($rates)) {
            $this->logger->log($rotulo_log, $cep, $rates->get_error_message(), 'error');
            wp_send_json_error(array('message' => $rates->get_error_message()));
        }

        if (empty($rates)) {
            wp_send_json_error(array('message' => $this->resolve_empty_rates_message($cep)));
        }

        $subtotal_carrinho = (function_exists('WC') && WC()->cart) ? (float) WC()->cart->get_subtotal() : 0.0;

        $contexto_stats = 'checkout_shipping' === $chave_opcao ? 'checkout' : 'cart';
        $this->record_shipping_stats($contexto_stats, $cep, $package, $rates);

        wp_send_json_success(array(
            'rates'                  => $rates,
            'free_shipping_bar_html' => $this->render_free_shipping_bar_html($subtotal_carrinho, $package, true),
        ));
    }

    public function ajax_clear_selected_rate()
    {
        check_ajax_referer(AUTOCEP_NONCE_ACTION, 'nonce');

        $this->ensure_wc_cart_session();

        if (function_exists('WC') && WC()->session) {
            WC()->session->set('chosen_shipping_methods', array());

            if (WC()->cart) {
                WC()->cart->calculate_shipping();
                WC()->cart->calculate_totals();
            }
        }

        wp_send_json_success();
    }

    public function ajax_update_shipping_cep()
    {
        check_ajax_referer(AUTOCEP_NONCE_ACTION, 'nonce');

        $this->ensure_wc_cart_session();

        $cep = $this->sanitize_cep($_POST['cep'] ?? '');

        if ($cep) {
            $this->apply_customer_location($cep);
        }

        wp_send_json_success();
    }

    public function ajax_select_shipping_rate()
    {
        check_ajax_referer(AUTOCEP_NONCE_ACTION, 'nonce');

        $this->ensure_wc_cart_session();

        $contexto    = (isset($_POST['context']) && 'checkout' === $_POST['context']) ? 'checkout' : 'cart';
        $chave_opcao = 'checkout' === $contexto ? 'checkout_shipping' : 'cart_shipping';

        $options = AutoCEP_Admin::get_options();

        if (empty($options[$chave_opcao]['enabled'])) {
            wp_send_json_error(array('message' => __('Simulador de frete desabilitado.', 'autocep')));
        }

        $cep     = $this->sanitize_cep($_POST['cep'] ?? '');
        $rate_id = isset($_POST['rate_id']) ? sanitize_text_field(wp_unslash($_POST['rate_id'])) : '';

        if (!$cep || !$rate_id) {
            wp_send_json_error(array('message' => __('Não foi possível selecionar este frete.', 'autocep')));
        }

        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            wp_send_json_error(array('message' => __('Seu carrinho está vazio.', 'autocep')));
        }

        $package = $this->build_package_for_cart($cep);
        $rates   = $this->calculate_rates($package);

        if (is_wp_error($rates) || empty($rates)) {
            wp_send_json_error(array('message' => __('Este frete não está mais disponível para o CEP informado.', 'autocep')));
        }

        $rate_valida = false;

        foreach ($rates as $rate) {
            if ($rate['id'] === $rate_id) {
                $rate_valida = true;
                break;
            }
        }

        if (!$rate_valida) {
            wp_send_json_error(array('message' => __('Este frete não está mais disponível para o CEP informado.', 'autocep')));
        }

        $this->apply_customer_location($cep);

        $escolhidos    = WC()->session->get('chosen_shipping_methods', array());
        $escolhidos    = is_array($escolhidos) ? $escolhidos : array();
        $escolhidos[0] = $rate_id;
        WC()->session->set('chosen_shipping_methods', $escolhidos);

        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        wp_send_json_success(array(
            'message' => __('Frete selecionado com sucesso.', 'autocep'),
            'context' => $contexto,
        ));
    }

    private function resolve_destination_from_cep($cep)
    {
        $endereco = AutoCEP_Api::find_address($cep, $this->logger);

        if (!is_wp_error($endereco) && !empty($endereco['uf'])) {
            return array(
                'country'   => 'BR',
                'state'     => $endereco['uf'],
                'postcode'  => $cep,
                'city'      => $endereco['localidade'],
                'address'   => '',
                'address_2' => '',
            );
        }

        // Sem resposta das APIs: o estado vem das faixas de CEP dos Correios.
        // Antes era usado o estado guardado na sessão, que podia ser de outra
        // busca (ex.: CEP do Piauí calculado como Paraíba).
        if ($this->logger) {
            $this->logger->log('shipping-destination', $cep, __('Não foi possível consultar o endereço do CEP; o estado foi definido pela faixa de CEP dos Correios.', 'autocep'), 'warning');
        }

        return array(
            'country'   => 'BR',
            'state'     => AutoCEP_Api::uf_from_cep($cep),
            'postcode'  => $cep,
            'city'      => '',
            'address'   => '',
            'address_2' => '',
        );
    }

    /**
     * Mensagem de "CEP não encontrado" quando os Correios confirmam que o CEP
     * não existe — nesse caso não faz sentido calcular frete. Retorna '' se
     * o CEP existe ou se não foi possível consultar (APIs fora do ar).
     */
    private function cep_not_found_message($cep)
    {
        $endereco = AutoCEP_Api::find_address($cep, $this->logger);

        if (is_wp_error($endereco) && 'autocep_not_found' === $endereco->get_error_code()) {
            $options = AutoCEP_Admin::get_options();

            return $options['messages']['not_found'];
        }

        return '';
    }

    /**
     * Grava o endereço do CEP simulado no cliente — CEP, estado e cidade
     * juntos. Gravar só o CEP deixava o estado e a cidade da busca
     * anterior (ex.: "Entrega para Paraíba, 64000001").
     */
    private function apply_customer_location($cep, $salvar = true)
    {
        if (!function_exists('WC') || !WC()->customer) {
            return;
        }

        $destino = $this->resolve_destination_from_cep($cep);

        foreach (array('shipping', 'billing') as $tipo) {
            WC()->customer->{'set_' . $tipo . '_country'}('BR');
            WC()->customer->{'set_' . $tipo . '_postcode'}($cep);
            WC()->customer->{'set_' . $tipo . '_state'}($destino['state']);
            WC()->customer->{'set_' . $tipo . '_city'}($destino['city']);
        }

        if ($salvar) {
            WC()->customer->save();
        }
    }

    /**
     * Garantia final para todo cálculo de frete (carrinho, checkout,
     * simulador): no Brasil, o estado do pacote é sempre o do CEP. Assim a
     * Zona de Entrega certa é escolhida mesmo se o estado da sessão ou do
     * formulário estiver desatualizado.
     */
    public function fix_package_state($pacotes)
    {
        foreach ($pacotes as $indice => $pacote) {
            if (empty($pacote['destination']) || 'BR' !== (isset($pacote['destination']['country']) ? $pacote['destination']['country'] : '')) {
                continue;
            }

            $uf = AutoCEP_Api::uf_from_cep(isset($pacote['destination']['postcode']) ? $pacote['destination']['postcode'] : '');

            if ($uf && $uf !== $pacote['destination']['state']) {
                $pacotes[$indice]['destination']['state'] = $uf;
            }
        }

        return $pacotes;
    }

    /**
     * Calculadora de frete nativa do carrinho ("Mudar endereço"): o cliente
     * digita só o CEP e o estado que estava selecionado (de outra busca)
     * era mantido. Aqui o estado e a cidade passam a vir do CEP.
     */
    public function fix_calculator_address($endereco)
    {
        if ('BR' !== (isset($endereco['country']) ? $endereco['country'] : '') || empty($endereco['postcode'])) {
            return $endereco;
        }

        $cep   = preg_replace('/\D/', '', $endereco['postcode']);
        $dados = AutoCEP_Api::find_address($cep, $this->logger);

        if (!is_wp_error($dados) && !empty($dados['uf'])) {
            $endereco['state'] = $dados['uf'];
            $endereco['city']  = $dados['localidade'];
        } elseif ($uf = AutoCEP_Api::uf_from_cep($cep)) {
            if ($uf !== (isset($endereco['state']) ? $endereco['state'] : '')) {
                $endereco['city'] = '';
            }

            $endereco['state'] = $uf;
        }

        return $endereco;
    }

    private function build_package_for_product(WC_Product $product, $quantidade, $cep)
    {
        $preco_sem_imposto = (float) $product->get_price_excluding_tax($quantidade);
        $preco_com_imposto = (float) $product->get_price_including_tax($quantidade);
        $imposto           = $preco_com_imposto - $preco_sem_imposto;

        $cart_id = (function_exists('WC') && WC()->cart)
            ? WC()->cart->generate_cart_id($product->get_id(), $product->is_type('variation') ? $product->get_id() : 0)
            : 'autocep_item';

        $cupons_aplicados = (function_exists('WC') && WC()->cart) ? WC()->cart->get_applied_coupons() : array();

        $item_pacote = array(
            'product_id'        => $product->get_id(),
            'data'              => $product,
            'quantity'          => $quantidade,
            'line_total'        => $preco_sem_imposto,
            'line_tax'          => $imposto,
            'line_subtotal'     => $preco_sem_imposto,
            'line_subtotal_tax' => $imposto,
        );

        $item_pacote['formatted_data'] = $this->get_melhor_envio_formatted_product_data($product, $quantidade);

        return array(
            'contents'      => array(
                $cart_id => $item_pacote,
            ),
            'contents_cost'    => $preco_sem_imposto,
            'applied_coupons'  => $cupons_aplicados,
            'user'             => array('ID' => get_current_user_id()),
            'destination'      => $this->resolve_destination_from_cep($cep),
            'package_id'       => 0,
            'product_page_calculation' => true,
        );
    }

    private function get_melhor_envio_formatted_product_data(WC_Product $product, $quantidade)
    {
        if (!class_exists('\MelhorEnvio\Factory\ProductServiceFactory')) {
            return null;
        }

        try {
            $servico_produto = \MelhorEnvio\Factory\ProductServiceFactory::fromId($product->get_id());

            if (!$servico_produto || !method_exists($servico_produto, 'getProduct')) {
                return null;
            }

            return $servico_produto->getProduct($product->get_id(), $quantidade);
        } catch (\Throwable $erro) {
            $this->logger->log('shipping-product', '', 'Falha ao montar dados do produto para o Melhor Envio: ' . $erro->getMessage(), 'warning');

            return null;
        }
    }

    private function build_package_for_cart($cep)
    {
        $cart_package = WC()->cart->get_shipping_packages();
        $package      = !empty($cart_package[0]) ? $cart_package[0] : array();

        $package['destination'] = $this->resolve_destination_from_cep($cep);

        if (!isset($package['package_id'])) {
            $package['package_id'] = 0;
        }

        if (!empty($package['contents']) && class_exists('\MelhorEnvio\Factory\ProductServiceFactory')) {
            foreach ($package['contents'] as $chave => $item) {
                if (empty($item['data']) || !($item['data'] instanceof WC_Product)) {
                    continue;
                }

                $quantidade = isset($item['quantity']) ? (int) $item['quantity'] : 1;

                $package['contents'][$chave]['formatted_data'] = $this->get_melhor_envio_formatted_product_data($item['data'], $quantidade);
            }
        }

        return $package;
    }

    private function calculate_rates($package)
    {
        $options    = AutoCEP_Admin::get_options();
        $horas_cache = isset($options['general']['shipping_cache_hours']) ? (int) $options['general']['shipping_cache_hours'] : 24;

        if ($horas_cache <= 0) {
            return $this->calculate_rates_uncached($package);
        }

        $cache_key = $this->build_shipping_cache_key($package);
        $em_cache  = get_transient($cache_key);

        if (false !== $em_cache) {
            return $em_cache;
        }

        $resultado = $this->calculate_rates_uncached($package);

        if (!is_wp_error($resultado) && !empty($resultado)) {
            set_transient($cache_key, $resultado, HOUR_IN_SECONDS * $horas_cache);
        }

        return $resultado;
    }

    private function build_shipping_cache_key($package)
    {
        $itens = array();

        if (!empty($package['contents'])) {
            foreach ($package['contents'] as $item) {
                $produto_id = (!empty($item['data']) && $item['data'] instanceof WC_Product)
                    ? $item['data']->get_id()
                    : 0;

                $itens[] = $produto_id . ':' . (isset($item['quantity']) ? (int) $item['quantity'] : 1);
            }
        }

        sort($itens);

        $assinatura = array(
            'postcode' => isset($package['destination']['postcode']) ? $package['destination']['postcode'] : '',
            'state'    => isset($package['destination']['state']) ? $package['destination']['state'] : '',
            'items'    => $itens,
            'coupons'  => !empty($package['applied_coupons']) ? $package['applied_coupons'] : array(),
            // Muda sempre que um método de entrega é salvo (ex.: a Tabela de
            // Frete), para o simulador não exibir valores antigos do cache.
            'version'  => class_exists('WC_Cache_Helper') ? WC_Cache_Helper::get_transient_version('shipping') : '',
        );

        return self::CACHE_PREFIX . md5(wp_json_encode($assinatura));
    }

    private function calculate_rates_uncached($package)
    {
        if (!class_exists('WC_Shipping')) {
            return new WP_Error('autocep_no_wc', __('WooCommerce não está disponível.', 'autocep'));
        }

        if (function_exists('set_time_limit') && !ini_get('safe_mode')) {
            @set_time_limit(45);
        }

        try {
            $resultado = WC_Shipping::instance()->calculate_shipping_for_package($package);
        } catch (\Throwable $erro) {
            $this->logger->log('shipping-methods', $package['destination']['postcode'] ?? '', $erro->getMessage(), 'error');

            return new WP_Error('autocep_shipping_exception', __('Uma das transportadoras configuradas não respondeu corretamente. Tente novamente em instantes.', 'autocep'));
        }

        if (empty($resultado['rates'])) {
            $this->logger->log('shipping-methods', $package['destination']['postcode'] ?? '', __('Nenhum método de frete retornou taxa para este destino.', 'autocep'), 'warning');

            return array();
        }

        $this->log_missing_rate_methods($package, $resultado['rates']);

        $taxas = array();

        foreach ($resultado['rates'] as $rate) {
            $label = $rate->get_label();
            $meta  = $rate->get_meta_data();

            $estimativa = $this->resolve_rate_delivery_estimate($meta, $label);

            if (!empty($estimativa['legacy_days_suffix'])) {
                $label .= ' ' . $estimativa['legacy_days_suffix'];
            }

            $taxas[] = array(
                'id'                  => $rate->get_id(),
                'label'               => $label,
                'cost'                => (float) $rate->get_cost(),
                'cost_formatted'      => wc_price($rate->get_cost()),
                'delivery_estimate'   => $estimativa['label'],
                'pickup_instructions' => isset($meta['_autocep_pickup_instructions']) ? (string) $meta['_autocep_pickup_instructions'] : '',
            );
        }

        if (class_exists('AutoCEP_Store_Pickup') && !empty($package['destination']['postcode'])) {
            $cep_destino = preg_replace('/\D/', '', $package['destination']['postcode']);
            $tem_retirada_nas_taxas = false;

            foreach ($resultado['rates'] as $rate) {
                if (strpos($rate->get_id(), 'autocep_store_pickup') !== false) {
                    $tem_retirada_nas_taxas = true;
                    break;
                }
            }

            if (!$tem_retirada_nas_taxas) {
                $lojas_proximas = AutoCEP_Store_Pickup::get_nearby_stores($cep_destino);

                foreach ($lojas_proximas as $loja) {
                    $gratis = empty($loja['pickup_fee']) || (float) $loja['pickup_fee'] <= 0;

                    if (null === $loja['distance_km']) {
                        $label = $gratis
                            ? sprintf(__('Retire Grátis na %s', 'autocep'), $loja['name'])
                            : sprintf(__('Retire na %s', 'autocep'), $loja['name']);
                    } else {
                        $dist_fmt = number_format((float) $loja['distance_km'], 1, ',', '.');
                        $label = $gratis
                            ? sprintf(__('Retire Grátis na %1$s (a %2$s km de você)', 'autocep'), $loja['name'], $dist_fmt)
                            : sprintf(__('Retire na %1$s (a %2$s km de você)', 'autocep'), $loja['name'], $dist_fmt);
                    }

                    $custo = (float) $loja['pickup_fee'];

                    $taxas[] = array(
                        'id'                  => 'autocep_store_pickup_fallback_' . $loja['id'],
                        'label'               => $label,
                        'cost'                => $custo,
                        'cost_formatted'      => wc_price($custo),
                        'delivery_estimate'   => '',
                        'pickup_instructions' => !empty($loja['instructions']) ? $loja['instructions'] : '',
                    );
                }
            }
        }

        if (class_exists('AutoCEP_Km_Delivery') && !empty($package['destination']['postcode'])) {
            $cep_km   = preg_replace('/\D/', '', $package['destination']['postcode']);
            $taxa_km  = AutoCEP_Km_Delivery::calculate($cep_km);

            if ($taxa_km !== null) {
                $ja_tem_km = false;
                foreach ($taxas as $t) {
                    if ($t['id'] === 'autocep_km_delivery') {
                        $ja_tem_km = true;
                        break;
                    }
                }
                if (!$ja_tem_km) {
                    $taxas[] = $taxa_km;
                }
            }
        }

        return $taxas;
    }

    private function resolve_rate_delivery_estimate($meta, $label = '')
    {
        $options = AutoCEP_Admin::get_options();
        $config  = isset($options['delivery_estimate']) ? $options['delivery_estimate'] : array();

        $legacy_suffix = !empty($meta['_delivery_forecast'])
            ? sprintf(__('(Entrega em %s dias úteis)', 'autocep'), $meta['_delivery_forecast'])
            : '';

        if (empty($config['enabled'])) {
            return array('label' => '', 'legacy_days_suffix' => $legacy_suffix);
        }

        $chaves_meta_dias = array('_delivery_forecast', 'delivery_time', '_melhorenvio_delivery_time', 'delivery-time', 'prazo_entrega');

        $dias_min = null;

        foreach ($chaves_meta_dias as $chave) {
            if (isset($meta[$chave]) && is_numeric($meta[$chave]) && (int) $meta[$chave] > 0) {
                $dias_min = (int) $meta[$chave];
                break;
            }
        }

        if (null === $dias_min && !empty($label) && preg_match('/(\d+)\s*dia/ui', $label, $matches_dias)) {
            $dias_extraidos = (int) $matches_dias[1];

            if ($dias_extraidos > 0) {
                $dias_min = $dias_extraidos;
            }
        }

        $dias_max = null;

        if (null === $dias_min && !empty($config['fallback_enabled'])) {
            $dias_min = isset($config['fallback_min_days']) ? (int) $config['fallback_min_days'] : 0;
            $dias_max = isset($config['fallback_max_days']) ? (int) $config['fallback_max_days'] : $dias_min;
        }

        if (null === $dias_min || $dias_min <= 0) {
            return array('label' => '', 'legacy_days_suffix' => $legacy_suffix);
        }

        if (null === $dias_max) {
            $margem   = isset($config['range_margin_days']) ? max(0, (int) $config['range_margin_days']) : 2;
            $dias_max = $dias_min + $margem;
        }

        $apenas_uteis = !empty($config['business_days_only']);
        $fuso         = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('America/Sao_Paulo');

        $data_min = $this->add_business_days(new \DateTime('now', $fuso), $dias_min, $apenas_uteis);
        $data_max = $dias_max > $dias_min
            ? $this->add_business_days(new \DateTime('now', $fuso), $dias_max, $apenas_uteis)
            : clone $data_min;

        return array(
            'label'               => $this->format_delivery_range($data_min, $data_max),
            'legacy_days_suffix'  => '',
        );
    }

    private function add_business_days(\DateTime $data, $dias, $apenas_uteis)
    {
        $data       = clone $data;
        $adicionados = 0;

        while ($adicionados < $dias) {
            $data->modify('+1 day');

            if ($apenas_uteis) {
                $dia_semana = (int) $data->format('N'); 

                if ($dia_semana >= 6) {
                    continue; 
                }
            }

            $adicionados++;
        }

        return $data;
    }

    private function format_delivery_range(\DateTime $data_min, \DateTime $data_max)
    {
        $formatar = function (\DateTime $data) {
            return date_i18n('l (d/m)', $data->getTimestamp());
        };

        if ($data_min->format('Y-m-d') === $data_max->format('Y-m-d')) {
            return sprintf(__('Chega em %s', 'autocep'), $formatar($data_min));
        }

        return sprintf(__('Chega entre %1$s e %2$s', 'autocep'), $formatar($data_min), $formatar($data_max));
    }

    private function get_free_shipping_threshold($package = null)
    {
        $options = AutoCEP_Admin::get_options();
        $config  = isset($options['free_shipping_bar']) ? $options['free_shipping_bar'] : array();

        if (!empty($config['threshold']) && (float) $config['threshold'] > 0) {
            return (float) $config['threshold'];
        }

        if (empty($config['auto_detect']) || !class_exists('WC_Shipping_Zones')) {
            return 0.0;
        }

        $zona_destino = null;

        if (!empty($package)) {
            $zona_destino = WC_Shipping_Zones::get_zone_matching_package($package);
        }

        $minimos = $this->collect_free_shipping_minimums($zona_destino);

        if (empty($minimos)) {
            return 0.0;
        }

        return min($minimos);
    }

    private function collect_free_shipping_minimums($zona_unica = null)
    {
        if (!class_exists('WC_Shipping_Zones')) {
            return array();
        }

        $zonas = array();

        if ($zona_unica instanceof WC_Shipping_Zone) {
            $zonas[] = $zona_unica;
        } else {
            foreach (WC_Shipping_Zones::get_zones() as $zona_dados) {
                $zona = WC_Shipping_Zones::get_zone($zona_dados['zone_id']);

                if ($zona) {
                    $zonas[] = $zona;
                }
            }

            $zonas[] = new WC_Shipping_Zone(0);
        }

        $minimos = array();

        foreach ($zonas as $zona) {
            if (!method_exists($zona, 'get_shipping_methods')) {
                continue;
            }

            foreach ($zona->get_shipping_methods(true) as $metodo) {
                if ('free_shipping' !== $metodo->id || !method_exists($metodo, 'get_option')) {
                    continue;
                }

                $valor_minimo = $metodo->get_option('min_amount');

                if ('' !== $valor_minimo && is_numeric($valor_minimo) && (float) $valor_minimo > 0) {
                    $minimos[] = (float) $valor_minimo;
                }
            }
        }

        return $minimos;
    }

    private function build_free_shipping_bar_data($subtotal, $package = null, $zona_confirmada = false)
    {
        $options = AutoCEP_Admin::get_options();
        $config  = isset($options['free_shipping_bar']) ? $options['free_shipping_bar'] : array();

        if (empty($config['enabled'])) {
            return null;
        }

        $limite = $this->get_free_shipping_threshold($package);

        if ($limite <= 0) {
            return null;
        }

        $subtotal   = max(0, (float) $subtotal);
        $faltante   = max(0, $limite - $subtotal);
        $percentual = $limite > 0 ? (int) min(100, round(($subtotal / $limite) * 100)) : 0;

        if ($faltante <= 0 && !$zona_confirmada) {
            return array(
                'achieved'   => false,
                'percentage' => min(99, $percentual),
                'message'    => __('Simule o frete abaixo e veja se você já ganhou Frete Grátis!', 'autocep'),
            );
        }

        if ($faltante <= 0) {
            $mensagem = !empty($config['message_achieved'])
                ? $config['message_achieved']
                : __('Você ganhou Frete Grátis!', 'autocep');
        } else {
            $modelo   = !empty($config['message_progress'])
                ? $config['message_progress']
                : __('Faltam {missing} para você ganhar Frete Grátis!', 'autocep');
            $mensagem = str_replace('{missing}', wp_strip_all_tags(wc_price($faltante)), $modelo);
        }

        return array(
            'achieved'   => $faltante <= 0,
            'percentage' => $percentual,
            'message'    => $mensagem,
        );
    }

    private function render_free_shipping_bar_html($subtotal, $package = null, $zona_confirmada = false)
    {
        $dados = $this->build_free_shipping_bar_data($subtotal, $package, $zona_confirmada);

        if (null === $dados) {
            return '';
        }

        ob_start();
        ?>
        <div class="autocep-free-shipping-bar<?php echo $dados['achieved'] ? ' autocep-free-shipping-bar--achieved' : ''; ?>">
            <div class="autocep-free-shipping-bar-track">
                <div class="autocep-free-shipping-bar-fill" style="width: <?php echo esc_attr($dados['percentage']); ?>%;"></div>
            </div>
            <p class="autocep-free-shipping-bar-message"><?php echo esc_html($dados['message']); ?></p>
        </div>
        <?php

        return trim((string) ob_get_clean());
    }

    private function record_shipping_stats($contexto, $cep, $package, $rates)
    {
        if (empty($rates) || !class_exists('AutoCEP_Stats')) {
            return;
        }

        $custos = wp_list_pluck($rates, 'cost');

        if (empty($custos)) {
            return;
        }

        AutoCEP_Stats::record_simulation(
            $contexto,
            $cep,
            isset($package['destination']['state']) ? $package['destination']['state'] : '',
            isset($package['destination']['city']) ? $package['destination']['city'] : '',
            min($custos)
        );
    }

    private function log_missing_rate_methods($package, $rates)
    {
        if (!class_exists('WC_Shipping_Zones')) {
            return;
        }

        $zona = WC_Shipping_Zones::get_zone_matching_package($package);

        if (!$zona) {
            return;
        }

        $ids_com_taxa = array();

        foreach ($rates as $rate) {
            $partes = explode(':', $rate->get_id());
            $ids_com_taxa[] = end($partes);
        }

        $sem_taxa = array();

        foreach ($zona->get_shipping_methods(true) as $metodo) {
            if (!$metodo->is_enabled()) {
                continue;
            }

            if (!in_array((string) $metodo->get_instance_id(), $ids_com_taxa, true)) {
                $sem_taxa[] = $metodo->get_title();
            }
        }

        if (!empty($sem_taxa)) {
            $aviso_peso = $this->build_missing_weight_hint($package);

            $this->logger->log(
                'shipping-methods',
                $package['destination']['postcode'] ?? '',
                sprintf(
                    __('Os seguintes métodos de frete habilitados na zona não retornaram taxa para este destino (sem erro, apenas sem preço): %1$s. Verifique se a transportadora atende a esse CEP.%2$s', 'autocep'),
                    implode(', ', $sem_taxa),
                    $aviso_peso
                ),
                'warning'
            );
        }
    }

    private function build_missing_weight_hint($package)
    {
        if (empty($package['contents'])) {
            return '';
        }

        $produtos_sem_peso = array();

        foreach ($package['contents'] as $item) {
            if (empty($item['data']) || !($item['data'] instanceof WC_Product)) {
                continue;
            }

            $peso = $item['data']->get_weight();

            if ('' === $peso || null === $peso || 0.0 === (float) $peso) {
                $produtos_sem_peso[] = $item['data']->get_name();
            }
        }

        if (empty($produtos_sem_peso)) {
            return '';
        }

        return ' ' . sprintf(
            __('Possível causa: produto(s) sem peso cadastrado — %s. Algumas transportadoras exigem peso mínimo para cotar.', 'autocep'),
            implode(', ', $produtos_sem_peso)
        );
    }

    private function resolve_empty_rates_message($cep)
    {
        $options = AutoCEP_Admin::get_options();

        $endereco = AutoCEP_Api::find_address($cep, null);

        if (is_wp_error($endereco) || empty($endereco)) {
            return $options['messages']['not_found'];
        }

        return __('Nenhuma opção de frete disponível para este CEP.', 'autocep');
    }

    private function sanitize_cep($cep)
    {
        $cep = preg_replace('/\D/', '', sanitize_text_field(wp_unslash($cep)));

        return preg_match('/^[0-9]{8}$/', $cep) ? $cep : false;
    }
}
