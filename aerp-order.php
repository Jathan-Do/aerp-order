<?php

/**
 * Plugin Name: AERP Order – Quản lý đơn hàng
 * Description: Module quản lý đơn hàng của hệ thống AERP.
 * Version: 1.0.0
 * Author: Truong Thinh Group
 * Text Domain: aerp-order
 */

if (!defined('ABSPATH')) exit;

// Constants
define('AERP_ORDER_PATH', plugin_dir_path(__FILE__));
define('AERP_ORDER_URL', plugin_dir_url(__FILE__));
define('AERP_ORDER_VERSION', '1.0.0');

add_action('admin_init', function () {
    if (!function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    if (!is_plugin_active('aerp-crm/aerp-crm.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        add_action('admin_notices', function () {
            echo '<div class="error"><p><b>AERP ORDER</b> yêu cầu cài và kích hoạt <b>AERP CRM</b> trước!</p></div>';
        });
    }
});
// Kiểm tra bản Pro
if (!function_exists('aerp_order_is_pro')) {
    function aerp_order_is_pro()
    {
        return function_exists('aerp_is_pro_module') && aerp_is_pro_module('order');
    }
}
// Khởi tạo plugin
function aerp_order_init()
{
    // Load func dùng chung
    require_once AERP_ORDER_PATH . 'includes/functions-common.php';
    require_once AERP_ORDER_PATH . '../aerp-hrm/includes/functions-common.php';
    require_once AERP_ORDER_PATH . '../aerp-crm/includes/functions-common.php';
    // Table 
    require_once AERP_ORDER_PATH . '../aerp-hrm/frontend/includes/table/class-frontend-table.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-order.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-order-status-log.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-product.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-inventory-log.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-unit.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-category.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-order-status.php';   
    require_once AERP_ORDER_PATH . 'includes/table/class-table-warehouse.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-product-stock.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-inventory-transfer.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-supplier.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-low-stock.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-device.php';
    require_once AERP_ORDER_PATH . 'includes/table/class-table-implementation-template.php';
    // Load các class cần thiết manager
    $includes = [
        'class-frontend-order-manager.php',
        'class-product-manager.php',
        'class-inventory-log-manager.php',
        'class-inventory-report-manager.php',
        'class-unit-manager.php',
        'class-category-manager.php',
        'class-order-status-manager.php',
        'class-warehouse-manager.php',
        'class-product-stock-manager.php',
        'class-inventory-transfer-manager.php',
        'class-supplier-manager.php',
        'class-device-manager.php',
        'class-implementation-template-manager.php',
    ];
    foreach ($includes as $file) {
        require_once AERP_ORDER_PATH . 'includes/managers/' . $file;
    }

    // Xử lý form và logic
    $managers = [
        'AERP_Frontend_Order_Manager',
        'AERP_Product_Manager',
        'AERP_Inventory_Log_Manager',
        'AERP_Inventory_Report_Manager',
        'AERP_Unit_Manager',
        'AERP_Category_Manager',
        'AERP_Order_Status_Manager',
        'AERP_Warehouse_Manager',
        'AERP_Product_Stock_Manager',
        'AERP_Inventory_Transfer_Manager',
        'AERP_Supplier_Manager',
        'AERP_Device_Manager',
        'AERP_Implementation_Template_Manager',
        ];
    foreach ($managers as $manager) {
        if (method_exists($manager, 'handle_submit')) {
            add_action('init', [$manager, 'handle_submit']);
        }
        if (method_exists($manager, 'handle_form_submit')) {
            add_action('init', [$manager, 'handle_form_submit']);
        }
        if (method_exists($manager, 'handle_delete')) {
            add_action('init', [$manager, 'handle_delete']);
        }
        if (method_exists($manager, 'handle_confirm_submit')) {
            add_action('init', [$manager, 'handle_confirm_submit']);
        }
    }
}
add_action('plugins_loaded', 'aerp_order_init');

// Đăng ký database khi kích hoạt
register_activation_hook(__FILE__, function () {
    require_once AERP_ORDER_PATH . 'install-schema.php';
    aerp_order_install_schema();
    flush_rewrite_rules();
});

// Xóa database khi deactivate
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
// === REWRITE RULES FOR FRONTEND DASHBOARD ===
require_once AERP_ORDER_PATH . 'includes/page-rewrite-rules.php';
// Enqueue script cho frontend nếu cần
add_action('wp_enqueue_scripts', function () {
    // $request_uri = $_SERVER['REQUEST_URI'];
    // if (preg_match('/\/aerp-order-orders/i', $request_uri)) {
        wp_enqueue_script('aerp-order-form', AERP_ORDER_URL . 'assets/js/order-form.js', ['jquery'], AERP_ORDER_VERSION, true);
        wp_localize_script('aerp-order-form', 'aerp_order_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            '_wpnonce_delete_attachment' => wp_create_nonce('aerp_delete_order_attachment_nonce'),
        ));
        
        // Enqueue order actions script
        wp_enqueue_script('aerp-order-actions', AERP_ORDER_URL . 'assets/js/order-actions.js', ['jquery'], AERP_ORDER_VERSION, true);
        wp_localize_script('aerp-order-actions', 'aerp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'reject_order_nonce' => wp_create_nonce('aerp_reject_order_nonce'),
            'complete_order_nonce' => wp_create_nonce('aerp_complete_order_nonce'),
            'mark_paid_nonce' => wp_create_nonce('aerp_mark_paid_nonce'),
            'cancel_order_nonce' => wp_create_nonce('aerp_cancel_order_nonce'),
        ));
        
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
    // }
}, 20);

// Ajax hooks nếu có
require_once AERP_ORDER_PATH . 'includes/ajax/ajax-hook.php';
require_once AERP_ORDER_PATH . 'includes/ajax/class-inventory-report-ajax.php';
