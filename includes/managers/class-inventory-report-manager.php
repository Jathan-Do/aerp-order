<?php
if (!defined('ABSPATH')) exit;

class AERP_Inventory_Report_Manager
{
    /**
     * Lấy danh sách ID kho mà user hiện tại quản lý
     */
    public static function get_user_warehouse_ids($user_id = null) {
        global $wpdb;
        if (!$user_id) $user_id = get_current_user_id();
        
        $warehouse_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT warehouse_id FROM {$wpdb->prefix}aerp_warehouse_managers WHERE user_id = %d",
            $user_id
        ));
        
        return array_map('intval', $warehouse_ids);
    }
    /**
     * Lấy danh sách user quản lý kho
     */
    public static function get_warehouse_managers($warehouse_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT wm.user_id, u.display_name, u.user_email
             FROM {$wpdb->prefix}aerp_warehouse_managers wm
             JOIN {$wpdb->prefix}users u ON wm.user_id = u.ID
             WHERE wm.warehouse_id = %d
             ORDER BY u.display_name",
            $warehouse_id
        ));
    }

    /**
     * Lấy dữ liệu tồn kho theo thời gian
     */
    public static function get_stock_timeline_data($warehouse_id = null, $product_id = null, $start_date = null, $end_date = null)
    {
        global $wpdb;
        
        $where_conditions = [];
        $where_values = [];
        
        // Lọc theo kho user quản lý
        $user_warehouse_ids = self::get_user_warehouse_ids();
        if (!empty($user_warehouse_ids)) {
            $warehouse_ids_str = implode(',', $user_warehouse_ids);
            $where_conditions[] = "ps.warehouse_id IN ($warehouse_ids_str)";
        } else {
            // User không quản lý kho nào
            return [];
        }
        
        if ($warehouse_id) {
            // Kiểm tra warehouse_id có trong danh sách kho user quản lý không
            if (!in_array($warehouse_id, $user_warehouse_ids)) {
                return [];
            }
            $where_conditions[] = "ps.warehouse_id = %d";
            $where_values[] = $warehouse_id;
        }
        
        if ($product_id) {
            $where_conditions[] = "ps.product_id = %d";
            $where_values[] = $product_id;
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        $query = $wpdb->prepare(
            "SELECT 
                ps.product_id,
                ps.warehouse_id,
                p.name as product_name,
                p.sku,
                w.name as warehouse_name,
                ps.quantity as current_stock,
                ps.updated_at
            FROM {$wpdb->prefix}aerp_product_stocks ps
            JOIN {$wpdb->prefix}aerp_products p ON ps.product_id = p.id
            JOIN {$wpdb->prefix}aerp_warehouses w ON ps.warehouse_id = w.id
            $where_clause
            ORDER BY ps.updated_at DESC",
            ...$where_values
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Lấy dữ liệu chuyển động kho theo thời gian
     */
    public static function get_movement_data($warehouse_id = null, $product_id = null, $start_date = null, $end_date = null, $type = null)
    {
        global $wpdb;
        
        $where_conditions = ["il.status = 'confirmed'"];
        $where_values = [];
        
        // Lọc theo kho user quản lý
        $user_warehouse_ids = self::get_user_warehouse_ids();
        if (!empty($user_warehouse_ids)) {
            $warehouse_ids_str = implode(',', $user_warehouse_ids);
            $where_conditions[] = "il.warehouse_id IN ($warehouse_ids_str)";
        } else {
            // User không quản lý kho nào
            return [];
        }
        
        if ($warehouse_id) {
            // Kiểm tra warehouse_id có trong danh sách kho user quản lý không
            if (!in_array($warehouse_id, $user_warehouse_ids)) {
                return [];
            }
            $where_conditions[] = "il.warehouse_id = %d";
            $where_values[] = $warehouse_id;
        }
        
        if ($product_id) {
            $where_conditions[] = "il.product_id = %d";
            $where_values[] = $product_id;
        }
        
        if ($start_date) {
            $where_conditions[] = "DATE(il.created_at) >= %s";
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where_conditions[] = "DATE(il.created_at) <= %s";
            $where_values[] = $end_date;
        }
        
        if ($type) {
            $where_conditions[] = "il.type = %s";
            $where_values[] = $type;
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        $query = $wpdb->prepare(
            "SELECT 
                il.id,
                il.product_id,
                il.warehouse_id,
                il.type,
                il.quantity,
                il.note,
                il.created_at,
                p.name as product_name,
                p.sku,
                w.name as warehouse_name,
                u.display_name as created_by_name
            FROM {$wpdb->prefix}aerp_inventory_logs il
            JOIN {$wpdb->prefix}aerp_products p ON il.product_id = p.id
            JOIN {$wpdb->prefix}aerp_warehouses w ON il.warehouse_id = w.id
            LEFT JOIN {$wpdb->prefix}users u ON il.created_by = u.ID
            $where_clause
            ORDER BY il.created_at DESC",
            ...$where_values
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Lấy dữ liệu tổng hợp chuyển động theo ngày
     */
    public static function get_daily_movement_summary($warehouse_id = null, $start_date = null, $end_date = null, $product_id = null)
    {
        global $wpdb;
        
        $where_conditions = ["il.status = 'confirmed'"];
        $where_values = [];
        
        // Lọc theo kho user quản lý
        $user_warehouse_ids = self::get_user_warehouse_ids();
        if (!empty($user_warehouse_ids)) {
            $warehouse_ids_str = implode(',', $user_warehouse_ids);
            $where_conditions[] = "il.warehouse_id IN ($warehouse_ids_str)";
        } else {
            // User không quản lý kho nào
            return [];
        }
        
        if ($warehouse_id) {
            // Kiểm tra warehouse_id có trong danh sách kho user quản lý không
            if (!in_array($warehouse_id, $user_warehouse_ids)) {
                return [];
            }
            $where_conditions[] = "il.warehouse_id = %d";
            $where_values[] = $warehouse_id;
        }
        
        if ($product_id) {
            $where_conditions[] = "il.product_id = %d";
            $where_values[] = $product_id;
        }
        
        if ($start_date) {
            $where_conditions[] = "DATE(il.created_at) >= %s";
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where_conditions[] = "DATE(il.created_at) <= %s";
            $where_values[] = $end_date;
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        $query = $wpdb->prepare(
            "SELECT 
                DATE(il.created_at) as date,
                il.type,
                SUM(CASE WHEN il.type = 'import' THEN il.quantity ELSE 0 END) as total_import,
                SUM(CASE WHEN il.type = 'export' THEN il.quantity ELSE 0 END) as total_export,
                SUM(CASE WHEN il.type = 'stocktake' THEN il.quantity ELSE 0 END) as total_adjustment,
                COUNT(*) as transaction_count
            FROM {$wpdb->prefix}aerp_inventory_logs il
            $where_clause
            GROUP BY DATE(il.created_at), il.type
            ORDER BY date DESC, il.type",
            ...$where_values
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Lấy danh sách sản phẩm có tồn kho thấp
     */
    public static function get_low_stock_products($warehouse_id = null, $threshold = 10)
    {
        global $wpdb;
        
        $where_conditions = ["ps.quantity <= %d"];
        $where_values = [$threshold];
        
        // Lọc theo kho user quản lý
        $user_warehouse_ids = self::get_user_warehouse_ids();
        if (!empty($user_warehouse_ids)) {
            $warehouse_ids_str = implode(',', $user_warehouse_ids);
            $where_conditions[] = "ps.warehouse_id IN ($warehouse_ids_str)";
        } else {
            // User không quản lý kho nào
            return [];
        }
        
        if ($warehouse_id) {
            // Kiểm tra warehouse_id có trong danh sách kho user quản lý không
            if (!in_array($warehouse_id, $user_warehouse_ids)) {
                return [];
            }
            $where_conditions[] = "ps.warehouse_id = %d";
            $where_values[] = $warehouse_id;
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        $query = $wpdb->prepare(
            "SELECT 
                ps.product_id,
                ps.warehouse_id,
                ps.quantity,
                ps.updated_at,
                p.name as product_name,
                p.sku,
                w.name as warehouse_name
            FROM {$wpdb->prefix}aerp_product_stocks ps
            JOIN {$wpdb->prefix}aerp_products p ON ps.product_id = p.id
            JOIN {$wpdb->prefix}aerp_warehouses w ON ps.warehouse_id = w.id
            $where_clause
            ORDER BY ps.quantity ASC, p.name ASC",
            ...$where_values
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Lấy thống kê tổng quan kho
     */
    public static function get_warehouse_summary($warehouse_id = null, $threshold = 10)
    {
        global $wpdb;
        $where_conditions = [];
        $where_values = [];
        // Lọc theo kho user quản lý
        $user_warehouse_ids = self::get_user_warehouse_ids();
        if (!empty($user_warehouse_ids)) {
            $warehouse_ids_str = implode(',', $user_warehouse_ids);
            $where_conditions[] = "ps.warehouse_id IN ($warehouse_ids_str)";
        } else {
            // User không quản lý kho nào
            return (object)[
                'total_products' => 0,
                'total_stock' => 0,
                'out_of_stock' => 0,
                'low_stock' => 0,
                'avg_stock' => 0
            ];
        }
        if ($warehouse_id) {
            // Kiểm tra warehouse_id có trong danh sách kho user quản lý không
            if (!in_array($warehouse_id, $user_warehouse_ids)) {
                return (object)[
                    'total_products' => 0,
                    'total_stock' => 0,
                    'out_of_stock' => 0,
                    'low_stock' => 0,
                    'avg_stock' => 0
                ];
            }
            $where_conditions[] = "ps.warehouse_id = %d";
            $where_values[] = $warehouse_id;
        }
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        $query = $wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT ps.product_id) as total_products,
                SUM(ps.quantity) as total_stock,
                COUNT(CASE WHEN ps.quantity = 0 THEN 1 END) as out_of_stock,
                COUNT(CASE WHEN ps.quantity <= %d THEN 1 END) as low_stock,
                AVG(ps.quantity) as avg_stock
            FROM {$wpdb->prefix}aerp_product_stocks ps
            $where_clause",
            $threshold, ...$where_values
        );
        return $wpdb->get_row($query);
    }
    
    /**
     * Lấy top sản phẩm có chuyển động nhiều nhất
     */
    public static function get_top_moving_products($warehouse_id = null, $limit = 10, $start_date = null, $end_date = null, $product_id = null)
    {
        global $wpdb;
        
        $where_conditions = ["il.status = 'confirmed'"];
        $where_values = [];
        
        // Lọc theo kho user quản lý
        $user_warehouse_ids = self::get_user_warehouse_ids();
        if (!empty($user_warehouse_ids)) {
            $warehouse_ids_str = implode(',', $user_warehouse_ids);
            $where_conditions[] = "il.warehouse_id IN ($warehouse_ids_str)";
        } else {
            // User không quản lý kho nào
            return [];
        }
        
        if ($warehouse_id) {
            // Kiểm tra warehouse_id có trong danh sách kho user quản lý không
            if (!in_array($warehouse_id, $user_warehouse_ids)) {
                return [];
            }
            $where_conditions[] = "il.warehouse_id = %d";
            $where_values[] = $warehouse_id;
        }
        
        if ($product_id) {
            $where_conditions[] = "il.product_id = %d";
            $where_values[] = $product_id;
        }
        
        if ($start_date) {
            $where_conditions[] = "DATE(il.created_at) >= %s";
            $where_values[] = $start_date;
        }
        
        if ($end_date) {
            $where_conditions[] = "DATE(il.created_at) <= %s";
            $where_values[] = $end_date;
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        $query = $wpdb->prepare(
            "SELECT 
                il.product_id,
                p.name as product_name,
                p.sku,
                COUNT(*) as transaction_count,
                SUM(CASE WHEN il.type = 'import' THEN il.quantity ELSE 0 END) as total_import,
                SUM(CASE WHEN il.type = 'export' THEN il.quantity ELSE 0 END) as total_export,
                ABS(SUM(CASE WHEN il.type = 'import' THEN il.quantity ELSE -il.quantity END)) as net_movement
            FROM {$wpdb->prefix}aerp_inventory_logs il
            JOIN {$wpdb->prefix}aerp_products p ON il.product_id = p.id
            $where_clause
            GROUP BY il.product_id, p.name, p.sku
            ORDER BY net_movement DESC
            LIMIT %d",
            ...array_merge($where_values, [$limit])
        );
        
        return $wpdb->get_results($query);
    }

    /**
     * Lấy lịch sử tồn kho theo ngày cho 1 sản phẩm (và kho nếu có)
     */
    public static function get_stock_history($product_id, $warehouse_id = null, $start_date = null, $end_date = null) {
        global $wpdb;
        
        // Kiểm tra quyền user với kho
        $user_warehouse_ids = self::get_user_warehouse_ids();
        if (!empty($user_warehouse_ids)) {
            if ($warehouse_id && !in_array($warehouse_id, $user_warehouse_ids)) {
                return [];
            }
        } else {
            return [];
        }
        
        // Lấy tồn kho đầu kỳ
        $where = ["product_id = %d"];
        $params = [$product_id];
        if ($warehouse_id) {
            $where[] = "warehouse_id = %d";
            $params[] = $warehouse_id;
        } else {
            // Nếu không chọn kho cụ thể, chỉ lấy dữ liệu của các kho user quản lý
            $warehouse_ids_str = implode(',', $user_warehouse_ids);
            $where[] = "warehouse_id IN ($warehouse_ids_str)";
        }
        if ($start_date) {
            $where[] = "DATE(created_at) < %s";
            $params[] = $start_date;
        }
        $where_str = implode(" AND ", $where);
        $sql = $wpdb->prepare(
            "SELECT SUM(CASE WHEN type='import' THEN quantity WHEN type='export' THEN -quantity WHEN type='stocktake' THEN quantity ELSE 0 END) as qty
             FROM {$wpdb->prefix}aerp_inventory_logs
             WHERE $where_str AND status='confirmed'",
            ...$params
        );
        $start_qty = (int)$wpdb->get_var($sql);

        // Lấy biến động từng ngày
        $where = ["product_id = %d"];
        $params = [$product_id];
        if ($warehouse_id) {
            $where[] = "warehouse_id = %d";
            $params[] = $warehouse_id;
        } else {
            // Nếu không chọn kho cụ thể, chỉ lấy dữ liệu của các kho user quản lý
            $warehouse_ids_str = implode(',', $user_warehouse_ids);
            $where[] = "warehouse_id IN ($warehouse_ids_str)";
        }
        if ($start_date) {
            $where[] = "DATE(created_at) >= %s";
            $params[] = $start_date;
        }
        if ($end_date) {
            $where[] = "DATE(created_at) <= %s";
            $params[] = $end_date;
        }
        $where_str = implode(" AND ", $where);
        $sql = $wpdb->prepare(
            "SELECT DATE(created_at) as date, 
                    SUM(CASE WHEN type='import' THEN quantity WHEN type='export' THEN -quantity WHEN type='stocktake' THEN quantity ELSE 0 END) as qty
             FROM {$wpdb->prefix}aerp_inventory_logs
             WHERE $where_str AND status='confirmed'
             GROUP BY DATE(created_at)
             ORDER BY DATE(created_at)",
            ...$params
        );
        $rows = $wpdb->get_results($sql);

        // Tính tồn kho lũy kế từng ngày
        $result = [];
        $current_qty = $start_qty;
        foreach ($rows as $row) {
            $current_qty += (int)$row->qty;
            $result[] = [
                'date' => $row->date,
                'stock' => $current_qty,
            ];
        }
        return $result;
    }
} 