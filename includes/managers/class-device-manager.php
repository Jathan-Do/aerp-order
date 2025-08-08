<?php
if (!defined('ABSPATH')) exit;

class AERP_Device_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_save_device'])) return;
        if (!wp_verify_nonce($_POST['aerp_save_device_nonce'], 'aerp_save_device_action')) wp_die('Invalid nonce for device save.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_devices';
        $id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;
        $data = [
            'device_name' => sanitize_text_field($_POST['device_name']),
            'serial_number' => sanitize_text_field($_POST['serial_number']),
            'status' => sanitize_text_field($_POST['status']),
            'note' => sanitize_text_field($_POST['note']),
            'partner_id' => sanitize_text_field($_POST['partner_id']),
        ];
        $format = ['%s', '%s', '%s', '%d'];
        if ($id) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật thiết bị!';
        } else {
            $wpdb->insert($table, $data, $format);
            $msg = 'Đã thêm thiết bị!';
        }
        aerp_clear_table_cache();
        set_transient('aerp_device_message', $msg, 10);
        wp_redirect(home_url('/aerp-devices'));
        exit;
    }
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_device_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_device_by_id($id)) {
                $message = 'Đã xóa thiết bị thành công!';
            } else {
                $message = 'Không thể xóa thiết bị.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_device_message', $message, 10);
            wp_redirect(home_url('/aerp-devices'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_device_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_order_devices', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function get_by_id($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_devices';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    public static function get_all()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_devices';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY device_name ASC");
    }
} 