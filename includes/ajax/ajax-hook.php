<?php

add_action('wp_ajax_aerp_order_filter_orders', 'aerp_order_filter_orders_callback');
add_action('wp_ajax_nopriv_aerp_order_filter_orders', 'aerp_order_filter_orders_callback');
function aerp_order_filter_orders_callback()
{
    $filters = [
        'status_id' => absint($_POST['status_id'] ?? 0),
        'employee_id' => intval($_POST['employee_id'] ?? 0),
        'customer_id' => intval($_POST['customer_id'] ?? 0),
        'order_type' => sanitize_text_field($_POST['order_type'] ?? ''),
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];
    $table = new AERP_Frontend_Order_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

// AJAX hook for deleting order attachments
add_action('wp_ajax_aerp_delete_order_attachment', ['AERP_Frontend_Order_Manager', 'handle_delete_attachment_ajax']);

add_action('wp_ajax_aerp_order_filter_status_logs', 'aerp_order_filter_status_logs_callback');
add_action('wp_ajax_nopriv_aerp_order_filter_status_logs', 'aerp_order_filter_status_logs_callback');
function aerp_order_filter_status_logs_callback()
{
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_send_json_error('Missing order ID');
    }

    $filters = [
        'order_id' => $order_id,
        'old_status_id' => absint($_POST['old_status_id'] ?? 0),
        'new_status_id' => absint($_POST['new_status_id'] ?? 0),
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];

    $table = new AERP_Frontend_Order_Status_Log_Table($order_id);
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_aerp_product_filter_products', 'aerp_product_filter_products_callback');
add_action('wp_ajax_nopriv_aerp_product_filter_products', 'aerp_product_filter_products_callback');
function aerp_product_filter_products_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];

    $table = new AERP_Product_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_aerp_order_search_products', function() {
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $products = function_exists('aerp_get_products') ? aerp_get_products($q) : [];
    $results = [];
    $count = 0;
    foreach ($products as $product) {
        $results[] = [
            'id' => $product->id,
            'text' => $product->name . (!empty($product->sku) ? ' (' . $product->sku . ')' : ''),
            'price' => $product->price,
            'unit_name' => $product->unit_name ?? '',
        ];
        if (!$q && ++$count >= 20) break; // chỉ trả về 20 sản phẩm đầu nếu không search
    }
    wp_send_json($results);
});

add_action('wp_ajax_aerp_category_filter_categories', 'aerp_category_filter_categories_callback');
add_action('wp_ajax_nopriv_aerp_category_filter_categories', 'aerp_category_filter_categories_callback');
function aerp_category_filter_categories_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];

    $table = new AERP_Category_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_aerp_unit_filter_units', 'aerp_unit_filter_units_callback');
add_action('wp_ajax_nopriv_aerp_unit_filter_units', 'aerp_unit_filter_units_callback');
function aerp_unit_filter_units_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];

    $table = new AERP_Unit_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_aerp_order_status_filter_statuses', 'aerp_order_status_filter_statuses_callback');
add_action('wp_ajax_nopriv_aerp_order_status_filter_statuses', 'aerp_order_status_filter_statuses_callback');
function aerp_order_status_filter_statuses_callback()
{
    $filters = [
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'color' => sanitize_text_field($_POST['color'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];
    $table = new AERP_Order_Status_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}