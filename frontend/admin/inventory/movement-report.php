<?php
if (!defined('ABSPATH')) exit;
// Get current user
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
$type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : null;

// Get warehouses for filter
global $wpdb;
$warehouses = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}aerp_warehouses ORDER BY name");

// Get products for filter
$products = $wpdb->get_results("SELECT id, name, sku FROM {$wpdb->prefix}aerp_products ORDER BY name");

// Get initial data
$movement_data = AERP_Inventory_Report_Manager::get_movement_data($warehouse_id, $product_id, $start_date, $end_date, $type);
$daily_summary = AERP_Inventory_Report_Manager::get_daily_movement_summary($warehouse_id, $start_date, $end_date, $product_id);
$top_products = AERP_Inventory_Report_Manager::get_top_moving_products($warehouse_id, 10, $start_date, $end_date, $product_id);

// Calculate totals
$total_import = 0;
$total_export = 0;
$total_adjustment = 0;
foreach ($movement_data as $item) {
    if ($item->type === 'import') $total_import += $item->quantity;
    elseif ($item->type === 'export') $total_export += $item->quantity;
    elseif ($item->type === 'stocktake') $total_adjustment += $item->quantity;
}
// Lấy employee_id từ user_id
global $wpdb;
$employee_id = $wpdb->get_var($wpdb->prepare(
    "SELECT id FROM {$wpdb->prefix}aerp_hrm_employees WHERE user_id = %d",
    $user_id
));
$table = new AERP_Inventory_Log_Table();
$table->set_filters(['manager_user_id' => $employee_id]);
$table->process_bulk_action();
ob_start();
?>
<style>
    .select2-container--default .select2-selection--single {
        border: 1px solid #dee2e6 !important;
        border-radius: 0.375rem !important;
        height: 38px !important;
        padding: 6px 12px !important;
        background: #fff !important;
        font-size: 1rem !important;
        box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075) !important;
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
    <h2>Báo cáo chuyển động kho</h2>
    <div class="user-info text-end">
        Xin chào, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Thoát
        </a>
    </div>
