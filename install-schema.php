<?php

/**
 * Install schema for AERP CRM Module
 */

function aerp_crm_get_table_names()
{
    global $wpdb;
    return [
        $wpdb->prefix . 'aerp_crm_customers',
        $wpdb->prefix . 'aerp_crm_customer_types',
        $wpdb->prefix . 'aerp_crm_customer_phones',
        $wpdb->prefix . 'aerp_crm_logs',
        $wpdb->prefix . 'aerp_crm_attachments',
    ];
}

function aerp_crm_install_schema()
{
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sqls = [];

    // 1. Khách hàng
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_crm_customers (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        customer_code VARCHAR(50),
        full_name VARCHAR(255),
        company_name VARCHAR(255),
        tax_code VARCHAR(50),
        address TEXT,
        email VARCHAR(255),
        customer_type_id BIGINT,
        status ENUM('active','inactive') DEFAULT 'active',
        assigned_to BIGINT,
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_customer_code (customer_code),
        INDEX idx_full_name (full_name),
        INDEX idx_company_name (company_name),
        INDEX idx_customer_type_id (customer_type_id),
        INDEX idx_status (status),
        INDEX idx_assigned_to (assigned_to),
        INDEX idx_created_at (created_at)
    ) $charset_collate;";

    // 1.1. Loại khách hàng
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_crm_customer_types (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        type_key VARCHAR(50) UNIQUE,
        name VARCHAR(255),
        description TEXT,
        color VARCHAR(20),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_type_key (type_key),
        INDEX idx_name (name)
    ) $charset_collate;";

    // 2. Số điện thoại khách hàng
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_crm_customer_phones (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        customer_id BIGINT,
        phone_number VARCHAR(20),
        is_primary BOOLEAN DEFAULT false,
        note VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_customer_id (customer_id),
        INDEX idx_phone_number (phone_number)
    ) $charset_collate;";

    // 3. Tương tác
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_crm_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        customer_id BIGINT,
        interaction_type VARCHAR(50),
        content TEXT,
        interacted_by BIGINT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_customer_id (customer_id),
        INDEX idx_interaction_type (interaction_type),
        INDEX idx_interacted_by (interacted_by),
        INDEX idx_created_at (created_at)
    ) $charset_collate;";

    // 4. File đính kèm
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_crm_attachments (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        customer_id BIGINT,
        file_name VARCHAR(255),
        file_url TEXT,
        file_type VARCHAR(50),
        uploaded_by BIGINT,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_customer_id (customer_id),
        INDEX idx_uploaded_by (uploaded_by),
        INDEX idx_uploaded_at (uploaded_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($sqls as $sql) {
        dbDelta($sql);
    }
} 
