<?php
if (!defined('ABSPATH')) exit;

class AERP_Order_Status_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_save_order_status'])) return;
        if (!wp_verify_nonce($_POST['aerp_save_order_status_nonce'], 'aerp_save_order_status_action')) wp_die('Invalid nonce for order status save.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_statuses';
        $id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'color' => sanitize_hex_color($_POST['color']),
            'description' => sanitize_text_field($_POST['description']),
        ];
        $format = ['%s', '%s', '%s', '%d'];
        if ($id) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật trạng thái!';
        } else {
            $wpdb->insert($table, $data, $format);
            $msg = 'Đã thêm trạng thái!';
        }
        aerp_clear_table_cache();
        set_transient('aerp_order_status_message', $msg, 10);
        wp_redirect(home_url('/aerp-order-statuses'));
        exit;
    }
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_order_status_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_status_by_id($id)) {
                $message = 'Đã xóa trạng thái thành công!';
            } else {
                $message = 'Không thể xóa trạng thái.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_order_status_message', $message, 10);
            wp_redirect(home_url('/aerp-order-statuses'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_status_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_order_statuses', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function get_by_id($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_statuses';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    public static function get_all()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_statuses';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }
} 