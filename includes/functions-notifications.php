<?php

/**
 * Helper functions for notifications
 */

if (!defined('ABSPATH')) exit;

/**
 * Tạo thông báo cho user
 */
function aerp_create_notification($user_id, $type, $title, $message = '', $link_url = null, $related_id = null)
{
    global $wpdb;

    $table = $wpdb->prefix . 'aerp_notifications';

    // Dùng timezone HCM để tạo created_at
    $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
    $now = new DateTime('now', $tz);
    $created_at = $now->format('Y-m-d H:i:s');

    $data = [
        'user_id' => absint($user_id),
        'type' => sanitize_text_field($type),
        'title' => sanitize_text_field($title),
        'message' => sanitize_textarea_field($message),
        'link_url' => $link_url ? esc_url_raw($link_url) : null,
        'related_id' => $related_id ? absint($related_id) : null,
        'is_read' => 0,
        'created_at' => $created_at
    ];

    $format = ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s'];

    return $wpdb->insert($table, $data, $format);
}

/**
 * Lấy user_id mục tiêu cho sự kiện lịch (ưu tiên người tạo, fallback admin đầu tiên)
 */
function aerp_get_calendar_event_user_id($event)
{
    global $wpdb;

    // Ưu tiên: lấy user theo created_by (employee_id)
    if (!empty($event->created_by)) {
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
            $event->created_by
        ));
        if ($user_id) {
            return (int) $user_id;
        }
    }

    // Fallback: admin đầu tiên
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => ['ID']]);
    if (!empty($admins)) {
        return (int) $admins[0]->ID;
    }

    // Fallback cuối: current_user nếu có
    $current = get_current_user_id();
    return $current ?: 0;
}

/**
 * Tạo thông báo đơn hàng mới
 * Gửi cho: tất cả admin, nhân viên được gán (employee_id), người tạo đơn (created_by)
 */
function aerp_notify_new_order($order_id, $created_by_employee_id)
{
    global $wpdb;

    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT order_code, customer_id, employee_id, created_by FROM {$wpdb->prefix}aerp_order_orders WHERE id = %d",
        $order_id
    ));

    if (!$order) return false;

    // Lấy tên khách hàng
    $customer_name = '';
    if ($order->customer_id) {
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT full_name FROM {$wpdb->prefix}aerp_crm_customers WHERE id = %d",
            $order->customer_id
        ));
        if ($customer) {
            $customer_name = $customer->full_name;
        }
    }

    $link_url = home_url('/aerp-order-orders/' . $order_id);
    $title = 'Đơn hàng mới: ' . $order->order_code;
    $message = $customer_name ? "Khách hàng: {$customer_name}" : '';
    
    // Danh sách user_id sẽ nhận thông báo
    $target_user_ids = [];
    
    // 1. Gửi cho TẤT CẢ admin (custom role system)
    $admin_user_ids = aerp_get_all_admin_user_ids();
    foreach ($admin_user_ids as $admin_user_id) {
        if ($admin_user_id > 0 && !in_array($admin_user_id, $target_user_ids, true)) {
            $target_user_ids[] = $admin_user_id;
        }
    }

    // 2. Gửi cho nhân viên được gán đơn (employee_id)
    if (!empty($order->employee_id)) {
        $employee_user_id = aerp_get_user_id_from_employee((int) $order->employee_id);
        if ($employee_user_id && !in_array($employee_user_id, $target_user_ids, true)) {
            $target_user_ids[] = $employee_user_id;
        }
    }

    // 3. Gửi cho người tạo đơn (created_by - employee_id)
    if (!empty($order->created_by)) {
        $creator_user_id = aerp_get_user_id_from_employee((int) $order->created_by);
        if ($creator_user_id && !in_array($creator_user_id, $target_user_ids, true)) {
            $target_user_ids[] = $creator_user_id;
        }
    }

    // Tạo thông báo cho từng user
    $success_count = 0;
    foreach ($target_user_ids as $user_id) {
        if ($user_id > 0) {
            $result = aerp_create_notification($user_id, 'new_order', $title, $message, $link_url, $order_id);
            if ($result !== false) {
                $success_count++;
            }
        }
    }

    return $success_count > 0;
}

/**
 * Lấy tất cả user_id có role 'admin' (custom role system)
 */
function aerp_get_all_admin_user_ids()
{
    global $wpdb;
    
    // Lấy role_id của role 'admin'
    $role_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}aerp_roles WHERE name = %s",
        'admin'
    ));
    
    if (!$role_id) {
        return [];
    }
    
    // Lấy tất cả user_id có role 'admin'
    $user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->prefix}aerp_user_role WHERE role_id = %d",
        $role_id
    ));
    
    return array_map('intval', (array) $user_ids);
}

