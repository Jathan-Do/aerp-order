<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('AERP_Supplier_Manager')) {
    class AERP_Supplier_Manager
    {
        public static function get_all()
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_suppliers';
            return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
        }
        public static function get_by_id($id)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_suppliers';
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        }
        public static function delete($id)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_suppliers';
            $deleted = $wpdb->delete($table, ['id' => absint($id)]);
            aerp_clear_table_cache();
            return (bool)$deleted;
        }
        public static function handle_form_submit()
        {
            if (!isset($_POST['aerp_save_supplier'])) return;
            if (!wp_verify_nonce($_POST['aerp_save_supplier_nonce'], 'aerp_save_supplier_action')) wp_die('Invalid nonce for supplier save.');
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_suppliers';
            $id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;
            $data = [
                'name' => sanitize_text_field($_POST['name']),
                'phone' => sanitize_text_field($_POST['phone'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'address' => sanitize_text_field($_POST['address'] ?? ''),
                'note' => sanitize_textarea_field($_POST['note'] ?? ''),
            ];
            if ($id) {
                $wpdb->update($table, $data, ['id' => $id]);
                $msg = 'Đã cập nhật nhà cung cấp!';
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert($table, $data);
                $msg = 'Đã thêm nhà cung cấp!';
            }
            aerp_clear_table_cache();
            set_transient('aerp_supplier_message', $msg, 10);
            wp_redirect(home_url('/aerp-suppliers'));
            exit;
        }
        public static function handle_single_delete()
        {
            $id = absint($_GET['id'] ?? 0);
            $nonce_action = 'delete_supplier_' . $id;
            if ($id && check_admin_referer($nonce_action)) {
                if (self::delete($id)) {
                    $message = 'Đã xóa nhà cung cấp thành công!';
                } else {
                    $message = 'Không thể xóa nhà cung cấp.';
                }
                aerp_clear_table_cache();
                set_transient('aerp_supplier_message', $message, 10);
                wp_redirect(home_url('/aerp-suppliers'));
                exit;
            }
            wp_die('Invalid request or nonce.');
        }
    }
}
