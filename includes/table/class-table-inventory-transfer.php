<?php
if (!defined('ABSPATH')) exit;

class AERP_Inventory_Transfer_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_inventory_transfers',
            'columns' => [
                'from_warehouse_id' => 'Kho xuất',
                'to_warehouse_id'   => 'Kho nhập',
                'products'          => 'Sản phẩm',
                'created_by'        => 'Người tạo',
                'note'              => 'Ghi chú',
                'created_at'        => 'Ngày tạo',
            ],
            'searchable_columns' => [
                'from_warehouse_id',
                'to_warehouse_id',
                'note',
            ],
            'sortable_columns' => ['id', 'from_warehouse_id', 'to_warehouse_id', 'created_at'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [],
            'bulk_actions' => ['delete'],
            'base_url' => home_url('/aerp-inventory-transfers'),
            'delete_item_callback' => ['AERP_Inventory_Transfer_Manager', 'delete_by_id'],
            'nonce_action_prefix' => 'delete_inventory_transfer_',
            'message_transient_key' => 'aerp_inventory_transfer_message',
            'hidden_columns_option_key' => 'aerp_inventory_transfer_table_hidden_columns',
            'ajax_action' => 'aerp_inventory_transfer_filter_inventory_transfers',
            'table_wrapper' => '#aerp-inventory-transfer-table-wrapper',
        ]);
    }
    protected function column_from_warehouse_id($item)
    {
        return AERP_Warehouse_Manager::get_warehouse_name($item->from_warehouse_id);
    }
    protected function column_to_warehouse_id($item)
    {
        return AERP_Warehouse_Manager::get_warehouse_name($item->to_warehouse_id);
    }
    protected function column_created_by($item)
    {
        global $wpdb;
        $user_id = intval($item->created_by);
        // Lấy tên nhân viên từ bảng employee dựa vào user_id
        $employee = $wpdb->get_row($wpdb->prepare(
            "SELECT full_name FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
            $user_id
        ));
        if ($employee && !empty($employee->full_name)) {
            return esc_html($employee->full_name);
        }
        return '-- ';
    }
    protected function column_products($item)
    {
        global $wpdb;
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, p.name 
                 FROM {$wpdb->prefix}aerp_inventory_transfer_items t
                 LEFT JOIN {$wpdb->prefix}aerp_products p ON t.product_id = p.id
                 WHERE t.transfer_id = %d",
                $item->id
            )
        );
        if (!$items) return '—';

        $lines = [];
        foreach ($items as $row) {
            $lines[] = esc_html($row->name) . ' (SL: ' . intval($row->quantity) . ')';
        }
        return implode('<br>', $lines);
    }

    protected function get_extra_search_conditions($search_term)
    {
        global $wpdb;
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';

        $extra = [];
        $params = [];

        // Search tên kho xuất
        $extra[] = "from_warehouse_id IN (SELECT id FROM {$wpdb->prefix}aerp_warehouses WHERE name LIKE %s)";
        $params[] = $search_term;

        // Search tên kho nhập
        $extra[] = "to_warehouse_id IN (SELECT id FROM {$wpdb->prefix}aerp_warehouses WHERE name LIKE %s)";
        $params[] = $search_term;

        // ✅ Search sản phẩm
        $extra[] = "{$this->primary_key} IN (
            SELECT transfer_id 
            FROM {$wpdb->prefix}aerp_inventory_transfer_items t 
            LEFT JOIN {$wpdb->prefix}aerp_products p ON t.product_id = p.id
            WHERE p.name LIKE %s
        )";
        $params[] = $search_term;

        return [$extra, $params];
    }
    protected function get_extra_filters()
    {
        global $wpdb;
        $filters = [];
        $params = [];
        if (!empty($this->filters['manager_user_id'])) {
            $filters[] = "(from_warehouse_id IN (SELECT warehouse_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE user_id = %d)
                          OR to_warehouse_id IN (SELECT warehouse_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE user_id = %d))";
            $params[] = (int)$this->filters['manager_user_id'];
            $params[] = (int)$this->filters['manager_user_id'];
        }
        return [$filters, $params];
    }
}
