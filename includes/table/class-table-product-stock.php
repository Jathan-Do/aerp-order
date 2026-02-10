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
                'sku'        => 'Mã SKU',
                'warehouse_id' => 'Kho',
                'quantity' => 'Tồn kho',
                'price'       => 'Giá lẻ',
                'whole_price' => 'Giá sỉ',
                'updated_at' => 'Cập nhật',
            ],
            'sortable_columns' => ['id', 'product_id', 'warehouse_id', 'quantity', 'price', 'whole_price', 'updated_at'],
            'searchable_columns' => ['product_id', 'warehouse_id', 'sku'],
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

    protected function column_sku($item)
    {
        if (isset($item->sku)) {
            return esc_html($item->sku);
        }
        $product = $this->get_product_info($item->product_id);
        return !empty($product['sku']) ? esc_html($product['sku']) : '';
    }

    protected function column_warehouse_id($item)
    {
        return $item->warehouse_id ? esc_html(AERP_Warehouse_Manager::get_warehouse_name($item->warehouse_id)) : '';
    }

    protected function column_price($item)
    {
        $price = isset($item->price) ? floatval($item->price) : 0;
        if (!$price) {
            $product = $this->get_product_info($item->product_id);
            $price = isset($product['price']) ? floatval($product['price']) : 0;
        }
        return number_format($price, 0, ',', '.') . ' đ';
    }

    protected function column_whole_price($item)
    {
        $price = isset($item->whole_price) ? floatval($item->whole_price) : 0;
        if (!$price) {
            $product = $this->get_product_info($item->product_id);
            $price = isset($product['whole_price']) ? floatval($product['whole_price']) : 0;
        }
        return number_format($price, 0, ',', '.') . ' đ';
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

    /**
     * Lấy thông tin sản phẩm kèm cache để tránh query lặp.
     */
    private static $product_cache = [];
    private function get_product_info($product_id)
    {
        $product_id = intval($product_id);
        if ($product_id <= 0) return [];

        if (isset(self::$product_cache[$product_id])) {
            return self::$product_cache[$product_id];
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT sku, price, whole_price FROM {$wpdb->prefix}aerp_products WHERE id = %d",
                $product_id
            ),
            ARRAY_A
        );

        self::$product_cache[$product_id] = $row ?: [];
        return self::$product_cache[$product_id];
    }

    /**
     * Override get_items để join sản phẩm, phục vụ sort theo giá/sku.
     */
    public function get_items()
    {
        global $wpdb;

        $where = [];
        $params = [];

        // Search
        if ($this->search_term && !empty($this->searchable_columns)) {
            $search_conditions = [];
            foreach ($this->searchable_columns as $column) {
                if ($column === 'sku') {
                    $search_conditions[] = "p.sku LIKE %s";
                } elseif ($column === 'product_id') {
                    $search_conditions[] = "p.name LIKE %s";
                } else {
                    $search_conditions[] = "s.$column LIKE %s";
                }
                $params[] = '%' . $wpdb->esc_like($this->search_term) . '%';
            }
            list($extra_search, $extra_params) = $this->get_extra_search_conditions($this->search_term);
            $search_conditions = array_merge($search_conditions, $extra_search);
            $params = array_merge($params, $extra_params);
            $where[] = '(' . implode(' OR ', $search_conditions) . ')';
        }

        // Extra filters
        list($extra_filters, $extra_filter_params) = $this->get_extra_filters();
        $where = array_merge($where, $extra_filters);
        $params = array_merge($params, $extra_filter_params);

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($this->current_page - 1) * $this->per_page;

        // Sort mapping
        $allowed_sort = [
            'id' => 's.id',
            'product_id' => 's.product_id',
            'warehouse_id' => 's.warehouse_id',
            'quantity' => 's.quantity',
            'price' => 'p.price',
            'whole_price' => 'p.whole_price',
            'updated_at' => 's.updated_at',
        ];
        $order_by = isset($allowed_sort[$this->sort_column]) ? $allowed_sort[$this->sort_column] : 's.id';
        $order_dir = strtolower($this->sort_order) === 'asc' ? 'ASC' : 'DESC';

        // Count
        $total_query = "SELECT COUNT(*) FROM {$this->table_name} s $where_clause";
        if (!empty($params)) {
            $total_query = $wpdb->prepare($total_query, $params);
        }
        $this->total_items = (int) $wpdb->get_var($total_query);

        // Data query with join
        $query = "
            SELECT s.*, p.sku, p.price, p.whole_price
            FROM {$this->table_name} s
            LEFT JOIN {$wpdb->prefix}aerp_products p ON p.id = s.product_id
            $where_clause
            ORDER BY $order_by $order_dir
            LIMIT %d OFFSET %d
        ";
        $params2 = array_merge($params, [$this->per_page, $offset]);
        $this->items = $wpdb->get_results($wpdb->prepare($query, $params2));

        return $this->items;
    }
}
