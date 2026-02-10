<?php
if (!class_exists('AERP_Product_Stock_Manager')) {
    class AERP_Product_Stock_Manager {
        public static function get_by_warehouse($warehouse_id) {
            global $wpdb;
            return $wpdb->get_results($wpdb->prepare(
                "SELECT ps.*, p.name, p.sku FROM {$wpdb->prefix}aerp_product_stocks ps
                 JOIN {$wpdb->prefix}aerp_products p ON ps.product_id = p.id
                 WHERE ps.warehouse_id = %d", $warehouse_id
            ));
        }
        public static function get($product_id, $warehouse_id) {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
                $product_id, $warehouse_id
            ));
        }
        public static function set($product_id, $warehouse_id, $quantity) {
            global $wpdb;
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
                $product_id, $warehouse_id
            ));
            if ($exists) {
                $wpdb->update($wpdb->prefix . 'aerp_product_stocks', [
                    'quantity' => $quantity,
                    'updated_at' => current_time('mysql', 1)
                ], ['id' => $exists]);
            } else {
                $wpdb->insert($wpdb->prefix . 'aerp_product_stocks', [
                    'product_id' => $product_id,
                    'warehouse_id' => $warehouse_id,
                    'quantity' => $quantity,
                    'updated_at' => current_time('mysql', 1)
                ]);
            }
            aerp_clear_table_cache();
            return (bool)$exists;
        }
        public static function delete_product_stock_by_id($id) {
            global $wpdb;
            $deleted = $wpdb->delete($wpdb->prefix . 'aerp_product_stocks', ['id' => $id]);
            aerp_clear_table_cache();
            return (bool)$deleted;
        }

    }
}