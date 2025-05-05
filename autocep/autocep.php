<?php
/*
* Plugin Name: AutoCEP
* Description: Preenche automaticamente o endereço no checkout com base no CEP digitado.
* Version: 1.0
* Author: TESW | Dev Wanderson Cesar
* Author URI: https://tesw.com.br
* Text Domain: autocep
* License: GPL-2.0+
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/*
Este plugin, todas as bibliotecas incluídas e quaisquer outros ativos incluídos são licenciados como GPL ou estão sob uma licença compatível com GPL.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
*/

if (!defined('ABSPATH')) {
    exit;
}

include_once(plugin_dir_path(__FILE__) . 'includes/autocep-get-address.php');
include_once(plugin_dir_path(__FILE__) . 'includes/autocep-result.php');
include_once(plugin_dir_path(__FILE__) . 'includes/autocep-json-error.php');
include_once(plugin_dir_path(__FILE__) . 'includes/autocep-remote-get.php');

function autocep_enqueue_scripts() {
    if (function_exists('is_checkout') && is_checkout()) {
        wp_enqueue_script('autocep-autocomplete', plugin_dir_url(__FILE__) . 'js/autocomplete.js', array('jquery'), '1.0', true);
        wp_localize_script('autocep-autocomplete', 'autocep_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('autocep_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'autocep_enqueue_scripts');

add_action('wp_ajax_autocep_get_address', 'autocep_get_address');
add_action('wp_ajax_nopriv_autocep_get_address', 'autocep_get_address');

function autocep_plugin_action_links($links) {
    $pro_link = '<a href="https://tesw.com.br/plugins/" target="_blank" style="color: green;">' . esc_html__('Conheça Nossos Plugins', 'autocep') . '</a>';
    $settings_link = '<a href="https://www.linkedin.com/in/wanderson-cesar-2710a621b?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=android_app" target="_blank">' . esc_html__('Perfil no LinkedIn do Dev', 'autocep') . '</a>';
    array_unshift($links, $pro_link, $settings_link);
    return $links;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'autocep_plugin_action_links');
