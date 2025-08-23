<?php
if (!defined('ABSPATH')) exit;

class AERP_Device_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_order_devices',
            'columns' => [
                // 'id' => 'ID',
                'order_id' => 'Đơn hàng',
                'device_name' => 'Tên thiết bị',
                'serial_number' => 'Serial/IMEI',
                'status' => 'Tình trạng',
                'progress_id' => 'Tiến độ',
                'note' => 'Ghi chú',
                'partner_id' => 'Nhà cung cấp',
                'device_status' => 'Trạng thái',
                'action' => 'Thao tác'
            ],
            'sortable_columns' => ['id', 'order_id', 'device_name', 'serial_number', 'status', 'partner_id'],
            'searchable_columns' => ['device_name', 'serial_number', 'status', 'note', 'partner_id'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [],
            'bulk_actions' => [],
            'base_url' => home_url('/aerp-devices'),
            'delete_item_callback' => ['AERP_Device_Manager', 'delete_device_by_id'],
            'nonce_action_prefix' => 'delete_device_',
            'message_transient_key' => 'aerp_device_message',
            'hidden_columns_option_key' => 'aerp_device_table_hidden_columns',
            'ajax_action' => 'aerp_device_filter_devices',
            'table_wrapper' => '#aerp-device-table-wrapper',
        ]);
    }
    protected function column_partner_id($item)
    {
        if (empty($item->partner_id)) {
            return '<span class="text-muted">--</span>';
        }

        global $wpdb;
        $partner = $wpdb->get_row($wpdb->prepare(
            "SELECT name, phone FROM {$wpdb->prefix}aerp_suppliers WHERE id = %d",
            $item->partner_id
        ));

        if ($partner) {
            $partner_info = esc_html($partner->name);
            if (!empty($partner->phone)) {
                $partner_info .= '<br><small class="text-muted">' . esc_html($partner->phone) . '</small>';
            }
            return $partner_info;
        }

        return '<span class="text-muted">--</span>';
    }

    protected function column_order_id($item)
    {
        if (empty($item->order_id)) {
            return '<span class="text-muted">--</span>';
        }

        global $wpdb;
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.order_code, o.customer_id, c.full_name 
             FROM {$wpdb->prefix}aerp_order_orders o 
             LEFT JOIN {$wpdb->prefix}aerp_crm_customers c ON o.customer_id = c.id 
             WHERE o.id = %d",
            $item->order_id
        ));

        if ($order) {
            $detail_url = home_url('/aerp-order-orders/' . $item->order_id);
            $order_info = sprintf('<a class="text-decoration-none" href="%s">%s</a>', esc_url($detail_url), esc_html($order->order_code));

            if (!empty($order->full_name)) {
                $order_info .= '<br><small class="text-muted">KH: ' . esc_html($order->full_name) . '</small>';
            }

            return $order_info;
        }

        return '<span class="text-muted">--</span>';
    }
    protected function column_action($item)
    {
        $id = intval($item->id);
        $user_id = get_current_user_id();
        $is_admin = function_exists('aerp_user_has_role') ? aerp_user_has_role($user_id, 'admin') : false;
        $is_department_lead = function_exists('aerp_user_has_role') ? aerp_user_has_role($user_id, 'department_lead') : false;
        $edit_url = add_query_arg(['action' => 'edit', 'id' => $id], $this->base_url);
        $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $id], $this->base_url), $this->nonce_action_prefix . $id);
        // Lấy ra id của khách hàng dựa vào order_id nối tới đơn hàng
        $customer_id = null;
        if (!empty($item->order_id)) {
            global $wpdb;
            $customer_id = $wpdb->get_var($wpdb->prepare(
                "SELECT customer_id FROM {$wpdb->prefix}aerp_order_orders WHERE id = %d",
                $item->order_id
            ));
        }
        $buttons = [];
        $order_url = esc_url(home_url('/aerp-order-orders/?action=add&customer_id=' . $customer_id)); 
        $buttons[] = sprintf('<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Tạo đơn trả" href="%s" class="btn btn-sm btn-info mb-2"><i class="fas fa-file-invoice "></i></a>', esc_url($order_url));
        if ($is_admin || $is_department_lead) {
            $buttons[] = sprintf('<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Chỉnh sửa" href="%s" class="btn btn-sm btn-success mb-2"><i class="fas fa-edit"></i></a>', esc_url($edit_url));
            $buttons[] = sprintf('<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Xóa" href="%s" class="btn btn-sm btn-danger mb-2" onclick="return confirm(\'Bạn có chắc muốn xóa?\')"><i class="fas fa-trash"></i></a>', esc_url($delete_url));
        }
        return implode(' ', $buttons);
    }
    protected function column_device_status($item)
    {
        $status = isset($item->device_status) ? $item->device_status : 'received';
        $map = [
            'received' => '<span class="badge bg-primary">Nhận thiết bị</span>',
            'disposed' => '<span class="badge bg-success">Trả thiết bị</span>',
        ];
        return $map[$status] ?? esc_html($status);
    }

    protected function column_status($item)
    {
        $status = isset($item->status) ? $item->status : '';
        if (empty($status)) {
            return '<span class="text-muted">--</span>';
        }
        return esc_html($status);
    }

    protected function column_progress_id($item)
    {
        $progress = AERP_Device_Progress_Manager::get_by_id($item->progress_id);

        if ($progress) {
            $color = $progress->color ?? '#007cba';
            return sprintf(
                '<span class="badge" style="background-color: %s; color: white;">%s</span>',
                esc_attr($color),
                esc_html($progress->name)
            );
        }

        return '<span class="badge bg-secondary">Không xác định</span>';
    }
    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];

        if (!empty($this->filters['partner_id'])) {
            $filters[] = 'partner_id = %s';
            $params[] = $this->filters['partner_id'];
        }

        if (!empty($this->filters['progress_id'])) {
            $filters[] = 'progress_id = %d';
            $params[] = (int)$this->filters['progress_id'];
        }

        return [$filters, $params];
    }
}
