<?php
/**
 * Classe responsável pelo painel administrativo do AutoCEP.
 *
 * @package AutoCEP
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra o menu do plugin no wp-admin, as abas de configuração
 * (Settings API) e a tela de diagnóstico/logs.
 */
class AutoCEP_Admin
{
    /**
     * Slugs das abas disponíveis no painel.
     *
     * @var array
     */
    const TABS = array('general', 'product_shipping', 'cart_shipping', 'checkout_shipping', 'messages', 'appearance', 'diagnostics');

    /**
     * Construtor.
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Registra o menu principal do plugin no wp-admin.
     *
     * @return void
     */
    public function register_menu()
    {
        add_menu_page(
            __('AutoCEP', 'autocep'),
            __('AutoCEP', 'autocep'),
            'manage_options',
            'autocep',
            array($this, 'render_page'),
            'dashicons-location-alt',
            58
        );
    }

    /**
     * Retorna as opções padrão do plugin. O comportamento legado
     * (autocompletar CEP no Checkout) permanece habilitado por padrão;
     * as novas funcionalidades de frete vêm desabilitadas até que o
     * lojista as ative conscientemente.
     *
     * @return array
     */
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
            ),
        );
    }

    /**
     * Retorna as opções atuais do plugin, mescladas de forma segura com
     * os valores padrão (garante que novas chaves adicionadas em
     * atualizações futuras sempre tenham um valor definido).
     *
     * @return array
     */
    public static function get_options()
    {
        $salvas   = get_option(AUTOCEP_OPTION_KEY, array());
        $padroes  = self::get_default_options();
        $opcoes   = self::merge_recursive_defaults($padroes, is_array($salvas) ? $salvas : array());

        // Autolimpeza: remove configurações legadas de "correios" que
        // possam ter ficado salvas em instalações de versões anteriores
        // do plugin, já que essa fonte de busca de CEP foi removida por
        // não possuir uma API pública oficial e estável.
        if (isset($opcoes['general']['apis']['correios'])) {
            unset($opcoes['general']['apis']['correios']);
        }

        return $opcoes;
    }

    /**
     * Mescla recursivamente as opções salvas sobre os valores padrão.
     *
     * @param array $padroes Estrutura de opções padrão.
     * @param array $salvas  Estrutura de opções salvas pelo usuário.
     *
     * @return array
     */
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

    /**
     * Hooks disponíveis para posicionamento da caixa de frete na página
     * de produto.
     *
     * @return array
     */
    public static function get_product_hook_choices()
    {
        return array(
            'woocommerce_single_product_summary' => __('Resumo do produto (junto ao preço/descrição)', 'autocep'),
            'woocommerce_after_add_to_cart_form' => __('Após o formulário de compra (padrão)', 'autocep'),
            'woocommerce_after_single_product_summary' => __('Após o resumo do produto', 'autocep'),
            'shortcode' => __('Manual — via shortcode [shipping_calculator_on_product_page]', 'autocep'),
        );
    }

    /**
     * Hooks disponíveis para posicionamento da caixa de frete no checkout.
     *
     * @return array
     */
    public static function get_checkout_hook_choices()
    {
        return array(
            'woocommerce_before_checkout_form'   => __('Antes do formulário de checkout', 'autocep'),
            'woocommerce_before_order_notes'     => __('Antes do campo de observações do pedido (padrão)', 'autocep'),
            'woocommerce_checkout_before_order_review' => __('Antes do resumo do pedido', 'autocep'),
        );
    }

    /**
     * Hooks disponíveis para posicionamento da caixa de frete na página
     * do carrinho de compras.
     *
     * @return array
     */
    public static function get_cart_hook_choices()
    {
        return array(
            'woocommerce_before_cart_table'  => __('Antes da tabela do carrinho', 'autocep'),
            'woocommerce_after_cart_table'   => __('Depois da tabela do carrinho', 'autocep'),
            'woocommerce_before_cart_totals' => __('Antes do resumo do pedido (padrão)', 'autocep'),
            'woocommerce_after_cart_totals'  => __('Depois do resumo do pedido', 'autocep'),
        );
    }

    /**
     * Registra as configurações via Settings API. Um único option array
     * ("autocep_options") é compartilhado por todas as abas; cada aba
     * registra apenas as suas próprias seções/campos, e o callback de
     * sanitização faz o merge com os valores já salvos das demais abas.
     *
     * @return void
     */
    public function register_settings()
    {
        register_setting('autocep_options_group', AUTOCEP_OPTION_KEY, array(
            'sanitize_callback' => array($this, 'sanitize_options'),
        ));

        $this->register_general_tab_fields();
        $this->register_product_shipping_tab_fields();
        $this->register_cart_shipping_tab_fields();
        $this->register_checkout_shipping_tab_fields();
        $this->register_messages_tab_fields();
        $this->register_appearance_tab_fields();
    }

    /**
     * Registra os campos da aba "Geral & Busca de CEP".
     *
     * @return void
     */
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
            $this->render_text('general', 'neighborhood_field', __('ID do campo do WooCommerce que deve receber o bairro (ex.: billing_address_2, billing_neighborhood).', 'autocep'));
        }, $page, 'autocep_general_checkout');

        add_settings_field('browser_autocomplete', __('Autocompletar do Navegador', 'autocep'), function () {
            $this->render_checkbox('general', 'browser_autocomplete', __('Ativar sugestões automáticas do navegador (autocomplete) enquanto o cliente digita nos campos de endereço, com base no histórico de preenchimentos anteriores.', 'autocep'));
        }, $page, 'autocep_general_checkout');

        add_settings_section('autocep_general_apis', __('APIs de Consulta de CEP', 'autocep'), function () {
            echo '<p>' . esc_html__('Selecione quais APIs ficam ativas e defina a ordem de prioridade (1 = primeira a ser consultada). Se uma API falhar ou expirar o tempo limite, a próxima da lista é consultada automaticamente.', 'autocep') . '</p>';
        }, $page);

        add_settings_field('apis', __('APIs Ativas e Prioridade', 'autocep'), array($this, 'render_apis_field'), $page, 'autocep_general_apis');

        add_settings_section('autocep_general_cache', __('Cache de CEPs', 'autocep'), '__return_false', $page);

        add_settings_field('cache_days', __('Duração do Cache (dias)', 'autocep'), function () {
            $this->render_number('general', 'cache_days', 0, 365, __('Quantos dias um CEP já consultado fica salvo em cache antes de ser buscado novamente nas APIs.', 'autocep'));
        }, $page, 'autocep_general_cache');

        add_settings_field('clear_cache', __('Limpar Cache Agora', 'autocep'), array($this, 'render_clear_cache_button'), $page, 'autocep_general_cache');
    }

    /**
     * Registra os campos da aba "Frete na Página de Produto".
     *
     * @return void
     */
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

    /**
     * Registra os campos da aba "Frete no Carrinho".
     *
     * @return void
     */
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

    /**
     * Registra os campos da aba "Frete no Checkout".
     *
     * @return void
     */
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

    /**
     * Registra os campos da aba "Mensagens & UX".
     *
     * @return void
     */
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

    /**
     * Registra os campos da aba "Aparência", que permite personalizar as
     * cores dos campos e botões das caixas de simulação de frete
     * (produto, carrinho e checkout).
     *
     * @return void
     */
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
    }

    /* ---------------------------------------------------------------
     * Renderizadores de campos reutilizáveis
     * ------------------------------------------------------------- */

    /**
     * Renderiza um campo checkbox, com fallback oculto para garantir
     * que o valor "0" seja enviado quando desmarcado.
     *
     * @param string $grupo    Grupo da opção (ex.: general).
     * @param string $campo    Nome do campo dentro do grupo.
     * @param string $descricao Texto de apoio exibido ao lado do checkbox.
     *
     * @return void
     */
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

    /**
     * Renderiza um campo de texto simples.
     *
     * @param string $grupo     Grupo da opção.
     * @param string $campo     Nome do campo.
     * @param string $descricao Texto de apoio (exibido abaixo do campo).
     *
     * @return void
     */
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

    /**
     * Renderiza um campo numérico com limites mínimo e máximo.
     *
     * @param string $grupo     Grupo da opção.
     * @param string $campo     Nome do campo.
     * @param int    $min       Valor mínimo aceito.
     * @param int    $max       Valor máximo aceito.
     * @param string $descricao Texto de apoio.
     *
     * @return void
     */
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

    /**
     * Renderiza um campo select a partir de uma lista de opções.
     *
     * @param string $grupo   Grupo da opção.
     * @param string $campo   Nome do campo.
     * @param array  $choices Array associativo (valor => rótulo).
     *
     * @return void
     */
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

    /**
     * Renderiza um campo de cor (seletor visual do WordPress), usado na
     * aba "Aparência" para personalizar campos e botões das caixas de
     * frete. O JavaScript que ativa o seletor visual (wp-color-picker)
     * é enfileirado em enqueue_admin_assets().
     *
     * @param string $grupo Grupo da opção (sempre "appearance" atualmente).
     * @param string $campo Nome do campo.
     *
     * @return void
     */
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

    /**
     * Renderiza a tabela de seleção e priorização das APIs de CEP.
     *
     * @return void
     */
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

    /**
     * Renderiza o botão de limpeza manual de cache (AJAX).
     *
     * @return void
     */
    public function render_clear_cache_button()
    {
        ?>
        <button type="button" class="button" id="autocep-clear-cache-btn">
            <?php esc_html_e('Limpar Cache de CEPs', 'autocep'); ?>
        </button>
        <span id="autocep-clear-cache-feedback" style="margin-left:10px;"></span>
        <?php
    }

    /**
     * Callback de sanitização das opções. Como cada aba envia apenas os
     * seus próprios campos, o resultado é mesclado com as opções já
     * salvas para não sobrescrever as demais abas.
     *
     * @param array $input Dados brutos enviados pelo formulário.
     *
     * @return array
     */
    public function sanitize_options($input)
    {
        $existentes = get_option(AUTOCEP_OPTION_KEY, self::get_default_options());
        $input      = is_array($input) ? $input : array();

        if (isset($input['general'])) {
            $existentes['general']['enable_billing']       = !empty($input['general']['enable_billing']) ? 1 : 0;
            $existentes['general']['enable_shipping']      = !empty($input['general']['enable_shipping']) ? 1 : 0;
            $existentes['general']['auto_focus']           = !empty($input['general']['auto_focus']) ? 1 : 0;
            $existentes['general']['browser_autocomplete'] = !empty($input['general']['browser_autocomplete']) ? 1 : 0;

            if (isset($input['general']['neighborhood_field'])) {
                $existentes['general']['neighborhood_field'] = sanitize_text_field($input['general']['neighborhood_field']);
            }

            if (isset($input['general']['cache_days'])) {
                $existentes['general']['cache_days'] = max(0, min(365, (int) $input['general']['cache_days']));
            }

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

        if (isset($input['messages'])) {
            foreach (array('searching', 'not_found', 'calculating', 'invalid_cep', 'generic_error', 'select_variation', 'no_street', 'select_rate', 'selecting_rate', 'rate_selected') as $campo) {
                if (isset($input['messages'][$campo])) {
                    $existentes['messages'][$campo] = sanitize_text_field($input['messages'][$campo]);
                }
            }

            $existentes['messages']['block_checkout_button'] = !empty($input['messages']['block_checkout_button']) ? 1 : 0;
        }

        if (isset($input['appearance'])) {
            foreach (array('field_bg_color', 'field_border_color', 'field_text_color', 'button_bg_color', 'button_text_color', 'button_hover_bg_color') as $campo) {
                if (isset($input['appearance'][$campo]) && preg_match('/^#[0-9a-fA-F]{3,6}$/', $input['appearance'][$campo])) {
                    $existentes['appearance'][$campo] = sanitize_text_field($input['appearance'][$campo]);
                }
            }
        }

        return $existentes;
    }

    /**
     * Enfileira os assets (CSS/JS inline) usados exclusivamente na tela
     * de administração do plugin.
     *
     * @param string $hook Hook da página atual do admin.
     *
     * @return void
     */
    public function enqueue_admin_assets($hook)
    {
        if ('toplevel_page_autocep' !== $hook) {
            return;
        }

        wp_enqueue_script('jquery');

        // Seletor de cores nativo do WordPress, usado na aba "Aparência"
        // para personalizar as cores dos campos e botões das caixas de frete.
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

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
            });
        ";

        wp_add_inline_script('jquery', $admin_js);

        wp_add_inline_style('common', '
            .autocep-nav-tabs { margin-bottom: 20px; }
            .autocep-apis-table th, .autocep-apis-table td { padding: 8px 10px; }
            .autocep-diagnostics-actions { margin: 15px 0; }
            .autocep-logs-table td, .autocep-logs-table th { padding: 6px 10px; font-size: 13px; }
        ');
    }

    /**
     * Renderiza a página principal do painel, com a navegação por abas.
     *
     * @return void
     */
    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab_atual = isset($_GET['tab']) && in_array($_GET['tab'], self::TABS, true)
            ? sanitize_key($_GET['tab'])
            : 'general';

        $abas = array(
            'general'            => __('Geral & Busca de CEP', 'autocep'),
            'product_shipping'   => __('Frete na Página de Produto', 'autocep'),
            'cart_shipping'      => __('Frete no Carrinho', 'autocep'),
            'checkout_shipping'  => __('Frete no Checkout', 'autocep'),
            'messages'           => __('Mensagens & UX', 'autocep'),
            'appearance'         => __('Aparência', 'autocep'),
            'diagnostics'        => __('Diagnóstico e Logs', 'autocep'),
        );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AutoCEP — Configurações', 'autocep'); ?></h1>

            <h2 class="nav-tab-wrapper autocep-nav-tabs">
                <?php foreach ($abas as $slug => $rotulo) : ?>
                    <a
                        href="<?php echo esc_url(admin_url('admin.php?page=autocep&tab=' . $slug)); ?>"
                        class="nav-tab <?php echo $tab_atual === $slug ? 'nav-tab-active' : ''; ?>"
                    >
                        <?php echo esc_html($rotulo); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php if ('diagnostics' === $tab_atual) : ?>
                <?php $this->render_diagnostics_tab(); ?>
            <?php else : ?>
                <form action="options.php" method="post">
                    <?php
                    settings_fields('autocep_options_group');
                    do_settings_sections('autocep_tab_' . $tab_atual);
                    submit_button();
                    ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderiza a aba de Diagnóstico e Logs (sem Settings API, pois não
     * possui campos de opção — apenas ações e listagem).
     *
     * @return void
     */
    private function render_diagnostics_tab()
    {
        $logs = AutoCEP_Logger::get_logs(50);

        ?>
        <div class="autocep-diagnostics">
            <h2><?php esc_html_e('Status das APIs de CEP', 'autocep'); ?></h2>
            <div class="autocep-diagnostics-actions">
                <button type="button" class="button button-primary" id="autocep-check-status-btn">
                    <?php esc_html_e('Verificar Status Agora', 'autocep'); ?>
                </button>
            </div>
            <div id="autocep-status-results"></div>

            <hr />

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
}
