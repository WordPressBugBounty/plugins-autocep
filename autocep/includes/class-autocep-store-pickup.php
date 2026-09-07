<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Store_Pickup
{
    const OPTION_KEY = 'autocep_stores';

    private $logger;

    public function __construct($logger = null)
    {
        $this->logger = $logger;

        add_action('wp_ajax_autocep_save_store', array($this, 'ajax_save_store'));
        add_action('wp_ajax_autocep_delete_store', array($this, 'ajax_delete_store'));
    }

    public static function get_stores()
    {
        $lojas = get_option(self::OPTION_KEY, array());

        return is_array($lojas) ? $lojas : array();
    }

    public function ajax_save_store()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para executar esta ação.', 'autocep')));
        }

        $id            = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';
        $nome          = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $cep           = isset($_POST['cep']) ? preg_replace('/\D/', '', wp_unslash($_POST['cep'])) : '';
        $instrucoes    = isset($_POST['instructions']) ? sanitize_textarea_field(wp_unslash($_POST['instructions'])) : '';
        $raio_km       = isset($_POST['radius_km']) ? (float) $_POST['radius_km'] : 0;
        $taxa_retirada = isset($_POST['pickup_fee']) ? (float) $_POST['pickup_fee'] : 0;
        $ceps_manuais  = isset($_POST['manual_ceps']) ? sanitize_textarea_field(wp_unslash($_POST['manual_ceps'])) : '';

        if ('' === $nome || '' === $cep) {
            wp_send_json_error(array('message' => __('Preencha ao menos o nome e o CEP da loja.', 'autocep')));
        }

        if (8 !== strlen($cep)) {
            wp_send_json_error(array('message' => __('CEP da loja inválido — informe os 8 dígitos.', 'autocep')));
        }

        $raio_km       = max(0.1, $raio_km ?: 10.0);
        $taxa_retirada = max(0.0, $taxa_retirada);

        $dados_loja = array(
            'name'         => $nome,
            'cep'          => $cep,
            'instructions' => $instrucoes,
            'radius_km'    => $raio_km,
            'pickup_fee'   => $taxa_retirada,
            'manual_ceps'  => $ceps_manuais,
        );

        $lojas = self::get_stores();

        if ('' === $id) {
            $dados_loja['id'] = 'loja_' . substr(md5(uniqid('autocep_store_', true)), 0, 12);
            $lojas[]          = $dados_loja;
        } else {
            $encontrada = false;

            foreach ($lojas as $indice => $loja_existente) {
                if (isset($loja_existente['id']) && $loja_existente['id'] === $id) {
                    $dados_loja['id'] = $id;
                    $lojas[$indice]   = $dados_loja;
                    $encontrada       = true;
                    break;
                }
            }

            if (!$encontrada) {
                $dados_loja['id'] = 'loja_' . substr(md5(uniqid('autocep_store_', true)), 0, 12);
                $lojas[]          = $dados_loja;
            }
        }

        update_option(self::OPTION_KEY, array_values($lojas));

        self::clear_distance_cache();

        wp_send_json_success(array('stores' => self::get_stores()));
    }

    public function ajax_delete_store()
    {
        check_ajax_referer(AUTOCEP_ADMIN_NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para executar esta ação.', 'autocep')));
        }

        $id = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';

        $lojas_restantes = array_values(
            array_filter(
                self::get_stores(),
                function ($loja) use ($id) {
                    return !isset($loja['id']) || $loja['id'] !== $id;
                }
            )
        );

        update_option(self::OPTION_KEY, $lojas_restantes);

        wp_send_json_success(array('stores' => $lojas_restantes));
    }

    private static function clear_distance_cache()
    {
        global $wpdb;

        $prefixo_transient = '_transient_' . AutoCEP_Maps_Service::CACHE_PREFIX;
        $prefixo_timeout   = '_transient_timeout_' . AutoCEP_Maps_Service::CACHE_PREFIX;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like($prefixo_transient) . '%',
                $wpdb->esc_like($prefixo_timeout) . '%'
            )
        );

        if (class_exists('AutoCEP_Shipping')) {
            $prefixo_transient_frete = '_transient_' . AutoCEP_Shipping::CACHE_PREFIX;
            $prefixo_timeout_frete   = '_transient_timeout_' . AutoCEP_Shipping::CACHE_PREFIX;

            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                    $wpdb->esc_like($prefixo_transient_frete) . '%',
                    $wpdb->esc_like($prefixo_timeout_frete) . '%'
                )
            );
        }
    }

    public static function get_nearby_stores($cep_destino)
    {
        $options = AutoCEP_Admin::get_options();

        if (empty($options['distance_shipping']['pickup_enabled'])) {
            return array();
        }

        $cep_destino = preg_replace('/\D/', '', (string) $cep_destino);

        if (empty($cep_destino)) {
            return array();
        }

        $lojas = self::get_stores();

        if (empty($lojas)) {
            return array();
        }

        $servico_mapas = new AutoCEP_Maps_Service();
        $lojas_no_raio = array();

        foreach ($lojas as $loja) {
            if (empty($loja['cep']) || empty($loja['id'])) {
                continue;
            }

            $cobertura_manual = self::store_matches_manual_cep($loja, $cep_destino);

            if ($cobertura_manual) {
                $distancia       = $servico_mapas->get_distance_km($loja['cep'], $cep_destino);
                $lojas_no_raio[] = array_merge(
                    $loja,
                    array('distance_km' => is_wp_error($distancia) ? null : $distancia)
                );
                continue;
            }

            $distancia = $servico_mapas->get_distance_km($loja['cep'], $cep_destino);

            if (is_wp_error($distancia)) {
                continue;
            }

            $raio_maximo = isset($loja['radius_km']) ? (float) $loja['radius_km'] : 0;

            if ($distancia <= $raio_maximo) {
                $lojas_no_raio[] = array_merge($loja, array('distance_km' => $distancia));
            }
        }

        usort(
            $lojas_no_raio,
            function ($a, $b) {
                if (null === $a['distance_km'] && null === $b['distance_km']) {
                    return 0;
                }

                if (null === $a['distance_km']) {
                    return -1;
                }

                if (null === $b['distance_km']) {
                    return 1;
                }

                return $a['distance_km'] <=> $b['distance_km'];
            }
        );

        return $lojas_no_raio;
    }

    private static function store_matches_manual_cep($loja, $cep_destino)
    {
        if (empty($loja['manual_ceps'])) {
            return false;
        }

        $entradas = preg_split('/[\r\n,;]+/', (string) $loja['manual_ceps'], -1, PREG_SPLIT_NO_EMPTY);

        foreach ($entradas as $entrada) {
            $prefixo = preg_replace('/\D/', '', $entrada);

            if ('' !== $prefixo && 0 === strpos($cep_destino, $prefixo)) {
                return true;
            }
        }

        return false;
    }
}
