<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('AERP_Implementation_Template_Manager')) {
    class AERP_Implementation_Template_Manager
    {
        public static function get_all()
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_implementation_templates';
            return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
        }

        public static function get_by_id($id)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_implementation_templates';
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        }

        public static function delete($id)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_implementation_templates';
            $deleted = $wpdb->delete($table, ['id' => absint($id)]);
            aerp_clear_table_cache();
            return (bool)$deleted;
        }

        public static function handle_form_submit()
        {
            if (!isset($_POST['aerp_save_implementation_template'])) return;
            if (!wp_verify_nonce($_POST['aerp_save_implementation_template_nonce'], 'aerp_save_implementation_template_action')) wp_die('Invalid nonce for implementation template save.');

            global $wpdb;
            $table = $wpdb->prefix . 'aerp_implementation_templates';
            $id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;

            $data = [
                'name'      => sanitize_text_field($_POST['name']),
                'content'   => sanitize_textarea_field($_POST['content']),
                'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            ];

            $user_id = get_current_user_id();
            $employee_current_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $user_id
            ));
            if ($id) {
                $wpdb->update($table, $data, ['id' => $id]);
                $msg = 'Đã cập nhật template nội dung triển khai!';
            } else {
                $data['created_by'] = $employee_current_id;
                $data['created_at'] = (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');
                $wpdb->insert($table, $data);
                $msg = 'Đã thêm template nội dung triển khai!';
            }

            aerp_clear_table_cache();
            set_transient('aerp_implementation_template_message', $msg, 10);
            wp_redirect(home_url('/aerp-implementation-templates'));
            exit;
        }

        public static function handle_single_delete()
        {
            $id = absint($_GET['id'] ?? 0);
            $nonce_action = 'delete_implementation_template_' . $id;
            if ($id && check_admin_referer($nonce_action)) {
                if (self::delete($id)) {
                    $message = 'Đã xóa template thành công!';
                } else {
                    $message = 'Không thể xóa template.';
                }
                aerp_clear_table_cache();
                set_transient('aerp_implementation_template_message', $message, 10);
                wp_redirect(home_url('/aerp-implementation-templates'));
                exit;
            }
            wp_die('Invalid request or nonce.');
        }
    }
}
