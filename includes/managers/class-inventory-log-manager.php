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
        $stock_table = $wpdb->prefix . 'aerp_product_stocks';
        $id = isset($_POST['log_id']) ? absint($_POST['log_id']) : 0;
        $product_id = absint($_POST['product_id']);
        $warehouse_id = absint($_POST['warehouse_id']);
        $type = sanitize_text_field($_POST['type']);
        $quantity = intval($_POST['quantity']);
        $note = sanitize_textarea_field($_POST['note']);
        $created_by = get_current_user_id();
        $data = [
            'product_id' => $product_id,
            'warehouse_id' => $warehouse_id,
            'type' => $type,
            'quantity' => $quantity,
            'note' => $note,
            'created_by' => $created_by,
            'created_at' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
        ];
        $format = ['%d', '%d', '%s', '%d', '%s', '%d', '%s'];
        if ($id) {
            wp_die('Không cho phép sửa phiếu nhập/xuất kho!');
        } else {
            $wpdb->insert($table, $data, $format);
            // Cập nhật tồn kho sản phẩm theo kho
            $stock = $wpdb->get_var($wpdb->prepare(
                "SELECT quantity FROM $stock_table WHERE product_id = %d AND warehouse_id = %d",
                $product_id,
                $warehouse_id
            ));
            if ($type === 'import') {
                if ($stock !== null) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE $stock_table SET quantity = quantity + %d, updated_at = %s WHERE product_id = %d AND warehouse_id = %d",
                        $quantity,
                        current_time('mysql', 1),
                        $product_id,
                        $warehouse_id
                    ));
                } else {
                    $wpdb->insert($stock_table, [
                        'product_id' => $product_id,
                        'warehouse_id' => $warehouse_id,
                        'quantity' => $quantity,
                        'updated_at' => current_time('mysql', 1)
                    ]);
                }
            } elseif ($type === 'export') {
                if ($stock === null || $stock < $quantity) {
                    wp_die('Không đủ tồn kho để xuất!');
                }
                $wpdb->query($wpdb->prepare(
                    "UPDATE $stock_table SET quantity = quantity - %d, updated_at = %s WHERE product_id = %d AND warehouse_id = %d",
                    $quantity,
                    current_time('mysql', 1),
                    $product_id,
                    $warehouse_id
                ));
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
    // public static function handle_stocktake_submit($post)
    // {
    //     if (!isset($post['aerp_save_stocktake'])) return false;
    //     if (!wp_verify_nonce($post['aerp_save_stocktake_nonce'], 'aerp_save_stocktake_action')) wp_die('Invalid nonce for stocktake.');
    //     global $wpdb;
    //     $products = $post['products'] ?? [];
    //     $notes = sanitize_textarea_field($post['note'] ?? '');
    //     $user_id = get_current_user_id();
    //     $changed = 0;
    //     foreach ($products as $row) {
    //         $product_id = absint($row['product_id']);
    //         $actual_qty = intval($row['actual_qty']);
    //         $product = $wpdb->get_row($wpdb->prepare("SELECT quantity FROM {$wpdb->prefix}aerp_products WHERE id = %d", $product_id));
    //         if ($product) {
    //             $diff = $actual_qty - intval($product->quantity);
    //             if ($diff !== 0) {
    //                 $wpdb->insert($wpdb->prefix . 'aerp_inventory_logs', [
    //                     'product_id' => $product_id,
    //                     'type' => 'stocktake',
    //                     'quantity' => $diff,
    //                     'note' => $notes,
    //                     'created_by' => $user_id,
    //                     'created_at' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
    //                 ]);
    //                 $wpdb->update($wpdb->prefix . 'aerp_products', [
    //                     'quantity' => $actual_qty
    //                 ], ['id' => $product_id]);
    //                 $changed++;
    //             }
    //         }
    //     }
    //     aerp_clear_table_cache();
    //     return $changed;
    // }
    public static function handle_stocktake_submit($post)
    {
        if (!isset($post['aerp_save_stocktake'])) return false;
        if (!wp_verify_nonce($post['aerp_save_stocktake_nonce'], 'aerp_save_stocktake_action')) wp_die('Invalid nonce for stocktake.');

        global $wpdb;
        $products = $post['products'] ?? [];


        $notes = sanitize_textarea_field($post['note'] ?? '');
        $user_id = get_current_user_id();
        $changed = 0;

        $stock_table = $wpdb->prefix . 'aerp_product_stocks';
        $logs_table  = $wpdb->prefix . 'aerp_inventory_logs';
        foreach ($products as $row) {
            $warehouse_id = absint($row['warehouse_id']);
            $product_id   = absint($row['product_id']);
            $actual_qty   = intval($row['actual_qty']);

            if (!$warehouse_id || !$product_id) continue;

            $current_qty = $wpdb->get_var($wpdb->prepare(
                "SELECT quantity FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
                $product_id,
                $warehouse_id
            ));
            if ($current_qty === null) $current_qty = 0;

            $diff = $actual_qty - intval($current_qty);

            if ($diff !== 0) {
                // ✅ Luôn log trước
                $wpdb->insert($wpdb->prefix . 'aerp_inventory_logs', [
                    'product_id'   => $product_id,
                    'warehouse_id' => $warehouse_id,
                    'type'         => 'stocktake',
                    'quantity'     => $diff,
                    'note'         => $notes,
                    'created_by'   => $user_id,
                    'created_at'   => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
                ]);

                // ✅ Update hoặc insert tồn kho
                $has_stock = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
                    $product_id,
                    $warehouse_id
                ));
                if ($has_stock) {
                    $wpdb->update(
                        $wpdb->prefix . 'aerp_product_stocks',
                        [
                            'quantity'   => $actual_qty,
                            'updated_at' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
                        ],
                        [
                            'product_id'   => $product_id,
                            'warehouse_id' => $warehouse_id
                        ]
                    );
                } else {
                    $wpdb->insert($wpdb->prefix . 'aerp_product_stocks', [
                        'product_id'   => $product_id,
                        'warehouse_id' => $warehouse_id,
                        'quantity'     => $actual_qty,
                        'updated_at'   => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
                    ]);
                }

                $changed++;
            }
        }
        aerp_clear_table_cache();
        return $changed;
    }
}
