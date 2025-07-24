<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('AERP_Warehouse_Manager')) {
    class AERP_Warehouse_Manager
    {
        public static function handle_form_submit()
        {
            if (!isset($_POST['aerp_save_warehouse'])) return;
            if (!wp_verify_nonce($_POST['aerp_save_warehouse_nonce'], 'aerp_save_warehouse_action')) wp_die('Invalid nonce for warehouse save.');
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_warehouses';
            $id = isset($_POST['edit_id']) ? absint($_POST['edit_id']) : 0;
            $data = [
                'name' => sanitize_text_field($_POST['name']),
                'work_location_id' => absint($_POST['work_location_id']), // ✅ dùng ID
            ];
            $format = ['%s', '%d'];

            if ($id) {
                $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
                $msg = 'Đã cập nhật kho!';
            } else {
                $wpdb->insert($table, $data, $format);
                $id = $wpdb->insert_id;
                $msg = 'Đã thêm kho!';
            }
            // --- Lưu user_ids vào bảng trung gian ---
            $user_ids = isset($_POST['user_ids']) ? (array)$_POST['user_ids'] : [];
            $user_ids = array_filter(array_map('intval', $user_ids));
            $manager_table = $wpdb->prefix . 'aerp_warehouse_managers';
            // Xóa hết user cũ của kho này
            $wpdb->delete($manager_table, ['warehouse_id' => $id]);
            // Thêm user mới
            foreach ($user_ids as $user_id) {
                $wpdb->insert($manager_table, [
                    'user_id' => $user_id,
                    'warehouse_id' => $id
                ], ['%d', '%d']);
            }
            // --- END ---
            aerp_clear_table_cache();
            set_transient('aerp_warehouse_message', $msg, 10);
            wp_redirect(home_url('/aerp-warehouses'));
            exit;
        }
        public static function handle_single_delete()
        {
            $id = absint($_GET['id'] ?? 0);
            $nonce_action = 'delete_warehouse_' . $id;
            if ($id && check_admin_referer($nonce_action)) {
                if (self::delete_by_id($id)) {
                    $message = 'Đã xóa kho thành công!';
                } else {
                    $message = 'Không thể xóa kho.';
                }
                aerp_clear_table_cache();
                set_transient('aerp_warehouse_message', $message, 10);
                wp_redirect(home_url('/aerp-warehouses'));
                exit;
            }
            wp_die('Invalid request or nonce.');
        }
        public static function delete_by_id($id)
        {
            global $wpdb;
            $deleted = $wpdb->delete($wpdb->prefix . 'aerp_warehouses', ['id' => absint($id)]);
            aerp_clear_table_cache();
            return (bool)$deleted;
        }
        public static function get_by_id($id)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_warehouses';
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        }
        public static function get_all()
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_warehouses';
            return $wpdb->get_results("SELECT * FROM $table ORDER BY name ASC");
        }
        public static function get_warehouse_name($id)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_warehouses';
            return $wpdb->get_var($wpdb->prepare("SELECT name FROM $table WHERE id = %d", $id));
        }
        public static function get_full_warehouse_name($id)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_warehouses';

            // Lấy thông tin kho
            $warehouse = $wpdb->get_row(
                $wpdb->prepare("SELECT name, work_location_id FROM $table WHERE id = %d", $id)
            );

            if (!$warehouse) return '';

            $location_name = '';
            if ($warehouse->work_location_id) {
                $location_name = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT name FROM {$wpdb->prefix}aerp_hrm_work_locations WHERE id = %d",
                        $warehouse->work_location_id
                    )
                );
            }
            return esc_html($warehouse->name . ($location_name ? " ({$location_name})" : ''));
        }
        public static function aerp_get_warehouses_by_user($user_id)
        {
            global $wpdb;
            $table = $wpdb->prefix . 'aerp_warehouses';
            $manager_table = $wpdb->prefix . 'aerp_warehouse_managers';
            return $wpdb->get_results($wpdb->prepare(
                "SELECT w.* FROM $table w
                 INNER JOIN $manager_table m ON w.id = m.warehouse_id
                 WHERE m.user_id = %d
                 ORDER BY w.name ASC",
                $user_id
            ));
        }
    }
}
