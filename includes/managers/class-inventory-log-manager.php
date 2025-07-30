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
        $user_id = get_current_user_id();
        $employee_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $user_id
        ));
        $id = isset($_POST['log_id']) ? absint($_POST['log_id']) : 0;
        $product_id = absint($_POST['product_id']);
        $warehouse_id = absint($_POST['warehouse_id']);
        $supplier_id = isset($_POST['supplier_id']) ? absint($_POST['supplier_id']) : null;
        $type = sanitize_text_field($_POST['type']);
        $quantity = intval($_POST['quantity']);
        $note = sanitize_textarea_field($_POST['note']);
        $created_by = $employee_id;
        $now = (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');

        $data = [
            'product_id'   => $product_id,
            'warehouse_id' => $warehouse_id,
            'supplier_id'  => $supplier_id,
            'type'         => $type,
            'quantity'     => $quantity,
            'note'         => $note,
            'created_by'   => $created_by,
            'status'       => 'draft',
        ];
        $format = ['%d', '%d', '%d', '%s', '%d', '%s', '%d', '%s'];

        if ($id) {
            $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
            if (!$log || $log->status !== 'draft') {
                wp_die('Không được sửa phiếu đã xác nhận!');
            }

            $data['created_at'] = $log->created_at;
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($table, $data, array_merge($format, ['%s']));
        }

        aerp_clear_table_cache();
        set_transient('aerp_inventory_log_message', 'Phiếu đã được lưu (nháp hoặc cập nhật nháp).', 10);
        wp_redirect(home_url('/aerp-inventory-logs'));
        exit;
    }

    public static function handle_confirm_submit()
    {
        if (!isset($_POST['aerp_confirm_inventory_log'])) return;

        global $wpdb;
        $id = absint($_POST['log_id']);
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_inventory_logs WHERE id = %d", $id));

        if (!$log || $log->status === 'confirmed') {
            wp_die('Phiếu không tồn tại hoặc đã xác nhận');
        }

        $type = $log->type;
        $now  = (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');

        $product_id   = isset($_POST['product_id'])   ? absint($_POST['product_id'])   : intval($log->product_id);
        $warehouse_id = isset($_POST['warehouse_id']) ? absint($_POST['warehouse_id']) : intval($log->warehouse_id);
        $supplier_id  = isset($_POST['supplier_id'])  ? absint($_POST['supplier_id'])  : (isset($log->supplier_id) ? intval($log->supplier_id) : null);
        $note         = isset($_POST['note'])         ? sanitize_textarea_field($_POST['note']) : $log->note;

        $stock_table = $wpdb->prefix . 'aerp_product_stocks';
        $system_qty = $wpdb->get_var($wpdb->prepare(
            "SELECT quantity FROM $stock_table WHERE product_id = %d AND warehouse_id = %d",
            $product_id, $warehouse_id
        ));
        if ($system_qty === null) $system_qty = 0;

        if ($type === 'stocktake' && isset($_POST['actual_qty'])) {
            $actual_qty = intval($_POST['actual_qty']);
            $quantity = $actual_qty - $system_qty;
        } else {
            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : intval($log->quantity);
        }

        // Dùng chung logic kiểm tra $has_stock như phần kiểm kho
        $has_stock = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $stock_table WHERE product_id = %d AND warehouse_id = %d",
            $product_id, $warehouse_id
        ));
        if ($type === 'import') {
            if ($has_stock) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $stock_table SET quantity = quantity + %d, updated_at = %s WHERE product_id = %d AND warehouse_id = %d",
                    $quantity, $now, $product_id, $warehouse_id
                ));
            } else {
                $wpdb->insert($stock_table, [
                    'product_id' => $product_id,
                    'warehouse_id' => $warehouse_id,
                    'quantity' => $quantity,
                    'updated_at' => $now,
                ]);
            }
        } elseif ($type === 'export') {
            if ($system_qty === null || $system_qty < $quantity) {
                wp_die('Không đủ tồn kho để xuất!');
            }
            $wpdb->query($wpdb->prepare(
                "UPDATE $stock_table SET quantity = quantity - %d, updated_at = %s WHERE product_id = %d AND warehouse_id = %d",
                $quantity, $now, $product_id, $warehouse_id
            ));
        } elseif ($type === 'stocktake') {
            $actual_qty = $system_qty + $quantity;
            // $has_stock = $wpdb->get_var($wpdb->prepare(
            //     "SELECT COUNT(*) FROM $stock_table WHERE product_id = %d AND warehouse_id = %d",
            //     $product_id, $warehouse_id
            // ));
            if ($has_stock) {
                $wpdb->update(
                    $stock_table,
                    ['quantity' => $actual_qty, 'updated_at' => $now],
                    ['product_id' => $product_id, 'warehouse_id' => $warehouse_id]
                );
            } else {
                $wpdb->insert($stock_table, [
                    'product_id'   => $product_id,
                    'warehouse_id' => $warehouse_id,
                    'quantity'     => $actual_qty,
                    'updated_at'   => $now,
                ]);
            }
        }

        $wpdb->update($wpdb->prefix . 'aerp_inventory_logs', [
            'quantity' => $quantity,
            'note'     => $note,
            'product_id' => $product_id,
            'warehouse_id' => $warehouse_id,
            'supplier_id' => $supplier_id,
            'status'   => 'confirmed'
        ], ['id' => $id]);

        aerp_clear_table_cache();
        set_transient('aerp_inventory_log_message', 'Phiếu đã được xác nhận thành công!', 10);
        wp_redirect(home_url('/aerp-inventory-logs'));
        exit;
    }

    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_inventory_log_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_log_by_id($id)) {
                $message = 'Đã xóa phiếu thành công!';
            } else {
                $message = 'Không thể xóa phiếu.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_inventory_log_message', $message, 10);
            wp_redirect(home_url('/aerp-inventory-logs'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }

    public static function delete_log_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_inventory_logs', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }

    public static function handle_stocktake_submit($post)
    {
        if (!isset($post['aerp_save_stocktake'])) return false;
        if (!wp_verify_nonce($post['aerp_save_stocktake_nonce'], 'aerp_save_stocktake_action')) wp_die('Invalid nonce for stocktake.');

        global $wpdb;

        $notes = sanitize_textarea_field($post['note'] ?? '');
        $user_id = get_current_user_id();
        $employee_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $user_id
        ));
        $changed = 0;
        $warehouse_id = absint($post['warehouse_id'] ?? 0);
        $product_id   = absint($post['product_id'] ?? 0);
        $actual_qty   = intval($post['actual_qty'] ?? 0);

        if (!$warehouse_id || !$product_id) {
            return 0;
        }

        $current_qty = $wpdb->get_var($wpdb->prepare(
            "SELECT quantity FROM {$wpdb->prefix}aerp_product_stocks WHERE product_id = %d AND warehouse_id = %d",
            $product_id, $warehouse_id
        ));
        if ($current_qty === null) $current_qty = 0;

        $diff = $actual_qty - intval($current_qty);
        if ($diff !== 0) {
            $wpdb->insert($wpdb->prefix . 'aerp_inventory_logs', [
                'product_id'   => $product_id,
                'warehouse_id' => $warehouse_id,
                'type'         => 'stocktake',
                'quantity'     => $diff,
                'note'         => $notes,
                'created_by'   => $employee_id,
                'status'       => 'draft',
                'created_at'   => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
            ]);
            $changed++;
        }

        aerp_clear_table_cache();
        return $changed;
    }

    public static function get_log_by_id($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aerp_inventory_logs WHERE id = %d",
            absint($id)
        ));
    }
}
