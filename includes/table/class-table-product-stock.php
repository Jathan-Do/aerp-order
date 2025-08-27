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
            'sortable_columns' => ['id', 'product_id', 'warehouse_id'],
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
        
        // Filter theo quyền xem phiếu nhập/xuất kho
        if (!empty($this->filters['manager_user_id'])) {
            $employee_id = (int)$this->filters['manager_user_id'];
            $user_id = get_current_user_id();
            // Nếu là admin thì không filter gì cả, được thấy hết
            if (function_exists('aerp_user_has_role') && aerp_user_has_role($user_id, 'admin')) {
                // Admin: không filter, thấy tất cả
            } else {
                if ($employee_id) {
                    // Lấy work_location_id của user hiện tại
                    $work_location_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT work_location_id FROM {$wpdb->prefix}aerp_hrm_employees WHERE id = %d",
                        $employee_id
                    ));

                    // 1. Lấy tất cả kho mà user hiện tại quản lý (không phụ thuộc chi nhánh)
                    $user_warehouse_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT warehouse_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE user_id = %d",
                        $employee_id
                    ));
                    $user_warehouse_ids = array_map('intval', $user_warehouse_ids);

                    // 2. Lấy tất cả kho thuộc cùng chi nhánh với user hiện tại
                    $branch_warehouse_ids = [];
                    if ($work_location_id) {
                        $branch_warehouse_ids = $wpdb->get_col($wpdb->prepare(
                            "SELECT id FROM {$wpdb->prefix}aerp_warehouses WHERE work_location_id = %d",
                            $work_location_id
                        ));
                        $branch_warehouse_ids = array_map('intval', $branch_warehouse_ids);
                    }

                    // 3. Gộp tất cả warehouse IDs
                    $all_warehouse_ids = array_unique(array_merge($user_warehouse_ids, $branch_warehouse_ids));

                    if (!empty($all_warehouse_ids)) {
                        $placeholders = implode(',', array_fill(0, count($all_warehouse_ids), '%d'));
                        $filters[] = "warehouse_id IN ($placeholders)";
                        $params = array_merge($params, $all_warehouse_ids);
                    } else {
                        // Nếu không có kho nào thì trả về điều kiện không có kết quả
                        $filters[] = "0=1";
                    }
                } else {
                    // Nếu không tìm thấy employee_id thì không có quyền xem gì cả
                    $filters[] = "0=1";
                }
            }
        }
        
        if (!empty($this->filters['warehouse_type'])) {
            $filters[] = "warehouse_id IN (SELECT id FROM {$wpdb->prefix}aerp_warehouses WHERE warehouse_type = %s)";
            $params[] = $this->filters['warehouse_type'];
        }
        
        return [$filters, $params];
    }
}
