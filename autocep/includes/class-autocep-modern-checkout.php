<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checkout Moderno (Multietapas).
 *
 * Transforma o checkout clássico do WooCommerce em 3 etapas — Identificação,
 * Entrega e Pagamento — com resumo do pedido fixo ao lado (ou recolhível no
 * celular). A montagem das etapas é feita no navegador, MOVENDO os campos
 * originais para dentro de painéis — nenhum campo é recriado ou removido
 * do formulário. Por isso continuam funcionando:
 *  - a validação e o envio nativos do WooCommerce (AJAX de checkout);
 *  - os fragmentos de atualização ("update_checkout"), que substituem a
 *    tabela de resumo e o bloco de pagamento pela classe CSS, onde quer que
 *    estejam na página;
 *  - os gateways de pagamento e plugins de campos (CPF/CNPJ, número, bairro);
 *  - o autocompletar de CEP do AutoCEP (os IDs dos campos não mudam).
 *
 * Fica desativado automaticamente em checkouts que já têm a própria
 * estrutura de etapas ou que não são o checkout clássico: Checkout em Blocos,
 * Fluid Checkout e CartFlows.
 */
class AutoCEP_Modern_Checkout
{
    // Prefixo das chaves dos campos extras criados no painel. Começar com
    // "billing_" faz o WooCommerce salvá-los sozinho no pedido (meta
    // "_billing_autocep_...").
    const CUSTOM_PREFIX = 'billing_autocep_';

    public function __construct()
    {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 20);
        add_filter('body_class', array($this, 'body_class'));

