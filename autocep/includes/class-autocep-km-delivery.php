<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Km_Delivery
{
    public static function is_enabled()
    {
        $options = AutoCEP_Admin::get_options();
        return !empty($options['km_delivery']['enabled']);
    }

    public static function get_config()
    {
        $options  = AutoCEP_Admin::get_options();
        $defaults = array(
            'enabled'       => 0,
            'title'         => __('Entrega Local / Motoboy', 'autocep'),
            'origin_cep'    => '',
            'base_fee'      => 5.00,
            'price_per_km'  => 2.00,
            'max_radius_km' => 20.0,
            'min_fee'       => 0.0,
            'round_km'      => 0,
        );

        $config = isset($options['km_delivery']) ? $options['km_delivery'] : array();
        return array_merge($defaults, $config);
    }

    public static function calculate($cep_destino)
    {
        if (!self::is_enabled()) {
            return null;
        }

        $config = self::get_config();

        $cep_destino = preg_replace('/\D/', '', (string) $cep_destino);
        if (empty($cep_destino)) {
            return null;
        }

        $cep_origem = preg_replace('/\D/', '', (string) $config['origin_cep']);
        if (empty($cep_origem)) {
            $cep_origem = preg_replace('/\D/', '', (string) get_option('woocommerce_store_postcode', ''));
        }
        if (empty($cep_origem)) {
            return null;
        }

        if (!class_exists('AutoCEP_Maps_Service')) {
            return null;
        }

        $servico   = new AutoCEP_Maps_Service();
        $distancia = $servico->get_distance_km($cep_origem, $cep_destino);

        if (is_wp_error($distancia)) {
            return null;
        }

        $raio_maximo = (float) $config['max_radius_km'];
        if ($distancia > $raio_maximo) {
            return null; 
        }

        $dist_calc = !empty($config['round_km']) ? ceil($distancia) : $distancia;

        $taxa_fixa    = (float) $config['base_fee'];
        $valor_por_km = (float) $config['price_per_km'];
        $valor_minimo = (float) $config['min_fee'];

        $custo = max(
            $taxa_fixa + ($dist_calc * $valor_por_km),
            $valor_minimo
        );

        return array(
            'id'             => 'autocep_km_delivery',
            'label'          => $config['title'],
            'cost'           => $custo,
            'cost_formatted' => wc_price($custo),
            'distance_km'    => round($distancia, 1),
            'delivery_estimate'   => '',
            'pickup_instructions' => '',
        );
    }
}
