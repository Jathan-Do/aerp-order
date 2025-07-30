<?php
if (!defined('ABSPATH')) exit;
class AERP_Product_Stock_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_product_stocks',
            'columns' => [
                'product_id' => 'Tên sản phẩm',
                'warehouse_id' => 'Kho',
                'quantity' => 'Tồn kho',
                'updated_at' => 'Cập nhật',
            ],
            'sortable_columns' => ['product_id', 'warehouse_id'],
            'searchable_columns' => ['product_id', 'warehouse_id'],
            'primary_key' => 'id',
            'per_page' => 20,
            'actions' => [],
            'bulk_actions' => ['delete'],
            'delete_item_callback' => ['AERP_Product_Stock_Manager', 'delete_product_stock_by_id'],
            'nonce_action_prefix' => 'delete_product_stock_',
            'message_transient_key' => 'aerp_product_stock_message',
            'base_url' => home_url('/aerp-warehouses/?action=stock'),
            'hidden_columns_option_key' => 'aerp_product_stock_table_hidden_columns',
            'ajax_action' => 'aerp_product_stock_filter',
            'table_wrapper' => '#aerp-product-stock-table-wrapper',
        ]);
    }
    protected function get_extra_search_conditions($search_term)
    {
        global $wpdb;
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        $extra = [];
        $params = [];

        // Search theo tên sản phẩm hoặc SKU
        $extra[] = "(
            product_id IN (SELECT id FROM {$wpdb->prefix}aerp_products WHERE name LIKE %s OR sku LIKE %s)
            OR
            warehouse_id IN (SELECT id FROM {$wpdb->prefix}aerp_warehouses WHERE name LIKE %s)
        )";
        $params[] = $search_term; // product name
        $params[] = $search_term; // product sku
        $params[] = $search_term; // warehouse name

        return [$extra, $params];
    }
    protected function column_product_id($item)
    {
        return $item->product_id ? esc_html(AERP_Product_Manager::get_product_name($item->product_id)) : '';
    }
    protected function column_warehouse_id($item)
    {
        return $item->warehouse_id ? esc_html(AERP_Warehouse_Manager::get_warehouse_name($item->warehouse_id)) : '';
    }
    protected function get_extra_filters()
    {
        global $wpdb;
        $filters = [];
        $params = [];
        if (!empty($this->filters['manager_user_id'])) {
            $filters[] = "warehouse_id IN (SELECT warehouse_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE user_id = %d)";
            $params[] = (int)$this->filters['manager_user_id'];
        }
        return [$filters, $params];
    }
}
