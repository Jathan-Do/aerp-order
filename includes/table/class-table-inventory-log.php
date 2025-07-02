<?php
if (!defined('ABSPATH')) exit;

class AERP_Inventory_Log_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_inventory_logs',
            'columns' => [
                'id' => 'ID',
                'product_id' => 'Sản phẩm',
                'type' => 'Loại phiếu',
                'quantity' => 'Số lượng',
                'note' => 'Ghi chú',
                'created_by' => 'Người tạo',
                'created_at' => 'Ngày tạo',
            ],
            'sortable_columns' => ['id', 'product_id', 'note'],
            'searchable_columns' => ['product_id', 'type', 'quantity', 'created_by', 'created_at'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [],
            'bulk_actions' => ['delete'],
            'delete_item_callback' => ['AERP_Inventory_Log_Manager', 'delete_log_by_id'],
            'nonce_action_prefix' => 'delete_inventory_log_',
            'base_url' => home_url('/aerp-inventory-logs'),
            'message_transient_key' => 'aerp_inventory_log_message',
            'hidden_columns_option_key' => 'aerp_inventory_log_table_hidden_columns',
            'ajax_action' => 'aerp_inventory_log_filter_inventory_logs',
            'table_wrapper' => '#aerp-inventory-log-table-wrapper',
        ]);
    }
    protected function column_product_id($item)
    {
        $product = function_exists('aerp_get_product') ? aerp_get_product($item->product_id) : null;
        return $product ? esc_html($product->name) : 'ID: ' . intval($item->product_id);
    }
    protected function column_type($item)
    {
        return $item->type === 'import' ? '<span class="badge bg-success">Nhập kho</span>' : ($item->type === 'export' ? '<span class="badge bg-danger">Xuất kho</span>' : '<span class="badge bg-warning">Kiểm kho</span>');
    }
    protected function column_created_by($item)
    {
        $user = get_userdata($item->created_by);
        return $user ? esc_html($user->display_name) : 'ID: ' . intval($item->created_by);
    }
    protected function get_extra_search_conditions($search_term)
    {
        global $wpdb;
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        // Join sang bảng trạng thái để search theo tên
        $extra = [];
        $params = [];
        $extra[] = "product_id IN (SELECT id FROM {$wpdb->prefix}aerp_products WHERE name LIKE %s)";
        $params[] = $search_term;
        $extra[] = "type LIKE %s";
        $params[] = $search_term;
        return [$extra, $params];
    }
}
