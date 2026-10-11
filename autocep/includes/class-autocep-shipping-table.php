<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Método de envio "Tabela de Frete — AutoCEP".
 *
 * Calcula o frete a partir de uma planilha de faixas de CEP x peso, colada
 * (ou importada de um arquivo CSV) nas configurações do método dentro de
 * uma Zona de Entrega. Cada linha da planilha tem as colunas:
 *
 *   cep_inicial ; cep_final ; peso_min ; peso_max ; valor ; prazo ; nome
 *
 * - "peso_min"/"peso_max" em kg; "peso_max" vazio = sem limite.
 * - "prazo" (dias úteis) e "nome" são opcionais. Linhas com nomes
 *   diferentes que atendem o mesmo CEP/peso viram opções de frete
 *   diferentes (ex.: "Econômico" e "Expresso").
 * - Separadores aceitos: ponto e vírgula, tabulação (colado do Excel /
 *   Google Planilhas) ou vírgula. Valores aceitam vírgula decimal.
 */
class AutoCEP_Shipping_Table extends WC_Shipping_Method
{
    private static $cache_tabelas = array();

    public function __construct($instance_id = 0)
    {
        $this->id                 = 'autocep_shipping_table';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Tabela de Frete — AutoCEP', 'autocep');
        $this->method_description = __('Calcula o frete a partir de uma planilha própria de faixas de CEP e peso (cole as linhas do Excel/Google Planilhas ou importe um arquivo CSV).', 'autocep');
        $this->supports           = array('shipping-zones', 'instance-settings', 'instance-settings-modal');

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
                'title'       => __('Nome exibido ao cliente', 'autocep'),
                'type'        => 'text',
                'description' => __('Usado quando a linha da tabela não tem a coluna "nome".', 'autocep'),
                'default'     => __('Entrega', 'autocep'),
                'desc_tip'    => true,
            ),
            'table' => array(
                'title'       => __('Tabela de Frete', 'autocep'),
                'type'        => 'autocep_table',
                'description' => __('Uma faixa por linha, nas colunas: cep_inicial; cep_final; peso_min (kg); peso_max (kg); valor; prazo (dias úteis, opcional); nome (opcional). Deixe "peso_max" vazio para "sem limite". Uma linha de cabeçalho é ignorada automaticamente.', 'autocep'),
                'default'     => '',
            ),
            'handling_fee' => array(
                'title'       => __('Taxa adicional', 'autocep'),
                'type'        => 'price',
                'description' => __('Valor fixo somado ao frete da tabela (ex.: embalagem). Deixe vazio para não cobrar.', 'autocep'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'show_days' => array(
                'title'   => __('Prazo no nome', 'autocep'),
                'type'    => 'checkbox',
                'label'   => __('Exibir o prazo da tabela ao lado do nome do frete (ex.: "Entrega (5 dias úteis)")', 'autocep'),
                'default' => 'yes',
            ),
            'limit_zone' => array(
                'title'       => __('Limitar a zona', 'autocep'),
                'type'        => 'checkbox',
                'label'       => __('Ao salvar, limitar esta Zona de Entrega às faixas de CEP da tabela', 'autocep'),
                'description' => __('Preenche automaticamente "Limitar para CEPs específicos" nas Regiões da zona com as faixas da tabela (faixas vizinhas são unidas). Se uma zona mais geral (ex.: Brasil inteiro) estiver acima desta na lista, esta zona é movida para cima dela, senão a tabela nunca seria usada. Vale para todos os métodos da zona. Recarregue a página após salvar para ver as mudanças.', 'autocep'),
                'default'     => 'yes',
            ),
        );
    }

