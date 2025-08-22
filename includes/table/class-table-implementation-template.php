<?php
if (!defined('ABSPATH')) exit;
class AERP_Implementation_Template_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_implementation_templates',
            'columns' => [
                'name' => 'Tên template',
                'is_active' => 'Kích hoạt',
                'created_by' => 'Người tạo',
                'created_at' => 'Ngày tạo',
            ],
            'sortable_columns' => ['id', 'name', 'is_active', 'created_at'],
            'searchable_columns' => ['name', 'content'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-implementation-templates'),
            'delete_item_callback' => ['AERP_Implementation_Template_Manager', 'delete'],
            'nonce_action_prefix' => 'delete_implementation_template_',
            'message_transient_key' => 'aerp_implementation_template_message',
            'hidden_columns_option_key' => 'aerp_implementation_template_table_hidden_columns',
            'ajax_action' => 'aerp_implementation_template_filter',
            'table_wrapper' => '#aerp-implementation-template-table-wrapper',
        ]);
    }
    protected function column_is_active($item)
    {
        return $item->is_active ? '<span class="badge bg-success">Bật</span>' : '<span class="badge bg-secondary">Tắt</span>';
    }
    protected function column_created_by($item)
    {
        if (empty($item->created_by)) return '<span class="text-muted">--</span>';
        $emp = function_exists('aerp_get_customer_assigned_name') ? aerp_get_customer_assigned_name((int)$item->created_by) : '';
        return $emp ? esc_html($emp) : '<span class="text-muted">--</span>';
    }
    protected function get_extra_filters()
    {
        $filters = [];
        $params = [];

        if (isset($this->filters['is_active']) && $this->filters['is_active'] !== '' && $this->filters['is_active'] !== null) {
            $filters[] = 'is_active = %d';
            $params[] = (int)$this->filters['is_active'];
        }

        return [$filters, $params];
    }
}


