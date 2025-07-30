<?php
if (!defined('ABSPATH')) exit;
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
if (!is_user_logged_in()) {
    wp_die(__('You must be logged in to access this page.'));
}

// Danh sách điều kiện, chỉ cần 1 cái đúng là qua
$access_conditions = [
    aerp_user_has_role($user_id, 'admin'),
    aerp_user_has_role($user_id, 'department_lead'),
];
if (!in_array(true, $access_conditions, true)) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}
// Get filter parameters
$warehouse_id = isset($_GET['warehouse_id']) ? absint($_GET['warehouse_id']) : null;
$product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : null;
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');

// Get warehouses for filter - chỉ lấy kho user quản lý
global $wpdb;
$user_warehouse_ids = AERP_Inventory_Report_Manager::get_user_warehouse_ids($current_user->ID);

if (!empty($user_warehouse_ids)) {
    $warehouse_ids_str = implode(',', $user_warehouse_ids);
    $warehouses = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}aerp_warehouses WHERE id IN ($warehouse_ids_str) ORDER BY name");
} else {
    $warehouses = [];
}

// Get products for filter - chỉ lấy sản phẩm có tồn kho ở các kho user quản lý
if (!empty($user_warehouse_ids)) {
    $warehouse_ids_str = implode(',', $user_warehouse_ids);
    $products = $wpdb->get_results("
        SELECT DISTINCT p.id, p.name, p.sku
        FROM {$wpdb->prefix}aerp_products p
        JOIN {$wpdb->prefix}aerp_product_stocks ps ON ps.product_id = p.id
        WHERE ps.warehouse_id IN ($warehouse_ids_str)
        ORDER BY p.name
    ");
} else {
    $products = [];
}

// Get initial data
$stock_data = AERP_Inventory_Report_Manager::get_stock_timeline_data($warehouse_id, $product_id, $start_date, $end_date);
$summary = AERP_Inventory_Report_Manager::get_warehouse_summary($warehouse_id);
// Lấy employee_id từ user_id
global $wpdb;
$employee_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
    $user_id
));
$table = new AERP_Product_Stock_Table();
$table->set_filters(['manager_user_id' => $employee_id]);
$table->process_bulk_action();
ob_start();
ob_start();
?>
<style>
    .select2-container--default .select2-selection--single {
        border: 1px solid #dee2e6 !important;
        border-radius: 0.375rem !important;
        height: 38px !important;
        min-height: 38px !important;
        padding: 6px 12px !important;
        background: #fff !important;
        font-size: 1rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 24px !important;
        padding-left: 0 !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
        right: 0.75rem !important;
    }
</style>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Báo cáo tồn kho theo thời gian</h2>
    <div class="user-info text-end">
        Xin chào, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Thoát
        </a>
    </div>
</div>

