<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Shipping_Store_Pickup extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id                 = 'autocep_store_pickup';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Retirada em Loja por Proximidade — AutoCEP', 'autocep');
        $this->method_description = __('Exibe automaticamente, como opções de frete, as lojas físicas cadastradas em AutoCEP > Retirada em Loja que estejam dentro do raio configurado para o CEP do cliente — inclusive lojas de cidades vizinhas, calculado por distância rodoviária real (Google Maps), não apenas por CEP idêntico.', 'autocep');
        $this->supports            = array('shipping-zones', 'instance-settings');

        $this->init();
    }

    public function init()
    {
        // O WooCommerce monta a janela de configuração a partir desta propriedade.
        $this->instance_form_fields = $this->get_fields_definition();
        $this->init_instance_settings();

        $this->title   = $this->get_option('title') ?: $this->method_title;
        $this->enabled = $this->get_option('enabled');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    private function get_fields_definition()
    {
        return array(
            'enabled' => array(
                'title'   => __('Habilitar/Desabilitar', 'autocep'),
                'type'    => 'checkbox',
                'label'   => __('Habilitar este método de envio', 'autocep'),
                'default' => 'yes',
            ),
            'title' => array(
                'title'       => __('Título (referência interna)', 'autocep'),
                'type'        => 'text',
                'description' => __('Usado apenas internamente — cada loja próxima aparece ao cliente com seu próprio nome e distância, não com este título.', 'autocep'),
                'default'     => __('Retirada em Loja', 'autocep'),
                'desc_tip'    => true,
            ),
            'info' => array(
                'title'       => __('Lojas Cadastradas', 'autocep'),
                'type'        => 'title',
                'description' => __('As lojas físicas (nome, CEP, raio de retirada, taxa) são cadastradas em AutoCEP > Retirada em Loja e valem automaticamente para toda zona onde este método estiver habilitado.', 'autocep'),
            ),
        );
    }

    public function calculate_shipping($package = array())
    {
        $cep_destino = isset($package['destination']['postcode'])
            ? preg_replace('/\D/', '', $package['destination']['postcode'])
            : '';

        if (empty($cep_destino) || !class_exists('AutoCEP_Store_Pickup')) {
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('wc_get_logger')) {
            wc_get_logger()->debug(
                sprintf(
                    'AutoCEP Retirada: calculate_shipping chamado com CEP="%s", state="%s"',
                    $cep_destino,
                    isset($package['destination']['state']) ? $package['destination']['state'] : '(vazio)'
                ),
                array('source' => 'autocep-pickup')
            );
        }

        $lojas_proximas = AutoCEP_Store_Pickup::get_nearby_stores($cep_destino);

        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('wc_get_logger')) {
            wc_get_logger()->debug(
                sprintf(
                    'AutoCEP Retirada: get_nearby_stores(%s) retornou %d loja(s)',
                    $cep_destino,
                    count($lojas_proximas)
                ),
                array('source' => 'autocep-pickup')
            );
        }

        foreach ($lojas_proximas as $loja) {
            $gratis = empty($loja['pickup_fee']) || (float) $loja['pickup_fee'] <= 0;

            if (null === $loja['distance_km']) {
                $label = $gratis
                    ? sprintf(__('Retire Grátis na %s', 'autocep'), $loja['name'])
                    : sprintf(__('Retire na %s', 'autocep'), $loja['name']);
            } else {
                $distancia_formatada = number_format((float) $loja['distance_km'], 1, ',', '.');

                $label = $gratis
                    ? sprintf(__('Retire Grátis na %1$s (a %2$s km de você)', 'autocep'), $loja['name'], $distancia_formatada)
                    : sprintf(__('Retire na %1$s (a %2$s km de você)', 'autocep'), $loja['name'], $distancia_formatada);
            }

            $this->add_rate(
                array(
                    'id'        => $this->get_rate_id($loja['id']),
                    'label'     => $label,
                    'cost'      => (float) $loja['pickup_fee'],
                    'meta_data' => array(
                        '_autocep_pickup_instructions' => $loja['instructions'],
                        '_autocep_pickup_distance_km'  => $loja['distance_km'],
                    ),
                )
            );
        }
    }
}
