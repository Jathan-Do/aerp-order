<?php
if (!defined('ABSPATH')) {
    exit;
}

class AERP_Frontend_Customer_Logs_Table extends AERP_Frontend_Table
{
    private $customer_id;

    public function __construct($customer_id)
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_crm_logs',
            'columns' => [
                'id' => 'ID',
                'interaction_type' => 'Loại tương tác',
                'content' => 'Nội dung',
                'interacted_by' => 'Nhân viên thực hiện',
                'created_at' => 'Thời gian',
            ],
            'sortable_columns' => ['id', 'interaction_type', 'created_at'],
            'searchable_columns' => ['interaction_type', 'content'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [], // Không có hành động chỉnh sửa/xóa trực tiếp trên logs table
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-crm-customers/' . $customer_id . '?action=logs'), // Base URL cho phân trang/tìm kiếm với query parameter
            'delete_item_callback' => ['AERP_Frontend_Customer_Manager', 'delete_customer_log_by_id'],
            'message_transient_key' => 'aerp_customer_log_message',
            'hidden_columns_option_key' => 'aerp_crm_customer_logs_table_hidden_columns',
            'ajax_action' => 'aerp_crm_filter_customer_logs',
            'table_wrapper' => '#aerp-customer-logs-table-wrapper',
        ]);
        $this->customer_id = $customer_id;
    }

    public function render()
    {
        // Khởi tạo bộ lọc với customer_id ngay từ đầu để đảm bảo nó không bị mất trong các lần AJAX request sau
        if (empty($this->filters)) {
            $this->set_filters([
                'customer_id'      => $this->customer_id,
                'interaction_type' => isset($_REQUEST['interaction_type']) ? sanitize_text_field($_REQUEST['interaction_type']) : '',
                'search_term'      => isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '',
                'paged'            => isset($_REQUEST['paged']) ? intval($_REQUEST['paged']) : 1,
                'orderby'          => isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'created_at',
                'order'            => isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'desc',
            ]);
        }
        parent::render();
    }

    public function set_filters($filters = [])
    {
        parent::set_filters($filters); // Gọi cha để xử lý đầy đủ orderby, order, paged, search_term
    }

    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];
        
        // Sử dụng customer_id từ filters (AJAX) hoặc từ property (non-AJAX)
        $customer_id = !empty($this->filters['customer_id']) ? $this->filters['customer_id'] : $this->customer_id;
        
        if ($customer_id) {
            $filters[] = "customer_id = %d";
            $params[] = $customer_id;
        }
        
        if (!empty($this->filters['interaction_type'])) {
            $filters[] = "interaction_type = %s";
            $params[] = $this->filters['interaction_type'];
        }
        
        return [$filters, $params];
    }

    /**
     * Hiển thị tên người thực hiện thay vì ID
     */
    protected function column_interacted_by($item)
    {
        return esc_html(get_the_author_meta('display_name', $item->interacted_by));
    }

    /**
     * Render content column with line breaks
     */
    protected function column_content($item)
    {
        return nl2br(esc_html($item->content));
    }
}
