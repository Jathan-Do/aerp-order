<?php
if (!defined('ABSPATH')) exit;

class AERP_Acc_Category_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_acc_save_category'])) return;
        if (!wp_verify_nonce($_POST['aerp_acc_save_category_nonce'], 'aerp_acc_save_category_action')) wp_die('Invalid nonce for acc category save.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_acc_categories';
        $id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'code' => sanitize_text_field($_POST['code'] ?? ''),
            'is_accounted' => isset($_POST['is_accounted']) ? 1 : 0,
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        $format = ['%s','%s','%d','%d'];
        if ($id) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật danh mục chi!';
        } else {
            $wpdb->insert($table, $data, $format);
            $msg = 'Đã thêm danh mục chi!';
        }
        aerp_clear_table_cache();
        set_transient('aerp_acc_category_message', $msg, 10);
        wp_redirect(home_url('/aerp-acc-categories'));
        exit;
    }
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_acc_category_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_by_id($id)) {
                $message = 'Đã xóa danh mục chi thành công!';
            } else {
                $message = 'Không thể xóa danh mục chi.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_acc_category_message', $message, 10);
            wp_redirect(home_url('/aerp-acc-categories'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_acc_categories', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function get_by_id($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_acc_categories WHERE id = %d", $id));
    }
    public static function get_name($id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}aerp_acc_categories WHERE id = %d", $id));
    }
}


