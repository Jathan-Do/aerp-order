<?php
if (!defined('ABSPATH')) {
    exit;
}

class AERP_Frontend_Customer_Type_Table extends AERP_Frontend_Table
{
    protected $filters = [];

    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_crm_customer_types',
            'columns' => [
                'id' => 'ID',
                'type_key' => 'Mã loại',
                'name' => 'Tên loại',
                'description' => 'Mô tả',
                'color' => 'Màu sắc',
                'created_at' => 'Ngày tạo',
            ],
            'sortable_columns' => ['id', 'type_key', 'name', 'created_at'],
            'searchable_columns' => ['type_key', 'name', 'description'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-crm-customer-types'),
            'delete_item_callback' => ['AERP_Frontend_Customer_Type_Manager', 'delete_customer_type_by_id'],
            'nonce_action_prefix' => 'delete_customer_type_',
            'message_transient_key' => 'aerp_customer_type_message',
            'hidden_columns_option_key' => 'aerp_crm_customer_type_table_hidden_columns',
            'ajax_action' => 'aerp_crm_filter_customers_type',
            'table_wrapper' => '#aerp-customer-type-table-wrapper',
        ]);
    }

    public function set_filters($filters = [])
    {
        parent::set_filters($filters); // Gọi cha để xử lý đầy đủ orderby, order, paged, search_term
    }

    /**
     * Hiển thị màu sắc dạng badge
     */
    protected function column_color($item)
    {
        $bootstrap_colors = [
            'primary' => 'Xanh dương',
            'secondary' => 'Xám',
            'success' => 'Xanh lá',
            'danger' => 'Đỏ',
            'warning' => 'Vàng',
            'info' => 'Xanh nhạt',
            'dark' => 'Đen',
        ];
        $label = $bootstrap_colors[$item->color] ?? $item->color;
        return '<span class="badge bg-' . esc_attr($item->color) . '">' . esc_html($label) . '</span>';
    }

    /**
     * Điều kiện filter đặc thù cho bảng loại khách hàng
     */
    protected function get_extra_filters() {
        $filters = [];
        $params = [];
        if (!empty($this->filters['color'])) {
            $filters[] = "color = %s";
            $params[] = $this->filters['color'];
        }
        return [$filters, $params];
    }
} 