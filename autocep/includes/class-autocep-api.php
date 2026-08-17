<?php
/**
 * Classe responsável pela consulta de CEP com fallback e cache.
 *
 * @package AutoCEP
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gerencia as requisições às APIs externas de CEP (ViaCEP, BrasilAPI e
 * ApiCEP), executando-as em cascata conforme a prioridade configurada
 * no painel administrativo, com cache via Transients API.
 */
class AutoCEP_Api
{
    /**
     * Prefixo utilizado nas chaves de transient de cache de CEP.
     *
     * @var string
     */
    const CACHE_PREFIX = 'autocep_cep_';

    /**
     * Timeout (em segundos) aplicado às requisições HTTP externas.
     *
     * @var int
     */
    const REQUEST_TIMEOUT = 8;

    /**
     * Instância do logger, usada para registrar falhas de API.
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

        // Ações AJAX de front-end (mantém a ação legada "autocep_get_address"
        // para preservar total compatibilidade com integrações existentes).
        add_action('wp_ajax_autocep_get_address', array($this, 'ajax_get_address'));
        add_action('wp_ajax_nopriv_autocep_get_address', array($this, 'ajax_get_address'));

        // Ação administrativa para limpar o cache de CEPs.
        add_action('wp_ajax_autocep_clear_cache', array($this, 'ajax_clear_cache'));
    }

    /**
     * Retorna a lista de APIs suportadas pelo plugin, com metadados
     * usados tanto no painel administrativo quanto no motor de consulta.
     *
     * @return array
     */
    public static function get_supported_apis()
    {
        return array(
            'viacep' => array(
                'label' => __('ViaCEP', 'autocep'),
                'url'   => __('https://viacep.com.br', 'autocep'),
            ),
            'brasilapi' => array(
                'label' => __('BrasilAPI', 'autocep'),
                'url'   => __('https://brasilapi.com.br', 'autocep'),
            ),
            'apicep' => array(
                'label' => __('ApiCEP', 'autocep'),
                'url'   => __('https://apicep.com', 'autocep'),
            ),
        );
    }

