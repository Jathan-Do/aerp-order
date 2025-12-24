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
                'order_date' => 'Ngày lập hóa đơn',
                'created_at' => 'Ngày tạo',
                'total_amount' => 'Doanh thu',
                'cost' => 'Chi phí',
                'profit' => 'Lợi nhuận',
                'customer_source' => 'Nguồn KH',
                'order_type' => 'Loại đơn',
                'reject_reason' => 'Lý do từ chối',
                'cancel_reason' => 'Lý do hủy',
                'status' => 'Tình trạng',
                'action' => 'Thao tác',
            ],
            'sortable_columns' => ['id', 'order_code', 'order_date', 'status', 'total_amount', 'created_at', 'cost', 'customer_id', 'created_by', 'profit'],
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
        // Nếu đã có profit_value từ query (phục vụ sort), dùng lại để đồng bộ hiển thị
        if (isset($item->profit_value)) {
            $profit = (float) $item->profit_value;
        } else {
            $orderId = (int) $item->id;
            $orderType = $item->order_type ?? '';
            $status = $item->status ?? '';

            // Tính các thành phần
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

            // Áp dụng công thức theo điều kiện
            if ($orderType === 'all') {
                // Nếu order_type là 'all': Lợi nhuận = Tổng tiền nội dung triển khai - Chi phí đơn hàng - Tổng tiền SP/DV - Tổng chi phí mua ngoài
                $profit = $contentTotal - $orderCost - $itemsTotal - $externalCostTotal;
            } elseif ($orderType !== 'all' && $status === 'paid') {
                // Nếu order_type khác 'all' và status là 'paid': Lợi nhuận = Chi phí đơn hàng + Tổng tiền SP/DV - Tổng chi phí mua ngoài
                $profit = $orderCost + $itemsTotal - $externalCostTotal;
            } else {
                // Trường hợp khác: giữ công thức cũ
                $profit = $contentTotal - $orderCost - $itemsTotal - $externalCostTotal;
            }
        }

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
                'mixed' => '<span class="badge bg-warning">Bán hàng + Dịch vụ</span>',
                'all' => '<span class="badge bg-dark">Tổng hợp</span>'
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

        // Fallback: xác định dựa trên order_items/device/return/content nếu không có order_type
        // if (empty($items)) {
        //     // Kiểm tra thêm content/devices/returns để quyết định mixed
        //     $content_count = (int) $wpdb->get_var($wpdb->prepare(
        //         "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_content_lines WHERE order_id = %d",
        //         $item->id
        //     ));
        //     $device_count = (int) $wpdb->get_var($wpdb->prepare(
        //         "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_devices WHERE order_id = %d",
        //         $item->id
        //     ));
        //     $return_count = (int) $wpdb->get_var($wpdb->prepare(
        //         "SELECT COUNT(*) FROM {$wpdb->prefix}aerp_order_device_returns WHERE order_id = %d",
        //         $item->id
        //     ));
        //     $sections = ($content_count>0 ? 1:0) + ($device_count>0 ? 1:0) + ($return_count>0 ? 1:0);
        //     if ($sections > 1) {
        //         return '<span class="badge bg-warning">Tổng hợp</span>';
        //     }
        //     return '<span class="badge bg-secondary">Không xác định</span>';
        // }

        if ($has_product && $has_service) {
            return '<span class="badge bg-warning">Bán hàng + Dịch vụ</span>';
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

    /**
     * Ghi đè get_items để hỗ trợ sort theo lợi nhuận (profit) - cột không có trong DB.
     */
    public function get_items()
    {
        global $wpdb;
        $where = [];
        $params = [];

        // Search
        if ($this->search_term && !empty($this->searchable_columns)) {
            $search_conditions = [];
            foreach ($this->searchable_columns as $column) {
                $search_conditions[] = "$column LIKE %s";
                $params[] = '%' . $wpdb->esc_like($this->search_term) . '%';
            }
            // Thêm điều kiện search mở rộng (số điện thoại, tên KH)
            list($extra_search, $extra_params) = $this->get_extra_search_conditions($this->search_term);
            $search_conditions = array_merge($search_conditions, $extra_search);
            $params = array_merge($params, $extra_params);
            $where[] = '(' . implode(' OR ', $search_conditions) . ')';
        }

        // Filter mở rộng
        list($extra_filters, $extra_filter_params) = $this->get_extra_filters();
        $where = array_merge($where, $extra_filters);
        $params = array_merge($params, $extra_filter_params);

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($this->current_page - 1) * $this->per_page;

        // Sort mapping (profit dùng alias profit_value)
        $allowed_sort = [
            'id'            => 'o.id',
            'order_code'    => 'o.order_code',
            'order_date'    => 'o.order_date',
            'status'        => 'o.status',
            'total_amount'  => 'o.total_amount',
            'created_at'    => 'o.created_at',
            'cost'          => 'o.cost',
            'customer_id'   => 'o.customer_id',
            'created_by'    => 'o.created_by',
            'profit'        => 'profit_value',
        ];
        $order_by = isset($allowed_sort[$this->sort_column]) ? $allowed_sort[$this->sort_column] : 'o.id';
        $order_dir = strtolower($this->sort_order) === 'asc' ? 'ASC' : 'DESC';

        // Count
        $total_query = "SELECT COUNT(*) FROM {$this->table_name} o $where_clause";
        if (!empty($params)) {
            $total_query = $wpdb->prepare($total_query, $params);
        }
        $this->total_items = (int) $wpdb->get_var($total_query);

        // Data query with computed profit
        $table_items = $wpdb->prefix . 'aerp_order_items';
        $table_contents = $wpdb->prefix . 'aerp_order_content_lines';

        $query = "
            SELECT 
                o.*,
                -- profit_value tính theo cùng công thức ở column_profit
                (
                    CASE 
                        WHEN o.order_type = 'all' THEN
                            (COALESCE((SELECT SUM(total_price) FROM {$table_contents} c WHERE c.order_id = o.id), 0)
                             - COALESCE(o.cost, 0)
                             - COALESCE((SELECT SUM(quantity * unit_price) FROM {$table_items} i WHERE i.order_id = o.id), 0)
                             - COALESCE((SELECT SUM(external_cost) FROM {$table_items} i2 WHERE i2.order_id = o.id AND i2.purchase_type = 'external'), 0))
                        WHEN o.order_type <> 'all' AND o.status = 'paid' THEN
                            (COALESCE(o.cost, 0)
                             + COALESCE((SELECT SUM(quantity * unit_price) FROM {$table_items} i3 WHERE i3.order_id = o.id), 0)
                             - COALESCE((SELECT SUM(external_cost) FROM {$table_items} i4 WHERE i4.order_id = o.id AND i4.purchase_type = 'external'), 0))
                        ELSE
                            (COALESCE((SELECT SUM(total_price) FROM {$table_contents} c2 WHERE c2.order_id = o.id), 0)
                             - COALESCE(o.cost, 0)
                             - COALESCE((SELECT SUM(quantity * unit_price) FROM {$table_items} i5 WHERE i5.order_id = o.id), 0)
                             - COALESCE((SELECT SUM(external_cost) FROM {$table_items} i6 WHERE i6.order_id = o.id AND i6.purchase_type = 'external'), 0))
                    END
                ) AS profit_value
            FROM {$this->table_name} o
            $where_clause
            ORDER BY $order_by $order_dir
            LIMIT %d OFFSET %d
        ";

        $params2 = array_merge($params, [$this->per_page, $offset]);
        $this->items = $wpdb->get_results($wpdb->prepare($query, $params2));

        return $this->items;
    }
    protected function column_note($item)
    {
        $note = isset($item->note) ? $item->note : '';
        // Giới hạn chiều cao, nếu vượt thì scroll
        $style = 'max-height:300px; overflow:auto; display:block; white-space:pre-line;';
        return sprintf('<div style="%s">%s</div>', esc_attr($style), esc_html($note));
    }
}
