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
        $wpdb->prefix . 'aerp_warehouse_managers',
        $wpdb->prefix . 'aerp_order_device_progresses',
        // Accounting tables
        $wpdb->prefix . 'aerp_acc_categories',
        $wpdb->prefix . 'aerp_acc_receipts',
        $wpdb->prefix . 'aerp_acc_receipt_lines',
        $wpdb->prefix . 'aerp_acc_deposits',
        $wpdb->prefix . 'aerp_acc_deposit_lines',
        $wpdb->prefix . 'aerp_acc_payments',
        $wpdb->prefix . 'aerp_acc_payment_lines',
        // Notifications
        $wpdb->prefix . 'aerp_notifications',
        // Calendar
        $wpdb->prefix . 'aerp_calendar_events',
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
        order_date DATETIME,
        total_amount FLOAT,
        cost FLOAT DEFAULT 0,
        customer_source_id BIGINT DEFAULT NULL,
        status_id BIGINT,
        status ENUM('new','assigned','rejected','completed','paid','cancelled') DEFAULT 'new',
        order_type ENUM('product','device','return','content','service','mixed','all') DEFAULT 'content',
        cancel_reason TEXT DEFAULT NULL,
        reject_reason TEXT DEFAULT NULL,
        note TEXT,
        created_by BIGINT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_code (order_code),
        INDEX idx_customer_id (customer_id),
        INDEX idx_employee_id (employee_id),
        INDEX idx_status_id (status_id),
        INDEX idx_customer_source_id (customer_source_id),
        INDEX idx_order_type (order_type),
        INDEX idx_created_by (created_by),
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

    // 1c. Thiết bị nhận từ khách (gắn với đơn hàng)
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_devices (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT NOT NULL,
        device_name VARCHAR(255) NOT NULL,
        serial_number VARCHAR(100) DEFAULT NULL,
        status VARCHAR(100) DEFAULT NULL,
        device_status ENUM('received','disposed') DEFAULT 'received',
        progress_id BIGINT DEFAULT NULL,
        note TEXT DEFAULT NULL,
        partner_id BIGINT DEFAULT NULL,
        device_date DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_device_name (device_name),
        INDEX idx_serial_number (serial_number),
        INDEX idx_device_status (device_status),
        INDEX idx_progress_id (progress_id),
        INDEX idx_partner_id (partner_id),
        INDEX idx_device_date (device_date)
    ) $charset_collate;";

    // 1c-b. Thiết bị trả lại cho khách (gắn với đơn hàng và thiết bị nhận)
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_device_returns (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT NOT NULL,
        device_id BIGINT NOT NULL,
        return_date DATETIME DEFAULT NULL,
        status ENUM('draft','confirmed') DEFAULT 'draft',
        note TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_device_id (device_id),
        INDEX idx_return_date (return_date)
    ) $charset_collate;";

    // 1c-c. Tiến độ thiết bị (tùy chỉnh)
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_device_progresses (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        color VARCHAR(20) DEFAULT '#007cba',
        is_active BOOLEAN DEFAULT TRUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_color (color),
        INDEX idx_is_active (is_active)
    ) $charset_collate;";

    // 1d. Template nội dung triển khai
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_implementation_templates (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_by BIGINT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_is_active (is_active),
        INDEX idx_created_by (created_by)
    ) $charset_collate;";

    // 1e. Nội dung yêu cầu và triển khai (nhiều dòng)
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_order_content_lines (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT NOT NULL,
        requirement TEXT DEFAULT NULL,
        implementation TEXT DEFAULT NULL,
        template_id BIGINT DEFAULT NULL,
        unit_price FLOAT,
        quantity FLOAT,
        total_price FLOAT,
        warranty VARCHAR(255) DEFAULT NULL,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_template_id (template_id),
        INDEX idx_sort_order (sort_order)
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
        item_type VARCHAR(20) DEFAULT 'product',
        purchase_type VARCHAR(20) DEFAULT 'warehouse',
        external_supplier_name VARCHAR(255) DEFAULT NULL,
        external_cost FLOAT DEFAULT 0,
        external_delivery_date DATE DEFAULT NULL,
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
        warehouse_type ENUM('branch','personal') DEFAULT 'branch',
        owner_user_id BIGINT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_warehouse_type (warehouse_type),
        INDEX idx_owner_user_id (owner_user_id)
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

    // 8. Phân quyền quản lý kho cho user (nhiều-nhiều)
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_warehouse_managers (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        warehouse_id BIGINT NOT NULL,
        manager_type ENUM('branch_manager','personal_owner') DEFAULT 'branch_manager',
        UNIQUE KEY uq_user_warehouse (user_id, warehouse_id),
        INDEX idx_user_id (user_id),
        INDEX idx_warehouse_id (warehouse_id),
        INDEX idx_manager_type (manager_type)
    ) $charset_collate;";

    // 9. Accounting - Categories
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_acc_categories (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) DEFAULT NULL,
        is_accounted BOOLEAN DEFAULT TRUE,
        active BOOLEAN DEFAULT TRUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_code (code),
        INDEX idx_is_accounted (is_accounted),
        INDEX idx_active (active)
    ) $charset_collate;";

    // 10. Accounting - Receipts (phiếu thu)
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_acc_receipts (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) DEFAULT NULL,
        created_by BIGINT DEFAULT NULL,
        status ENUM('draft','submitted','approved','rejected','cancelled') DEFAULT 'draft',
        receipt_date DATE DEFAULT NULL,
        total_amount DECIMAL(18,2) DEFAULT 0,
        note TEXT DEFAULT NULL,
        approved_by BIGINT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        attachments TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_code (code),
        INDEX idx_status (status),
        INDEX idx_receipt_date (receipt_date),
        INDEX idx_created_by (created_by)
    ) $charset_collate;";

    // 10b. Accounting - Receipt lines
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_acc_receipt_lines (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        receipt_id BIGINT NOT NULL,
        order_id BIGINT DEFAULT NULL,
        amount DECIMAL(18,2) NOT NULL,
        note TEXT DEFAULT NULL,
        INDEX idx_receipt_id (receipt_id),
        INDEX idx_order_id (order_id)
    ) $charset_collate;";

    // 10c. Accounting - Deposits (phiếu nộp tiền)
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_acc_deposits (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) DEFAULT NULL,
        receipt_id BIGINT NOT NULL,
        created_by BIGINT DEFAULT NULL,
        status ENUM('draft','submitted','approved','rejected','cancelled') DEFAULT 'draft',
        deposit_date DATE DEFAULT NULL,
        total_amount DECIMAL(18,2) NOT NULL,
        note TEXT DEFAULT NULL,
        approved_by BIGINT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        attachments TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_code (code),
        INDEX idx_status (status),
        INDEX idx_deposit_date (deposit_date),
        INDEX idx_receipt_id (receipt_id),
        INDEX idx_created_by (created_by)
    ) $charset_collate;";

    // 10d. Accounting - Deposit lines
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_acc_deposit_lines (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        deposit_id BIGINT NOT NULL,
        order_id BIGINT NOT NULL,
        revenue_amount DECIMAL(18,2) NOT NULL,
        advance_amount DECIMAL(18,2) NOT NULL,
        advance_payment_id BIGINT DEFAULT NULL,
        external_amount DECIMAL(18,2) NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        note TEXT DEFAULT NULL,
        INDEX idx_deposit_id (deposit_id),
        INDEX idx_order_id (order_id),
        INDEX idx_advance_payment_id (advance_payment_id)
    ) $charset_collate;";

    // 11. Accounting - Payments (phiếu chi)
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_acc_payments (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) DEFAULT NULL,
        created_by BIGINT DEFAULT NULL,
        status ENUM('draft','confirmed','paid') DEFAULT 'draft',
        payment_date DATE DEFAULT NULL,
        -- Loại phiếu (lấy từ danh mục phiếu chi)
        voucher_type_id BIGINT DEFAULT NULL,
        -- Người chi (nhân sự)
        payer_employee_id BIGINT DEFAULT NULL,
        -- Loại chi: employee (NV), supplier (NCC), customer (KH), other
        payee_type ENUM('employee','supplier','customer','other') DEFAULT 'employee',
        -- Người nhận theo loại
        payee_employee_id BIGINT DEFAULT NULL,
        supplier_id BIGINT DEFAULT NULL,
        customer_id BIGINT DEFAULT NULL,
        payee_text VARCHAR(255) DEFAULT NULL,
        -- Phương thức thanh toán và số tài khoản
        payment_method ENUM('cash','bank_transfer','card','other') DEFAULT 'cash',
        bank_account VARCHAR(100) DEFAULT NULL,
        -- Tổng tiền & ghi chú
        total_amount DECIMAL(18,2) NOT NULL,
        note TEXT DEFAULT NULL,
        -- Xác nhận đã chi
        confirmed_by BIGINT DEFAULT NULL,
        confirmed_at DATETIME DEFAULT NULL,
        attachments TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_code (code),
        INDEX idx_status (status),
        INDEX idx_payment_date (payment_date),
        INDEX idx_voucher_type (voucher_type_id),
        INDEX idx_payer_employee (payer_employee_id),
        INDEX idx_payee_employee (payee_employee_id),
        INDEX idx_supplier_id (supplier_id),
        INDEX idx_customer_id (customer_id)
    ) $charset_collate;";

    // 11b. Accounting - Payment lines
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_acc_payment_lines (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        payment_id BIGINT NOT NULL,
        order_id BIGINT DEFAULT NULL,
        description TEXT DEFAULT NULL,
        amount DECIMAL(18,2) NOT NULL,
        vat_percent FLOAT DEFAULT NULL,
        is_accounted_override BOOLEAN DEFAULT NULL,
        note TEXT DEFAULT NULL,
        category_id BIGINT DEFAULT NULL,
        INDEX idx_payment_id (payment_id),
        INDEX idx_order_id (order_id),
        INDEX idx_category_id (category_id)
    ) $charset_collate;";

    // 12. Notifications - Thông báo realtime
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_notifications (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        link_url VARCHAR(500) DEFAULT NULL,
        related_id BIGINT DEFAULT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_type (type),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    ) $charset_collate;";

    // 13. Calendar - Lịch hẹn và sự kiện
    $sqls[] = "CREATE TABLE {$wpdb->prefix}aerp_calendar_events (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        event_type ENUM('appointment','delivery','meeting','reminder','other') DEFAULT 'appointment',
        customer_id BIGINT DEFAULT NULL,
        order_id BIGINT DEFAULT NULL,
        employee_id BIGINT DEFAULT NULL,
        start_date DATETIME NOT NULL,
        end_date DATETIME DEFAULT NULL,
        location VARCHAR(255) DEFAULT NULL,
        color VARCHAR(20) DEFAULT '#007cba',
        is_all_day BOOLEAN DEFAULT FALSE,
        reminder_minutes INT DEFAULT NULL,
        reminder_sent BOOLEAN DEFAULT FALSE,
        created_by BIGINT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_customer_id (customer_id),
        INDEX idx_order_id (order_id),
        INDEX idx_employee_id (employee_id),
        INDEX idx_start_date (start_date),
        INDEX idx_event_type (event_type),
        INDEX idx_created_by (created_by),
        INDEX idx_reminder (reminder_sent)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($sqls as $sql) {
        dbDelta($sql);
    }
}
