<?php
function autocep_remote_get($response) {
    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}
?>