    /**
     * Handler AJAX para busca de endereço por CEP. Mantido com a mesma
     * assinatura da versão legada (ação "autocep_get_address") para não
     * quebrar nenhuma integração já existente.
     *
     * @return void
     */
    public function ajax_get_address()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), AUTOCEP_NONCE_ACTION)) {
            wp_send_json_error(array('message' => __('Nonce inválida ou ausente.', 'autocep')));
        }

        if (empty($_POST['cep'])) {
            wp_send_json_error(array('message' => __('CEP não informado.', 'autocep')));
        }

        $cep = preg_replace('/\D/', '', sanitize_text_field(wp_unslash($_POST['cep'])));

        if (!preg_match('/^[0-9]{8}$/', $cep)) {
            wp_send_json_error(array('message' => __('CEP inválido.', 'autocep')));
        }

        $endereco = self::find_address($cep, $this->logger);

        if (is_wp_error($endereco)) {
            wp_send_json_error(array('message' => $endereco->get_error_message()));
        }

        wp_send_json_success($endereco);
    }

    /**
     * Busca o endereço de um CEP, primeiro no cache (Transients) e,
     * caso não exista, executa a cascata de APIs habilitadas conforme
     * a prioridade configurada.
     *
     * @param string              $cep    CEP com 8 dígitos, apenas números.
     * @param AutoCEP_Logger|null $logger Instância do logger (opcional).
     *
     * @return array|WP_Error Dados normalizados do endereço ou erro.
     */
    public static function find_address($cep, $logger = null)
    {
        $cache_key = self::CACHE_PREFIX . $cep;
        $cached    = get_transient($cache_key);

        if (false !== $cached) {
            return $cached;
        }

        $options       = AutoCEP_Admin::get_options();
        $apis_ativas   = self::get_ordered_active_apis($options);

        if (empty($apis_ativas)) {
            return new WP_Error('autocep_no_api', __('Nenhuma API de CEP está habilitada.', 'autocep'));
        }

        $ultimo_erro = null;

        foreach ($apis_ativas as $slug) {
            $resultado = self::request_provider($slug, $cep);

            if (is_wp_error($resultado)) {
                $ultimo_erro = $resultado;

                if ($logger) {
                    $logger->log($slug, $cep, $resultado->get_error_message(), 'error');
                }

                continue; // Tenta a próxima API da cascata.
            }

            if (empty($resultado)) {
                $ultimo_erro = new WP_Error('autocep_not_found', __('CEP não encontrado.', 'autocep'));

                if ($logger) {
                    $logger->log($slug, $cep, __('CEP não encontrado nesta API.', 'autocep'), 'warning');
                }

                continue;
            }

            // Sucesso: grava em cache e retorna.
            $dias_cache = isset($options['general']['cache_days']) ? (int) $options['general']['cache_days'] : 30;
            set_transient($cache_key, $resultado, DAY_IN_SECONDS * max(0, $dias_cache));

            return $resultado;
        }

        return $ultimo_erro ? $ultimo_erro : new WP_Error('autocep_not_found', __('CEP não encontrado.', 'autocep'));
    }

    /**
     * Retorna a lista de slugs de APIs habilitadas, ordenada pela
     * prioridade configurada (menor número = maior prioridade).
     *
     * @param array $options Opções do plugin.
     *
     * @return array
     */
    public static function get_ordered_active_apis($options)
    {
        $apis = isset($options['general']['apis']) ? $options['general']['apis'] : array();
        $ativas = array();

        foreach ($apis as $slug => $config) {
            if (!empty($config['enabled'])) {
                $ativas[$slug] = isset($config['priority']) ? (int) $config['priority'] : 99;
            }
        }

        asort($ativas);

        return array_keys($ativas);
    }

    /**
     * Executa a requisição a um provedor específico e normaliza a
     * resposta para o formato padrão do plugin.
     *
     * @param string $slug Identificador da API (viacep, brasilapi, apicep).
     * @param string $cep  CEP com 8 dígitos.
     *
     * @return array|WP_Error
     */
    public static function request_provider($slug, $cep)
    {
        switch ($slug) {
            case 'viacep':
                return self::fetch_viacep($cep);

            case 'brasilapi':
                return self::fetch_brasilapi($cep);

            case 'apicep':
                return self::fetch_apicep($cep);

            default:
                return new WP_Error('autocep_unknown_api', __('API de CEP desconhecida.', 'autocep'));
        }
    }

    /**
     * Consulta a API ViaCEP.
     *
     * @param string $cep CEP com 8 dígitos.
     *
     * @return array|WP_Error
     */
    private static function fetch_viacep($cep)
    {
        $response = wp_remote_get("https://viacep.com.br/ws/{$cep}/json/", array(
            'timeout' => self::REQUEST_TIMEOUT,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data) || !empty($data['erro'])) {
            return array();
        }

        return self::normalize(array(
            'logradouro' => $data['logradouro'] ?? '',
            'bairro'     => $data['bairro'] ?? '',
            'localidade' => $data['localidade'] ?? '',
            'uf'         => $data['uf'] ?? '',
        ), $cep);
    }

    /**
     * Consulta a API BrasilAPI (v2).
     *
     * @param string $cep CEP com 8 dígitos.
     *
     * @return array|WP_Error
     */
    private static function fetch_brasilapi($cep)
    {
        $response = wp_remote_get("https://brasilapi.com.br/api/cep/v2/{$cep}", array(
            'timeout' => self::REQUEST_TIMEOUT,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $codigo_http = wp_remote_retrieve_response_code($response);

        if (404 === (int) $codigo_http) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data) || empty($data['cep'])) {
            return array();
        }

        return self::normalize(array(
            'logradouro' => $data['street'] ?? '',
            'bairro'     => $data['neighborhood'] ?? '',
            'localidade' => $data['city'] ?? '',
            'uf'         => $data['state'] ?? '',
        ), $cep);
    }

    /**
     * Consulta a API ApiCEP.
     *
     * @param string $cep CEP com 8 dígitos.
     *
     * @return array|WP_Error
     */
    private static function fetch_apicep($cep)
    {
        $response = wp_remote_get("https://cdn.apicep.com/file/apicep/{$cep}.json", array(
            'timeout' => self::REQUEST_TIMEOUT,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data) || (isset($data['status']) && 400 === (int) $data['status'])) {
            return array();
        }

        return self::normalize(array(
            'logradouro' => $data['address'] ?? '',
            'bairro'     => $data['district'] ?? '',
            'localidade' => $data['city'] ?? '',
            'uf'         => $data['state'] ?? '',
        ), $cep);
    }

    /**
     * Normaliza os dados de um endereço para o formato padrão usado em
     * todo o plugin (compatível com o formato da versão legada).
     *
     * @param array  $data Dados brutos já mapeados (logradouro, bairro, localidade, uf).
     * @param string $cep  CEP consultado.
     *
     * @return array
     */
    private static function normalize($data, $cep)
    {
        if (empty($data['logradouro']) && empty($data['localidade'])) {
            return array();
        }

        return array(
            'cep'        => $cep,
            'logradouro' => sanitize_text_field($data['logradouro']),
            'bairro'     => sanitize_text_field($data['bairro']),
            'localidade' => sanitize_text_field($data['localidade']),
            'uf'         => sanitize_text_field($data['uf']),
        );
    }

    /**
     * Limpa todo o cache de CEPs armazenado via Transients API, a
     * pedido do administrador (botão "Limpar Cache de CEPs").
     *
     * @return void
     */
    public function ajax_clear_cache()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para executar esta ação.', 'autocep')));
        }

        global $wpdb;

        $prefixo_transient  = '_transient_' . self::CACHE_PREFIX;
        $prefixo_timeout    = '_transient_timeout_' . self::CACHE_PREFIX;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- consulta com LIKE preparada via wpdb->prepare.
        $total_removido = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like($prefixo_transient) . '%',
                $wpdb->esc_like($prefixo_timeout) . '%'
            )
        );

        wp_send_json_success(array(
            /* translators: %d: quantidade de registros removidos. */
            'message' => sprintf(__('Cache limpo com sucesso (%d registros removidos).', 'autocep'), (int) $total_removido),
        ));
    }
}
