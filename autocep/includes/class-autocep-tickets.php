<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Tickets
{
    const MAX_TICKETS_ROWS = 500;

    public static function get_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'autocep_tickets';
    }

    public static function create_table()
    {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            numero VARCHAR(20) NOT NULL DEFAULT '',
            tipo VARCHAR(30) NOT NULL DEFAULT '',
            titulo VARCHAR(255) NOT NULL DEFAULT '',
            descricao TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'aberto',
            criado_em DATETIME NOT NULL,
            atualizado_em DATETIME NULL DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY numero (numero)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function create_ticket($tipo, $titulo, $descricao)
    {
        global $wpdb;

        $numero = self::generate_ticket_number();

        $wpdb->insert(
            self::get_table_name(),
            array(
                'numero'    => $numero,
                'tipo'      => sanitize_text_field($tipo),
                'titulo'    => sanitize_text_field($titulo),
                'descricao' => sanitize_textarea_field($descricao),
                'status'    => 'aberto',
                'criado_em' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );

        self::prune_old_tickets();

        return $numero;
    }

    private static function generate_ticket_number()
    {
        $contador = (int) get_option('autocep_ticket_counter', 0);
        $contador++;

        update_option('autocep_ticket_counter', $contador, false);

        return 'AC-' . str_pad((string) $contador, 6, '0', STR_PAD_LEFT);
    }

    public static function get_tickets($status = '', $limit = 50)
    {
        global $wpdb;

        $table_name = self::get_table_name();

        if ($status) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE status = %s ORDER BY id DESC LIMIT %d",
                    $status,
                    $limit
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d", $limit)
        );
    }

    public static function count_by_status($status)
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE status = %s", $status)
        );
    }

    public static function set_status($id, $status)
    {
        global $wpdb;

        if (!in_array($status, array('aberto', 'fechado'), true)) {
            return false;
        }

        $atualizado = $wpdb->update(
            self::get_table_name(),
            array(
                'status'        => $status,
                'atualizado_em' => current_time('mysql'),
            ),
            array('id' => (int) $id),
            array('%s', '%s'),
            array('%d')
        );

        return false !== $atualizado;
    }

    private static function prune_old_tickets()
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $total      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($total <= self::MAX_TICKETS_ROWS) {
            return;
        }

        $excedente = $total - self::MAX_TICKETS_ROWS;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} ORDER BY id ASC LIMIT %d",
                $excedente
            )
        );
    }
}
