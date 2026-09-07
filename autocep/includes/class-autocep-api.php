<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Api
{
    const CACHE_PREFIX = 'autocep_cep_';

    const REQUEST_TIMEOUT = 8;

    private $logger;

    public function __construct(AutoCEP_Logger $logger)
    {
        $this->logger = $logger;

        add_action('wp_ajax_autocep_get_address', array($this, 'ajax_get_address'));
        add_action('wp_ajax_nopriv_autocep_get_address', array($this, 'ajax_get_address'));

        add_action('wp_ajax_autocep_clear_cache', array($this, 'ajax_clear_cache'));
    }

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

                continue; 
            }

            if (empty($resultado)) {
                $ultimo_erro = new WP_Error('autocep_not_found', __('CEP não encontrado.', 'autocep'));

                if ($logger) {
                    $logger->log($slug, $cep, __('CEP não encontrado nesta API.', 'autocep'), 'warning');
                }

                continue;
            }

            $dias_cache = isset($options['general']['cache_days']) ? (int) $options['general']['cache_days'] : 30;
            set_transient($cache_key, $resultado, DAY_IN_SECONDS * max(0, $dias_cache));

            return $resultado;
        }

        return $ultimo_erro ? $ultimo_erro : new WP_Error('autocep_not_found', __('CEP não encontrado.', 'autocep'));
    }

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

    public function ajax_clear_cache()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para executar esta ação.', 'autocep')));
        }

        global $wpdb;

        $prefixo_transient  = '_transient_' . self::CACHE_PREFIX;
        $prefixo_timeout    = '_transient_timeout_' . self::CACHE_PREFIX;

        $total_removido = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like($prefixo_transient) . '%',
                $wpdb->esc_like($prefixo_timeout) . '%'
            )
        );

        $prefixo_transient_frete = '_transient_' . AutoCEP_Shipping::CACHE_PREFIX;
        $prefixo_timeout_frete   = '_transient_timeout_' . AutoCEP_Shipping::CACHE_PREFIX;

        $total_removido += (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like($prefixo_transient_frete) . '%',
                $wpdb->esc_like($prefixo_timeout_frete) . '%'
            )
        );

        wp_send_json_success(array(
            'message' => sprintf(__('Cache limpo com sucesso (%d registros removidos).', 'autocep'), (int) $total_removido),
        ));
    }
}
