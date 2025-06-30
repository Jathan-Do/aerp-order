<?php
if (!defined('ABSPATH')) exit;

class AERP_Frontend_Order_Status_Log_Table extends AERP_Frontend_Table
{
    private $order_id;

    public function __construct($order_id)
    {
        $this->order_id = absint($order_id);

        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_order_status_logs',
            'columns' => [
                'id' => 'ID',
                'old_status_id' => 'Trạng thái cũ',
                'new_status_id' => 'Trạng thái mới',
                'changed_at' => 'Thời gian',
                'changed_by' => 'Người thay đổi',
            ],
            'sortable_columns' => ['id', 'changed_at', 'changed_by'],
            'searchable_columns' => [
                'old_status_id',
                'new_status_id'
            ],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-order-orders/' . $this->order_id),
            'delete_item_callback' => ['AERP_Frontend_Order_Manager', 'delete_order_log_by_id'],
            'message_transient_key' => 'aerp_order_status_log_message',
            'hidden_columns_option_key' => 'aerp_order_status_log_table_hidden_columns',
            'ajax_action' => 'aerp_order_filter_status_logs',
            'table_wrapper' => '#aerp-order-status-log-table-wrapper',
        ]);
    }

    public function set_filters($filters = [])
    {
        parent::set_filters($filters); // Gọi cha để xử lý đầy đủ orderby, order, paged, search_term
    }

    protected function column_changed_at($item)
    {
        return esc_html(date('d/m/Y H:i', strtotime($item->changed_at)));
    }

    protected function column_changed_by($item)
    {
        $user = get_userdata($item->changed_by);
        return $user ? esc_html($user->display_name) : 'ID: ' . intval($item->changed_by);
    }

    protected function column_old_status_id($item)
    {
        return $this->format_status($item->old_status_id);
    }

    protected function column_new_status_id($item)
    {
        return $this->format_status($item->new_status_id);
    }

    private function format_status($status_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aerp_order_statuses';
        $status = $wpdb->get_row($wpdb->prepare("SELECT name, color FROM $table WHERE id = %d", $status_id));
        if ($status) {
            $color = $status->color ? 'bg-' . esc_attr($status->color) : 'bg-secondary';
            return '<span class="badge ' . $color . '">' . esc_html($status->name) . '</span>';
        }
        return '<span class="badge bg-secondary">Không rõ</span>';
    }

    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];

        if ($this->order_id) {
            $filters[] = 'order_id = %d';
            $params[] = $this->order_id;
        }

        if (!empty($this->filters['old_status_id'])) {
            $filters[] = 'old_status_id = %d';
            $params[] = (int)$this->filters['old_status_id'];
        }

        if (!empty($this->filters['new_status_id'])) {
            $filters[] = 'new_status_id = %d';
            $params[] = (int)$this->filters['new_status_id'];
        }

        return [$filters, $params];
    }

    protected function get_extra_search_conditions($search_term)
    {
        global $wpdb;
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        // Join sang bảng trạng thái để search theo tên
        $extra = [];
        $params = [];
        $extra[] = "old_status_id IN (SELECT id FROM {$wpdb->prefix}aerp_order_statuses WHERE name LIKE %s)";
        $params[] = $search_term;
        $extra[] = "new_status_id IN (SELECT id FROM {$wpdb->prefix}aerp_order_statuses WHERE name LIKE %s)";
        $params[] = $search_term;
        return [$extra, $params];
    }
}