<?php include plugin_dir_path(__FILE__) . 'reports-menu.php'; ?>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Bộ lọc</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="page" value="aerp-stock-timeline">

            <div class="col-md-3">
                <label for="warehouse_id" class="form-label">Kho</label>
                <select class="form-select warehouse-select-by-user" id="warehouse_id" name="warehouse_id">
                    <option value="">-- Tất cả kho --</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo esc_attr($warehouse->id); ?>" <?php selected($warehouse_id, $warehouse->id); ?>>
                            <?php echo esc_html($warehouse->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="product_id" class="form-label">Sản phẩm <span class="text-danger">*</span></label>
                <select class="form-select product-select-by-warehouse" id="product_id" name="product_id" required>
                    <option value="">-- Chọn sản phẩm --</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->id); ?>" <?php selected($product_id, $product->id); ?>>
                            <?php echo esc_html($product->name . ' (' . $product->sku . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="start_date" class="form-label">Từ ngày</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
            </div>

            <div class="col-md-2">
                <label for="end_date" class="form-label">Đến ngày</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <a href="?page=aerp-stock-timeline" class="btn btn-secondary">Làm mới</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Tổng sản phẩm</h5>
                <h3 class="card-text"><?php echo number_format($summary->total_products ?? 0); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Tổng tồn kho</h5>
                <h3 class="card-text"><?php echo number_format($summary->total_stock ?? 0); ?></h3>
            </div>
        </div>
    </div>
    <!-- <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Tồn kho thấp</h5>
                    <h3 class="card-text"><?php echo number_format($summary->low_stock ?? 0); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Hết hàng</h5>
                    <h3 class="card-text"><?php echo number_format($summary->out_of_stock ?? 0); ?></h3>
                </div>
            </div>
        </div> -->

</div>

<!-- Chart -->
<div class="card mb-4 mt-4">
    <div class="card-header">
        <h5 class="mb-0">Biểu đồ tồn kho theo thời gian</h5>
    </div>
    <div class="card-body">
        <?php if (!$product_id): ?>
            <div class="alert alert-info">Vui lòng chọn <b>1 sản phẩm</b> để xem biểu đồ tồn kho theo thời gian.</div>
        <?php else: ?>
            <canvas id="stockTimelineChart" width="400" height="200"></canvas>
        <?php endif; ?>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Dữ liệu tồn kho</h5>
        <div>
            <form method="post" action="<?= admin_url('admin-post.php') ?>">
                <?php wp_nonce_field('aerp_export_excel', 'aerp_export_nonce'); ?>
                <input type="hidden" name="action" value="aerp_export_excel_common">
                <input type="hidden" name="callback" value="stock_timeline_export">
                <input type="hidden" name="warehouse_id" value="<?= esc_attr($warehouse_id) ?>">
                <button type="submit" name="aerp_export_excel" class="btn btn-success">📥 Xuất Excel</button>
            </form>
        </div>
        
    </div>
    <div class="card-body">
    <div id="aerp-product-stock-table-wrapper">
            <?php $table->render(); ?>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    jQuery(document).ready(function($) {
        <?php if ($product_id): ?>
            // Lấy lịch sử tồn kho qua AJAX
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'aerp_get_stock_history',
                    nonce: '<?php echo wp_create_nonce('aerp_inventory_report_nonce'); ?>',
                    product_id: <?php echo (int)$product_id; ?>,
                    warehouse_id: <?php echo $warehouse_id ? (int)$warehouse_id : 'null'; ?>,
                    start_date: '<?php echo esc_js($start_date); ?>',
                    end_date: '<?php echo esc_js($end_date); ?>'
                },
                success: function(res) {
                    if (res.success && res.data && res.data.length > 0) {
                        const ctx = document.getElementById('stockTimelineChart').getContext('2d');
                        const labels = res.data.map(item => item.date);
                        const stockValues = res.data.map(item => item.stock);
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Tồn kho',
                                    data: stockValues,
                                    borderColor: '#007bff',
                                    backgroundColor: 'rgba(0,123,255,0.1)',
                                    fill: true,
                                    tension: 0.2
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Số lượng'
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Ngày'
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return 'Tồn kho: ' + context.parsed.y;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        $('#stockTimelineChart').replaceWith('<div class="alert alert-warning">Không có dữ liệu lịch sử tồn kho cho sản phẩm này.</div>');
                    }
                },
                error: function() {
                    $('#stockTimelineChart').replaceWith('<div class="alert alert-danger">Lỗi khi tải dữ liệu lịch sử tồn kho.</div>');
                }
            });
        <?php endif; ?>
    });
    jQuery(function($) {
        function initSelect2() {
            $('.product-select-by-warehouse').select2({
                placeholder: '-- Chọn sản phẩm trong kho --',
                allowClear: true,
                ajax: {
                    url: (typeof aerp_order_ajax !== 'undefined' ? aerp_order_ajax.ajaxurl : ajaxurl),
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            action: "aerp_order_search_products_in_warehouse",
                            warehouse_id: $("select[name='warehouse_id']").val(), // <-- lấy động mỗi lần gọi ajax
                            q: params.term || "",
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });

            $('.product-select-by-warehouse').on('select2:select', function(e) {
                const product_id = e.params.data.id;
                const warehouse_id = $('.warehouse-select-by-user').val();

                if (!warehouse_id) {
                    alert('Vui lòng chọn kho trước!');
                    $(this).val(null).trigger('change');
                    return;
                }
            });
        }

        initSelect2();
    });
</script>

<style>
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: 1px solid rgba(0, 0, 0, 0.125);
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    }

    .table th {
        background-color: #f8f9fa;
        border-top: none;
    }

    .badge {
        font-size: 0.875em;
    }
</style>

<?php
$content = ob_get_clean();
$title = 'Báo cáo tồn kho theo thời gian';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
