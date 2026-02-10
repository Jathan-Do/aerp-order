<?php
if (!defined('ABSPATH')) exit;

class AERP_Device_Return_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_save_device_return'])) return;
        if (!wp_verify_nonce($_POST['aerp_save_device_return_nonce'], 'aerp_save_device_return_action')) wp_die('Invalid nonce for device returnsave.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_device_returns';
        $id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;
        $posted_order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if ($id && !$posted_order_id) {
            // Preserve existing order_id on update if not posted correctly
            $existing = self::get_by_id($id);
            $posted_order_id = $existing ? (int) $existing->order_id : 0;
        }

        $data = [
            'order_id' => $posted_order_id,
            'device_id' => absint($_POST['device_id']),
            'return_date' => sanitize_text_field($_POST['return_date']),
            'note' => sanitize_text_field($_POST['note']),
        ];
        $format = ['%d', '%d', '%s', '%s'];
        if ($id) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật thiết bị trả lại!';
        } else {
            $wpdb->insert($table, $data, $format);
            $msg = 'Đã thêm thiết bị trả lại!';
        }
        aerp_clear_table_cache();
        set_transient('aerp_device_return_message', $msg, 10);
        wp_redirect(home_url('/aerp-device-returns'));
        exit;
    }
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_device_return_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_device_return_by_id($id)) {
                $message = 'Đã xóa thiết bị trả lại thành công!';
            } else {
                $message = 'Không thể xóa thiết bị trả lại.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_device_return_message', $message, 10);
            wp_redirect(home_url('/aerp-device-returns'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_device_return_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_order_device_returns', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function get_by_id($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_device_returns';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    public static function get_all()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_device_returns';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY return_date ASC");
    }
} 