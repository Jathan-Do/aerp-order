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

// Handle threshold update
if (isset($_POST['update_threshold']) && wp_verify_nonce($_POST['threshold_nonce'], 'update_threshold_action')) {
    $new_threshold = absint($_POST['threshold']);
    update_option('aerp_low_stock_threshold', $new_threshold);
    set_transient('aerp_alert_message', 'Đã cập nhật ngưỡng cảnh báo thành công!', 10);
    wp_redirect($_SERVER['REQUEST_URI']);
    exit;
}

// Get filter parameters
$warehouse_id = isset($_GET['warehouse_id']) ? absint($_GET['warehouse_id']) : null;
$threshold = isset($_GET['threshold']) ? absint($_GET['threshold']) : get_option('aerp_low_stock_threshold', 10);
$product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : null;

// Get warehouses for filter
global $wpdb;
$warehouses = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}aerp_warehouses ORDER BY name");

// Get alert message
$alert_message = get_transient('aerp_alert_message');

// Table
$table = new AERP_Low_Stock_Table();
$table->set_filters([
    'warehouse_id' => $warehouse_id,
    'threshold' => $threshold,
    'product_id' => $product_id,
    'manager_user_id' => $current_user->ID,
]);

$summary = AERP_Inventory_Report_Manager::get_warehouse_summary($warehouse_id, $threshold);

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
    <h2>Cảnh báo tồn kho thấp</h2>
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

<!-- Alert Settings -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Cài đặt cảnh báo</h5>
    </div>
    <div class="card-body">
        <?php if ($alert_message) {
            echo '<div class="notice notice-success alert alert-success alert-dismissible fade show" role="alert">' . esc_html($alert_message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            delete_transient('aerp_alert_message');
        } ?>
        <form method="POST" action="" class="row g-3">
            <?php wp_nonce_field('update_threshold_action', 'threshold_nonce'); ?>
            <div class="col-md-4">
                <label for="threshold" class="form-label">Ngưỡng cảnh báo tồn kho thấp</label>
                <div class="input-group">
                    <input type="number" class="form-control shadow-sm" id="threshold" name="threshold"
                        value="<?php echo esc_attr($threshold); ?>" min="0" required>
                    <span class="input-group-text">sản phẩm</span>
                </div>
                <small class="form-text text-muted">Sản phẩm có tồn kho ≤ ngưỡng này sẽ được cảnh báo</small>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" name="update_threshold" class="btn btn-primary">
                        <i class="fas fa-save"></i> Cập nhật ngưỡng
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Bộ lọc</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <input type="hidden" name="page" value="aerp-low-stock-alert">
            <div class="col-md-4">
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
            <div class="col-md-4">
                <label for="threshold_filter" class="form-label">Ngưỡng tùy chỉnh</label>
                <input type="number" class="form-control shadow-sm" id="threshold_filter" name="threshold"
                    value="<?php echo esc_attr($threshold); ?>" min="0">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <a href="?page=aerp-low-stock-alert" class="btn btn-secondary">Làm mới</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
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
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Tổng sản phẩm</h5>
                <h3 class="card-text"><?php echo number_format($summary->total_products ?? 0); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Tồn kho bình thường</h5>
                <h3 class="card-text"><?php echo number_format(($summary->total_products ?? 0) - ($summary->low_stock ?? 0) - ($summary->out_of_stock ?? 0)); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Alert Chart -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Biểu đồ phân bố tồn kho</h5>
    </div>
    <div class="card-body">
        <canvas id="stockDistributionChart" width="400" height="200"></canvas>
    </div>
</div>

<!-- Low Stock Table (Table class) -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Danh sách sản phẩm tồn kho thấp</h5>
        <div>
            <form method="post" action="<?= admin_url('admin-post.php') ?>">
                <?php wp_nonce_field('aerp_export_excel', 'aerp_export_nonce'); ?>
                <input type="hidden" name="action" value="aerp_export_excel_common">
                <input type="hidden" name="callback" value="low_stock_alert_export">
                <input type="hidden" name="threshold" value="<?= esc_attr($threshold) ?>">
                <input type="hidden" name="warehouse_id" value="<?= esc_attr($warehouse_id) ?>">
                <button type="submit" name="aerp_export_excel" class="btn btn-success">📥 Xuất Excel</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div id="aerp-low-stock-table-wrapper">
            <?php $table->render(); ?>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    jQuery(document).ready(function($) {
        // Stock Distribution Chart
        const ctx = document.getElementById('stockDistributionChart').getContext('2d');

        const chartData = {
            labels: ['Hết hàng', 'Tồn kho thấp', 'Bình thường'],
            datasets: [{
                data: [
                    <?php echo $summary->out_of_stock ?? 0; ?>,
                    <?php echo ($summary->low_stock ?? 0) - ($summary->out_of_stock ?? 0); ?>,
                    <?php echo ($summary->total_products ?? 0) - ($summary->low_stock ?? 0); ?>
                ],
                backgroundColor: ['#dc3545', '#ffc107', '#28a745'],
                borderColor: ['#dc3545', '#ffc107', '#28a745'],
                borderWidth: 1
            }]
        };

        const stockChart = new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        // Auto refresh every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000); // 5 minutes
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

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .notice {
        margin: 20px 0;
    }
</style>
<?php
$content = ob_get_clean();
$title = 'Cảnh báo tồn kho thấp';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
