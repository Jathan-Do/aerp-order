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
                'changed_at' => 'Thời gian',
                'changed_by' => 'Người thay đổi',
                'old_status' => 'Trạng thái cũ',
                'new_status' => 'Trạng thái mới',
            ],
            'sortable_columns' => ['id', 'changed_at', 'changed_by'],
            'searchable_columns' => [],
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

    protected function column_old_status($item)
    {
        return $this->format_status($item->old_status);
    }

    protected function column_new_status($item)
    {
        return $this->format_status($item->new_status);
    }

    private function format_status($status)
    {
        $statuses = [
            'new' => '<span class="badge bg-primary">Mới</span>',
            'processing' => '<span class="badge bg-warning">Xử lý</span>',
            'completed' => '<span class="badge bg-success">Hoàn tất</span>',
            'cancelled' => '<span class="badge bg-danger">Hủy</span>',
        ];
        return $statuses[$status] ?? esc_html($status);
    }

    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];

        if ($this->order_id) {
            $filters[] = 'order_id = %d';
            $params[] = $this->order_id;
        }

        if (!empty($this->filters['old_status'])) {
            $filters[] = 'old_status = %s';
            $params[] = $this->filters['old_status'];
        }

        if (!empty($this->filters['new_status'])) {
            $filters[] = 'new_status = %s';
            $params[] = $this->filters['new_status'];
        }

        return [$filters, $params];
    }
}
