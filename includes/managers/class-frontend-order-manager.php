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
        // Nếu không gửi order_type (ví dụ từ các hành động khác), giữ nguyên order_type hiện có thay vì mặc định 'product'
        $existing_order_type = '';
        if (!empty($id)) {
            $existing_order_type = $wpdb->get_var($wpdb->prepare("SELECT order_type FROM $table WHERE id = %d", $id));
        }
        $order_type = (isset($_POST['order_type']) && $_POST['order_type'] !== '')
            ? sanitize_text_field($_POST['order_type'])
            : (!empty($existing_order_type) ? $existing_order_type : 'product');
        $order_date = !empty($_POST['order_date']) ? sanitize_text_field($_POST['order_date']) : date('Y-m-d');

        if ($id) {
            // Cập nhật đơn hàng
            // --- Lấy trạng thái cũ để ghi log nếu có thay đổi ---
            // Lấy status_id cũ để ghi log đúng kiểu
            $old_status_id_log = (int) $wpdb->get_var($wpdb->prepare("SELECT status_id FROM $table WHERE id = %d", $id));
            $new_status_log = absint($_POST['status_id'] ?? 0);
            $old_status = $wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE id = %d", $id));
            $old_employee_id = $wpdb->get_var($wpdb->prepare("SELECT employee_id FROM $table WHERE id = %d", $id));
            $new_employee_id = absint($_POST['employee_id'] ?? 0);

            $data = [
                'customer_id'   => absint($_POST['customer_id']),
                'employee_id'   => $new_employee_id,
                'order_date'    => $order_date,
                'status_id'     => $new_status_log,
                'order_type'    => $order_type,
                'note'          => sanitize_textarea_field($_POST['note']),
                'total_amount'  => $total_amount,
                'cost'          => floatval($_POST['cost'] ?? 0),
                'customer_source_id' => !empty($_POST['customer_source_id']) ? absint($_POST['customer_source_id']) : null,
            ];

            // Logic xử lý status khi edit
            if ($old_status === 'rejected' && $new_employee_id > 0 && $new_employee_id !== $old_employee_id) {
                // Nếu đơn từ chối và được phân cho nhân viên khác → chuyển về assigned
                $data['status'] = 'assigned';
            } elseif ($old_status === 'rejected' && $new_employee_id === 0) {
                // Nếu đơn từ chối và không có nhân viên → giữ nguyên rejected
                $data['status'] = 'rejected';
            } elseif ($old_status === 'new' && $new_employee_id > 0 && $old_employee_id == 0) {
                // Nếu đơn mới và được phân cho nhân viên lần đầu → chuyển về assigned
                $data['status'] = 'assigned';
            } elseif ($old_status === 'assigned' && $new_employee_id == 0 && $old_employee_id > 0) {
                // Nếu đơn đã phân và bị bỏ phân công → chuyển về new
                $data['status'] = 'new';
            } else {
                // Các trường hợp khác giữ nguyên status cũ
                $data['status'] = $old_status;
            }

            // Nếu có lý do hủy, thêm vào data
            if (!empty($_POST['cancel_reason'])) {
                $data['cancel_reason'] = sanitize_textarea_field($_POST['cancel_reason']);
                $data['status'] = 'cancelled';
            }
            if (!empty($_POST['reject_reason'])) {
                $data['reject_reason'] = sanitize_textarea_field($_POST['reject_reason']);
                $data['status'] = 'rejected';
            }

            // Build format array dynamically by key to avoid mismatch
            $format = [];
            foreach (array_keys($data) as $key) {
                switch ($key) {
                    case 'customer_id':
                    case 'employee_id':
                    case 'status_id':
                    case 'customer_source_id':
                        $format[] = '%d';
                        break;
                    case 'total_amount':
                    case 'cost':
                        $format[] = '%f';
                        break;
                    case 'order_date':
                    case 'note':
                    case 'status':
                    case 'cancel_reason':
                    case 'reject_reason':
                    case 'order_type':
                        $format[] = '%s';
                        break;
                    default:
                        // Fallback safe
                        $format[] = '%s';
                }
            }
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $order_id = $id;
            $msg = 'Đã cập nhật đơn hàng!';

            // --- Ghi log nếu trạng thái thay đổi ---
            if ((int)$old_status_id_log !== (int)$new_status_log && $old_status_id_log && $new_status_log) {
                $wpdb->insert(
                    $wpdb->prefix . 'aerp_order_status_logs',
                    [
                        'order_id'   => $order_id,
                        'old_status_id' => $old_status_id_log,
                        'new_status_id' => $new_status_log,
                        'changed_by' => get_current_user_id(),
                        'changed_at' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
                    ],
                    ['%d', '%d', '%d', '%d', '%s']
                );
            }
            // Xử lý thiết bị nhận nếu có submit (không phụ thuộc order_type để hỗ trợ đơn tổng hợp)
            $device_table = $wpdb->prefix . 'aerp_order_devices';
            // Xóa thiết bị cũ
            $wpdb->delete($device_table, ['order_id' => $order_id]);
            if (!empty($_POST['devices']) && is_array($_POST['devices'])) {
                foreach ($_POST['devices'] as $device) {
                    $device_name = sanitize_text_field($device['device_name'] ?? '');
                    if ($device_name === '') { continue; }
                    $device_data = [
                        'order_id' => $order_id,
                        'device_name' => $device_name,
                        'serial_number' => sanitize_text_field($device['serial_number'] ?? ''),
                        'status' => sanitize_text_field($device['status'] ?? ''),
                        'device_status' => 'received',
                        'note' => sanitize_text_field($device['note'] ?? ''),
                        'partner_id' => !empty($device['partner_id']) ? absint($device['partner_id']) : null,
                    ];
                    $wpdb->insert($device_table, $device_data, ['%d', '%s', '%s', '%s', '%s', '%s', '%d']);
                }
            }
        } else {
            // Thêm mới đơn hàng
            $employee_id = absint($_POST['employee_id'] ?? 0);

            // Tự động set status dựa trên employee_id
            $status = $employee_id > 0 ? 'assigned' : 'new';
            $user_id = get_current_user_id();
            $employee_current_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $user_id
            ));
            $data = [
                'order_code'    => self::generate_order_code(),
                'customer_id'   => absint($_POST['customer_id']),
                'employee_id'   => $employee_id,
                'order_date'    => $order_date,
                'total_amount'  => $total_amount,
                'status_id'     => sanitize_text_field($_POST['status_id']),
                'status'        => $status, // Tự động set: 'new' nếu chưa có nhân viên, 'assigned' nếu đã có
                'order_type'    => $order_type,
                'note'          => sanitize_textarea_field($_POST['note']),
                'cost'          => floatval($_POST['cost'] ?? 0),
                'customer_source_id' => !empty($_POST['customer_source_id']) ? absint($_POST['customer_source_id']) : null,
                'created_by'    => $employee_current_id,
                'created_at'    => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
            ];
            $format = ['%s', '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%f', '%d', '%d', '%s'];
            $wpdb->insert($table, $data, $format);
            $order_id = $wpdb->insert_id;
            $msg = 'Đã thêm đơn hàng!';
            // Xử lý thiết bị nhận nếu có submit (không phụ thuộc order_type để hỗ trợ đơn tổng hợp)
            $device_table = $wpdb->prefix . 'aerp_order_devices';
            if (!empty($_POST['devices']) && is_array($_POST['devices'])) {
                foreach ($_POST['devices'] as $device) {
                    $device_name = sanitize_text_field($device['device_name'] ?? '');
                    if ($device_name === '') { continue; }
                    $device_data = [
                        'order_id' => $order_id,
                        'device_name' => $device_name,
                        'serial_number' => sanitize_text_field($device['serial_number'] ?? ''),
                        'status' => sanitize_text_field($device['status'] ?? ''),
                        'device_status' => 'received',
                        'note' => sanitize_text_field($device['note'] ?? ''),
                        'partner_id' => !empty($device['partner_id']) ? absint($device['partner_id']) : null,
                    ];
                    $wpdb->insert($device_table, $device_data, ['%d', '%s', '%s', '%s', '%s', '%s', '%d']);
                }
            }
        }

        if ($order_id) {
            self::handle_attachment_upload($order_id);

            // Xử lý nhiều dòng nội dung (yêu cầu và triển khai)
            if (!empty($_POST['content_lines']) && is_array($_POST['content_lines'])) {
                $content_table = $wpdb->prefix . 'aerp_order_content_lines';
                // Xóa nội dung cũ
                $wpdb->delete($content_table, ['order_id' => $order_id]);

                foreach ($_POST['content_lines'] as $idx => $line) {
                    $requirement = sanitize_textarea_field($line['requirement'] ?? '');
                    $implementation = sanitize_textarea_field($line['implementation'] ?? '');
                    $template_id = !empty($line['template_id']) ? absint($line['template_id']) : null;
                    $unit_price = !empty($line['unit_price']) ? floatval($line['unit_price']) : 0.00;
                    $quantity = !empty($line['quantity']) ? floatval($line['quantity']) : 1.00;

                    // Xử lý total_price giống như sản phẩm - chỉ loại bỏ dấu phẩy
                    $total_price_raw = $line['total_price'] ?? 0;
                    if (is_string($total_price_raw)) {
                        $total_price_raw = str_replace('.', '', $total_price_raw);
                    }
                    $total_price = !empty($total_price_raw) ? floatval($total_price_raw) : 0.00;

                    $warranty = sanitize_text_field($line['warranty'] ?? '');

                    // Chỉ lưu nếu có ít nhất 1 trong 2 trường có nội dung
                    if (!empty($requirement) || !empty($implementation)) {
                        $wpdb->insert($content_table, [
                            'order_id' => $order_id,
                            'requirement' => $requirement,
                            'implementation' => $implementation,
                            'template_id' => $template_id,
                            'unit_price' => $unit_price,
                            'quantity' => $quantity,
                            'total_price' => $total_price,
                            'warranty' => $warranty,
                            'sort_order' => $idx
                        ], ['%d', '%s', '%s', '%d', '%f', '%f', '%f', '%s', '%d']);
                    }
                }
            }

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
                    $item_type = isset($item['item_type']) ? sanitize_text_field($item['item_type']) : 'product';

                    // Validation logic
                    if ($item_type === 'product') {
                        // Nếu là sản phẩm, cần có product_id hoặc product_name
                        if (empty($product_id) && empty($product_name)) {
                            continue; // Bỏ qua dòng không hợp lệ
                        }
                    } else {
                        // Nếu là dịch vụ, cần có product_name
                        if (empty($product_name)) {
                            continue; // Bỏ qua dòng không hợp lệ
                        }
                    }

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
                        'item_type'     => $item_type,
                        'purchase_type' => isset($item['purchase_type']) ? sanitize_text_field($item['purchase_type']) : 'warehouse',
                        'external_supplier_name' => isset($item['external_supplier_name']) ? sanitize_text_field($item['external_supplier_name']) : null,
                        'external_cost' => isset($item['external_cost']) ? floatval($item['external_cost']) : 0.00,
                        'external_delivery_date' => isset($item['external_delivery_date']) ? sanitize_text_field($item['external_delivery_date']) : null,
                    ];
                    $item_format = ['%d', '%d', '%s', '%f', '%f', '%f', '%s', '%f', '%s', '%s', '%s', '%f', '%s'];

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

            // 3.5. Tự động cập nhật order_type dựa trên dữ liệu thực tế (items/content/devices/returns)
            if (!empty($_POST['order_items']) && is_array($_POST['order_items'])) {
                $count_product = 0;
                $count_service = 0;
                
                foreach ($_POST['order_items'] as $item) {
                    $item_type = isset($item['item_type']) ? sanitize_text_field($item['item_type']) : 'product';
                    $product_name = sanitize_text_field($item['product_name'] ?? '');
                    $quantity = floatval($item['quantity'] ?? 0);
                    
                    // Chỉ đếm các dòng hợp lệ
                    if (!empty($product_name) && $quantity > 0) {
                        if ($item_type === 'product') {
                            $count_product++;
                        } elseif ($item_type === 'service') {
                            $count_service++;
                        }
                    }
                }
                
                // Cập nhật order_type dựa trên loại sản phẩm/dịch vụ trước (tạm)
                if ($count_product > 0 && $count_service > 0) {
                    $new_order_type = 'mixed';
                } elseif ($count_product > 0) {
                    $new_order_type = 'product';
                } elseif ($count_service > 0) {
                    $new_order_type = 'service';
                } else {
                    // Nếu không có order_items hợp lệ, giữ nguyên order_type hiện tại
                    $new_order_type = $order_type;
                }
                
                // Việc update cuối cùng sẽ xét thêm content/devices/returns bên dưới
            } else {
                // Nếu không có order_items, kiểm tra xem có phải đang chuyển từ content sang product/service không
                if (in_array($order_type, ['product', 'service', 'mixed']) && !in_array($existing_order_type ?? '', ['device', 'return', 'content'])) {
                    // Nếu order_type là product/service/mixed nhưng không có order_items, 
                    // vẫn cập nhật order_type vào database để đảm bảo tính nhất quán
                    $wpdb->update($table, ['order_type' => $order_type], ['id' => $order_id], ['%s'], ['%d']);
                }
            }

            // 3.6. Sau khi lưu hết dữ liệu, xác định lại order_type theo các phần hiện có
            $content_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_content_lines WHERE order_id = %d",
                $order_id
            ));
            $item_counts = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    SUM(CASE WHEN item_type = 'product' THEN 1 ELSE 0 END) AS num_product,
                    SUM(CASE WHEN item_type = 'service' THEN 1 ELSE 0 END) AS num_service
                 FROM {$wpdb->prefix}aerp_order_items WHERE order_id = %d",
                $order_id
            ));
            $device_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_devices WHERE order_id = %d",
                $order_id
            ));
            $return_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_device_returns WHERE order_id = %d",
                $order_id
            ));

            $has_items = (($item_counts->num_product ?? 0) > 0) || (($item_counts->num_service ?? 0) > 0);
            $sections = 0;
            $sections += $content_count > 0 ? 1 : 0;
            $sections += $has_items ? 1 : 0;
            $sections += $device_count > 0 ? 1 : 0;
            $sections += $return_count > 0 ? 1 : 0;

            $final_order_type = $order_type; // default giữ
            // Quy ước:
            // - mixed: chỉ khi có cả product và service (trong tab Sản phẩm/Dịch vụ)
            // - all: khi có từ 2 phần trở lên giữa: content/devices/returns/items (bất kỳ kết hợp)
            if ($sections > 1) {
                $final_order_type = 'all';
            } elseif ($return_count > 0) {
                $final_order_type = 'return';
            } elseif ($device_count > 0) {
                $final_order_type = 'device';
            } elseif ($has_items) {
                if (($item_counts->num_product ?? 0) > 0 && ($item_counts->num_service ?? 0) > 0) {
                    $final_order_type = 'mixed';
                } elseif (($item_counts->num_product ?? 0) > 0) {
                    $final_order_type = 'product';
                } else {
                    $final_order_type = 'service';
                }
            } elseif ($content_count > 0) {
                $final_order_type = 'content';
            }

            if ($final_order_type !== $order_type) {
                $wpdb->update($table, ['order_type' => $final_order_type], ['id' => $order_id], ['%s'], ['%d']);
            }

            // 4. Lưu thông tin TRẢ THIẾT BỊ
            if (!empty($_POST['device_returns']) && is_array($_POST['device_returns'])) {
                $return_table = $wpdb->prefix . 'aerp_order_device_returns';
                // Chiến lược: xóa hết rồi thêm lại để đơn giản hóa (do chưa cần giữ id)
                $wpdb->delete($return_table, ['order_id' => $order_id]);
                foreach ($_POST['device_returns'] as $ret) {
                    $device_id = isset($ret['device_id']) ? absint($ret['device_id']) : 0;
                    $return_date = !empty($ret['return_date']) ? sanitize_text_field($ret['return_date']) : null;
                    $note = sanitize_text_field($ret['note'] ?? '');
                    if ($device_id > 0) {
                        $wpdb->insert($return_table, [
                            'order_id' => $order_id,
                            'device_id' => $device_id,
                            'return_date' => $return_date,
                            'note' => $note,
                            'status' => 'draft',
                        ], ['%d', '%d', '%s', '%s', '%s']);
                    }
                }
            } else {
                // Nếu không submit returns, coi như xóa (để đồng bộ)
                $wpdb->delete($wpdb->prefix . 'aerp_order_device_returns', ['order_id' => $order_id]);
            }
        }

        aerp_clear_table_cache();
        set_transient('aerp_order_message', $msg, 10);
        wp_redirect(home_url('/aerp-order-orders/' . $order_id));
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
        $wpdb->delete($wpdb->prefix . 'aerp_order_devices', ['order_id' => absint($id)]);
        $wpdb->delete($wpdb->prefix . 'aerp_order_device_returns', ['order_id' => absint($id)]);
        $wpdb->delete($wpdb->prefix . 'aerp_order_content_lines', ['order_id' => absint($id)]);
        $wpdb->delete($wpdb->prefix . 'aerp_order_attachments', ['order_id' => absint($id)]);
        $wpdb->delete($wpdb->prefix . 'aerp_order_status_logs', ['order_id' => absint($id)]);
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

    public static function handle_attachment_upload($order_id)
    {
        if (empty($_FILES['attachments']['name'][0])) return;

        global $wpdb;
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        $user_id = get_current_user_id();
        $employee_current_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $user_id
        ));
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
                    'uploaded_by'   => $employee_current_id,
                    'uploaded_at'   => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
                ], ['%d', '%s', '%s', '%s', '%d', '%s']);
            }
        }
    }

    public static function handle_delete_attachment_ajax()
    {
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

    public static function cancel_order($order_id, $reason)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_orders';

        $data = [
            'cancel_reason' => sanitize_textarea_field($reason),
            'status' => 'cancelled'
        ];

        $result = $wpdb->update(
            $table,
            $data,
            ['id' => $order_id],
            ['%s', '%s'],
            ['%d']
        );
        aerp_clear_table_cache();
        return $result !== false;
    }

    // Nhân viên từ chối đơn
    public static function reject_order($order_id, $reason = '')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_orders';

        $data = [
            'status' => 'rejected',
            'reject_reason' => !empty($reason) ? sanitize_textarea_field($reason) : 'Đơn hàng bị từ chối'
        ];

        $result = $wpdb->update(
            $table,
            $data,
            ['id' => $order_id],
            ['%s', '%s'],
            ['%d']
        );

        aerp_clear_table_cache();
        return $result !== false;
    }

    // Đánh dấu đơn đã hoàn thành
    // Hàm này xử lý khi nhấn nút hoàn thành, cần xóa thiết bị ở bảng aerp_order_devices nếu có
    public static function complete_order($order_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_orders';

        $data = [
            'status' => 'completed'
        ];

        // Lấy loại đơn hàng để xử lý thiết bị đúng cách
        $order_type = $wpdb->get_var($wpdb->prepare(
            "SELECT order_type FROM $table WHERE id = %d",
            $order_id
        ));

        // Nếu order_type chưa được set, xác định dựa trên dữ liệu thực tế
        if (empty($order_type)) {
            // Kiểm tra xem có thiết bị trả không
            $device_return_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_device_returns WHERE order_id = %d",
                $order_id
            ));
            
            if ($device_return_count > 0) {
                $order_type = 'return';
            } else {
                // Kiểm tra xem có thiết bị nhận không
                $device_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_devices WHERE order_id = %d",
                    $order_id
                ));
                
                if ($device_count > 0) {
                    $order_type = 'device';
                } else {
                    $order_type = 'product';
                }
            }
            
            // Cập nhật order_type vào database
            $wpdb->update(
                $table,
                ['order_type' => $order_type],
                ['id' => $order_id],
                ['%s'],
                ['%d']
            );
        }

        if ($order_type === 'return') {
            // Nếu là đơn trả thiết bị: cập nhật trạng thái thiết bị thành 'disposed' khi hoàn thành
            $device_returns = $wpdb->get_results($wpdb->prepare(
                "SELECT device_id FROM {$wpdb->prefix}aerp_order_device_returns WHERE order_id = %d AND status = 'draft'",
                $order_id
            ));
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}aerp_order_device_returns SET status = 'confirmed' WHERE order_id = %d AND status = 'draft'",
                $order_id
            ));
            if (!empty($device_returns)) {
                $device_ids = array_column($device_returns, 'device_id');
                $device_ids_placeholder = implode(', ', array_fill(0, count($device_ids), '%d'));
                
                // Cập nhật trạng thái thiết bị thành 'disposed' khi hoàn thành trả thiết bị
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}aerp_order_devices SET device_status = 'disposed' WHERE id IN ($device_ids_placeholder) AND device_status = 'received'",
                    $device_ids
                ));
            }
        }

        $result = $wpdb->update(
            $table,
            $data,
            ['id' => $order_id],
            ['%s'],
            ['%d']
        );

        aerp_clear_table_cache();
        return $result !== false;
    }

    // Đánh dấu đã thu tiền
    public static function mark_order_paid($order_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_orders';

        $data = [
            'status' => 'paid'
        ];

        $result = $wpdb->update(
            $table,
            $data,
            ['id' => $order_id],
            ['%s'],
            ['%d']
        );

        aerp_clear_table_cache();
        return $result !== false;
    }
}
