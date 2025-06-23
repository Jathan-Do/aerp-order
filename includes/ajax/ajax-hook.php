<?php

// AJAX Hooks for Customer Attachments
add_action('wp_ajax_aerp_delete_customer_attachment', ['AERP_Frontend_Customer_Manager', 'handle_delete_attachment_ajax']);
add_action('wp_ajax_nopriv_aerp_delete_customer_attachment', ['AERP_Frontend_Customer_Manager', 'handle_delete_attachment_ajax']); // If non-logged in users can delete, though typically not recommended

//ajax hook for filtering customers
add_action('wp_ajax_aerp_crm_filter_customers', 'aerp_crm_filter_customers_callback');
add_action('wp_ajax_nopriv_aerp_crm_filter_customers', 'aerp_crm_filter_customers_callback');
function aerp_crm_filter_customers_callback()
{
    $filters = [
        'customer_type_id' => sanitize_text_field($_POST['customer_type_id'] ?? ''),
        'status' => sanitize_text_field($_POST['status'] ?? ''),
        'assigned_to' => intval($_POST['assigned_to'] ?? 0),
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];
    $table = new AERP_Frontend_Customer_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}

//ajax hook for filtering customers type
add_action('wp_ajax_aerp_crm_filter_customers_type', 'aerp_crm_filter_customers_type_callback');
add_action('wp_ajax_nopriv_aerp_crm_filter_customers_type', 'aerp_crm_filter_customers_type_callback');
function aerp_crm_filter_customers_type_callback()
{
    $filters = [
        'color' => sanitize_text_field($_POST['color'] ?? ''),
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];
    $table = new AERP_Frontend_Customer_Type_Table();
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}


// AJAX filter logs
add_action('wp_ajax_aerp_crm_filter_customer_logs', 'aerp_crm_filter_customer_logs_callback');
add_action('wp_ajax_nopriv_aerp_crm_filter_customer_logs', 'aerp_crm_filter_customer_logs_callback');
function aerp_crm_filter_customer_logs_callback() {
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $filters = [
        'customer_id' => $customer_id,
        'interaction_type' => sanitize_text_field($_POST['interaction_type'] ?? ''),
        'search_term' => sanitize_text_field($_POST['s'] ?? ''),
        'paged' => intval($_POST['paged'] ?? 1),
        'orderby' => sanitize_text_field($_POST['orderby'] ?? ''),
        'order' => sanitize_text_field($_POST['order'] ?? ''),
    ];
    $table = new AERP_Frontend_Customer_Logs_Table($customer_id);
    $table->set_filters($filters);
    ob_start();
    $table->render();
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}