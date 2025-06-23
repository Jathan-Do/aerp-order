<?php

add_action('wp_ajax_aerp_order_filter_orders', 'aerp_order_filter_orders_callback');
add_action('wp_ajax_nopriv_aerp_order_filter_orders', 'aerp_order_filter_orders_callback');
function aerp_order_filter_orders_callback()
{
    $filters = [
        'status' => sanitize_text_field($_POST['status'] ?? ''),
        'employee_id' => intval($_POST['employee_id'] ?? 0),
        'customer_id' => intval($_POST['customer_id'] ?? 0),
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
        'old_status' => sanitize_text_field($_POST['old_status'] ?? ''),
        'new_status' => sanitize_text_field($_POST['new_status'] ?? ''),
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