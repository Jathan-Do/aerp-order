<?php
if (!defined('ABSPATH')) exit;

class AERP_Device_Progress_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_save_progress'])) return;
        if (!wp_verify_nonce($_POST['aerp_save_progress_nonce'], 'aerp_save_progress_action')) wp_die('Invalid nonce for progress save.');
        
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_device_progresses';
        $id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;
        
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'color' => sanitize_hex_color($_POST['color']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        
        $format = ['%s', '%s', '%s', '%d'];
        
        if ($id) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật tiến độ!';
        } else {
            $wpdb->insert($table, $data, $format);
            $msg = 'Đã thêm tiến độ!';
        }
        
        aerp_clear_table_cache();
        set_transient('aerp_device_progress_message', $msg, 10);
        wp_redirect(home_url('/aerp-device-progresses'));
        exit;
    }

    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_progress_' . $id;
        
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_progress_by_id($id)) {
                $message = 'Đã xóa tiến độ thành công!';
            } else {
                $message = 'Không thể xóa tiến độ.';
            }
            
            aerp_clear_table_cache();
            set_transient('aerp_device_progress_message', $message, 10);
            wp_redirect(home_url('/aerp-device-progresses'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }

    public static function delete_progress_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_order_device_progresses', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }

    public static function get_by_id($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_device_progresses';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }

    public static function get_all()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_device_progresses';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }

    public static function get_active()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_device_progresses';
        return $wpdb->get_results("SELECT * FROM $table WHERE is_active = 1 ORDER BY name ASC");
    }
}
