<?php
if (!defined('ABSPATH')) exit;

class AERP_Unit_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_save_unit'])) return;
        if (!wp_verify_nonce($_POST['aerp_save_unit_nonce'], 'aerp_save_unit_action')) wp_die('Invalid nonce for unit save.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_units';
        $id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'symbol' => sanitize_text_field($_POST['symbol']),
        ];
        $format = ['%s', '%s'];
        if ($id) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật đơn vị!';
        } else {
            $wpdb->insert($table, $data, $format);
            $msg = 'Đã thêm đơn vị!';
        }
        aerp_clear_table_cache();
        set_transient('aerp_unit_message', $msg, 10);
        wp_redirect(home_url('/aerp-units'));
        exit;
    }
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_unit_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_unit_by_id($id)) {
                $message = 'Đã xóa đơn vị thành công!';
            } else {
                $message = 'Không thể xóa đơn vị.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_unit_message', $message, 10);
            wp_redirect(home_url('/aerp-units'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_unit_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_units', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function get_by_id($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_units';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    public static function get_all()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_units';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
    }
} 