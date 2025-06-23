<?php
if (!defined('ABSPATH')) {
    exit;
}

class AERP_Frontend_Customer_Table extends AERP_Frontend_Table
{
    protected $filters = [];

    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_crm_customers',
            'columns' => [
                'id' => 'ID',
                'full_name' => 'Họ và tên',
                'phones' => 'Số điện thoại',
                'company_name' => 'Tên công ty',
                'address' => 'Địa chỉ',
                'customer_type_id' => 'Loại khách hàng',
                'status' => 'Trạng thái',
                'assigned_to' => 'Nhân viên phụ trách',
                'created_at' => 'Ngày tạo',
            ],
            'sortable_columns' => ['id', 'full_name', 'status', 'customer_type_id', 'created_at'],
            'searchable_columns' => ['full_name', 'company_name'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-crm-customers'),
            'delete_item_callback' => ['AERP_Frontend_Customer_Manager', 'delete_customer_by_id'],
            'nonce_action_prefix' => 'delete_customer_',
            'message_transient_key' => 'aerp_customer_message',
            'hidden_columns_option_key' => 'aerp_crm_customer_table_hidden_columns',
            'ajax_action' => 'aerp_crm_filter_customers',
            'table_wrapper' => '#aerp-customer-table-wrapper',
        ]);
    }

    public function set_filters($filters = [])
    {
        parent::set_filters($filters); // Gọi cha để xử lý đầy đủ orderby, order, paged, search_term
    }

    /**
     * Điều kiện tìm kiếm số điện thoại liên bảng
     */
    protected function get_extra_search_conditions($search_term)
    {
        global $wpdb;
        return [
            ["id IN (SELECT customer_id FROM {$wpdb->prefix}aerp_crm_customer_phones WHERE phone_number LIKE %s)"],
            ['%' . $wpdb->esc_like($search_term) . '%']
        ];
    }

    /**
     * Điều kiện filter đặc thù cho bảng khách hàng
     */
    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];
        if (!empty($this->filters['customer_type_id'])) {
            $filters[] = "customer_type_id = %s";
            $params[] = $this->filters['customer_type_id'];
        }
        if (!empty($this->filters['status'])) {
            $filters[] = "status = %s";
            $params[] = $this->filters['status'];
        }
        if (!empty($this->filters['assigned_to'])) {
            $filters[] = "assigned_to = %d";
            $params[] = (int)$this->filters['assigned_to'];
        }
        return [$filters, $params];
    }

    /**
     * Hiển thị tên người phụ trách thay vì ID
     */
    protected function column_assigned_to($item)
    {
        $assigned_to_id = $item->assigned_to;
        $employee_name = aerp_get_customer_assigned_name($assigned_to_id); // Hàm đã có để lấy tên nhân viên
        if (empty($employee_name)) {
            return '<span class="badge bg-secondary">Không xác định</span>';
        }
        return esc_html($employee_name);
    }

    /**
     * Hiển thị cột full_name với liên kết đến trang chi tiết khách hàng
     */
    protected function column_full_name($item)
    {
        $detail_url = home_url('/aerp-crm-customers/' . $item->id);
        return sprintf('<a class="text-decoration-none" href="%s">%s</a>', esc_url($detail_url), esc_html($item->full_name));
    }

    /**
     * Hiển thị loại khách hàng thân thiện hơn
     */
    protected function column_customer_type_id($item)
    {
        global $wpdb;
        // $type = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}aerp_crm_customer_types WHERE id = %d", $item->customer_type_id));
        $type = aerp_get_customer_type($item->customer_type_id); // Sử dụng hàm đã có để lấy loại khách hàng
        if ($type) {
            $color = !empty($type->color) ? $type->color : 'secondary';
            return '<span class="badge bg-' . esc_attr($color) . '">' . esc_html($type->name) . '</span>';
        }
        return '<span class="badge bg-secondary">Không xác định</span>';
    }

    /**
     * Hiển thị trạng thái khách hàng thân thiện hơn
     */
    protected function column_status($item)
    {
        $statuses = [
            'active' => '<span class="badge bg-success">Hoạt động</span>',
            'inactive' => '<span class="badge bg-secondary">Không hoạt động</span>',
        ];
        return $statuses[$item->status] ?? esc_html($item->status);
    }

    /**
     * Hiển thị cột số điện thoại nâng cấp UX/UI
     */
    protected function column_phones($item)
    {
        $phones = aerp_get_customer_phones($item->id);
        if (!$phones) return '<span class="text-muted">--</span>';
        $out = [];
        foreach ($phones as $phone) {
            $str = '<a href="tel:' . esc_attr($phone->phone_number) . '">' . esc_html($phone->phone_number) . '</a>';
            $str .= ' <a href="#" class="copy-phone ms-1" data-phone="' . esc_attr($phone->phone_number) . '" title="Copy"><i class="fas fa-copy"></i></a>';
            if ($phone->is_primary) $str .= ' <span class="badge bg-success">Chính</span>';
            $out[] = $str;
        }
        return implode('<br>', $out);
    }
}
