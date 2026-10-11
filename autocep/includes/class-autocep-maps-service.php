<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Maps_Service
{
    const CACHE_TTL = 7 * DAY_IN_SECONDS; 

    const CACHE_PREFIX = 'autocep_km_dist_';

    // "Pausa" das consultas ao Google após uma falha de conexão/timeout.
    const PAUSE_KEY = 'autocep_maps_pausa';

    const PAUSE_SECONDS = 10 * MINUTE_IN_SECONDS;

    public $ultima_fonte = null;

    private $logger;

    public function __construct($logger = null)
    {
        if (null === $logger && class_exists('AutoCEP_Plugin')) {
            $instancia_plugin = AutoCEP_Plugin::get_instance();
            $logger           = isset($instancia_plugin->logger) ? $instancia_plugin->logger : null;
        }

        $this->logger = $logger;
    }

    public static function get_api_key()
    {
        $options = AutoCEP_Admin::get_options();

        return isset($options['distance_shipping']['google_maps_api_key'])
            ? trim($options['distance_shipping']['google_maps_api_key'])
            : '';
    }

    public function get_distance_km($cep_origem, $cep_destino)
    {
        $cep_origem  = preg_replace('/\D/', '', (string) $cep_origem);
        $cep_destino = preg_replace('/\D/', '', (string) $cep_destino);

        if (empty($cep_origem) || empty($cep_destino)) {
            return new WP_Error('autocep_maps_missing_cep', __('CEP de origem ou destino ausente para o cálculo de distância.', 'autocep'));
        }

        if ($cep_origem === $cep_destino) {
            $this->ultima_fonte = 'identico';

            return 0.0;
        }

        $chave_cache = self::CACHE_PREFIX . md5('km_dist_' . $cep_origem . '_' . $cep_destino);
        $em_cache    = get_transient($chave_cache);

        if (false !== $em_cache) {
            if (is_array($em_cache) && isset($em_cache['km'])) {
                $this->ultima_fonte = isset($em_cache['fonte']) ? $em_cache['fonte'] : 'cache';

                return (float) $em_cache['km'];
            }

            $this->ultima_fonte = 'cache';

            return (float) $em_cache;
        }

        $api_key = self::get_api_key();

        if (empty($api_key)) {
            return new WP_Error('autocep_maps_no_key', __('A Chave de API do Google Maps não está configurada em AutoCEP > Retirada em Loja.', 'autocep'));
        }

        // Google em pausa (falhou por conexão há pouco): vai direto para a
        // estimativa por linha reta, sem esperar novos timeouts.
        $google_em_pausa = (bool) get_transient(self::PAUSE_KEY);

        if ($google_em_pausa) {
            $resultado = new WP_Error('autocep_maps_paused', __('Google Maps temporariamente em pausa após falha de conexão.', 'autocep'));
        } else {
            $resultado = $this->consultar_distancia(
                $this->build_geocoding_query($cep_origem),
                $this->build_geocoding_query($cep_destino),
                $api_key,
                $cep_origem,
                $cep_destino
            );
        }

        if (!is_wp_error($resultado)) {
            $this->ultima_fonte = 'google_maps_endereco';
        }

        // A segunda tentativa (só cidade) resolve CEPs que o Google não
        // encontra, mas não adianta se a falha foi de conexão.
        if (is_wp_error($resultado) && !$google_em_pausa && 'http_request_failed' !== $resultado->get_error_code()) {
            $query_origem_simples  = $this->build_city_query($cep_origem);
            $query_destino_simples = $this->build_city_query($cep_destino);

            $resultado_simples = $this->consultar_distancia(
                $query_origem_simples,
                $query_destino_simples,
                $api_key,
                $cep_origem,
                $cep_destino
            );

            if (!is_wp_error($resultado_simples)) {
                $resultado = $resultado_simples;
                $this->ultima_fonte = 'google_maps_cidade';
            }
        }

        if (is_wp_error($resultado)) {
            $resultado_haversine = $this->haversine_fallback($cep_origem, $cep_destino);

            if (!is_wp_error($resultado_haversine)) {
                $resultado = $resultado_haversine;
                $this->ultima_fonte = 'haversine';
            }
        }

        if (!is_wp_error($resultado)) {
            set_transient($chave_cache, array('km' => $resultado, 'fonte' => $this->ultima_fonte), self::CACHE_TTL);
        }

        return $resultado;
    }

    private function haversine_fallback($cep_origem, $cep_destino)
    {
        $coords_a = $this->get_city_coords($cep_origem);
        $coords_b = $this->get_city_coords($cep_destino);

        if (is_wp_error($coords_a)) {
            return $coords_a;
        }

        if (is_wp_error($coords_b)) {
            return $coords_b;
        }

        $lat1 = deg2rad((float) $coords_a['lat']);
        $lon1 = deg2rad((float) $coords_a['lon']);
        $lat2 = deg2rad((float) $coords_b['lat']);
        $lon2 = deg2rad((float) $coords_b['lon']);

        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
        $c = 2 * asin(sqrt($a));

        $raio_terra_km = 6371.0;
        $km_linha_reta = $raio_terra_km * $c;

        $km_estimado = round($km_linha_reta * 1.3, 2);

        $this->log_falha(
            sprintf(
                'Google Maps falhou — usando Haversine como fallback: %.2f km em linha reta × 1.3 = %.2f km estimados.',
                $km_linha_reta,
                $km_estimado
            ),
            $cep_origem,
            $cep_destino
        );

        return $km_estimado;
    }

    private function get_city_coords($cep)
    {
        if (!class_exists('AutoCEP_Api')) {
            return new WP_Error('autocep_haversine_no_api', 'AutoCEP_Api não disponível.');
        }

        $endereco = AutoCEP_Api::find_address($cep, $this->logger);

        if (is_wp_error($endereco) || empty($endereco['localidade']) || empty($endereco['uf'])) {
            return new WP_Error('autocep_haversine_no_address', 'Cidade não encontrada para CEP ' . $cep);
        }

        $chave_coords = 'autocep_coords_' . md5($endereco['localidade'] . $endereco['uf']);
        $em_cache     = get_transient($chave_coords);

        if (false !== $em_cache) {
            return $em_cache;
        }

        $query = $endereco['localidade'] . ', ' . $endereco['uf'] . ', Brasil';

        $url = add_query_arg(
            array(
                'q'              => $query,
                'format'         => 'json',
                'limit'          => '1',
                'countrycodes'   => 'br',
                'addressdetails' => '0',
            ),
            'https://nominatim.openstreetmap.org/search'
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 5,
                'headers' => array(
                    'User-Agent' => 'AutoCEP-Plugin/2.7 (WooCommerce shipping plugin; contact: autocep)',
                ),
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('autocep_nominatim_error', 'Nominatim: ' . $response->get_error_message());
        }

        $dados = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($dados) || !isset($dados[0]['lat'], $dados[0]['lon'])) {
            return new WP_Error('autocep_nominatim_not_found', 'Nominatim: cidade não encontrada para "' . $query . '"');
        }

        $coords = array(
            'lat' => (float) $dados[0]['lat'],
            'lon' => (float) $dados[0]['lon'],
        );

        set_transient($chave_coords, $coords, 30 * DAY_IN_SECONDS);

        return $coords;
    }

    private function consultar_distancia($query_origem, $query_destino, $api_key, $cep_origem_log, $cep_destino_log)
    {
        $url = add_query_arg(
            array(
                'origins'      => rawurlencode($query_origem),
                'destinations' => rawurlencode($query_destino),
                'mode'         => 'driving',
                'language'     => 'pt-BR',
                'key'          => $api_key,
            ),
            'https://maps.googleapis.com/maps/api/distancematrix/json'
        );

        $response = wp_remote_get($url, array('timeout' => 6));

        if (is_wp_error($response)) {
            // Google fora do ar/lento: pausa as consultas por alguns minutos
            // e usa direto a estimativa, sem cada cliente esperar o timeout.
            set_transient(self::PAUSE_KEY, 1, self::PAUSE_SECONDS);

            $this->log_falha('Falha de conexão com a API do Google Maps: ' . $response->get_error_message(), $cep_origem_log, $cep_destino_log);

            return $response;
        }

        $codigo_http = (int) wp_remote_retrieve_response_code($response);
        $corpo       = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $codigo_http || empty($corpo)) {
            $this->log_falha(sprintf('Resposta HTTP inválida da API do Google Maps (código %d).', $codigo_http), $cep_origem_log, $cep_destino_log);

            return new WP_Error('autocep_maps_bad_response', __('Não foi possível calcular a distância no momento. Tente novamente em instantes.', 'autocep'));
        }

        $status_api = isset($corpo['status']) ? $corpo['status'] : 'UNKNOWN';

        if ('OK' !== $status_api) {
            $mensagem_status = sprintf(
                'Status retornado pela API do Google Maps: %1$s (consulta: "%2$s" → "%3$s").',
                $status_api,
                $query_origem,
                $query_destino
            );

            $this->log_falha($mensagem_status, $cep_origem_log, $cep_destino_log);

            return new WP_Error(
                'autocep_maps_status_' . strtolower($status_api),
                sprintf('Google Maps API status: %s — %s', $status_api, $mensagem_status)
            );
        }

        $elemento        = isset($corpo['rows'][0]['elements'][0]) ? $corpo['rows'][0]['elements'][0] : null;
        $status_elemento = isset($elemento['status']) ? $elemento['status'] : 'UNKNOWN';

        if (empty($elemento) || 'OK' !== $status_elemento) {
            $mensagem_elemento = sprintf(
                'Elemento de rota inválido (status: %1$s, consulta: "%2$s" → "%3$s").',
                $status_elemento,
                $query_origem,
                $query_destino
            );

            $this->log_falha($mensagem_elemento, $cep_origem_log, $cep_destino_log);

            return new WP_Error(
                'autocep_maps_element_status',
                sprintf('Google Maps route status: %s — %s', $status_elemento, $mensagem_elemento)
            );
        }

        $metros = isset($elemento['distance']['value']) ? (float) $elemento['distance']['value'] : null;

        if (null === $metros) {
            return new WP_Error('autocep_maps_no_distance', __('A API do Google Maps não retornou uma distância válida.', 'autocep'));
        }

        return round($metros / 1000, 2);
    }

    private function build_geocoding_query($cep)
    {
        if (!class_exists('AutoCEP_Api')) {
            return $cep . ', Brasil';
        }

        $endereco = AutoCEP_Api::find_address($cep, $this->logger);

        if (is_wp_error($endereco) || empty($endereco['localidade'])) {
            return $cep . ', Brasil';
        }

        $cidade_uf = $endereco['localidade'] . (!empty($endereco['uf']) ? ' - ' . $endereco['uf'] : '');

        $partes = array_filter(
            array(
                !empty($endereco['logradouro']) ? $endereco['logradouro'] : '',
                !empty($endereco['bairro']) ? $endereco['bairro'] : '',
                $cidade_uf,
                'Brasil',
            )
        );

        return implode(', ', $partes);
    }

    private function build_city_query($cep)
    {
        if (!class_exists('AutoCEP_Api')) {
            return $cep . ', Brasil';
        }

        $endereco = AutoCEP_Api::find_address($cep, $this->logger);

        if (is_wp_error($endereco) || empty($endereco['localidade'])) {
            return $cep . ', Brasil';
        }

        $cidade_uf = $endereco['localidade'] . (!empty($endereco['uf']) ? ' - ' . $endereco['uf'] : '');

        return $cidade_uf . ', Brasil';
    }

    private function log_falha($mensagem, $cep_origem = '', $cep_destino = '')
    {
        if ($this->logger) {
            $this->logger->log('google-maps', $cep_destino, $mensagem . ' (origem: ' . $cep_origem . ')', 'warning');
        }

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->warning($mensagem, array('source' => 'autocep-maps'));
        }
    }
}
