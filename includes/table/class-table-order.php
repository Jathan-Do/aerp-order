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
                'customer_id' => 'Tên KH',
                'address' => 'Địa chỉ',
                'phones' => 'Số điện thoại',
                'content_lines' => 'ND yêu cầu & triển khai',
                'status_id' => 'Trạng thái',
                'note' => 'Ghi chú',
                'employee_id' => 'Người triển khai',
                'created_by' => 'Người tạo đơn',
                'created_at' => 'Ngày tạo',
                'reject_reason' => 'Lý do từ chối',
                'cancel_reason' => 'Lý do hủy',
                'order_date' => 'Ngày lập hóa đơn',
                'total_amount' => 'Doanh thu',
                'cost' => 'Chi phí',
                'profit' => 'Lợi nhuận',
                'customer_source' => 'Nguồn KH',
                'order_type' => 'Loại đơn',
                'status' => 'Tình trạng',
                'action' => 'Thao tác',
            ],
            'sortable_columns' => ['id', 'order_code', 'order_date', 'status', 'total_amount', 'created_at', 'cost', 'customer_id', 'created_by'],
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
    protected function get_extra_filters()
    {
        global $wpdb;
        $filters = [];
        $params = [];

        // Lấy thông tin user hiện tại
        $current_user_id = get_current_user_id();


        // Nếu là admin thì được xem tất cả đơn hàng
        if (function_exists('aerp_user_has_role') && aerp_user_has_role($current_user_id, 'admin')) {
            // Không filter gì cả, admin xem full
        } else {
            $current_user_employee = $wpdb->get_row($wpdb->prepare(
                "SELECT id, work_location_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
                $current_user_id
            ));
            $employee_current_id = $current_user_employee->id;
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
                            $filters[] = "(employee_id IN ($placeholders) OR created_by = %d)";
                            $params = array_merge($params, $branch_employee_ids, [$employee_current_id]);
                        } else {
                            // Nếu không có nhân viên nào trong chi nhánh, chỉ hiển thị đơn mình tạo
                            $filters[] = "created_by = %d";
                            $params[] = $employee_current_id;
                        }
                    } else {
                        // Không có chi nhánh, chỉ hiển thị đơn mình tạo
                        $filters[] = "created_by = %d";
                        $params[] = $employee_current_id;
                    }
                } else {
                    // Không có quyền: chỉ hiển thị đơn hàng của user hiện tại (tạo hoặc được phân)
                    $filters[] = "(employee_id = %d OR created_by = %d)";
                    $params[] = $current_user_employee->id;
                    $params[] = $employee_current_id;
                }
            } else {
                // Không phải nhân viên, chỉ hiển thị đơn mình tạo
                $filters[] = "created_by = %d";
                $params[] = $employee_current_id;
            }
        }

        if (!empty($this->filters['status_id'])) {
            $filters[] = "status_id = %d";
            $params[] = (int)$this->filters['status_id'];
        }
        if (!empty($this->filters['status'])) {
            $filters[] = "status = %s";
            $params[] = $this->filters['status'];
        }
        if (!empty($this->filters['employee_id'])) {
            $filters[] = "employee_id = %d";
            $params[] = (int)$this->filters['employee_id'];
        }
        if (!empty($this->filters['customer_id'])) {
            $filters[] = "customer_id = %d";
            $params[] = (int)$this->filters['customer_id'];
        }
        if (!empty($this->filters['customer_source_id'])) {
            $filters[] = "customer_source_id = %s";
            $params[] = (int)$this->filters['customer_source_id'];
        }
        if (!empty($this->filters['date_from'])) {
            $filters[] = "order_date >= %s";
            $params[] = $this->filters['date_from'];
        }
        if (!empty($this->filters['date_to'])) {
            $filters[] = "order_date <= %s";
            $params[] = $this->filters['date_to'];
        }
        if (!empty($this->filters['order_type'])) {
            $filters[] = "order_type = %s";
            $params[] = $this->filters['order_type'];
        }
        return [$filters, $params];
    }
    protected function column_phones($item)
    {
        $phones = aerp_get_customer_phones($item->customer_id);
        if (!$phones) return '<span class="text-muted">--</span>';
        $out = [];
        foreach ($phones as $phone) {
            $str = '<a href="tel:' . esc_attr($phone->phone_number) . '">' . esc_html($phone->phone_number) . '</a>';
            $str .= ' <a href="#" class="copy-phone ms-1" data-phone="' . esc_attr($phone->phone_number) . '" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Copy"><i class="fas fa-clipboard"></i></a>';
            if ($phone->is_primary) $str .= ' <span class="badge bg-success">Chính</span>';
            $out[] = $str;
        }
        return implode('<br>', $out);
    }
    protected function column_address($item)
    {
        if (empty($item->customer_id)) {
            return '<span class="text-muted">--</span>';
        }
        global $wpdb;
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT address FROM {$wpdb->prefix}aerp_crm_customers WHERE id = %d",
            $item->customer_id
        ));
        if ($customer && !empty($customer->address)) {
            return esc_html($customer->address);
        }
        return '<span class="text-muted">--</span>';
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
            return sprintf(
                '<span class="badge" style="background-color: %s; color: white;">%s</span>',
                esc_attr($color),
                esc_html($status->name)
            );
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

    protected function column_cost($item)
    {
        return sprintf('%s %s', number_format($item->cost ?? 0, 0), 'đ');
    }

    protected function column_profit($item)
    {
        global $wpdb;
        $orderId = (int) $item->id;

        // Lợi nhuận = Tổng tiền nội dung triển khai - Chi phí đơn hàng - Tổng tiền SP/DV - Tổng chi phí mua ngoài
        $contentTotal = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_price),0) FROM {$wpdb->prefix}aerp_order_content_lines WHERE order_id = %d",
            $orderId
        ));
        $itemsTotal = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(quantity * unit_price),0) FROM {$wpdb->prefix}aerp_order_items WHERE order_id = %d",
            $orderId
        ));
        $externalCostTotal = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(external_cost),0) FROM {$wpdb->prefix}aerp_order_items WHERE order_id = %d AND purchase_type = 'external'",
            $orderId
        ));

        $orderCost = (float) ($item->cost ?? 0);
        $profit = $contentTotal - $orderCost - $itemsTotal - $externalCostTotal;

        $color_class = $profit >= 0 ? 'text-success' : 'text-danger';
        return sprintf('<span class="%s fw-bold">%s %s</span>', $color_class, number_format($profit, 0), 'đ');
    }

    protected function column_customer_source($item)
    {
        $source_id = $item->customer_source_id ?? null;
        if ($source_id) {
            $source = function_exists('aerp_get_customer_source') ? aerp_get_customer_source($source_id) : null;
            if ($source) {
                $color = !empty($source->color) ? $source->color : 'secondary';
                return sprintf(
                    '<span class="badge" style="background-color: %s; color: white;">%s</span>',
                    esc_attr($color),
                    esc_html($source->name)
                );
            }
        }
        return '<span class="text-muted">--</span>';
    }

    protected function column_created_by($item)
    {
        if (empty($item->created_by)) {
            return '<span class="text-muted">--</span>';
        }
        // created_by lưu ID nhân sự => dùng helper để lấy tên nhân sự
        $employee_name = function_exists('aerp_get_customer_assigned_name')
            ? aerp_get_customer_assigned_name((int)$item->created_by)
            : '';
        if (empty($employee_name)) {
            return '<span class="text-muted">--</span>';
        }
        return esc_html($employee_name);
    }

    protected function column_content_lines($item)
    {
        global $wpdb;
        $order_id = $item->id;

        // Lấy nội dung từ bảng content_lines
        $content_lines = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aerp_order_content_lines WHERE order_id = %d ORDER BY sort_order ASC",
            $order_id
        ));

        if (empty($content_lines)) {
            return '<span class="text-muted">--</span>';
        }

        $output = [];
        foreach ($content_lines as $idx => $line) {
            $line_number = $idx + 1;
            $requirement = !empty($line->requirement) ? esc_html($line->requirement) : '<span class="text-muted">--</span>';
            $implementation = !empty($line->implementation) ? esc_html($line->implementation) : '<span class="text-muted">--</span>';

            $output[] = sprintf(
                '<div class="mb-2 p-2 border rounded bg-light">
                    <div class="fw-bold text-primary">Nội dung %d:</div>
                    <div class="row">
                        <div class="col-12">
                            <small class="text-muted">Yêu cầu:</small><br>
                            <span class="text-truncate" title="%s">%s</span>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">Triển khai:</small><br>
                            <span class="text-truncate " title="%s">%s</span>
                        </div>
                    </div>
                </div>',
                $line_number,
                esc_attr($line->requirement ?? ''),
                $requirement,
                esc_attr($line->implementation ?? ''),
                $implementation
            );
        }

        return implode('', $output);
    }
    protected function column_order_type($item)
    {
        global $wpdb;
        $order_type = $item->order_type ?? '';

        // Lấy các item_type và purchase_type để xác định có mua ngoài không
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT item_type, purchase_type FROM {$wpdb->prefix}aerp_order_items WHERE order_id = %d",
            $item->id
        ));

        $has_product = false;
        $has_service = false;
        $has_external = false;

        if (!empty($items)) {
            foreach ($items as $item_row) {
                if ($item_row->item_type === 'product') {
                    $has_product = true;
                } elseif ($item_row->item_type === 'service') {
                    $has_service = true;
                }

                if (isset($item_row->purchase_type) && $item_row->purchase_type === 'external') {
                    $has_external = true;
                }
            }
        }

        // Nếu có order_type trong DB, ưu tiên sử dụng, nhưng nếu là product thì kiểm tra có mua ngoài không
        if (!empty($order_type)) {
            $type_labels = [
                'content' => '<span class="badge bg-secondary">Nội dung</span>',
                'device' => '<span class="badge bg-primary">Nhận thiết bị</span>',
                'return' => '<span class="badge bg-danger">Trả thiết bị</span>',
                'service' => '<span class="badge bg-success">Dịch vụ</span>',
                'product' => '<span class="badge bg-info">Bán hàng</span>',
                'mixed' => '<span class="badge bg-warning">Tổng hợp</span>'
            ];

            if ($order_type === 'product') {
                $label = $type_labels['product'];
                if ($has_external) {
                    $label .= '<br><small class="text-muted">(Có mua ngoài)</small>';
                }
                return $label;
            }

            if (isset($type_labels[$order_type])) {
                return $type_labels[$order_type];
            }
        }

        // Fallback: xác định dựa trên order_items nếu không có order_type
        if (empty($items)) {
            return '<span class="badge bg-secondary">Không xác định</span>';
        }

        if ($has_product && $has_service) {
            return '<span class="badge bg-warning">Tổng hợp</span>';
        } elseif ($has_product) {
            $label = '<span class="badge bg-info">Bán hàng</span>';
            if ($has_external) {
                $label .= '<br><small class="text-muted">(Có mua ngoài)</small>';
            }
            return $label;
        } elseif ($has_service) {
            return '<span class="badge bg-success">Dịch vụ</span>';
        } else {
            return '<span class="badge bg-secondary">Không xác định</span>';
        }
    }

    protected function column_action($item)
    {
        $id = intval($item->id);
        $user_id = get_current_user_id();
        global $wpdb;
        $employee_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $user_id
        ));
        $is_admin = function_exists('aerp_user_has_role') ? aerp_user_has_role($user_id, 'admin') : false;
        $is_accountant = function_exists('aerp_user_has_role') ? aerp_user_has_role($user_id, 'accountant') : false;
        $current_status = isset($item->status) ? $item->status : 'new';

        $edit_url = add_query_arg(['action' => 'edit', 'id' => $id], $this->base_url);
        $delete_url = wp_nonce_url(add_query_arg(['action' => 'delete', 'id' => $id], $this->base_url), $this->nonce_action_prefix . $id);

        $buttons = [];

        // Nút chỉnh sửa - chỉ bị disabled khi đã thu tiền (trừ admin)
        if ($current_status !== 'paid' || $is_admin) {
            $buttons[] = sprintf('<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Chỉnh sửa" href="%s" class="btn btn-sm btn-success mb-2"><i class="fas fa-edit"></i></a>', esc_url($edit_url));
        }

        // Nút xóa - chỉ admin mới có quyền, và chỉ cho đơn chưa thu tiền
        if ($is_admin || $current_status !== 'paid') {
            $buttons[] = sprintf('<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Xóa" href="%s" class="btn btn-sm btn-danger mb-2" onclick="return confirm(\'Bạn có chắc muốn xóa?\')"><i class="fas fa-trash"></i></a>', esc_url($delete_url));
        }

        // Nút từ chối - chỉ cho đơn đã phân và nhân viên được phân (hoặc admin)
        if ($current_status === 'assigned' && ($item->employee_id == $employee_id || $is_admin)) {
            $buttons[] = sprintf(
                '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Từ chối" href="#" class="btn btn-sm btn-warning reject-order-btn mb-2" data-order-id="%d" data-order-code="%s"><i class="fas fa-times"></i></a>',
                $id,
                esc_attr($item->order_code)
            );
        }

        // Nút hoàn thành - chỉ cho đơn đã phân và nhân viên được phân (hoặc admin)
        if ($current_status === 'assigned' && ($item->employee_id == $employee_id || $is_admin)) {
            $buttons[] = sprintf(
                '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Hoàn thành" href="#" class="btn btn-sm btn-info complete-order-btn mb-2" data-order-id="%d" data-order-code="%s"><i class="fas fa-check"></i></a>',
                $id,
                esc_attr($item->order_code)
            );
        }

        // Nút thu tiền - chỉ kế toán mới có quyền, cho đơn đã hoàn thành (hoặc admin)
        if ($current_status === 'completed' && ($is_accountant || $is_admin)) {
            $buttons[] = sprintf(
                '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Thu tiền" href="#" class="btn btn-sm btn-success mark-paid-btn mb-2" data-order-id="%d" data-order-code="%s"><i class="fas fa-money-bill"></i></a>',
                $id,
                esc_attr($item->order_code)
            );
        }

        // Nút hủy đơn - chỉ cho đơn chưa thu tiền
        if ($current_status !== 'paid' && $current_status !== 'cancelled') {
            $buttons[] = sprintf(
                '<a data-bs-toggle="tooltip" data-bs-placement="left" data-bs-title="Hủy" href="#" class="btn btn-sm btn-danger cancel-order-btn mb-2" data-order-id="%d" data-order-code="%s"><i class="fas fa-ban"></i></a>',
                $id,
                esc_attr($item->order_code)
            );
        }

        return implode(' ', $buttons);
    }
    protected function column_status($item)
    {
        $status = isset($item->status) ? $item->status : 'new';
        $map = [
            'new'       => '<span class="badge bg-secondary">Mới tiếp nhận</span>',
            'assigned'  => '<span class="badge bg-primary">Đã phân đơn</span>',
            'rejected'  => '<span class="badge bg-warning">Đơn từ chối</span>',
            'completed' => '<span class="badge bg-info">Đã hoàn thành</span>',
            'paid'      => '<span class="badge bg-success">Đã thu tiền</span>',
            'cancelled' => '<span class="badge bg-danger">Đã hủy</span>',
        ];
        return $map[$status] ?? esc_html($status);
    }
    protected function get_extra_search_conditions($search_term)
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($search_term) . '%';
        return [
            [
                "(
                    customer_id IN (SELECT customer_id FROM {$wpdb->prefix}aerp_crm_customer_phones WHERE phone_number LIKE %s)
                    OR
                    customer_id IN (SELECT id FROM {$wpdb->prefix}aerp_crm_customers WHERE full_name LIKE %s)
                )"
            ],
            [$like, $like]
        ];
    }
    protected function column_note($item)
    {
        $note = isset($item->note) ? $item->note : '';
        // Giới hạn chiều cao, nếu vượt thì scroll
        $style = 'max-height:300px; overflow:auto; display:block; white-space:pre-line;';
        return sprintf('<div style="%s">%s</div>', esc_attr($style), esc_html($note));
    }
}
