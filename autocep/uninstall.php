<?php
/**
 * Limpeza ao EXCLUIR o plugin (não ao desativar).
 *
 * Remove os dados que o AutoCEP criou: tabelas de estatísticas, logs,
 * chamados, avaliações e funil do checkout; configurações; e caches
 * (transients). Os dados dos PEDIDOS (forma de pagamento na entrega, troco,
 * campos extras do checkout) NÃO são apagados: são registros de venda da loja.
 *
 * Para manter os dados do AutoCEP mesmo excluindo o plugin, adicione ao
 * wp-config.php ou a um plugin de snippets:
 *   add_filter('autocep_keep_data_on_uninstall', '__return_true');
 *
 * @package AutoCEP
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!function_exists('autocep_uninstall_current_site')) {
/**
 * Remove os dados do AutoCEP de UM site.
 */
function autocep_uninstall_current_site()
{
    global $wpdb;

    // Nomes fixos (não vêm de nenhuma entrada do usuário).
    $tabelas = array('autocep_shipping_stats', 'autocep_logs', 'autocep_tickets', 'autocep_feedback', 'autocep_checkout_events');

    foreach ($tabelas as $tabela) {
        $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . $tabela . '`'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- nome de tabela fixo.
    }

    foreach (array('autocep_options', 'autocep_db_version', 'autocep_checkout_events_db', 'autocep_ticket_counter') as $opcao) {
        delete_option($opcao);
    }

    // Configurações da forma de pagamento e dos métodos de envio do AutoCEP
    // ("woocommerce_autocep_..._settings") e todos os caches do plugin.
    $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('woocommerce_autocep_') . '%',
            $wpdb->esc_like('_transient_autocep_') . '%',
            $wpdb->esc_like('_transient_timeout_autocep_') . '%',
            $wpdb->esc_like('autocep_') . '%'
        )
    );
}
}

if (apply_filters('autocep_keep_data_on_uninstall', false)) {
    return;
}

if (is_multisite()) {
    foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $site_id) {
        switch_to_blog($site_id);
        autocep_uninstall_current_site();
        restore_current_blog();
    }
} else {
    autocep_uninstall_current_site();
}