    /**
     * Campo da tabela: textarea + botão para importar um arquivo CSV, cujo
     * conteúdo é lido no navegador e colocado na textarea.
     */
    public function generate_autocep_table_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $valor     = $this->get_option($key);
        $exemplo   = "cep_inicial;cep_final;peso_min;peso_max;valor;prazo;nome\n01000000;09999999;0;5;19,90;3;Econômico\n01000000;09999999;5;;29,90;3;Econômico\n10000000;19999999;0;;34,90;5;Econômico";

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($data['title']); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <textarea
                        rows="10"
                        style="width:100%;font-family:monospace;white-space:pre;"
                        id="<?php echo esc_attr($field_key); ?>"
                        name="<?php echo esc_attr($field_key); ?>"
                        placeholder="<?php echo esc_attr($exemplo); ?>"
                    ><?php echo esc_textarea($valor); ?></textarea>
                    <p>
                        <label>
                            <?php esc_html_e('Importar arquivo CSV:', 'autocep'); ?>
                            <input
                                type="file"
                                accept=".csv,.txt,text/csv,text/plain"
                                onchange="var f=this.files&&this.files[0],t=document.getElementById('<?php echo esc_js($field_key); ?>');if(!f||!t){return;}var r=new FileReader();r.onload=function(){var b=new Uint8Array(r.result),s;try{s=new TextDecoder('utf-8',{fatal:true}).decode(b);}catch(e){s=new TextDecoder('windows-1252').decode(b);}t.value=s.replace(/^﻿/,'');t.dispatchEvent(new Event('change',{bubbles:true}));};r.readAsArrayBuffer(f);"
                            />
                        </label>
                    </p>
                    <p class="description"><?php echo wp_kses_post($data['description']); ?></p>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    /**
     * Ao salvar, renova a versão do cache de frete do WooCommerce — assim os
     * carrinhos abertos e o simulador do AutoCEP recalculam com a tabela nova.
     */
    public function process_admin_options()
    {
        $salvo = parent::process_admin_options();

        if ('yes' === $this->get_option('limit_zone')) {
            $this->limit_zone_to_table();
        }

        if (class_exists('WC_Cache_Helper')) {
            WC_Cache_Helper::get_transient_version('shipping', true);
        }

        return $salvo;
    }

    /**
     * Junta faixas de CEP sobrepostas ou vizinhas (ex.: 01000000–05999999 e
     * 06000000–09999999 viram 01000000–09999999), para a zona ficar com o
     * menor número possível de faixas.
     *
     * @param array $faixas Faixas lidas por parse_table().
     * @return array<int, array{0: int, 1: int}> Pares [início, fim].
     */
    public static function merge_cep_ranges($faixas)
    {
        $intervalos = array();

        foreach ($faixas as $faixa) {
            $intervalos[] = array(
                min($faixa['cep_inicial'], $faixa['cep_final']),
                max($faixa['cep_inicial'], $faixa['cep_final']),
            );
        }

        usort($intervalos, function ($a, $b) {
            return $a[0] - $b[0];
        });

        $unidos = array();

        foreach ($intervalos as $intervalo) {
            $ultimo = count($unidos) - 1;

            if ($ultimo >= 0 && $intervalo[0] <= $unidos[$ultimo][1] + 1) {
                $unidos[$ultimo][1] = max($unidos[$ultimo][1], $intervalo[1]);
            } else {
                $unidos[] = $intervalo;
            }
        }

        return $unidos;
    }

    /**
     * Grava as faixas de CEP da tabela como "Limitar para CEPs específicos"
     * da Zona de Entrega deste método. Se a zona não tiver nenhuma região
     * (país/estado/continente), adiciona o Brasil.
     */
    private function limit_zone_to_table()
    {
        if (!$this->instance_id || !class_exists('WC_Shipping_Zones')) {
            return;
        }

        $tabela = self::parse_table($this->get_option('table'));

        if (empty($tabela['rows'])) {
            return;
        }

        $zona = WC_Shipping_Zones::get_zone_by('instance_id', $this->instance_id);

        // A zona 0 ("Locais não cobertos pelas outras zonas") não aceita regiões.
        if (!$zona || !$zona->get_id()) {
            return;
        }

        // Só mexe em zonas exclusivas da tabela: limitar uma zona com outros
        // métodos (ex.: Melhor Envio) restringiria esses métodos também.
        foreach ($zona->get_shipping_methods(true) as $metodo) {
            if ((int) $metodo->instance_id !== (int) $this->instance_id) {
                $this->add_error(__('Tabela de Frete: esta zona tem outros métodos de entrega ativos, então as regiões e a ordem da zona não foram alteradas. Para limitar a zona automaticamente, use uma zona só para a Tabela de Frete.', 'autocep'));

                return;
            }
        }

        if ('yes' !== $this->get_option('enabled')) {
            return;
        }

        $tem_regiao = false;

        foreach ($zona->get_zone_locations() as $local) {
            if ('postcode' !== $local->type) {
                $tem_regiao = true;
                break;
            }
        }

        $zona->clear_locations(array('postcode'));

        if (!$tem_regiao) {
            $zona->add_location('BR', 'country');
        }

        foreach (self::merge_cep_ranges($tabela['rows']) as $intervalo) {
            $inicio = str_pad((string) $intervalo[0], 8, '0', STR_PAD_LEFT);
            $fim    = str_pad((string) $intervalo[1], 8, '0', STR_PAD_LEFT);

            $zona->add_location($inicio === $fim ? $inicio : $inicio . '...' . $fim, 'postcode');
        }

        $zona->save();

        self::move_zone_above_broader_zones($zona->get_id());
    }

