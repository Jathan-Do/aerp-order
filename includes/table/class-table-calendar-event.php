<?php
if (!defined('ABSPATH')) exit;

class AERP_Calendar_Event_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_calendar_events',
            'columns' => [
                'start_date' => 'Thời gian',
                'title' => 'Tiêu đề',
                'related' => 'Liên quan',
                'event_type' => 'Loại',
                'description' => 'Mô tả',
            ],
            'sortable_columns' => ['start_date', 'title', 'event_type', 'created_at'],
            'searchable_columns' => ['title', 'description', 'location'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-calendar'),
            'delete_item_callback' => ['AERP_Calendar_Manager', 'delete_event_by_id'],
            'nonce_action_prefix' => 'delete_event_',
            'message_transient_key' => 'aerp_calendar_message',
            'hidden_columns_option_key' => 'aerp_calendar_event_table_hidden_columns',
            'ajax_action' => 'aerp_calendar_filter_events',
            'table_wrapper' => '#aerp-calendar-event-table-wrapper',
        ]);
    }

    protected function column_start_date($item)
    {
        global $wpdb;
        $start = new DateTime($item->start_date);
        $end = $item->end_date ? new DateTime($item->end_date) : null;
        $time_str = $start->format('d/m H:i');
        if ($end) {
            $time_str .= ' - ' . $end->format('d/m H:i');
        }

        $html = '<span class="badge" style="background-color: ' . esc_attr($item->color ?: '#007cba') . ';">&nbsp;</span> ';
        $html .= esc_html($time_str);
        if (!empty($item->is_all_day)) {
            $html .= '<br><small class="text-muted">Cả ngày</small>';
        }
        return $html;
    }

    protected function column_title($item)
    {
        $edit_url = $this->get_base_url(['action' => 'edit', 'id' => $item->id]);
        $html = '<strong><a href="' . esc_url($edit_url) . '" class="text-decoration-none">' . esc_html($item->title) . '</a></strong>';
        if (!empty($item->location)) {
            $html .= '<br><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i>' . esc_html($item->location) . '</small>';
        }
        return $html;
    }

    protected function column_related($item)
    {
        global $wpdb;
        $related = [];

        if (!empty($item->customer_id)) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT full_name, customer_code FROM {$wpdb->prefix}aerp_crm_customers WHERE id = %d",
                $item->customer_id
            ));
            if ($customer) {
                $customer_url = home_url('/aerp-crm-customers/' . $item->customer_id);
                $customer_label = sprintf(
                    '<a href="%s" class="text-decoration-none" target="_blank">%s%s</a>',
                    esc_url($customer_url),
                    esc_html($customer->full_name),
                    !empty($customer->customer_code) ? ' (' . esc_html($customer->customer_code) . ')' : ''
                );
                $related[] = 'KH: ' . $customer_label;
            }
        }

        if (!empty($item->order_id)) {
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT order_code FROM {$wpdb->prefix}aerp_order_orders WHERE id = %d",
                $item->order_id
            ));
            if ($order) {
                $order_url = home_url('/aerp-order-orders/' . $item->order_id);
                $order_label = sprintf(
                    '<a href="%s" class="text-decoration-none" target="_blank">%s</a>',
                    esc_url($order_url),
                    esc_html($order->order_code)
                );
                $related[] = 'ĐH: ' . $order_label;
            }
        }

        if (empty($related)) {
            return '<span class="text-muted">--</span>';
        }

        return implode('<br>', $related);
    }

    protected function column_event_type($item)
    {
        switch ($item->event_type) {
            case 'appointment':
                return '<span class="badge bg-primary">Lịch hẹn</span>';
            case 'delivery':
                return '<span class="badge bg-success">Giao hàng</span>';
            case 'meeting':
                return '<span class="badge bg-info">Cuộc họp</span>';
            case 'reminder':
                return '<span class="badge bg-warning text-dark">Nhắc nhở</span>';
            default:
                return '<span class="badge bg-secondary">Khác</span>';
        }
    }

    protected function get_extra_filters()
    {
        global $wpdb;
        $conditions = [];
        $params = [];

        // Filter theo employee_id nếu có
        $user_id = get_current_user_id();
        $employee_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $user_id
        ));
        if ($employee_id) {
            $conditions[] = "(employee_id = %d OR employee_id IS NULL OR employee_id = 0)";
            $params[] = $employee_id;
        }
        
        // Filter theo color nếu có
        if (!empty($this->filters['color'])) {
            $conditions[] = 'color = %s';
            $params[] = sanitize_text_field($this->filters['color']);
        }
        
        // Filter theo date_from và date_to (ưu tiên hơn filter theo tháng)
        if (!empty($this->filters['date_from']) || !empty($this->filters['date_to'])) {
            if (!empty($this->filters['date_from'])) {
                // Thêm thời gian 00:00:00 nếu chỉ có date
                $date_from = $this->filters['date_from'];
                if (strlen($date_from) === 10) {
                    $date_from .= ' 00:00:00';
                }
                $conditions[] = "start_date >= %s";
                $params[] = $date_from;
            }
            if (!empty($this->filters['date_to'])) {
                // Thêm thời gian 23:59:59 nếu chỉ có date
                $date_to = $this->filters['date_to'];
                if (strlen($date_to) === 10) {
                    $date_to .= ' 23:59:59';
                }
                $conditions[] = "start_date <= %s";
                $params[] = $date_to;
            }
        } else {
            // Chỉ filter theo tháng nếu không có date_from/date_to
            $month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : (isset($this->filters['month']) ? $this->filters['month'] : date('Y-m'));
            if ($month) {
                try {
                    $start_of_month = new DateTime($month . '-01');
                    $end_of_month = clone $start_of_month;
                    $end_of_month->modify('last day of this month')->setTime(23, 59, 59);
                    $start_str = $start_of_month->format('Y-m-d 00:00:00');
                    $end_str = $end_of_month->format('Y-m-d 23:59:59');
                    $conditions[] = "start_date BETWEEN %s AND %s";
                    $params[] = $start_str;
                    $params[] = $end_str;
                } catch (Exception $e) {
                    // Ignore invalid date
                }
            }
        }
        
        return [$conditions, $params];
    }
}
