<?php

/**
 * Plugin Name: AERP CRM – Quản lý khách hàng
 * Description: Module quản lý khách hàng và bán hàng của hệ thống AERP.
 * Version: 1.0.0
 * Author: Truong Thinh Group
 * Text Domain: aerp-crm
 */

if (!defined('ABSPATH')) exit;

// Constants
define('AERP_CRM_PATH', plugin_dir_path(__FILE__));
define('AERP_CRM_URL', plugin_dir_url(__FILE__));
define('AERP_CRM_VERSION', '1.0.0');

add_action('admin_init', function() {
    if (!function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    if (!is_plugin_active('aerp-hrm/aerp-hrm.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        add_action('admin_notices', function() {
            echo '<div class="error"><p><b>AERP CRM</b> yêu cầu cài và kích hoạt <b>AERP HRM</b> trước!</p></div>';
        });
    }
});
// Kiểm tra bản Pro
if (!function_exists('aerp_crm_is_pro')) {
    function aerp_crm_is_pro()
    {
        return function_exists('aerp_is_pro_module') && aerp_is_pro_module('crm');
    }
}

// Khởi tạo plugin
function aerp_crm_init()
{
    // Load func dùng chung
    require_once AERP_CRM_PATH . 'includes/functions-common.php';
    require_once AERP_CRM_PATH . '../aerp-hrm/includes/functions-common.php';

    // Table 
    require_once AERP_CRM_PATH . '../aerp-hrm/frontend/includes/table/class-frontend-table.php';
    require_once AERP_CRM_PATH . 'includes/table/class-table-customer.php';
    require_once AERP_CRM_PATH . 'includes/table/class-table-customer-logs.php';
    require_once AERP_CRM_PATH . 'includes/table/class-table-customer-type.php';

    // Load các class cần thiết manager
    $includes = [
        'class-frontend-customer-manager.php',
        'class-frontend-customer-type-manager.php',
    ];
    foreach ($includes as $file) {
        require_once AERP_CRM_PATH . 'includes/managers/' . $file;
    }

    // Xử lý form và logic
    $managers = [
        'AERP_Frontend_Customer_Manager',
        'AERP_Frontend_Customer_Type_Manager',
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
        if (method_exists($manager, 'handle_add_customer_log')) {
            add_action('init', [$manager, 'handle_add_customer_log']);
        }
    }
}
add_action('plugins_loaded', 'aerp_crm_init');

// Đăng ký database khi kích hoạt
register_activation_hook(__FILE__, function () {
    require_once AERP_CRM_PATH . 'install-schema.php';
    aerp_crm_install_schema();
    flush_rewrite_rules();
});

// Xóa database khi deactivate
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
// === REWRITE RULES FOR FRONTEND DASHBOARD ===
require_once AERP_CRM_PATH . 'includes/page-rewrite-rules.php';

// Enqueue CRM specific scripts
add_action('wp_enqueue_scripts', function () {
    if (!is_admin()) {
        // Enqueue the customer-form.js only on relevant pages
        $request_uri = $_SERVER['REQUEST_URI'];
        // Check if the current URL contains '/aerp-crm-customers' (case-insensitive)
        // This is a direct check on the URI, which is more reliable than query vars at this stage.
        if (preg_match('/\/aerp-crm-customers/i', $request_uri)) {
            wp_enqueue_script('aerp-crm-customer-form', AERP_CRM_URL . 'assets/js/customer-form.js', ['jquery'], '1.0', true);
            // Add ajaxurl and nonce to JavaScript
            wp_localize_script('aerp-crm-customer-form', 'aerp_crm_ajax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                '_wpnonce_delete_attachment' => wp_create_nonce('aerp_delete_attachment_nonce'),
            ));

            // Enqueue and localize aerp-frontend-table.js for CRM customer table
            wp_enqueue_script('aerp-frontend-table', AERP_HRM_URL . 'assets/js/frontend-table.js', ['jquery', 'jquery-ui-dialog'], '1.0', true);

            // Instantiate AERP_Frontend_Customer_Table to get column keys and option key
            $customer_table_instance = new AERP_Frontend_Customer_Table();
            $all_crm_column_keys = $customer_table_instance->get_column_keys();
            $crm_hidden_columns_option_key = $customer_table_instance->get_hidden_columns_option_key();

            wp_localize_script('aerp-frontend-table', 'aerp_table_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('aerp_save_column_preferences'), // Same nonce action as HRM
                'all_column_keys' => $all_crm_column_keys,
                'hidden_columns_option_key' => $crm_hidden_columns_option_key
            ));
        }
    }
}, 20); // Use a higher priority to ensure it loads after other scripts if needed

// Ajax hooks
require_once AERP_CRM_PATH . 'includes/ajax/ajax-hook.php';