        // Prioridade alta: aplicada depois de outros plugins de campos
        // (ex.: Brazilian Market), para que a escolha do lojista prevaleça.
        add_filter('woocommerce_checkout_fields', array($this, 'customize_fields'), 1000);
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'admin_order_custom_fields'));
        add_filter('woocommerce_email_order_meta_fields', array($this, 'email_custom_fields'), 10, 3);

        // Modo de conta escolhido no AutoCEP (sem mexer nas configurações do WooCommerce).
        add_filter('woocommerce_checkout_registration_required', array($this, 'filter_registration_required'), 20);
        add_filter('woocommerce_checkout_registration_enabled', array($this, 'filter_registration_enabled'), 20);

        // "Entregar em um endereço diferente?" começa desmarcada (o cliente marca se quiser).
        add_filter('woocommerce_ship_to_different_address_checked', array($this, 'filter_ship_to_different'), 20);
    }

    /**
     * Campos padrão que o lojista pode esconder, tornar obrigatórios/
     * opcionais ou renomear no painel. E-mail, nome, CEP, endereço, cidade,
     * estado e país ficam de fora: são necessários para o pedido e o frete.
     */
    public static function get_managed_fields()
    {
        return array(
            'billing_last_name' => __('Sobrenome', 'autocep'),
            'billing_company'   => __('Empresa', 'autocep'),
            'billing_address_2' => __('Complemento', 'autocep'),
            'billing_phone'     => __('Telefone', 'autocep'),
            'order_comments'    => __('Observações do pedido', 'autocep'),
        );
    }

    /**
     * Modo de conta do painel: "woocommerce" (segue a loja), "required"
     * (conta obrigatória), "optional" (caixa "Criar uma conta?") ou "guest"
     * (compra sem conta). Vale só com o Checkout Moderno ativo.
     */
    private static function account_mode()
    {
        if (!self::fields_active()) {
            return 'woocommerce';
        }

        $config = self::get_config();
        $modo   = isset($config['account_mode']) ? $config['account_mode'] : 'woocommerce';

        return in_array($modo, array('required', 'optional', 'guest'), true) ? $modo : 'woocommerce';
    }

    public function filter_ship_to_different($marcado)
    {
        if (!self::fields_active()) {
            return $marcado;
        }

        $config = self::get_config();

        return 'woocommerce' === (isset($config['ship_different']) ? $config['ship_different'] : 'unchecked') ? $marcado : 0;
    }

    public function filter_registration_required($obrigatoria)
    {
        $modo = self::account_mode();

        return 'woocommerce' === $modo ? $obrigatoria : 'required' === $modo;
    }

    public function filter_registration_enabled($permitida)
    {
        $modo = self::account_mode();

        return 'woocommerce' === $modo ? $permitida : 'guest' !== $modo;
    }

    public static function get_custom_field_types()
    {
        return array(
            'text'     => __('Texto', 'autocep'),
            'textarea' => __('Texto longo', 'autocep'),
            'number'   => __('Número', 'autocep'),
            'date'     => __('Data', 'autocep'),
            'select'   => __('Lista de opções', 'autocep'),
            'checkbox' => __('Caixa de seleção', 'autocep'),
        );
    }

    /**
     * Gerenciamento de campos ligado: Checkout Moderno ativo e compatível.
     * Não depende da página atual, porque o filtro de campos também roda no
     * envio do pedido (AJAX), onde is_checkout() é falso.
     */
    private static function fields_active()
    {
        $config = self::get_config();

        return !empty($config['enabled']) && '' === self::get_incompatibility_reason();
    }

    /**
     * Aplica as escolhas do painel aos campos do checkout.
     */
    public function customize_fields($fields)
    {
        if (!self::fields_active()) {
            return $fields;
        }

        $config = self::get_config();
        $ajustes = isset($config['fields']) && is_array($config['fields']) ? $config['fields'] : array();

        foreach ($ajustes as $chave => $ajuste) {
            $grupo = 'order_comments' === $chave ? 'order' : 'billing';

            // Empresa, complemento e sobrenome valem também para a entrega.
            $alvos = array(array($grupo, $chave));

            if ('billing' === $grupo && 'billing_phone' !== $chave) {
                $alvos[] = array('shipping', preg_replace('/^billing_/', 'shipping_', $chave));
            }

            foreach ($alvos as $alvo) {
                list($g, $k) = $alvo;

                if (!isset($fields[$g][$k])) {
                    continue;
                }

                if (isset($ajuste['show']) && !$ajuste['show']) {
                    unset($fields[$g][$k]);
                    continue;
                }

                if (isset($ajuste['required']) && in_array($ajuste['required'], array('yes', 'no'), true)) {
                    $fields[$g][$k]['required'] = 'yes' === $ajuste['required'];
                }

                if (!empty($ajuste['label'])) {
                    $fields[$g][$k]['label'] = $ajuste['label'];
                    // O complemento vem com o rótulo só para leitores de tela.
                    $fields[$g][$k]['label_class'] = array();
                }
            }
        }

        foreach (self::get_custom_fields() as $campo) {
            // Verificação antes de adicionar: campo que o WooCommerce não
            // aceitaria (tipo inválido, lista sem opções, chave já usada...)
            // simplesmente não entra — o checkout nunca quebra por causa dele.
            if ('' !== self::validate_custom_field($campo, $fields)) {
                continue;
            }

            $definicao = array(
                'type'     => $campo['type'],
                'label'    => $campo['label'],
                'required' => !empty($campo['required']),
                'class'    => array('form-row-wide', 'autocep-mc-custom-field'),
                // Etapa 1: logo depois do nome; etapa 2: depois do endereço.
                'priority' => 1 === (int) $campo['step'] ? 25 : 95,
            );

            if ('select' === $campo['type']) {
                $opcoes = array_filter(array_map('trim', explode(',', $campo['options'])), 'strlen');
                $definicao['options'] = array('' => __('Selecione', 'autocep')) + array_combine($opcoes, $opcoes);
            }

            if ('checkbox' === $campo['type']) {
                $definicao['class'][] = 'form-row-checkbox';
            }

            $fields['billing'][$campo['key']] = $definicao;
        }

        return $fields;
    }

    /**
     * Verifica se um campo extra pode ser adicionado ao checkout sem quebrar
     * a página e dentro do padrão do WooCommerce.
     *
     * Usada em três momentos: ao salvar no painel (campo inválido nem é
     * gravado), na tela do painel (aviso por campo) e no checkout (campo
     * inválido é ignorado em vez de causar erro).
     *
     * @param array      $campo Item de campo extra (key, label, type, options...).
     * @param array|null $campos_checkout Campos do checkout já montados
     *                   (WooCommerce + outros plugins), para detectar conflito
     *                   de identificador; null pula essa checagem.
     * @return string '' se o campo é aceito, ou o motivo da recusa.
     */
    public static function validate_custom_field($campo, $campos_checkout = null)
    {
        $chave = isset($campo['key']) ? (string) $campo['key'] : '';
        $tipo  = isset($campo['type']) ? (string) $campo['type'] : '';

        if (0 !== strpos($chave, self::CUSTOM_PREFIX) || !preg_match('/^[a-z0-9_]{1,90}$/', $chave)) {
            return __('Identificador do campo inválido.', 'autocep');
        }

        if ('' === trim(wp_strip_all_tags(isset($campo['label']) ? (string) $campo['label'] : ''))) {
            return __('O campo precisa de um rótulo (o nome que o cliente vê).', 'autocep');
        }

        // Os mesmos tipos que a função woocommerce_form_field() sabe desenhar.
        if (!isset(self::get_custom_field_types()[$tipo])) {
            return __('Tipo de campo não suportado pelo checkout do WooCommerce.', 'autocep');
        }

        if ('select' === $tipo) {
            $opcoes = array_filter(array_map('trim', explode(',', isset($campo['options']) ? (string) $campo['options'] : '')), 'strlen');

            // Lista vazia + obrigatório = o cliente nunca consegue finalizar a compra.
            if (empty($opcoes)) {
                return __('Informe ao menos uma opção (separadas por vírgula) para o campo "Lista de opções".', 'autocep');
            }

            if (count($opcoes) > 50) {
                return __('A lista de opções tem mais de 50 itens — reduza para o checkout continuar rápido.', 'autocep');
            }
        }

        if (is_array($campos_checkout) && isset($campos_checkout['billing'][$chave])) {
            $existente = $campos_checkout['billing'][$chave];
            $classes   = isset($existente['class']) ? (array) $existente['class'] : array();

            // Já está lá por ser do próprio AutoCEP (não é conflito); qualquer
            // outro campo com o mesmo identificador é de outro plugin/tema.
            if (!in_array('autocep-mc-custom-field', $classes, true)) {
                return __('Já existe outro campo no checkout com este identificador (WooCommerce, tema ou outro plugin).', 'autocep');
            }
        }

        return '';
    }

    /**
     * Campos extras válidos salvos no painel.
     *
     * @return array<int, array{key: string, label: string, type: string, step: int, required: int, options: string}>
     */
    public static function get_custom_fields()
    {
        $config = self::get_config();
        $lista  = isset($config['custom_fields']) && is_array($config['custom_fields']) ? $config['custom_fields'] : array();

        return array_values(array_filter($lista, function ($campo) {
            return !empty($campo['key']) && !empty($campo['label']) && 0 === strpos($campo['key'], self::CUSTOM_PREFIX);
        }));
    }

    /**
     * Mostra os campos extras no admin do pedido, abaixo do endereço.
     */
    public function admin_order_custom_fields($order)
    {
        foreach (self::get_custom_fields() as $campo) {
            $valor = $order->get_meta('_' . $campo['key']);

            if ('' === $valor || null === $valor) {
                continue;
            }

            if ('checkbox' === $campo['type']) {
                $valor = $valor ? __('Sim', 'autocep') : __('Não', 'autocep');
            }

            printf('<p><strong>%s:</strong> %s</p>', esc_html($campo['label']), esc_html($valor));
        }
    }

    /**
     * Inclui os campos extras nos e-mails do pedido.
     */
    public function email_custom_fields($campos, $sent_to_admin, $order)
    {
        if (!$order instanceof WC_Order) {
            return $campos;
        }

        foreach (self::get_custom_fields() as $campo) {
            $valor = $order->get_meta('_' . $campo['key']);

            if ('' === $valor || null === $valor) {
                continue;
            }

            $campos[$campo['key']] = array(
                'label' => $campo['label'],
                'value' => 'checkbox' === $campo['type'] ? ($valor ? __('Sim', 'autocep') : __('Não', 'autocep')) : $valor,
            );
        }

        return $campos;
    }

    /**
     * Motivo pelo qual o Checkout Moderno não pode ser usado nesta loja, ou
     * string vazia se ele é compatível. Exibido como aviso no painel.
     */
    public static function get_incompatibility_reason()
    {
        if (class_exists('FluidCheckout')) {
            return __('O Fluid Checkout está ativo e já organiza o checkout em etapas — o Checkout Moderno do AutoCEP fica desativado para não conflitar.', 'autocep');
        }

        if (self::checkout_page_uses_blocks()) {
            return __('Sua página de checkout usa o Checkout em Blocos, que tem layout próprio — o Checkout Moderno funciona com o checkout clássico. Para usá-lo, edite a página "Finalizar compra" e troque o bloco de checkout pelo shortcode [woocommerce_checkout] (ou use a opção "Mudar para checkout clássico" do bloco).', 'autocep');
        }

        return '';
    }

    private static function checkout_page_uses_blocks()
    {
        if (!function_exists('wc_get_page_id') || !function_exists('has_block')) {
            return false;
        }

        $pagina_id = wc_get_page_id('checkout');

        return $pagina_id > 0 && has_block('woocommerce/checkout', $pagina_id);
    }

    private static function get_config()
    {
        $options = AutoCEP_Admin::get_options();

        return isset($options['modern_checkout']) ? $options['modern_checkout'] : array();
    }

    /**
     * Página atual é o formulário de checkout clássico, com o recurso ativo
     * e sem nenhum checkout de terceiros que já cuide das etapas.
     */
    public static function is_active_on_current_page()
    {
        $config = self::get_config();

        $ativo = !empty($config['enabled'])
            && function_exists('is_checkout') && is_checkout()
            && !is_order_received_page()
            && !is_wc_endpoint_url('order-pay')
            && '' === self::get_incompatibility_reason()
            && !(function_exists('_is_wcf_checkout_type') && _is_wcf_checkout_type());

        /**
         * Permite desativar o Checkout Moderno em páginas específicas.
         *
         * @param bool $ativo Se o Checkout Moderno será aplicado nesta página.
         */
        return (bool) apply_filters('autocep_modern_checkout_enabled', $ativo);
    }

    private static function is_pix_thankyou_page()
    {
        $config = self::get_config();

        return !empty($config['enabled']) && !empty($config['pix_thankyou'])
            && function_exists('is_order_received_page') && is_order_received_page();
    }

    public function body_class($classes)
    {
        if (self::is_active_on_current_page()) {
            $classes[] = 'autocep-mc-active';

            $config = self::get_config();

            if (!empty($config['compact'])) {
                $classes[] = 'autocep-mc-compact';
            }
        }

        return $classes;
    }

    /**
     * Tipo de cada forma de pagamento disponível (pix, card, boleto ou
     * other), detectado pelo ID e pelo título do gateway — funciona com os
     * gateways brasileiros mais comuns sem configuração (Mercado Pago,
     * PagSeguro, Pagar.me, Asaas, Iugu, Efí, Cielo, Rede etc.).
     */
    /**
     * Versão pública do mapa de tipos (usada pelo Analytics do checkout).
     */
    public static function get_gateway_types_public()
    {
        return self::get_gateway_types();
    }

    private static function get_gateway_types()
    {
        if (!function_exists('WC') || !WC()->payment_gateways()) {
            return array();
        }

        $tipos = array();

        foreach (WC()->payment_gateways()->payment_gateways() as $id => $gateway) {
            $texto = $id . ' ' . wp_strip_all_tags($gateway->get_title());

            if (preg_match('/pix/iu', $texto)) {
                $tipo = 'pix';
            } elseif (preg_match('/boleto|bank.?slip|billet|ticket|bolbradesco/iu', $texto)) {
                $tipo = 'boleto';
            } elseif (preg_match('/card|cart[aã]o|credit|cr[eé]dito|debit|d[eé]bito/iu', $texto)) {
                $tipo = 'card';
            } else {
                $tipo = 'other';
            }

            $tipos[$id] = $tipo;
        }

        /**
         * Permite corrigir o tipo detectado de um gateway.
         *
         * @param array $tipos Mapa id_do_gateway => pix|card|boleto|other.
         */
        return apply_filters('autocep_modern_checkout_gateway_types', $tipos);
    }

    /**
     * Aviso de criação de conta para quem ainda não tem conta, de acordo com
     * a configuração da loja em WooCommerce > Contas e privacidade.
     *
     * @return array|null Dados do aviso, ou null quando não há o que avisar
     *                    (cliente logado, aviso desligado ou a loja não cria
     *                    contas no checkout).
     */
    private static function get_account_notice($config)
    {
        if (empty($config['account_notice']) || is_user_logged_in() || !function_exists('WC') || !WC()->checkout()) {
            return null;
        }

        $checkout    = WC()->checkout();
        $obrigatoria = $checkout->is_registration_required();
        $permitida   = $checkout->is_registration_enabled();

        if (!$obrigatoria && !$permitida) {
            return null;
        }

        $senha_auto = 'yes' === get_option('woocommerce_registration_generate_password');

        if (!empty($config['account_message'])) {
            $texto = $config['account_message'];
        } elseif ($obrigatoria && $senha_auto) {
            $texto = __('Para concluir a compra, vamos criar sua conta com o e-mail {email} e enviar a senha para ele. Assim você acompanha seus pedidos e compra mais rápido da próxima vez.', 'autocep');
        } elseif ($obrigatoria) {
            $texto = __('Para concluir a compra, vamos criar sua conta com o e-mail {email}. Assim você acompanha seus pedidos e compra mais rápido da próxima vez — é só escolher uma senha abaixo.', 'autocep');
        } else {
            $texto = __('Quer acompanhar seus pedidos e comprar mais rápido da próxima vez? Marque "Criar uma conta" abaixo — é opcional.', 'autocep');
        }

        return array(
            'mode'       => $obrigatoria ? 'required' : 'optional',
            'message'    => $texto,
            'title'      => $obrigatoria ? __('Sua conta será criada nesta compra', 'autocep') : __('Crie sua conta (opcional)', 'autocep'),
            'login_text' => __('Já tem conta? Entrar', 'autocep'),
            'email_fallback' => __('informado acima', 'autocep'),
            // Formulário de login do próprio checkout ("Já é cliente? Clique
            // aqui"), se ativo; senão, a página Minha Conta.
            'login_url'  => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '',
        );
    }

    public function enqueue_assets()
    {
        $no_checkout = self::is_active_on_current_page();
        $pix_pedido  = self::is_pix_thankyou_page();

        if (!$no_checkout && !$pix_pedido) {
            return;
        }

        $config  = self::get_config();
        $options = AutoCEP_Admin::get_options();
        $padroes = AutoCEP_Admin::get_default_options();
        $padroes = $padroes['modern_checkout'];

        wp_enqueue_style(
            'autocep-modern-checkout',
            AUTOCEP_PLUGIN_URL . 'assets/css/autocep-modern-checkout.css',
            array(),
            autocep_asset_version('assets/css/autocep-modern-checkout.css')
        );

        $cor = !empty($config['accent_color']) ? $config['accent_color'] : $padroes['accent_color'];
        $tamanho = function ($chave, $padrao, $min, $max) use ($config) {
            return isset($config[$chave]) && '' !== $config[$chave] ? max($min, min($max, (int) $config[$chave])) : $padrao;
        };

        wp_add_inline_style('autocep-modern-checkout', sprintf(
            ':root{--autocep-mc-accent:%1$s;--autocep-mc-form-w:%2$dpx;--autocep-mc-summary-w:%3$dpx;--autocep-mc-field-h:%4$dpx;}',
            esc_attr($cor),
            $tamanho('form_width', 580, 400, 1200),
            $tamanho('summary_width', 340, 260, 600),
            $tamanho('field_height', 38, 32, 60)
        ));

        wp_enqueue_script(
            'autocep-modern-checkout',
            AUTOCEP_PLUGIN_URL . 'assets/js/autocep-modern-checkout.js',
            $no_checkout ? array('jquery', 'wc-checkout') : array('jquery'),
            autocep_asset_version('assets/js/autocep-modern-checkout.js'),
            true
        );

        $texto = function ($chave) use ($config, $padroes) {
            return isset($config[$chave]) && '' !== $config[$chave] ? $config[$chave] : $padroes[$chave];
        };

        wp_localize_script('autocep-modern-checkout', 'autocep_mc_params', array(
            'mode'               => $no_checkout ? 'checkout' : 'thankyou',
            'compact'            => !empty($config['compact']),
            // Login dentro da etapa 1 (no lugar do formulário do topo).
            'login'              => $no_checkout && !is_user_logged_in() ? array(
                'lost_url'    => wp_lostpassword_url(),
                'link'        => __('Já tem conta? Entrar', 'autocep'),
                'title'       => __('Entrar na sua conta', 'autocep'),
                'user'        => __('E-mail ou nome de usuário', 'autocep'),
                'password'    => __('Senha', 'autocep'),
                'remember'    => __('Lembrar de mim', 'autocep'),
                'submit'      => __('Entrar', 'autocep'),
                'lost'        => __('Esqueceu a senha?', 'autocep'),
                'cancel'      => __('Continuar sem entrar', 'autocep'),
                'empty'       => __('Digite o e-mail e a senha.', 'autocep'),
            ) : null,
            'account'            => $no_checkout ? self::get_account_notice($config) : null,
            'neighborhood_field' => isset($options['general']['neighborhood_field']) ? $options['general']['neighborhood_field'] : 'billing_address_2',
            'needs_shipping'     => function_exists('WC') && WC()->cart ? WC()->cart->needs_shipping() : true,
            'simulator'          => isset($config['simulator']) ? $config['simulator'] : 'top',
            // Funil de conversão (aba Analytics): só com a coleta de estatísticas ligada.
            'track'              => array(
                'enabled' => $no_checkout && class_exists('AutoCEP_Checkout_Analytics') && AutoCEP_Checkout_Analytics::is_enabled(),
                'url'     => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('autocep_mc_track'),
            ),
            'step1_fields'       => array_values(array_map(function ($campo) {
                return $campo['key'];
            }, array_filter(self::get_custom_fields(), function ($campo) {
                return 1 === (int) $campo['step'];
            }))),
            'steps'              => array($texto('step1_title'), $texto('step2_title'), $texto('step3_title')),
            'gateway_types'      => $no_checkout ? self::get_gateway_types() : array(),
            'pay_messages'       => array(
                'pix'    => isset($config['pix_message']) ? $config['pix_message'] : $padroes['pix_message'],
                'card'   => isset($config['card_message']) ? $config['card_message'] : $padroes['card_message'],
                'boleto' => isset($config['boleto_message']) ? $config['boleto_message'] : $padroes['boleto_message'],
            ),
            'i18n'               => array(
                'next'            => $texto('next_label'),
                'back'            => __('Voltar', 'autocep'),
                'edit'            => __('Editar', 'autocep'),
                'step_of'         => __('Etapa %1$d de %2$d', 'autocep'),
                'summary'         => __('Resumo do pedido', 'autocep'),
                'show_summary'    => __('Ver resumo do pedido', 'autocep'),
                'hide_summary'    => __('Ocultar resumo do pedido', 'autocep'),
                'shipping_title'  => __('Forma de entrega', 'autocep'),
                'shipping_loading' => __('Calculando o frete...', 'autocep'),
                'coupon_question'    => __('Tem um cupom de desconto?', 'autocep'),
                'coupon_placeholder' => __('Código do cupom', 'autocep'),
                'coupon_apply'       => __('Aplicar', 'autocep'),
                'coupon_applied'     => __('Cupom aplicado!', 'autocep'),
                'required'        => __('Preencha este campo.', 'autocep'),
                'invalid_email'   => __('Digite um e-mail válido.', 'autocep'),
                'invalid_phone'   => __('Digite um telefone válido com DDD.', 'autocep'),
                'invalid_cep'     => __('Digite um CEP válido.', 'autocep'),
                'choose_shipping' => __('Escolha uma forma de entrega para continuar.', 'autocep'),
                'add_company'     => __('+ Adicionar empresa', 'autocep'),
                'add_address_2'   => __('+ Adicionar complemento', 'autocep'),
                'add_notes'       => __('+ Adicionar observação ao pedido', 'autocep'),
                'copy_pix'        => __('Copiar código Pix', 'autocep'),
                'copied'          => __('Código copiado!', 'autocep'),
                'pix_title'       => __('Pague com Pix', 'autocep'),
            ),
        ));
    }
}
