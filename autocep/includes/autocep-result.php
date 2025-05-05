<?php
function autocep_send_result($data, $cep) {
    $result = array(
        'cep' => $cep,
        'logradouro' => $data['logradouro'] ?? '',
        'bairro' => $data['bairro'] ?? '',
        'localidade' => $data['localidade'] ?? '',
        'uf' => $data['uf'] ?? '',
    );

    error_log('Endereço encontrado: ' . print_r($result, true));
    wp_send_json_success($result);
}
?>
