<?php
if (!defined('ABSPATH')) exit;

class AERP_Product_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_save_product'])) return;
        if (!wp_verify_nonce($_POST['aerp_save_product_nonce'], 'aerp_save_product_action')) wp_die('Invalid nonce for product save.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_products';
        $id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'sku' => sanitize_text_field($_POST['sku']),
            'price' => floatval($_POST['price']),
            'category_id' => isset($_POST['category_id']) ? intval($_POST['category_id']) : null,
            'unit_id' => isset($_POST['unit_id']) ? intval($_POST['unit_id']) : null,
        ];
        $format = ['%s', '%s', '%f', '%d', '%d'];
        if ($id) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật sản phẩm!';
        } else {
            $wpdb->insert($table, $data, $format);
            $new_product_id = $wpdb->insert_id;
            $msg = 'Đã thêm sản phẩm!';
        }
        aerp_clear_table_cache();
        set_transient('aerp_product_message', $msg, 10);
        wp_redirect(home_url('/aerp-products'));
        exit;
    }
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_product_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_product_by_id($id)) {
                $message = 'Đã xóa sản phẩm thành công!';
            } else {
                $message = 'Không thể xóa sản phẩm.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_product_message', $message, 10);
            wp_redirect(home_url('/aerp-products'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_product_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_products', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function get_by_id($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_products';
        $unit_table = $wpdb->prefix . 'aerp_units';
        return $wpdb->get_row($wpdb->prepare("SELECT p.*, u.name as unit_name FROM $table p LEFT JOIN $unit_table u ON p.unit_id = u.id WHERE p.id = %d", $id));
    }
    public static function get_all_categories() {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_product_categories';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }
    public static function get_all_units() {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_units';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }
    public static function get_category_name($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_product_categories';
        return $wpdb->get_var($wpdb->prepare("SELECT name FROM $table WHERE id = %d", $id));
    }
    public static function get_unit_name($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_units';
        return $wpdb->get_var($wpdb->prepare("SELECT name FROM $table WHERE id = %d", $id));
    }
    public static function get_product_name($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_products';
        return $wpdb->get_var($wpdb->prepare("SELECT name FROM $table WHERE id = %d", $id));
    }
} 