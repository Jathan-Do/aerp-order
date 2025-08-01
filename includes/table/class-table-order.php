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
                'status' => 'Tình trạng',
                'created_at' => 'Ngày tạo',
                'action' => 'Thao tác',
            ],
            'sortable_columns' => ['id', 'order_code', 'order_date', 'status', 'total_amount', 'created_at'],
            'searchable_columns' => ['order_code'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [],
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

        // Lấy thông tin user hiện tại
        $current_user_id = get_current_user_id();
        $current_user_employee = $wpdb->get_row($wpdb->prepare(
            "SELECT id, work_location_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $current_user_id
        ));

        if ($current_user_employee) {
            // Kiểm tra quyền order_view_full
            if (aerp_user_has_permission($current_user_id, 'order_view_full')) {
                // Có quyền xem full: hiển thị tất cả đơn hàng thuộc chi nhánh
                if ($current_user_employee->work_location_id) {
                    $branch_employee_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE work_location_id = %d",
                        $current_user_employee->work_location_id
                    ));

                    if (!empty($branch_employee_ids)) {
                        $placeholders = implode(',', array_fill(0, count($branch_employee_ids), '%d'));
                        $filters[] = "employee_id IN ($placeholders)";
                        $params = array_merge($params, $branch_employee_ids);
                    }
                }
            } else {
                // Không có quyền: chỉ hiển thị đơn hàng của user hiện tại
                $filters[] = "employee_id = %d";
                $params[] = $current_user_employee->id;
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

    protected function column_action($item)
    {
        $id = intval($item->id);
        $user_id = get_current_user_id();
        $is_admin = function_exists('aerp_user_has_role') ? aerp_user_has_role($user_id, 'admin') : false;


        // Kiểm tra trạng thái đã xác nhận (status có thể là chuỗi hoặc số, tùy hệ thống)
        $is_confirmed = (isset($item->status) && $item->status === 'confirmed');
        $is_cancelled = (isset($item->status) && $item->status === 'cancelled') || !empty($item->cancel_reason);


        if ($is_confirmed && !$is_admin) {
            return '<a href="#" class="btn btn-sm btn-success disabled mb-2 mb-md-0"><i class="fas fa-edit"></i></a> 
            <a href="#" class="btn btn-sm btn-danger disabled"><i class="fas fa-trash"></i></a>';
        }
        $edit_url = add_query_arg(['action' => 'edit', 'id' => $id], $this->base_url);
        $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $id], $this->base_url), $this->nonce_action_prefix . $id);

        $cancel_btn = '';
        if (!$is_cancelled) {
            $cancel_btn = sprintf(
                '<button type="button" class="btn btn-sm btn-warning cancel-order-btn" data-order-id="%d" data-order-code="%s"><i class="fas fa-times"></i></button>',
                $id,
                esc_attr($item->order_code)
            );
        }
        return sprintf(
            '<a href="%s" class="btn btn-sm btn-success mb-2 mb-md-0"><i class="fas fa-edit"></i></a> 
            <a href="%s" class="btn btn-sm btn-danger" onclick="return confirm(\'Bạn có chắc muốn xóa?\')"><i class="fas fa-trash"></i></a>
            %s',
            esc_url($edit_url),
            esc_url($delete_url),
            $cancel_btn
        );
    }
    protected function column_status($item)
    {
        $status = isset($item->status) ? $item->status : 'draft';
        $map = [
            'draft'     => '<span class="badge bg-secondary">Nháp</span>',
            'confirmed' => '<span class="badge bg-success">Đã xác nhận</span>',
            'cancelled' => '<span class="badge bg-danger">Đã hủy</span>',
        ];
        return $map[$status] ?? esc_html($status);
    }
}
