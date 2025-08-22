<?php
if (!defined('ABSPATH')) exit;
class AERP_Warehouse_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        global $wpdb;
        parent::__construct([
            'table_name' => $wpdb->prefix . 'aerp_warehouses',
            'columns' => [
                'name' => 'Tên kho',
                'work_location_id' => 'Vị trí',
            ],
            'sortable_columns' => ['id', 'name', 'work_location_id'],
            'searchable_columns' => ['name', 'work_location_id'],
            'primary_key' => 'id',
            'per_page' => 20,
            'actions' => ['edit', 'delete'],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-warehouses'),
            'delete_item_callback' => ['AERP_Warehouse_Manager', 'delete_by_id'],
            'nonce_action_prefix' => 'delete_warehouse_',
            'message_transient_key' => 'aerp_warehouse_message',
            'hidden_columns_option_key' => 'aerp_warehouse_table_hidden_columns',
            'ajax_action' => 'aerp_warehouse_filter_warehouses',
            'table_wrapper' => '#aerp-warehouse-table-wrapper',
        ]);
    }
    protected function get_extra_filters()
    {
        global $wpdb;
        $filters = [];
        $params = [];
        if (!empty($this->filters['manager_user_id'])) {
            $filters[] = "id IN (SELECT warehouse_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE user_id = %d)";
            $params[] = (int)$this->filters['manager_user_id'];
        }
        return [$filters, $params];
    }
    protected function column_work_location_id($item)
    {
        $location = AERP_Work_Location_Manager::get_by_id($item->work_location_id);
        return $location ? esc_html($location->name) : '--';
    }
}