    /**
     * Uma zona "geral" atende o Brasil inteiro sem limite de CEP: sem
     * nenhuma região, ou com o país Brasil / continente América do Sul e
     * nenhum CEP. Zonas por estado ou por CEP são consideradas específicas.
     */
    private static function is_broad_zone($locais)
    {
        if (empty($locais)) {
            return true;
        }

        $cobre_brasil = false;

        foreach ($locais as $local) {
            $local = (object) $local;

            if ('postcode' === $local->type || 'state' === $local->type) {
                return false;
            }

            if (('country' === $local->type && 'BR' === $local->code) || ('continent' === $local->type && 'SA' === $local->code)) {
                $cobre_brasil = true;
            }
        }

        return $cobre_brasil;
    }

    /**
     * O WooCommerce usa a primeira zona da lista que combina com o endereço.
     * Se uma zona geral (ex.: "Brasil" com Melhor Envio) estiver acima da
     * zona da tabela, a tabela nunca seria usada — então movemos a zona da
     * tabela para logo antes da primeira zona geral acima dela. As demais
     * zonas mantêm a ordem relativa.
     */
    private static function move_zone_above_broader_zones($zona_id)
    {
        $zonas = array_values(WC_Shipping_Zones::get_zones('admin'));
        $ids   = array_map(function ($zona) {
            return (int) $zona['zone_id'];
        }, $zonas);

        $posicao_atual = array_search((int) $zona_id, $ids, true);

        if (false === $posicao_atual) {
            return;
        }

        $posicao_nova = null;

        for ($i = 0; $i < $posicao_atual; $i++) {
            if (self::is_broad_zone($zonas[$i]['zone_locations'])) {
                $posicao_nova = $i;
                break;
            }
        }

        if (null === $posicao_nova) {
            return;
        }

        array_splice($ids, $posicao_atual, 1);
        array_splice($ids, $posicao_nova, 0, array((int) $zona_id));

        self::save_zone_order($ids);
    }

    /**
     * Ao desativar ou excluir a Tabela de Frete, uma zona que ficou sem
     * nenhum método ativo desce para o fim da lista. Sem isso, ela
     * continuaria acima das outras zonas "roubando" os clientes das faixas
     * de CEP — e eles ficariam sem nenhuma opção de frete (ex.: o Melhor
     * Envio da zona de baixo deixaria de aparecer). Ao reativar, a zona
     * volta a subir acima das zonas gerais.
     *
     * Ligado aos eventos "woocommerce_shipping_zone_method_status_toggled"
     * e "woocommerce_shipping_zone_method_deleted" do WooCommerce.
     */
    public static function handle_method_change($instance_id, $method_id, $zona_id)
    {
        if ('autocep_shipping_table' !== $method_id || !$zona_id || !class_exists('WC_Shipping_Zones')) {
            return;
        }

        $zona = WC_Shipping_Zones::get_zone($zona_id);

        if (!$zona) {
            return;
        }

        $metodos_ativos = $zona->get_shipping_methods(true);

        if (empty($metodos_ativos)) {
            $ids = array_map(function ($dados) {
                return (int) $dados['zone_id'];
            }, array_values(WC_Shipping_Zones::get_zones('admin')));

            $posicao = array_search((int) $zona_id, $ids, true);

            if (false !== $posicao && $posicao !== count($ids) - 1) {
                array_splice($ids, $posicao, 1);
                $ids[] = (int) $zona_id;
                self::save_zone_order($ids);
            }
        } else {
            // Reativada: sobe de novo, se for uma zona só da Tabela de Frete
            // com a opção "Limitar a zona" ligada.
            foreach ($metodos_ativos as $metodo) {
                if ('autocep_shipping_table' !== $metodo->id || 'yes' !== $metodo->get_option('limit_zone') || count($metodos_ativos) > 1) {
                    return;
                }
            }

            self::move_zone_above_broader_zones($zona_id);
        }

        if (class_exists('WC_Cache_Helper')) {
            WC_Cache_Helper::get_transient_version('shipping', true);
        }
    }

