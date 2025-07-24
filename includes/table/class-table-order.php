<?php
if (!defined('ABSPATH')) exit;

class AERP_Frontend_Order_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_order_orders',
            'columns' => [
                // 'id' => 'ID',
                'order_code' => 'Mã đơn',
                'customer_id' => 'Khách hàng',
                'employee_id' => 'Nhân viên',
                'order_date' => 'Ngày lập hóa đơn',
                'total_amount' => 'Tổng tiền',
                'status_id' => 'Trạng thái',
                'order_type' => 'Loại đơn',
                'note' => 'Ghi chú',
                'created_at' => 'Ngày tạo',
            ],
            'sortable_columns' => ['id', 'order_code', 'order_date', 'status', 'total_amount', 'created_at'],
            'searchable_columns' => ['order_code'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-order-orders'),
            'delete_item_callback' => ['AERP_Frontend_Order_Manager', 'delete_order_by_id'],
            'nonce_action_prefix' => 'delete_order_',
            'message_transient_key' => 'aerp_order_message',
            'hidden_columns_option_key' => 'aerp_order_table_hidden_columns',
            'ajax_action' => 'aerp_order_filter_orders',
            'table_wrapper' => '#aerp-order-table-wrapper',
        ]);
    }
    public function set_filters($filters = [])
    {
        parent::set_filters($filters); // Gọi cha để xử lý đầy đủ orderby, order, paged, search_term
    }
    protected function get_extra_filters()
    {
        global $wpdb;
        $filters = [];
        $params = [];
        
        // Filter theo chi nhánh của user hiện tại
        $current_user_id = get_current_user_id();
        $current_user_branch = $wpdb->get_var($wpdb->prepare(
            "SELECT work_location_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $current_user_id
        ));
        
        if ($current_user_branch) {
            // Lấy tất cả employee_id thuộc chi nhánh này
            $branch_employee_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE work_location_id = %d",
                $current_user_branch
            ));
            
            if (!empty($branch_employee_ids)) {
                $placeholders = implode(',', array_fill(0, count($branch_employee_ids), '%d'));
                $filters[] = "employee_id IN ($placeholders)";
                $params = array_merge($params, $branch_employee_ids);
            }
        }
        
        if (!empty($this->filters['status_id'])) {
            $filters[] = "status_id = %d";
            $params[] = (int)$this->filters['status_id'];
        }
        if (!empty($this->filters['employee_id'])) {
            $filters[] = "employee_id = %d";
            $params[] = (int)$this->filters['employee_id'];
        }
        if (!empty($this->filters['customer_id'])) {
            $filters[] = "customer_id = %d";
            $params[] = (int)$this->filters['customer_id'];
        }
        // Bổ sung filter loại đơn hàng động
        if (!empty($this->filters['order_type'])) {
            global $wpdb;
            $order_type = $this->filters['order_type'];
            $order_ids = [];
            if ($order_type === 'product') {
                $order_ids = $wpdb->get_col("SELECT order_id FROM {$wpdb->prefix}aerp_order_items GROUP BY order_id HAVING SUM(CASE WHEN item_type = 'service' OR (item_type IS NULL AND product_id IS NULL) THEN 1 ELSE 0 END) = 0");
            } elseif ($order_type === 'service') {
                $order_ids = $wpdb->get_col("SELECT order_id FROM {$wpdb->prefix}aerp_order_items GROUP BY order_id HAVING SUM(CASE WHEN item_type = 'product' OR (item_type IS NULL AND product_id IS NOT NULL) THEN 1 ELSE 0 END) = 0");
            } elseif ($order_type === 'mixed') {
                $order_ids = $wpdb->get_col("SELECT order_id FROM {$wpdb->prefix}aerp_order_items GROUP BY order_id HAVING SUM(CASE WHEN item_type = 'product' OR (item_type IS NULL AND product_id IS NOT NULL) THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN item_type = 'service' OR (item_type IS NULL AND product_id IS NULL) THEN 1 ELSE 0 END) > 0");
            }
            if (!empty($order_ids)) {
                $filters[] = "id IN (" . implode(',', array_map('intval', $order_ids)) . ")";
            } else {
                $filters[] = "0=1"; // Không có đơn nào
            }
        }
        return [$filters, $params];
    }

    protected function column_customer_id($item)
    {
        $customer = function_exists('aerp_get_customer') ? aerp_get_customer($item->customer_id) : null;
        if ($customer) {
            $url = home_url('/aerp-crm-customers/' . $customer->id);
            return sprintf('<a class="text-decoration-none" href="%s">%s</a>', esc_url($url), esc_html($customer->full_name));
        }
        return '<span class="text-muted">--</span>';
    }

    protected function column_employee_id($item)
    {
        $employee = function_exists('aerp_get_customer_assigned_name') ? aerp_get_customer_assigned_name($item->employee_id) : '';
        return $employee ? esc_html($employee) : '<span class="text-muted">--</span>';
    }

    protected function column_status_id($item)
    {
        $status = aerp_get_order_status($item->status_id);
        if ($status) {
            $color = !empty($status->color) ? $status->color : 'secondary';
            return '<span class="badge bg-' . esc_attr($color) . '">' . esc_html($status->name) . '</span>';
        }
        return '<span class="badge bg-secondary">Không xác định</span>';
    }
    protected function column_order_code($item)
    {
        $detail_url = home_url('/aerp-order-orders/' . $item->id);
        return sprintf('<a class="text-decoration-none" href="%s">%s</a>', esc_url($detail_url), esc_html($item->order_code));
    }
    protected function column_total_amount($item)
    {
        return sprintf('%s %s', number_format($item->total_amount, 0), 'đ');
    }
    protected function column_order_type($item)
    {
        global $wpdb;
        $order_id = $item->id;
        // Đếm số dòng sản phẩm và dịch vụ
        $count_product = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_items WHERE order_id = %d AND (item_type = 'product' OR (item_type IS NULL AND product_id IS NOT NULL))",
            $order_id
        ));
        $count_service = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_items WHERE order_id = %d AND (item_type = 'service' OR (item_type IS NULL AND product_id IS NULL))",
            $order_id
        ));
        if ($count_product > 0 && $count_service > 0) {
            $type = 'mixed';
        } elseif ($count_product > 0) {
            $type = 'product';
        } else {
            $type = 'service';
        }
        $types = [
            'product' => '<span class="badge bg-info">Bán hàng</span>',
            'service' => '<span class="badge bg-success">Dịch vụ</span>',
            'mixed'   => '<span class="badge bg-warning">Tổng hợp</span>',
        ];
        return $types[$type] ?? esc_html($type);
    }
}
