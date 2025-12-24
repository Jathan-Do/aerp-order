<?php
if (!defined('ABSPATH')) exit;

class AERP_Acc_Deposit_Manager
{
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_acc_save_deposit']) && !isset($_POST['aerp_acc_submit_deposit']) && !isset($_POST['aerp_acc_approve_deposit'])) return;
        if (!wp_verify_nonce($_POST['aerp_acc_save_deposit_nonce'], 'aerp_acc_save_deposit_action')) wp_die('Invalid nonce for acc deposit save.');
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_acc_deposits';
        $id = isset($_POST['deposit_id']) ? absint($_POST['deposit_id']) : 0;

        // Bắt buộc phải có receipt_id (phiếu thu đã tồn tại)
        $receipt_id = isset($_POST['receipt_id']) ? absint($_POST['receipt_id']) : 0;
        if ($receipt_id <= 0) {
            wp_die('Thiếu phiếu thu (receipt_id).');
        }

        $data = [
            'receipt_id' => $receipt_id,
            'deposit_date' => sanitize_text_field($_POST['deposit_date'] ?? date('Y-m-d')),
            'note' => sanitize_text_field($_POST['note'] ?? ''),
            // parse with high precision to avoid float rounding
            'total_amount' => isset($_POST['total_amount']) ? (string)round((float)str_replace([','], [''], $_POST['total_amount']), 2) : '0.00',
        ];
        $format = ['%d','%s','%s','%s'];

        if ($id) {
            $wpdb->update($table, $data, ['id' => $id], $format, ['%d']);
            $msg = 'Đã cập nhật phiếu nộp tiền!';
        } else {
            // created_by = employee_id của user hiện tại
            $current_user_id = get_current_user_id();
            $employee_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $current_user_id
            ));
            if ($employee_id) {
                $data['created_by'] = intval($employee_id);
                $format[] = '%d';
            }
            // Sinh mã PN-<number> (tạm thời, sẽ update sau)
            $data['code'] = 'TEMP';
            $format[] = '%s';
            $wpdb->insert($table, $data, $format);
            $id = $wpdb->insert_id;
            
            // Tạo mã phiếu nộp tiền theo format: PN-id-ddmmyyyy sau khi insert
            if ($id) {
                $deposit_code = self::generate_deposit_code($id);
                $wpdb->update(
                    $table,
                    ['code' => $deposit_code],
                    ['id' => $id],
                    ['%s'],
                    ['%d']
                );
            }
            
            $msg = 'Đã thêm phiếu nộp tiền!';
        }

        // Lưu dòng
        $lines = isset($_POST['lines']) && is_array($_POST['lines']) ? $_POST['lines'] : [];
        $line_table = $wpdb->prefix . 'aerp_acc_deposit_lines';
        if ($id) {
            $wpdb->delete($line_table, ['deposit_id' => $id]);
        }
        $sum = 0;
        foreach ($lines as $line) {
            $amount = isset($line['amount']) ? (string)round((float)str_replace([','], [''], $line['amount']), 2) : '0.00';
            $order_id = isset($line['order_id']) ? absint($line['order_id']) : 0;
            $rev = isset($line['revenue_amount']) ? (string)round((float)str_replace([','], [''], $line['revenue_amount']), 2) : '0.00';
            $adv = isset($line['advance_amount']) ? (string)round((float)str_replace([','], [''], $line['advance_amount']), 2) : '0.00';
            $ext = isset($line['external_amount']) ? (string)round((float)str_replace([','], [''], $line['external_amount']), 2) : '0.00';
            $adv_payment_id = isset($line['advance_payment_id']) ? absint($line['advance_payment_id']) : null;
            if ((float)$amount <= 0 || $order_id <= 0) continue;
            $wpdb->insert($line_table, [
                'deposit_id' => $id,
                'order_id' => $order_id,
                'revenue_amount' => $rev,
                'advance_amount' => $adv,
                'advance_payment_id' => $adv_payment_id,
                'external_amount' => $ext,
                'amount' => $amount,
                'note' => sanitize_text_field($line['note'] ?? ''),
            ], ['%d','%d','%s','%s','%d','%s','%s','%s']);
            $sum += (float)$amount;
        }
        if ($sum > 0) {
            $wpdb->update($table, ['total_amount' => (string)round($sum, 2)], ['id' => $id], ['%s'], ['%d']);
        }

        // Trạng thái submit/approve
        if (!empty($_POST['aerp_acc_submit_deposit'])) {
            $wpdb->update($table, [ 'status' => 'submitted' ], ['id' => $id], ['%s'], ['%d']);
        }
        if (!empty($_POST['aerp_acc_approve_deposit'])) {
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
        set_transient('aerp_acc_deposit_message', $msg, 10);
        wp_redirect(home_url('/aerp-acc-deposits'));
        exit;
    }

    private static function generate_deposit_code($deposit_id = null)
    {
        // Nếu đã có deposit_id, tạo mã theo format: PN-id-ddmmyyyy
        if ($deposit_id) {
            $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
            $date_str = $now->format('dmy'); // ddmmYYYY format
            return 'PN-' . $deposit_id . '-' . $date_str;
        }
        // Nếu chưa có deposit_id, trả về giá trị tạm thời (sẽ được update sau khi insert)
        return 'TEMP';
    }

    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_acc_deposit_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_by_id($id)) {
                $message = 'Đã xóa phiếu nộp tiền thành công!';
            } else {
                $message = 'Không thể xóa phiếu nộp tiền.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_acc_deposit_message', $message, 10);
            wp_redirect(home_url('/aerp-acc-deposits'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }

    public static function delete_by_id($id)
    {
        global $wpdb;
        $did = absint($id);
        $wpdb->delete($wpdb->prefix . 'aerp_acc_deposit_lines', ['deposit_id' => $did]);
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_acc_deposits', ['id' => $did]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }

    public static function get_by_id($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_acc_deposits WHERE id = %d", $id));
    }

    public static function get_lines($deposit_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_acc_deposit_lines WHERE deposit_id = %d ORDER BY id ASC", absint($deposit_id)));
    }
}