/**
 * Lấy user_id từ employee_id (fallback current user / admin)
 */
function aerp_get_user_id_from_employee($employee_id)
{
    global $wpdb;
    if ($employee_id) {
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
            $employee_id
        ));
        if ($user_id) {
            return (int) $user_id;
        }
    }

    $current = get_current_user_id();
    if ($current) {
        return $current;
    }
    $admin_user_ids = aerp_get_all_admin_user_ids();
    if (!empty($admin_user_ids)) {
        return (int) $admin_user_ids[0];
    }
    return $current ?: 0;
}

/**
 * Cron: quét các sự kiện lịch và tạo thông báo nhắc lịch trước giờ hẹn
 */
function aerp_run_calendar_reminders()
{
    global $wpdb;

    // Dùng timezone cố định Asia/Ho_Chi_Minh để đồng bộ với database
    $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
    $now = new DateTime('now', $tz);
    $now_ts = $now->getTimestamp();
    $now_mysql = $now->format('Y-m-d H:i:s');

    // Lấy các sự kiện có reminder_minutes, chưa gửi reminder
    // Lấy cả event đã qua nhưng chưa quá xa (trong vòng 2 giờ) và cả event trong tương lai
    $two_hours_ago = (clone $now)->modify('-2 hours')->format('Y-m-d H:i:s');
    $table = $wpdb->prefix . 'aerp_calendar_events';
    $events = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE reminder_minutes IS NOT NULL
               AND reminder_minutes > 0
               AND reminder_sent = 0
               AND start_date >= %s
             ORDER BY start_date ASC
             LIMIT 100",
            $two_hours_ago
        )
    );

    if (empty($events)) {
        return;
    }

    foreach ($events as $event) {
        // Parse start_date theo timezone Asia/Ho_Chi_Minh (giả sử DB lưu theo timezone này)
        try {
            $start_dt = new DateTime($event->start_date, $tz);
            $start_ts = $start_dt->getTimestamp();
        } catch (Exception $e) {
            // Fallback nếu parse lỗi
            $start_ts = strtotime($event->start_date);
            if (!$start_ts) {
                continue;
            }
            // Convert sang timezone Asia/Ho_Chi_Minh
            $start_dt = new DateTime('@' . $start_ts);
            $start_dt->setTimezone($tz);
            $start_ts = $start_dt->getTimestamp();
        }

        if (!$start_ts) {
            continue;
        }

        // Tính số phút chênh lệch (âm = đã qua, dương = chưa đến)
        $diff_minutes = ($start_ts - $now_ts) / 60;

        // Tính thời điểm nhắc (start_date - reminder_minutes)
        $reminder_ts = $start_ts - ((int)$event->reminder_minutes * 60);

        // Nhắc khi: đã đến hoặc vượt quá thời điểm nhắc
        // Điều kiện: hiện tại >= thời điểm nhắc (reminder_ts) và chưa quá 1 giờ sau giờ hẹn
        if ($now_ts >= $reminder_ts && $diff_minutes >= -60) {
            $user_id = aerp_get_calendar_event_user_id($event);
            if (!$user_id) {
                // Nếu không tìm được user_id, thử lấy user hiện tại
                $user_id = get_current_user_id();
                if (!$user_id) {
                    continue;
                }
            }

            // Tạo nội dung thông báo
            $title = 'Nhắc lịch: ' . $event->title;

            // Format thời gian theo timezone Asia/Ho_Chi_Minh
            $time_str = $start_dt->format('d/m/Y H:i');
            $message_parts = ["Thời gian: {$time_str}"];
            if (!empty($event->location)) {
                $message_parts[] = 'Địa điểm: ' . $event->location;
            }
            if (!empty($event->description)) {
                $message_parts[] = $event->description;
            }
            $message = implode(' | ', $message_parts);

            // Link ưu tiên: đơn hàng nếu có, sau đó khách hàng, nếu không thì ở lại trang lịch
            $link_url = home_url('/aerp-calendar');
            if (!empty($event->order_id)) {
                $link_url = home_url('/aerp-order-orders/' . $event->order_id);
            } elseif (!empty($event->customer_id)) {
                $link_url = home_url('/aerp-crm-customers/' . $event->customer_id);
            }

            $result = aerp_create_notification($user_id, 'calendar_reminder', $title, $message, $link_url, $event->id);

            // Đánh dấu đã gửi reminder (chỉ khi tạo notification thành công)
            if ($result !== false) {
                $wpdb->update(
                    $table,
                    ['reminder_sent' => 1],
                    ['id' => $event->id],
                    ['%d'],
                    ['%d']
                );
            }
        }
    }
}
