<?php
function autocep_json_error($response) {
    error_log('Erro na busca do endereço: ' . $response->get_error_message());
    wp_send_json_error('Erro na busca do endereço');
}
?>
