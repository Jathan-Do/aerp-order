<?php
if (!defined('ABSPATH')) exit;

class AERP_Calendar_Manager
{
    /**
     * Xử lý submit form thêm/sửa sự kiện lịch
     */
    public static function handle_form_submit()
    {
        if (!isset($_POST['aerp_save_event'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['aerp_save_event_nonce'] ?? '', 'aerp_save_event_action')) {
            wp_die('Invalid nonce for calendar event save.');
        }

        if (!is_user_logged_in()) {
            wp_die(__('You must be logged in to manage calendar events.'));
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aerp_calendar_events';

        $id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;

        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $event_type = sanitize_text_field($_POST['event_type'] ?? 'appointment');
        $customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : null;
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : null;
        $location = sanitize_text_field($_POST['location'] ?? '');
        $color = sanitize_text_field($_POST['color'] ?? '#007cba');
        $is_all_day = !empty($_POST['is_all_day']) ? 1 : 0;
        $reminder_minutes = isset($_POST['reminder_minutes']) && $_POST['reminder_minutes'] !== '' ? intval($_POST['reminder_minutes']) : null;

        // Ghép ngày + giờ
        $start_date_raw = sanitize_text_field($_POST['start_date'] ?? '');
        $start_time_raw = sanitize_text_field($_POST['start_time'] ?? '');
        $end_date_raw = sanitize_text_field($_POST['end_date'] ?? '');
        $end_time_raw = sanitize_text_field($_POST['end_time'] ?? '');

        if (empty($start_date_raw)) {
            $start_datetime = current_time('mysql');
        } else {
            $start_datetime = $start_date_raw . ' ' . ($start_time_raw ?: '08:00:00');
        }

        $end_datetime = null;
        if (!empty($end_date_raw)) {
            $end_datetime = $end_date_raw . ' ' . ($end_time_raw ?: '17:00:00');
        }

        $current_user_id = get_current_user_id();
        $employee_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $current_user_id
        ));

        $data = [
            'title' => $title,
            'description' => $description,
            'event_type' => $event_type,
            'customer_id' => $customer_id ?: null,
            'order_id' => $order_id ?: null,
            'employee_id' => $employee_id ?: null,
            'start_date' => $start_datetime,
            'end_date' => $end_datetime,
            'location' => $location ?: null,
            'color' => $color ?: '#007cba',
            'is_all_day' => $is_all_day,
            'reminder_minutes' => $reminder_minutes,
        ];

        $format = [
            '%s', // title
            '%s', // description
            '%s', // event_type
            '%d', // customer_id
            '%d', // order_id
            '%d', // employee_id
            '%s', // start_date
            '%s', // end_date
            '%s', // location
            '%s', // color
            '%d', // is_all_day
            '%d', // reminder_minutes
        ];

        if ($id) {
            $wpdb->update(
                $table,
                $data,
                ['id' => $id],
                $format,
                ['%d']
            );
            $msg = 'Đã cập nhật sự kiện lịch!';
        } else {
            $data['created_by'] = $employee_id ?: null;
            $data['created_at'] = (new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');
            $format[] = '%d';
            $format[] = '%s';

            $wpdb->insert($table, $data, $format);
            $id = $wpdb->insert_id;
            $msg = 'Đã thêm sự kiện lịch!';
        }
        aerp_clear_table_cache();
        // Lưu message để hiển thị
        set_transient('aerp_calendar_message', $msg, 30);

        // Redirect lại trang lịch để tránh resubmit
        wp_safe_redirect(home_url('/aerp-calendar'));
        exit;
    }

    /**
     * Xóa 1 sự kiện lịch
     */
    public static function handle_single_delete()
    {
        $id = absint($_GET['id'] ?? 0);
        $nonce_action = 'delete_event_' . $id;
        if ($id && check_admin_referer($nonce_action)) {
            if (self::delete_event_by_id($id)) {
                $message = 'Đã xóa sự kiện lịch thành công!';
            } else {
                $message = 'Không thể xóa sự kiện lịch.';
            }
            aerp_clear_table_cache();
            set_transient('aerp_calendar_message', $message, 10);
            wp_redirect(home_url('/aerp-calendar'));
            exit;
        }
        wp_die('Invalid request or nonce.');
    }
    public static function delete_event_by_id($id)
    {
        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'aerp_calendar_events', ['id' => absint($id)]);
        aerp_clear_table_cache();
        return (bool)$deleted;
    }
}


