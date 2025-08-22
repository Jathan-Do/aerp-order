<?php
if (!defined('ABSPATH')) exit;

class AERP_Device_Return_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_order_device_returns',
            'columns' => [
                // 'id' => 'ID',
                'order_id' => 'Đơn hàng',
                'device_id' => 'Thiết bị',
                'return_date' => 'Ngày trả lại',
                'note' => 'Ghi chú',
                'action' => 'Thao tác'
            ],
            'sortable_columns' => ['id', 'order_id', 'device_id', 'return_date'],
            'searchable_columns' => ['order_id', 'device_id', 'return_date', 'note'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [],
            'bulk_actions' => [],
            'base_url' => home_url('/aerp-device-returns'),
            'delete_item_callback' => ['AERP_Device_Return_Manager', 'delete_device_return_by_id'],
            'nonce_action_prefix' => 'delete_device_return_',
            'message_transient_key' => 'aerp_device_return_message',
            'hidden_columns_option_key' => 'aerp_device_return_table_hidden_columns',
            'ajax_action' => 'aerp_device_return_filter_device_returns',
            'table_wrapper' => '#aerp-device-return-table-wrapper',
        ]);
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
        $buttons = [];
        if ($is_admin || $is_department_lead) {
            $buttons[] = sprintf('<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Chỉnh sửa" href="%s" class="btn btn-sm btn-success mb-2"><i class="fas fa-edit"></i></a>', esc_url($edit_url));
            $buttons[] = sprintf('<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Xóa" href="%s" class="btn btn-sm btn-danger mb-2" onclick="return confirm(\'Bạn có chắc muốn xóa?\')"><i class="fas fa-trash"></i></a>', esc_url($delete_url));
        }
        return implode(' ', $buttons);
    }
    protected function column_device_id($item)
    {
        if (empty($item->device_id)) {
            return '<span class="text-muted">--</span>';
        }
        
        global $wpdb;
        $device = $wpdb->get_row($wpdb->prepare(
            "SELECT device_name, serial_number FROM {$wpdb->prefix}aerp_order_devices WHERE id = %d",
            $item->device_id
        ));
        
        if ($device) {
            $device_info = esc_html($device->device_name);
            if (!empty($device->serial_number)) {
                $device_info .= '<br><small class="text-muted">Serial/IMEI: ' . esc_html($device->serial_number) . '</small>';
            }
            return $device_info;
        }
        
        return '<span class="text-muted">--</span>';
    }
    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];

        if (!empty($this->filters['date_from'])) {
            $filters[] = "return_date >= %s";
            $params[] = $this->filters['date_from'];
        }
        if (!empty($this->filters['date_to'])) {
            $filters[] = "return_date <= %s";
            $params[] = $this->filters['date_to'];
        }

        return [$filters, $params];
    }
    protected function get_extra_search_conditions($search_term)
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($search_term) . '%';
        return [
            [
                "(
                    order_id IN (SELECT id FROM {$wpdb->prefix}aerp_order_orders WHERE order_code LIKE %s)
                    OR
                    device_id IN (SELECT id FROM {$wpdb->prefix}aerp_order_devices WHERE device_name LIKE %s)
                )"
            ],
            [$like, $like]
        ];
    }
}
