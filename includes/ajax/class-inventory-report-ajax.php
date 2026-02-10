<?php
if (!defined('ABSPATH')) exit;

class AERP_Inventory_Report_Ajax
{
    public static function init()
    {
        add_action('wp_ajax_aerp_get_stock_timeline', [__CLASS__, 'get_stock_timeline']);
        add_action('wp_ajax_aerp_get_movement_data', [__CLASS__, 'get_movement_data']);
        add_action('wp_ajax_aerp_get_daily_movement_summary', [__CLASS__, 'get_daily_movement_summary']);
        add_action('wp_ajax_aerp_get_low_stock_products', [__CLASS__, 'get_low_stock_products']);
        add_action('wp_ajax_aerp_get_warehouse_summary', [__CLASS__, 'get_warehouse_summary']);
        add_action('wp_ajax_aerp_get_top_moving_products', [__CLASS__, 'get_top_moving_products']);
        add_action('wp_ajax_aerp_get_stock_history', [__CLASS__, 'get_stock_history']);
    }
    
    /**
     * Lấy dữ liệu tồn kho theo thời gian
     */
    public static function get_stock_timeline()
    {
        check_ajax_referer('aerp_inventory_report_nonce', 'nonce');
        
        $warehouse_id = isset($_POST['warehouse_id']) ? absint($_POST['warehouse_id']) : null;
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : null;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        
        $data = AERP_Inventory_Report_Manager::get_stock_timeline_data($warehouse_id, $product_id, $start_date, $end_date);
        
        wp_send_json_success($data);
    }
    
    /**
     * Lấy dữ liệu chuyển động kho
     */
    public static function get_movement_data()
    {
        check_ajax_referer('aerp_inventory_report_nonce', 'nonce');
        
        $warehouse_id = isset($_POST['warehouse_id']) ? absint($_POST['warehouse_id']) : null;
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : null;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : null;
        
        $data = AERP_Inventory_Report_Manager::get_movement_data($warehouse_id, $product_id, $start_date, $end_date, $type);
        
        wp_send_json_success($data);
    }
    
    /**
     * Lấy tổng hợp chuyển động theo ngày
     */
    public static function get_daily_movement_summary()
    {
        check_ajax_referer('aerp_inventory_report_nonce', 'nonce');
        
        $warehouse_id = isset($_POST['warehouse_id']) ? absint($_POST['warehouse_id']) : null;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        
        $data = AERP_Inventory_Report_Manager::get_daily_movement_summary($warehouse_id, $start_date, $end_date);
        
        wp_send_json_success($data);
    }
    
    /**
     * Lấy danh sách sản phẩm tồn kho thấp
     */
    public static function get_low_stock_products()
    {
        check_ajax_referer('aerp_inventory_report_nonce', 'nonce');
        
        $warehouse_id = isset($_POST['warehouse_id']) ? absint($_POST['warehouse_id']) : null;
        $threshold = isset($_POST['threshold']) ? absint($_POST['threshold']) : 10;
        
        $data = AERP_Inventory_Report_Manager::get_low_stock_products($warehouse_id, $threshold);
        
        wp_send_json_success($data);
    }
    
    /**
     * Lấy thống kê tổng quan kho
     */
    public static function get_warehouse_summary()
    {
        check_ajax_referer('aerp_inventory_report_nonce', 'nonce');
        
        $warehouse_id = isset($_POST['warehouse_id']) ? absint($_POST['warehouse_id']) : null;
        
        $data = AERP_Inventory_Report_Manager::get_warehouse_summary($warehouse_id);
        
        wp_send_json_success($data);
    }
    
    /**
     * Lấy top sản phẩm chuyển động nhiều nhất
     */
    public static function get_top_moving_products()
    {
        check_ajax_referer('aerp_inventory_report_nonce', 'nonce');
        
        $warehouse_id = isset($_POST['warehouse_id']) ? absint($_POST['warehouse_id']) : null;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 10;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        
        $data = AERP_Inventory_Report_Manager::get_top_moving_products($warehouse_id, $limit, $start_date, $end_date);
        
        wp_send_json_success($data);
    }

    /**
     * Lấy lịch sử tồn kho theo ngày cho 1 sản phẩm
     */
    public static function get_stock_history()
    {
        check_ajax_referer('aerp_inventory_report_nonce', 'nonce');
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $warehouse_id = isset($_POST['warehouse_id']) ? absint($_POST['warehouse_id']) : null;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        if (!$product_id) {
            wp_send_json_error('Thiếu product_id');
        }
        $data = AERP_Inventory_Report_Manager::get_stock_history($product_id, $warehouse_id, $start_date, $end_date);
        wp_send_json_success($data);
    }
}

// Khởi tạo AJAX handlers
AERP_Inventory_Report_Ajax::init(); 