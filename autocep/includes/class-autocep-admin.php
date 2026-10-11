<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Admin
{
    const TABS = array('general', 'product_shipping', 'cart_shipping', 'checkout_shipping', 'modern_checkout', 'post_purchase', 'growth', 'distance_shipping', 'integrations', 'messages', 'appearance', 'shipping_dashboard', 'support', 'feedback', 'diagnostics');

    const REVIEW_URL = 'https://wordpress.org/support/plugin/autocep/reviews/#new-post';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_head', array($this, 'print_menu_badge_style'));
        add_action('wp_ajax_autocep_send_support_ticket', array($this, 'ajax_send_support_ticket'));
        add_action('wp_ajax_autocep_toggle_ticket_status', array($this, 'ajax_toggle_ticket_status'));
        add_action('wp_ajax_autocep_send_feedback', array($this, 'ajax_send_feedback'));
        add_action('wp_ajax_autocep_test_distance', array($this, 'ajax_test_distance'));
        add_action('wp_ajax_autocep_check_google_maps_status', array($this, 'ajax_check_google_maps_status'));
        add_action('wp_ajax_autocep_test_melhor_envio', array($this, 'ajax_test_melhor_envio'));
        add_action('wp_ajax_autocep_test_frenet', array($this, 'ajax_test_frenet'));
    }

    public function print_menu_badge_style()
    {
        echo '<style>
            #adminmenu .autocep-menu-plus-badge {
                display: inline-block;
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                color: #fff;
                font-size: 10px;
                font-weight: 800;
                line-height: 1;
                padding: 2px 5px;
                border-radius: 8px;
                margin-left: 4px;
                vertical-align: 2px;
            }
        </style>';
    }

    public function register_menu()
    {
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">' .
            '<path d="M3 6.5 8 4.5 14 6.5 20 4.5V18.5L14 20.5 8 18.5 2.5 20.5V6.5Z" fill="#fff" fill-opacity="0.28" stroke="#fff" stroke-width="1.2" stroke-linejoin="round"/>' .
            '<path d="M8 4.5V18.5M14 6.5V20.5" stroke="#fff" stroke-width="1" stroke-dasharray="1.6 1.8"/>' .
            '<path d="M18 10.2C18 7.33 15.76 5 13 5s-5 2.33-5 5.2c0 3.9 5 9.3 5 9.3s5-5.4 5-9.3Z" fill="#fff"/>' .
            '<circle cx="13" cy="10.2" r="1.9" fill="#1d4ed8"/>' .
            '</svg>'
        );

        add_menu_page(
            __('AutoCEP', 'autocep'),
            __('AutoCEP', 'autocep') . ' <span class="autocep-menu-plus-badge">+</span>',
            'manage_options',
            'autocep',
            array($this, 'render_page'),
            $icon_svg,
            58
        );

        $submenus = array(
            ''                    => __('🏠 Visão Geral', 'autocep'),
            '&tab=checkout_shipping' => __('🛒 Checkout', 'autocep'),
            '&tab=post_purchase'  => __('✅ Pós-Compra', 'autocep'),
            '&tab=shipping_dashboard' => __('📊 Analytics', 'autocep'),
            '&tab=growth'         => __('🚀 Growth Marketing', 'autocep'),
            '&tab=distance_shipping' => __('📍 Retirada em Loja', 'autocep'),
            '&tab=support'        => __('🎧 Suporte Técnico', 'autocep'),
            '&tab=feedback'       => __('⭐ Avalie o Plugin', 'autocep'),
            '&tab=diagnostics'    => __('🔧 Diagnóstico', 'autocep'),
        );

        foreach ($submenus as $sufixo => $titulo) {
            add_submenu_page(
                'autocep',
                $titulo,
                $titulo,
                'manage_options',
                'autocep' . $sufixo,
                array($this, 'render_page')
            );
        }

    }

    public static function get_default_options()
    {
        return array(
            'general' => array(
                'enable_billing'       => 1,
                'enable_shipping'      => 1,
                'apis'                 => array(
                    'viacep'    => array('enabled' => 1, 'priority' => 1),
                    'brasilapi' => array('enabled' => 1, 'priority' => 2),
                    'apicep'    => array('enabled' => 1, 'priority' => 3),
                ),
                'neighborhood_field'   => 'billing_address_2',
                'auto_focus'           => 1,
                'cache_days'           => 30,
                'browser_autocomplete' => 1,
                'address_suggest'      => 1,
                'address_suggest_provider' => 'auto',
                'shipping_cache_hours' => 24,
                'stats_enabled'        => 1,
            ),
            'product_shipping' => array(
                'enabled'           => 0,
                'hook'              => 'woocommerce_after_add_to_cart_form',
                'consider_quantity' => 1,
                'title'             => __('Calcular Frete e Prazo', 'autocep'),
            ),
            'cart_shipping' => array(
                'enabled'  => 0,
                'position' => 'woocommerce_before_cart_totals',
                'title'    => __('Simular Frete', 'autocep'),
            ),
            'checkout_shipping' => array(
                'enabled'  => 0,
                'position' => 'woocommerce_before_order_notes',
            ),

            'modern_checkout' => array(
                'enabled'        => 0,
                'compact'        => 0,
                'step1_title'    => __('Identificação', 'autocep'),
                'step2_title'    => __('Entrega', 'autocep'),
                'step3_title'    => __('Pagamento', 'autocep'),
                'next_label'     => __('Continuar', 'autocep'),
                'accent_color'   => '#1d4ed8',
                'pix_message'    => __('Ao finalizar, o QR Code e o código Pix copia e cola aparecem na próxima tela. A aprovação é imediata.', 'autocep'),
                'card_message'   => __('Pagamento seguro: os dados do cartão são enviados criptografados direto para a operadora.', 'autocep'),
                'boleto_message' => __('O boleto é gerado ao finalizar o pedido. A compensação leva até 3 dias úteis e o envio acontece após a confirmação.', 'autocep'),
                'pix_thankyou'   => 1,
                'account_notice' => 1,
                'account_message' => '',
                'account_mode'   => 'woocommerce',
                'ship_different' => 'unchecked',
                'form_width'     => 580,
                'summary_width'  => 340,
                'field_height'   => 38,
                'simulator'      => 'top',
                'fields'         => array(),
                'custom_fields'  => array(),
            ),

            'order_status' => array(
                'enabled'         => 0,
                'paid_on_load'    => 1,
                'redirect_url'    => '',
                'redirect_delay'  => 4,
                'msg_processing'  => __('Pagamento aprovado! Obrigado pela sua compra. Pedido nº {pedido}.', 'autocep'),
                'msg_completed'   => __('Seu pedido nº {pedido} foi concluído. Obrigado!', 'autocep'),
                'msg_on-hold'     => __('Seu pedido nº {pedido} está aguardando a confirmação do pagamento.', 'autocep'),
                'msg_failed'      => __('O pagamento do pedido nº {pedido} não foi aprovado. Tente novamente ou escolha outra forma de pagamento.', 'autocep'),
                'msg_cancelled'   => __('O pedido nº {pedido} foi cancelado.', 'autocep'),
                'msg_refunded'    => __('O pedido nº {pedido} foi reembolsado.', 'autocep'),
            ),

            'delivery_estimate' => array(
                'enabled'            => 0,
                'business_days_only' => 1,
                'range_margin_days'  => 2,
                'fallback_enabled'   => 0,
                'fallback_min_days'  => 3,
                'fallback_max_days'  => 7,
            ),

            'free_shipping_bar' => array(
                'enabled'          => 0,
                'auto_detect'      => 1,
                'threshold'        => 0,
                'message_progress' => __('Faltam {missing} para você ganhar Frete Grátis! 🚚', 'autocep'),
                'message_achieved' => __('Você ganhou Frete Grátis! 🎉', 'autocep'),
            ),

            'distance_shipping' => array(
                'google_maps_api_key' => '',
                'pickup_enabled'      => 0,
            ),

            'km_delivery' => array(
                'enabled'       => 0,
                'title'         => __('Entrega Local / Motoboy', 'autocep'),
                'origin_cep'    => '',
                'base_fee'      => 5.00,
                'price_per_km'  => 2.00,
                'max_radius_km' => 20.0,
                'min_fee'       => 0.0,
                'round_km'      => 0,
            ),

            'messages' => array(
                'searching'             => __('Buscando endereço...', 'autocep'),
                'not_found'             => __('CEP não encontrado.', 'autocep'),
                'calculating'           => __('Calculando frete...', 'autocep'),
                'invalid_cep'           => __('CEP inválido. Verifique e tente novamente.', 'autocep'),
                'generic_error'         => __('Ocorreu um erro. Tente novamente.', 'autocep'),
                'select_variation'      => __('Por favor, escolha uma opção do produto antes de calcular o frete.', 'autocep'),
                'no_street'             => __('Este CEP não possui um endereço específico cadastrado nos Correios. Por favor, preencha o campo "Endereço" manualmente.', 'autocep'),
                'select_rate'           => __('Selecionar', 'autocep'),
                'selecting_rate'        => __('Aplicando frete...', 'autocep'),
                'rate_selected'         => __('Frete selecionado com sucesso!', 'autocep'),
                'block_checkout_button' => 1,
            ),
            'appearance' => array(
                'field_bg_color'        => '#ffffff',
                'field_border_color'    => '#cccccc',
                'field_text_color'      => '#333333',
                'button_bg_color'       => '#1e1e1e',
                'button_text_color'     => '#ffffff',
                'button_hover_bg_color' => '#333333',
                'box_bg_color'          => '#ffffff',
                'box_border_color'      => '#e5e5e5',
                'border_radius'         => 'medio',
                'font_size'             => 'medio',
                'spacing'               => 'normal',
                'button_font_weight'    => 'normal',
                'free_shipping_bar_color'      => '#1e1e1e',
                'free_shipping_achieved_color' => '#1a7d36',
            ),
        );
    }

    public static function get_options()
    {
        $salvas   = get_option(AUTOCEP_OPTION_KEY, array());
        $padroes  = self::get_default_options();
        $opcoes   = self::merge_recursive_defaults($padroes, is_array($salvas) ? $salvas : array());

        if (isset($opcoes['general']['apis']['correios'])) {
            unset($opcoes['general']['apis']['correios']);
        }

        return $opcoes;
    }

    private static function merge_recursive_defaults($padroes, $salvas)
    {
        foreach ($padroes as $chave => $valor) {
            if (is_array($valor) && isset($salvas[$chave]) && is_array($salvas[$chave])) {
                $salvas[$chave] = self::merge_recursive_defaults($valor, $salvas[$chave]);
            } elseif (!isset($salvas[$chave])) {
                $salvas[$chave] = $valor;
            }
        }

        return $salvas;
    }

    public static function get_product_hook_choices()
    {
        return array(
            'woocommerce_single_product_summary' => __('Resumo do produto (junto ao preço/descrição)', 'autocep'),
            'woocommerce_after_add_to_cart_form' => __('Após o formulário de compra (padrão)', 'autocep'),
            'woocommerce_after_single_product_summary' => __('Após o resumo do produto', 'autocep'),
            'shortcode' => __('Manual — via shortcode [shipping_calculator_on_product_page]', 'autocep'),
        );
    }

    public static function get_checkout_hook_choices()
    {
        return array(
            'woocommerce_before_checkout_form'   => __('Antes do formulário de checkout', 'autocep'),
            'woocommerce_before_order_notes'     => __('Antes do campo de observações do pedido (padrão)', 'autocep'),
            'woocommerce_checkout_before_order_review' => __('Antes do resumo do pedido', 'autocep'),
        );
    }

    public static function get_cart_hook_choices()
    {
        return array(
            'woocommerce_before_cart_table'  => __('Antes da tabela do carrinho', 'autocep'),
            'woocommerce_after_cart_table'   => __('Depois da tabela do carrinho', 'autocep'),
            'woocommerce_before_cart_totals' => __('Antes do resumo do pedido (padrão)', 'autocep'),
            'woocommerce_after_cart_totals'  => __('Depois do resumo do pedido', 'autocep'),
        );
    }

    public function register_settings()
    {
        register_setting('autocep_options_group', AUTOCEP_OPTION_KEY, array(
            'sanitize_callback' => array($this, 'sanitize_options'),
        ));

        $this->register_general_tab_fields();
        $this->register_product_shipping_tab_fields();
        $this->register_cart_shipping_tab_fields();
        $this->register_checkout_shipping_tab_fields();
        $this->register_modern_checkout_tab_fields();
        $this->register_post_purchase_tab_fields();
        $this->register_growth_tab_fields();
        $this->register_distance_shipping_tab_fields();
        $this->register_integrations_tab_fields();
        $this->register_messages_tab_fields();
        $this->register_appearance_tab_fields();
    }

    private function register_growth_tab_fields()
    {
        $page = 'autocep_tab_growth';

        add_settings_section('autocep_growth_delivery', __('Previsão de Entrega (Data Estimada)', 'autocep'), function () {
            echo '<p>' . esc_html__('Em vez de mostrar apenas "Prazo: 5 dias úteis", exibe a data estimada de chegada junto de cada opção de frete simulada — ex.: "Chega entre Quarta-feira (25/10) e Sexta-feira (27/10)". Isso passa mais segurança ao cliente e tende a aumentar a conversão. Usa automaticamente o prazo informado pela transportadora (Correios, Melhor Envio, Frenet etc.) quando disponível.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('enabled', __('Ativar Previsão de Data', 'autocep'), function () {
            $this->render_checkbox('delivery_estimate', 'enabled', __('Exibir a data estimada de entrega junto a cada opção de frete simulada.', 'autocep'));
        }, $page, 'autocep_growth_delivery');

        add_settings_field('business_days_only', __('Contar Apenas Dias Úteis', 'autocep'), function () {
            $this->render_checkbox('delivery_estimate', 'business_days_only', __('Pula sábados e domingos ao calcular a data estimada de entrega.', 'autocep'));
        }, $page, 'autocep_growth_delivery');

        add_settings_field('range_margin_days', __('Margem do Intervalo (dias)', 'autocep'), function () {
            $this->render_number('delivery_estimate', 'range_margin_days', 0, 10, __('Quantos dias somar à data mínima calculada para formar o intervalo "chega entre X e Y". Use 0 para exibir uma única data ("Chega em X").', 'autocep'));
        }, $page, 'autocep_growth_delivery');

        add_settings_field('fallback_enabled', __('Usar Estimativa Padrão (Sem Prazo da Transportadora)', 'autocep'), function () {
            $this->render_checkbox('delivery_estimate', 'fallback_enabled', __('Quando o método de frete não informar um prazo (ex.: Frete Fixo simples, sem integração de transportadora), usar os dias padrão definidos abaixo em vez de omitir a previsão.', 'autocep'));
        }, $page, 'autocep_growth_delivery');

        add_settings_field('fallback_min_days', __('Dias Padrão (mínimo)', 'autocep'), function () {
            $this->render_number('delivery_estimate', 'fallback_min_days', 0, 60);
        }, $page, 'autocep_growth_delivery');

        add_settings_field('fallback_max_days', __('Dias Padrão (máximo)', 'autocep'), function () {
            $this->render_number('delivery_estimate', 'fallback_max_days', 0, 60);
        }, $page, 'autocep_growth_delivery');

        add_settings_section('autocep_growth_free_shipping', __('Barra de Progresso de Frete Grátis', 'autocep'), function () {
            echo '<p>' . esc_html__('Exibe uma barra de progresso motivacional nas caixas de simulação de frete (produto, carrinho e checkout) mostrando quanto falta em reais para o cliente ganhar frete grátis — ex.: "Faltam apenas R$ 29,00 para você ganhar Frete Grátis!".', 'autocep') . '</p>';
        }, $page);

        add_settings_field('enabled', __('Ativar Barra de Frete Grátis', 'autocep'), function () {
            $this->render_checkbox('free_shipping_bar', 'enabled', __('Exibir a barra de progresso de frete grátis nas caixas do AutoCEP.', 'autocep'));
        }, $page, 'autocep_growth_free_shipping');

        add_settings_field('auto_detect', __('Detectar Valor Automaticamente', 'autocep'), function () {
            $this->render_checkbox('free_shipping_bar', 'auto_detect', __('Buscar automaticamente o valor mínimo configurado no método "Frete Grátis" das Zonas de Entrega do WooCommerce.', 'autocep'));
        }, $page, 'autocep_growth_free_shipping');

        add_settings_field('threshold', __('Valor Manual (R$)', 'autocep'), function () {
            $this->render_decimal('free_shipping_bar', 'threshold', __('Usado quando a detecção automática estiver desligada (ou não encontrar nenhum método de Frete Grátis configurado). Deixe 0 para depender apenas da detecção automática.', 'autocep'));
        }, $page, 'autocep_growth_free_shipping');

        add_settings_field('message_progress', __('Mensagem (Faltando)', 'autocep'), function () {
            $this->render_text('free_shipping_bar', 'message_progress', __('Use {missing} para inserir o valor restante já formatado em reais. Ex.: "Faltam {missing} para o Frete Grátis!".', 'autocep'));
        }, $page, 'autocep_growth_free_shipping');

        add_settings_field('message_achieved', __('Mensagem (Conquistado)', 'autocep'), function () {
            $this->render_text('free_shipping_bar', 'message_achieved', __('Exibida quando o cliente já atingiu o valor mínimo para frete grátis.', 'autocep'));
        }, $page, 'autocep_growth_free_shipping');
    }

    private function register_distance_shipping_tab_fields()
    {
        $page = 'autocep_tab_distance_shipping';

        add_settings_section('autocep_distance_api', __('Chave de API do Google Maps', 'autocep'), function () {
            echo '<p>' . wp_kses(
                __('Usada pelo módulo de Retirada em Loja para calcular a distância rodoviária real entre o CEP do cliente e cada loja cadastrada, via <strong>Google Distance Matrix API</strong>. Crie uma chave no <a href="https://console.cloud.google.com/google/maps-apis" target="_blank" rel="noopener noreferrer">Google Cloud Console</a> e habilite a "Distance Matrix API" para o projeto (essa API é paga acima de uma cota gratuita mensal do Google — consulte os preços atuais no próprio console). Os resultados ficam em cache por 7 dias, então o consumo tende a ser baixo em CEPs repetidos.', 'autocep'),
                array('strong' => array(), 'a' => array('href' => array(), 'target' => array(), 'rel' => array()))
            ) . '</p>';
        }, $page);

        add_settings_field('google_maps_api_key', __('Chave de API', 'autocep'), function () {
            $this->render_text('distance_shipping', 'google_maps_api_key', __('Cole aqui a chave da Distance Matrix API do Google Maps.', 'autocep'));
        }, $page, 'autocep_distance_api');

        add_settings_field('google_maps_tests', __('Testes e Diagnóstico', 'autocep'), array($this, 'render_google_maps_tests_field'), $page, 'autocep_distance_api');

        add_settings_section('autocep_distance_pickup', __('Retirada em Loja por Proximidade', 'autocep'), function () {
            echo '<p>' . esc_html__('Mostra automaticamente, como opção de frete, cada loja física cadastrada abaixo que esteja dentro do próprio raio configurado para o CEP do cliente — a distância é calculada por rota real, então lojas de cidades vizinhas também aparecem, desde que dentro do raio. Depois de habilitar aqui e cadastrar suas lojas (mais abaixo nesta página), vá em WooCommerce > Configurações > Entrega, abra a zona desejada e adicione o método "Retirada em Loja por Proximidade — AutoCEP".', 'autocep') . '</p>';
        }, $page);

        add_settings_field('pickup_enabled', __('Habilitar Módulo', 'autocep'), function () {
            $this->render_checkbox('distance_shipping', 'pickup_enabled', __('Disponibilizar o método "Retirada em Loja" para ser adicionado nas Zonas de Entrega.', 'autocep'));
        }, $page, 'autocep_distance_pickup');

        add_settings_section('autocep_distance_km', __('Frete por Quilometragem (Km) — Entrega Local / Motoboy', 'autocep'), function () {
            echo '<p>' . esc_html__('Calcula o frete automaticamente com base na distância rodoviária real até o cliente: Taxa Fixa + (Km × Valor por Km). Se a distância for maior que o Raio Máximo configurado, a opção fica oculta. Ideal para entrega local ou motoboy.', 'autocep') . '</p>';
            echo '<p><strong>' . esc_html__('Não precisa de Zona de Entrega no WooCommerce', 'autocep') . '</strong> — ' . esc_html__('configure tudo abaixo e ative. O módulo injeta a opção automaticamente nas simulações do AutoCEP (produto, carrinho e checkout) sempre que o CEP do cliente estiver dentro do raio definido.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('km_enabled', __('Ativar Frete por Km', 'autocep'), function () {
            $this->render_checkbox('km_delivery', 'enabled', __('Exibir opção de "Entrega Local / Motoboy" calculada por distância rodoviária.', 'autocep'));
        }, $page, 'autocep_distance_km');

        add_settings_field('km_title', __('Título da Opção', 'autocep'), function () {
            $this->render_text('km_delivery', 'title', __('Nome exibido ao cliente. Ex.: "Entrega Expressa", "Motoboy".', 'autocep'));
        }, $page, 'autocep_distance_km');

        add_settings_field('km_origin_cep', __('CEP de Origem', 'autocep'), function () {
            $this->render_text('km_delivery', 'origin_cep', __('CEP do seu estabelecimento / ponto de saída. Deixe em branco para usar o CEP da loja configurado no WooCommerce.', 'autocep'));
        }, $page, 'autocep_distance_km');

        add_settings_field('km_base_fee', __('Taxa Fixa (R$)', 'autocep'), function () {
            $this->render_decimal('km_delivery', 'base_fee', __('Valor base cobrado independentemente da distância. Ex.: R$ 5,00.', 'autocep'));
        }, $page, 'autocep_distance_km');

        add_settings_field('km_price_per_km', __('Valor por Km (R$)', 'autocep'), function () {
            $this->render_decimal('km_delivery', 'price_per_km', __('Valor cobrado por cada quilômetro rodado. Ex.: R$ 2,00 → 10 km = R$ 20,00 + taxa fixa.', 'autocep'));
        }, $page, 'autocep_distance_km');

        add_settings_field('km_max_radius_km', __('Raio Máximo (Km)', 'autocep'), function () {
            $this->render_number('km_delivery', 'max_radius_km', 0.1, 999, __('Se a distância até o cliente for maior que este valor, a opção não aparece.', 'autocep'));
        }, $page, 'autocep_distance_km');

        add_settings_field('km_min_fee', __('Valor Mínimo do Frete (R$)', 'autocep'), function () {
            $this->render_decimal('km_delivery', 'min_fee', __('Cobrado quando o resultado calculado (Taxa Fixa + Km × Valor por Km) for menor que este mínimo. Use 0 para desativar.', 'autocep'));
        }, $page, 'autocep_distance_km');

        add_settings_field('km_round_km', __('Arredondar Distância para Cima', 'autocep'), function () {
            $this->render_checkbox('km_delivery', 'round_km', __('Usa Km inteiro (arredondado para cima) ao calcular o valor final. Ex.: 7,3 km vira 8 km.', 'autocep'));
        }, $page, 'autocep_distance_km');
    }

    private function register_integrations_tab_fields()
    {
        $page = 'autocep_tab_integrations';

        add_settings_section('autocep_integrations_intro', __('Testes de Conexão de API', 'autocep'), function () {
            echo '<p>' . esc_html__('Testa, em tempo real, se as APIs das transportadoras estão ativas, bem configuradas ou fora do ar — sem precisar simular um pedido completo. O AutoCEP lê automaticamente o token já salvo pelos plugins oficiais do Melhor Envio e da Frenet; não é preciso preencher nada aqui. Esta aba não substitui esses plugins (que continuam responsáveis pelo cálculo real do frete), ela apenas testa a conexão.', 'autocep') . '</p>';
        }, $page);

        add_settings_section('autocep_integrations_melhor_envio', __('Melhor Envio', 'autocep'), function () {
            echo '<p>' . esc_html__('Detecta automaticamente o token salvo pelo plugin oficial do Melhor Envio (produção ou sandbox, conforme o ambiente ativo lá) e testa a conexão.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('melhor_envio_test', __('Testar Conexão', 'autocep'), array($this, 'render_melhor_envio_test_field'), $page, 'autocep_integrations_melhor_envio');

        add_settings_section('autocep_integrations_frenet', __('Frenet', 'autocep'), function () {
            echo '<p>' . esc_html__('Detecta automaticamente o token e o CEP de origem já salvos numa Zona de Entrega com o método oficial da Frenet configurado, e testa a conexão através de uma cotação simulada.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('frenet_test', __('Testar Conexão', 'autocep'), array($this, 'render_frenet_test_field'), $page, 'autocep_integrations_frenet');
    }

    private function detect_melhor_envio_token()
    {
        $ambiente = get_option('wpmelhorenvio_token_environment', 'production');
        $ambiente = in_array($ambiente, array('production', 'sandbox'), true) ? $ambiente : 'production';

        $token = 'sandbox' === $ambiente
            ? get_option('wpmelhorenvio_token_sandbox', '')
            : get_option('wpmelhorenvio_token', '');

        return array(
            'token'    => trim((string) $token),
            'ambiente' => $ambiente,
        );
    }

    private function detect_frenet_credentials()
    {
        if (!class_exists('WC_Shipping_Zones')) {
            return array('token' => '', 'origin_cep' => '');
        }

        $zonas = array();

        foreach (WC_Shipping_Zones::get_zones() as $zona_dados) {
            $zona = WC_Shipping_Zones::get_zone($zona_dados['zone_id']);

            if ($zona) {
                $zonas[] = $zona;
            }
        }

        $zonas[] = new WC_Shipping_Zone(0);

        foreach ($zonas as $zona) {
            if (!method_exists($zona, 'get_shipping_methods')) {
                continue;
            }

            foreach ($zona->get_shipping_methods(true) as $metodo) {
                if ('frenet' !== $metodo->id || !method_exists($metodo, 'get_option')) {
                    continue;
                }

                $token = trim((string) $metodo->get_option('token'));

                if ('' === $token) {
                    continue;
                }

                return array(
                    'token'      => $token,
                    'origin_cep' => preg_replace('/\D/', '', (string) $metodo->get_option('zip_origin')),
                );
            }
        }

        return array('token' => '', 'origin_cep' => '');
    }

    private function register_general_tab_fields()
    {
        $page = 'autocep_tab_general';

        add_settings_section('autocep_general_checkout', __('Autocompletar no Checkout', 'autocep'), '__return_false', $page);

        add_settings_field('enable_billing', __('Cobrança (Billing)', 'autocep'), function () {
            $this->render_checkbox('general', 'enable_billing', __('Autocompletar endereço de cobrança ao digitar o CEP.', 'autocep'));
        }, $page, 'autocep_general_checkout');

        add_settings_field('enable_shipping', __('Entrega (Shipping)', 'autocep'), function () {
            $this->render_checkbox('general', 'enable_shipping', __('Autocompletar endereço de entrega ao digitar o CEP.', 'autocep'));
        }, $page, 'autocep_general_checkout');

        add_settings_field('auto_focus', __('Foco Automático', 'autocep'), function () {
            $this->render_checkbox('general', 'auto_focus', __('Mover o cursor automaticamente para o campo "Número" após preencher o endereço.', 'autocep'));
        }, $page, 'autocep_general_checkout');

        add_settings_field('neighborhood_field', __('Campo do Bairro', 'autocep'), function () {
            $this->render_text('general', 'neighborhood_field', __('Usado só como último recurso: o plugin detecta automaticamente campos comuns de bairro na página (ex.: billing_neighborhood, billing_bairro, billing_district) antes de usar este valor. Só preencha aqui se sua loja usa um ID de campo diferente desses.', 'autocep'));
        }, $page, 'autocep_general_checkout');

        add_settings_field('browser_autocomplete', __('Autocompletar do Navegador', 'autocep'), function () {
            $this->render_checkbox('general', 'browser_autocomplete', __('Ativar sugestões automáticas do navegador (autocomplete) enquanto o cliente digita nos campos de endereço, com base no histórico de preenchimentos anteriores.', 'autocep'));
        }, $page, 'autocep_general_checkout');

        add_settings_field('address_suggest', __('Sugestões de Endereço ao Digitar', 'autocep'), function () {
            $this->render_checkbox('general', 'address_suggest', __('Mostrar uma lista de ruas reais enquanto o cliente digita no campo Endereço. Ao escolher, preenche rua, bairro, cidade, estado e o CEP — ideal para quem não sabe o CEP.', 'autocep'));
            echo '<p style="margin-top:8px;">';
            $this->render_select('general', 'address_suggest_provider', array(
                'auto'   => __('Automático — ViaCEP quando houver cidade e estado; Google Maps quando houver chave de API (recomendado)', 'autocep'),
                'viacep' => __('Somente ViaCEP — grátis (precisa da cidade e do estado preenchidos)', 'autocep'),
                'google' => __('Somente Google Maps — não precisa da cidade (usa a chave de API da aba Retirada em Loja; cobrado pelo Google)', 'autocep'),
            ));
            echo '</p>';

            $tem_chave = class_exists('AutoCEP_Maps_Service') && '' !== AutoCEP_Maps_Service::get_api_key();
            echo '<p class="description">' . esc_html($tem_chave
                ? __('Chave do Google encontrada. Para usar o Google, ative a "Places API (New)" no Google Cloud para essa chave. A consulta passa pelo servidor: a chave nunca aparece para o cliente.', 'autocep')
                : __('Sem chave do Google configurada: as sugestões usam o ViaCEP (grátis), depois que o cliente informa a cidade e o estado.', 'autocep')) . '</p>';
        }, $page, 'autocep_general_checkout');

        add_settings_section('autocep_general_apis', __('APIs de Consulta de CEP', 'autocep'), function () {
            echo '<p>' . esc_html__('Selecione quais APIs ficam ativas e defina a ordem de prioridade (1 = primeira a ser consultada). Se uma API falhar ou expirar o tempo limite, a próxima da lista é consultada automaticamente.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('apis', __('APIs Ativas e Prioridade', 'autocep'), array($this, 'render_apis_field'), $page, 'autocep_general_apis');

        add_settings_field('apis_status', __('Testar Velocidade das APIs', 'autocep'), array($this, 'render_apis_status_field'), $page, 'autocep_general_apis');

        add_settings_section('autocep_general_cache', __('Cache de CEPs', 'autocep'), '__return_false', $page);

        add_settings_field('cache_days', __('Duração do Cache (dias)', 'autocep'), function () {
            $this->render_number('general', 'cache_days', 0, 365, __('Quantos dias um CEP já consultado fica salvo em cache antes de ser buscado novamente nas APIs.', 'autocep'));
        }, $page, 'autocep_general_cache');

        add_settings_field('clear_cache', __('Limpar Cache Agora', 'autocep'), array($this, 'render_clear_cache_button'), $page, 'autocep_general_cache');

        add_settings_section('autocep_general_shipping_cache', __('Cache de Cotações de Frete', 'autocep'), function () {
            echo '<p>' . esc_html__('Diferente do cache de CEPs acima (que guarda apenas o endereço), este cache guarda o próprio resultado do cálculo de frete — o valor e o prazo de cada transportadora — para o mesmo CEP combinado com os mesmos itens do carrinho. Isso deixa simulações repetidas quase instantâneas (menos de 0,1s) e reduz bastante o número de chamadas às APIs de frete (ex.: Melhor Envio, Frenet), especialmente em produtos ou CEPs muito pesquisados.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('shipping_cache_hours', __('Duração do Cache (horas)', 'autocep'), function () {
            $this->render_number('general', 'shipping_cache_hours', 0, 48, __('Quantas horas uma cotação de frete já calculada fica salva antes de ser recalculada. Recomendado: 24 a 48 horas. Use 0 para desativar este cache.', 'autocep'));
        }, $page, 'autocep_general_shipping_cache');

        add_settings_section('autocep_general_stats', __('Dashboard de Inteligência de Frete', 'autocep'), function () {
            echo '<p>' . esc_html__('Controla a coleta de dados usados na aba "Dashboard de Frete": quais CEPs/regiões mais pesquisam frete no seu site, o valor médio calculado vs. o valor médio efetivamente pago nos pedidos, e uma taxa estimada de desistência relacionada ao frete. Nenhum dado pessoal do cliente é armazenado além do CEP/cidade/estado pesquisado e dos valores de frete.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('stats_enabled', __('Coletar Estatísticas', 'autocep'), function () {
            $this->render_checkbox('general', 'stats_enabled', __('Habilitar a coleta de dados para o Dashboard de Frete.', 'autocep'));
        }, $page, 'autocep_general_stats');
    }

    private function register_product_shipping_tab_fields()
    {
        $page = 'autocep_tab_product_shipping';

        add_settings_section('autocep_product_shipping_main', __('Simulador de Frete no Produto', 'autocep'), function () {
            echo '<p>' . esc_html__('O cálculo usa os métodos já configurados em WooCommerce > Configurações > Entrega (Zonas de Entrega). Funciona automaticamente com Frete Fixo, Frete Grátis, Correios ou qualquer integração de transportadora instalada (ex.: Melhor Envio, Frenet) — basta que o método esteja habilitado na zona correspondente ao CEP simulado.', 'autocep') . '</p>';
            echo '<p>' . esc_html__('Se o plugin Melhor Envio estiver instalado, sua própria caixa de frete na página de produto é desligada automaticamente enquanto este simulador estiver habilitado, para evitar duas caixas na tela. A cotação real do Melhor Envio continua funcionando normalmente.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('enabled', __('Ativar Simulador', 'autocep'), function () {
            $this->render_checkbox('product_shipping', 'enabled', __('Exibir a caixa de cálculo de frete na página do produto.', 'autocep'));
        }, $page, 'autocep_product_shipping_main');

        add_settings_field('hook', __('Posição na Página', 'autocep'), function () {
            $this->render_select('product_shipping', 'hook', self::get_product_hook_choices());
            echo '<p class="description">' . esc_html__('Escolha "Manual" se preferir inserir o shortcode [shipping_calculator_on_product_page] diretamente na descrição do produto ou no construtor de páginas (Elementor etc.) — útil para posicionar a caixa em um local específico. Compatível com o shortcode do plugin antigo de calculadora de frete.', 'autocep') . '</p>';
        }, $page, 'autocep_product_shipping_main');

        add_settings_field('consider_quantity', __('Considerar Quantidade', 'autocep'), function () {
            $this->render_checkbox('product_shipping', 'consider_quantity', __('Usar a quantidade selecionada no seletor do produto para calcular o frete.', 'autocep'));
        }, $page, 'autocep_product_shipping_main');

        add_settings_field('title', __('Título da Caixa', 'autocep'), function () {
            $this->render_text('product_shipping', 'title');
        }, $page, 'autocep_product_shipping_main');
    }

    private function register_cart_shipping_tab_fields()
    {
        $page = 'autocep_tab_cart_shipping';

        add_settings_section('autocep_cart_shipping_main', __('Simulador de Frete no Carrinho', 'autocep'), function () {
            echo '<p>' . esc_html__('Exibe uma caixa de simulação de frete na página do carrinho de compras (antes de o cliente ir para o checkout), usando os itens já adicionados. Assim como nas demais telas, reconhece automaticamente Frete Fixo, Frete Grátis, Correios, Melhor Envio, Frenet etc.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('enabled', __('Ativar Simulador', 'autocep'), function () {
            $this->render_checkbox('cart_shipping', 'enabled', __('Exibir a caixa de cálculo de frete na página do carrinho.', 'autocep'));
        }, $page, 'autocep_cart_shipping_main');

        add_settings_field('position', __('Posição na Página', 'autocep'), function () {
            $this->render_select('cart_shipping', 'position', self::get_cart_hook_choices());
        }, $page, 'autocep_cart_shipping_main');

        add_settings_field('title', __('Título da Caixa', 'autocep'), function () {
            $this->render_text('cart_shipping', 'title');
        }, $page, 'autocep_cart_shipping_main');
    }

    private function register_checkout_shipping_tab_fields()
    {
        $page = 'autocep_tab_checkout_shipping';

        add_settings_section('autocep_checkout_shipping_main', __('Simulador de Frete no Checkout', 'autocep'), function () {
            echo '<p>' . esc_html__('Assim como no produto, o cálculo usa as Zonas de Entrega do WooCommerce e reconhece automaticamente métodos de transportadoras de terceiros (Melhor Envio, Frenet etc.) já configurados na loja.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('enabled', __('Ativar Simulador', 'autocep'), function () {
            $this->render_checkbox('checkout_shipping', 'enabled', __('Exibir uma caixa de cálculo rápido de frete por CEP no checkout.', 'autocep'));
        }, $page, 'autocep_checkout_shipping_main');

        add_settings_field('position', __('Posição no Formulário', 'autocep'), function () {
            $this->render_select('checkout_shipping', 'position', self::get_checkout_hook_choices());
        }, $page, 'autocep_checkout_shipping_main');
    }

    private function register_post_purchase_tab_fields()
    {
        $page = 'autocep_tab_post_purchase';

        add_settings_section('autocep_post_purchase_main', __('Status do Pedido em Tempo Real', 'autocep'), function () {
            echo '<p>' . esc_html__('Na página de pedido recebido (e na página do pedido em Minha Conta), o AutoCEP acompanha o status do pedido enquanto o cliente está na tela — por exemplo, aguardando o Pix. Quando o status muda, aparece uma mensagem e, se o pagamento foi aprovado, o cliente pode ser levado para a sua página de agradecimento.', 'autocep') . '</p>';
            echo '<p class="description">' . esc_html__('Seguro e leve: só o dono do pedido consulta o status (pela chave secreta do pedido), a verificação roda apenas nessas páginas, a cada 5 segundos, por até 15 minutos.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('enabled', __('Ativar', 'autocep'), function () {
            $this->render_checkbox('order_status', 'enabled', __('Mostrar mensagem quando o status do pedido mudar (pagamento aprovado, falhou, cancelado...).', 'autocep'));
        }, $page, 'autocep_post_purchase_main');

        add_settings_section('autocep_post_purchase_redirect', __('Página de Obrigado (Redirecionamento)', 'autocep'), function () {
            echo '<p>' . esc_html__('Quando o pagamento for aprovado, levar o cliente para uma página sua (ex.: página de agradecimento, grupo VIP, área de membros). Deixe em branco para ele continuar na página de pedido recebido do WooCommerce. Pedidos com Pix ou boleto pendente continuam na página do WooCommerce até o pagamento ser aprovado — assim o cliente não perde o QR Code ou o boleto.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('redirect_url', __('Endereço da Página', 'autocep'), function () {
            $this->render_text('order_status', 'redirect_url', __('Ex.: https://sualoja.com.br/obrigado/ — use {pedido} para incluir o número do pedido (ex.: https://sualoja.com.br/obrigado/?pedido={pedido}).', 'autocep'));
        }, $page, 'autocep_post_purchase_redirect');

        add_settings_field('redirect_delay', __('Esperar Antes de Redirecionar', 'autocep'), function () {
            $this->render_number('order_status', 'redirect_delay', 0, 30, __('Segundos com a mensagem na tela antes de ir para a página (0 = imediato).', 'autocep'));
        }, $page, 'autocep_post_purchase_redirect');

        add_settings_field('paid_on_load', __('Pedido Já Pago', 'autocep'), function () {
            $this->render_checkbox('order_status', 'paid_on_load', __('Também mostrar a mensagem e redirecionar quando o pedido já chega aprovado (ex.: cartão de crédito aprovado na hora).', 'autocep'));
        }, $page, 'autocep_post_purchase_redirect');

        add_settings_section('autocep_post_purchase_messages', __('Mensagens por Status', 'autocep'), function () {
            echo '<p>' . esc_html__('Use {pedido} para o número do pedido e {nome} para o primeiro nome do cliente. Deixe em branco para não mostrar mensagem naquele status.', 'autocep') . '</p>';
        }, $page);

        foreach (AutoCEP_Order_Status::get_statuses() as $status => $rotulo) {
            add_settings_field('msg_' . $status, $rotulo, function () use ($status) {
                $this->render_textarea('order_status', 'msg_' . $status);
            }, $page, 'autocep_post_purchase_messages');
        }
    }

    private function register_modern_checkout_tab_fields()
    {
        // Mesma aba do simulador de frete do checkout: tudo de checkout fica
        // numa aba só (Checkout), logo abaixo do simulador.
        $page = 'autocep_tab_checkout_shipping';

        add_settings_section('autocep_modern_checkout_main', __('Checkout Moderno (Multietapas)', 'autocep'), function () {
            echo '<p>' . esc_html__('Divide o checkout clássico do WooCommerce em 3 etapas — Identificação, Entrega e Pagamento — com resumo do pedido sempre visível, validação a cada etapa e layout que se adapta à forma de pagamento escolhida. Todos os campos continuam sendo os do WooCommerce, então gateways de pagamento, plugins de campos brasileiros (CPF/CNPJ, número, bairro) e o autocompletar de CEP do AutoCEP continuam funcionando.', 'autocep') . '</p>';

            $motivo = class_exists('AutoCEP_Modern_Checkout') ? AutoCEP_Modern_Checkout::get_incompatibility_reason() : '';

            if ($motivo) {
                printf('<div class="notice notice-warning inline"><p>%s</p></div>', esc_html($motivo));
            }
        }, $page);

        add_settings_field('enabled', __('Ativar Checkout Moderno', 'autocep'), function () {
            $this->render_checkbox('modern_checkout', 'enabled', __('Usar o checkout do AutoCEP em etapas (Identificação → Entrega → Pagamento), com o gerenciamento de campos abaixo.', 'autocep'));
            echo '<p class="description">' . esc_html__('Deixe desmarcado se a loja já usa outro checkout personalizado (CartFlows, Fluid Checkout, checkout do tema ou do Elementor): com a opção desligada, o AutoCEP não altera nada no checkout — nem o layout nem os campos.', 'autocep') . '</p>';
        }, $page, 'autocep_modern_checkout_main');

        add_settings_field('simulator', __('Simulador no Checkout Moderno', 'autocep'), function () {
            $this->render_select('modern_checkout', 'simulator', array(
                'top'  => __('No topo da etapa Entrega (padrão)', 'autocep'),
                'hide' => __('Ocultar — as formas de entrega já aparecem na etapa Entrega', 'autocep'),
            ));
            echo '<p class="description">' . esc_html__('Onde a caixa "Simular Frete" do AutoCEP aparece no Checkout Moderno (quando ativada na aba Checkout). No topo, o CEP digitado nela preenche o endereço e as formas de entrega da etapa.', 'autocep') . '</p>';
        }, $page, 'autocep_modern_checkout_main');

        add_settings_field('account_mode', __('Conta do Cliente', 'autocep'), function () {
            $this->render_select('modern_checkout', 'account_mode', array(
                'woocommerce' => __('Seguir a configuração do WooCommerce (padrão)', 'autocep'),
                'optional'    => __('Opcional — o cliente escolhe criar conta ou comprar sem conta (recomendado)', 'autocep'),
                'required'    => __('Obrigatória — toda compra cria uma conta', 'autocep'),
                'guest'       => __('Sem conta — compra sempre como visitante', 'autocep'),
            ));
            echo '<p class="description">' . esc_html__('Exigir conta faz muitos clientes desistirem; "Opcional" costuma vender mais. Vale enquanto o Checkout Moderno estiver ativo, sem alterar as configurações do WooCommerce.', 'autocep') . '</p>';
        }, $page, 'autocep_modern_checkout_main');

        add_settings_field('account_notice', __('Aviso de Criação de Conta', 'autocep'), function () {
            $this->render_checkbox('modern_checkout', 'account_notice', __('Na etapa Identificação, explicar ao cliente que não tem conta por que e como ela é criada, com o link "Já tem conta? Entrar".', 'autocep'));
            echo '<p style="margin-top:8px;">' . esc_html__('Texto personalizado (opcional — deixe em branco para o texto automático, que se adapta à configuração da loja). Use {email} para mostrar o e-mail digitado pelo cliente:', 'autocep') . '</p>';
            $this->render_textarea('modern_checkout', 'account_message');

            if (function_exists('WC') && WC()->checkout()) {
                $obrigatoria = WC()->checkout()->is_registration_required();
                $permitida   = WC()->checkout()->is_registration_enabled();
                $auto        = 'yes' === get_option('woocommerce_registration_generate_password');

                printf(
                    '<p class="description">%s <a href="%s">%s</a></p>',
                    esc_html($obrigatoria
                        ? ($auto ? __('Sua loja hoje: conta obrigatória, senha gerada automaticamente e enviada por e-mail.', 'autocep') : __('Sua loja hoje: conta obrigatória — o cliente cria uma senha no checkout.', 'autocep'))
                        : ($permitida ? __('Sua loja hoje: compra sem conta permitida; criar conta é opcional.', 'autocep') : __('Sua loja hoje: compra sem conta, sem opção de criar conta no checkout.', 'autocep'))),
                    esc_url(admin_url('admin.php?page=wc-settings&tab=account')),
                    esc_html__('Alterar em WooCommerce > Configurações > Contas e privacidade', 'autocep')
                );
            }
        }, $page, 'autocep_modern_checkout_main');

        add_settings_field('compact', __('Modo Compacto', 'autocep'), function () {
            $this->render_checkbox('modern_checkout', 'compact', __('Esconder campos opcionais (empresa, complemento quando não usado para o bairro, observações do pedido) atrás de links "+ adicionar", deixando cada etapa mais curta.', 'autocep'));
        }, $page, 'autocep_modern_checkout_main');

        add_settings_field('step_titles', __('Títulos das Etapas', 'autocep'), function () {
            foreach (array('step1_title', 'step2_title', 'step3_title') as $indice => $campo) {
                printf('<p><span style="display:inline-block;width:60px;">%s</span>', esc_html(sprintf(__('Etapa %d', 'autocep'), $indice + 1)));
                $this->render_text('modern_checkout', $campo);
                echo '</p>';
            }
        }, $page, 'autocep_modern_checkout_main');

        add_settings_field('next_label', __('Texto do Botão de Avançar', 'autocep'), function () {
            $this->render_text('modern_checkout', 'next_label');
        }, $page, 'autocep_modern_checkout_main');

        add_settings_field('ship_different', __('Entregar em Endereço Diferente', 'autocep'), function () {
            $this->render_select('modern_checkout', 'ship_different', array(
                'unchecked'   => __('Começa desmarcado — o cliente marca se quiser entregar em outro endereço (padrão)', 'autocep'),
                'woocommerce' => __('Seguir a configuração do WooCommerce (Destino de entrega)', 'autocep'),
            ));
        }, $page, 'autocep_modern_checkout_main');

        add_settings_field('layout_size', __('Tamanho do Checkout', 'autocep'), function () {
            echo '<p><label style="display:inline-block;width:210px;">' . esc_html__('Largura do formulário (px)', 'autocep') . '</label>';
            $this->render_number('modern_checkout', 'form_width', 400, 1200);
            echo '</p><p><label style="display:inline-block;width:210px;">' . esc_html__('Largura do resumo do pedido (px)', 'autocep') . '</label>';
            $this->render_number('modern_checkout', 'summary_width', 260, 600);
            echo '</p><p><label style="display:inline-block;width:210px;">' . esc_html__('Altura dos campos (px)', 'autocep') . '</label>';
            $this->render_number('modern_checkout', 'field_height', 32, 60);
            echo '</p><p class="description">' . esc_html__('Padrão: formulário 580, resumo 340, campos 38. As larguras valem no computador (a partir de 992px); no celular o checkout ocupa a tela toda. No celular os campos têm no mínimo 16px de fonte para o iPhone não dar zoom.', 'autocep') . '</p>';
        }, $page, 'autocep_modern_checkout_main');

        add_settings_field('accent_color', __('Cor de Destaque', 'autocep'), function () {
            $this->render_color_field('modern_checkout', 'accent_color');
            echo '<p class="description">' . esc_html__('Usada na barra de etapas, nos botões e na forma de pagamento selecionada.', 'autocep') . '</p>';
        }, $page, 'autocep_modern_checkout_main');

        add_settings_section('autocep_modern_checkout_payment', __('Mensagens por Forma de Pagamento', 'autocep'), function () {
            echo '<p>' . esc_html__('Exibidas na etapa de pagamento conforme o método escolhido. O tipo (Pix, cartão ou boleto) é detectado pelo nome/ID de cada gateway. Deixe em branco para não exibir.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('pix_message', __('Pix', 'autocep'), function () {
            $this->render_textarea('modern_checkout', 'pix_message');
        }, $page, 'autocep_modern_checkout_payment');

        add_settings_field('card_message', __('Cartão de Crédito', 'autocep'), function () {
            $this->render_textarea('modern_checkout', 'card_message');
        }, $page, 'autocep_modern_checkout_payment');

        add_settings_field('boleto_message', __('Boleto', 'autocep'), function () {
            $this->render_textarea('modern_checkout', 'boleto_message');
        }, $page, 'autocep_modern_checkout_payment');

        add_settings_field('pix_thankyou', __('Pix no Pedido Recebido', 'autocep'), function () {
            $this->render_checkbox('modern_checkout', 'pix_thankyou', __('Na página de pedido recebido, destacar o QR Code Pix e adicionar o botão "Copiar código Pix" (funciona com a maioria dos gateways de Pix).', 'autocep'));
        }, $page, 'autocep_modern_checkout_payment');

        add_settings_section('autocep_modern_checkout_fields', __('Campos do Checkout', 'autocep'), function () {
            echo '<p>' . esc_html__('Menos campos = mais vendas. Esconda o que a loja não usa, defina o que é obrigatório e crie campos extras. Vale enquanto o Checkout Moderno estiver ativo. E-mail, nome, CEP, endereço, cidade, estado e país são sempre exibidos, pois são necessários para o pedido e o frete.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('fields', __('Campos Padrão', 'autocep'), array($this, 'render_checkout_fields_table'), $page, 'autocep_modern_checkout_fields');
        add_settings_field('custom_fields', __('Campos Extras', 'autocep'), array($this, 'render_custom_fields_editor'), $page, 'autocep_modern_checkout_fields');

        add_settings_section('autocep_checkout_delivery_payment', __('Pagamento na Entrega', 'autocep'), function () {
            $config = get_option('woocommerce_autocep_entrega_settings', array());
            $ativo  = !empty($config['enabled']) && 'yes' === $config['enabled'];
            $url    = admin_url('admin.php?page=wc-settings&tab=checkout&section=autocep_entrega');

            echo '<p>' . esc_html__('Forma de pagamento do AutoCEP para delivery e retirada: o cliente escolhe como vai pagar ao receber — dinheiro (com "Troco para quanto?"), Pix na entrega, cartão de débito/crédito na maquininha ou vale-refeição. Você escolhe as formas aceitas e para quais fretes ela aparece (ex.: só Motoboy e Retirada). A escolha e o troco aparecem no pedido e nos e-mails.', 'autocep') . '</p>';

            printf(
                '<p><span style="display:inline-block;padding:2px 10px;border-radius:12px;font-weight:600;%1$s">%2$s</span> &nbsp; <a class="button button-primary" href="%3$s">%4$s</a></p>',
                $ativo ? 'background:#dcfce7;color:#15803d;' : 'background:#f3f4f6;color:#4b5563;',
                esc_html($ativo ? __('Ativo', 'autocep') : __('Desativado', 'autocep')),
                esc_url($url),
                esc_html__('Configurar Pagamento na Entrega', 'autocep')
            );

            echo '<p class="description">' . esc_html__('Ele também aparece em WooCommerce > Configurações > Pagamentos, como "Pagamento na Entrega — AutoCEP".', 'autocep') . '</p>';
        }, $page);
    }

    public function render_checkout_fields_table()
    {
        $options = self::get_options();
        $salvos  = isset($options['modern_checkout']['fields']) ? $options['modern_checkout']['fields'] : array();
        $base    = AUTOCEP_OPTION_KEY . '[modern_checkout][fields]';
        ?>
        <table class="widefat striped" style="max-width:720px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Campo', 'autocep'); ?></th>
                    <th style="width:80px;"><?php esc_html_e('Exibir', 'autocep'); ?></th>
                    <th style="width:190px;"><?php esc_html_e('Preenchimento', 'autocep'); ?></th>
                    <th><?php esc_html_e('Rótulo personalizado', 'autocep'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (AutoCEP_Modern_Checkout::get_managed_fields() as $chave => $rotulo) :
                    $atual    = isset($salvos[$chave]) ? $salvos[$chave] : array();
                    $exibir   = !isset($atual['show']) || !empty($atual['show']);
                    $obrig    = isset($atual['required']) ? $atual['required'] : '';
                    $nome     = $base . '[' . $chave . ']';
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($rotulo); ?></strong></td>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr($nome . '[show]'); ?>" value="0" />
                            <input type="checkbox" name="<?php echo esc_attr($nome . '[show]'); ?>" value="1" <?php checked($exibir); ?> aria-label="<?php echo esc_attr(sprintf(__('Exibir %s', 'autocep'), $rotulo)); ?>" />
                        </td>
                        <td>
                            <select name="<?php echo esc_attr($nome . '[required]'); ?>">
                                <option value="" <?php selected($obrig, ''); ?>><?php esc_html_e('Padrão do WooCommerce', 'autocep'); ?></option>
                                <option value="yes" <?php selected($obrig, 'yes'); ?>><?php esc_html_e('Obrigatório', 'autocep'); ?></option>
                                <option value="no" <?php selected($obrig, 'no'); ?>><?php esc_html_e('Opcional', 'autocep'); ?></option>
                            </select>
                        </td>
                        <td>
                            <input type="text" class="regular-text" style="width:100%;" name="<?php echo esc_attr($nome . '[label]'); ?>" value="<?php echo esc_attr(isset($atual['label']) ? $atual['label'] : ''); ?>" placeholder="<?php echo esc_attr($rotulo); ?>" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description"><?php esc_html_e('Empresa, complemento e sobrenome valem também para o endereço de entrega diferente.', 'autocep'); ?></p>
        <?php
    }

    /**
     * Campos reais do checkout (WooCommerce + outros plugins) para comparar
     * identificadores. Fora do checkout o WooCommerce pode não estar
     * disponível; nesse caso devolve null e a checagem de conflito é pulada.
     *
     * @return array|null
     */
    private static function get_checkout_fields_snapshot()
    {
        static $campos = false;

        if (false !== $campos) {
            return $campos;
        }

        $campos = null;

        if (function_exists('WC') && is_object(WC()) && method_exists(WC(), 'checkout') && WC()->checkout()) {
            $obtidos = WC()->checkout()->get_checkout_fields();
            $campos  = is_array($obtidos) ? $obtidos : null;
        }

        return $campos;
    }

    public function render_custom_fields_editor()
    {
        $options = self::get_options();
        $campos  = isset($options['modern_checkout']['custom_fields']) && is_array($options['modern_checkout']['custom_fields'])
            ? array_values($options['modern_checkout']['custom_fields'])
            : array();
        $tipos   = AutoCEP_Modern_Checkout::get_custom_field_types();
        $base    = AUTOCEP_OPTION_KEY . '[modern_checkout][custom_fields]';

        // Linha de um campo; "__i__" é trocado pelo índice no modelo do JS.
        $linha = function ($indice, $campo) use ($tipos, $base) {
            $n = $base . '[' . $indice . ']';
            ob_start();
            ?>
            <?php
            $aviso_campo = isset($campo['key']) ? AutoCEP_Modern_Checkout::validate_custom_field($campo, self::get_checkout_fields_snapshot()) : '';
            ?>
            <tr class="autocep-cf-row">
                <td>
                    <input type="hidden" name="<?php echo esc_attr($n . '[key]'); ?>" value="<?php echo esc_attr(isset($campo['key']) ? $campo['key'] : ''); ?>" />
                    <input type="text" style="width:100%;" name="<?php echo esc_attr($n . '[label]'); ?>" value="<?php echo esc_attr(isset($campo['label']) ? $campo['label'] : ''); ?>" placeholder="<?php esc_attr_e('Ex.: Ponto de referência', 'autocep'); ?>" />
                </td>
                <td>
                    <select name="<?php echo esc_attr($n . '[type]'); ?>" class="autocep-cf-type">
                        <?php foreach ($tipos as $valor => $rotulo) : ?>
                            <option value="<?php echo esc_attr($valor); ?>" <?php selected(isset($campo['type']) ? $campo['type'] : 'text', $valor); ?>><?php echo esc_html($rotulo); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" style="width:100%;margin-top:4px;" class="autocep-cf-options" name="<?php echo esc_attr($n . '[options]'); ?>" value="<?php echo esc_attr(isset($campo['options']) ? $campo['options'] : ''); ?>" placeholder="<?php esc_attr_e('Opções separadas por vírgula', 'autocep'); ?>" />
                </td>
                <td>
                    <select name="<?php echo esc_attr($n . '[step]'); ?>">
                        <option value="1" <?php selected(isset($campo['step']) ? (int) $campo['step'] : 2, 1); ?>><?php esc_html_e('Identificação', 'autocep'); ?></option>
                        <option value="2" <?php selected(isset($campo['step']) ? (int) $campo['step'] : 2, 2); ?>><?php esc_html_e('Entrega', 'autocep'); ?></option>
                    </select>
                </td>
                <td style="text-align:center;">
                    <input type="hidden" name="<?php echo esc_attr($n . '[required]'); ?>" value="0" />
                    <input type="checkbox" name="<?php echo esc_attr($n . '[required]'); ?>" value="1" <?php checked(!empty($campo['required'])); ?> aria-label="<?php esc_attr_e('Obrigatório', 'autocep'); ?>" />
                </td>
                <td><button type="button" class="button-link-delete autocep-cf-remove"><?php esc_html_e('Remover', 'autocep'); ?></button></td>
            </tr>
            <?php if ('' !== $aviso_campo) : ?>
                <tr class="autocep-cf-warning"><td colspan="5" style="color:#b45309;background:#fffbeb;">⚠ <?php echo esc_html(sprintf(__('Este campo não aparece no checkout: %s', 'autocep'), $aviso_campo)); ?></td></tr>
            <?php endif; ?>
            <?php
            return ob_get_clean();
        };
        ?>
        <table class="widefat striped" style="max-width:900px;" id="autocep-cf-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Rótulo', 'autocep'); ?></th>
                    <th style="width:220px;"><?php esc_html_e('Tipo', 'autocep'); ?></th>
                    <th style="width:140px;"><?php esc_html_e('Etapa', 'autocep'); ?></th>
                    <th style="width:90px;"><?php esc_html_e('Obrigatório', 'autocep'); ?></th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campos as $indice => $campo) {
                    echo $linha($indice, $campo); // phpcs:ignore WordPress.Security.EscapeOutput -- montado com esc_* acima.
                } ?>
            </tbody>
        </table>
        <p>
            <button type="button" class="button" id="autocep-cf-add"><?php esc_html_e('+ Adicionar campo', 'autocep'); ?></button>
        </p>
        <p class="description"><?php esc_html_e('O valor preenchido pelo cliente fica salvo no pedido e aparece na tela do pedido (abaixo do endereço de cobrança) e nos e-mails.', 'autocep'); ?></p>
        <p class="description"><?php esc_html_e('Segurança do checkout: antes de salvar, o AutoCEP verifica se o WooCommerce aceita o campo (rótulo preenchido, tipo suportado, lista com opções, identificador sem conflito com outros campos). Campos que não passam não são adicionados e o motivo aparece no topo da página. Até 20 campos extras.', 'autocep'); ?></p>
        <script type="text/html" id="autocep-cf-template"><?php echo $linha('__i__', array()); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
        <script>
            jQuery(function ($) {
                var $corpo = $('#autocep-cf-table tbody');

                function atualizarOpcoes($linha) {
                    $linha.find('.autocep-cf-options').toggle('select' === $linha.find('.autocep-cf-type').val());
                }

                $corpo.find('.autocep-cf-row').each(function () { atualizarOpcoes($(this)); });

                $('#autocep-cf-add').on('click', function () {
                    // Índice único, para não sobrescrever linhas já existentes.
                    var indice = Date.now();
                    var $nova  = $($('#autocep-cf-template').html().replace(/__i__/g, indice));

                    $corpo.append($nova);
                    atualizarOpcoes($nova);
                    $nova.find('input[type="text"]').first().trigger('focus');
                });

                $corpo.on('click', '.autocep-cf-remove', function () {
                    $(this).closest('tr').remove();
                });

                $corpo.on('change', '.autocep-cf-type', function () {
                    atualizarOpcoes($(this).closest('tr'));
                });
            });
        </script>
        <?php
    }

    private function register_messages_tab_fields()
    {
        $page = 'autocep_tab_messages';

        add_settings_section('autocep_messages_texts', __('Textos Exibidos ao Cliente', 'autocep'), '__return_false', $page);

        add_settings_field('searching', __('Buscando Endereço', 'autocep'), function () {
            $this->render_text('messages', 'searching');
        }, $page, 'autocep_messages_texts');

        add_settings_field('not_found', __('CEP Não Encontrado', 'autocep'), function () {
            $this->render_text('messages', 'not_found');
        }, $page, 'autocep_messages_texts');

        add_settings_field('calculating', __('Calculando Frete', 'autocep'), function () {
            $this->render_text('messages', 'calculating');
        }, $page, 'autocep_messages_texts');

        add_settings_field('invalid_cep', __('CEP Inválido', 'autocep'), function () {
            $this->render_text('messages', 'invalid_cep');
        }, $page, 'autocep_messages_texts');

        add_settings_field('generic_error', __('Erro Genérico', 'autocep'), function () {
            $this->render_text('messages', 'generic_error');
        }, $page, 'autocep_messages_texts');

        add_settings_field('select_variation', __('Variação Não Selecionada', 'autocep'), function () {
            $this->render_text('messages', 'select_variation', __('Exibida quando o cliente tenta calcular o frete de um produto variável sem escolher as opções (cor, tamanho etc).', 'autocep'));
        }, $page, 'autocep_messages_texts');

        add_settings_field('no_street', __('CEP Sem Rua Cadastrada', 'autocep'), function () {
            $this->render_text('messages', 'no_street', __('Exibida no checkout quando o CEP é válido mas não possui uma rua específica cadastrada (comum em cidades pequenas) — cidade e estado são preenchidos, mas o endereço precisa ser digitado manualmente.', 'autocep'));
        }, $page, 'autocep_messages_texts');

        add_settings_field('select_rate', __('Botão "Selecionar Frete"', 'autocep'), function () {
            $this->render_text('messages', 'select_rate', __('Texto do botão exibido em cada opção de frete simulada no carrinho e no checkout, para o cliente aplicá-la ao pedido.', 'autocep'));
        }, $page, 'autocep_messages_texts');

        add_settings_field('selecting_rate', __('Aplicando Frete Selecionado', 'autocep'), function () {
            $this->render_text('messages', 'selecting_rate');
        }, $page, 'autocep_messages_texts');

        add_settings_field('rate_selected', __('Frete Selecionado com Sucesso', 'autocep'), function () {
            $this->render_text('messages', 'rate_selected');
        }, $page, 'autocep_messages_texts');

        add_settings_section('autocep_messages_ux', __('Comportamento', 'autocep'), '__return_false', $page);

        add_settings_field('block_checkout_button', __('Bloquear Botão "Finalizar Compra"', 'autocep'), function () {
            $this->render_checkbox('messages', 'block_checkout_button', __('Impede o envio do pedido enquanto uma requisição de CEP/Frete estiver em andamento.', 'autocep'));
        }, $page, 'autocep_messages_ux');
    }

    private function register_appearance_tab_fields()
    {
        $page = 'autocep_tab_appearance';

        add_settings_section('autocep_appearance_fields', __('Campo de CEP', 'autocep'), '__return_false', $page);

        add_settings_field('field_bg_color', __('Cor de Fundo', 'autocep'), function () {
            $this->render_color_field('appearance', 'field_bg_color');
        }, $page, 'autocep_appearance_fields');

        add_settings_field('field_border_color', __('Cor da Borda', 'autocep'), function () {
            $this->render_color_field('appearance', 'field_border_color');
        }, $page, 'autocep_appearance_fields');

        add_settings_field('field_text_color', __('Cor do Texto', 'autocep'), function () {
            $this->render_color_field('appearance', 'field_text_color');
        }, $page, 'autocep_appearance_fields');

        add_settings_section('autocep_appearance_button', __('Botão "Calcular"', 'autocep'), '__return_false', $page);

        add_settings_field('button_bg_color', __('Cor de Fundo', 'autocep'), function () {
            $this->render_color_field('appearance', 'button_bg_color');
        }, $page, 'autocep_appearance_button');

        add_settings_field('button_text_color', __('Cor do Texto', 'autocep'), function () {
            $this->render_color_field('appearance', 'button_text_color');
        }, $page, 'autocep_appearance_button');

        add_settings_field('button_hover_bg_color', __('Cor de Fundo (ao passar o mouse)', 'autocep'), function () {
            $this->render_color_field('appearance', 'button_hover_bg_color');
        }, $page, 'autocep_appearance_button');

        add_settings_section('autocep_appearance_box', __('Caixa de Frete (fundo, borda e arredondamento)', 'autocep'), '__return_false', $page);

        add_settings_field('box_bg_color', __('Cor de Fundo da Caixa', 'autocep'), function () {
            $this->render_color_field('appearance', 'box_bg_color');
        }, $page, 'autocep_appearance_box');

        add_settings_field('box_border_color', __('Cor da Borda da Caixa', 'autocep'), function () {
            $this->render_color_field('appearance', 'box_border_color');
        }, $page, 'autocep_appearance_box');

        add_settings_field('border_radius', __('Arredondamento (Caixa, Campo e Botão)', 'autocep'), function () {
            $this->render_select('appearance', 'border_radius', array(
                'nenhum' => __('Nenhum (cantos retos)', 'autocep'),
                'pequeno' => __('Pequeno', 'autocep'),
                'medio'   => __('Médio (padrão)', 'autocep'),
                'grande'  => __('Grande (bem arredondado)', 'autocep'),
            ));
        }, $page, 'autocep_appearance_box');

        add_settings_section('autocep_appearance_typography', __('Tipografia e Espaçamento', 'autocep'), '__return_false', $page);

        add_settings_field('font_size', __('Tamanho da Fonte', 'autocep'), function () {
            $this->render_select('appearance', 'font_size', array(
                'pequeno' => __('Pequeno', 'autocep'),
                'medio'   => __('Médio (padrão)', 'autocep'),
                'grande'  => __('Grande', 'autocep'),
            ));
        }, $page, 'autocep_appearance_typography');

        add_settings_field('spacing', __('Espaçamento Interno', 'autocep'), function () {
            $this->render_select('appearance', 'spacing', array(
                'compacto' => __('Compacto', 'autocep'),
                'normal'   => __('Normal (padrão)', 'autocep'),
                'espacoso' => __('Espaçoso', 'autocep'),
            ));
        }, $page, 'autocep_appearance_typography');

        add_settings_field('button_font_weight', __('Peso da Fonte do Botão', 'autocep'), function () {
            $this->render_select('appearance', 'button_font_weight', array(
                'normal'  => __('Normal (padrão)', 'autocep'),
                'negrito' => __('Negrito', 'autocep'),
            ));
        }, $page, 'autocep_appearance_typography');

        add_settings_section('autocep_appearance_freeship', __('Barra de Frete Grátis', 'autocep'), function () {
            echo '<p class="description">' . esc_html__('Controla a barra de progresso "Faltam R$ X para o Frete Grátis" exibida nas caixas de simulação (aba Growth Marketing precisa estar com a barra ativada).', 'autocep') . '</p>';
        }, $page);

        add_settings_field('free_shipping_bar_color', __('Cor da Barra (Em Progresso)', 'autocep'), function () {
            $this->render_color_field('appearance', 'free_shipping_bar_color');
        }, $page, 'autocep_appearance_freeship');

        add_settings_field('free_shipping_achieved_color', __('Cor da Barra ("Você Ganhou Frete Grátis!")', 'autocep'), function () {
            $this->render_color_field('appearance', 'free_shipping_achieved_color');
        }, $page, 'autocep_appearance_freeship');
    }

    private function render_checkbox($grupo, $campo, $descricao = '')
    {
        $options = self::get_options();
        $valor   = !empty($options[$grupo][$campo]);
        $name    = sprintf('%s[%s][%s]', AUTOCEP_OPTION_KEY, $grupo, $campo);
        $id      = sprintf('autocep_%s_%s', $grupo, $campo);

        printf('<input type="hidden" name="%s" value="0" />', esc_attr($name));
        printf(
            '<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
            esc_attr($id),
            esc_attr($name),
            checked($valor, true, false),
            esc_html($descricao)
        );
    }

    private function render_text($grupo, $campo, $descricao = '')
    {
        $options = self::get_options();
        $valor   = isset($options[$grupo][$campo]) ? $options[$grupo][$campo] : '';
        $name    = sprintf('%s[%s][%s]', AUTOCEP_OPTION_KEY, $grupo, $campo);

        printf('<input type="text" class="regular-text" name="%s" value="%s" />', esc_attr($name), esc_attr($valor));

        if ($descricao) {
            printf('<p class="description">%s</p>', esc_html($descricao));
        }
    }

    private function render_textarea($grupo, $campo, $descricao = '')
    {
        $options = self::get_options();
        $valor   = isset($options[$grupo][$campo]) ? $options[$grupo][$campo] : '';
        $name    = sprintf('%s[%s][%s]', AUTOCEP_OPTION_KEY, $grupo, $campo);

        printf('<textarea class="large-text" rows="2" name="%s">%s</textarea>', esc_attr($name), esc_textarea($valor));

        if ($descricao) {
            printf('<p class="description">%s</p>', esc_html($descricao));
        }
    }

    private function render_number($grupo, $campo, $min, $max, $descricao = '')
    {
        $options = self::get_options();
        $valor   = isset($options[$grupo][$campo]) ? (int) $options[$grupo][$campo] : $min;
        $name    = sprintf('%s[%s][%s]', AUTOCEP_OPTION_KEY, $grupo, $campo);

        printf(
            '<input type="number" min="%d" max="%d" name="%s" value="%d" class="small-text" />',
            (int) $min,
            (int) $max,
            esc_attr($name),
            (int) $valor
        );

        if ($descricao) {
            printf('<p class="description">%s</p>', esc_html($descricao));
        }
    }

    private function render_decimal($grupo, $campo, $descricao = '')
    {
        $options = self::get_options();
        $valor   = isset($options[$grupo][$campo]) ? (float) $options[$grupo][$campo] : 0;
        $name    = sprintf('%s[%s][%s]', AUTOCEP_OPTION_KEY, $grupo, $campo);

        printf(
            '<input type="number" step="0.01" min="0" name="%s" value="%s" class="small-text" />',
            esc_attr($name),
            esc_attr($valor)
        );

        if ($descricao) {
            printf('<p class="description">%s</p>', esc_html($descricao));
        }
    }

    private function render_select($grupo, $campo, $choices)
    {
        $options = self::get_options();
        $valor   = isset($options[$grupo][$campo]) ? $options[$grupo][$campo] : '';
        $name    = sprintf('%s[%s][%s]', AUTOCEP_OPTION_KEY, $grupo, $campo);

        printf('<select name="%s">', esc_attr($name));

        foreach ($choices as $chave => $rotulo) {
            printf('<option value="%s" %s>%s</option>', esc_attr($chave), selected($valor, $chave, false), esc_html($rotulo));
        }

        echo '</select>';
    }

    private function render_color_field($grupo, $campo)
    {
        $options = self::get_options();
        $valor   = isset($options[$grupo][$campo]) ? $options[$grupo][$campo] : '';
        $name    = sprintf('%s[%s][%s]', AUTOCEP_OPTION_KEY, $grupo, $campo);

        printf(
            '<input type="text" class="autocep-color-field" name="%s" value="%s" data-default-color="%s" />',
            esc_attr($name),
            esc_attr($valor),
            esc_attr($valor)
        );
    }

    public function render_apis_field()
    {
        $options       = self::get_options();
        $apis_salvas   = $options['general']['apis'];
        $apis_suportadas = AutoCEP_Api::get_supported_apis();

        ?>
        <table class="widefat autocep-apis-table" style="max-width:600px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Ativa', 'autocep'); ?></th>
                    <th><?php esc_html_e('API', 'autocep'); ?></th>
                    <th><?php esc_html_e('Prioridade', 'autocep'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apis_suportadas as $slug => $dados) : ?>
                    <?php
                    $enabled  = !empty($apis_salvas[$slug]['enabled']);
                    $priority = isset($apis_salvas[$slug]['priority']) ? (int) $apis_salvas[$slug]['priority'] : 9;
                    $name_base = sprintf('%s[general][apis][%s]', AUTOCEP_OPTION_KEY, $slug);
                    ?>
                    <tr>
                        <td>
                            <input type="hidden" name="<?php echo esc_attr($name_base); ?>[enabled]" value="0" />
                            <input type="checkbox" name="<?php echo esc_attr($name_base); ?>[enabled]" value="1" <?php checked($enabled, true); ?> />
                        </td>
                        <td><?php echo esc_html($dados['label']); ?></td>
                        <td>
                            <input type="number" min="1" max="9" class="small-text" name="<?php echo esc_attr($name_base); ?>[priority]" value="<?php echo esc_attr($priority); ?>" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function render_apis_status_field()
    {
        ?>
        <div class="autocep-apis-status-box">
            <p>
                <strong><?php esc_html_e('Por que isso é um diferencial:', 'autocep'); ?></strong>
                <?php esc_html_e('o AutoCEP consulta as APIs em cascata — se uma estiver fora do ar, a próxima da lista responde automaticamente, então o autocompletar de CEP nunca para de funcionar. Use o teste abaixo para ver qual API responde mais rápido agora, e ajuste a prioridade na tabela acima de acordo (1 = a mais rápida).', 'autocep'); ?>
            </p>
            <div class="autocep-diagnostics-actions">
                <button type="button" class="button button-primary" id="autocep-check-status-btn">
                    <?php esc_html_e('Testar CEP Agora', 'autocep'); ?>
                </button>
            </div>
            <div id="autocep-status-results"></div>
        </div>
        <?php
    }

    public function render_google_maps_tests_field()
    {
        $lojas_cadastradas = class_exists('AutoCEP_Store_Pickup') ? AutoCEP_Store_Pickup::get_stores() : array();
        $cep_primeira_loja = !empty($lojas_cadastradas[0]['cep']) ? $lojas_cadastradas[0]['cep'] : '';
        ?>
        <div class="autocep-google-diff-box">
            <p>
                <strong><?php esc_html_e('Por que isso é um diferencial:', 'autocep'); ?></strong>
                <?php esc_html_e('a Retirada em Loja e o Frete por Km calculam a distância REAL pela estrada (não em linha reta), usando a Distance Matrix API do Google. Se o Google ficar indisponível por qualquer motivo, o AutoCEP recalcula automaticamente por uma estimativa (linha reta × 1.3) — então a loja nunca fica sem calcular o frete, mesmo com a API fora do ar. Use os testes abaixo para confirmar que a chave está funcionando corretamente e ver a diferença entre um cálculo real e uma estimativa.', 'autocep'); ?>
            </p>
        </div>

        <div class="autocep-google-status-box">
            <h3><?php esc_html_e('1. Status Rápido da API (um clique)', 'autocep'); ?></h3>
            <p class="description" style="max-width:680px;">
                <?php esc_html_e('Testa se a sua chave de API do Google Maps está respondendo agora, sem precisar digitar nenhum CEP.', 'autocep'); ?>
            </p>
            <button type="button" class="button button-primary" id="autocep-google-status-btn">
                <?php esc_html_e('Verificar Status da API Agora', 'autocep'); ?>
            </button>
            <div id="autocep-google-status-result"></div>
        </div>

        <div class="autocep-google-status-box">
            <h3><?php esc_html_e('2. Testar Distância entre CEPs (Diagnóstico Detalhado)', 'autocep'); ?></h3>
            <p class="description" style="max-width:680px;">
                <?php esc_html_e('Use abaixo para testar se a API do Google Maps está conseguindo calcular a distância entre o CEP da sua loja e o CEP de um cliente específico (ex.: cidade vizinha). O resultado mostra exatamente o que aconteceu: qual endereço foi enviado ao Google, a distância calculada (ou a mensagem de erro).', 'autocep'); ?>
            </p>
            <table class="form-table" style="max-width:640px;">
                <tbody>
                    <tr>
                        <th><label for="autocep-dist-cep-a"><?php esc_html_e('CEP da Loja', 'autocep'); ?></label></th>
                        <td>
                            <input type="text" id="autocep-dist-cep-a" class="regular-text" maxlength="9" placeholder="00000-000" value="<?php echo esc_attr($cep_primeira_loja); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="autocep-dist-cep-b"><?php esc_html_e('CEP do Cliente (específico)', 'autocep'); ?></label></th>
                        <td><input type="text" id="autocep-dist-cep-b" class="regular-text" maxlength="9" placeholder="00000-000" /></td>
                    </tr>
                </tbody>
            </table>
            <p>
                <button type="button" class="button button-primary" id="autocep-test-distance-btn">
                    <?php esc_html_e('Testar Agora', 'autocep'); ?>
                </button>
                <span id="autocep-test-distance-feedback" style="margin-left:10px;"></span>
            </p>
            <div id="autocep-test-distance-result" style="display:none; margin-top:12px; padding:12px 16px; background:#f9f9f9; border:1px solid #dcdcde; border-radius:4px; max-width:640px;"></div>
        </div>
        <?php
    }

    public function render_melhor_envio_test_field()
    {
        $detectado = $this->detect_melhor_envio_token();
        ?>
        <?php if ('' !== $detectado['token']) : ?>
            <p class="description">
                <?php
                printf(
                    esc_html__('Token detectado automaticamente — ambiente: %s.', 'autocep'),
                    'sandbox' === $detectado['ambiente'] ? esc_html__('Sandbox', 'autocep') : esc_html__('Produção', 'autocep')
                );
                ?>
            </p>
        <?php else : ?>
            <p class="description"><?php esc_html_e('Nenhum token encontrado no plugin oficial do Melhor Envio ainda — configure-o primeiro para poder testar a conexão aqui.', 'autocep'); ?></p>
        <?php endif; ?>
        <button type="button" class="button button-primary" id="autocep-test-melhorenvio-btn">
            <?php esc_html_e('Testar Conexão com o Melhor Envio', 'autocep'); ?>
        </button>
        <div id="autocep-test-melhorenvio-result" style="margin-top:10px;"></div>
        <?php
    }

    public function render_frenet_test_field()
    {
        $detectado = $this->detect_frenet_credentials();
        ?>
        <?php if ('' !== $detectado['token']) : ?>
            <p class="description"><?php esc_html_e('Token e CEP de origem detectados automaticamente numa Zona de Entrega com o método oficial da Frenet configurado.', 'autocep'); ?></p>
        <?php else : ?>
            <p class="description"><?php esc_html_e('Nenhum token da Frenet encontrado em nenhuma Zona de Entrega ainda — adicione e configure o método oficial da Frenet numa zona primeiro.', 'autocep'); ?></p>
        <?php endif; ?>
        <button type="button" class="button button-primary" id="autocep-test-frenet-btn">
            <?php esc_html_e('Testar Conexão com a Frenet', 'autocep'); ?>
        </button>
        <div id="autocep-test-frenet-result" style="margin-top:10px;"></div>
        <?php
    }

    public function ajax_check_google_maps_status()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sem permissão.', 'autocep')));
        }

        if (!class_exists('AutoCEP_Maps_Service')) {
            wp_send_json_error(array('message' => __('Módulo de mapas não disponível.', 'autocep')));
        }

        $options            = self::get_options();
        $chave_configurada  = !empty($options['distance_shipping']['google_maps_api_key']);

        if (!$chave_configurada) {
            wp_send_json_success(array(
                'status'   => 'sem_chave',
                'mensagem' => __('Nenhuma chave de API configurada. Preencha o campo "Chave de API" acima primeiro.', 'autocep'),
            ));
        }

        $cep_teste_a = '01310930'; 
        $cep_teste_b = '04547000'; 

        $chave_cache = AutoCEP_Maps_Service::CACHE_PREFIX . md5('km_dist_' . $cep_teste_a . '_' . $cep_teste_b);
        delete_transient($chave_cache);

        $inicio    = microtime(true);
        $servico   = new AutoCEP_Maps_Service();
        delete_transient(AutoCEP_Maps_Service::PAUSE_KEY); // o teste consulta o Google de verdade, mesmo em pausa.
        $resultado = $servico->get_distance_km($cep_teste_a, $cep_teste_b);
        $tempo_ms  = round((microtime(true) - $inicio) * 1000);
        $fonte     = $servico->ultima_fonte;

        if (is_wp_error($resultado)) {
            wp_send_json_success(array(
                'status'   => 'erro',
                'mensagem' => $resultado->get_error_message(),
                'tempo_ms' => $tempo_ms,
            ));
        }

        if (in_array($fonte, array('google_maps_endereco', 'google_maps_cidade'), true)) {
            wp_send_json_success(array(
                'status'    => 'ok',
                'mensagem'  => 'google_maps_cidade' === $fonte
                    ? __('API respondendo normalmente (calculado via cidade).', 'autocep')
                    : __('API respondendo normalmente (calculado via endereço completo).', 'autocep'),
                'tempo_ms'  => $tempo_ms,
                'distancia' => $resultado,
            ));
        }

        $motivo = __('Motivo não identificado — confira a aba Diagnóstico e Logs.', 'autocep');

        if (class_exists('AutoCEP_Logger')) {
            foreach (AutoCEP_Logger::get_logs(5) as $log) {
                if ('google-maps' === $log->api) {
                    $motivo = $log->mensagem;
                    break;
                }
            }
        }

        wp_send_json_success(array(
            'status'    => 'falha',
            'mensagem'  => $motivo,
            'tempo_ms'  => $tempo_ms,
            'distancia' => $resultado,
        ));
    }

    public function ajax_test_melhor_envio()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sem permissão.', 'autocep')));
        }

        $detectado = $this->detect_melhor_envio_token();
        $token     = $detectado['token'];

        if (empty($token)) {
            wp_send_json_success(array(
                'status'   => 'sem_token',
                'mensagem' => __('Nenhum token encontrado no plugin oficial do Melhor Envio. Configure-o primeiro (instale o plugin e cole o token nas configurações dele).', 'autocep'),
            ));
        }

        $url_api = 'sandbox' === $detectado['ambiente']
            ? 'https://sandbox.melhorenvio.com.br/api/v2/me'
            : 'https://api.melhorenvio.com/v2/me';

        $inicio   = microtime(true);
        $response = wp_remote_get($url_api, array(
            'timeout' => 12,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
        ));
        $tempo_ms = round((microtime(true) - $inicio) * 1000);

        if (is_wp_error($response)) {
            wp_send_json_success(array(
                'status'   => 'fora_do_ar',
                'mensagem' => sprintf(
                    __('Falha de conexão com a API do Melhor Envio: %s', 'autocep'),
                    $response->get_error_message()
                ),
                'tempo_ms' => $tempo_ms,
            ));
        }

        $codigo_http = (int) wp_remote_retrieve_response_code($response);
        $corpo       = json_decode(wp_remote_retrieve_body($response), true);

        if (in_array($codigo_http, array(401, 403), true)) {
            wp_send_json_success(array(
                'status'   => 'token_invalido',
                'mensagem' => __('Token inválido ou expirado — a API respondeu, mas recusou a autenticação (HTTP ' . $codigo_http . '). Gere um novo token em melhorenvio.com.br.', 'autocep'),
                'tempo_ms' => $tempo_ms,
            ));
        }

        if (200 === $codigo_http && !empty($corpo)) {
            wp_send_json_success(array(
                'status'   => 'ok',
                'mensagem' => !empty($corpo['email'])
                    ? sprintf(
                        __('Token válido — API respondendo normalmente (autenticado como %s).', 'autocep'),
                        $corpo['email']
                    )
                    : __('Token válido e API do Melhor Envio respondendo normalmente.', 'autocep'),
                'tempo_ms' => $tempo_ms,
            ));
        }

        wp_send_json_success(array(
            'status'   => 'erro',
            'mensagem' => sprintf(
                __('A API do Melhor Envio respondeu com um erro inesperado (HTTP %d).', 'autocep'),
                $codigo_http
            ),
            'tempo_ms' => $tempo_ms,
        ));
    }

    public function ajax_test_frenet()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sem permissão.', 'autocep')));
        }

        $detectado  = $this->detect_frenet_credentials();
        $token      = $detectado['token'];
        $cep_origem = $detectado['origin_cep'];

        if (empty($token)) {
            wp_send_json_success(array(
                'status'   => 'sem_token',
                'mensagem' => __('Nenhum token da Frenet encontrado em nenhuma Zona de Entrega. Adicione e configure o método oficial da Frenet numa zona primeiro.', 'autocep'),
            ));
        }

        if (8 !== strlen($cep_origem)) {
            wp_send_json_success(array(
                'status'   => 'sem_cep',
                'mensagem' => __('O método da Frenet foi encontrado, mas o campo "CEP de Origem" dele está vazio ou inválido. Preencha-o nas configurações do método (dentro da Zona de Entrega) e tente novamente.', 'autocep'),
            ));
        }

        $cep_destino_teste = '01310930';

        $inicio   = microtime(true);
        $response = wp_remote_post('https://api.frenet.com.br/shipping/quote', array(
            'timeout' => 12,
            'headers' => array(
                'Content-Type' => 'application/json',
                'token'        => $token,
            ),
            'body' => wp_json_encode(array(
                'SellerCEP'            => $cep_origem,
                'RecipientCEP'         => $cep_destino_teste,
                'ShipmentInvoiceValue' => 50,
                'ShippingItemArray'    => array(
                    array('Height' => 10, 'Length' => 15, 'Width' => 15, 'Weight' => 1, 'Quantity' => 1),
                ),
            )),
        ));
        $tempo_ms = round((microtime(true) - $inicio) * 1000);

        if (is_wp_error($response)) {
            wp_send_json_success(array(
                'status'   => 'fora_do_ar',
                'mensagem' => sprintf(
                    __('Falha de conexão com a API da Frenet: %s', 'autocep'),
                    $response->get_error_message()
                ),
                'tempo_ms' => $tempo_ms,
            ));
        }

        $codigo_http = (int) wp_remote_retrieve_response_code($response);
        $corpo       = json_decode(wp_remote_retrieve_body($response), true);

        if (in_array($codigo_http, array(401, 403), true)) {
            wp_send_json_success(array(
                'status'   => 'token_invalido',
                'mensagem' => __('Token recusado pela Frenet (não autorizado). Confira o Token de Integração em sua conta Frenet.', 'autocep'),
                'tempo_ms' => $tempo_ms,
            ));
        }

        if (200 !== $codigo_http || empty($corpo)) {
            wp_send_json_success(array(
                'status'   => 'erro',
                'mensagem' => sprintf(
                    __('A API da Frenet respondeu com um erro inesperado (HTTP %d).', 'autocep'),
                    $codigo_http
                ),
                'tempo_ms' => $tempo_ms,
            ));
        }

        $mensagem_corpo = '';

        if (!empty($corpo['ShippingSevicesArray'][0]['Msg'])) {
            $mensagem_corpo = $corpo['ShippingSevicesArray'][0]['Msg'];
        } elseif (!empty($corpo['Msg'])) {
            $mensagem_corpo = $corpo['Msg'];
        }

        if ($mensagem_corpo && false !== stripos($mensagem_corpo, 'token')) {
            wp_send_json_success(array(
                'status'   => 'token_invalido',
                'mensagem' => sprintf(
                    __('Token inválido: %s', 'autocep'),
                    $mensagem_corpo
                ),
                'tempo_ms' => $tempo_ms,
            ));
        }

        if (!empty($corpo['ShippingSevicesArray'])) {
            wp_send_json_success(array(
                'status'   => 'ok',
                'mensagem' => __('Token válido e API da Frenet respondendo normalmente (cotação de teste recebida com sucesso).', 'autocep'),
                'tempo_ms' => $tempo_ms,
            ));
        }

        wp_send_json_success(array(
            'status'   => 'erro',
            'mensagem' => $mensagem_corpo ? $mensagem_corpo : __('A API respondeu, mas não retornou nenhum serviço de frete — confira se há serviços habilitados na sua conta Frenet.', 'autocep'),
            'tempo_ms' => $tempo_ms,
        ));
    }

    public function ajax_test_distance()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sem permissão.', 'autocep')));
        }

        if (!class_exists('AutoCEP_Maps_Service')) {
            wp_send_json_error(array('message' => __('Módulo de mapas não disponível.', 'autocep')));
        }

        $cep_a = isset($_POST['cep_a']) ? preg_replace('/\D/', '', wp_unslash($_POST['cep_a'])) : '';
        $cep_b = isset($_POST['cep_b']) ? preg_replace('/\D/', '', wp_unslash($_POST['cep_b'])) : '';

        if (strlen($cep_a) !== 8 || strlen($cep_b) !== 8) {
            wp_send_json_error(array('message' => __('Informe dois CEPs com 8 dígitos.', 'autocep')));
        }

        $chave = AutoCEP_Maps_Service::CACHE_PREFIX . md5('km_dist_' . $cep_a . '_' . $cep_b);
        $chave_inversa = AutoCEP_Maps_Service::CACHE_PREFIX . md5('km_dist_' . $cep_b . '_' . $cep_a);

        delete_transient($chave);
        delete_transient($chave_inversa);

        if (class_exists('AutoCEP_Shipping')) {
            global $wpdb;
            $prefixo_transient = '_transient_' . AutoCEP_Shipping::CACHE_PREFIX;
            $prefixo_timeout   = '_transient_timeout_' . AutoCEP_Shipping::CACHE_PREFIX;

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like($prefixo_transient) . '%',
                    $wpdb->esc_like($prefixo_timeout) . '%'
                )
            );
        }

        $endereco_a = class_exists('AutoCEP_Api') ? AutoCEP_Api::find_address($cep_a) : null;
        $endereco_b = class_exists('AutoCEP_Api') ? AutoCEP_Api::find_address($cep_b) : null;

        $label_a = is_wp_error($endereco_a) || empty($endereco_a['localidade'])
            ? __('(CEP não encontrado nas APIs de endereço)', 'autocep')
            : $endereco_a['localidade'] . ' - ' . $endereco_a['uf'];

        $label_b = is_wp_error($endereco_b) || empty($endereco_b['localidade'])
            ? __('(CEP não encontrado nas APIs de endereço)', 'autocep')
            : $endereco_b['localidade'] . ' - ' . $endereco_b['uf'];

        $servico = new AutoCEP_Maps_Service();
        delete_transient(AutoCEP_Maps_Service::PAUSE_KEY); // o teste consulta o Google de verdade, mesmo em pausa.
        $resultado = $servico->get_distance_km($cep_a, $cep_b);
        $fonte     = $servico->ultima_fonte;

        $lojas_proximas = array();
        $lojas_debug    = array();

        if (class_exists('AutoCEP_Store_Pickup')) {
            $lojas_proximas = AutoCEP_Store_Pickup::get_nearby_stores($cep_b);

            foreach (AutoCEP_Store_Pickup::get_stores() as $loja) {
                $lojas_debug[] = array(
                    'nome'         => $loja['name'],
                    'cep_loja'     => $loja['cep'],
                    'raio'         => $loja['radius_km'],
                    'manual_ceps'  => isset($loja['manual_ceps']) ? $loja['manual_ceps'] : '',
                    'no_raio'      => false,
                    'distancia_km' => null,
                );
            }

            foreach ($lojas_proximas as $loja_raio) {
                foreach ($lojas_debug as &$ld) {
                    if ($ld['cep_loja'] === $loja_raio['cep']) {
                        $ld['no_raio']      = true;
                        $ld['distancia_km'] = $loja_raio['distance_km'];
                    }
                }
                unset($ld);
            }
        }

        if (is_wp_error($resultado)) {
            wp_send_json_success(array(
                'ok'            => false,
                'cep_a'         => $cep_a,
                'cep_b'         => $cep_b,
                'cidade_a'      => $label_a,
                'cidade_b'      => $label_b,
                'distancia'     => null,
                'erro'          => $resultado->get_error_message(),
                'erro_code'     => $resultado->get_error_code(),
                'lojas_debug'   => $lojas_debug,
            ));
        }

        wp_send_json_success(array(
            'ok'            => true,
            'cep_a'         => $cep_a,
            'cep_b'         => $cep_b,
            'cidade_a'      => $label_a,
            'cidade_b'      => $label_b,
            'distancia'     => $resultado,
            'fonte'         => $fonte,
            'zona_info'     => $this->get_zone_debug_info($cep_b),
            'lojas_debug'   => $lojas_debug,
        ));
    }

    private function get_zone_debug_info($cep)
    {
        if (!class_exists('WC_Shipping_Zones')) {
            return array('erro' => 'WC_Shipping_Zones não disponível.');
        }

        $package = array(
            'destination' => array(
                'country'  => 'BR',
                'state'    => '',
                'postcode' => $cep,
                'city'     => '',
            ),
            'contents'        => array(),
            'applied_coupons' => array(),
            'package_id'      => 0,
        );

        $zona = WC_Shipping_Zones::get_zone_matching_package($package);

        if (!$zona) {
            return array('zona_nome' => 'Nenhuma zona encontrada', 'metodos' => array());
        }

        $metodos_raw = method_exists($zona, 'get_shipping_methods') ? $zona->get_shipping_methods(true) : array();
        $metodos     = array();

        foreach ($metodos_raw as $metodo) {
            $metodos[] = array(
                'id'      => $metodo->id,
                'titulo'  => $metodo->get_title(),
                'ativo'   => $metodo->is_enabled() ? 'sim' : 'não',
            );
        }

        $todas_zonas = array();
        foreach (WC_Shipping_Zones::get_zones() as $z) {
            $zona_obj = WC_Shipping_Zones::get_zone($z['zone_id']);
            if (!$zona_obj) continue;
            $todos_ceps = array();
            foreach ($z['zone_locations'] as $loc) {
                if ('postcode' === $loc->type) {
                    $todos_ceps[] = $loc->code;
                }
            }
            $todas_zonas[] = array(
                'id'    => $z['zone_id'],
                'nome'  => $z['zone_name'],
                'ceps'  => implode(', ', $todos_ceps),
                'ordem' => $z['zone_order'],
            );
        }
        usort($todas_zonas, function($a, $b) { return $a['ordem'] <=> $b['ordem']; });

        return array(
            'zona_id'      => $zona->get_id(),
            'zona_nome'    => $zona->get_zone_name(),
            'metodos'      => $metodos,
            'todas_zonas'  => $todas_zonas,
        );
    }

    public function ajax_send_support_ticket()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sem permissão.', 'autocep')));
        }

        $tipo    = sanitize_text_field(wp_unslash($_POST['tipo'] ?? ''));
        $titulo  = sanitize_text_field(wp_unslash($_POST['titulo'] ?? ''));
        $desc    = sanitize_textarea_field(wp_unslash($_POST['desc'] ?? ''));
        $email   = get_option('admin_email'); 

        if (empty($titulo) || empty($desc)) {
            wp_send_json_error(array('message' => __('Dados incompletos.', 'autocep')));
        }

        $numero = class_exists('AutoCEP_Tickets') ? AutoCEP_Tickets::create_ticket($tipo, $titulo, $desc) : '';

        $site_url = get_site_url();
        $wc_ver   = defined('WC_VERSION') ? WC_VERSION : 'N/D';
        $php_ver  = PHP_VERSION;
        $wp_ver   = get_bloginfo('version');

        $assunto = sprintf('[AutoCEP Suporte] [%s]%s %s', strtoupper($tipo), $numero ? ' [' . $numero . ']' : '', $titulo);

        $corpo  = "=== NOVO CHAMADO AUTOCEP ===\n\n";
        $corpo .= $numero ? "Número do chamado: {$numero}\n" : '';
        $corpo .= "Tipo: {$tipo}\n";
        $corpo .= "Título: {$titulo}\n";
        $corpo .= "E-mail do cliente: {$email}\n\n";
        $corpo .= "--- Descrição ---\n{$desc}\n\n";
        $corpo .= "--- Informações do Sistema ---\n";
        $corpo .= "AutoCEP: " . AUTOCEP_VERSION . "\n";
        $corpo .= "WordPress: {$wp_ver}\n";
        $corpo .= "WooCommerce: {$wc_ver}\n";
        $corpo .= "PHP: {$php_ver}\n";
        $corpo .= "Site: {$site_url}\n";

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $email,
        );

        $attachments       = array();
        $tmp_criados       = array();
        $extensoes_validas = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
        );
        $tamanho_max = 5 * 1024 * 1024;

        if (!empty($_FILES['arquivos']['name'][0])) {
            $arquivos = $_FILES['arquivos'];
            $total    = min(count($arquivos['name']), 5); 

            for ($i = 0; $i < $total; $i++) {
                if (empty($arquivos['tmp_name'][$i]) || !is_uploaded_file($arquivos['tmp_name'][$i])) {
                    continue;
                }

                $tamanho = (int) $arquivos['size'][$i];

                if ($tamanho <= 0 || $tamanho > $tamanho_max) {
                    continue;
                }

                $checagem       = wp_check_filetype_and_ext($arquivos['tmp_name'][$i], $arquivos['name'][$i]);
                $ext_detectada  = strtolower((string) $checagem['ext']);
                $mime_detectado = (string) $checagem['type'];

                if (
                    empty($ext_detectada)
                    || empty($mime_detectado)
                    || !isset($extensoes_validas[$ext_detectada])
                    || $extensoes_validas[$ext_detectada] !== $mime_detectado
                ) {
                    continue;
                }

                $tmp_path = trailingslashit(get_temp_dir()) . 'autocep_ticket_' . wp_generate_password(12, false) . '_' . $i . '.' . $ext_detectada;

                if (move_uploaded_file($arquivos['tmp_name'][$i], $tmp_path)) {
                    $attachments[] = $tmp_path;
                    $tmp_criados[] = $tmp_path;
                }
            }
        }

        $erro_real = null;
        $capturar_erro = function ($wp_error) use (&$erro_real) {
            $erro_real = $wp_error->get_error_message();
        };
        add_action('wp_mail_failed', $capturar_erro);

        $enviado = wp_mail('teswinfor@gmail.com', $assunto, $corpo, $headers, $attachments);

        remove_action('wp_mail_failed', $capturar_erro);

        foreach ($tmp_criados as $tmp) {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }

        if ($enviado) {
            wp_mail(
                $email,
                '[AutoCEP] Chamado recebido: ' . ($numero ? $numero . ' — ' : '') . $titulo,
                "Olá!\n\nSeu chamado foi recebido pela equipe TESW.\n\n" .
                ($numero ? "Número do chamado: {$numero}\n" : '') .
                "Título: {$titulo}\nTipo: {$tipo}\n\nRetornaremos em breve pelo e-mail teswinfor@gmail.com ou WhatsApp (83) 99407-5812.\n\nEquipe TESW - AutoCEP",
                array('Content-Type: text/plain; charset=UTF-8')
            );
            wp_send_json_success(array('numero' => $numero));
        } else {
            $logger = new AutoCEP_Logger();
            $logger->log('support-ticket-email', '', $erro_real ? $erro_real : 'Motivo desconhecido (wp_mail retornou false sem detalhes).', 'error');

            $dica_hospedagem = '';

            if ($erro_real) {
                $indicadores = array('mail function', 'não foi possível instanciar', 'could not instantiate', 'smtp connect', 'mail() has been disabled', 'failed to connect to mailserver', 'sendmail');
                $erro_minusculo = function_exists('mb_strtolower') ? mb_strtolower($erro_real) : strtolower($erro_real);

                foreach ($indicadores as $indicador) {
                    if (false !== strpos($erro_minusculo, $indicador)) {
                        $dica_hospedagem = __('Isso costuma acontecer porque a hospedagem do site não permite o envio de e-mail direto pelo PHP (muito comum em hospedagem compartilhada, incluindo Hostinger). Para corrigir de vez, instale um plugin de SMTP (ex.: "WP Mail SMTP") e configure com uma conta de e-mail real — não é um problema do plugin AutoCEP.', 'autocep');
                        break;
                    }
                }
            }

            $mensagem = $numero
                ? sprintf(
                    __('Chamado %s salvo localmente, mas não foi possível enviar por e-mail.', 'autocep'),
                    $numero
                )
                : __('Não foi possível enviar o chamado por e-mail.', 'autocep');

            if ($dica_hospedagem) {
                $mensagem .= ' ' . $dica_hospedagem;
            }

            $mensagem .= ' ' . __('Se preferir não configurar isso agora, use os contatos diretos no topo desta página (e-mail ou WhatsApp) — já abrem com uma mensagem pronta sobre suporte ao AutoCEP.', 'autocep');

            if ($erro_real) {
                $mensagem .= ' [' . $erro_real . ']';
            }

            wp_send_json_error(array('message' => $mensagem));
        }
    }

    public function ajax_toggle_ticket_status()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sem permissão.', 'autocep')));
        }

        if (!class_exists('AutoCEP_Tickets')) {
            wp_send_json_error(array('message' => __('Módulo de chamados não disponível.', 'autocep')));
        }

        $id     = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

        if (!$id || !in_array($status, array('aberto', 'fechado'), true)) {
            wp_send_json_error(array('message' => __('Dados inválidos.', 'autocep')));
        }

        $ok = AutoCEP_Tickets::set_status($id, $status);

        if (!$ok) {
            wp_send_json_error(array('message' => __('Não foi possível atualizar o chamado.', 'autocep')));
        }

        wp_send_json_success(array('status' => $status));
    }

    public function ajax_send_feedback()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sem permissão.', 'autocep')));
        }

        if (!class_exists('AutoCEP_Feedback')) {
            wp_send_json_error(array('message' => __('Módulo de avaliação não disponível.', 'autocep')));
        }

        $nota       = isset($_POST['nota']) ? absint($_POST['nota']) : 0;
        $comentario = sanitize_textarea_field(wp_unslash($_POST['comentario'] ?? ''));
        $sugestao   = sanitize_textarea_field(wp_unslash($_POST['sugestao'] ?? ''));

        if ($nota < 1 || $nota > 5) {
            wp_send_json_error(array('message' => __('Escolha de 1 a 5 estrelas.', 'autocep')));
        }

        AutoCEP_Feedback::create_feedback($nota, $comentario, $sugestao);

        $estrelas = str_repeat('★', $nota) . str_repeat('☆', 5 - $nota);

        $corpo  = "=== NOVA AVALIAÇÃO AUTOCEP ===\n\n";
        $corpo .= "Nota: {$estrelas} ({$nota}/5)\n\n";
        $corpo .= "--- Comentário ---\n" . ($comentario ? $comentario : '(vazio)') . "\n\n";
        $corpo .= "--- Sugestões ---\n" . ($sugestao ? $sugestao : '(vazio)') . "\n\n";
        $corpo .= "--- Informações do Sistema ---\n";
        $corpo .= "AutoCEP: " . AUTOCEP_VERSION . "\n";
        $corpo .= "WordPress: " . get_bloginfo('version') . "\n";
        $corpo .= "WooCommerce: " . (defined('WC_VERSION') ? WC_VERSION : 'N/D') . "\n";
        $corpo .= "PHP: " . PHP_VERSION . "\n";
        $corpo .= "Site: " . get_site_url() . "\n";

        $enviado = wp_mail(
            'teswinfor@gmail.com',
            sprintf('[AutoCEP Avaliação] %s (%d/5)', $estrelas, $nota),
            $corpo,
            array(
                'Content-Type: text/plain; charset=UTF-8',
                'Reply-To: ' . get_option('admin_email'),
            )
        );

        wp_send_json_success(array(
            'nota'    => $nota,
            'enviado' => (bool) $enviado,
        ));
    }

    public function render_clear_cache_button()
    {
        ?>
        <button type="button" class="button" id="autocep-clear-cache-btn">
            <?php esc_html_e('Limpar Cache de CEPs e Cotações de Frete', 'autocep'); ?>
        </button>
        <span id="autocep-clear-cache-feedback" style="margin-left:10px;"></span>
        <?php
    }

    private function clear_shipping_rates_cache()
    {
        if (!class_exists('AutoCEP_Shipping')) {
            return;
        }

        global $wpdb;

        $prefixo_transient = '_transient_' . AutoCEP_Shipping::CACHE_PREFIX;
        $prefixo_timeout   = '_transient_timeout_' . AutoCEP_Shipping::CACHE_PREFIX;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like($prefixo_transient) . '%',
                $wpdb->esc_like($prefixo_timeout) . '%'
            )
        );
    }

    public function sanitize_options($input)
    {
        $existentes = get_option(AUTOCEP_OPTION_KEY, self::get_default_options());
        $input      = is_array($input) ? $input : array();

        if (isset($input['general'])) {
            $existentes['general']['enable_billing']       = !empty($input['general']['enable_billing']) ? 1 : 0;
            $existentes['general']['enable_shipping']      = !empty($input['general']['enable_shipping']) ? 1 : 0;
            $existentes['general']['auto_focus']           = !empty($input['general']['auto_focus']) ? 1 : 0;
            $existentes['general']['browser_autocomplete'] = !empty($input['general']['browser_autocomplete']) ? 1 : 0;
            $existentes['general']['address_suggest']      = !empty($input['general']['address_suggest']) ? 1 : 0;

            if (isset($input['general']['address_suggest_provider']) && in_array($input['general']['address_suggest_provider'], array('auto', 'viacep', 'google'), true)) {
                $existentes['general']['address_suggest_provider'] = $input['general']['address_suggest_provider'];
            }

            if (isset($input['general']['neighborhood_field'])) {
                $existentes['general']['neighborhood_field'] = sanitize_text_field($input['general']['neighborhood_field']);
            }

            if (isset($input['general']['cache_days'])) {
                $existentes['general']['cache_days'] = max(0, min(365, (int) $input['general']['cache_days']));
            }

            if (isset($input['general']['shipping_cache_hours'])) {
                $existentes['general']['shipping_cache_hours'] = max(0, min(48, (int) $input['general']['shipping_cache_hours']));
            }

            $existentes['general']['stats_enabled'] = !empty($input['general']['stats_enabled']) ? 1 : 0;

            if (isset($input['general']['apis']) && is_array($input['general']['apis'])) {
                foreach (AutoCEP_Api::get_supported_apis() as $slug => $dados) {
                    $existentes['general']['apis'][$slug] = array(
                        'enabled'  => !empty($input['general']['apis'][$slug]['enabled']) ? 1 : 0,
                        'priority' => isset($input['general']['apis'][$slug]['priority'])
                            ? max(1, min(9, (int) $input['general']['apis'][$slug]['priority']))
                            : 9,
                    );
                }
            }
        }

        if (isset($input['product_shipping'])) {
            $existentes['product_shipping']['enabled']           = !empty($input['product_shipping']['enabled']) ? 1 : 0;
            $existentes['product_shipping']['consider_quantity'] = !empty($input['product_shipping']['consider_quantity']) ? 1 : 0;

            if (isset($input['product_shipping']['hook']) && array_key_exists($input['product_shipping']['hook'], self::get_product_hook_choices())) {
                $existentes['product_shipping']['hook'] = sanitize_text_field($input['product_shipping']['hook']);
            }

            if (isset($input['product_shipping']['title'])) {
                $existentes['product_shipping']['title'] = sanitize_text_field($input['product_shipping']['title']);
            }
        }

        if (isset($input['cart_shipping'])) {
            $existentes['cart_shipping']['enabled'] = !empty($input['cart_shipping']['enabled']) ? 1 : 0;

            if (isset($input['cart_shipping']['position']) && array_key_exists($input['cart_shipping']['position'], self::get_cart_hook_choices())) {
                $existentes['cart_shipping']['position'] = sanitize_text_field($input['cart_shipping']['position']);
            }

            if (isset($input['cart_shipping']['title'])) {
                $existentes['cart_shipping']['title'] = sanitize_text_field($input['cart_shipping']['title']);
            }
        }

        if (isset($input['checkout_shipping'])) {
            $existentes['checkout_shipping']['enabled'] = !empty($input['checkout_shipping']['enabled']) ? 1 : 0;

            if (isset($input['checkout_shipping']['position']) && array_key_exists($input['checkout_shipping']['position'], self::get_checkout_hook_choices())) {
                $existentes['checkout_shipping']['position'] = sanitize_text_field($input['checkout_shipping']['position']);
            }
        }

        if (isset($input['modern_checkout'])) {
            $mc = $input['modern_checkout'];

            $existentes['modern_checkout']['enabled']      = !empty($mc['enabled']) ? 1 : 0;
            $existentes['modern_checkout']['compact']      = !empty($mc['compact']) ? 1 : 0;
            $existentes['modern_checkout']['pix_thankyou'] = !empty($mc['pix_thankyou']) ? 1 : 0;
            $existentes['modern_checkout']['account_notice'] = !empty($mc['account_notice']) ? 1 : 0;

            $limites = array('form_width' => array(400, 1200), 'summary_width' => array(260, 600), 'field_height' => array(32, 60));

            foreach ($limites as $campo => $faixa) {
                if (isset($mc[$campo]) && '' !== $mc[$campo]) {
                    $existentes['modern_checkout'][$campo] = max($faixa[0], min($faixa[1], (int) $mc[$campo]));
                }
            }

            if (isset($mc['ship_different']) && in_array($mc['ship_different'], array('unchecked', 'woocommerce'), true)) {
                $existentes['modern_checkout']['ship_different'] = $mc['ship_different'];
            }

            if (isset($mc['account_mode']) && in_array($mc['account_mode'], array('woocommerce', 'optional', 'required', 'guest'), true)) {
                $existentes['modern_checkout']['account_mode'] = $mc['account_mode'];
            }

            if (isset($mc['account_message'])) {
                $existentes['modern_checkout']['account_message'] = sanitize_textarea_field($mc['account_message']);
            }

            foreach (array('step1_title', 'step2_title', 'step3_title', 'next_label') as $campo) {
                if (isset($mc[$campo]) && '' !== trim($mc[$campo])) {
                    $existentes['modern_checkout'][$campo] = sanitize_text_field($mc[$campo]);
                }
            }

            foreach (array('pix_message', 'card_message', 'boleto_message') as $campo) {
                if (isset($mc[$campo])) {
                    $existentes['modern_checkout'][$campo] = sanitize_textarea_field($mc[$campo]);
                }
            }

            if (isset($mc['accent_color']) && preg_match('/^#[0-9a-fA-F]{3,6}$/', $mc['accent_color'])) {
                $existentes['modern_checkout']['accent_color'] = sanitize_text_field($mc['accent_color']);
            }

            if (isset($mc['simulator']) && in_array($mc['simulator'], array('top', 'hide'), true)) {
                $existentes['modern_checkout']['simulator'] = $mc['simulator'];
            }

            if (isset($mc['fields']) && is_array($mc['fields'])) {
                $campos = array();

                foreach (AutoCEP_Modern_Checkout::get_managed_fields() as $chave => $rotulo) {
                    if (!isset($mc['fields'][$chave]) || !is_array($mc['fields'][$chave])) {
                        continue;
                    }

                    $dados = $mc['fields'][$chave];

                    $campos[$chave] = array(
                        'show'     => !empty($dados['show']) ? 1 : 0,
                        'required' => isset($dados['required']) && in_array($dados['required'], array('yes', 'no'), true) ? $dados['required'] : '',
                        'label'    => isset($dados['label']) ? sanitize_text_field($dados['label']) : '',
                    );
                }

                $existentes['modern_checkout']['fields'] = $campos;
            }

            // Campos extras: a lista inteira é reenviada a cada salvamento
            // (removidos no painel simplesmente não vêm).
            if (isset($mc['enabled'])) {
                $extras = array();
                $usadas = array();
                $tipos  = AutoCEP_Modern_Checkout::get_custom_field_types();
                $lista  = isset($mc['custom_fields']) && is_array($mc['custom_fields']) ? $mc['custom_fields'] : array();

                foreach (array_slice($lista, 0, 20) as $dados) {
                    $rotulo = isset($dados['label']) ? sanitize_text_field($dados['label']) : '';

                    if ('' === $rotulo) {
                        continue;
                    }

                    // A chave nasce do rótulo e nunca muda depois (é ela que
                    // liga o campo aos valores já salvos nos pedidos).
                    $chave = isset($dados['key']) ? sanitize_key($dados['key']) : '';

                    if (0 !== strpos($chave, AutoCEP_Modern_Checkout::CUSTOM_PREFIX)) {
                        $slug  = substr(sanitize_key(str_replace('-', '_', sanitize_title($rotulo))), 0, 30);
                        $chave = AutoCEP_Modern_Checkout::CUSTOM_PREFIX . ($slug ? $slug : 'campo');
                    }

                    $original = $chave;
                    $n        = 2;

                    while (isset($usadas[$chave])) {
                        $chave = $original . '_' . $n++;
                    }

                    $usadas[$chave] = true;
                    $tipo           = isset($dados['type']) && isset($tipos[$dados['type']]) ? $dados['type'] : 'text';

                    $novo = array(
                        'key'      => $chave,
                        'label'    => $rotulo,
                        'type'     => $tipo,
                        'step'     => isset($dados['step']) && 1 === (int) $dados['step'] ? 1 : 2,
                        'required' => !empty($dados['required']) ? 1 : 0,
                        'options'  => 'select' === $tipo && isset($dados['options']) ? sanitize_text_field($dados['options']) : '',
                    );

                    // Verificação antes de adicionar: só grava o que o checkout
                    // do WooCommerce vai aceitar. O que não passa fica de fora e
                    // o motivo aparece como aviso no topo da página.
                    $motivo = AutoCEP_Modern_Checkout::validate_custom_field($novo, self::get_checkout_fields_snapshot());

                    if ('' !== $motivo) {
                        if (function_exists('add_settings_error')) {
                            add_settings_error(
                                AUTOCEP_OPTION_KEY,
                                'autocep_campo_extra_' . $chave,
                                sprintf(__('O campo extra "%1$s" não foi adicionado: %2$s', 'autocep'), $rotulo, $motivo),
                                'error'
                            );
                        }

                        continue;
                    }

                    $extras[] = $novo;
                }

                $existentes['modern_checkout']['custom_fields'] = $extras;
            }
        }

        if (isset($input['order_status'])) {
            $os = $input['order_status'];

            $existentes['order_status']['enabled']      = !empty($os['enabled']) ? 1 : 0;
            $existentes['order_status']['paid_on_load'] = !empty($os['paid_on_load']) ? 1 : 0;

            if (isset($os['redirect_url'])) {
                // Só endereços http(s); {pedido} é trocado pelo número do pedido.
                $url = trim($os['redirect_url']);
                $existentes['order_status']['redirect_url'] = '' === $url ? '' : esc_url_raw($url, array('http', 'https'));
            }

            if (isset($os['redirect_delay'])) {
                $existentes['order_status']['redirect_delay'] = max(0, min(30, (int) $os['redirect_delay']));
            }

            foreach (array_keys(AutoCEP_Order_Status::get_statuses()) as $status) {
                if (isset($os['msg_' . $status])) {
                    $existentes['order_status']['msg_' . $status] = sanitize_textarea_field($os['msg_' . $status]);
                }
            }
        }

        if (isset($input['delivery_estimate'])) {
            $existentes['delivery_estimate']['enabled']            = !empty($input['delivery_estimate']['enabled']) ? 1 : 0;
            $existentes['delivery_estimate']['business_days_only'] = !empty($input['delivery_estimate']['business_days_only']) ? 1 : 0;
            $existentes['delivery_estimate']['fallback_enabled']   = !empty($input['delivery_estimate']['fallback_enabled']) ? 1 : 0;

            if (isset($input['delivery_estimate']['range_margin_days'])) {
                $existentes['delivery_estimate']['range_margin_days'] = max(0, min(10, (int) $input['delivery_estimate']['range_margin_days']));
            }

            if (isset($input['delivery_estimate']['fallback_min_days'])) {
                $existentes['delivery_estimate']['fallback_min_days'] = max(0, min(60, (int) $input['delivery_estimate']['fallback_min_days']));
            }

            if (isset($input['delivery_estimate']['fallback_max_days'])) {
                $existentes['delivery_estimate']['fallback_max_days'] = max(0, min(60, (int) $input['delivery_estimate']['fallback_max_days']));
            }

            if ($existentes['delivery_estimate']['fallback_max_days'] < $existentes['delivery_estimate']['fallback_min_days']) {
                $existentes['delivery_estimate']['fallback_max_days'] = $existentes['delivery_estimate']['fallback_min_days'];
            }
        }

        if (isset($input['free_shipping_bar'])) {
            $existentes['free_shipping_bar']['enabled']     = !empty($input['free_shipping_bar']['enabled']) ? 1 : 0;
            $existentes['free_shipping_bar']['auto_detect'] = !empty($input['free_shipping_bar']['auto_detect']) ? 1 : 0;

            if (isset($input['free_shipping_bar']['threshold'])) {
                $existentes['free_shipping_bar']['threshold'] = max(0, (float) $input['free_shipping_bar']['threshold']);
            }

            if (isset($input['free_shipping_bar']['message_progress'])) {
                $existentes['free_shipping_bar']['message_progress'] = sanitize_text_field($input['free_shipping_bar']['message_progress']);
            }

            if (isset($input['free_shipping_bar']['message_achieved'])) {
                $existentes['free_shipping_bar']['message_achieved'] = sanitize_text_field($input['free_shipping_bar']['message_achieved']);
            }
        }

        if (isset($input['distance_shipping'])) {
            if (isset($input['distance_shipping']['google_maps_api_key'])) {
                $existentes['distance_shipping']['google_maps_api_key'] = sanitize_text_field($input['distance_shipping']['google_maps_api_key']);
            }

            $existentes['distance_shipping']['pickup_enabled'] = !empty($input['distance_shipping']['pickup_enabled']) ? 1 : 0;
        }

        if (isset($input['km_delivery'])) {
            $existentes['km_delivery']['enabled']       = !empty($input['km_delivery']['enabled']) ? 1 : 0;
            $existentes['km_delivery']['round_km']      = !empty($input['km_delivery']['round_km']) ? 1 : 0;

            if (isset($input['km_delivery']['title'])) {
                $existentes['km_delivery']['title'] = sanitize_text_field($input['km_delivery']['title']);
            }
            if (isset($input['km_delivery']['origin_cep'])) {
                $existentes['km_delivery']['origin_cep'] = preg_replace('/\D/', '', $input['km_delivery']['origin_cep']);
            }
            foreach (array('base_fee', 'price_per_km', 'min_fee') as $campo) {
                if (isset($input['km_delivery'][$campo])) {
                    $existentes['km_delivery'][$campo] = max(0, (float) $input['km_delivery'][$campo]);
                }
            }
            if (isset($input['km_delivery']['max_radius_km'])) {
                $existentes['km_delivery']['max_radius_km'] = max(0.1, (float) $input['km_delivery']['max_radius_km']);
            }

            $this->clear_shipping_rates_cache();
        }

        if (isset($input['messages'])) {
            foreach (array('searching', 'not_found', 'calculating', 'invalid_cep', 'generic_error', 'select_variation', 'no_street', 'select_rate', 'selecting_rate', 'rate_selected') as $campo) {
                if (isset($input['messages'][$campo])) {
                    $existentes['messages'][$campo] = sanitize_text_field($input['messages'][$campo]);
                }
            }

            $existentes['messages']['block_checkout_button'] = !empty($input['messages']['block_checkout_button']) ? 1 : 0;
        }

        if (isset($input['appearance'])) {
            foreach (array('field_bg_color', 'field_border_color', 'field_text_color', 'button_bg_color', 'button_text_color', 'button_hover_bg_color', 'box_bg_color', 'box_border_color', 'free_shipping_bar_color', 'free_shipping_achieved_color') as $campo) {
                if (isset($input['appearance'][$campo]) && preg_match('/^#[0-9a-fA-F]{3,6}$/', $input['appearance'][$campo])) {
                    $existentes['appearance'][$campo] = sanitize_text_field($input['appearance'][$campo]);
                }
            }

            if (isset($input['appearance']['border_radius']) && in_array($input['appearance']['border_radius'], array('nenhum', 'pequeno', 'medio', 'grande'), true)) {
                $existentes['appearance']['border_radius'] = $input['appearance']['border_radius'];
            }

            if (isset($input['appearance']['font_size']) && in_array($input['appearance']['font_size'], array('pequeno', 'medio', 'grande'), true)) {
                $existentes['appearance']['font_size'] = $input['appearance']['font_size'];
            }

            if (isset($input['appearance']['spacing']) && in_array($input['appearance']['spacing'], array('compacto', 'normal', 'espacoso'), true)) {
                $existentes['appearance']['spacing'] = $input['appearance']['spacing'];
            }

            if (isset($input['appearance']['button_font_weight']) && in_array($input['appearance']['button_font_weight'], array('normal', 'negrito'), true)) {
                $existentes['appearance']['button_font_weight'] = $input['appearance']['button_font_weight'];
            }
        }

        return $existentes;
    }

    public function enqueue_admin_assets($hook)
    {
        if ('toplevel_page_autocep' !== $hook) {
            return;
        }

        wp_enqueue_script('jquery');

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        $aba_atual = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';

        if ('shipping_dashboard' === $aba_atual) {
            wp_enqueue_script(
                'autocep-chartjs',
                AUTOCEP_PLUGIN_URL . 'assets/js/vendor/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );
        }

        $admin_js = "
            jQuery(function ($) {
                if ($.fn.wpColorPicker) {
                    $('.autocep-color-field').wpColorPicker();
                }

                var nonce = " . wp_json_encode(wp_create_nonce(AUTOCEP_ADMIN_NONCE_ACTION)) . ";
                var ajaxUrl = " . wp_json_encode(admin_url('admin-ajax.php')) . ";

                $('#autocep-clear-cache-btn').on('click', function () {
                    var \$btn = $(this);
                    var \$feedback = $('#autocep-clear-cache-feedback');
                    \$btn.prop('disabled', true);
                    \$feedback.text('" . esc_js(__('Limpando...', 'autocep')) . "');

                    $.post(ajaxUrl, { action: 'autocep_clear_cache', nonce: nonce })
                        .done(function (response) {
                            \$feedback.text(response && response.data && response.data.message ? response.data.message : '" . esc_js(__('Concluído.', 'autocep')) . "');
                        })
                        .fail(function () {
                            \$feedback.text('" . esc_js(__('Falha ao limpar o cache.', 'autocep')) . "');
                        })
                        .always(function () {
                            \$btn.prop('disabled', false);
                        });
                });

                $('#autocep-check-status-btn').on('click', function () {
                    var \$btn = $(this);
                    var \$wrap = $('#autocep-status-results');
                    \$btn.prop('disabled', true);
                    \$wrap.html('<p>" . esc_js(__('Verificando...', 'autocep')) . "</p>');

                    $.post(ajaxUrl, { action: 'autocep_check_api_status', nonce: nonce })
                        .done(function (response) {
                            if (!response.success) {
                                \$wrap.html('<p>" . esc_js(__('Erro ao verificar status.', 'autocep')) . "</p>');
                                return;
                            }

                            var html = '<table class=\"widefat\"><thead><tr><th>API</th><th>Status</th><th>Detalhe</th></tr></thead><tbody>';
                            $.each(response.data, function (slug, info) {
                                var cor = info.online ? '#008a20' : '#d63638';
                                var texto = info.online ? '" . esc_js(__('Online', 'autocep')) . "' : '" . esc_js(__('Offline', 'autocep')) . "';
                                html += '<tr><td>' + info.nome + '</td><td style=\"color:' + cor + ';font-weight:600;\">' + texto + '</td><td>' + info.mensagem + '</td></tr>';
                            });
                            html += '</tbody></table>';

                            \$wrap.html(html);
                        })
                        .fail(function () {
                            \$wrap.html('<p>" . esc_js(__('Erro ao verificar status.', 'autocep')) . "</p>');
                        })
                        .always(function () {
                            \$btn.prop('disabled', false);
                        });
                });

                $('#autocep-clear-logs-btn').on('click', function () {
                    if (!window.confirm(" . wp_json_encode(__('Tem certeza que deseja apagar todos os logs?', 'autocep')) . ")) {
                        return;
                    }

                    var \$btn = $(this);
                    \$btn.prop('disabled', true);

                    $.post(ajaxUrl, { action: 'autocep_clear_logs', nonce: nonce })
                        .done(function () {
                            window.location.reload();
                        })
                        .always(function () {
                            \$btn.prop('disabled', false);
                        });
                });

                $('#autocep-clear-stats-btn').on('click', function () {
                    if (!window.confirm(" . wp_json_encode(__('Tem certeza que deseja apagar todas as estatísticas do Dashboard de Frete?', 'autocep')) . ")) {
                        return;
                    }

                    var \$btn = $(this);
                    var \$feedback = $('#autocep-clear-stats-feedback');
                    \$btn.prop('disabled', true);
                    \$feedback.text('" . esc_js(__('Limpando...', 'autocep')) . "');

                    $.post(ajaxUrl, { action: 'autocep_clear_stats', nonce: nonce })
                        .done(function (response) {
                            \$feedback.text(response && response.data && response.data.message ? response.data.message : '" . esc_js(__('Concluído.', 'autocep')) . "');
                            window.location.reload();
                        })
                        .fail(function () {
                            \$feedback.text('" . esc_js(__('Falha ao limpar as estatísticas.', 'autocep')) . "');
                        })
                        .always(function () {
                            \$btn.prop('disabled', false);
                        });
                });

                // Gerenciamento das Lojas Físicas (Retirada por Proximidade):
                // toda a listagem é renderizada no lado do cliente a partir do
                // array abaixo, atualizado a cada resposta bem-sucedida das
                // rotas AJAX de salvar/excluir.
                var autocepStores = " . wp_json_encode(class_exists('AutoCEP_Store_Pickup') ? AutoCEP_Store_Pickup::get_stores() : array()) . ";

                function autocepRenderStoresTable() {
                    var \$tbody = $('#autocep-stores-tbody');

                    if (!\$tbody.length) {
                        return;
                    }

                    \$tbody.empty();

                    if (!autocepStores.length) {
                        \$tbody.append(
                            $('<tr class=\"autocep-stores-empty-row\" />').append(
                                $('<td colspan=\"7\" />').text('" . esc_js(__('Nenhuma loja cadastrada ainda.', 'autocep')) . "')
                            )
                        );
                        return;
                    }

                    autocepStores.forEach(function (loja) {
                        var \$linha = $('<tr />').attr('data-id', loja.id);

                        \$linha.append($('<td class=\"autocep-store-col-name\" />').text(loja.name));
                        \$linha.append($('<td class=\"autocep-store-col-cep\" />').text(loja.cep));
                        \$linha.append($('<td class=\"autocep-store-col-radius\" />').text(loja.radius_km));
                        \$linha.append($('<td class=\"autocep-store-col-fee\" />').text(Number(loja.pickup_fee || 0).toFixed(2).replace('.', ',')));
                        \$linha.append($('<td class=\"autocep-store-col-manual-ceps\" />').text(loja.manual_ceps ? String(loja.manual_ceps).substring(0, 40) : '—'));
                        \$linha.append($('<td class=\"autocep-store-col-instructions\" />').text(String(loja.instructions || '').substring(0, 60)));

                        var \$acoes = $('<td />');
                        \$acoes.append(
                            $('<button type=\"button\" class=\"button button-small autocep-store-edit-btn\" />')
                                .text('" . esc_js(__('Editar', 'autocep')) . "')
                                .data('store', loja)
                        );
                        \$acoes.append(' ');
                        \$acoes.append(
                            $('<button type=\"button\" class=\"button button-small autocep-store-delete-btn\" />')
                                .text('" . esc_js(__('Excluir', 'autocep')) . "')
                                .data('id', loja.id)
                        );
                        \$linha.append(\$acoes);

                        \$tbody.append(\$linha);
                    });
                }

                function autocepResetStoreForm() {
                    $('#autocep-store-id').val('');
                    $('#autocep-store-name').val('');
                    $('#autocep-store-cep').val('');
                    $('#autocep-store-radius').val('15');
                    $('#autocep-store-fee').val('0');
                    $('#autocep-store-manual-ceps').val('');
                    $('#autocep-store-instructions').val('');
                    $('#autocep-store-form-title').text('" . esc_js(__('Adicionar Nova Loja', 'autocep')) . "');
                    $('#autocep-store-cancel-btn').hide();
                }

                $('#autocep-store-save-btn').on('click', function () {
                    var \$btn = $(this);
                    var \$feedback = $('#autocep-store-feedback');

                    var payload = {
                        action: 'autocep_save_store',
                        nonce: nonce,
                        id: $('#autocep-store-id').val(),
                        name: $('#autocep-store-name').val(),
                        cep: $('#autocep-store-cep').val(),
                        radius_km: $('#autocep-store-radius').val(),
                        pickup_fee: $('#autocep-store-fee').val(),
                        manual_ceps: $('#autocep-store-manual-ceps').val(),
                        instructions: $('#autocep-store-instructions').val()
                    };

                    \$btn.prop('disabled', true);
                    \$feedback.text('" . esc_js(__('Salvando...', 'autocep')) . "');

                    $.post(ajaxUrl, payload)
                        .done(function (response) {
                            if (response && response.success) {
                                autocepStores = (response.data && response.data.stores) || [];
                                autocepRenderStoresTable();
                                autocepResetStoreForm();
                                \$feedback.text('" . esc_js(__('Loja salva com sucesso.', 'autocep')) . "');
                            } else {
                                \$feedback.text((response && response.data && response.data.message) || '" . esc_js(__('Não foi possível salvar a loja.', 'autocep')) . "');
                            }
                        })
                        .fail(function () {
                            \$feedback.text('" . esc_js(__('Falha de comunicação ao salvar a loja.', 'autocep')) . "');
                        })
                        .always(function () {
                            \$btn.prop('disabled', false);
                        });
                });

                $('#autocep-store-cancel-btn').on('click', function () {
                    autocepResetStoreForm();
                });

                $(document).on('click', '.autocep-store-edit-btn', function () {
                    var loja = $(this).data('store');

                    if (!loja) {
                        return;
                    }

                    $('#autocep-store-id').val(loja.id);
                    $('#autocep-store-name').val(loja.name);
                    $('#autocep-store-cep').val(loja.cep);
                    $('#autocep-store-radius').val(loja.radius_km);
                    $('#autocep-store-fee').val(loja.pickup_fee);
                    $('#autocep-store-manual-ceps').val(loja.manual_ceps || '');
                    $('#autocep-store-instructions').val(loja.instructions);
                    $('#autocep-store-form-title').text('" . esc_js(__('Editar Loja', 'autocep')) . "');
                    $('#autocep-store-cancel-btn').show();

                    var \$alvo = $('#autocep-store-form-title');

                    if (\$alvo.length) {
                        $('html, body').animate({ scrollTop: \$alvo.offset().top - 50 }, 300);
                    }
                });

                $(document).on('click', '.autocep-store-delete-btn', function () {
                    if (!window.confirm(" . wp_json_encode(__('Tem certeza que deseja excluir esta loja?', 'autocep')) . ")) {
                        return;
                    }

                    var id = $(this).data('id');

                    $.post(ajaxUrl, { action: 'autocep_delete_store', nonce: nonce, id: id })
                        .done(function (response) {
                            if (response && response.success) {
                                autocepStores = (response.data && response.data.stores) || [];
                                autocepRenderStoresTable();
                            }
                        });
                });

                autocepRenderStoresTable();

                // Status rápido (um clique) da API do Google Maps.
                $('#autocep-google-status-btn').on('click', function () {
                    var \$btn = $(this);
                    var \$result = $('#autocep-google-status-result');

                    \$btn.prop('disabled', true);
                    \$result.html('<p style=\"color:#6b7280;\">" . esc_js(__('Verificando...', 'autocep')) . "</p>');

                    $.post(ajaxUrl, {
                        action: 'autocep_check_google_maps_status',
                        nonce: nonce
                    })
                    .done(function (response) {
                        if (!response || !response.success || !response.data) {
                            \$result.html('<p style=\"color:#b32d2e;\"><strong>" . esc_js(__('Erro de comunicação.', 'autocep')) . "</strong></p>');
                            return;
                        }

                        var d = response.data;
                        var html = '';

                        if ('ok' === d.status) {
                            html = '<p style=\"color:#1a7d36; margin-top:10px;\"><strong>🟢 " . esc_js(__('API Online', 'autocep')) . "</strong> — ' + d.mensagem + ' (' + d.tempo_ms + ' ms)</p>';
                        } else if ('sem_chave' === d.status) {
                            html = '<p style=\"color:#a07000; margin-top:10px;\"><strong>⚪ " . esc_js(__('Não configurada', 'autocep')) . "</strong> — ' + d.mensagem + '</p>';
                        } else {
                            html = '<p style=\"color:#b32d2e; margin-top:10px;\"><strong>🔴 " . esc_js(__('API com problema', 'autocep')) . "</strong> — ' + d.mensagem + (d.tempo_ms ? ' (' + d.tempo_ms + ' ms)' : '') + '</p>';
                        }

                        \$result.html(html);
                    })
                    .fail(function () {
                        \$result.html('<p style=\"color:#b32d2e;\">" . esc_js(__('Erro de comunicação.', 'autocep')) . "</p>');
                    })
                    .always(function () {
                        \$btn.prop('disabled', false);
                    });
                });

                // Monta o HTML de feedback (verde/vermelho/âmbar) para os
                // testes de conexão de API — reaproveitado pelos testes
                // do Melhor Envio e da Frenet, que usam o mesmo
                // vocabulário de status vindo do backend.
                function autocepRenderApiTestResult(d) {
                    if ('ok' === d.status) {
                        return '<p style=\"color:#1a7d36; margin-top:0;\"><strong>🟢 " . esc_js(__('API Operacional', 'autocep')) . "</strong> — ' + d.mensagem + (d.tempo_ms ? ' (' + d.tempo_ms + ' ms)' : '') + '</p>';
                    }

                    if ('sem_token' === d.status || 'sem_cep' === d.status) {
                        return '<p style=\"color:#a07000; margin-top:0;\"><strong>⚪ " . esc_js(__('Não configurado', 'autocep')) . "</strong> — ' + d.mensagem + '</p>';
                    }

                    if ('token_invalido' === d.status) {
                        return '<p style=\"color:#b32d2e; margin-top:0;\"><strong>🔴 " . esc_js(__('Token Inválido', 'autocep')) . "</strong> — ' + d.mensagem + '</p>';
                    }

                    return '<p style=\"color:#b32d2e; margin-top:0;\"><strong>🔴 " . esc_js(__('API Fora do Ar', 'autocep')) . "</strong> — ' + d.mensagem + (d.tempo_ms ? ' (' + d.tempo_ms + ' ms)' : '') + '</p>';
                }

                $('#autocep-test-melhorenvio-btn').on('click', function () {
                    var \$btn = $(this);
                    var \$result = $('#autocep-test-melhorenvio-result');

                    \$btn.prop('disabled', true);
                    \$result.html('<p style=\"color:#6b7280;\">" . esc_js(__('Testando conexão com o Melhor Envio...', 'autocep')) . "</p>');

                    $.post(ajaxUrl, { action: 'autocep_test_melhor_envio', nonce: nonce })
                        .done(function (response) {
                            if (!response || !response.success || !response.data) {
                                \$result.html('<p style=\"color:#b32d2e;\"><strong>" . esc_js(__('Erro de comunicação.', 'autocep')) . "</strong></p>');
                                return;
                            }

                            \$result.html(autocepRenderApiTestResult(response.data));
                        })
                        .fail(function () {
                            \$result.html('<p style=\"color:#b32d2e;\">" . esc_js(__('Erro de comunicação.', 'autocep')) . "</p>');
                        })
                        .always(function () {
                            \$btn.prop('disabled', false);
                        });
                });

                $('#autocep-test-frenet-btn').on('click', function () {
                    var \$btn = $(this);
                    var \$result = $('#autocep-test-frenet-result');

                    \$btn.prop('disabled', true);
                    \$result.html('<p style=\"color:#6b7280;\">" . esc_js(__('Testando conexão com a Frenet...', 'autocep')) . "</p>');

                    $.post(ajaxUrl, { action: 'autocep_test_frenet', nonce: nonce })
                        .done(function (response) {
                            if (!response || !response.success || !response.data) {
                                \$result.html('<p style=\"color:#b32d2e;\"><strong>" . esc_js(__('Erro de comunicação.', 'autocep')) . "</strong></p>');
                                return;
                            }

                            \$result.html(autocepRenderApiTestResult(response.data));
                        })
                        .fail(function () {
                            \$result.html('<p style=\"color:#b32d2e;\">" . esc_js(__('Erro de comunicação.', 'autocep')) . "</p>');
                        })
                        .always(function () {
                            \$btn.prop('disabled', false);
                        });
                });

                // Testador de Distância entre CEPs.
                $('#autocep-test-distance-btn').on('click', function () {
                    var \$btn = $(this);
                    var \$feedback = $('#autocep-test-distance-feedback');
                    var \$result = $('#autocep-test-distance-result');

                    var cepA = $('#autocep-dist-cep-a').val().replace(/\D/g, '');
                    var cepB = $('#autocep-dist-cep-b').val().replace(/\D/g, '');

                    if (cepA.length !== 8 || cepB.length !== 8) {
                        \$feedback.text('" . esc_js(__('Informe dois CEPs com 8 dígitos.', 'autocep')) . "');
                        return;
                    }

                    \$btn.prop('disabled', true);
                    \$feedback.text('" . esc_js(__('Consultando API do Google Maps...', 'autocep')) . "');
                    \$result.hide();

                    $.post(ajaxUrl, {
                        action: 'autocep_test_distance',
                        nonce: nonce,
                        cep_a: cepA,
                        cep_b: cepB
                    })
                    .done(function (response) {
                        \$feedback.text('');
                        \$result.show();

                        if (!response || !response.success || !response.data) {
                            \$result.html('<p style=\"color:#b32d2e;\"><strong>" . esc_js(__('Erro de comunicação.', 'autocep')) . "</strong></p>');
                            return;
                        }

                        var d = response.data;

                        if (d.ok) {
                            var cor = '#1a7d36';
                            var html = '<p style=\"color:' + cor + ';\"><strong>✅ " . esc_js(__('Distância calculada com sucesso!', 'autocep')) . "</strong></p>';
                            html += '<table style=\"border-collapse:collapse; width:100%;\">';
                            html += '<tr><th style=\"text-align:left; padding:4px 8px; background:#f6f7f7;\">" . esc_js(__('CEP da loja', 'autocep')) . "</th><td style=\"padding:4px 8px;\">' + d.cep_a + ' — ' + d.cidade_a + '</td></tr>';
                            html += '<tr><th style=\"text-align:left; padding:4px 8px; background:#f6f7f7;\">" . esc_js(__('CEP do cliente', 'autocep')) . "</th><td style=\"padding:4px 8px;\">' + d.cep_b + ' — ' + d.cidade_b + '</td></tr>';
                            html += '<tr><th style=\"text-align:left; padding:4px 8px; background:#f6f7f7;\">" . esc_js(__('Distância rodoviária', 'autocep')) . "</th><td style=\"padding:4px 8px; font-weight:600;\">' + d.distancia + ' km</td></tr>';
                            html += '</table>';

                            // Deixa sempre explícito qual método calculou a
                            // distância — antes esse aviso dependia de um
                            // campo (distancia_fonte) que o servidor nunca
                            // enviava, então nunca aparecia, mesmo quando o
                            // resultado vinha da estimativa (não do Google).
                            if ('haversine' === d.fonte) {
                                html += '<p style=\"margin-top:8px; font-size:12px; color:#a07000;\">⚠️ " . esc_js(__('Distância ESTIMADA (linha reta × 1.3), pois o Google Maps não respondeu (chave inválida, API não habilitada, ou sem cobertura de rota para essa região). O resultado é aproximado, mas geralmente suficiente para decidir se a cidade está dentro do raio configurado. Confira a aba Diagnóstico e Logs para o motivo exato da falha do Google Maps.', 'autocep')) . "</p>';
                            } else if ('google_maps_endereco' === d.fonte) {
                                html += '<p style=\"margin-top:8px; font-size:12px; color:#1a7d36;\">✅ " . esc_js(__('Distância real calculada pela API do Google Maps, usando o endereço completo (rua/bairro/cidade).', 'autocep')) . "</p>';
                            } else if ('google_maps_cidade' === d.fonte) {
                                html += '<p style=\"margin-top:8px; font-size:12px; color:#1a7d36;\">✅ " . esc_js(__('Distância real calculada pela API do Google Maps, usando apenas Cidade/UF (o endereço completo não foi encontrado pelo Google).', 'autocep')) . "</p>';
                            } else if ('cache' === d.fonte) {
                                html += '<p style=\"margin-top:8px; font-size:12px; color:#555;\">ℹ️ " . esc_js(__('Resultado veio do cache (calculado nos últimos 7 dias). Use o botão \"Testar Agora\" de novo se quiser forçar um novo cálculo.', 'autocep')) . "</p>';
                            } else if ('identico' === d.fonte) {
                                html += '<p style=\"margin-top:8px; font-size:12px; color:#555;\">ℹ️ " . esc_js(__('CEPs idênticos — distância zero, sem necessidade de consultar nenhuma API.', 'autocep')) . "</p>';
                            }
                            html += '<p style=\"margin-top:10px; padding:8px 12px; background:#edfaed; border-left:3px solid #1a7d36; font-size:12px;\">✅ <strong>" . esc_js(__('Cache limpo automaticamente.', 'autocep')) . "</strong> " . esc_js(__('O cache de cotações de frete foi limpo. Agora vá ao carrinho/checkout, simule o frete com o CEP acima e a opção de Retirada em Loja deve aparecer.', 'autocep')) . "</p>';

                            // Tabela de diagnóstico de lojas — mostra se
                            // get_nearby_stores retornou a loja para este CEP.
                            if (d.lojas_debug && d.lojas_debug.length) {
                                html += '<h4 style=\"margin:14px 0 4px;\">" . esc_js(__('Diagnóstico Direto: Lojas × CEP do Cliente', 'autocep')) . "</h4>';
                                html += '<table style=\"border-collapse:collapse; width:100%; font-size:12px;\">';
                                html += '<tr style=\"background:#f6f7f7;\"><th style=\"padding:4px 8px; text-align:left;\">Loja</th><th style=\"padding:4px 8px;\">CEP loja</th><th style=\"padding:4px 8px;\">Raio (km)</th><th style=\"padding:4px 8px;\">Distância calculada</th><th style=\"padding:4px 8px;\">Aparece?</th><th style=\"padding:4px 8px;\">CEPs Manuais</th></tr>';
                                d.lojas_debug.forEach(function(l) {
                                    var cor = l.no_raio ? '#edfaed' : '#fff8f8';
                                    var sim = l.no_raio ? '✅ Sim' : '❌ Não';
                                    var dist = l.distancia_km !== null ? l.distancia_km + ' km' : '(não calculada)';
                                    html += '<tr style=\"background:' + cor + ';\"><td style=\"padding:4px 8px;\">' + l.nome + '</td><td style=\"padding:4px 8px;\">' + l.cep_loja + '</td><td style=\"padding:4px 8px;\">' + l.raio + '</td><td style=\"padding:4px 8px;\">' + dist + '</td><td style=\"padding:4px 8px; font-weight:bold;\">' + sim + '</td><td style=\"padding:4px 8px; font-size:11px;\">' + (l.manual_ceps || '—') + '</td></tr>';
                                });
                                html += '</table>';
                            }

                            // Informações de zona de entrega do WooCommerce.
                            if (d.zona_info) {
                                var zi = d.zona_info;
                                html += '<h4 style=\"margin:14px 0 4px;\">" . esc_js(__('Zona de Entrega do WooCommerce para este CEP', 'autocep')) . "</h4>';

                                if (zi.erro) {
                                    html += '<p style=\"color:#b32d2e;\">' + zi.erro + '</p>';
                                } else {
                                    var temRetirada = false;
                                    var nomesMetodos = [];
                                    if (zi.metodos && zi.metodos.length) {
                                        zi.metodos.forEach(function(m) {
                                            nomesMetodos.push(m.titulo + ' (' + m.id + ', ativo: ' + m.ativo + ')');
                                            if (m.id === 'autocep_store_pickup') temRetirada = true;
                                        });
                                    }

                                    html += '<table style=\"border-collapse:collapse; width:100%; margin-top:4px;\">';
                                    html += '<tr><th style=\"text-align:left; padding:4px 8px; background:#f6f7f7;\">" . esc_js(__('Zona encontrada', 'autocep')) . "</th><td style=\"padding:4px 8px;\">' + zi.zona_nome + ' (ID ' + zi.zona_id + ')</td></tr>';
                                    html += '<tr><th style=\"text-align:left; padding:4px 8px; background:#f6f7f7;\">" . esc_js(__('Métodos na zona', 'autocep')) . "</th><td style=\"padding:4px 8px;\">' + (nomesMetodos.length ? nomesMetodos.join('<br>') : '" . esc_js(__('(nenhum método ativo)', 'autocep')) . "') + '</td></tr>';
                                    html += '</table>';

                                    if (zi.todas_zonas && zi.todas_zonas.length) {
                                        html += '<h4 style=\"margin:14px 0 4px;\">" . esc_js(__('Ordem das Zonas (do topo para baixo = maior prioridade)', 'autocep')) . "</h4>';
                                        html += '<table style=\"border-collapse:collapse; width:100%; font-size:12px;\">';
                                        html += '<tr style=\"background:#f6f7f7;\"><th style=\"text-align:left; padding:4px 8px;\">Ordem</th><th style=\"text-align:left; padding:4px 8px;\">Nome</th><th style=\"text-align:left; padding:4px 8px;\">CEPs específicos</th></tr>';
                                        zi.todas_zonas.forEach(function(z) {
                                            var destaque = (z.id == zi.zona_id) ? 'background:#fffbcc; font-weight:bold;' : '';
                                            html += '<tr style=\"' + destaque + '\"><td style=\"padding:4px 8px;\">' + z.ordem + '</td><td style=\"padding:4px 8px;\">' + z.nome + (z.id == zi.zona_id ? ' ← usada' : '') + '</td><td style=\"padding:4px 8px;\">' + (z.ceps || '(nenhum — vale para todo o Brasil)') + '</td></tr>';
                                        });
                                        html += '</table>';
                                        html += '<p style=\"font-size:11px; color:#646970; margin-top:4px;\">" . esc_js(__('O WooCommerce usa a primeira zona da lista que bater com o CEP do cliente. Se uma zona mais específica (com CEP) estiver ABAIXO de uma genérica, a genérica ganha sempre.', 'autocep')) . "</p>';
                                    }

                                    if (!temRetirada) {
                                        html += '<p style=\"margin-top:8px; padding:8px 12px; background:#fcf0d1; border-left:3px solid #a07000; font-size:12px;\">⚠️ <strong>" . esc_js(__('Causa encontrada:', 'autocep')) . "</strong> " . esc_js(__('O método "Retirada em Loja por Proximidade — AutoCEP" (autocep_store_pickup) NÃO está na zona acima. Vá em WooCommerce > Configurações > Entrega, abra a zona indicada e clique em "Adicionar Método de Envio" para adicionar o método de Retirada.', 'autocep')) . "</p>';
                                    } else {
                                        html += '<p style=\"margin-top:8px; padding:8px 12px; background:#edfaed; border-left:3px solid #1a7d36; font-size:12px;\">✅ " . esc_js(__('O método de Retirada em Loja está na zona correta. Se ainda não aparece no carrinho, tente limpar o cache do WP Rocket e do WooCommerce.', 'autocep')) . "</p>';
                                    }
                                }
                            }

                            \$result.html(html);
                        } else {
                            var erroCode = String(d.erro_code || '');
                            var dica = '';

                            if (erroCode.indexOf('request_denied') !== -1) {
                                dica = '" . esc_js(__('Causa: REQUEST_DENIED — a chave de API não tem a "Distance Matrix API" habilitada. Acesse o Google Cloud Console → Biblioteca de APIs → habilite "Distance Matrix API" para este projeto.', 'autocep')) . "';
                            } else if (erroCode.indexOf('element_status') !== -1 || erroCode.indexOf('zero_results') !== -1) {
                                dica = '" . esc_js(__('Causa: ZERO_RESULTS — o Google não encontrou uma rota rodoviária entre estes dois pontos. Isso acontece em cidades muito pequenas sem estradas mapeadas. Solução: cadastre o CEP na lista "CEPs Atendidos Manualmente" da loja para garantir que ela apareça para este CEP.', 'autocep')) . "';
                            } else {
                                dica = '" . esc_js(__('Verifique se a "Distance Matrix API" está habilitada para esta chave no Google Cloud Console.', 'autocep')) . "';
                            }

                            var html = '<p style=\"color:#b32d2e;\"><strong>❌ " . esc_js(__('A API do Google Maps retornou um erro para este par de CEPs.', 'autocep')) . "</strong></p>';
                            html += '<table style=\"border-collapse:collapse; width:100%;\">';
                            html += '<tr><th style=\"text-align:left; padding:4px 8px; background:#f6f7f7;\">" . esc_js(__('CEP da loja', 'autocep')) . "</th><td style=\"padding:4px 8px;\">' + d.cep_a + ' — ' + d.cidade_a + '</td></tr>';
                            html += '<tr><th style=\"text-align:left; padding:4px 8px; background:#f6f7f7;\">" . esc_js(__('CEP do cliente', 'autocep')) . "</th><td style=\"padding:4px 8px;\">' + d.cep_b + ' — ' + d.cidade_b + '</td></tr>';
                            html += '<tr><th style=\"text-align:left; padding:4px 8px; background:#f6f7f7;\">" . esc_js(__('Código do erro', 'autocep')) . "</th><td style=\"padding:4px 8px; color:#b32d2e; font-family:monospace;\">' + (erroCode || 'desconhecido') + '</td></tr>';
                            html += '<tr><th style=\"text-align:left; padding:4px 8px; background:#f6f7f7;\">" . esc_js(__('Detalhe', 'autocep')) . "</th><td style=\"padding:4px 8px; color:#b32d2e; font-size:12px;\">' + (d.erro || '') + '</td></tr>';
                            html += '</table>';
                            if (dica) {
                                html += '<p style=\"margin-top:10px; padding:8px 12px; background:#fcf0d1; border-left:3px solid #a07000; font-size:12px;\">💡 ' + dica + '</p>';
                            }
                            \$result.html(html);
                        }
                    })
                    .fail(function () {
                        \$feedback.text('" . esc_js(__('Falha ao conectar com o servidor.', 'autocep')) . "');
                    })
                    .always(function () {
                        \$btn.prop('disabled', false);
                    });
                });
            });
        ";

        wp_add_inline_script('jquery', $admin_js);

        wp_add_inline_style('common', '
            /* ── AutoCEP Admin: design moderno ─────────────────────── */

            /* Cabeçalho azul do painel — substitui o antigo <h1> "solto"
               em fundo branco, reforçando a identidade em tons de azul
               pedida para telas de configuração e cabeçalhos do plugin. */
            .autocep-page-header {
                display: flex;
                align-items: center;
                gap: 12px;
                background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
                border-radius: 10px;
                padding: 16px 22px;
                margin: 16px 0 18px;
                box-shadow: 0 1px 4px rgba(0,0,0,.08);
            }
            .autocep-page-header h1 {
                margin: 0;
                padding: 0;
                color: #fff;
                font-size: 21px;
                flex: 1 1 auto;
            }
            .autocep-page-header-links {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .autocep-page-header-links a {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 6px 12px;
                border-radius: 20px;
                background: rgba(255,255,255,.15);
                color: #fff;
                font-size: 12px;
                font-weight: 600;
                text-decoration: none;
                white-space: nowrap;
                transition: background .15s;
            }
            .autocep-page-header-links a:hover {
                background: rgba(255,255,255,.28);
                color: #fff;
            }
            .autocep-page-header-links .dashicons {
                font-size: 15px;
                width: 15px;
                height: 15px;
            }
            .autocep-page-header-icon {
                display: inline-block;
                flex-shrink: 0;
                width: 26px;
                height: 26px;
                background-color: #fff;
                -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27%3E%3Cpath d=%27M18 10.2C18 7.33 15.76 5 13 5s-5 2.33-5 5.2c0 3.9 5 9.3 5 9.3s5-5.4 5-9.3Zm-5 2.4a2.4 2.4 0 1 1 0-4.8 2.4 2.4 0 0 1 0 4.8Z%27/%3E%3C/svg%3E") center / contain no-repeat;
                mask: url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27%3E%3Cpath d=%27M18 10.2C18 7.33 15.76 5 13 5s-5 2.33-5 5.2c0 3.9 5 9.3 5 9.3s5-5.4 5-9.3Zm-5 2.4a2.4 2.4 0 1 1 0-4.8 2.4 2.4 0 0 1 0 4.8Z%27/%3E%3C/svg%3E") center / contain no-repeat;
            }

            /* Navegação de abas */
            .autocep-nav-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 2px 0;
                margin: 0 0 0;
                padding: 0;
                border-bottom: 2px solid #e5e7eb;
                align-items: flex-end;
                background: #fff;
                padding: 0 4px;
                border-radius: 8px 8px 0 0;
                box-shadow: 0 1px 4px rgba(0,0,0,.06);
            }
            .autocep-nav-tabs .nav-tab {
                font-size: 12.5px;
                padding: 9px 14px;
                margin: 0;
                white-space: nowrap;
                border: none !important;
                border-bottom: 2px solid transparent !important;
                color: #6b7280;
                background: transparent !important;
                border-radius: 0;
                font-weight: 500;
            }
            .autocep-nav-tabs .nav-tab:hover { color: #3b82f6; background: #eff6ff !important; }
            .autocep-nav-tabs .nav-tab-active { color: #1d4ed8 !important; border-bottom: 2px solid #3b82f6 !important; font-weight: 600; }
            .autocep-nav-tabs .nav-tab .dashicons {
                font-size: 15px;
                width: 15px;
                height: 15px;
                line-height: 1;
                vertical-align: -2px;
                margin-right: 2px;
            }
            /* Ícone de Headset/Fone (Suporte Técnico) — os Dashicons
               nativos do WordPress não têm um ícone de headset, então
               este é desenhado via máscara SVG e colorido com
               "currentColor", assim como um dashicons normal, para se
               comportar igual nos estados de hover/ativo da aba. */
            .autocep-icon-headset {
                display: inline-block;
                width: 1em;
                height: 1em;
                background-color: currentColor;
                -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27%3E%3Cpath d=%27M12 3a8 8 0 0 0-8 8v6a3 3 0 0 0 3 3h1a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H6.2A5.8 5.8 0 0 1 18 11v1.2A2 2 0 0 0 15.7 15H16a2 2 0 0 0 2-2v-2A8 8 0 0 0 12 3Zm4 12v3.2a1.8 1.8 0 0 1-1.8 1.8H13v-2h1.8a.2.2 0 0 0 .2-.2V15h1Z%27/%3E%3C/svg%3E") center / contain no-repeat;
                mask: url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27%3E%3Cpath d=%27M12 3a8 8 0 0 0-8 8v6a3 3 0 0 0 3 3h1a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H6.2A5.8 5.8 0 0 1 18 11v1.2A2 2 0 0 0 15.7 15H16a2 2 0 0 0 2-2v-2A8 8 0 0 0 12 3Zm4 12v3.2a1.8 1.8 0 0 1-1.8 1.8H13v-2h1.8a.2.2 0 0 0 .2-.2V15h1Z%27/%3E%3C/svg%3E") center / contain no-repeat;
                vertical-align: -0.15em;
            }
            .autocep-nav-tabs .nav-tab .autocep-icon-headset {
                width: 15px;
                height: 15px;
                vertical-align: -2px;
                margin-right: 2px;
            }
            .autocep-nav-tabs .autocep-tab-group-sep {
                width: 1px; height: 20px; background: #e5e7eb;
                margin: 0 4px 6px; align-self: flex-end; flex-shrink: 0;
            }

            /* Formulários */
            .autocep-apis-table th, .autocep-apis-table td { padding: 8px 10px; }
            .autocep-diagnostics-actions { margin: 15px 0; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
            .autocep-apis-status-box {
                max-width: 600px;
                margin-top: 10px;
                padding: 14px 16px;
                background: #f0f6ff;
                border-left: 4px solid #2271b1;
                border-radius: 4px;
            }
            .autocep-apis-status-box p { margin: 0 0 10px; font-size: 13px; }
            .autocep-diagnostics-pointer {
                display: flex;
                align-items: flex-start;
                gap: 8px;
                max-width: 700px;
                margin: 0 0 20px;
                padding: 12px 16px;
                background: #f6f7f7;
                border-left: 4px solid #8c8f94;
                border-radius: 4px;
                font-size: 13px;
            }
            .autocep-diagnostics-pointer .dashicons { color: #2271b1; margin-top: 2px; }
            .autocep-tests-heading {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-top: 30px;
            }
            .autocep-tests-heading .dashicons { color: #2271b1; }
            .autocep-google-diff-box {
                max-width: 700px;
                margin: 10px 0 20px;
                padding: 14px 16px;
                background: #f0f6ff;
                border-left: 4px solid #2271b1;
                border-radius: 4px;
            }
            .autocep-google-diff-box p { margin: 0; font-size: 13px; }
            .autocep-google-status-box {
                max-width: 640px;
                margin: 0 0 24px;
                padding: 14px 16px;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 6px;
            }
            .autocep-google-status-box h3 { margin-top: 0; }
            #autocep-google-status-result p { margin: 10px 0 0; font-size: 13px; }
            .autocep-logs-table td, .autocep-logs-table th { padding: 6px 10px; font-size: 13px; }

            /* ── Frete Analytics ────────────────────────────────────── */
            .autocep-analytics-wrap { padding: 20px 0; }
            .autocep-analytics-header {
                display: flex; align-items: center; justify-content: space-between;
                flex-wrap: wrap; gap: 12px; margin-bottom: 24px;
                background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
                padding: 16px 20px; box-shadow: 0 1px 4px rgba(0,0,0,.05);
            }
            .autocep-analytics-title { display: flex; align-items: center; gap: 10px; }
            .autocep-analytics-title h2 { margin: 0; font-size: 20px; color: #111827; }
            .autocep-analytics-badge {
                background: #eff6ff; color: #1d4ed8; font-size: 11px;
                font-weight: 600; padding: 3px 10px; border-radius: 20px;
            }
            .autocep-analytics-period { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
            .autocep-period-btn {
                padding: 6px 14px; border: 1px solid #d1d5db; border-radius: 6px;
                background: #fff; color: #374151; font-size: 12px; cursor: pointer;
                text-decoration: none; font-weight: 500; transition: all .15s;
            }
            .autocep-period-btn:hover, .autocep-period-btn.active {
                background: #3b82f6; border-color: #3b82f6; color: #fff;
            }
            .autocep-danger-btn { border-color: #fca5a5 !important; color: #dc2626 !important; }
            .autocep-danger-btn:hover { background: #dc2626 !important; border-color: #dc2626 !important; color: #fff !important; }
            .autocep-period-btn .dashicons { font-size: 14px; vertical-align: middle; }

            /* KPI Cards */
            .autocep-kpi-grid {
                display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
                gap: 16px; margin-bottom: 24px;
            }
            .autocep-kpi-card {
                background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
                padding: 18px 16px; display: flex; align-items: center; gap: 14px;
                box-shadow: 0 1px 4px rgba(0,0,0,.05); transition: box-shadow .2s;
            }
            .autocep-kpi-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.1); }
            .autocep-kpi-icon {
                width: 44px; height: 44px; border-radius: 10px;
                display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            }
            .autocep-kpi-icon .dashicons { font-size: 22px; color: #fff; }
            .autocep-kpi-blue  { background: #3b82f6; }
            .autocep-kpi-green { background: #10b981; }
            .autocep-kpi-orange{ background: #f59e0b; }
            .autocep-kpi-teal  { background: #06b6d4; }
            .autocep-kpi-info  { flex: 1; min-width: 0; }
            .autocep-kpi-label { display: block; font-size: 11px; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; }
            .autocep-kpi-value { display: block; font-size: 22px; font-weight: 700; color: #111827; margin: 2px 0; }
            .autocep-kpi-sub   { display: block; font-size: 11px; color: #9ca3af; }
            .autocep-empty-val { color: #d1d5db; }

            /* Gráficos */
            .autocep-charts-grid {
                display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;
            }
            @media (max-width: 900px) { .autocep-charts-grid { grid-template-columns: 1fr; } }
            .autocep-chart-card {
                background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
                overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.05);
            }
            .autocep-chart-header {
                padding: 14px 18px; border-bottom: 1px solid #f3f4f6;
                display: flex; align-items: center;
            }
            .autocep-chart-header h3 {
                margin: 0; font-size: 14px; font-weight: 600; color: #374151;
                display: flex; align-items: center; gap: 6px;
            }
            .autocep-chart-header .dashicons { color: #6b7280; font-size: 16px; }
            .autocep-chart-body { padding: 16px 18px; min-height: 200px; }
            .autocep-chart-empty {
                display: flex; flex-direction: column; align-items: center;
                justify-content: center; padding: 40px; color: #9ca3af; text-align: center;
            }
            .autocep-chart-empty .dashicons { font-size: 40px; margin-bottom: 8px; }
            .autocep-chart-empty p { margin: 0; font-size: 13px; }

            /* Tabelas modernas */
            .autocep-tables-grid {
                display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;
            }
            @media (max-width: 900px) { .autocep-tables-grid { grid-template-columns: 1fr; } }
            .autocep-table-card {
                background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
                overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.05);
            }
            .autocep-table-header { padding: 12px 18px; border-bottom: 1px solid #f3f4f6; }
            .autocep-table-header h3 { margin: 0; font-size: 13px; font-weight: 600; color: #374151; }
            .autocep-modern-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .autocep-modern-table th {
                background: #f9fafb; color: #6b7280; font-size: 11px; font-weight: 600;
                text-transform: uppercase; letter-spacing: .04em; padding: 8px 16px; text-align: left;
            }
            .autocep-modern-table td { padding: 9px 16px; border-top: 1px solid #f3f4f6; color: #374151; }
            .autocep-modern-table tr:hover td { background: #f9fafb; }
            .autocep-rank {
                display: inline-flex; align-items: center; justify-content: center;
                width: 22px; height: 22px; border-radius: 50%;
                background: #f3f4f6; font-size: 11px; font-weight: 700; color: #6b7280;
            }
            .autocep-rank-top { background: #fef3c7; color: #92400e; }
            .autocep-badge-count {
                background: #eff6ff; color: #1d4ed8; font-size: 12px;
                font-weight: 600; padding: 2px 8px; border-radius: 12px;
            }
            .autocep-info-note {
                background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
                padding: 12px 16px; font-size: 12px; color: #166534;
                display: flex; gap: 8px; align-items: flex-start;
            }
            .autocep-analytics-empty {
                text-align: center; padding: 60px 20px; color: #6b7280;
            }
            .autocep-analytics-empty .dashicons { font-size: 48px; color: #d1d5db; display: block; margin-bottom: 12px; }

            /* ── Suporte & Chamados ────────────────────────────────── */
            .autocep-support-wrap { padding: 20px 0; }
            .autocep-support-header {
                background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
                border-radius: 10px; padding: 20px 24px; margin-bottom: 24px;
                display: flex; align-items: center; justify-content: space-between;
                flex-wrap: wrap; gap: 16px;
            }
            .autocep-support-brand { display: flex; align-items: center; gap: 14px; }
            .autocep-support-brand h2 { margin: 0; color: #fff; font-size: 20px; }
            .autocep-support-brand p { margin: 4px 0 0; color: #bfdbfe; font-size: 13px; }
            .autocep-support-contacts { display: flex; gap: 10px; flex-wrap: wrap; }
            .autocep-contact-btn {
                display: flex; align-items: center; gap: 6px; padding: 9px 16px;
                border-radius: 8px; font-size: 13px; font-weight: 600;
                text-decoration: none; transition: all .15s;
            }
            .autocep-contact-btn .dashicons { font-size: 16px; }
            .autocep-contact-email { background: #fff; color: #1d4ed8; }
            .autocep-contact-email:hover { background: #eff6ff; }
            .autocep-contact-whatsapp { background: #25d366; color: #fff; }
            .autocep-contact-whatsapp:hover { background: #128c7e; color: #fff; }
            .autocep-contact-resource { background: rgba(255,255,255,.15); color: #fff; }
            .autocep-contact-resource:hover { background: rgba(255,255,255,.28); color: #fff; }

            .autocep-support-grid {
                display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px;
            }
            @media (max-width: 900px) { .autocep-support-grid { grid-template-columns: 1fr; } }

            .autocep-support-card {
                background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
                overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.05);
            }
            .autocep-support-card-header {
                padding: 14px 18px; border-bottom: 1px solid #f3f4f6;
                display: flex; align-items: center; gap: 8px;
                background: #f9fafb;
            }
            .autocep-support-card-header h3 { margin: 0; font-size: 14px; font-weight: 600; color: #374151; }
            .autocep-support-card-header .dashicons { color: #3b82f6; font-size: 18px; }
            .autocep-support-card-body { padding: 18px; }

            .autocep-field-group { margin-bottom: 14px; }
            .autocep-field-group label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 5px; }
            .autocep-field-group input[type="text"],
            .autocep-field-group input[type="email"],
            .autocep-field-group select,
            .autocep-field-group textarea {
                width: 100%; padding: 9px 12px; border: 1px solid #d1d5db;
                border-radius: 7px; font-size: 13px; color: #111827;
                transition: border .15s; background: #fff; box-sizing: border-box;
            }
            .autocep-field-group input:focus,
            .autocep-field-group select:focus,
            .autocep-field-group textarea:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
            .autocep-field-group textarea { resize: vertical; min-height: 100px; }

            .autocep-btn-primary {
                display: inline-flex; align-items: center; gap: 6px;
                background: #3b82f6; color: #fff; border: none; cursor: pointer;
                padding: 10px 20px; border-radius: 8px; font-size: 13px;
                font-weight: 600; transition: background .15s; width: 100%;
                justify-content: center;
            }
            .autocep-btn-primary:hover { background: #1d4ed8; }
            .autocep-btn-primary .dashicons { font-size: 16px; }

            .autocep-ticket-feedback {
                padding: 10px 14px; border-radius: 7px; font-size: 13px;
                margin-bottom: 12px; font-weight: 500;
            }
            .autocep-fb-ok  { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
            .autocep-fb-erro{ background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

            .autocep-sysinfo-table { width: 100%; font-size: 12px; border-collapse: collapse; }
            .autocep-sysinfo-table td { padding: 5px 0; border-bottom: 1px solid #f3f4f6; }
            .autocep-sysinfo-table td:first-child { color: #6b7280; font-weight: 500; width: 50%; }
            .autocep-sysinfo-table code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 11px; }

            .autocep-checklist { display: flex; flex-direction: column; gap: 10px; }
            .autocep-checklist-item { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: #374151; }
            .autocep-checklist-item .dashicons { flex-shrink: 0; margin-top: 1px; }

            /* Meus Chamados */
            .autocep-tickets-list { display: flex; flex-direction: column; gap: 8px; max-height: 360px; overflow-y: auto; }
            .autocep-ticket-row { border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px 10px; font-size: 12px; }
            .autocep-ticket-row-main { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
            .autocep-ticket-numero { font-weight: 700; color: #374151; font-family: monospace; }
            .autocep-ticket-titulo { color: #111827; font-weight: 500; }
            .autocep-ticket-tipo { color: #6b7280; font-size: 11px; }
            .autocep-ticket-row-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
            .autocep-ticket-data { color: #9ca3af; font-size: 11px; }
            .autocep-ticket-status { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
            .autocep-ticket-status--aberto { background: #fef3c7; color: #92400e; }
            .autocep-ticket-status--fechado { background: #dcfce7; color: #166534; }
            .autocep-ticket-toggle-btn {
                margin-left: auto;
                border: 1px solid #d1d5db;
                background: #fff;
                border-radius: 4px;
                padding: 3px 8px;
                font-size: 11px;
                cursor: pointer;
                color: #374151;
            }
            .autocep-ticket-toggle-btn:hover { background: #f9fafb; }

            /* Stores manager */
            .autocep-stores-manager h3 { margin-top: 25px; }
            .autocep-stores-manager .form-table { max-width: 640px; }

            /* Dashboard legacy */
            .autocep-dashboard-tables { display: flex; flex-wrap: wrap; gap: 24px; }
            .autocep-dashboard-table-col { flex: 1 1 320px; min-width: 280px; }

            .autocep-file-drop {
                position: relative; border: 2px dashed #d1d5db; border-radius: 8px;
                padding: 20px; text-align: center; cursor: pointer;
                transition: border-color .15s, background .15s; background: #f9fafb;
            }
            .autocep-file-drop:hover, .autocep-file-drop.autocep-file-has {
                border-color: #3b82f6; background: #eff6ff;
            }
            .autocep-file-drop.autocep-file-drag { border-color: #1d4ed8; background: #dbeafe; }
            .autocep-file-drop .dashicons { font-size: 28px; color: #9ca3af; display: block; margin-bottom: 4px; }
            .autocep-file-drop p { margin: 0 0 2px; font-size: 13px; color: #374151; font-weight: 500; }
            .autocep-file-hint { font-size: 11px; color: #9ca3af; }

            /* Cards de preview de arquivo */
            .autocep-file-card {
                position: relative; width: 80px; background: #fff;
                border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px;
                text-align: center; font-size: 11px; color: #374151;
                box-shadow: 0 1px 3px rgba(0,0,0,.06);
            }
            .autocep-file-card-name { display: block; margin-top: 4px; word-break: break-all; line-height: 1.2; }
            .autocep-file-card-size { display: block; color: #9ca3af; font-size: 10px; margin-top: 2px; }
            .autocep-file-remove {
                position: absolute; top: -6px; right: -6px;
                background: #ef4444; color: #fff; border: none; border-radius: 50%;
                width: 18px; height: 18px; cursor: pointer; padding: 0;
                display: flex; align-items: center; justify-content: center;
                box-shadow: 0 1px 3px rgba(0,0,0,.2);
            }
            .autocep-file-remove .dashicons { font-size: 12px; width: 12px; height: 12px; line-height: 12px; }

            /* Submenus do menu lateral customizados */
            #adminmenu .toplevel_page_autocep .wp-submenu a { padding: 5px 10px 5px 26px; }

            /* Melhoria geral formulários admin */
            .wrap .form-table td { padding: 12px 10px; }
            .wrap .form-table th { padding: 16px 10px 16px 0; }

            /* Tables grades */
            .autocep-tables-grid { grid-template-columns: 1fr; }
            @media (min-width: 900px) {
                .autocep-tables-grid { grid-template-columns: 1fr 1fr; }
                .autocep-tables-grid .autocep-table-card:first-child { grid-column: 1 / -1; }
            }
        ');
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab_atual = isset($_GET['tab']) && in_array($_GET['tab'], self::TABS, true)
            ? sanitize_key($_GET['tab'])
            : 'general';

        // A antiga aba "Checkout Moderno" agora faz parte da aba Checkout.
        if ('modern_checkout' === $tab_atual) {
            $tab_atual = 'checkout_shipping';
        }

        $abas = array(
            'general'            => array('label' => __('Frete e Busca de CEP', 'autocep'), 'icon' => 'dashicons dashicons-location-alt'),
            'product_shipping'   => array('label' => __('Página de Produto', 'autocep'), 'icon' => 'dashicons dashicons-archive'),
            'cart_shipping'      => array('label' => __('Carrinho', 'autocep'), 'icon' => 'dashicons dashicons-cart'),
            'checkout_shipping'  => array('label' => __('Checkout', 'autocep'), 'icon' => 'dashicons dashicons-money-alt'),
            'post_purchase'      => array('label' => __('Pós-Compra', 'autocep'), 'icon' => 'dashicons dashicons-yes-alt'),
            'growth'             => array('label' => __('Growth Marketing', 'autocep'), 'icon' => 'dashicons dashicons-chart-line'),
            'distance_shipping'  => array('label' => __('Retirada em Loja', 'autocep'), 'icon' => 'dashicons dashicons-store'),
            'integrations'       => array('label' => __('Integrações de Frete', 'autocep'), 'icon' => 'dashicons dashicons-rest-api'),
            'messages'           => array('label' => __('Mensagens & UX', 'autocep'), 'icon' => 'dashicons dashicons-format-chat'),
            'appearance'         => array('label' => __('Aparência', 'autocep'), 'icon' => 'dashicons dashicons-admin-appearance'),
            'shipping_dashboard' => array('label' => __('Analytics', 'autocep'), 'icon' => 'dashicons dashicons-chart-bar'),
            'support'            => array('label' => __('Suporte Técnico', 'autocep'), 'icon' => 'autocep-icon-headset'),
            'feedback'           => array('label' => __('Avalie o Plugin', 'autocep'), 'icon' => 'dashicons dashicons-star-filled'),
            'diagnostics'        => array('label' => __('Diagnóstico e Logs', 'autocep'), 'icon' => 'dashicons dashicons-admin-tools'),
        );

        ?>
        <div class="wrap">
            <div class="autocep-page-header">
                <span class="autocep-page-header-icon" aria-hidden="true"></span>
                <h1><?php esc_html_e('AutoCEP — Configurações', 'autocep'); ?></h1>
                <div class="autocep-page-header-links">
                    <a href="https://tesw.com.br/documentacao-autocep" target="_blank" rel="noopener noreferrer">
                        <span class="dashicons dashicons-media-document"></span> <?php esc_html_e('Documentação', 'autocep'); ?>
                    </a>
                    <a href="https://youtu.be/EkEC8CJ_H5c" target="_blank" rel="noopener noreferrer">
                        <span class="dashicons dashicons-video-alt3"></span> <?php esc_html_e('Vídeo de Demonstração', 'autocep'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=autocep&tab=feedback')); ?>">
                        <span class="dashicons dashicons-star-filled"></span> <?php esc_html_e('Avalie o Plugin', 'autocep'); ?>
                    </a>
                </div>
            </div>

            <?php
            $grupos = array(
                'geral'       => array('general'),
                'frete'       => array('product_shipping', 'cart_shipping', 'checkout_shipping', 'post_purchase'),
                'marketing'   => array('growth'),
                'logistica'   => array('distance_shipping', 'integrations'),
                'ux'          => array('messages', 'appearance'),
                'analytics'   => array('shipping_dashboard', 'support', 'feedback', 'diagnostics'),
            );
            ?>
            <div class="nav-tab-wrapper autocep-nav-tabs">
                <?php
                $primeiro_grupo = true;

                foreach ($grupos as $tabs_do_grupo) {
                    if (!$primeiro_grupo) {
                        echo '<span class="autocep-tab-group-sep" aria-hidden="true"></span>';
                    }

                    $primeiro_grupo = false;

                    foreach ($tabs_do_grupo as $slug) {
                        if (!isset($abas[$slug])) {
                            continue;
                        }

                        printf(
                            '<a href="%s" class="nav-tab %s"><span class="%s" aria-hidden="true"></span> %s</a>',
                            esc_url(admin_url('admin.php?page=autocep&tab=' . $slug)),
                            $tab_atual === $slug ? 'nav-tab-active' : '',
                            esc_attr($abas[$slug]['icon']),
                            esc_html($abas[$slug]['label'])
                        );
                    }
                }
                ?>
            </div>

            <?php if ('diagnostics' === $tab_atual) : ?>
                <?php $this->render_diagnostics_tab(); ?>
            <?php elseif ('shipping_dashboard' === $tab_atual) : ?>
                <?php $this->render_shipping_dashboard_tab(); ?>
            <?php elseif ('support' === $tab_atual) : ?>
                <?php $this->render_support_tab(); ?>
            <?php elseif ('feedback' === $tab_atual) : ?>
                <?php $this->render_feedback_tab(); ?>
            <?php else : ?>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('autocep_options_group');
                    do_settings_sections('autocep_tab_' . $tab_atual);
                    submit_button();
                    ?>
                </form>
                <?php if ('distance_shipping' === $tab_atual) : ?>
                    <?php $this->render_distance_shipping_stores_ui(); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_diagnostics_tab()
    {
        $logs = AutoCEP_Logger::get_logs(50);

        ?>
        <div class="autocep-diagnostics">
            <p class="autocep-diagnostics-pointer">
                <span class="dashicons dashicons-info"></span>
                <?php
                printf(
                    wp_kses(
                        __('O teste de velocidade e status das APIs de CEP agora fica em <a href="%s">Frete e Busca de CEP</a>, junto da tabela de prioridade.', 'autocep'),
                        array('a' => array('href' => array()))
                    ),
                    esc_url(admin_url('admin.php?page=autocep&tab=general'))
                );
                ?>
            </p>

            <h2><?php esc_html_e('Log de Falhas e Timeouts', 'autocep'); ?></h2>
            <div class="autocep-diagnostics-actions">
                <button type="button" class="button" id="autocep-clear-logs-btn">
                    <?php esc_html_e('Limpar Logs', 'autocep'); ?>
                </button>
            </div>

            <table class="widefat autocep-logs-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Data', 'autocep'); ?></th>
                        <th><?php esc_html_e('API', 'autocep'); ?></th>
                        <th><?php esc_html_e('CEP', 'autocep'); ?></th>
                        <th><?php esc_html_e('Tipo', 'autocep'); ?></th>
                        <th><?php esc_html_e('Mensagem', 'autocep'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('Nenhum registro encontrado.', 'autocep'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($logs as $log) : ?>
                            <tr>
                                <td><?php echo esc_html($log->criado_em); ?></td>
                                <td><?php echo esc_html($log->api); ?></td>
                                <td><?php echo esc_html($log->cep); ?></td>
                                <td><?php echo esc_html($log->tipo); ?></td>
                                <td><?php echo esc_html($log->mensagem); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_distance_shipping_stores_ui()
    {
        if (!class_exists('AutoCEP_Store_Pickup')) {
            return;
        }

        $lojas = AutoCEP_Store_Pickup::get_stores();

        ?>
        <hr />
        <div class="autocep-diagnostics autocep-stores-manager">
            <h2><?php esc_html_e('Lojas Físicas para Retirada por Proximidade', 'autocep'); ?></h2>
            <p class="description" style="max-width:760px;">
                <?php esc_html_e('Cadastre aqui as lojas/depósitos que podem aparecer como opção de "Retirada em Loja" para o cliente. Depois de cadastradas, ative o módulo na seção acima e adicione o método "Retirada em Loja por Proximidade — AutoCEP" nas Zonas de Entrega desejadas (WooCommerce > Configurações > Entrega) — ele mostrará automaticamente as lojas dentro do raio (Km) configurado para o CEP de cada cliente, da mais próxima para a mais distante, além das lojas indicadas na lista de "CEPs Atendidos Manualmente" de cada uma.', 'autocep'); ?>
            </p>

            <table class="widefat autocep-logs-table" id="autocep-stores-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Nome', 'autocep'); ?></th>
                        <th><?php esc_html_e('CEP', 'autocep'); ?></th>
                        <th><?php esc_html_e('Raio (Km)', 'autocep'); ?></th>
                        <th><?php esc_html_e('Taxa (R$)', 'autocep'); ?></th>
                        <th><?php esc_html_e('CEPs Manuais', 'autocep'); ?></th>
                        <th><?php esc_html_e('Instruções', 'autocep'); ?></th>
                        <th><?php esc_html_e('Ações', 'autocep'); ?></th>
                    </tr>
                </thead>
                <tbody id="autocep-stores-tbody">
                    <?php if (empty($lojas)) : ?>
                        <tr class="autocep-stores-empty-row">
                            <td colspan="7"><?php esc_html_e('Nenhuma loja cadastrada ainda.', 'autocep'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($lojas as $loja) : ?>
                            <tr data-id="<?php echo esc_attr($loja['id']); ?>">
                                <td class="autocep-store-col-name"><?php echo esc_html($loja['name']); ?></td>
                                <td class="autocep-store-col-cep"><?php echo esc_html($loja['cep']); ?></td>
                                <td class="autocep-store-col-radius"><?php echo esc_html($loja['radius_km']); ?></td>
                                <td class="autocep-store-col-fee"><?php echo esc_html(number_format((float) $loja['pickup_fee'], 2, ',', '.')); ?></td>
                                <td class="autocep-store-col-manual-ceps"><?php echo esc_html(!empty($loja['manual_ceps']) ? wp_trim_words($loja['manual_ceps'], 6) : '—'); ?></td>
                                <td class="autocep-store-col-instructions"><?php echo esc_html(wp_trim_words($loja['instructions'], 8)); ?></td>
                                <td>
                                    <button type="button" class="button button-small autocep-store-edit-btn"><?php esc_html_e('Editar', 'autocep'); ?></button>
                                    <button type="button" class="button button-small autocep-store-delete-btn"><?php esc_html_e('Excluir', 'autocep'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <h3 id="autocep-store-form-title"><?php esc_html_e('Adicionar Nova Loja', 'autocep'); ?></h3>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="autocep-store-name"><?php esc_html_e('Nome da Loja', 'autocep'); ?></label></th>
                        <td><input type="text" id="autocep-store-name" class="regular-text" placeholder="<?php esc_attr_e('Ex.: Loja Centro', 'autocep'); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="autocep-store-cep"><?php esc_html_e('CEP da Loja', 'autocep'); ?></label></th>
                        <td><input type="text" id="autocep-store-cep" class="regular-text" maxlength="9" placeholder="00000-000" /></td>
                    </tr>
                    <tr>
                        <th><label for="autocep-store-radius"><?php esc_html_e('Raio Máximo para Retirada (Km)', 'autocep'); ?></label></th>
                        <td>
                            <input type="number" id="autocep-store-radius" step="0.1" min="0.1" value="15" class="small-text" />
                            <p class="description"><?php esc_html_e('Calculado automaticamente por distância rodoviária real (Google Maps). Funciona bem para a maioria das cidades, mas pode falhar em CEPs de cidades pequenas/rurais — use o campo "CEPs Atendidos Manualmente" abaixo como garantia nesses casos.', 'autocep'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="autocep-store-fee"><?php esc_html_e('Taxa de Retirada (R$)', 'autocep'); ?></label></th>
                        <td>
                            <input type="number" id="autocep-store-fee" step="0.01" min="0" value="0" class="small-text" />
                            <p class="description"><?php esc_html_e('Deixe 0,00 para retirada grátis.', 'autocep'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="autocep-store-manual-ceps"><?php esc_html_e('CEPs Atendidos Manualmente (opcional)', 'autocep'); ?></label></th>
                        <td>
                            <textarea id="autocep-store-manual-ceps" class="large-text" rows="3" placeholder="<?php esc_attr_e('Ex.: 58260, 58265, 58268000', 'autocep'); ?>"></textarea>
                            <p class="description"><?php esc_html_e('Liste CEPs completos ou só os primeiros dígitos (prefixo da cidade — ex.: "58260" cobre todos os CEPs que começam com 58260), um por linha ou separados por vírgula. Esses CEPs SEMPRE mostram esta loja como opção de retirada, mesmo que o cálculo automático por Km falhe — garante que cidades vizinhas nunca fiquem de fora por causa de uma falha de geocodificação do Google.', 'autocep'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="autocep-store-instructions"><?php esc_html_e('Endereço / Instruções de Retirada', 'autocep'); ?></label></th>
                        <td><textarea id="autocep-store-instructions" class="large-text" rows="3" placeholder="<?php esc_attr_e('Ex.: Rua Exemplo, 123 — Retirar no balcão 2 das 8h às 18h', 'autocep'); ?>"></textarea></td>
                    </tr>
                </tbody>
            </table>

            <input type="hidden" id="autocep-store-id" value="" />
            <p>
                <button type="button" class="button button-primary" id="autocep-store-save-btn"><?php esc_html_e('Salvar Loja', 'autocep'); ?></button>
                <button type="button" class="button" id="autocep-store-cancel-btn" style="display:none;"><?php esc_html_e('Cancelar Edição', 'autocep'); ?></button>
                <span id="autocep-store-feedback" style="margin-left:10px;"></span>
            </p>
        </div>
        <?php
    }

    /**
     * Rótulo legível de um campo do checkout (para a lista de erros).
     */
    private static function checkout_field_label($id)
    {
        $nomes = array(
            'billing_email'      => __('E-mail', 'autocep'),
            'billing_first_name' => __('Nome', 'autocep'),
            'billing_last_name'  => __('Sobrenome', 'autocep'),
            'billing_phone'      => __('Telefone', 'autocep'),
            'billing_cellphone'  => __('Celular', 'autocep'),
            'billing_cpf'        => __('CPF', 'autocep'),
            'billing_cnpj'       => __('CNPJ', 'autocep'),
            'billing_persontype' => __('Tipo de pessoa', 'autocep'),
            'billing_company'    => __('Empresa', 'autocep'),
            'billing_postcode'   => __('CEP', 'autocep'),
            'billing_address_1'  => __('Endereço', 'autocep'),
            'billing_number'     => __('Número', 'autocep'),
            'billing_address_2'  => __('Complemento', 'autocep'),
            'billing_neighborhood' => __('Bairro', 'autocep'),
            'billing_city'       => __('Cidade', 'autocep'),
            'billing_state'      => __('Estado', 'autocep'),
            'billing_country'    => __('País', 'autocep'),
            'shipping_method'    => __('Forma de entrega (não escolhida)', 'autocep'),
            'terms'              => __('Aceite dos termos', 'autocep'),
            'payment_method'     => __('Forma de pagamento', 'autocep'),
        );

        foreach (AutoCEP_Modern_Checkout::get_custom_fields() as $campo) {
            $nomes[$campo['key']] = $campo['label'];
        }

        if (isset($nomes[$id])) {
            return $nomes[$id];
        }

        // Endereço de entrega diferente: "shipping_city" → "Cidade (entrega)".
        $cobranca = preg_replace('/^shipping_/', 'billing_', $id);

        if ($cobranca !== $id && isset($nomes[$cobranca])) {
            return sprintf(__('%s (entrega)', 'autocep'), $nomes[$cobranca]);
        }

        return $id;
    }

    /**
     * Seção "Funil do Checkout" da aba Analytics.
     */
    private function render_checkout_funnel($dias)
    {
        if (!class_exists('AutoCEP_Checkout_Analytics')) {
            return;
        }

        $funil    = AutoCEP_Checkout_Analytics::get_funnel($dias);
        $receita  = AutoCEP_Checkout_Analytics::get_revenue($dias);
        $tempo    = AutoCEP_Checkout_Analytics::get_average_time($dias);
        $pagtos   = AutoCEP_Checkout_Analytics::get_by_payment($dias);
        $aparelho = AutoCEP_Checkout_Analytics::get_by_device($dias);
        $erros    = AutoCEP_Checkout_Analytics::get_top_errors($dias);
        $options  = self::get_options();
        $ativo    = !empty($options['modern_checkout']['enabled']);

        $pct = function ($parte, $todo) {
            return $todo > 0 ? round($parte / $todo * 100, 1) : null;
        };

        $formatar_pct = function ($valor) {
            return null === $valor ? '—' : number_format_i18n($valor, 1) . '%';
        };

        $entradas  = $funil['view'];
        $conversao = $pct($funil['order'], $entradas);

        $etapas = array(
            'view'   => __('Entraram no checkout', 'autocep'),
            'step2'  => __('Chegaram à Entrega', 'autocep'),
            'step3'  => __('Chegaram ao Pagamento', 'autocep'),
            'submit' => __('Clicaram em Finalizar', 'autocep'),
            'order'  => __('Concluíram o pedido', 'autocep'),
        );

        $tipos_pagto = array(
            'pix'    => __('Pix', 'autocep'),
            'card'   => __('Cartão', 'autocep'),
            'boleto' => __('Boleto', 'autocep'),
            'other'  => __('Outros', 'autocep'),
        );
        ?>
        <div class="autocep-funnel">
            <h3 class="autocep-funnel-section-title"><span class="dashicons dashicons-filter"></span> <?php esc_html_e('Funil do Checkout', 'autocep'); ?></h3>

            <?php if (!$ativo && 0 === $entradas) : ?>
                <div class="autocep-funnel-empty">
                    <?php
                    printf(
                        wp_kses(__('O funil mede o <strong>Checkout Moderno</strong>. <a href="%s">Ative-o na aba Checkout</a> para começar a ver a taxa de conversão e onde os clientes desistem.', 'autocep'), array('strong' => array(), 'a' => array('href' => array()))),
                        esc_url(admin_url('admin.php?page=autocep&tab=checkout_shipping'))
                    );
                    ?>
                </div>
            <?php elseif (0 === $entradas) : ?>
                <div class="autocep-funnel-empty"><?php esc_html_e('Ainda não há visitas ao checkout neste período. Os dados aparecem assim que clientes abrirem a página de finalizar compra.', 'autocep'); ?></div>
            <?php else : ?>

                <div class="autocep-kpi-grid">
                    <div class="autocep-kpi-card">
                        <div class="autocep-kpi-icon autocep-kpi-teal"><span class="dashicons dashicons-yes-alt"></span></div>
                        <div class="autocep-kpi-info">
                            <span class="autocep-kpi-label"><?php esc_html_e('Taxa de Conversão do Checkout', 'autocep'); ?></span>
                            <span class="autocep-kpi-value"><?php echo esc_html($formatar_pct($conversao)); ?></span>
                            <span class="autocep-kpi-sub"><?php echo esc_html(sprintf(__('%1$d pedidos de %2$d visitas', 'autocep'), $funil['order'], $entradas)); ?></span>
                        </div>
                    </div>
                    <div class="autocep-kpi-card">
                        <div class="autocep-kpi-icon autocep-kpi-orange"><span class="dashicons dashicons-dismiss"></span></div>
                        <div class="autocep-kpi-info">
                            <span class="autocep-kpi-label"><?php esc_html_e('Desistência no Checkout', 'autocep'); ?></span>
                            <span class="autocep-kpi-value"><?php echo esc_html(null === $conversao ? '—' : $formatar_pct(round(100 - $conversao, 1))); ?></span>
                            <span class="autocep-kpi-sub"><?php echo esc_html(sprintf(__('%d clientes saíram sem comprar', 'autocep'), max(0, $entradas - $funil['order']))); ?></span>
                        </div>
                    </div>
                    <div class="autocep-kpi-card">
                        <div class="autocep-kpi-icon autocep-kpi-green"><span class="dashicons dashicons-money-alt"></span></div>
                        <div class="autocep-kpi-info">
                            <span class="autocep-kpi-label"><?php esc_html_e('Receita pelo Checkout', 'autocep'); ?></span>
                            <span class="autocep-kpi-value"><?php echo wp_kses_post(wc_price($receita['receita'])); ?></span>
                            <span class="autocep-kpi-sub"><?php echo null === $receita['ticket'] ? esc_html__('Sem pedidos no período', 'autocep') : wp_kses_post(sprintf(__('Ticket médio %s', 'autocep'), wc_price($receita['ticket']))); ?></span>
                        </div>
                    </div>
                    <div class="autocep-kpi-card">
                        <div class="autocep-kpi-icon autocep-kpi-blue"><span class="dashicons dashicons-clock"></span></div>
                        <div class="autocep-kpi-info">
                            <span class="autocep-kpi-label"><?php esc_html_e('Tempo Médio até o Pedido', 'autocep'); ?></span>
                            <span class="autocep-kpi-value"><?php echo null === $tempo ? '—' : esc_html($tempo < 60 ? sprintf(__('%d s', 'autocep'), $tempo) : sprintf(__('%1$d min %2$02d s', 'autocep'), floor($tempo / 60), $tempo % 60)); ?></span>
                            <span class="autocep-kpi-sub"><?php esc_html_e('da entrada no checkout ao pedido', 'autocep'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="autocep-funnel-grid">
                    <div class="autocep-chart-card">
                        <h3><span class="dashicons dashicons-filter"></span> <?php esc_html_e('Onde os clientes desistem', 'autocep'); ?></h3>
                        <div class="autocep-funnel-steps">
                            <?php
                            $anterior = null;

                            foreach ($etapas as $chave => $rotulo) :
                                $total   = $funil[$chave];
                                $largura = $entradas ? max(2, round($total / $entradas * 100)) : 0;
                                $perda   = null !== $anterior ? max(0, $anterior - $total) : 0;
                                ?>
                                <div class="autocep-funnel-step">
                                    <div class="autocep-funnel-step-head">
                                        <span class="autocep-funnel-step-label"><?php echo esc_html($rotulo); ?></span>
                                        <span class="autocep-funnel-step-value"><strong><?php echo esc_html(number_format_i18n($total)); ?></strong> · <?php echo esc_html($formatar_pct($pct($total, $entradas))); ?></span>
                                    </div>
                                    <div class="autocep-funnel-bar"><span style="width: <?php echo esc_attr($largura); ?>%;"></span></div>
                                    <?php if (null !== $anterior && $perda > 0) : ?>
                                        <span class="autocep-funnel-drop"><?php echo esc_html(sprintf(__('▼ %1$d desistiram nesta etapa (%2$s)', 'autocep'), $perda, $formatar_pct($pct($perda, $anterior)))); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $anterior = $total;
                            endforeach;
                            ?>
                        </div>
                    </div>

                    <div class="autocep-funnel-side">
                        <div class="autocep-chart-card">
                            <h3><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Conversão por Forma de Pagamento', 'autocep'); ?></h3>
                            <?php if (empty($pagtos)) : ?>
                                <p class="autocep-funnel-muted"><?php esc_html_e('Nenhum clique em Finalizar ainda.', 'autocep'); ?></p>
                            <?php else : ?>
                                <table class="autocep-funnel-table">
                                    <thead><tr><th><?php esc_html_e('Pagamento', 'autocep'); ?></th><th><?php esc_html_e('Finalizar', 'autocep'); ?></th><th><?php esc_html_e('Pedidos', 'autocep'); ?></th><th><?php esc_html_e('Conversão', 'autocep'); ?></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($pagtos as $tipo => $dados) : ?>
                                            <tr>
                                                <td><?php echo esc_html(isset($tipos_pagto[$tipo]) ? $tipos_pagto[$tipo] : $tipo); ?></td>
                                                <td><?php echo esc_html(number_format_i18n($dados['submit'])); ?></td>
                                                <td><?php echo esc_html(number_format_i18n($dados['order'])); ?></td>
                                                <td><strong><?php echo esc_html($formatar_pct($pct($dados['order'], $dados['submit']))); ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p class="autocep-funnel-muted"><?php esc_html_e('Quem clicou em Finalizar com cada forma e quantos pedidos foram criados. Conversão baixa costuma indicar pagamento recusado ou erro no gateway.', 'autocep'); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="autocep-chart-card">
                            <h3><span class="dashicons dashicons-smartphone"></span> <?php esc_html_e('Celular x Computador', 'autocep'); ?></h3>
                            <table class="autocep-funnel-table">
                                <thead><tr><th><?php esc_html_e('Aparelho', 'autocep'); ?></th><th><?php esc_html_e('Visitas', 'autocep'); ?></th><th><?php esc_html_e('Pedidos', 'autocep'); ?></th><th><?php esc_html_e('Conversão', 'autocep'); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach (array('mobile' => __('Celular', 'autocep'), 'desktop' => __('Computador', 'autocep')) as $chave => $rotulo) : ?>
                                        <tr>
                                            <td><?php echo esc_html($rotulo); ?></td>
                                            <td><?php echo esc_html(number_format_i18n($aparelho[$chave]['view'])); ?></td>
                                            <td><?php echo esc_html(number_format_i18n($aparelho[$chave]['order'])); ?></td>
                                            <td><strong><?php echo esc_html($formatar_pct($pct($aparelho[$chave]['order'], $aparelho[$chave]['view']))); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="autocep-chart-card">
                            <h3><span class="dashicons dashicons-warning"></span> <?php esc_html_e('Campos com Mais Erros', 'autocep'); ?></h3>
                            <?php if (empty($erros)) : ?>
                                <p class="autocep-funnel-muted"><?php esc_html_e('Nenhum erro de preenchimento no período. 🎉', 'autocep'); ?></p>
                            <?php else : ?>
                                <table class="autocep-funnel-table">
                                    <thead><tr><th><?php esc_html_e('Campo', 'autocep'); ?></th><th><?php esc_html_e('Clientes', 'autocep'); ?></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($erros as $erro) : ?>
                                            <tr><td><?php echo esc_html(self::checkout_field_label($erro->campo)); ?></td><td><?php echo esc_html(number_format_i18n((int) $erro->total)); ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p class="autocep-funnel-muted"><?php esc_html_e('Clientes que tentaram avançar com o campo vazio ou inválido. Campos no topo da lista são candidatos a ficar opcionais ou com instrução mais clara (aba Checkout > Campos do Checkout).', 'autocep'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <style>
            .autocep-funnel-section-title { display: flex; align-items: center; gap: 6px; margin: 24px 0 12px; font-size: 15px; color: #1e293b; }
            .autocep-funnel-section-title .dashicons { color: #3b82f6; }
            .autocep-funnel-empty { padding: 18px 20px; border: 1px dashed #93c5fd; border-radius: 10px; background: #eff6ff; color: #1e3a8a; }
            .autocep-funnel-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 16px; margin-top: 16px; }
            .autocep-funnel-side { display: flex; flex-direction: column; gap: 16px; }
            .autocep-funnel-steps { display: flex; flex-direction: column; gap: 14px; }
            .autocep-funnel-step-head { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 4px; font-size: 13px; }
            .autocep-funnel-step-label { color: #334155; font-weight: 600; }
            .autocep-funnel-step-value { color: #475569; white-space: nowrap; }
            .autocep-funnel-bar { height: 14px; border-radius: 7px; background: #f1f5f9; overflow: hidden; }
            .autocep-funnel-bar span { display: block; height: 100%; border-radius: 7px; background: linear-gradient(90deg, #1d4ed8, #3b82f6); }
            .autocep-funnel-drop { display: block; margin-top: 4px; font-size: 12px; color: #c2410c; }
            .autocep-funnel-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .autocep-funnel-table th { padding: 6px 4px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600; text-align: left; }
            .autocep-funnel-table td { padding: 7px 4px; border-bottom: 1px solid #f1f5f9; }
            .autocep-funnel-table th:not(:first-child), .autocep-funnel-table td:not(:first-child) { text-align: right; }
            .autocep-funnel-muted { margin: 8px 0 0; font-size: 12px; color: #64748b; }
            @media (max-width: 960px) { .autocep-funnel-grid { grid-template-columns: minmax(0, 1fr); } }
        </style>
        <?php
    }

    private function render_shipping_dashboard_tab()
    {
        if (!class_exists('AutoCEP_Stats')) {
            return;
        }

        $periodos_disponiveis = array(7 => '7 dias', 30 => '30 dias', 90 => '90 dias');
        $dias = (isset($_GET['periodo']) && isset($periodos_disponiveis[(int) $_GET['periodo']])) ? (int) $_GET['periodo'] : 30;
        $options = self::get_options();

        if (empty($options['general']['stats_enabled'])) {
            echo '<div class="autocep-analytics-empty"><span class="dashicons dashicons-chart-bar"></span><p>';
            printf(
                wp_kses(__('A coleta de dados está desativada. <a href="%s">Ative em Frete e Busca de CEP</a> para ver os analytics.', 'autocep'), array('a' => array('href' => array()))),
                esc_url(admin_url('admin.php?page=autocep&tab=general'))
            );
            echo '</p></div>';
            return;
        }

        $top_ceps    = AutoCEP_Stats::get_top_ceps($dias, 10);
        $top_regioes = AutoCEP_Stats::get_top_regions($dias, 10);
        $custos      = AutoCEP_Stats::get_average_costs($dias);
        $desistencia = AutoCEP_Stats::get_abandonment_rate($dias);
        $top_methods = AutoCEP_Stats::get_top_shipping_methods($dias, 8);

        $flags_json_script = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        $ceps_labels  = wp_json_encode(array_map(function($r) {
            return strlen($r->cep) === 8 ? substr($r->cep,0,5).'-'.substr($r->cep,5) : $r->cep;
        }, $top_ceps ?: array()), $flags_json_script);
        $ceps_data    = wp_json_encode(array_map(function($r){ return (int)$r->total; }, $top_ceps ?: array()), $flags_json_script);
        $uf_labels    = wp_json_encode(array_map(function($r){ return $r->uf ?: 'N/D'; }, $top_regioes ?: array()), $flags_json_script);
        $uf_data      = wp_json_encode(array_map(function($r){ return (int)$r->total; }, $top_regioes ?: array()), $flags_json_script);
        $meth_labels  = wp_json_encode(array_map(function($r){ return $r->metodo; }, $top_methods ?: array()), $flags_json_script);
        $meth_data    = wp_json_encode(array_map(function($r){ return (int)$r->total; }, $top_methods ?: array()), $flags_json_script);

        $taxa_val     = $desistencia ? $desistencia['taxa'] : null;
        $conv_val     = $desistencia ? round(100 - $desistencia['taxa'], 1) : null;
        ?>
        <div class="autocep-analytics-wrap">

            <!-- Período -->
            <div class="autocep-analytics-header">
                <div class="autocep-analytics-title">
                    <span class="dashicons dashicons-chart-area"></span>
                    <h2><?php esc_html_e('Analytics', 'autocep'); ?></h2>
                    <span class="autocep-analytics-badge"><?php echo esc_html(sprintf(__('Últimos %d dias', 'autocep'), $dias)); ?></span>
                </div>
                <div class="autocep-analytics-period">
                    <?php foreach ($periodos_disponiveis as $v => $r) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=autocep&tab=shipping_dashboard&periodo='.$v)); ?>"
                           class="autocep-period-btn <?php echo ($dias === $v) ? 'active' : ''; ?>">
                            <?php echo esc_html($r); ?>
                        </a>
                    <?php endforeach; ?>
                    <button type="button" class="autocep-period-btn autocep-danger-btn" id="autocep-clear-stats-btn">
                        <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Limpar', 'autocep'); ?>
                    </button>
                    <span id="autocep-clear-stats-feedback"></span>
                </div>
            </div>

            <?php $this->render_checkout_funnel($dias); ?>

            <h3 class="autocep-funnel-section-title"><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e('Frete e CEP', 'autocep'); ?></h3>

            <!-- KPI Cards -->
            <div class="autocep-kpi-grid">
                <div class="autocep-kpi-card">
                    <div class="autocep-kpi-icon autocep-kpi-blue"><span class="dashicons dashicons-cart"></span></div>
                    <div class="autocep-kpi-info">
                        <span class="autocep-kpi-label"><?php esc_html_e('Frete Médio Calculado', 'autocep'); ?></span>
                        <span class="autocep-kpi-value"><?php echo (null !== $custos['calculado_media']) ? wp_kses_post(wc_price($custos['calculado_media'])) : '<span class="autocep-empty-val">—</span>'; ?></span>
                        <span class="autocep-kpi-sub"><?php echo esc_html(sprintf(_n('%d simulação', '%d simulações', $custos['calculado_total'], 'autocep'), $custos['calculado_total'])); ?></span>
                    </div>
                </div>
                <div class="autocep-kpi-card">
                    <div class="autocep-kpi-icon autocep-kpi-green"><span class="dashicons dashicons-money-alt"></span></div>
                    <div class="autocep-kpi-info">
                        <span class="autocep-kpi-label"><?php esc_html_e('Frete Médio Pago', 'autocep'); ?></span>
                        <span class="autocep-kpi-value"><?php echo (null !== $custos['pago_media']) ? wp_kses_post(wc_price($custos['pago_media'])) : '<span class="autocep-empty-val">—</span>'; ?></span>
                        <span class="autocep-kpi-sub"><?php echo esc_html(sprintf(_n('%d pedido', '%d pedidos', $custos['pago_total'], 'autocep'), $custos['pago_total'])); ?></span>
                    </div>
                </div>
                <div class="autocep-kpi-card">
                    <div class="autocep-kpi-icon autocep-kpi-orange"><span class="dashicons dashicons-warning"></span></div>
                    <div class="autocep-kpi-info">
                        <span class="autocep-kpi-label"><?php esc_html_e('Taxa de Desistência', 'autocep'); ?></span>
                        <span class="autocep-kpi-value"><?php echo $taxa_val !== null ? esc_html($taxa_val . '%') : '<span class="autocep-empty-val">—</span>'; ?></span>
                        <span class="autocep-kpi-sub"><?php esc_html_e('estimativa por frete', 'autocep'); ?></span>
                    </div>
                </div>
                <div class="autocep-kpi-card">
                    <div class="autocep-kpi-icon autocep-kpi-teal"><span class="dashicons dashicons-yes-alt"></span></div>
                    <div class="autocep-kpi-info">
                        <span class="autocep-kpi-label"><?php esc_html_e('Taxa de Conversão', 'autocep'); ?></span>
                        <span class="autocep-kpi-value"><?php echo $conv_val !== null ? esc_html($conv_val . '%') : '<span class="autocep-empty-val">—</span>'; ?></span>
                        <span class="autocep-kpi-sub"><?php esc_html_e('simulações → pedidos', 'autocep'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="autocep-charts-grid">
                <div class="autocep-chart-card">
                    <div class="autocep-chart-header">
                        <h3><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e('CEPs Mais Pesquisados', 'autocep'); ?></h3>
                    </div>
                    <div class="autocep-chart-body">
                        <?php if (empty($top_ceps)) : ?>
                            <div class="autocep-chart-empty"><span class="dashicons dashicons-chart-bar"></span><p><?php esc_html_e('Sem dados no período', 'autocep'); ?></p></div>
                        <?php else : ?>
                            <canvas id="autocep-chart-ceps" height="280"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="autocep-chart-card">
                    <div class="autocep-chart-header">
                        <h3><span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e('Regiões Mais Pesquisadas', 'autocep'); ?></h3>
                    </div>
                    <div class="autocep-chart-body">
                        <?php if (empty($top_regioes)) : ?>
                            <div class="autocep-chart-empty"><span class="dashicons dashicons-chart-bar"></span><p><?php esc_html_e('Sem dados no período', 'autocep'); ?></p></div>
                        <?php else : ?>
                            <canvas id="autocep-chart-regioes" height="280"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tabelas detalhadas -->
            <div class="autocep-tables-grid">
                <!-- Métodos de frete mais escolhidos -->
                <div class="autocep-table-card" style="grid-column: 1 / -1;">
                    <div class="autocep-table-header" style="display:flex;align-items:center;justify-content:space-between;">
                        <h3><span style="font-size:16px;">🚚</span> <?php esc_html_e('Métodos de Frete Mais Escolhidos (Todos os Pedidos)', 'autocep'); ?></h3>
                        <?php if (!empty($top_methods)) : ?>
                            <span style="font-size:12px;color:#6b7280;"><?php echo esc_html(sprintf(__('Últimos %d dias', 'autocep'), $dias)); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($top_methods)) : ?>
                        <div class="autocep-chart-empty" style="padding:30px;">
                            <span class="dashicons dashicons-chart-bar" style="font-size:32px;color:#d1d5db;"></span>
                            <p><?php esc_html_e('Nenhum pedido registrado no período.', 'autocep'); ?></p>
                        </div>
                    <?php else : ?>
                        <div style="padding:16px 18px;">
                            <canvas id="autocep-chart-methods" height="100"></canvas>
                        </div>
                        <table class="autocep-modern-table">
                            <thead><tr>
                                <th>
                                <th><?php esc_html_e('Método de Frete', 'autocep'); ?></th>
                                <th><?php esc_html_e('Pedidos', 'autocep'); ?></th>
                                <th><?php esc_html_e('Participação', 'autocep'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php
                            $total_pedidos = array_sum(array_column($top_methods, 'total'));
                            foreach ($top_methods as $i => $m) :
                                $pct = $total_pedidos > 0 ? round(($m->total / $total_pedidos) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td><span class="autocep-rank <?php echo $i < 3 ? 'autocep-rank-top' : ''; ?>"><?php echo $i+1; ?></span></td>
                                    <td><strong><?php echo esc_html($m->metodo ?: __('(sem nome)', 'autocep')); ?></strong></td>
                                    <td><span class="autocep-badge-count"><?php echo esc_html($m->total); ?></span></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <div style="flex:1;background:#f3f4f6;border-radius:999px;height:6px;overflow:hidden;">
                                                <div style="width:<?php echo esc_attr($pct); ?>%;background:#3b82f6;height:100%;border-radius:999px;"></div>
                                            </div>
                                            <span style="font-size:12px;color:#6b7280;min-width:36px;"><?php echo esc_html($pct . '%'); ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                <?php if (!empty($top_ceps)) : ?>
                <div class="autocep-table-card">
                    <div class="autocep-table-header">
                        <h3><?php esc_html_e('Ranking de CEPs', 'autocep'); ?></h3>
                    </div>
                    <table class="autocep-modern-table">
                        <thead><tr><th>
                        <tbody>
                        <?php foreach ($top_ceps as $i => $l) :
                            $cep_f = strlen($l->cep) === 8 ? substr($l->cep,0,5).'-'.substr($l->cep,5) : $l->cep; ?>
                            <tr>
                                <td><span class="autocep-rank <?php echo $i < 3 ? 'autocep-rank-top' : ''; ?>"><?php echo $i+1; ?></span></td>
                                <td><strong><?php echo esc_html($cep_f); ?></strong></td>
                                <td><?php echo esc_html(trim($l->cidade.'/'.$l->uf, '/ ') ?: '—'); ?></td>
                                <td><span class="autocep-badge-count"><?php echo esc_html($l->total); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (!empty($top_regioes)) : ?>
                <div class="autocep-table-card">
                    <div class="autocep-table-header">
                        <h3><?php esc_html_e('Ranking de Estados', 'autocep'); ?></h3>
                    </div>
                    <table class="autocep-modern-table">
                        <thead><tr><th>
                        <tbody>
                        <?php foreach ($top_regioes as $i => $l) : ?>
                            <tr>
                                <td><span class="autocep-rank <?php echo $i < 3 ? 'autocep-rank-top' : ''; ?>"><?php echo $i+1; ?></span></td>
                                <td><strong><?php echo esc_html($l->uf ?: '—'); ?></strong></td>
                                <td><span class="autocep-badge-count"><?php echo esc_html($l->total); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($desistencia) : ?>
            <div class="autocep-info-note">
                <span class="dashicons dashicons-info"></span>
                <?php esc_html_e('A taxa de desistência considera sessões que simularam frete mas não concluíram pedido nas 48h seguintes. Use como indicativo — outros fatores também influenciam.', 'autocep'); ?>
            </div>
            <?php endif; ?>
        </div>

        <script>
        (function(){
            var cepsLabels  = <?php echo $ceps_labels; ?>;
            var cepsData    = <?php echo $ceps_data; ?>;
            var ufLabels    = <?php echo $uf_labels; ?>;
            var ufData      = <?php echo $uf_data; ?>;
            var methLabels  = <?php echo $meth_labels; ?>;
            var methData    = <?php echo $meth_data; ?>;
            var colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#84cc16','#f97316','#ec4899','#6366f1'];

            function buildChart(id, labels, data, type) {
                var canvas = document.getElementById(id);
                if (!canvas || !labels.length) return;
                new Chart(canvas.getContext('2d'), {
                    type: type,
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: type === 'doughnut' ? colors : (type === 'bar' ? colors[0] : colors),
                            borderColor: type === 'bar' ? colors[0] : colors,
                            borderWidth: type === 'bar' ? 0 : 2,
                            borderRadius: type === 'bar' ? 6 : 0,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: type === 'doughnut', position: 'right' } },
                        scales: type === 'bar' ? {
                            x: { grid: { display: false } },
                            y: { grid: { color: '#f3f4f6' }, ticks: { stepSize: 1 } }
                        } : {}
                    }
                });
            }

            function initCharts() {
                buildChart('autocep-chart-ceps', cepsLabels, cepsData, 'bar');
                buildChart('autocep-chart-regioes', ufLabels, ufData, 'doughnut');
                if (methLabels.length) {
                    buildChart('autocep-chart-methods', methLabels, methData, 'horizontalBar');
                    var canvas = document.getElementById('autocep-chart-methods');
                    if (canvas && canvas._chartjs) { canvas._chartjs.destroy(); }
                    if (canvas) {
                        new Chart(canvas.getContext('2d'), {
                            type: 'bar',
                            data: {
                                labels: methLabels,
                                datasets: [{ data: methData, backgroundColor: colors, borderRadius: 6 }]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    x: { grid: { color: '#f3f4f6' }, ticks: { stepSize: 1 } },
                                    y: { grid: { display: false } }
                                }
                            }
                        });
                    }
                }
            }

            function aguardarChartJs(tentativasRestantes) {
                if (typeof Chart !== 'undefined') {
                    initCharts();
                    return;
                }

                if (tentativasRestantes <= 0) {
                    return;
                }

                setTimeout(function () { aguardarChartJs(tentativasRestantes - 1); }, 100);
            }

            aguardarChartJs(50);
        })();
        </script>
        <?php
    }

    private function render_my_tickets_list()
    {
        if (!class_exists('AutoCEP_Tickets')) {
            echo '<p>' . esc_html__('Módulo de chamados não disponível.', 'autocep') . '</p>';

            return;
        }

        $tickets = AutoCEP_Tickets::get_tickets('', 30);
        $abertos = AutoCEP_Tickets::count_by_status('aberto');

        $rotulos_tipo = array(
            'bug'             => __('🐛 Bug / Erro', 'autocep'),
            'config'          => __('⚙️ Configuração', 'autocep'),
            'performance'     => __('⚡ Performance', 'autocep'),
            'compatibilidade' => __('🔗 Compatibilidade', 'autocep'),
            'feature'         => __('✨ Funcionalidade', 'autocep'),
            'outro'           => __('📋 Outro', 'autocep'),
        );

        if ($abertos > 0) {
            printf(
                '<p style="margin:0 0 10px;font-size:13px;"><strong>%s</strong></p>',
                esc_html(
                    sprintf(
                        _n('%d chamado em aberto', '%d chamados em aberto', $abertos, 'autocep'),
                        $abertos
                    )
                )
            );
        }

        if (empty($tickets)) {
            echo '<p style="color:#6b7280;font-size:13px;">' . esc_html__('Nenhum chamado registrado ainda.', 'autocep') . '</p>';

            return;
        }
        ?>
        <div id="autocep-my-tickets-list" class="autocep-tickets-list">
            <?php foreach ($tickets as $ticket) : ?>
                <?php
                $fechado     = 'fechado' === $ticket->status;
                $rotulo_tipo = isset($rotulos_tipo[$ticket->tipo]) ? $rotulos_tipo[$ticket->tipo] : $ticket->tipo;
                ?>
                <div class="autocep-ticket-row" data-ticket-id="<?php echo esc_attr($ticket->id); ?>">
                    <div class="autocep-ticket-row-main">
                        <span class="autocep-ticket-numero"><?php echo esc_html($ticket->numero); ?></span>
                        <span class="autocep-ticket-titulo"><?php echo esc_html($ticket->titulo); ?></span>
                        <span class="autocep-ticket-tipo"><?php echo esc_html($rotulo_tipo); ?></span>
                    </div>
                    <div class="autocep-ticket-row-meta">
                        <span class="autocep-ticket-data">
                            <?php echo esc_html(mysql2date(get_option('date_format'), $ticket->criado_em)); ?>
                        </span>
                        <span class="autocep-ticket-status autocep-ticket-status--<?php echo esc_attr($ticket->status); ?>">
                            <?php echo $fechado ? esc_html__('Fechado', 'autocep') : esc_html__('Aberto', 'autocep'); ?>
                        </span>
                        <button
                            type="button"
                            class="autocep-ticket-toggle-btn"
                            data-id="<?php echo esc_attr($ticket->id); ?>"
                            data-novo-status="<?php echo esc_attr($fechado ? 'aberto' : 'fechado'); ?>"
                        >
                            <?php echo $fechado ? esc_html__('Reabrir', 'autocep') : esc_html__('Marcar como resolvido', 'autocep'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_support_tab()
    {
        $nonce = wp_create_nonce(AUTOCEP_ADMIN_NONCE_ACTION);

        $chamado_aberto = class_exists('AutoCEP_Tickets') ? AutoCEP_Tickets::get_tickets('aberto', 1) : array();
        $numero_aberto  = !empty($chamado_aberto[0]->numero) ? $chamado_aberto[0]->numero : '';

        $mensagem_padrao = sprintf(
            __("Olá, preciso de suporte sobre o plugin AutoCEP.\n\nSite: %1\$s\nVersão do AutoCEP: %2\$s%3\$s\n\nDescreva aqui o problema ou dúvida:\n", 'autocep'),
            get_site_url(),
            AUTOCEP_VERSION,
            $numero_aberto ? "\n" . sprintf(__('Chamado em aberto: %s', 'autocep'), $numero_aberto) : ''
        );
        $assunto_email = $numero_aberto
            ? sprintf('%s — %s', __('Suporte AutoCEP', 'autocep'), $numero_aberto)
            : __('Suporte AutoCEP', 'autocep');
        $link_email    = 'mailto:teswinfor@gmail.com?subject=' . rawurlencode($assunto_email) . '&body=' . rawurlencode($mensagem_padrao);
        $link_whatsapp = 'https://wa.me/5583994075812?text=' . rawurlencode($mensagem_padrao);
        ?>
        <div class="autocep-support-wrap">

            <!-- Contato Rápido -->
            <div class="autocep-support-header">
                <div class="autocep-support-brand">
                    <span class="autocep-icon-headset" style="font-size:32px;color:#fff;"></span>
                    <div>
                        <h2><?php esc_html_e('Suporte AutoCEP', 'autocep'); ?></h2>
                        <p><?php esc_html_e('Atendimento exclusivo TESW — Tecnologia e Soluções Web', 'autocep'); ?></p>
                    </div>
                </div>
                <div class="autocep-support-contacts">
                    <a href="<?php echo esc_attr($link_email); ?>" class="autocep-contact-btn autocep-contact-email">
                        <span class="dashicons dashicons-email-alt"></span>
                        teswinfor@gmail.com
                    </a>
                    <a href="<?php echo esc_attr($link_whatsapp); ?>" target="_blank" rel="noopener noreferrer" class="autocep-contact-btn autocep-contact-whatsapp">
                        <span class="dashicons dashicons-phone"></span>
                        (83) 99407-5812
                    </a>
                    <a href="https://tesw.com.br/documentacao-autocep" target="_blank" rel="noopener noreferrer" class="autocep-contact-btn autocep-contact-resource">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e('Documentação Oficial', 'autocep'); ?>
                    </a>
                    <a href="https://youtu.be/EkEC8CJ_H5c" target="_blank" rel="noopener noreferrer" class="autocep-contact-btn autocep-contact-resource">
                        <span class="dashicons dashicons-video-alt3"></span>
                        <?php esc_html_e('Vídeo de Demonstração', 'autocep'); ?>
                    </a>
                </div>
                <p style="width:100%;margin:6px 0 0;font-size:11px;color:#9ca3af;">
                    <?php if ($numero_aberto) : ?>
                        <?php
                        printf(
                            esc_html__('Os links acima já abrem com uma mensagem padrão preenchida, identificando este site, a versão do AutoCEP e o chamado %s em aberto — é só completar com o problema.', 'autocep'),
                            esc_html($numero_aberto)
                        );
                        ?>
                    <?php else : ?>
                        <?php esc_html_e('Os links acima já abrem com uma mensagem padrão preenchida, identificando este site e a versão do AutoCEP. O número de um chamado só é incluído automaticamente quando existe um chamado em aberto no momento — sem chamado aberto, a mensagem sai só com a identificação do site.', 'autocep'); ?>
                    <?php endif; ?>
                </p>
            </div>

            <div class="autocep-support-grid">

                <!-- Formulário de Chamado -->
                <div class="autocep-support-card">
                    <div class="autocep-support-card-header">
                        <span class="dashicons dashicons-tickets-alt"></span>
                        <h3><?php esc_html_e('Abrir Novo Chamado', 'autocep'); ?></h3>
                    </div>
                    <div class="autocep-support-card-body">
                        <div class="autocep-field-group">
                            <label><?php esc_html_e('Tipo do Problema', 'autocep'); ?></label>
                            <select id="autocep-ticket-tipo">
                                <option value="bug"><?php esc_html_e('🐛 Bug / Erro', 'autocep'); ?></option>
                                <option value="config"><?php esc_html_e('⚙️ Dúvida de Configuração', 'autocep'); ?></option>
                                <option value="performance"><?php esc_html_e('⚡ Problema de Performance', 'autocep'); ?></option>
                                <option value="compatibilidade"><?php esc_html_e('🔗 Compatibilidade com Plugin', 'autocep'); ?></option>
                                <option value="feature"><?php esc_html_e('✨ Solicitação de Funcionalidade', 'autocep'); ?></option>
                                <option value="outro"><?php esc_html_e('📋 Outro', 'autocep'); ?></option>
                            </select>
                        </div>
                        <div class="autocep-field-group">
                            <label><?php esc_html_e('Título do Chamado', 'autocep'); ?></label>
                            <input type="text" id="autocep-ticket-titulo" placeholder="<?php esc_attr_e('Descreva resumidamente o problema...', 'autocep'); ?>" />
                        </div>
                        <div class="autocep-field-group">
                            <label><?php esc_html_e('Descrição Detalhada', 'autocep'); ?></label>
                            <textarea id="autocep-ticket-descricao" rows="5" placeholder="<?php esc_attr_e('Descreva o problema com detalhes: o que acontece, quando acontece, passos para reproduzir...', 'autocep'); ?>"></textarea>
                        </div>
                        <div class="autocep-field-group">
                            <label><?php esc_html_e('Anexar Arquivos / Prints (opcional — até 5)', 'autocep'); ?></label>
                            <div class="autocep-file-drop" id="autocep-file-drop">
                                <span class="dashicons dashicons-upload"></span>
                                <p><?php esc_html_e('Clique ou arraste arquivos aqui', 'autocep'); ?></p>
                                <span class="autocep-file-hint"><?php esc_html_e('PNG, JPG, GIF ou PDF — máx. 5 MB cada — até 5 arquivos', 'autocep'); ?></span>
                                <input type="file" id="autocep-ticket-arquivo" accept="image/*,.pdf" multiple
                                    style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;" />
                            </div>
                            <div id="autocep-file-preview-grid" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;"></div>
                        </div>
                        <!-- E-mail do admin usado automaticamente - campo oculto -->
                        <input type="hidden" id="autocep-ticket-email" value="<?php echo esc_attr(get_option('admin_email')); ?>" />
                        <div id="autocep-ticket-feedback" class="autocep-ticket-feedback" style="display:none;"></div>
                        <button type="button" class="autocep-btn-primary" id="autocep-ticket-submit">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php esc_html_e('Enviar Chamado', 'autocep'); ?>
                        </button>
                    </div>
                </div>

                <!-- Chamados anteriores e info -->
                <div style="display:flex;flex-direction:column;gap:16px;">

                    <!-- Meus Chamados -->
                    <div class="autocep-support-card">
                        <div class="autocep-support-card-header">
                            <span class="dashicons dashicons-list-view"></span>
                            <h3><?php esc_html_e('Meus Chamados', 'autocep'); ?></h3>
                        </div>
                        <div class="autocep-support-card-body">
                            <?php $this->render_my_tickets_list(); ?>
                        </div>
                    </div>

                    <!-- Informações do sistema para o suporte -->
                    <div class="autocep-support-card">
                        <div class="autocep-support-card-header">
                            <span class="dashicons dashicons-info"></span>
                            <h3><?php esc_html_e('Informações do Sistema', 'autocep'); ?></h3>
                        </div>
                        <div class="autocep-support-card-body">
                            <table class="autocep-sysinfo-table">
                                <?php
                                $infos = array(
                                    __('Versão AutoCEP', 'autocep')   => AUTOCEP_VERSION,
                                    __('Versão WordPress', 'autocep') => get_bloginfo('version'),
                                    __('Versão WooCommerce', 'autocep') => defined('WC_VERSION') ? WC_VERSION : '—',
                                    __('Versão PHP', 'autocep')        => PHP_VERSION,
                                    __('Site URL', 'autocep')          => get_site_url(),
                                );
                                foreach ($infos as $k => $v) :
                                ?>
                                <tr>
                                    <td><?php echo esc_html($k); ?></td>
                                    <td><code><?php echo esc_html($v); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                            <p style="font-size:11px;color:#6b7280;margin-top:8px;">
                                <?php esc_html_e('Essas informações são enviadas automaticamente junto com o chamado para agilizar o suporte.', 'autocep'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- FAQ Rápido -->
                    <div class="autocep-support-card">
                        <div class="autocep-support-card-header">
                            <span class="dashicons dashicons-editor-help"></span>
                            <h3><?php esc_html_e('Antes de Abrir um Chamado', 'autocep'); ?></h3>
                        </div>
                        <div class="autocep-support-card-body">
                            <div class="autocep-checklist">
                                <div class="autocep-checklist-item">
                                    <span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span>
                                    <span><?php esc_html_e('Limpou o cache do WP Rocket / plugin de cache?', 'autocep'); ?></span>
                                </div>
                                <div class="autocep-checklist-item">
                                    <span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span>
                                    <span><?php esc_html_e('Verificou os Logs em Diagnóstico e Logs?', 'autocep'); ?></span>
                                </div>
                                <div class="autocep-checklist-item">
                                    <span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span>
                                    <span><?php esc_html_e('Usou o Testador de Distância para CEPs próximos?', 'autocep'); ?></span>
                                </div>
                                <div class="autocep-checklist-item">
                                    <span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span>
                                    <span><?php esc_html_e('A chave de API do Google Maps tem a Distance Matrix API ativa?', 'autocep'); ?></span>
                                </div>
                                <div class="autocep-checklist-item">
                                    <span class="dashicons dashicons-yes-alt" style="color:#10b981;"></span>
                                    <span><?php esc_html_e('Verificou se o método de Retirada está na Zona de Entrega correta?', 'autocep'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            $('#autocep-ticket-submit').on('click', function(){
                var $btn = $(this);
                var tipo      = $('#autocep-ticket-tipo').val();
                var titulo    = $('#autocep-ticket-titulo').val().trim();
                var descricao = $('#autocep-ticket-descricao').val().trim();
                var $fb       = $('#autocep-ticket-feedback');

                if (!titulo || !descricao) {
                    $fb.removeClass('autocep-fb-ok').addClass('autocep-fb-erro')
                       .text('<?php echo esc_js(__('Preencha o título e a descrição do chamado.', 'autocep')); ?>').show();
                    return;
                }

                $btn.prop('disabled', true).text('<?php echo esc_js(__('Enviando...', 'autocep')); ?>');

                var formData = new FormData();
                formData.append('action', 'autocep_send_support_ticket');
                formData.append('nonce', '<?php echo esc_js($nonce); ?>');
                formData.append('tipo', tipo);
                formData.append('titulo', titulo);
                formData.append('desc', descricao);

                arquivosSelecionados.forEach(function(file, i){
                    formData.append('arquivos[]', file, file.name);
                });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                }).done(function(r){
                    if (r && r.success) {
                        var numero = r.data && r.data.numero ? r.data.numero : '';
                        var msgSucesso = numero
                            ? '<?php echo esc_js(__('✅ Chamado', 'autocep')); ?> ' + numero + ' <?php echo esc_js(__('enviado! A equipe TESW entrará em contato em breve.', 'autocep')); ?>'
                            : '<?php echo esc_js(__('✅ Chamado enviado! A equipe TESW entrará em contato em breve.', 'autocep')); ?>';

                        $fb.removeClass('autocep-fb-erro').addClass('autocep-fb-ok')
                           .text(msgSucesso).show();
                        $('#autocep-ticket-titulo').val('');
                        $('#autocep-ticket-descricao').val('');
                        arquivosSelecionados = [];
                        renderizarPreviews();

                        setTimeout(function(){ window.location.reload(); }, 1400);
                    } else {
                        $fb.removeClass('autocep-fb-ok').addClass('autocep-fb-erro')
                           .text((r && r.data && r.data.message) || '<?php echo esc_js(__('Falha ao enviar. Tente pelo e-mail ou WhatsApp.', 'autocep')); ?>').show();
                    }
                }).fail(function(){
                    $fb.removeClass('autocep-fb-ok').addClass('autocep-fb-erro')
                       .text('<?php echo esc_js(__('Erro de conexão. Use o e-mail teswinfor@gmail.com.', 'autocep')); ?>').show();
                }).always(function(){
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-email-alt"></span> <?php echo esc_js(__('Enviar Chamado', 'autocep')); ?>');
                });
            });

            $(document).on('click', '.autocep-ticket-toggle-btn', function(){
                var $btn  = $(this);
                var $row  = $btn.closest('.autocep-ticket-row');
                var id    = $btn.data('id');
                var novoStatus = $btn.data('novo-status');

                $btn.prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'autocep_toggle_ticket_status',
                    nonce: '<?php echo esc_js($nonce); ?>',
                    id: id,
                    status: novoStatus,
                }).done(function(r){
                    if (!r || !r.success) {
                        $btn.prop('disabled', false);
                        return;
                    }

                    var fechado = 'fechado' === r.data.status;

                    $row.find('.autocep-ticket-status')
                        .removeClass('autocep-ticket-status--aberto autocep-ticket-status--fechado')
                        .addClass('autocep-ticket-status--' + r.data.status)
                        .text(fechado ? '<?php echo esc_js(__('Fechado', 'autocep')); ?>' : '<?php echo esc_js(__('Aberto', 'autocep')); ?>');

                    $btn
                        .data('novo-status', fechado ? 'aberto' : 'fechado')
                        .attr('data-novo-status', fechado ? 'aberto' : 'fechado')
                        .text(fechado ? '<?php echo esc_js(__('Reabrir', 'autocep')); ?>' : '<?php echo esc_js(__('Marcar como resolvido', 'autocep')); ?>')
                        .prop('disabled', false);
                }).fail(function(){
                    $btn.prop('disabled', false);
                });
            });

            var arquivosSelecionados = []; 

            function renderizarPreviews() {
                var $grid = $('#autocep-file-preview-grid');
                $grid.empty();

                if (!arquivosSelecionados.length) {
                    $grid.hide();
                    $('#autocep-file-drop').removeClass('autocep-file-has');
                    return;
                }

                $grid.show();
                $('#autocep-file-drop').addClass('autocep-file-has');

                arquivosSelecionados.forEach(function(file, idx) {
                    var $card = $('<div class="autocep-file-card" />');

                    if (file.type.startsWith('image/')) {
                        var reader = new FileReader();
                        reader.onload = (function($c){ return function(e) {
                            $c.prepend($('<img />').attr('src', e.target.result)
                                .css({width:'60px',height:'60px',objectFit:'cover',borderRadius:'6px',border:'1px solid #e5e7eb'}));
                        }; })($card);
                        reader.readAsDataURL(file);
                    } else {
                        $card.prepend(
                            $('<span class="dashicons dashicons-media-document" />')
                                .css({fontSize:'36px',color:'#6b7280',display:'block'})
                        );
                    }

                    var tamanhoKB = Math.round(file.size / 1024);
                    $card.append(
                        $('<span class="autocep-file-card-name" />')
                            .text(file.name.length > 20 ? file.name.substring(0,18)+'…' : file.name),
                        $('<span class="autocep-file-card-size" />').text(tamanhoKB + ' KB')
                    );

                    var $removeBtn = $('<button type="button" class="autocep-file-remove" title="Remover" />')
                        .html('<span class="dashicons dashicons-no-alt"></span>')
                        .data('idx', idx)
                        .on('click', function(){
                            arquivosSelecionados.splice($(this).data('idx'), 1);
                            renderizarPreviews();
                        });
                    $card.append($removeBtn);
                    $grid.append($card);
                });
            }

            $('#autocep-ticket-arquivo').on('change', function(){
                var novos = Array.from(this.files || []);
                var limite = 5;

                novos.forEach(function(f){
                    var tiposValidos = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
                    var maxBytes = 5 * 1024 * 1024;

                    if (!tiposValidos.includes(f.type)) return;
                    if (f.size > maxBytes) {
                        alert(f.name + ': ' + '<?php echo esc_js(__('Arquivo maior que 5 MB. Ignorado.', 'autocep')); ?>');
                        return;
                    }
                    if (arquivosSelecionados.length < limite) {
                        arquivosSelecionados.push(f);
                    } else {
                        alert('<?php echo esc_js(__('Limite de 5 arquivos atingido.', 'autocep')); ?>');
                    }
                });

                $(this).val('');
                renderizarPreviews();
            });

            $('#autocep-file-drop').on('dragover', function(e){
                e.preventDefault();
                $(this).addClass('autocep-file-drag');
            }).on('dragleave', function(){
                $(this).removeClass('autocep-file-drag');
            }).on('drop', function(e){
                e.preventDefault();
                $(this).removeClass('autocep-file-drag');
                var dt = e.originalEvent.dataTransfer;
                if (dt && dt.files.length) {
                    var fakeEvt = { target: { files: dt.files } };
                    $('#autocep-ticket-arquivo').prop('files', dt.files).trigger('change');
                }
            });
        });
        </script>
        <?php
    }

    private function render_feedback_tab()
    {
        $nonce     = wp_create_nonce(AUTOCEP_ADMIN_NONCE_ACTION);
        $historico = class_exists('AutoCEP_Feedback') ? AutoCEP_Feedback::get_feedback(10) : array();
        $rotulos   = array(
            1 => __('Muito ruim', 'autocep'),
            2 => __('Ruim', 'autocep'),
            3 => __('Regular', 'autocep'),
            4 => __('Bom', 'autocep'),
            5 => __('Excelente!', 'autocep'),
        );
        ?>
        <style>
            .autocep-feedback-hero { background: linear-gradient(135deg, #1d4ed8, #3b82f6); color: #fff; border-radius: 12px; padding: 22px 26px; margin: 18px 0; }
            .autocep-feedback-hero h2 { color: #fff; margin: 0 0 6px; font-size: 20px; }
            .autocep-feedback-hero p { margin: 0; font-size: 14px; opacity: .95; max-width: 760px; line-height: 1.55; }
            .autocep-feedback-badge { display: inline-block; background: #fbbf24; color: #1f2937; font-weight: 800; font-size: 11px; letter-spacing: .04em; padding: 3px 9px; border-radius: 999px; margin-bottom: 10px; }
            .autocep-stars { display: inline-flex; gap: 4px; }
            .autocep-star { background: none; border: 0; padding: 0; cursor: pointer; font-size: 36px; line-height: 1; color: #d1d5db; transition: transform .1s, color .1s; }
            .autocep-star:hover { transform: scale(1.12); }
            .autocep-star.is-on { color: #f59e0b; }
            .autocep-star:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; border-radius: 4px; }
            .autocep-star-label { margin-left: 10px; font-weight: 600; color: #374151; vertical-align: 8px; }
            .autocep-review-cta { display: none; margin-top: 14px; padding: 14px 16px; border-radius: 8px; background: #fffbeb; border: 1px solid #fcd34d; }
            .autocep-review-cta a.button-primary { margin-top: 8px; }
            .autocep-feedback-item { border-bottom: 1px solid #f3f4f6; padding: 10px 0; font-size: 13px; }
            .autocep-feedback-item:last-child { border-bottom: 0; }
            .autocep-feedback-item-stars { color: #f59e0b; letter-spacing: 2px; }
            .autocep-feedback-item-date { color: #9ca3af; font-size: 11px; margin-left: 6px; }
            .autocep-feedback-item p { margin: 4px 0 0; color: #4b5563; white-space: pre-line; }
            .autocep-video-embed { position: relative; padding-top: 56.25%; border-radius: 8px; overflow: hidden; background: #000; }
            .autocep-video-embed iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        </style>

        <div class="autocep-support-wrap">
            <div class="autocep-feedback-hero">
                <span class="autocep-feedback-badge"><?php esc_html_e('100% GRATUITO', 'autocep'); ?></span>
                <h2><?php esc_html_e('O AutoCEP é gratuito — e sua avaliação é o que mantém ele vivo', 'autocep'); ?></h2>
                <p><?php esc_html_e('Não existe versão paga, nem recurso bloqueado. Leva menos de 1 minuto: dê sua nota, conte o que achou e diga o que podemos melhorar. Cada sugestão é lida pela equipe TESW e ajuda a decidir as próximas funcionalidades.', 'autocep'); ?></p>
            </div>

            <div class="autocep-support-grid">
                <div class="autocep-support-card">
                    <div class="autocep-support-card-header">
                        <span class="dashicons dashicons-star-filled"></span>
                        <h3><?php esc_html_e('Sua Avaliação', 'autocep'); ?></h3>
                    </div>
                    <div class="autocep-support-card-body">
                        <div class="autocep-field-group">
                            <label><?php esc_html_e('Qual nota você dá ao AutoCEP?', 'autocep'); ?></label>
                            <div>
                                <div class="autocep-stars" id="autocep-stars" role="radiogroup" aria-label="<?php esc_attr_e('Nota de 1 a 5 estrelas', 'autocep'); ?>">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                                        <button type="button" class="autocep-star" data-nota="<?php echo esc_attr($i); ?>" role="radio" aria-checked="false"
                                            aria-label="<?php echo esc_attr(sprintf(_n('%d estrela', '%d estrelas', $i, 'autocep'), $i)); ?>">★</button>
                                    <?php endfor; ?>
                                </div>
                                <span class="autocep-star-label" id="autocep-star-label"></span>
                            </div>
                        </div>
                        <div class="autocep-field-group">
                            <label for="autocep-feedback-comentario"><?php esc_html_e('Comentário', 'autocep'); ?></label>
                            <textarea id="autocep-feedback-comentario" rows="4" placeholder="<?php esc_attr_e('O que você mais gostou? Algo te incomodou?', 'autocep'); ?>"></textarea>
                        </div>
                        <div class="autocep-field-group">
                            <label for="autocep-feedback-sugestao"><?php esc_html_e('Sugestões de melhoria (opcional)', 'autocep'); ?></label>
                            <textarea id="autocep-feedback-sugestao" rows="4" placeholder="<?php esc_attr_e('Qual funcionalidade você gostaria de ver no AutoCEP?', 'autocep'); ?>"></textarea>
                        </div>
                        <p style="font-size:11px;color:#6b7280;margin:0 0 12px;">
                            <?php esc_html_e('Ao enviar, sua avaliação é salva neste site e enviada por e-mail para a equipe TESW, junto com a URL do site e as versões do WordPress, WooCommerce, PHP e AutoCEP.', 'autocep'); ?>
                        </p>
                        <div id="autocep-feedback-msg" class="autocep-ticket-feedback" style="display:none;"></div>
                        <button type="button" class="autocep-btn-primary" id="autocep-feedback-submit">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Enviar Avaliação', 'autocep'); ?>
                        </button>

                        <div class="autocep-review-cta" id="autocep-review-cta">
                            <strong><?php esc_html_e('Que bom que você gostou! 💙', 'autocep'); ?></strong><br />
                            <?php esc_html_e('Pode repetir essa nota no WordPress.org? Avaliações públicas ajudam outros lojistas a encontrar o AutoCEP — e é o que mais nos motiva a continuar oferecendo tudo de graça.', 'autocep'); ?><br />
                            <a href="<?php echo esc_url(self::REVIEW_URL); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
                                ⭐ <?php esc_html_e('Avaliar no WordPress.org', 'autocep'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:16px;">
                    <div class="autocep-support-card">
                        <div class="autocep-support-card-header">
                            <span class="dashicons dashicons-video-alt3"></span>
                            <h3><?php esc_html_e('Vídeo de Apresentação', 'autocep'); ?></h3>
                        </div>
                        <div class="autocep-support-card-body">
                            <div class="autocep-video-embed">
                                <iframe src="https://www.youtube-nocookie.com/embed/EkEC8CJ_H5c" title="<?php esc_attr_e('Vídeo de apresentação do AutoCEP', 'autocep'); ?>" loading="lazy"
                                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                            </div>
                        </div>
                    </div>

                    <div class="autocep-support-card">
                        <div class="autocep-support-card-header">
                            <span class="dashicons dashicons-list-view"></span>
                            <h3><?php esc_html_e('Avaliações Enviadas deste Site', 'autocep'); ?></h3>
                        </div>
                        <div class="autocep-support-card-body" id="autocep-feedback-history">
                            <?php if (empty($historico)) : ?>
                                <p class="autocep-feedback-empty" style="color:#6b7280;font-size:13px;margin:0;"><?php esc_html_e('Nenhuma avaliação enviada ainda.', 'autocep'); ?></p>
                            <?php else : ?>
                                <?php foreach ($historico as $item) : ?>
                                    <div class="autocep-feedback-item">
                                        <span class="autocep-feedback-item-stars"><?php echo esc_html(str_repeat('★', (int) $item->nota) . str_repeat('☆', 5 - (int) $item->nota)); ?></span>
                                        <span class="autocep-feedback-item-date"><?php echo esc_html(mysql2date(get_option('date_format'), $item->criado_em)); ?></span>
                                        <?php if ($item->comentario) : ?>
                                            <p><?php echo esc_html($item->comentario); ?></p>
                                        <?php endif; ?>
                                        <?php if ($item->sugestao) : ?>
                                            <p><strong><?php esc_html_e('Sugestão:', 'autocep'); ?></strong> <?php echo esc_html($item->sugestao); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var rotulos = <?php echo wp_json_encode($rotulos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            var notaAtual = 0;
            var $estrelas = $('#autocep-stars .autocep-star');

            function pintar(nota) {
                $estrelas.each(function(){
                    var n = parseInt($(this).data('nota'), 10);
                    $(this).toggleClass('is-on', n <= nota).attr('aria-checked', n === notaAtual ? 'true' : 'false');
                });
                $('#autocep-star-label').text(nota ? rotulos[nota] : '');
            }

            $estrelas.on('mouseenter focus', function(){
                pintar(parseInt($(this).data('nota'), 10));
            }).on('click', function(){
                notaAtual = parseInt($(this).data('nota'), 10);
                pintar(notaAtual);
            });
            $('#autocep-stars').on('mouseleave', function(){ pintar(notaAtual); });

            $('#autocep-feedback-submit').on('click', function(){
                var $btn = $(this);
                var $msg = $('#autocep-feedback-msg');
                var comentario = $('#autocep-feedback-comentario').val().trim();
                var sugestao   = $('#autocep-feedback-sugestao').val().trim();

                if (!notaAtual) {
                    $msg.removeClass('autocep-fb-ok').addClass('autocep-fb-erro')
                        .text('<?php echo esc_js(__('Escolha uma nota de 1 a 5 estrelas.', 'autocep')); ?>').show();
                    return;
                }

                $btn.prop('disabled', true).text('<?php echo esc_js(__('Enviando...', 'autocep')); ?>');

                $.post(ajaxurl, {
                    action: 'autocep_send_feedback',
                    nonce: '<?php echo esc_js($nonce); ?>',
                    nota: notaAtual,
                    comentario: comentario,
                    sugestao: sugestao
                }).done(function(r){
                    if (!r || !r.success) {
                        $msg.removeClass('autocep-fb-ok').addClass('autocep-fb-erro')
                            .text((r && r.data && r.data.message) || '<?php echo esc_js(__('Não foi possível enviar sua avaliação.', 'autocep')); ?>').show();
                        return;
                    }

                    $msg.removeClass('autocep-fb-erro').addClass('autocep-fb-ok')
                        .text('<?php echo esc_js(__('✅ Obrigado! Sua avaliação foi recebida pela equipe TESW.', 'autocep')); ?>').show();

                    var estrelasTxt = new Array(r.data.nota + 1).join('★') + new Array(6 - r.data.nota).join('☆');
                    var $item = $('<div class="autocep-feedback-item" />')
                        .append($('<span class="autocep-feedback-item-stars" />').text(estrelasTxt))
                        .append($('<span class="autocep-feedback-item-date" />').text('<?php echo esc_js(__('agora', 'autocep')); ?>'));
                    if (comentario) { $item.append($('<p />').text(comentario)); }
                    if (sugestao) { $item.append($('<p />').text('<?php echo esc_js(__('Sugestão:', 'autocep')); ?> ' + sugestao)); }
                    $('#autocep-feedback-history .autocep-feedback-empty').remove();
                    $('#autocep-feedback-history').prepend($item);

                    $('#autocep-feedback-comentario, #autocep-feedback-sugestao').val('');

                    if (r.data.nota >= 4) {
                        $('#autocep-review-cta').slideDown(150);
                    }
                }).fail(function(){
                    $msg.removeClass('autocep-fb-ok').addClass('autocep-fb-erro')
                        .text('<?php echo esc_js(__('Erro de conexão. Tente novamente.', 'autocep')); ?>').show();
                }).always(function(){
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> <?php echo esc_js(__('Enviar Avaliação', 'autocep')); ?>');
                });
            });
        });
        </script>
        <?php
    }
}