    /**
     * Renumera todas as zonas (0, 1, 2...) na ordem informada, para não
     * depender de empates de ordem, salvando só as que mudaram.
     */
    private static function save_zone_order($ids)
    {
        foreach ($ids as $ordem => $id) {
            $zona = WC_Shipping_Zones::get_zone($id);

            if ($zona && (int) $zona->get_zone_order() !== $ordem) {
                $zona->set_zone_order($ordem);
                $zona->save();
            }
        }
    }

    /**
     * Normaliza o texto da tabela e informa ao administrador as linhas que
     * não puderam ser lidas (as demais são salvas normalmente).
     */
    public function validate_autocep_table_field($key, $value)
    {
        $texto = is_null($value) ? '' : trim(str_replace(array("\r\n", "\r"), "\n", wp_unslash($value)));
        $texto = sanitize_textarea_field($texto);

        $resultado = self::parse_table($texto);

        foreach (array_slice($resultado['errors'], 0, 10) as $linha) {
            $this->add_error(sprintf(__('Tabela de Frete: a linha %d não pôde ser lida e será ignorada.', 'autocep'), $linha));
        }

        if (count($resultado['errors']) > 10) {
            $this->add_error(sprintf(__('Tabela de Frete: mais %d linha(s) com erro.', 'autocep'), count($resultado['errors']) - 10));
        }

        return $texto;
    }

    /**
     * Converte o texto da planilha em faixas.
     *
     * @param string $texto Conteúdo da tabela.
     * @return array{rows: array, errors: int[]} Faixas válidas e números das linhas inválidas.
     */
    public static function parse_table($texto)
    {
        $chave = md5($texto);

        if (isset(self::$cache_tabelas[$chave])) {
            return self::$cache_tabelas[$chave];
        }

        $faixas = array();
        $erros  = array();
        $linhas = preg_split('/\n/', (string) $texto);

        foreach ($linhas as $indice => $linha) {
            $linha = trim($linha);

            if ('' === $linha || 0 === strpos($linha, '#')) {
                continue;
            }

            if (false !== strpos($linha, "\t")) {
                $separador = "\t";
            } elseif (false !== strpos($linha, ';')) {
                $separador = ';';
            } else {
                $separador = ',';
            }

            $colunas = array_map('trim', str_getcsv($linha, $separador, '"', ''));

            $cep_inicial = preg_replace('/\D/', '', isset($colunas[0]) ? $colunas[0] : '');
            $cep_final   = preg_replace('/\D/', '', isset($colunas[1]) ? $colunas[1] : '');

            // Cabeçalho (ex.: "cep_inicial;cep_final;...") é ignorado sem erro.
            if ('' === $cep_inicial && 0 === count($faixas) && preg_match('/[a-z]/i', $linha)) {
                continue;
            }

            $valor = isset($colunas[4]) ? self::parse_number($colunas[4]) : null;

            if ('' === $cep_inicial || '' === $cep_final || strlen($cep_inicial) > 8 || strlen($cep_final) > 8 || null === $valor) {
                $erros[] = $indice + 1;
                continue;
            }

            $peso_min = isset($colunas[2]) && '' !== $colunas[2] ? self::parse_number($colunas[2]) : 0.0;
            $peso_max = isset($colunas[3]) && '' !== $colunas[3] ? self::parse_number($colunas[3]) : null;

            if (null === $peso_min || (isset($colunas[3]) && '' !== $colunas[3] && null === $peso_max)) {
                $erros[] = $indice + 1;
                continue;
            }

            $faixas[] = array(
                'cep_inicial' => (int) str_pad($cep_inicial, 8, '0', STR_PAD_LEFT),
                'cep_final'   => (int) str_pad($cep_final, 8, '0', STR_PAD_LEFT),
                'peso_min'    => $peso_min,
                'peso_max'    => $peso_max,
                'valor'       => $valor,
                'prazo'       => isset($colunas[5]) ? absint(preg_replace('/\D/', '', $colunas[5])) : 0,
                'nome'        => isset($colunas[6]) ? $colunas[6] : '',
            );
        }

        self::$cache_tabelas[$chave] = array('rows' => $faixas, 'errors' => $erros);

        return self::$cache_tabelas[$chave];
    }

