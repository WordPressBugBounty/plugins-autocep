<?php
/**
 * Classe responsável pelo motor de cálculo de frete.
 *
 * @package AutoCEP
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renderiza e processa os simuladores de frete da página de produto e
 * do checkout, consultando dinamicamente as Zonas de Entrega do
 * WooCommerce configuradas pela loja.
 */
class AutoCEP_Shipping
{
    /**
     * Instância do logger.
     *
     * @var AutoCEP_Logger
     */
    private $logger;

    /**
     * Construtor.
     *
     * @param AutoCEP_Logger $logger Instância do logger do plugin.
     */
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

        add_action('init', array($this, 'register_display_hooks'));

        // Compatibilidade retroativa: o antigo plugin "Calculadora de Frete
        // na Página do Produto" usava o shortcode [shipping_calculator_on_product_page]
        // para posicionar a caixa manualmente dentro da descrição do produto.
        // Registrando o mesmo shortcode aqui, qualquer conteúdo que já o
        // utilize (ex.: descrições antigas) volta a funcionar automaticamente,
        // sem precisar editar produto por produto.
        add_shortcode('shipping_calculator_on_product_page', array($this, 'render_shipping_calculator_shortcode'));
    }

    /**
     * Registra os hooks de exibição das caixas de frete (produto,
     * carrinho e checkout) de acordo com a posição escolhida no painel
     * administrativo.
     *
     * @return void
     */
    public function register_display_hooks()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $options = AutoCEP_Admin::get_options();

        // Evita duplicidade visual com plugins de transportadora que também
        // inserem sua própria caixa de frete na página de produto (ex.:
        // Melhor Envio): quando o simulador do AutoCEP está habilitado,
        // desliga a caixa nativa deles automaticamente.
        $this->sync_third_party_product_calculators(!empty($options['product_shipping']['enabled']));

        if (!empty($options['product_shipping']['enabled'])) {
            $hook = !empty($options['product_shipping']['hook'])
                ? $options['product_shipping']['hook']
                : 'woocommerce_after_add_to_cart_form';

            // Quando a posição escolhida é "shortcode", a caixa não é
            // anexada automaticamente a nenhum hook: ela só aparece onde o
            // shortcode [shipping_calculator_on_product_page] for inserido
            // manualmente (na descrição do produto, por exemplo), evitando
            // que a caixa apareça duplicada na página.
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

    /**
     * Callback do shortcode legado [shipping_calculator_on_product_page].
     * Renderiza a mesma caixa de simulação de frete usada nos hooks
     * automáticos, permitindo posicionamento manual dentro do conteúdo.
     *
     * @return string
     */
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

    /**
     * Evita que a caixa de simulação de frete do AutoCEP apareça
     * duplicada na página de produto junto com a caixa nativa de
     * plugins de transportadora que também se inserem automaticamente
     * ali (ex.: Melhor Envio).
     *
     * Quando o simulador de produto do AutoCEP está habilitado, a caixa
     * nativa dessas integrações é desligada via a própria opção que
     * elas já oferecem para isso — a cotação real continua funcionando
     * normalmente, pois ela é feita através das Zonas de Entrega do
     * WooCommerce (métodos de frete), não pela caixa em si. Se o
     * simulador do AutoCEP for desligado, a caixa nativa volta a
     * aparecer automaticamente.
     *
     * @param bool $autocep_habilitado Se o simulador de produto do AutoCEP está ativo.
     *
     * @return void
     */
    private function sync_third_party_product_calculators($autocep_habilitado)
    {
        // Melhor Envio (plugin oficial "melhor-envio-cotacao"): expõe a
        // opção "melhorenvio_hide_calculator_product" para ligar/desligar
        // sua própria calculadora na página de produto. Definir com o
        // valor "1" a desliga; removê-la (delete_option) restaura o
        // comportamento padrão do plugin.
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

    /**
     * Imprime o HTML da caixa de simulação de frete na página de produto.
     *
     * @return void
     */
    public function render_product_shipping_box()
    {
        global $product;

        $produto_atual = $product instanceof WC_Product ? $product : null;

        // Fallback: quando a caixa é renderizada via shortcode legado em
        // contextos onde o global $product ainda não foi configurado
        // (ex.: alguns construtores de página), tenta resolver o produto
        // pelo ID da página/post atual.
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

        ?>
        <div class="autocep-shipping-box autocep-shipping-box--product" data-product-id="<?php echo esc_attr($produto_atual->get_id()); ?>">
            <p class="autocep-shipping-title"><strong><?php echo esc_html($titulo); ?></strong></p>
            <div class="autocep-shipping-form">
                <input
                    type="text"
                    class="autocep-shipping-cep-input"
                    placeholder="<?php esc_attr_e('Digite seu CEP', 'autocep'); ?>"
                    maxlength="9"
                    inputmode="numeric"
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

    /**
     * Imprime o HTML da caixa de simulação de frete na página do carrinho.
     *
     * @return void
     */
    public function render_cart_shipping_box()
    {
        $options = AutoCEP_Admin::get_options();
        $titulo  = !empty($options['cart_shipping']['title'])
            ? $options['cart_shipping']['title']
            : __('Simular Frete', 'autocep');

        $this->print_cart_or_checkout_box('cart', $titulo);
    }

    /**
     * Imprime o HTML da caixa de simulação de frete no checkout.
     *
     * @return void
     */
    public function render_checkout_shipping_box()
    {
        $this->print_cart_or_checkout_box('checkout', __('Simular Frete', 'autocep'));
    }

    /**
     * Imprime o markup compartilhado das caixas de frete baseadas no
     * conteúdo do carrinho (usada tanto na página do carrinho quanto no
     * checkout — a única diferença entre elas é a classe CSS, usada pelo
     * JavaScript para acionar a ação AJAX correta de cada contexto).
     *
     * @param string $contexto "cart" ou "checkout".
     * @param string $titulo   Título exibido no topo da caixa.
     *
     * @return void
     */
    private function print_cart_or_checkout_box($contexto, $titulo)
    {
        ?>
        <div class="autocep-shipping-box autocep-shipping-box--<?php echo esc_attr($contexto); ?>">
            <p class="autocep-shipping-title"><strong><?php echo esc_html($titulo); ?></strong></p>
            <div class="autocep-shipping-form">
                <input
                    type="text"
                    class="autocep-shipping-cep-input"
                    placeholder="<?php esc_attr_e('Digite seu CEP', 'autocep'); ?>"
                    maxlength="9"
                    inputmode="numeric"
                />
                <button type="button" class="autocep-shipping-calc-btn"><?php esc_html_e('Calcular', 'autocep'); ?></button>
            </div>
            <div class="autocep-shipping-feedback" aria-live="polite"></div>
            <div class="autocep-shipping-results"></div>
        </div>
        <?php
    }

    /**
     * Garante que o carrinho, a sessão e o cliente do WooCommerce
     * (`WC()->cart`, `WC()->session`, `WC()->customer`) estejam
     * totalmente inicializados antes de calcular ou aplicar frete.
     *
     * Isto é necessário porque nossas rotas AJAX passam por
     * `admin-ajax.php` (via `wp_ajax_`/`wp_ajax_nopriv_`), que — ao
     * contrário do endpoint próprio do WooCommerce (`wc-ajax`) — não
     * garante esses objetos prontos para um visitante anônimo que ainda
     * não interagiu com o carrinho. Sem isso, integrações de frete de
     * terceiros que dependem de `WC()->cart`/`WC()->session`
     * internamente (ex.: Melhor Envio, Frenet) podem lançar exceção ou
     * falhar somente para visitantes não autenticados — mesmo
     * funcionando normalmente para um usuário logado, cuja sessão já
     * está sempre disponível.
     *
     * @return void
     */
    private function ensure_wc_cart_session()
    {
        if (function_exists('wc_load_cart')) {
            wc_load_cart();
        }
    }

    /**
     * Handler AJAX: calcula o frete a partir da página de produto
     * (antes de o item estar no carrinho), considerando a quantidade
     * selecionada quando a opção estiver habilitada.
     *
     * @return void
     */
    public function ajax_calc_shipping_product()
    {
        check_ajax_referer(AUTOCEP_NONCE_ACTION, 'nonce');

        $this->ensure_wc_cart_session();

        $options = AutoCEP_Admin::get_options();

        if (empty($options['product_shipping']['enabled'])) {
            wp_send_json_error(array('message' => __('Simulador de frete desabilitado.', 'autocep')));
        }

        // Se a loja tiver o cálculo de frete desabilitado globalmente, não há o que simular.
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

        // Valida o CEP também com o validador nativo do WooCommerce, considerando o país base da loja.
        $pais_base = WC()->countries ? WC()->countries->get_base_country() : 'BR';

        if (class_exists('WC_Validation') && !WC_Validation::is_postcode($cep, $pais_base)) {
            wp_send_json_error(array('message' => __('Por favor, insira um CEP válido.', 'autocep')));
        }

        // Resolve a variação selecionada (produtos variáveis) ou o produto simples.
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

        $package = $this->build_package_for_product($product, $quantidade, $cep);
        $rates   = $this->calculate_rates($package);

        if (is_wp_error($rates)) {
            $this->logger->log('shipping-product', $cep, $rates->get_error_message(), 'error');
            wp_send_json_error(array('message' => $rates->get_error_message()));
        }

        if (empty($rates)) {
            wp_send_json_error(array('message' => $this->resolve_empty_rates_message($cep)));
        }

        // Mantém o CEP informado na sessão do cliente, para consistência com o checkout
        // (mesmo comportamento de calculadoras de frete de página de produto tradicionais).
        if (function_exists('WC') && WC()->customer) {
            WC()->customer->set_shipping_postcode($cep);
            WC()->customer->set_billing_postcode($cep);
        }

        wp_send_json_success(array('rates' => $rates));
    }

    /**
     * Resolve o produto (ou variação) informado na requisição AJAX da
     * página de produto, validando se a variação de fato pertence ao
     * produto pai indicado.
     *
     * @param int $product_id   ID do produto (pai, no caso de variáveis).
     * @param int $variation_id ID da variação selecionada (0 se não houver).
     *
     * @return WC_Product|false
     */
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

    /**
     * Handler AJAX: calcula o frete a partir do checkout, usando o
     * conteúdo atual do carrinho do cliente.
     *
     * @return void
     */
    public function ajax_calc_shipping_checkout()
    {
        $this->handle_cart_based_shipping_ajax('checkout_shipping', 'shipping-checkout');
    }

    /**
     * Handler AJAX: calcula o frete a partir da página do carrinho de
     * compras, usando os itens já adicionados pelo cliente.
     *
     * @return void
     */
    public function ajax_calc_shipping_cart()
    {
        $this->handle_cart_based_shipping_ajax('cart_shipping', 'shipping-cart');
    }

    /**
     * Lógica compartilhada de cálculo de frete baseado no carrinho atual
     * do cliente — usada tanto pela caixa da página do carrinho quanto
     * pela caixa do checkout, já que ambas calculam a partir dos mesmos
     * itens já adicionados, diferindo apenas na opção que as habilita e
     * no rótulo usado nos logs de diagnóstico.
     *
     * @param string $chave_opcao Chave em $options que controla se esta caixa está habilitada ("cart_shipping" ou "checkout_shipping").
     * @param string $rotulo_log  Identificador usado ao registrar falhas no log de diagnóstico.
     *
     * @return void
     */
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

        $package = $this->build_package_for_cart($cep);
        $rates   = $this->calculate_rates($package);

        if (is_wp_error($rates)) {
            $this->logger->log($rotulo_log, $cep, $rates->get_error_message(), 'error');
            wp_send_json_error(array('message' => $rates->get_error_message()));
        }

        if (empty($rates)) {
            wp_send_json_error(array('message' => $this->resolve_empty_rates_message($cep)));
        }

        wp_send_json_success(array('rates' => $rates));
    }

    /**
     * Handler AJAX: aplica de fato uma taxa de frete simulada como o
     * método escolhido para o carrinho/checkout, usando o mesmo
     * mecanismo nativo do WooCommerce (sessão "chosen_shipping_methods")
     * que os botões de rádio de frete usam — ou seja, a partir daqui a
     * escolha vale de verdade para o pedido, não é apenas visual.
     *
     * O CEP e a taxa recebidos são sempre revalidados no servidor antes
     * de serem aplicados (recalculando as taxas disponíveis no momento
     * da seleção), em vez de confiar apenas no que o cliente enviou.
     *
     * @return void
     */
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

        // Revalida as taxas disponíveis para este CEP no exato momento da
        // seleção — evita aplicar um método que não existe mais (ex.:
        // zona de entrega alterada nesse meio tempo, ou requisição
        // manipulada com um ID inexistente).
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

        // Atualiza o CEP do cliente na sessão, para consistência com o
        // restante do carrinho/checkout.
        if (WC()->customer) {
            WC()->customer->set_shipping_postcode($cep);
            WC()->customer->set_billing_postcode($cep);
            WC()->customer->save();
        }

        // Define este método como o frete escolhido para o pacote
        // principal do carrinho — o mesmo mecanismo nativo usado pelos
        // botões de rádio de frete do próprio WooCommerce, então a
        // escolha é respeitada normalmente no restante do fluxo de compra.
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

    /**
     * Resolve o destino (país, estado e cidade) a partir do CEP digitado
     * pelo cliente, usando a própria busca de endereço do AutoCEP
     * (ViaCEP/BrasilAPI/ApiCEP, já com cache).
     *
     * Isto é essencial para o casamento correto das Zonas de Entrega do
     * WooCommerce: uma zona restrita a um estado específico (ex.: "PB")
     * só é encontrada se o pacote informar a UF correspondente — apenas
     * o CEP não é suficiente. Sem isso, a simulação pode retornar
     * "nenhuma opção de frete" mesmo quando o checkout real, com o
     * endereço completo já preenchido, encontra métodos disponíveis.
     *
     * @param string $cep CEP com 8 dígitos.
     *
     * @return array Array no formato esperado pela chave "destination" do pacote.
     */
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

        // Fallback: se a busca de endereço falhar, tenta aproveitar o
        // estado/cidade já conhecidos na sessão do cliente (ex.: quando
        // o CEP simulado é o mesmo do endereço já preenchido no checkout).
        $estado_sessao = (function_exists('WC') && WC()->customer) ? WC()->customer->get_shipping_state() : '';
        $cidade_sessao = (function_exists('WC') && WC()->customer) ? WC()->customer->get_shipping_city() : '';

        if ($this->logger) {
            $this->logger->log('shipping-destination', $cep, __('Não foi possível determinar o estado/cidade do CEP para o cálculo de frete; usando dados da sessão como alternativa.', 'autocep'), 'warning');
        }

        return array(
            'country'   => 'BR',
            'state'     => $estado_sessao,
            'postcode'  => $cep,
            'city'      => $cidade_sessao,
            'address'   => '',
            'address_2' => '',
        );
    }

    /**
     * Monta o "pacote" de envio (formato esperado pelo WooCommerce) a
     * partir de um único produto, usado na simulação da página de produto.
     *
     * @param WC_Product $product    Produto sendo simulado.
     * @param int        $quantidade Quantidade considerada no cálculo.
     * @param string     $cep        CEP de destino.
     *
     * @return array
     */
    private function build_package_for_product(WC_Product $product, $quantidade, $cep)
    {
        // Calcula o preço com e sem imposto (necessário para métodos de
        // frete que consideram o valor declarado do item, como os
        // Correios), da mesma forma que o WooCommerce faz ao montar o
        // carrinho real.
        $preco_sem_imposto = (float) $product->get_price_excluding_tax($quantidade);
        $preco_com_imposto = (float) $product->get_price_including_tax($quantidade);
        $imposto           = $preco_com_imposto - $preco_sem_imposto;

        $cart_id = (function_exists('WC') && WC()->cart)
            ? WC()->cart->generate_cart_id($product->get_id(), $product->is_type('variation') ? $product->get_id() : 0)
            : 'autocep_item';

        // Reaproveita os cupons já aplicados no carrinho real do cliente
        // (se houver), para que regras de frete grátis por cupom sejam
        // respeitadas também na simulação da página de produto.
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

        // Compatibilidade com o plugin oficial do Melhor Envio: como o
        // item ainda não está no carrinho real nesta simulação, o método
        // de cálculo deles (CalculateShippingMethodService) espera duas
        // coisas para não tentar ler do carrinho real (que aqui estaria
        // vazio para um visitante que ainda não comprou nada): a flag
        // "product_page_calculation" no pacote, e uma chave
        // "formatted_data" em cada item com os dados do produto no
        // formato interno deles. Sem isso, o cálculo funcionava por
        // acidente apenas quando havia algo no carrinho real da sessão
        // (ex.: durante testes logado), e falhava para qualquer
        // visitante com o carrinho vazio.
        $item_pacote['formatted_data'] = $this->get_melhor_envio_formatted_product_data($product, $quantidade);

        return array(
            'contents'      => array(
                $cart_id => $item_pacote,
            ),
            'contents_cost'    => $preco_sem_imposto,
            'applied_coupons'  => $cupons_aplicados,
            'user'             => array('ID' => get_current_user_id()),
            'destination'      => $this->resolve_destination_from_cep($cep),

            // Também usado pelo Melhor Envio para saber que este pacote
            // não veio do carrinho real do cliente.
            'product_page_calculation' => true,
        );
    }

    /**
     * Monta os dados do produto no formato interno esperado pelo plugin
     * oficial do Melhor Envio, para simulações fora do carrinho real
     * (ex.: página de produto). Retorna null se o plugin não estiver
     * instalado ou se algo der errado — nesse caso o item simplesmente
     * não terá essa chave extra, sem afetar o restante do cálculo.
     *
     * @param WC_Product $product    Produto sendo simulado.
     * @param int        $quantidade Quantidade considerada.
     *
     * @return mixed|null
     */
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

    /**
     * Monta o "pacote" de envio a partir do carrinho atual do cliente,
     * usado na simulação do checkout.
     *
     * @param string $cep CEP de destino.
     *
     * @return array
     */
    private function build_package_for_cart($cep)
    {
        $cart_package = WC()->cart->get_shipping_packages();
        $package      = !empty($cart_package[0]) ? $cart_package[0] : array();

        $package['destination'] = $this->resolve_destination_from_cep($cep);

        // Mesma compatibilidade aplicada à página de produto: injeta
        // "formatted_data" em cada item como rede de segurança para o
        // plugin do Melhor Envio. Diferente da página de produto, aqui
        // NÃO marcamos "product_page_calculation", pois o carrinho é
        // real — o caminho normal deles (ler direto do carrinho) deve
        // continuar sendo tentado primeiro; isto só socorre o cálculo
        // caso esse caminho normal não retorne nada (o que estava
        // acontecendo silenciosamente para alguns visitantes, resultando
        // em "nenhuma opção de frete disponível" em vez de um erro).
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

    /**
     * Calcula as taxas de frete disponíveis para um pacote, consultando
     * a Zona de Entrega correspondente e executando cada método de envio
     * habilitado nela.
     *
     * @param array $package Pacote no formato do WooCommerce.
     *
     * @return array|WP_Error Lista de taxas normalizadas ou erro.
     */
    private function calculate_rates($package)
    {
        if (!class_exists('WC_Shipping')) {
            return new WP_Error('autocep_no_wc', __('WooCommerce não está disponível.', 'autocep'));
        }

        // Alguns métodos de frete de terceiros (ex.: Melhor Envio, Frenet)
        // consultam uma API externa em tempo real para cotar o frete, o
        // que pode levar alguns segundos a mais que um Flat Rate comum.
        // Estendemos o tempo de execução do PHP nesta requisição AJAX
        // pontualmente, quando o servidor permitir, para evitar que a
        // simulação seja interrompida antes da resposta da transportadora.
        if (function_exists('set_time_limit') && !ini_get('safe_mode')) {
            @set_time_limit(45);
        }

        try {
            // Usa o pipeline oficial do WooCommerce (o mesmo utilizado pelo
            // carrinho/checkout reais): resolve a Zona de Entrega
            // correspondente ao destino e calcula as taxas de todos os
            // métodos habilitados nela — incluindo integrações de
            // terceiros como Melhor Envio e Frenet, já que elas também se
            // registram como métodos de frete padrão do WooCommerce e
            // respondem à mesma chamada `calculate_shipping()`. Também
            // respeita regras nativas de disponibilidade (ex.: frete
            // grátis por cupom ou valor mínimo).
            $resultado = WC_Shipping::instance()->calculate_shipping_for_package($package);
        } catch (\Throwable $erro) {
            // Protege a simulação contra falhas de plugins de frete de
            // terceiros (ex.: exceção não tratada, API fora do ar) para
            // que o restante do checkout/produto continue funcionando
            // normalmente em vez de retornar um erro fatal no AJAX.
            $this->logger->log('shipping-methods', $package['destination']['postcode'] ?? '', $erro->getMessage(), 'error');

            return new WP_Error('autocep_shipping_exception', __('Uma das transportadoras configuradas não respondeu corretamente. Tente novamente em instantes.', 'autocep'));
        }

        if (empty($resultado['rates'])) {
            // Registra no diagnóstico quando a zona foi encontrada mas
            // nenhum método retornou taxa — útil para identificar, por
            // exemplo, quando o Melhor Envio/Frenet não está configurado
            // corretamente (token inválido, CEP de origem ausente etc.).
            $this->logger->log('shipping-methods', $package['destination']['postcode'] ?? '', __('Nenhum método de frete retornou taxa para este destino.', 'autocep'), 'warning');

            return array();
        }

        // Diagnóstico extra: se a zona de entrega tem mais métodos
        // habilitados do que taxas retornadas, alguns métodos ficaram
        // "silenciosos" (não lançaram erro, mas também não retornaram
        // preço). É um comportamento comum de transportadoras via
        // Melhor Envio/Frenet quando o produto não tem peso/dimensões
        // cadastrados, ou quando aquela transportadora específica não
        // atende ao destino — registramos os nomes para facilitar o
        // diagnóstico, sem impactar o resultado exibido ao cliente.
        $this->log_missing_rate_methods($package, $resultado['rates']);

        $taxas = array();

        foreach ($resultado['rates'] as $rate) {
            $label = $rate->get_label();
            $meta  = $rate->get_meta_data();

            // Compatibilidade com plugins de frete (ex.: Correios) que
            // armazenam o prazo estimado de entrega como meta da taxa.
            if (!empty($meta['_delivery_forecast'])) {
                /* translators: %s: prazo estimado em dias úteis. */
                $label .= ' ' . sprintf(__('(Entrega em %s dias úteis)', 'autocep'), $meta['_delivery_forecast']);
            }

            $taxas[] = array(
                'id'             => $rate->get_id(),
                'label'          => $label,
                'cost'           => (float) $rate->get_cost(),
                'cost_formatted' => wc_price($rate->get_cost()),
            );
        }

        return $taxas;
    }

    /**
     * Compara os métodos de frete habilitados na Zona de Entrega
     * correspondente ao pacote com as taxas efetivamente retornadas, e
     * registra no log de diagnóstico o nome de qualquer método que não
     * tenha retornado nenhuma taxa — sem lançar erro, mas também sem dar
     * preço (comportamento comum quando o produto não tem peso/dimensões
     * cadastrados, ou quando a transportadora específica não atende ao
     * destino consultado).
     *
     * @param array $package Pacote usado no cálculo.
     * @param array $rates   Taxas (`WC_Shipping_Rate`) retornadas pelo WooCommerce.
     *
     * @return void
     */
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
            // O ID da taxa vem no formato "método:id_da_instância" (ex.:
            // "flat_rate:2"); o ID da instância é o que identifica o
            // método configurado na zona.
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
                    /* translators: 1: lista de nomes dos métodos de frete que não retornaram taxa. 2: aviso extra sobre peso, se aplicável. */
                    __('Os seguintes métodos de frete habilitados na zona não retornaram taxa para este destino (sem erro, apenas sem preço): %1$s. Verifique se a transportadora atende a esse CEP.%2$s', 'autocep'),
                    implode(', ', $sem_taxa),
                    $aviso_peso
                ),
                'warning'
            );
        }
    }

    /**
     * Verifica se algum produto do pacote está sem peso cadastrado (0 ou
     * vazio) — causa muito comum de transportadoras específicas (ex.:
     * Jadlog, Azul) rejeitarem a cotação silenciosamente, enquanto
     * outras (ex.: Correios) toleram e retornam preço mesmo assim.
     *
     * @param array $package Pacote usado no cálculo.
     *
     * @return string Texto complementar para o log, ou string vazia.
     */
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
            /* translators: %s: lista de nomes dos produtos sem peso cadastrado. */
            __('Possível causa: produto(s) sem peso cadastrado — %s. Algumas transportadoras exigem peso mínimo para cotar.', 'autocep'),
            implode(', ', $produtos_sem_peso)
        );
    }

    /**
     * Escolhe a mensagem mais precisa para exibir quando nenhuma taxa
     * de frete foi retornada: se o próprio CEP não foi encontrado em
     * nenhuma das APIs de busca de endereço (situação já registrada no
     * log de diagnóstico em resolve_destination_from_cep()), diz isso
     * diretamente — em vez da mensagem genérica "nenhuma opção de
     * frete disponível", que nesse caso escondia a causa real.
     *
     * @param string $cep CEP consultado.
     *
     * @return string
     */
    private function resolve_empty_rates_message($cep)
    {
        $options = AutoCEP_Admin::get_options();

        $endereco = AutoCEP_Api::find_address($cep, null);

        if (is_wp_error($endereco) || empty($endereco)) {
            return $options['messages']['not_found'];
        }

        return __('Nenhuma opção de frete disponível para este CEP.', 'autocep');
    }

    /**
     * Sanitiza e valida um CEP recebido via requisição AJAX.
     *
     * @param string $cep CEP bruto.
     *
     * @return string|false CEP com 8 dígitos ou false se inválido.
     */
    private function sanitize_cep($cep)
    {
        $cep = preg_replace('/\D/', '', sanitize_text_field(wp_unslash($cep)));

        return preg_match('/^[0-9]{8}$/', $cep) ? $cep : false;
    }
}
