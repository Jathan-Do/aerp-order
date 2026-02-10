<?php
if (!defined('ABSPATH')) exit;

class AERP_Acc_Payment_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_acc_save_payment']) && !isset($_POST['aerp_acc_submit_payment']) && !isset($_POST['aerp_acc_approve_payment']) && !isset($_POST['aerp_acc_confirm_payment'])&& !isset($_POST['aerp_acc_mark_paid'])) return;
        if (!wp_verify_nonce($_POST['aerp_acc_save_payment_nonce'], 'aerp_acc_save_payment_action')) wp_die('Invalid nonce for acc payment save.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_acc_payments';
        $id = isset($_POST['payment_id']) ? absint($_POST['payment_id']) : 0;
        $data = [
            'payment_date' => sanitize_text_field($_POST['payment_date'] ?? date('Y-m-d')),
            // voucher_type_id không dùng nữa (lấy theo từng dòng)
            'payer_employee_id' => isset($_POST['payer_employee_id']) ? (int)$_POST['payer_employee_id'] : null,
            'payee_type' => in_array(($_POST['payee_type'] ?? 'employee'), ['employee','supplier','customer','other'], true) ? $_POST['payee_type'] : 'employee',
            'payee_employee_id' => isset($_POST['payee_employee_id']) ? (int)$_POST['payee_employee_id'] : null,
            'supplier_id' => isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null,
            'customer_id' => isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : null,
            'payee_text' => sanitize_text_field($_POST['payee_text'] ?? ''),
            'payment_method' => in_array(($_POST['payment_method'] ?? 'cash'), ['cash','bank_transfer','card','other'], true) ? $_POST['payment_method'] : 'cash',
            'bank_account' => sanitize_text_field($_POST['bank_account'] ?? ''),
            'note' => sanitize_text_field($_POST['note'] ?? ''),
            'total_amount' => floatval($_POST['total_amount'] ?? 0),
        ];
        // Format map must match $data order exactly
        // payment_date(%s), payer_employee_id(%d), payee_type(%s), payee_employee_id(%d), supplier_id(%d), customer_id(%d),
        // payee_text(%s), payment_method(%s), bank_account(%s), note(%s), total_amount(%f)
        $format = ['%s','%d','%s','%d','%d','%d','%s','%s','%s','%s','%f'];
        if ($id) {
            // Chặn sửa nếu đã xác nhận/đã chi và không phải admin/kế toán
            $current = $wpdb->get_row($wpdb->prepare("SELECT status FROM $table WHERE id = %d", $id));
            $user_id_guard = get_current_user_id();
            $is_admin = function_exists('aerp_user_has_role') ? aerp_user_has_role($user_id_guard, 'admin') : false;
            $is_accountant = function_exists('aerp_user_has_role') ? aerp_user_has_role($user_id_guard, 'accountant') : false;
            if ($current && in_array($current->status, ['confirmed','paid'], true) && !($is_admin || $is_accountant)) {
                wp_die('Phiếu đã xác nhận/đã chi. Bạn không có quyền chỉnh sửa.');
            }
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật phiếu chi!';
        } else {
            // created_by = employee_id của user hiện tại để thống nhất (acc_receipts dùng employee_id)
            $current_user_id = get_current_user_id();
            $employee_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $current_user_id
            ));
            if ($employee_id) {
                $data['created_by'] = intval($employee_id);
                $format[] = '%d';
            }
            // Sinh mã phiếu chi tự động (tạm thời, sẽ update sau)
            $data['code'] = 'TEMP';
            $format[] = '%s';
            // Mặc định ở trạng thái nháp cho đến khi xác nhận
            $data['status'] = 'draft';
            $format[] = '%s';
            $wpdb->insert($table, $data, $format);
            $id = $wpdb->insert_id;
            
            // Tạo mã phiếu chi theo format: PC-id-ddmmyyyy sau khi insert
            if ($id) {
                $payment_code = self::generate_payment_code($id);
                $wpdb->update(
                    $table,
                    ['code' => $payment_code],
                    ['id' => $id],
                    ['%s'],
                    ['%d']
                );
            }
            
            $msg = 'Đã thêm phiếu chi!';
        }
        // Lưu dòng
        $lines = isset($_POST['lines']) && is_array($_POST['lines']) ? $_POST['lines'] : [];
        $line_table = $wpdb->prefix . 'aerp_acc_payment_lines';
        if ($id) {
            $wpdb->delete($line_table, ['payment_id' => $id]);
        }
        $sum = 0;
        foreach ($lines as $line) {
            $desc = sanitize_text_field($line['description'] ?? '');
            $amount = isset($line['amount']) ? floatval($line['amount']) : 0;
            $vat = isset($line['vat_percent']) ? floatval($line['vat_percent']) : 0;
            $category_id = isset($line['category_id']) ? (int)$line['category_id'] : null;
            if ($amount <= 0) continue;
            // Kiểm tra cột category_id đã tồn tại chưa
            $has_category_col = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = 'category_id'",
                $line_table
            ));
            if ($has_category_col) {
                $wpdb->insert($line_table, [
                    'payment_id' => $id,
                    'order_id' => null,
                    'description' => $desc,
                    'amount' => $amount,
                    'vat_percent' => $vat,
                    'is_accounted_override' => null,
                    'note' => '',
                    'category_id' => $category_id,
                ], ['%d','%d','%s','%f','%f','%d','%s','%d']);
            } else {
                // Fallback nếu DB chưa cập nhật schema
                $wpdb->insert($line_table, [
                    'payment_id' => $id,
                    'order_id' => null,
                    'description' => $desc,
                    'amount' => $amount,
                    'vat_percent' => $vat,
                    'is_accounted_override' => null,
                    'note' => '',
                ], ['%d','%d','%s','%f','%f','%d','%s']);
            }
            $sum += $amount + ($vat>0 ? $amount*$vat/100 : 0);
        }
        if ($sum > 0) {
            $wpdb->update($table, ['total_amount' => $sum], ['id' => $id], ['%f'], ['%d']);
        }
        // Xác nhận đã chi nếu bấm nút
        if (!empty($_POST['aerp_acc_confirm_payment'])) {
            $current_user_id2 = get_current_user_id();
            $employee_id2 = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $current_user_id2
            ));
            $wpdb->update($table, [
                'status' => 'confirmed',
                'confirmed_by' => $employee_id2 ?: null,
                'confirmed_at' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
            ], ['id' => $id], ['%s','%d','%s'], ['%d']);
        }
        if (!empty($_POST['aerp_acc_mark_paid'])) {
            $current_user_id3 = get_current_user_id();
            $employee_id3 = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $current_user_id3
            ));
            $wpdb->update($table, [
                'status' => 'paid',
                'confirmed_by' => $employee_id3 ?: null,
                'confirmed_at' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
            ], ['id' => $id], ['%s','%d','%s'], ['%d']);
        }
        aerp_clear_table_cache();
        set_transient('aerp_acc_payment_message', $msg, 10);
        wp_redirect(home_url('/aerp-acc-payments'));
        exit;
    }
    private static function generate_payment_code($payment_id = null)
    {
        // Nếu đã có payment_id, tạo mã theo format: PC-id-ddmmyyyy
        if ($payment_id) {
            $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
            $date_str = $now->format('dmy'); // ddmmYYYY format
            return 'PC-' . $payment_id . '-' . $date_str;
        }
        // Nếu chưa có payment_id, trả về giá trị tạm thời (sẽ được update sau khi insert)
        return 'TEMP';
    }
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_acc_payment_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_by_id($id)) {
                $message = 'Đã xóa phiếu chi thành công!';
            } else {
                $message = 'Không thể xóa phiếu chi.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_acc_payment_message', $message, 10);
            wp_redirect(home_url('/aerp-acc-payments'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_by_id($id)
    {
        global $wpdb;
        $pid = absint($id);
        $wpdb->delete($wpdb->prefix . 'aerp_acc_payment_lines', ['payment_id' => $pid]);
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_acc_payments', ['id' => $pid]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function get_by_id($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_acc_payments WHERE id = %d", $id));
    }
    public static function get_prefill_from_order($order_id)
    {
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare("SELECT id, order_code, cost FROM {$wpdb->prefix}aerp_order_orders WHERE id = %d", $order_id));
        return $order;
    }
    public static function get_lines($payment_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_acc_payment_lines WHERE payment_id = %d ORDER BY id ASC", absint($payment_id)));
    }
}


