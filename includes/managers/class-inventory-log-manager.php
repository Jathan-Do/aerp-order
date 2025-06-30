<?php
if (!defined('ABSPATH')) exit;

class AERP_Inventory_Log_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_save_inventory_log'])) return;
        if (!wp_verify_nonce($_POST['aerp_save_inventory_log_nonce'], 'aerp_save_inventory_log_action')) wp_die('Invalid nonce for inventory log.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_inventory_logs';
        $product_table = $wpdb->prefix . 'aerp_products';
        $id = isset($_POST['log_id']) ? absint($_POST['log_id']) : 0;
        $product_id = absint($_POST['product_id']);
        $type = sanitize_text_field($_POST['type']);
        $quantity = intval($_POST['quantity']);
        $note = sanitize_textarea_field($_POST['note']);
        $created_by = get_current_user_id();
        $data = [
            'product_id' => $product_id,
            'type' => $type,
            'quantity' => $quantity,
            'note' => $note,
            'created_by' => $created_by,
            'created_at' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
        ];
        $format = ['%d', '%s', '%d', '%s', '%d', '%s'];
        if ($id) {
            // Không cho sửa log nhập/xuất kho để đảm bảo an toàn
            wp_die('Không cho phép sửa phiếu nhập/xuất kho!');
        } else {
            $wpdb->insert($table, $data, $format);
            // Cập nhật tồn kho sản phẩm
            if ($type === 'import') {
                $wpdb->query($wpdb->prepare("UPDATE $product_table SET quantity = quantity + %d WHERE id = %d", $quantity, $product_id));
            } elseif ($type === 'export') {
                // Kiểm tra tồn kho đủ mới cho xuất
                $current_qty = $wpdb->get_var($wpdb->prepare("SELECT quantity FROM $product_table WHERE id = %d", $product_id));
                if ($current_qty < $quantity) {
                    wp_die('Không đủ tồn kho để xuất!');
                }
                $wpdb->query($wpdb->prepare("UPDATE $product_table SET quantity = quantity - %d WHERE id = %d", $quantity, $product_id));
            }
        }
        aerp_clear_table_cache();
        set_transient('aerp_inventory_log_message', 'Đã ghi nhận phiếu nhập/xuất kho!', 10);
        wp_redirect(home_url('/aerp-inventory-logs'));
        exit;
    }
    public static function delete_log_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_inventory_logs', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
} 