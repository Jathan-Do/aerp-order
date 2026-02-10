<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('AERP_Inventory_Transfer_Manager')) {
    class AERP_Inventory_Transfer_Manager
    {
        public static function handle_form_submit()
        {
            if (!isset($_POST['aerp_save_inventory_transfer'])) return;
            if (!wp_verify_nonce($_POST['aerp_save_inventory_transfer_nonce'], 'aerp_save_inventory_transfer_action')) wp_die('Invalid nonce for transfer.');

            global $wpdb;
            $transfer_table = $wpdb->prefix . 'aerp_inventory_transfers';
            $transfer_items_table = $wpdb->prefix . 'aerp_inventory_transfer_items';
            $stocks_table = $wpdb->prefix . 'aerp_product_stocks';

            $from_warehouse_id = absint($_POST['warehouse_id']);
            $to_warehouse_id = absint($_POST['to_warehouse_id']);
            $products = $_POST['products'] ?? [];
            $note = sanitize_textarea_field($_POST['note']);
            $user_id = get_current_user_id();

            if ($from_warehouse_id === $to_warehouse_id) {
                wp_die('Kho chuyển và kho nhận không được trùng nhau.');
            }

            // 1) Pre-validate tất cả dòng để gom lỗi trước
            $insufficient = [];
            foreach ($products as $product) {
                $product_id = absint($product['product_id'] ?? 0);
                $quantity   = intval($product['quantity'] ?? 0);
                if (!$product_id || $quantity <= 0) {
                    continue;
                }
                $current_stock = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT quantity FROM $stocks_table WHERE product_id = %d AND warehouse_id = %d",
                    $product_id,
                    $from_warehouse_id
                ));
                $product_name = $wpdb->get_var($wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}aerp_products WHERE id = %d",
                    $product_id
                ));
                if ($current_stock < $quantity) {
                    $insufficient[] = [
                        'name' => $product_name,
                        'current' => $current_stock,
                        'need' => $quantity,
                    ];
                }
            }

            if (!empty($insufficient)) {
                // Gộp thông điệp chi tiết cho tất cả sản phẩm thiếu tồn
                $lines = array_map(function ($row) {
                    return sprintf('%s: tồn %d, yêu cầu %d', $row['name'], $row['current'], $row['need']);
                }, $insufficient);
                $message = 'Không đủ tồn kho!' .  $lines;
                aerp_clear_table_cache();
                set_transient('aerp_inventory_transfer_message', $message, 10);
                wp_redirect(home_url('/aerp-inventory-transfers?action=add'));
                exit;
            }

            // 2) Không có lỗi -> tạo phiếu và cập nhật tồn kho
            $wpdb->insert($transfer_table, [
                'from_warehouse_id' => $from_warehouse_id,
                'to_warehouse_id'   => $to_warehouse_id,
                'created_by'        => $user_id,
                'created_at'        => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
                'note'              => $note,
            ]);
            $transfer_id = $wpdb->insert_id;

            foreach ($products as $product) {
                $product_id = absint($product['product_id'] ?? 0);
                $quantity   = intval($product['quantity'] ?? 0);
                if ($product_id && $quantity > 0) {
                    // Lưu chi tiết phiếu
                    $wpdb->insert($transfer_items_table, [
                        'transfer_id' => $transfer_id,
                        'product_id'  => $product_id,
                        'quantity'    => $quantity
                    ]);

                    // Trừ kho xuất
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $stocks_table SET quantity = quantity - %d, updated_at = %s WHERE product_id = %d AND warehouse_id = %d",
                        $quantity,
                        (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
                        $product_id,
                        $from_warehouse_id
                    ));

                    // Cộng kho nhập
                    $exists = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $stocks_table WHERE product_id = %d AND warehouse_id = %d",
                        $product_id,
                        $to_warehouse_id
                    ));
                    if ($exists) {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $stocks_table SET quantity = quantity + %d, updated_at = %s WHERE product_id = %d AND warehouse_id = %d",
                            $quantity,
                            current_time('mysql', 1),
                            $product_id,
                            $to_warehouse_id
                        ));
                    } else {
                        $wpdb->insert($stocks_table, [
                            'product_id'   => $product_id,
                            'warehouse_id' => $to_warehouse_id,
                            'quantity'     => $quantity,
                            'updated_at'   => current_time('mysql', 1)
                        ]);
                    }
                }
            }

            aerp_clear_table_cache();
            set_transient('aerp_inventory_transfer_message', 'Đã tạo phiếu chuyển kho!', 10);
            wp_redirect(home_url('/aerp-inventory-transfers'));
            exit;
        }

        public static function delete_by_id($id)
        {
            global $wpdb;
            $transfer_id = absint($id);
            $wpdb->delete($wpdb->prefix . 'aerp_inventory_transfers', ['id' => $transfer_id]);
            $wpdb->delete($wpdb->prefix . 'aerp_inventory_transfer_items', ['transfer_id' => $transfer_id]);
            aerp_clear_table_cache();
            return true;
        }

        public static function get_by_id($id)
        {
            global $wpdb;
            $transfer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aerp_inventory_transfers WHERE id = %d",
                absint($id)
            ));
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aerp_inventory_transfer_items WHERE transfer_id = %d",
                absint($id)
            ));
            return [$transfer, $items];
        }
    }
}