</div>
<?php
if (function_exists('aerp_render_breadcrumb')) {
    aerp_render_breadcrumb([
        ['label' => 'Trang chủ', 'url' => home_url('/aerp-dashboard'), 'icon' => 'fas fa-home'],
        ['label' => 'Báo cáo']
    ]);
}
?>
<?php include plugin_dir_path(__FILE__) . 'reports-menu.php'; ?>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Bộ lọc</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="page" value="aerp-movement-report">

            <div class="col-md-2">
                <label for="warehouse_id" class="form-label">Kho</label>
                <select class="form-select shadow-sm warehouse-select-by-user" id="warehouse_id" name="warehouse_id">
                    <option value="">-- Tất cả kho --</option>
                    <?php foreach ($warehouses as $warehouse): ?>
                        <option value="<?php echo esc_attr($warehouse->id); ?>" <?php selected($warehouse_id, $warehouse->id); ?>>
                            <?php echo esc_html($warehouse->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="product_id" class="form-label">Sản phẩm</label>
                <select class="form-select shadow-sm product-select-by-warehouse" id="product_id" name="product_id">
                    <option value="">-- Tất cả sản phẩm --</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo esc_attr($product->id); ?>" <?php selected($product_id, $product->id); ?>>
                            <?php echo esc_html($product->name . ' (' . $product->sku . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label for="type" class="form-label">Loại</label>
                <select class="form-select shadow-sm" id="type" name="type">
                    <option value="">-- Tất cả --</option>
                    <option value="import" <?php selected($type, 'import'); ?>>Nhập kho</option>
                    <option value="export" <?php selected($type, 'export'); ?>>Xuất kho</option>
                    <option value="stocktake" <?php selected($type, 'stocktake'); ?>>Kiểm kho</option>
                </select>
            </div>

            <div class="col-md-2">
                <label for="start_date" class="form-label">Từ ngày</label>
                <input type="date" class="form-control shadow-sm bg-body" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
            </div>

            <div class="col-md-2">
                <label for="end_date" class="form-label">Đến ngày</label>
                <input type="date" class="form-control shadow-sm bg-body" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <a href="?page=aerp-movement-report" class="btn btn-secondary">Làm mới</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Tổng nhập kho</h5>
                <h3 class="card-text"><?php echo number_format($total_import); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h5 class="card-title">Tổng xuất kho</h5>
                <h3 class="card-text"><?php echo number_format($total_export); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5 class="card-title">Điều chỉnh</h5>
                <h3 class="card-text"><?php echo number_format($total_adjustment); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Tổng giao dịch</h5>
                <h3 class="card-text"><?php echo number_format(count($movement_data)); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <!-- Daily Movement Chart -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Biểu đồ chuyển động theo ngày</h5>
            </div>
            <div class="card-body">
                <canvas id="dailyMovementChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Products Chart -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Top sản phẩm chuyển động</h5>
            </div>
            <div class="card-body">
                <canvas id="topProductsChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Movement Data Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Chi tiết chuyển động kho</h5>
        <div>
            <form method="post" action="<?= admin_url('admin-post.php') ?>">
                <?php wp_nonce_field('aerp_export_excel', 'aerp_export_nonce'); ?>
                <input type="hidden" name="action" value="aerp_export_excel_common">
                <input type="hidden" name="callback" value="movement_report_export">
                <button type="submit" name="aerp_export_excel" class="btn btn-success">📥 Xuất Excel</button>
            </form>
        </div>
    </div>
    <div class="card-body">

        <form id="aerp-inventory-log-filter-form" class="row g-2 mb-3 aerp-table-ajax-form" data-table-wrapper="#aerp-inventory-log-table-wrapper" data-ajax-action="aerp_inventory_log_filter_inventory_logs">
            <input type="hidden" name="manager_user_id" value="<?php echo esc_attr($user_id); ?>">
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-type" class="form-label mb-1">Loại phiếu</label>
                <select id="filter-type" name="type" class="form-select shadow-sm">
                    <option value="">-- Tất cả --</option>
                    <option value="import">Nhập kho</option>
                    <option value="export">Xuất kho</option>
                    <option value="stocktake">Kiểm kho</option>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-status" class="form-label mb-1">Trạng thái</label>
                <select id="filter-status" name="status" class="form-select shadow-sm">
                    <option value="">-- Tất cả --</option>
                    <option value="confirmed">Đã xác nhận</option>
                    <option value="draft">Nháp</option>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-warehouse" class="form-label mb-1">Kho</label>
                <select id="filter-warehouse" name="warehouse_id" class="form-select shadow-sm warehouse-select-by-user">
                    <?php
                    $warehouses = aerp_get_warehouses_by_user($user_id);
                    aerp_safe_select_options($warehouses, $warehouse_id, 'id', 'name', true);
                    ?>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label for="filter-supplier" class="form-label mb-1">Nhà cung cấp</label>
                <select id="filter-supplier" name="supplier_id" class="form-select shadow-sm supplier-select">
                    <?php
                    $suppliers = aerp_get_suppliers();
                    aerp_safe_select_options($suppliers, $supplier_id, 'id', 'name', true);
                    ?>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary w-100">Lọc</button>
            </div>
        </form>
        <?php if ($message) {
            echo '<div class="notice notice-success alert alert-success alert-dismissible fade show" role="alert">' . esc_html($message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_inventory_log_message');
        } ?>
        <div id="aerp-inventory-log-table-wrapper">
            <?php $table->render(); ?>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    jQuery(document).ready(function($) {
        // Daily Movement Chart
        const dailyCtx = document.getElementById('dailyMovementChart').getContext('2d');
        const dailyData = <?php echo json_encode($daily_summary); ?>;

        // Group data by date
        const dateGroups = {};
        dailyData.forEach(item => {
            if (!dateGroups[item.date]) {
                dateGroups[item.date] = {
                    import: 0,
                    export: 0,
                    adjustment: 0
                };
            }
            dateGroups[item.date][item.type === 'import' ? 'import' : (item.type === 'export' ? 'export' : 'adjustment')] += parseInt(item.total_import || item.total_export || item.total_adjustment || 0);
        });

        const dates = Object.keys(dateGroups).sort();
        const importData = dates.map(date => dateGroups[date].import);
        const exportData = dates.map(date => dateGroups[date].export);
        const adjustmentData = dates.map(date => dateGroups[date].adjustment);

        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dates.map(date => new Date(date).toLocaleDateString('vi-VN')),
                datasets: [{
                        label: 'Nhập kho',
                        data: importData,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Xuất kho',
                        data: exportData,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Điều chỉnh',
                        data: adjustmentData,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        tension: 0.1
                    }
                ]
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
                }
            }
        });

        // Top Products Chart
        const topCtx = document.getElementById('topProductsChart').getContext('2d');
        const topData = <?php echo json_encode($top_products); ?>;

        const topChart = new Chart(topCtx, {
            type: 'doughnut',
            data: {
                labels: topData.map(item => item.product_name),
                datasets: [{
                    data: topData.map(item => item.net_movement),
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                        '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });

        // Export to Excel function
        window.exportToExcel = function() {
            const table = document.getElementById('movementTable');
            const html = table.outerHTML;
            const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            const downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            downloadLink.href = url;
            downloadLink.download = 'bao-cao-chuyen-dong-kho-' + new Date().toISOString().split('T')[0] + '.xls';
            downloadLink.click();
            document.body.removeChild(downloadLink);
        };
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
$title = 'Báo cáo chuyển động kho';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
