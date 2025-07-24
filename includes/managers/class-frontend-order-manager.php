<?php
if (!defined('ABSPATH')) exit;

class AERP_Frontend_Order_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_save_order'])) return;
        if (!wp_verify_nonce($_POST['aerp_save_order_nonce'], 'aerp_save_order_action')) wp_die('Invalid nonce for order save.');
        
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_orders';
        $id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;

        // 1. Tính tổng tiền từ sản phẩm
        $total_amount = 0;
        if (!empty($_POST['order_items']) && is_array($_POST['order_items'])) {
            foreach ($_POST['order_items'] as $item) {
                $quantity = floatval($item['quantity'] ?? 0);
                $unit_price = floatval($item['unit_price'] ?? 0);
                $vat_percent = floatval($item['vat_percent'] ?? 0);
                $total_amount += $quantity * $unit_price + ($quantity * $unit_price * $vat_percent / 100);
            }
        }

        // 2. Chuẩn hóa dữ liệu
        $order_date = !empty($_POST['order_date']) ? sanitize_text_field($_POST['order_date']) : date('Y-m-d');
        
        if ($id) {
            // Cập nhật đơn hàng
            // --- Lấy trạng thái cũ để ghi log nếu có thay đổi ---
            $old_status = $wpdb->get_var($wpdb->prepare("SELECT status_id FROM $table WHERE id = %d", $id));
            $new_status = absint($_POST['status_id']);
            $data = [
                'customer_id'   => absint($_POST['customer_id']),
                'employee_id'   => absint($_POST['employee_id']),
                'order_date'    => $order_date,
                'status_id'     => $new_status,
                'note'          => sanitize_textarea_field($_POST['note']),
                'total_amount'  => $total_amount,
            ];
            $format = ['%d', '%d', '%s', '%s', '%s', '%f'];
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $order_id = $id;
            $msg = 'Đã cập nhật đơn hàng!';
            // --- Ghi log nếu trạng thái thay đổi ---
            if ((int)$old_status !== (int)$new_status && $old_status && $new_status) {
                $wpdb->insert(
                    $wpdb->prefix . 'aerp_order_status_logs',
                    [
                        'order_id'   => $order_id,
                        'old_status_id' => $old_status,
                        'new_status_id' => $new_status,
                        'changed_by' => get_current_user_id(),
                        'changed_at' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
                    ],
                    ['%d', '%d', '%d', '%d', '%s']
                );
            }
        } else {
            // Thêm mới đơn hàng
            $data = [
                'order_code'    => self::generate_order_code(),
                'customer_id'   => absint($_POST['customer_id']),
                'employee_id'   => absint($_POST['employee_id']),
                'order_date'    => $order_date,
                'total_amount'  => $total_amount,
                'status_id'     => sanitize_text_field($_POST['status_id']),
                'note'          => sanitize_textarea_field($_POST['note']),
                'created_at'    => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
            ];
            $format = ['%s', '%d', '%d', '%s', '%f', '%s', '%s', '%s'];
            $wpdb->insert($table, $data, $format);
            $order_id = $wpdb->insert_id;
            $msg = 'Đã thêm đơn hàng!';
        }

        if ($order_id) {
            self::handle_attachment_upload($order_id);
            // --- Logic mới: Cập nhật, Thêm, Xóa riêng biệt để giữ ID ---
            $item_table = $wpdb->prefix . 'aerp_order_items';
            
            // 1. Lấy ID các sản phẩm hiện có trong DB
            $existing_item_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $item_table WHERE order_id = %d", $order_id));
            $existing_item_ids = array_map('intval', $existing_item_ids);
            
            $submitted_item_ids = [];

            if (!empty($_POST['order_items']) && is_array($_POST['order_items'])) {
                foreach ($_POST['order_items'] as $item) {
                    $item_id = isset($item['id']) ? absint($item['id']) : 0;
                    $product_name = sanitize_text_field($item['product_name'] ?? '');
                    $quantity = floatval($item['quantity'] ?? 0);
                    $unit_price = floatval($item['unit_price'] ?? 0);
                    $product_id = isset($item['product_id']) && !empty($item['product_id']) ? absint($item['product_id']) : null;
                    $vat_percent = isset($item['vat_percent']) && $item['vat_percent'] !== '' ? floatval($item['vat_percent']) : null;
                    if (empty($product_name) || $quantity <= 0) continue; // Bỏ qua dòng trống

                    $item_data = [
                        'order_id'      => $order_id,
                        'product_id'    => $product_id,
                        'product_name'  => $product_name,
                        'quantity'      => $quantity,
                        'unit_price'    => $unit_price,
                        'total_price'   => $quantity * $unit_price + ($quantity * $unit_price * $vat_percent / 100),
                        'unit_name'     => isset($item['unit_name']) ? sanitize_text_field($item['unit_name']) : '',
                        'vat_percent'   => $vat_percent,
                        'item_type'     => isset($item['item_type']) ? sanitize_text_field($item['item_type']) : 'product',
                    ];
                    $item_format = ['%d', '%d', '%s', '%f', '%f', '%f', '%s', '%f', '%s'];

                    if ($item_id > 0 && in_array($item_id, $existing_item_ids, true)) {
                        // Cập nhật sản phẩm đã có
                        $wpdb->update($item_table, $item_data, ['id' => $item_id], $item_format, ['%d']);
                        $submitted_item_ids[] = $item_id;
                    } else {
                        // Thêm sản phẩm mới
                        $wpdb->insert($item_table, $item_data, $item_format);
                        // Không thêm vào $submitted_item_ids vì là dòng mới
                    }
                }
            }

            // 3. Xóa các sản phẩm không được gửi lên (đã bị xóa khỏi form)
            $items_to_delete = array_diff($existing_item_ids, $submitted_item_ids);
            if (!empty($items_to_delete)) {
                $ids_placeholder = implode(', ', array_fill(0, count($items_to_delete), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM $item_table WHERE id IN ($ids_placeholder)", $items_to_delete));
            }
        }
        
        aerp_clear_table_cache();
        set_transient('aerp_order_message', $msg, 10);
        wp_redirect(home_url('/aerp-order-orders'));
        exit;
    }
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_order_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_order_by_id($id)) {
                $message = 'Đã xóa đơn hàng thành công!';
            } else {
                $message = 'Không thể xóa đơn hàng.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_order_message', $message, 10);
            wp_redirect(home_url('/aerp-order-orders'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_order_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_order_orders', ['id' => absint($id)]);
        $wpdb->delete($wpdb->prefix . 'aerp_order_items', ['order_id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function delete_order_log_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_order_status_logs', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function get_by_id($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_orders';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    private static function generate_order_code()
    {
        global $wpdb;
        $max_code = $wpdb->get_var("SELECT order_code FROM {$wpdb->prefix}aerp_order_orders WHERE order_code LIKE 'DH-%' ORDER BY id DESC LIMIT 1");
        if (preg_match('/DH-(\\d+)/', $max_code, $matches)) {
            $next_number = intval($matches[1]) + 1;
        } else {
            $next_number = 1;
        }
        return 'DH-' . $next_number;
    }

    public static function handle_attachment_upload($order_id) {
        if (empty($_FILES['attachments']['name'][0])) return;
        
        global $wpdb;
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        $upload_overrides = ['test_form' => false];
        foreach ($_FILES['attachments']['name'] as $key => $filename) {
            if ($_FILES['attachments']['error'][$key] !== UPLOAD_ERR_OK) continue;
            
            $file = [
                'name'     => $_FILES['attachments']['name'][$key],
                'type'     => $_FILES['attachments']['type'][$key],
                'tmp_name' => $_FILES['attachments']['tmp_name'][$key],
                'error'    => $_FILES['attachments']['error'][$key],
                'size'     => $_FILES['attachments']['size'][$key],
            ];

            $uploaded_file = wp_handle_upload($file, $upload_overrides);

            if (isset($uploaded_file['file'])) {
                $attachment_table = $wpdb->prefix . 'aerp_order_attachments';
                $wpdb->insert($attachment_table, [
                    'order_id'      => $order_id,
                    'file_name'     => sanitize_file_name($filename),
                    'file_url'      => $uploaded_file['url'],
                    'file_type'     => $uploaded_file['type'],
                    'uploaded_by'   => get_current_user_id(),
                    'uploaded_at'   => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
                ], ['%d', '%s', '%s', '%s', '%d', '%s']);
            }
        }
    }

    public static function handle_delete_attachment_ajax() {
        check_ajax_referer('aerp_delete_order_attachment_nonce', '_wpnonce');

        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error('Thiếu ID file đính kèm.');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_attachments';
        $file_info = $wpdb->get_row($wpdb->prepare("SELECT file_url FROM $table WHERE id = %d", $attachment_id));

        if (!$file_info) {
            wp_send_json_error('File không tồn tại.');
        }

        if ($wpdb->delete($table, ['id' => $attachment_id], ['%d'])) {
            $upload_dir = wp_upload_dir();
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_info->file_url);
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            wp_send_json_success('Đã xóa file thành công.');
        } else {
            wp_send_json_error('Không thể xóa file khỏi cơ sở dữ liệu.');
        }
    }
} 