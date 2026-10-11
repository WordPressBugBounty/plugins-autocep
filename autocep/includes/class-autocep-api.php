<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Api
{
    const CACHE_PREFIX = 'autocep_cep_';

    const REQUEST_TIMEOUT = 5;

    // Prefixo e duração da "pausa" de uma API que falhou por conexão/timeout.
    const PAUSE_PREFIX = 'autocep_api_pausa_';

    const PAUSE_SECONDS = 5 * MINUTE_IN_SECONDS;

    private $logger;

    public function __construct(AutoCEP_Logger $logger)
    {
        $this->logger = $logger;

        add_action('wp_ajax_autocep_get_address', array($this, 'ajax_get_address'));
        add_action('wp_ajax_nopriv_autocep_get_address', array($this, 'ajax_get_address'));

        add_action('wp_ajax_autocep_clear_cache', array($this, 'ajax_clear_cache'));

        // Sugestões de endereço enquanto o cliente digita a rua.
        add_action('wp_ajax_autocep_address_suggest', array($this, 'ajax_address_suggest'));
        add_action('wp_ajax_nopriv_autocep_address_suggest', array($this, 'ajax_address_suggest'));
        add_action('wp_ajax_autocep_address_details', array($this, 'ajax_address_details'));
        add_action('wp_ajax_nopriv_autocep_address_details', array($this, 'ajax_address_details'));
    }

    /**
     * Limite de uso por visitante (por IP) numa janela fixa de tempo.
     *
     * Protege os endpoints públicos que consultam serviços externos (ViaCEP,
     * Google — este é cobrado por consulta) ou gravam no banco: sem o limite,
     * qualquer robô poderia usar a chave de segurança da página (que é
     * pública) para gerar milhares de consultas. Os limites padrão são bem
     * acima do uso humano normal; use o filtro "autocep_rate_limit" para
     * ajustar (retornar 0 desliga o limite de um grupo).
     *
     * @param string $grupo   Nome do grupo de endpoints.
     * @param int    $limite  Máximo de chamadas por janela.
     * @param int    $janela  Tamanho da janela em segundos.
     * @return bool true se ainda pode usar; false se passou do limite.
     */
    public static function throttle($grupo, $limite, $janela = 60)
    {
        $limite = (int) apply_filters('autocep_rate_limit', (int) $limite, $grupo);

        if ($limite <= 0) {
            return true;
        }

        $ip    = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $chave = 'autocep_rl_' . md5($grupo . '|' . $ip);
        $agora = time();
        $dados = get_transient($chave);

        if (!is_array($dados) || !isset($dados['n'], $dados['t']) || $agora - (int) $dados['t'] >= $janela) {
            $dados = array('n' => 0, 't' => $agora);
        }

        if ($dados['n'] >= $limite) {
            return false;
        }

        $dados['n']++;
        set_transient($chave, $dados, max(1, $janela - ($agora - (int) $dados['t'])));

        return true;
    }

    /**
     * Estado (UF) de um CEP pelas faixas oficiais dos Correios. Não depende
     * de nenhuma API: funciona até com as APIs fora do ar e evita que o
     * estado de uma busca anterior fique "grudado" num CEP novo (ex.: CEP do
     * Piauí aparecendo como Paraíba).
     *
     * @param string $cep CEP com ou sem máscara.
     * @return string UF ou '' se o CEP for inválido.
     */
    public static function uf_from_cep($cep)
    {
        $cep = preg_replace('/\D/', '', (string) $cep);

        if (8 !== strlen($cep)) {
            return '';
        }

        $prefixo = (int) substr($cep, 0, 5);

        $faixas = array(
            array(1000, 19999, 'SP'), array(20000, 28999, 'RJ'), array(29000, 29999, 'ES'),
            array(30000, 39999, 'MG'), array(40000, 48999, 'BA'), array(49000, 49999, 'SE'),
            array(50000, 56999, 'PE'), array(57000, 57999, 'AL'), array(58000, 58999, 'PB'),
            array(59000, 59999, 'RN'), array(60000, 63999, 'CE'), array(64000, 64999, 'PI'),
            array(65000, 65999, 'MA'), array(66000, 68899, 'PA'), array(68900, 68999, 'AP'),
            array(69000, 69299, 'AM'), array(69300, 69399, 'RR'), array(69400, 69899, 'AM'),
            array(69900, 69999, 'AC'), array(70000, 72799, 'DF'), array(72800, 72999, 'GO'),
            array(73000, 73699, 'DF'), array(73700, 76799, 'GO'), array(76800, 76999, 'RO'),
            array(77000, 77999, 'TO'), array(78000, 78899, 'MT'), array(78900, 78999, 'RO'),
            array(79000, 79999, 'MS'), array(80000, 87999, 'PR'), array(88000, 89999, 'SC'),
            array(90000, 99999, 'RS'),
        );

        foreach ($faixas as $faixa) {
            if ($prefixo >= $faixa[0] && $prefixo <= $faixa[1]) {
                return $faixa[2];
            }
        }

        return '';
    }

    /* -----------------------------------------------------------------
     * Sugestões de endereço ao digitar
     * ------------------------------------------------------------- */

    const SUGGEST_LIMIT = 8;

    /**
     * Rua digitada sem número nem símbolos (o ViaCEP só aceita letras,
     * números e espaços, e o número da casa atrapalha a busca).
     */
    private static function clean_street_query($texto)
    {
        $texto = preg_replace('/[,\-–].*$/u', '', (string) $texto);
        $texto = preg_replace('/\s+\d+\s*$/u', '', $texto);
        $texto = preg_replace('/[^\p{L}\p{N}\s\.]/u', ' ', $texto);

        $texto = trim(preg_replace('/\s+/u', ' ', $texto));

        // Abreviações que a base dos Correios não entende: "Av Paulista"
        // não acha nada; "Avenida Paulista" acha.
        $partes = explode(' ', $texto, 2);
        $tipo   = self::street_type($partes[0]);

        if ($tipo && isset($partes[1])) {
            $texto = $tipo . ' ' . $partes[1];
        }

        // Títulos abreviados no nome: "Dr. Arnaldo" → "Doutor Arnaldo".
        $titulos = array(
            'dr' => 'Doutor', 'dra' => 'Doutora', 'prof' => 'Professor', 'profa' => 'Professora',
            'gal' => 'General', 'gen' => 'General', 'cel' => 'Coronel', 'mal' => 'Marechal',
            'alm' => 'Almirante', 'cap' => 'Capitão', 'ten' => 'Tenente', 'sgt' => 'Sargento',
            'pres' => 'Presidente', 'gov' => 'Governador', 'sen' => 'Senador', 'dep' => 'Deputado',
            'ver' => 'Vereador', 'des' => 'Desembargador', 'eng' => 'Engenheiro', 'pe' => 'Padre',
            'sto' => 'Santo', 'sta' => 'Santa', 'sr' => 'Senhor', 'sra' => 'Senhora', 'ns' => 'Nossa Senhora',
        );

        $palavras = explode(' ', $texto);

        foreach ($palavras as $indice => $palavra) {
            $chave = self::fold(rtrim($palavra, '.'));

            if ($indice > 0 && isset($titulos[$chave])) {
                $palavras[$indice] = $titulos[$chave];
            }
        }

        $texto = implode(' ', $palavras);

        // Pontos restantes ("Dr. Arnaldo") fazem o ViaCEP recusar a busca.
        return trim(preg_replace('/\s+/u', ' ', str_replace('.', ' ', $texto)));
    }

    /**
     * Nome completo do tipo de logradouro ("av" → "Avenida"), ou '' se a
     * palavra não for um tipo.
     */
    private static function street_type($palavra)
    {
        $tipos = array(
            'av' => 'Avenida', 'ave' => 'Avenida', 'avenida' => 'Avenida',
            'r' => 'Rua', 'rua' => 'Rua',
            'al' => 'Alameda', 'alameda' => 'Alameda',
            'tv' => 'Travessa', 'trav' => 'Travessa', 'travessa' => 'Travessa',
            'pc' => 'Praça', 'pca' => 'Praça', 'praca' => 'Praça',
            'rod' => 'Rodovia', 'rodovia' => 'Rodovia',
            'est' => 'Estrada', 'estr' => 'Estrada', 'estrada' => 'Estrada',
            'lg' => 'Largo', 'largo' => 'Largo',
            'bc' => 'Beco', 'beco' => 'Beco',
            'vd' => 'Viaduto', 'viaduto' => 'Viaduto',
        );

        $chave = self::fold(rtrim($palavra, '.'));

        return isset($tipos[$chave]) ? $tipos[$chave] : '';
    }

    private static function provider()
    {
        $options = AutoCEP_Admin::get_options();

        return isset($options['general']['address_suggest_provider']) ? $options['general']['address_suggest_provider'] : 'auto';
    }

    private static function google_key()
    {
        return class_exists('AutoCEP_Maps_Service') ? (string) AutoCEP_Maps_Service::get_api_key() : '';
    }

    public function ajax_address_suggest()
    {
        check_ajax_referer(AUTOCEP_NONCE_ACTION, 'nonce');

        if (!AutoCEP_Api::throttle('address_suggest', 90)) {
            wp_send_json_error(array('message' => __('Muitas consultas seguidas. Aguarde um instante e tente de novo.', 'autocep')), 429);
        }

        $options = AutoCEP_Admin::get_options();

        if (empty($options['general']['address_suggest'])) {
            wp_send_json_success(array('items' => array()));
        }

        $rua    = self::clean_street_query(isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '');
        $cidade = isset($_POST['cidade']) ? sanitize_text_field(wp_unslash($_POST['cidade'])) : '';
        $uf     = isset($_POST['uf']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['uf']))) : '';
        $sessao = isset($_POST['sessao']) ? sanitize_key(wp_unslash($_POST['sessao'])) : '';

        if (function_exists('mb_strlen') ? mb_strlen($rua) < 3 : strlen($rua) < 3) {
            wp_send_json_success(array('items' => array(), 'need_city' => false));
        }

        $provedor   = self::provider();
        $tem_cidade = preg_match('/^[A-Z]{2}$/', $uf) && (function_exists('mb_strlen') ? mb_strlen($cidade) >= 3 : strlen($cidade) >= 3);
        $tem_google = '' !== self::google_key();
        $itens      = array();

        if ('google' !== $provedor && $tem_cidade) {
            $itens = self::suggest_viacep($uf, $cidade, $rua);

            // Tipo digitado errado (ex.: "Rua Paulista", que é Avenida):
            // se nenhuma rua encontrada tem exatamente o nome digitado, busca
            // também só pelo nome e junta os resultados.
            $partes = explode(' ', $rua, 2);

            if (isset($partes[1]) && self::street_type($partes[0]) && strlen($partes[1]) >= 3) {
                $nome_digitado = self::fold($partes[1]);
                $achou_nome    = false;

                foreach ($itens as $item) {
                    if (!empty($item['logradouro']) && 0 === strpos(preg_replace('/^\S+\s/', '', self::fold($item['logradouro'])) . ' ', $nome_digitado . ' ')) {
                        $achou_nome = true;
                        break;
                    }
                }

                if (!$achou_nome) {
                    $ceps  = wp_list_pluck($itens, 'cep');
                    $extra = array_filter(self::suggest_viacep($uf, $cidade, $partes[1]), function ($item) use ($ceps) {
                        return !in_array($item['cep'], $ceps, true);
                    });

                    // Nome exato primeiro (Avenida Paulista antes de Rua Paulistânia).
                    $itens = array_slice(array_merge(array_values($extra), $itens), 0, self::SUGGEST_LIMIT);
                }
            }
        }

        // Cidade de CEP único (comum em cidades pequenas): os Correios não têm
        // as ruas, só o CEP geral. As ruas vêm do OpenStreetMap, com o CEP
        // geral oficial (o CEP do OpenStreetMap nem sempre é o certo).
        if (1 === count($itens) && !empty($itens[0]['keep_street']) && 'google' !== $provedor) {
            $ruas = self::suggest_osm($uf, $itens[0]['localidade'], $rua, $itens[0]['cep']);

            if (!empty($ruas)) {
                $itens = $ruas;
            }
        }

        if (empty($itens) && 'viacep' !== $provedor && $tem_google) {
            $itens = self::suggest_google($rua, $tem_cidade ? $cidade : '', $tem_cidade ? $uf : '', $sessao);
        }

        wp_send_json_success(array(
            'items'     => $itens,
            // Sem cidade/estado e sem Google: o navegador mostra uma dica.
            'need_city' => empty($itens) && !$tem_cidade && !('viacep' !== $provedor && $tem_google),
        ));
    }

    /**
     * Ruas da cidade que combinam com o texto (base dos Correios via ViaCEP).
     */
    public static function suggest_viacep($uf, $cidade, $rua)
    {
        $chave = self::CACHE_PREFIX . 'sug_' . md5(strtolower($uf . '|' . $cidade . '|' . $rua));
        $cache = get_transient($chave);

        if (false !== $cache) {
            return $cache;
        }

        $resposta = wp_remote_get(
            'https://viacep.com.br/ws/' . rawurlencode($uf) . '/' . rawurlencode($cidade) . '/' . rawurlencode($rua) . '/json/',
            array('timeout' => self::REQUEST_TIMEOUT)
        );

        if (is_wp_error($resposta) || 200 !== (int) wp_remote_retrieve_response_code($resposta)) {
            return array();
        }

        $lista = json_decode(wp_remote_retrieve_body($resposta), true);

        if (!is_array($lista) || isset($lista['erro'])) {
            return array();
        }

        $busca   = self::fold($rua);
        // Busca sem o tipo ("avenida paulista" → "paulista"), para a ordem.
        $partes_busca = explode(' ', $busca, 2);
        $busca_nome   = isset($partes_busca[1]) && self::street_type($partes_busca[0]) ? $partes_busca[1] : $busca;
        $itens   = array();
        $geral   = null;
        $vistos  = array();

        foreach ($lista as $linha) {
            if (!is_array($linha) || empty($linha['cep'])) {
                continue;
            }

            $cep = preg_replace('/\D/', '', $linha['cep']);

            // Cidade pequena com CEP único: sem rua cadastrada.
            if (empty($linha['logradouro'])) {
                $geral = array(
                    'id'         => 'viacep-' . $cep,
                    'label'      => sprintf(__('CEP geral de %s', 'autocep'), $linha['localidade']),
                    'sub'        => sprintf(__('%1$s/%2$s · CEP %3$s', 'autocep'), $linha['localidade'], $linha['uf'], self::format_cep($cep)),
                    'logradouro' => '',
                    'bairro'     => '',
                    'localidade' => sanitize_text_field($linha['localidade']),
                    'uf'         => sanitize_text_field($linha['uf']),
                    'cep'        => $cep,
                    'keep_street' => true,
                );
                continue;
            }

            // Terminações 900–999: grandes usuários (prédios, empresas),
            // caixas postais e CEPs promocionais — não servem para a
            // entrega comum de um cliente.
            if (isset($vistos[$cep]) || (int) substr($cep, 5) >= 900) {
                continue;
            }

            $vistos[$cep] = true;
            $trecho       = trim(isset($linha['complemento']) ? $linha['complemento'] : '');
            $nome         = self::fold($linha['logradouro']);

            $itens[] = array(
                'id'         => 'viacep-' . $cep,
                'label'      => $linha['logradouro'] . ('' !== $trecho ? ' (' . $trecho . ')' : ''),
                'sub'        => sprintf(__('%1$s, %2$s/%3$s · CEP %4$s', 'autocep'), $linha['bairro'] ? $linha['bairro'] : __('Centro', 'autocep'), $linha['localidade'], $linha['uf'], self::format_cep($cep)),
                'logradouro' => sanitize_text_field($linha['logradouro']),
                'bairro'     => sanitize_text_field($linha['bairro']),
                'localidade' => sanitize_text_field($linha['localidade']),
                'uf'         => sanitize_text_field($linha['uf']),
                'cep'        => $cep,
                // Relevância: o nome da rua (sem "Rua", "Avenida"...) começa com o que foi digitado.
                '_ordem'     => (0 === strpos($nome, $busca) || 0 === strpos(preg_replace('/^\S+\s/', '', $nome), $busca_nome) ? 0 : (false !== strpos($nome, $busca_nome) ? 1 : 2)) . $nome,
            );
        }

        usort($itens, function ($a, $b) {
            return strcmp($a['_ordem'], $b['_ordem']);
        });

        $itens = array_slice($itens, 0, self::SUGGEST_LIMIT);

        foreach ($itens as &$item) {
            unset($item['_ordem']);
        }

        unset($item);

        if ($geral) {
            $itens[] = $geral;
        }

        set_transient($chave, $itens, 7 * DAY_IN_SECONDS);

        return $itens;
    }

    /**
     * Ruas de uma cidade de CEP único, pelo Photon (busca de endereços do
     * OpenStreetMap, gratuita e feita para autocompletar). Só entram ruas
     * da cidade e do estado informados; o CEP é sempre o geral da cidade.
     */
    public static function suggest_osm($uf, $cidade, $rua, $cep_geral)
    {
        $chave = self::CACHE_PREFIX . 'osm_' . md5(strtolower($uf . '|' . $cidade . '|' . $rua));
        $cache = get_transient($chave);

        if (false !== $cache) {
            return $cache;
        }

        $estados = array(
            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas', 'BA' => 'Bahia',
            'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo', 'GO' => 'Goiás',
            'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
            'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco', 'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte', 'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina', 'SP' => 'São Paulo',
            'SE' => 'Sergipe', 'TO' => 'Tocantins',
        );

        if (!isset($estados[$uf])) {
            return array();
        }

        $url = add_query_arg(array(
            'q'     => $rua . ' ' . $cidade . ' ' . $estados[$uf],
            'layer' => 'street',
            'limit' => 15,
        ), 'https://photon.komoot.io/api/');

        $resposta = wp_remote_get($url, array(
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => array('User-Agent' => 'AutoCEP-Plugin/' . (defined('AUTOCEP_VERSION') ? AUTOCEP_VERSION : '2') . ' (WooCommerce; autocompletar de endereço)'),
        ));

        if (is_wp_error($resposta) || 200 !== (int) wp_remote_retrieve_response_code($resposta)) {
            return array();
        }

        $dados  = json_decode(wp_remote_retrieve_body($resposta), true);
        $itens  = array();
        $vistos = array();

        // Palavras digitadas (sem o tipo da rua): cada uma tem que ser o
        // começo de alguma palavra do nome. O Photon é tolerante demais e
        // devolveria ruas que não têm nada a ver ("rua rs" → "Rua Nova").
        $palavras_busca = array_filter(explode(' ', self::fold($rua)));
        $primeira       = reset($palavras_busca);

        if (count($palavras_busca) > 1 && self::street_type($primeira)) {
            array_shift($palavras_busca);
        }

        foreach (isset($dados['features']) && is_array($dados['features']) ? $dados['features'] : array() as $f) {
            $p    = isset($f['properties']) ? $f['properties'] : array();
            $nome = isset($p['name']) ? trim($p['name']) : '';

            // Só ruas da própria cidade e do próprio estado, no Brasil.
            $cidade_osm = isset($p['city']) ? $p['city'] : (isset($p['county']) ? $p['county'] : '');

            if ('' === $nome || 'BR' !== (isset($p['countrycode']) ? $p['countrycode'] : '')
                || self::fold($cidade_osm) !== self::fold($cidade)
                || self::fold(isset($p['state']) ? $p['state'] : '') !== self::fold($estados[$uf])
                || isset($vistos[self::fold($nome)])) {
                continue;
            }

            $palavras_nome = explode(' ', self::fold($nome));
            $combina       = true;

            foreach ($palavras_busca as $palavra) {
                $achou = false;

                foreach ($palavras_nome as $parte) {
                    if (0 === strpos($parte, $palavra)) {
                        $achou = true;
                        break;
                    }
                }

                if (!$achou) {
                    $combina = false;
                    break;
                }
            }

            if (!$combina) {
                continue;
            }

            $vistos[self::fold($nome)] = true;
            $bairro = isset($p['district']) && self::fold($p['district']) !== self::fold($cidade) ? $p['district'] : '';

            $itens[] = array(
                'id'         => 'osm-' . md5($nome),
                'label'      => sanitize_text_field($nome),
                'sub'        => sprintf(__('%1$s%2$s/%3$s · CEP %4$s', 'autocep'), $bairro ? sanitize_text_field($bairro) . ', ' : '', $cidade, $uf, self::format_cep($cep_geral)),
                'logradouro' => sanitize_text_field($nome),
                'bairro'     => sanitize_text_field($bairro),
                'localidade' => $cidade,
                'uf'         => $uf,
                'cep'        => $cep_geral,
            );

            if (count($itens) >= self::SUGGEST_LIMIT) {
                break;
            }
        }

        set_transient($chave, $itens, 7 * DAY_IN_SECONDS);

        return $itens;
    }

    /**
     * Endereços do Google Places (API "New"), consultados pelo servidor —
     * a chave nunca vai para o navegador.
     */
    public static function suggest_google($rua, $cidade, $uf, $sessao)
    {
        $texto = $rua . ($cidade ? ', ' . $cidade . ($uf ? ' - ' . $uf : '') : '');

        $resposta = wp_remote_post('https://places.googleapis.com/v1/places:autocomplete', array(
            'timeout' => 6,
            'headers' => array(
                'Content-Type'   => 'application/json',
                'X-Goog-Api-Key' => self::google_key(),
            ),
            'body'    => wp_json_encode(array_filter(array(
                'input'                => $texto,
                'includedRegionCodes'  => array('br'),
                'languageCode'         => 'pt-BR',
                'includedPrimaryTypes' => array('route', 'street_address', 'premise'),
                'sessionToken'         => $sessao,
            ))),
        ));

        if (is_wp_error($resposta) || 200 !== (int) wp_remote_retrieve_response_code($resposta)) {
            return array();
        }

        $dados = json_decode(wp_remote_retrieve_body($resposta), true);
        $itens = array();

        if (empty($dados['suggestions']) || !is_array($dados['suggestions'])) {
            return array();
        }

        foreach ($dados['suggestions'] as $sugestao) {
            if (empty($sugestao['placePrediction']['placeId'])) {
                continue;
            }

            $p = $sugestao['placePrediction'];

            $itens[] = array(
                'id'      => 'google-' . sanitize_text_field($p['placeId']),
                'place_id' => sanitize_text_field($p['placeId']),
                'label'   => sanitize_text_field(isset($p['structuredFormat']['mainText']['text']) ? $p['structuredFormat']['mainText']['text'] : $p['text']['text']),
                'sub'     => sanitize_text_field(isset($p['structuredFormat']['secondaryText']['text']) ? $p['structuredFormat']['secondaryText']['text'] : ''),
            );
        }

        return array_slice($itens, 0, self::SUGGEST_LIMIT);
    }

    /**
     * Endereço completo de uma sugestão do Google. Se o Google não trouxer o
     * CEP completo (comum em ruas longas), busca o CEP da rua no ViaCEP.
     */
    public function ajax_address_details()
    {
        check_ajax_referer(AUTOCEP_NONCE_ACTION, 'nonce');

        if (!AutoCEP_Api::throttle('address_details', 40)) {
            wp_send_json_error(array('message' => __('Muitas consultas seguidas. Aguarde um instante e tente de novo.', 'autocep')), 429);
        }

        $place  = isset($_POST['place_id']) ? sanitize_text_field(wp_unslash($_POST['place_id'])) : '';
        $sessao = isset($_POST['sessao']) ? sanitize_key(wp_unslash($_POST['sessao'])) : '';

        if ('' === $place || '' === self::google_key() || !preg_match('/^[A-Za-z0-9_\-]+$/', $place)) {
            wp_send_json_error();
        }

        $url = add_query_arg(array_filter(array('languageCode' => 'pt-BR', 'sessionToken' => $sessao)), 'https://places.googleapis.com/v1/places/' . rawurlencode($place));

        $resposta = wp_remote_get($url, array(
            'timeout' => 6,
            'headers' => array(
                'X-Goog-Api-Key'   => self::google_key(),
                'X-Goog-FieldMask' => 'addressComponents',
            ),
        ));

        if (is_wp_error($resposta) || 200 !== (int) wp_remote_retrieve_response_code($resposta)) {
            wp_send_json_error();
        }

        $dados = json_decode(wp_remote_retrieve_body($resposta), true);
        $end   = array('logradouro' => '', 'numero' => '', 'bairro' => '', 'localidade' => '', 'uf' => '', 'cep' => '');

        foreach (isset($dados['addressComponents']) ? $dados['addressComponents'] : array() as $parte) {
            $tipos = isset($parte['types']) ? $parte['types'] : array();
            $longo = isset($parte['longText']) ? $parte['longText'] : '';

            if (in_array('route', $tipos, true)) {
                $end['logradouro'] = $longo;
            } elseif (in_array('street_number', $tipos, true)) {
                $end['numero'] = $longo;
            } elseif (in_array('sublocality_level_1', $tipos, true) || (in_array('sublocality', $tipos, true) && '' === $end['bairro'])) {
                $end['bairro'] = $longo;
            } elseif (in_array('administrative_area_level_2', $tipos, true)) {
                $end['localidade'] = $longo;
            } elseif (in_array('administrative_area_level_1', $tipos, true)) {
                $end['uf'] = isset($parte['shortText']) ? strtoupper($parte['shortText']) : '';
            } elseif (in_array('postal_code', $tipos, true)) {
                $end['cep'] = preg_replace('/\D/', '', $longo);
            }
        }

        // CEP incompleto: o ViaCEP traz o CEP exato da rua.
        if (8 !== strlen($end['cep']) && $end['logradouro'] && $end['localidade'] && $end['uf']) {
            $ruas = self::suggest_viacep($end['uf'], $end['localidade'], self::clean_street_query($end['logradouro']));

            if (!empty($ruas[0]['cep'])) {
                $end['cep']    = $ruas[0]['cep'];
                $end['bairro'] = $end['bairro'] ? $end['bairro'] : $ruas[0]['bairro'];
            }
        }

        wp_send_json_success(array_map('sanitize_text_field', $end));
    }

    /**
     * Texto em minúsculas e sem acentos, para comparar nomes de ruas.
     */
    private static function fold($texto)
    {
        $texto = function_exists('remove_accents') ? remove_accents((string) $texto) : (string) $texto;

        return strtolower($texto);
    }

    private static function format_cep($cep)
    {
        return strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : $cep;
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

        if (!AutoCEP_Api::throttle('get_address', 120)) {
            wp_send_json_error(array('message' => __('Muitas consultas seguidas. Aguarde um instante e tente de novo.', 'autocep')), 429);
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

        // CEP que nenhuma API encontrou recentemente: responde na hora, sem
        // consultar as três APIs de novo (usa o mesmo prefixo do cache de
        // CEPs, então o botão "Limpar cache" também o remove).
        $chave_nao_encontrado = self::CACHE_PREFIX . 'nf_' . $cep;

        if (get_transient($chave_nao_encontrado)) {
            return new WP_Error('autocep_not_found', __('CEP não encontrado.', 'autocep'));
        }

        // APIs que falharam por conexão/timeout há pouco ficam "em pausa" e
        // são puladas, para que cada cliente não espere o timeout de novo.
        // Se todas estiverem em pausa, tenta todas mesmo assim.
        $apis_disponiveis = array_values(array_filter($apis_ativas, function ($slug) {
            return !get_transient(self::PAUSE_PREFIX . $slug);
        }));

        $alguma_em_pausa = count($apis_disponiveis) < count($apis_ativas);

        if (!empty($apis_disponiveis)) {
            $apis_ativas = $apis_disponiveis;
        }

        $ultimo_erro     = null;
        $houve_falha_api = false;

        foreach ($apis_ativas as $slug) {
            $resultado = self::request_provider($slug, $cep);

            if (is_wp_error($resultado)) {
                $ultimo_erro     = $resultado;
                $houve_falha_api = true;

                if (in_array($resultado->get_error_code(), array('http_request_failed', 'autocep_api_bad_response'), true)) {
                    set_transient(self::PAUSE_PREFIX . $slug, 1, self::PAUSE_SECONDS);
                }

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

        // Só memoriza "não encontrado" quando todas as APIs responderam de
        // fato — uma falha de conexão não prova que o CEP não existe.
        if (!$houve_falha_api && !$alguma_em_pausa) {
            set_transient($chave_nao_encontrado, 1, 6 * HOUR_IN_SECONDS);
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

    /**
     * Resposta que não é nem um endereço nem um "CEP não existe" explícito
     * (ex.: bloqueio por excesso de consultas, erro 5xx, página HTML). É
     * tratada como falha da API — nunca como CEP inexistente.
     */
    private static function bad_response($api, $codigo_http)
    {
        return new WP_Error(
            'autocep_api_bad_response',
            sprintf(__('%1$s respondeu de forma inesperada (HTTP %2$d).', 'autocep'), $api, (int) $codigo_http)
        );
    }

    private static function fetch_viacep($cep)
    {
        $response = wp_remote_get("https://viacep.com.br/ws/{$cep}/json/", array(
            'timeout' => self::REQUEST_TIMEOUT,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $codigo_http = (int) wp_remote_retrieve_response_code($response);
        $data        = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $codigo_http || !is_array($data)) {
            return self::bad_response('ViaCEP', $codigo_http);
        }

        // A ViaCEP responde {"erro": true} quando o CEP não existe.
        if (!empty($data['erro'])) {
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

        $codigo_http = (int) wp_remote_retrieve_response_code($response);

        // A BrasilAPI responde 404 quando o CEP não existe.
        if (404 === $codigo_http) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $codigo_http || !is_array($data) || empty($data['cep'])) {
            return self::bad_response('BrasilAPI', $codigo_http);
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
        // A ApiCEP só encontra o arquivo com o CEP no formato 00000-000;
        // sem o hífen ela responde "429 Blocked by flood".
        $cep_formatado = substr($cep, 0, 5) . '-' . substr($cep, 5, 3);

        $response = wp_remote_get("https://cdn.apicep.com/file/apicep/{$cep_formatado}.json", array(
            'timeout' => self::REQUEST_TIMEOUT,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $codigo_http = (int) wp_remote_retrieve_response_code($response);
        $data        = json_decode(wp_remote_retrieve_body($response), true);
        $status      = is_array($data) && isset($data['status']) ? (int) $data['status'] : $codigo_http;

        // CEP inexistente: arquivo não encontrado (404) ou status 400.
        if (404 === $codigo_http || 404 === $status || 400 === $status) {
            return array();
        }

        if (200 !== $codigo_http || 200 !== $status || !is_array($data)) {
            return self::bad_response('ApiCEP', 200 !== $codigo_http ? $codigo_http : $status);
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
