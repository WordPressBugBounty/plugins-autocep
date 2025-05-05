<?php
function autocep_get_address() {
    // Sanitização e validação do nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'autocep_nonce')) {
        error_log('Nonce inválida ou ausente');
        wp_send_json_error('Nonce inválida ou ausente');
    }

    // Verificação se o CEP foi informado
    if (!isset($_POST['cep'])) {
        error_log('CEP não informado');
        wp_send_json_error('CEP não informado');
    }

    // Sanitização e validação do CEP
    $cep = sanitize_text_field(wp_unslash($_POST['cep']));
    if (!preg_match('/^[0-9]{8}$/', $cep)) {
        error_log('CEP inválido: ' . $cep);
        wp_send_json_error('CEP inválido');
    }

    error_log('Buscando endereço para o CEP: ' . $cep);

    // Adiciona um timeout de 20 segundos para a requisição
    $response = wp_remote_get("https://opencep.com/v1/{$cep}.json", array(
        'timeout' => 20
    ));

    // Tratamento de erros na requisição
    if (is_wp_error($response)) {
        include_once(plugin_dir_path(__FILE__) . 'autocep-json-error.php');
        autocep_json_error($response);
    }

    include_once(plugin_dir_path(__FILE__) . 'autocep-remote-get.php');
    $data = autocep_remote_get($response);

    // Verifica se o CEP foi encontrado
    if (isset($data['erro']) && $data['erro'] === true) {
        error_log('CEP não encontrado: ' . $cep);
        wp_send_json_error('CEP não encontrado');
    }

    include_once(plugin_dir_path(__FILE__) . 'autocep-result.php');
    autocep_send_result($data, $cep);
}
?>
