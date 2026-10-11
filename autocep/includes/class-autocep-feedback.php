<?php

if (!defined('ABSPATH')) {
    exit;
}

class AutoCEP_Feedback
{
    const MAX_FEEDBACK_ROWS = 200;

    public static function get_table_name()
    {
        global $wpdb;

        return $wpdb->prefix . 'autocep_feedback';
    }

    public static function create_table()
    {
        global $wpdb;

        $table_name      = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nota TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            comentario TEXT NOT NULL,
            sugestao TEXT NOT NULL,
            criado_em DATETIME NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function create_feedback($nota, $comentario, $sugestao)
    {
        global $wpdb;

        $wpdb->insert(
            self::get_table_name(),
            array(
                'nota'       => max(1, min(5, (int) $nota)),
                'comentario' => sanitize_textarea_field($comentario),
                'sugestao'   => sanitize_textarea_field($sugestao),
                'criado_em'  => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s')
        );

        self::prune_old_feedback();

        return (int) $wpdb->insert_id;
    }

    public static function get_feedback($limit = 20)
    {
        global $wpdb;

        $table_name = self::get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d", $limit)
        );
    }

    private static function prune_old_feedback()
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $total      = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        if ($total <= self::MAX_FEEDBACK_ROWS) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} ORDER BY id ASC LIMIT %d",
                $total - self::MAX_FEEDBACK_ROWS
            )
        );
    }
}
