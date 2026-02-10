<?php
if (!defined('ABSPATH')) exit;

class AERP_Order_Status_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_order_statuses',
            'columns' => [
                // 'id' => 'ID',
                'name' => 'Tên trạng thái',
                'color' => 'Màu sắc',
                'description' => 'Mô tả',
            ],
            'sortable_columns' => ['id', 'name', 'color'],
            'searchable_columns' => ['name', 'description'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-order-statuses'),
            'delete_item_callback' => ['AERP_Order_Status_Manager', 'delete_status_by_id'],
            'nonce_action_prefix' => 'delete_order_status_',
            'message_transient_key' => 'aerp_order_status_message',
            'hidden_columns_option_key' => 'aerp_order_status_table_hidden_columns',
            'ajax_action' => 'aerp_order_status_filter_statuses',
            'table_wrapper' => '#aerp-order-status-table-wrapper',
        ]);
    }

    protected function column_color($item)
    {
        if (!empty($item->color)) {
            return sprintf(
                '<span class="badge" style="background-color: %s; color: white;">%s</span>',
                esc_attr($item->color),
                esc_html($item->color)
            );
        }
        return '<span class="text-muted">--</span>';
    }
    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];

        if (!empty($this->filters['color'])) {
            $filters[] = 'color = %s';
            $params[] = $this->filters['color'];
        }

        return [$filters, $params];
    }
} 