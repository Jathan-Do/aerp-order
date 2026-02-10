<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit();

if (get_option('aerp_order_delete_data_on_uninstall') != 1) return;

require_once dirname(__FILE__) . '/install-schema.php';

global $wpdb;
$tables = aerp_order_get_table_names();

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS `$table`");
}

// Xóa các option liên quan Order
delete_option('aerp_order_delete_data_on_uninstall'); 