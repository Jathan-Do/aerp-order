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
        $wpdb->prefix . 'aerp_products',
        $wpdb->prefix . 'aerp_inventory_logs',
        $wpdb->prefix . 'aerp_order_statuses',
        $wpdb->prefix . 'aerp_product_categories',
        $wpdb->prefix . 'aerp_units',
        $wpdb->prefix . 'aerp_warehouses',
        $wpdb->prefix . 'aerp_product_stocks',
        $wpdb->prefix . 'aerp_inventory_transfers',
        $wpdb->prefix . 'aerp_inventory_transfer_items',
        $wpdb->prefix . 'aerp_suppliers',
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
        status_id BIGINT,
        order_type ENUM('service','product') DEFAULT 'product',
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_code (order_code),
        INDEX idx_customer_id (customer_id),
        INDEX idx_employee_id (employee_id),
        INDEX idx_status_id (status_id),
        INDEX idx_order_type (order_type),
        INDEX idx_created_at (created_at)
    ) $charset_collate;";

    // 1b. Trạng thái đơn hàng động
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_statuses (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name)
    ) $charset_collate;";

    // 2. Sản phẩm trong đơn
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_items (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT,
        product_id BIGINT NULL,
        product_name VARCHAR(255),
        quantity FLOAT,
        unit_price FLOAT,
        total_price FLOAT,
        unit_name VARCHAR(255),
        vat_percent FLOAT NULL,
        INDEX idx_order_id (order_id),
        INDEX idx_product_id (product_id),
        INDEX idx_product_name (product_name)
    ) $charset_collate;";
    
    // 3. Lịch sử trạng thái đơn
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_status_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT,
        old_status_id BIGINT,
        new_status_id BIGINT,
        changed_by BIGINT,
        changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_old_status_id (old_status_id),
        INDEX idx_new_status_id (new_status_id),
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

    // 5. Sản phẩm kho
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_products (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        sku VARCHAR(100) DEFAULT NULL,
        price FLOAT DEFAULT 0,
        whole_price FLOAT DEFAULT 0,
        category_id BIGINT NULL,
        unit_id BIGINT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_sku (sku),
        INDEX idx_category_id (category_id),
        INDEX idx_unit_id (unit_id)
    ) $charset_collate;";

    // 5b. Danh mục sản phẩm
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_product_categories (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        parent_id BIGINT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_parent_id (parent_id)
    ) $charset_collate;";

    // 5c. Đơn vị tính
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_units (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        symbol VARCHAR(20) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name)
    ) $charset_collate;";

    // 5d. Danh sách kho
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_warehouses (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        work_location_id BIGINT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name)
    ) $charset_collate;";

    // 5e. Tồn kho từng sản phẩm theo từng kho
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_product_stocks (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        product_id BIGINT NOT NULL,
        warehouse_id BIGINT NOT NULL,
        quantity INT DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_product_warehouse (product_id, warehouse_id),
        INDEX idx_product_id (product_id),
        INDEX idx_warehouse_id (warehouse_id)
    ) $charset_collate;";

    // 6. Lịch sử nhập/xuất kho
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_inventory_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        product_id BIGINT NOT NULL,
        warehouse_id BIGINT NOT NULL,
        supplier_id BIGINT NULL,
        type ENUM('import','export','stocktake') NOT NULL,
        status ENUM('draft','confirmed') DEFAULT 'draft',
        quantity INT NOT NULL,
        note TEXT,
        created_by BIGINT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product_id (product_id),
        INDEX idx_warehouse_id (warehouse_id),
        INDEX idx_supplier_id (supplier_id),
        INDEX idx_type (type),
        INDEX idx_created_by (created_by),
        INDEX idx_created_at (created_at)
    ) $charset_collate;";

    // 7. Nhà cung cấp
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_suppliers (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        email VARCHAR(100) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        note TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name)
    ) $charset_collate;";

    // 6b. Phiếu chuyển kho
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_inventory_transfers (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        from_warehouse_id BIGINT NOT NULL,
        to_warehouse_id BIGINT NOT NULL,
        created_by BIGINT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        note TEXT,
        INDEX idx_from_warehouse (from_warehouse_id),
        INDEX idx_to_warehouse (to_warehouse_id),
        INDEX idx_created_by (created_by)
    ) $charset_collate;";

    // 6c. Chi tiết sản phẩm chuyển kho
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_inventory_transfer_items (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        transfer_id BIGINT NOT NULL,
        product_id BIGINT NOT NULL,
        quantity INT NOT NULL,
        INDEX idx_transfer_id (transfer_id),
        INDEX idx_product_id (product_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($sqls as $sql) {
        dbDelta($sql);
    }
}
