<?php
if (!defined('ABSPATH')) exit;

class AERP_Low_Stock_Table extends AERP_Frontend_Table
{
    public function __construct()
    {
        parent::__construct([
            'table_name' => $GLOBALS['wpdb']->prefix . 'aerp_product_stocks',
            'columns' => [
                'product_id' => 'Sản phẩm',
                'sku' => 'SKU',
                'warehouse_id' => 'Kho',
                'quantity' => 'Tồn kho',
                'updated_at' => 'Cập nhật',
                'threshold' => 'Ngưỡng cảnh báo',
                'status' => 'Trạng thái',
                'action' => 'Hành động',
            ],
            'sortable_columns' => ['product_id', 'warehouse_id', 'quantity', 'updated_at'],
            'searchable_columns' => ['product_id'],
            'primary_key' => 'id',
            'per_page' => 10,
            'actions' => [],
            'bulk_actions' => [],
            'hidden_columns_option_key' => 'aerp_low_stock_table_hidden_columns',
            'base_url' => home_url('/aerp-low-stock-alert'),
            'ajax_action' => 'aerp_low_stock_filter_table',
            'table_wrapper' => '#aerp-low-stock-table-wrapper',
        ]);
    }
    protected function column_product_id($item)
    {
        $product = function_exists('aerp_get_product') ? aerp_get_product($item->product_id) : null;
        return $product ? esc_html($product->name) : 'ID: ' . intval($item->product_id);
    }
    protected function column_sku($item)
    {
        $product = function_exists('aerp_get_product') ? aerp_get_product($item->product_id) : null;
        return $product ? esc_html($product->sku) : '';
    }
    protected function column_warehouse_id($item)
    {
        $warehouse = aerp_get_warehouse($item->warehouse_id);
        return $warehouse ? esc_html($warehouse->name) : '--';
    }
    protected function column_quantity($item)
    {
        $threshold = $this->filters['threshold'] ?? get_option('aerp_low_stock_threshold', 10);
        $class = ($item->quantity == 0) ? 'bg-danger' : 'bg-warning';
        return '<span class="badge ' . $class . '">' . number_format($item->quantity) . '</span>';
    }
    protected function column_threshold($item)
    {
        $threshold = $this->filters['threshold'] ?? get_option('aerp_low_stock_threshold', 10);
        return number_format($threshold);
    }
    protected function column_updated_at($item)
    {
        return date('d/m/Y H:i', strtotime($item->updated_at));
    }
    protected function column_status($item)
    {
        if ($item->quantity == 0) {
            return '<span class="badge bg-danger">Hết hàng</span>';
        } else {
            return '<span class="badge bg-warning">Tồn kho thấp</span>';
        }
    }
    protected function column_action($item)
    {
        $import_url = home_url('/aerp-inventory-logs/?action=add&type=import');
        $product_url = home_url('/aerp-products?action=edit&id=' . $item->product_id);
        return '<div class="btn-group btn-group-sm" role="group">'
            . '<a href="' . esc_url($import_url) . '" class="btn btn-success" title="Tạo phiếu nhập kho"><i class="fas fa-plus"></i></a>'
            . '<a href="' . esc_url($product_url) . '" class="btn btn-info" title="Xem chi tiết sản phẩm"><i class="fas fa-eye"></i></a>'
            . '</div>';
    }
    protected function get_extra_filters()
    {
        global $wpdb;
        $filters = [];
        $params = [];
        $threshold = $this->filters['threshold'] ?? get_option('aerp_low_stock_threshold', 10);
        $filters[] = 'quantity <= %d';
        $params[] = $threshold;
        if (!empty($this->filters['warehouse_id'])) {
            $filters[] = 'warehouse_id = %d';
            $params[] = $this->filters['warehouse_id'];
        }
        if (!empty($this->filters['product_id'])) {
            $filters[] = 'product_id = %d';
            $params[] = $this->filters['product_id'];
        }
        if (!empty($this->filters['manager_user_id'])) {
            $filters[] = "warehouse_id IN (SELECT warehouse_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE user_id = %d)";
            $params[] = (int)$this->filters['manager_user_id'];
        }
        return [$filters, $params];
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
} 