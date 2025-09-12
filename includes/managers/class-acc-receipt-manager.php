<?php
if (!defined('ABSPATH')) exit;

class AERP_Acc_Receipt_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_acc_save_receipt']) && !isset($_POST['aerp_acc_submit_receipt']) && !isset($_POST['aerp_acc_approve_receipt'])) return;
        if (!wp_verify_nonce($_POST['aerp_acc_save_receipt_nonce'], 'aerp_acc_save_receipt_action')) wp_die('Invalid nonce for acc receipt save.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_acc_receipts';
        $id = isset($_POST['receipt_id']) ? absint($_POST['receipt_id']) : 0;
        $data = [
            'receipt_date' => sanitize_text_field($_POST['receipt_date'] ?? date('Y-m-d')),
            'note' => sanitize_text_field($_POST['note'] ?? ''),
            'total_amount' => floatval($_POST['total_amount'] ?? 0),
        ];
        $format = ['%s','%s','%f'];
        if ($id) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật phiếu thu!';
        } else {
            // Gán created_by = employee_id của user hiện tại
            $current_user_id = get_current_user_id();
            $employee_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $current_user_id
            ));
            if ($employee_id) {
                $data['created_by'] = intval($employee_id);
                $format[] = '%d';
            }
            // Sinh mã phiếu thu tự động
            $data['code'] = self::generate_receipt_code();
            $format[] = '%s';
            $wpdb->insert($table, $data, $format);
            $id = $wpdb->insert_id;
            $msg = 'Đã thêm phiếu thu!';
        }

        // Lưu dòng
        $lines = isset($_POST['lines']) && is_array($_POST['lines']) ? $_POST['lines'] : [];
        $line_table = $wpdb->prefix . 'aerp_acc_receipt_lines';
        $prepared_lines = [];
        $sum = 0;
        foreach ($lines as $line) {
            $amount = isset($line['amount']) ? floatval($line['amount']) : 0;
            if ($amount <= 0) {
                continue;
            }
            $prepared_lines[] = [
                'receipt_id' => $id,
                'order_id' => isset($line['order_id']) ? absint($line['order_id']) : null,
                'amount' => $amount,
                'note' => sanitize_text_field($line['note'] ?? ''),
            ];
            $sum += $amount;
        }

        // Chỉ xoá và ghi lại khi có dữ liệu dòng gửi lên; nếu không, giữ nguyên các dòng cũ
        if ($id && count($prepared_lines) > 0) {
            $wpdb->delete($line_table, ['receipt_id' => $id]);
            foreach ($prepared_lines as $pl) {
                // Columns: receipt_id (int), order_id (int or null), amount (float), note (text)
                $wpdb->insert($line_table, $pl, ['%d','%d','%f','%s']);
            }
        }
        // Cập nhật tổng nếu cần
        if ($sum > 0) {
            $wpdb->update($table, ['total_amount' => $sum], ['id' => $id], ['%f'], ['%d']);
        }

        // Trạng thái submit/approve
        if (!empty($_POST['aerp_acc_submit_receipt'])) {
            $wpdb->update($table, [
                'status' => 'submitted',
            ], ['id' => $id], ['%s'], ['%d']);
        }
        if (!empty($_POST['aerp_acc_approve_receipt'])) {
            // Gán created_by = employee_id của user hiện tại
            $current_user_id = get_current_user_id();
            $employee_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $current_user_id
            ));
            if ($employee_id) {
                $wpdb->update($table, [
                    'status' => 'approved',
                    'approved_by' => $employee_id,
                    'approved_at' => (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s'),
                ], ['id' => $id], ['%s','%d','%s'], ['%d']);
            }
        }
        aerp_clear_table_cache();
        set_transient('aerp_acc_receipt_message', $msg, 10);
        wp_redirect(home_url('/aerp-acc-receipts'));
        exit;
    }
    private static function generate_receipt_code()
    {
        global $wpdb;
        $max_code = $wpdb->get_var("SELECT code FROM {$wpdb->prefix}aerp_acc_receipts WHERE code LIKE 'PT-%' ORDER BY id DESC LIMIT 1");
        if (preg_match('/PT-(\\d+)/', (string)$max_code, $matches)) {
            $next_number = intval($matches[1]) + 1;
        } else {
            $next_number = 1;
        }
        return 'PT-' . $next_number;
    }
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_acc_receipt_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_by_id($id)) {
                $message = 'Đã xóa phiếu thu thành công!';
            } else {
                $message = 'Không thể xóa phiếu thu.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_acc_receipt_message', $message, 10);
            wp_redirect(home_url('/aerp-acc-receipts'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_by_id($id)
    {
        global $wpdb;
        $rid = absint($id);
        // Xóa dòng trước để đảm bảo toàn vẹn
        $wpdb->delete($wpdb->prefix . 'aerp_acc_receipt_lines', ['receipt_id' => $rid]);
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_acc_receipts', ['id' => $rid]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
    public static function get_by_id($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_acc_receipts WHERE id = %d", $id));
    }
    public static function get_prefill_from_order($order_id)
    {
        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare("SELECT id, order_code, total_amount, cost FROM {$wpdb->prefix}aerp_order_orders WHERE id = %d", $order_id));
        return $order;
    }
    public static function get_lines($receipt_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_acc_receipt_lines WHERE receipt_id = %d ORDER BY id ASC", absint($receipt_id)));
    }
}


