<?php

/**
 * Install schema for AERP Order Module
 */

function aerp_order_get_table_names()
{
    global $wpdb;
    return [
        $wpdb->prefix . 'aerp_order_orders',
        $wpdb->prefix . 'aerp_order_items',
        $wpdb->prefix . 'aerp_order_status_logs',
        $wpdb->prefix . 'aerp_order_attachments',
    ];
}

function aerp_order_install_schema()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sqls = [];

    // 1. Đơn hàng
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_orders (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_code VARCHAR(50),
        customer_id BIGINT,
        employee_id BIGINT,
        order_date DATE,
        total_amount FLOAT,
        status ENUM('new','processing','completed','cancelled') DEFAULT 'new',
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_code (order_code),
        INDEX idx_customer_id (customer_id),
        INDEX idx_employee_id (employee_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) $charset_collate;";

    // 2. Sản phẩm trong đơn
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_items (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT,
        product_name VARCHAR(255),
        quantity INT,
        unit_price FLOAT,
        total_price FLOAT,
        INDEX idx_order_id (order_id)
    ) $charset_collate;";

    // 3. Lịch sử trạng thái đơn
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_status_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT,
        old_status VARCHAR(50),
        new_status VARCHAR(50),
        changed_by BIGINT,
        changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_changed_by (changed_by),
        INDEX idx_changed_at (changed_at)
    ) $charset_collate;";

    // 4. File đính kèm
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_attachments (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT,
        file_name VARCHAR(255),
        file_url TEXT,
        file_type VARCHAR(50),
        uploaded_by BIGINT,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_uploaded_by (uploaded_by),
        INDEX idx_uploaded_at (uploaded_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($sqls as $sql) {
        dbDelta($sql);
    }
} 