    /**
     * Lê um número no formato brasileiro ("1.234,56") ou internacional
     * ("1234.56"). Retorna null se não for um número.
     */
    private static function parse_number($texto)
    {
        $texto = str_replace(array('R$', ' '), '', (string) $texto);

        if ('' === $texto) {
            return null;
        }

        if (false !== strpos($texto, ',')) {
            $texto = str_replace(array('.', ','), array('', '.'), $texto);
        }

        return is_numeric($texto) ? (float) $texto : null;
    }

    /**
     * Peso total do pacote em kg (produtos sem peso contam como 0).
     */
    private function get_package_weight($package)
    {
        $peso = 0.0;

        if (empty($package['contents'])) {
            return $peso;
        }

        foreach ($package['contents'] as $item) {
            if (empty($item['data']) || !is_object($item['data']) || !$item['data']->needs_shipping()) {
                continue;
            }

            $peso_item = (float) $item['data']->get_weight();

            if ($peso_item > 0) {
                $peso += (float) wc_get_weight($peso_item, 'kg') * (int) $item['quantity'];
            }
        }

        return $peso;
    }

    public function calculate_shipping($package = array())
    {
        $cep_destino = isset($package['destination']['postcode'])
            ? preg_replace('/\D/', '', $package['destination']['postcode'])
            : '';

        if (8 !== strlen($cep_destino)) {
            return;
        }

        $tabela = self::parse_table($this->get_option('table'));

        if (empty($tabela['rows'])) {
            return;
        }

        $cep   = (int) $cep_destino;
        $peso  = $this->get_package_weight($package);
        $taxa  = (float) wc_format_decimal($this->get_option('handling_fee'));
        $nomes = array();

        foreach ($tabela['rows'] as $faixa) {
            if ($cep < $faixa['cep_inicial'] || $cep > $faixa['cep_final']) {
                continue;
            }

            // Faixa de peso: inclui o limite superior (0 a 5 kg atende 5 kg).
            if ($peso < $faixa['peso_min'] || (null !== $faixa['peso_max'] && $peso > $faixa['peso_max'])) {
                continue;
            }

            $nome = '' !== $faixa['nome'] ? $faixa['nome'] : $this->title;

            // A primeira linha que atende o CEP/peso vale para cada nome.
            if (isset($nomes[$nome])) {
                continue;
            }

            $nomes[$nome] = true;

            $label = $nome;

            if ($faixa['prazo'] > 0 && 'yes' === $this->get_option('show_days')) {
                $label .= ' ' . sprintf(
                    _n('(%d dia útil)', '(%d dias úteis)', $faixa['prazo'], 'autocep'),
                    $faixa['prazo']
                );
            }

            $meta = array();

            if ($faixa['prazo'] > 0) {
                $meta['_delivery_forecast'] = $faixa['prazo'];
            }

            $this->add_rate(
                array(
                    'id'        => $this->get_rate_id('' !== $faixa['nome'] ? sanitize_title($faixa['nome']) : ''),
                    'label'     => $label,
                    'cost'      => max(0, $faixa['valor'] + $taxa),
                    'package'   => $package,
                    'meta_data' => $meta,
                )
            );
        }
    }
}
