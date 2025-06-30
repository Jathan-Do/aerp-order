<?php
if (!defined('ABSPATH')) exit;

class AERP_Category_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_save_category'])) return;
        if (!wp_verify_nonce($_POST['aerp_save_category_nonce'], 'aerp_save_category_action')) wp_die('Invalid nonce for category save.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_product_categories';
        $id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'parent_id' => isset($_POST['parent_id']) ? intval($_POST['parent_id']) : null,
        ];
        $format = ['%s', '%d'];
        if ($id) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật danh mục!';
        } else {
            $wpdb->insert($table, $data, $format);
            $msg = 'Đã thêm danh mục!';
        }
        aerp_clear_table_cache();
        set_transient('aerp_category_message', $msg, 10);
        wp_redirect(home_url('/aerp-product-categories'));
        exit;
    }
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_category_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_category_by_id($id)) {
                $message = 'Đã xóa danh mục thành công!';
            } else {
                $message = 'Không thể xóa danh mục.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_category_message', $message, 10);
            wp_redirect(home_url('/aerp-product-categories'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_category_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_product_categories', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function get_by_id($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_product_categories';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    public static function get_all()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_product_categories';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }
} 