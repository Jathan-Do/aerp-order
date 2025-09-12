<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$current_user = wp_get_current_user();

// Filters: day/week/month/quarter/year or custom range
$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
$month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : '';
$start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

$today = date('Y-m-d');
$time_label = '';

function aerp_acc_first_day_of_quarter($date)
{
    $month = (int)date('n', strtotime($date));
    $quarter_start_month = floor(($month - 1) / 3) * 3 + 1;
    return date('Y-m-01', strtotime(date('Y', strtotime($date)) . '-' . $quarter_start_month . '-01'));
}
function aerp_acc_last_day_of_quarter($date)
{
    $start = aerp_acc_first_day_of_quarter($date);
    return date('Y-m-t', strtotime('+2 months', strtotime($start)));
}

// Resolve time window by period
$label_expr = 'DATE(tx_date)';
switch ($period) {
    case 'day':
        $start_date = $start_date ?: $today;
        $end_date = $end_date ?: $today;
        $time_label = 'Ngày ' . date('d/m/Y', strtotime($start_date));
        $group_by = 'DATE(tx_date)';
        $label_expr = 'DATE(tx_date)';
        break;
    case 'week':
        $start_date = $start_date ?: date('Y-m-d', strtotime('monday this week'));
        $end_date = $end_date ?: date('Y-m-d', strtotime('sunday this week'));
        $time_label = 'Tuần ' . date('d/m', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date));
        $group_by = 'DATE(tx_date)';
        $label_expr = 'DATE(tx_date)';
        break;
    case 'month':
        $m = $month ?: date('Y-m');
        $start_date = date('Y-m-01', strtotime($m));
        $end_date = date('Y-m-t', strtotime($m));
        $time_label = 'Tháng ' . date('m/Y', strtotime($start_date));
        $group_by = 'DATE(tx_date)';
        $label_expr = 'DATE(tx_date)';
        break;
    case 'quarter':
        $ref = $start_date ?: $today;
        $start_date = aerp_acc_first_day_of_quarter($ref);
        $end_date = aerp_acc_last_day_of_quarter($ref);
        $q = ceil(date('n', strtotime($start_date)) / 3);
        $time_label = 'Quý ' . $q . ' năm ' . date('Y', strtotime($start_date));
        $group_by = 'DATE_FORMAT(tx_date, "%Y-%m")';
        $label_expr = 'DATE_FORMAT(tx_date, "%Y-%m")';
        break;
    case 'year':
        $y = date('Y');
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        $time_label = 'Năm ' . $y;
        $group_by = 'DATE_FORMAT(tx_date, "%Y-%m")';
        $label_expr = 'DATE_FORMAT(tx_date, "%Y-%m")';
        break;
    case 'last12':
        // Rolling last 12 full months including current month
        $start_date = date('Y-m-01', strtotime('-11 months'));
        $end_date = date('Y-m-t');
        $time_label = '12 tháng gần nhất (' . date('m/Y', strtotime($start_date)) . ' - ' . date('m/Y', strtotime($end_date)) . ')';
        $group_by = 'DATE_FORMAT(tx_date, "%Y-%m")';
        $label_expr = 'DATE_FORMAT(tx_date, "%Y-%m")';
        break;
    default:
        $start_date = $start_date ?: date('Y-m-01');
        $end_date = $end_date ?: $today;
        $time_label = 'Từ ' . date('d/m/Y', strtotime($start_date)) . ' đến ' . date('d/m/Y', strtotime($end_date));
        $group_by = 'DATE(tx_date)';
        $label_expr = 'DATE(tx_date)';
}

// Queries: receipts (approved), payments (confirmed/paid)
$receipts_total = (float)$wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(total_amount),0) FROM {$wpdb->prefix}aerp_acc_receipts
     WHERE status IN ('approved') AND receipt_date BETWEEN %s AND %s",
    $start_date,
    $end_date
));

$payments_total = (float)$wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(total_amount),0) FROM {$wpdb->prefix}aerp_acc_payments
     WHERE status IN ('confirmed','paid') AND payment_date BETWEEN %s AND %s",
    $start_date,
    $end_date
));

$profit = $receipts_total - $payments_total;

// Time series for chart
$tx_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT $label_expr AS label,
            SUM(receipts_amount) AS receipts_amount,
            SUM(payments_amount) AS payments_amount,
            SUM(receipts_count) AS receipts_count,
            SUM(payments_count) AS payments_count
     FROM (
         SELECT receipt_date AS tx_date,
                total_amount AS receipts_amount,
                0 AS payments_amount,
                1 AS receipts_count,
                0 AS payments_count
         FROM {$wpdb->prefix}aerp_acc_receipts
         WHERE status='approved' AND receipt_date BETWEEN %s AND %s
         UNION ALL
         SELECT payment_date AS tx_date,
                0 AS receipts_amount,
                total_amount AS payments_amount,
                0 AS receipts_count,
                1 AS payments_count
         FROM {$wpdb->prefix}aerp_acc_payments
         WHERE status IN ('confirmed','paid') AND payment_date BETWEEN %s AND %s
     ) t
     GROUP BY label
     ORDER BY label ASC",
    $start_date,
    $end_date,
    $start_date,
    $end_date
), ARRAY_A);

$labels = array_column($tx_rows, 'label');
$series_receipts_amount = array_map('floatval', array_column($tx_rows, 'receipts_amount'));
$series_payments_amount = array_map('floatval', array_column($tx_rows, 'payments_amount'));
$series_receipts_count = array_map('intval', array_column($tx_rows, 'receipts_count'));
$series_payments_count = array_map('intval', array_column($tx_rows, 'payments_count'));
$series_profit_amount = array_map(function ($r) {
    $rA = floatval($r['receipts_amount'] ?? 0);
    $pA = floatval($r['payments_amount'] ?? 0);
    return $rA - $pA;
}, $tx_rows);
ob_start();
?>
<div class="d-flex flex-column-reverse flex-md-row justify-content-between align-items-md-center mb-4">
    <h2>Báo cáo kế toán (tổng hợp)</h2>
    <div class="user-info text-end">
        Welcome, <?php echo esc_html($current_user->display_name); ?>
        <a href="<?php echo wp_logout_url(site_url('/aerp-dang-nhap')); ?>" class="btn btn-sm btn-outline-danger ms-2">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>
<div class="container-fluid py-3">
    <h4 class="mb-3">Báo cáo kế toán (tổng hợp)</h4>
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Chu kỳ</label>
                    <select name="period" class="form-select shadow-sm">
                        <option value="day" <?php selected($period, 'day'); ?>>Ngày</option>
                        <option value="week" <?php selected($period, 'week'); ?>>Tuần</option>
                        <option value="month" <?php selected($period, 'month'); ?>>Tháng</option>
                        <option value="quarter" <?php selected($period, 'quarter'); ?>>Quý</option>
                        <option value="year" <?php selected($period, 'year'); ?>>Năm</option>
                        <option value="last12" <?php selected($period, 'last12'); ?>>12 tháng gần nhất</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tháng</label>
                    <input type="month" name="month" class="form-control shadow-sm" value="<?php echo esc_attr($month); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Từ ngày</label>
                    <input type="date" name="start_date" class="form-control shadow-sm" value="<?php echo esc_attr($start_date); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Đến ngày</label>
                    <input type="date" name="end_date" class="form-control shadow-sm" value="<?php echo esc_attr($end_date); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary me-2" type="submit">Lọc</button>
                    <a href="?" class="btn btn-outline-secondary">Xóa lọc</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="summary-card card">
                <div class="summary-content p-3">
                    <div class="summary-label">Tổng thu</div>
                    <div class="summary-value text-success"><?php echo number_format($receipts_total, 0, ',', '.'); ?> đ</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card card">
                <div class="summary-content p-3">
                    <div class="summary-label">Tổng chi</div>
                    <div class="summary-value text-danger"><?php echo number_format($payments_total, 0, ',', '.'); ?> đ</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card card">
                <div class="summary-content p-3">
                    <div class="summary-label">Lợi nhuận</div>
                    <div class="summary-value <?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($profit, 0, ',', '.'); ?> đ</div>
                </div>
            </div>
        </div>
    </div>

    <div class="chart-container card">
        <h5><i class="fas fa-chart-bar"></i> Phiếu thu theo thời gian (<?php echo esc_html($time_label); ?>)</h5>
        <?php if (empty($tx_rows)): ?>
            <div class="no-data">Không có dữ liệu</div>
        <?php else: ?>
            <canvas id="receiptChart"></canvas>
        <?php endif; ?>
    </div>

    <div class="chart-container card">
        <h5><i class="fas fa-chart-bar"></i> Phiếu chi theo thời gian (<?php echo esc_html($time_label); ?>)</h5>
        <?php if (empty($tx_rows)): ?>
            <div class="no-data">Không có dữ liệu</div>
        <?php else: ?>
            <canvas id="paymentChart"></canvas>
        <?php endif; ?>
    </div>

    <div class="chart-container card">
        <h5><i class="fas fa-chart-line"></i> Lợi nhuận theo thời gian (<?php echo esc_html($time_label); ?>)</h5>
        <?php if (empty($tx_rows)): ?>
            <div class="no-data">Không có dữ liệu</div>
        <?php else: ?>
            <canvas id="profitChart"></canvas>
        <?php endif; ?>
    </div>
</div>

<style>
    .chart-container {
        min-height: 300px;
        max-width: 100%;
        margin-bottom: 24px;
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solidrgb(205, 205, 206);
        position: relative;
    }

    .chart-container h5 {
        margin-bottom: 20px;
        color: #495057;
        font-weight: 600;
    }

    .chart-container h5 i {
        margin-right: 8px;
        color: #007bff;
    }

    .chart-container canvas {
        max-width: 100% !important;
        max-height: 250px !important;
    }

    .summary-content {
        text-align: center;
    }

    .summary-label {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 8px;
        font-weight: 500;
    }

    .summary-value {
        font-size: 1.8rem;
        font-weight: bold;
        color: #212529;
    }

    @media (max-width: 768px) {
        .chart-container {
            min-height: 250px;
        }
    }
</style>

<script>
    jQuery(function($) {
        var labels = <?php echo json_encode($labels); ?>;
        var receiptsCount = <?php echo json_encode($series_receipts_count); ?>;
        var paymentsCount = <?php echo json_encode($series_payments_count); ?>;
        var receiptsAmount = <?php echo json_encode($series_receipts_amount); ?>;
        var paymentsAmount = <?php echo json_encode($series_payments_amount); ?>;
        var profitAmount = <?php echo json_encode($series_profit_amount); ?>;

        function renderReceiptChart() {
            if (!$('#receiptChart').length || labels.length === 0) return;
            if (typeof Chart === 'undefined') {
                // Chart.js chưa sẵn sàng, thử lại sau một chút
                return setTimeout(renderReceiptChart, 100);
            }
            if (typeof ChartDataLabels !== 'undefined') {
                Chart.register(ChartDataLabels);
            }
            new Chart(document.getElementById('receiptChart'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Số phiếu thu',
                            type: 'bar',
                            data: receiptsCount,
                            backgroundColor: 'rgba(54, 162, 235, 0.5)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Tiền Thu (đ)',
                            type: 'line',
                            data: receiptsAmount,
                            backgroundColor: 'rgba(25, 135, 84, 0.2)',
                            borderColor: '#198754',
                            borderWidth: 3,
                            pointRadius: 5,
                            yAxisID: 'y1',
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left'
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('vi-VN', {
                                        style: 'currency',
                                        currency: 'VND',
                                        maximumFractionDigits: 0
                                    }).format(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            position: 'nearest', // đảm bảo tooltip luôn nằm gần điểm hover
                            yAlign: 'center', // căn giữa theo trục y
                            xAlign: 'right', // HIỂN THỊ TOOLTIP BÊN PHẢI
                            callbacks: {
                                label: function(context) {
                                    var val = context.raw || 0;
                                    if (context.dataset.yAxisID === 'y1') {
                                        return context.dataset.label + ': ' + new Intl.NumberFormat('vi-VN', {
                                            style: 'currency',
                                            currency: 'VND',
                                            maximumFractionDigits: 0
                                        }).format(val);
                                    }
                                    return context.dataset.label + ': ' + val;
                                }
                            }
                        },
                        datalabels: {
                            color: '#000',
                            font: {
                                weight: 'bold',
                                size: 12
                            },
                            anchor: function(ctx) {
                                return ctx.dataset.yAxisID === 'y1' ? 'right' : 'right';
                            },
                            align: function(ctx) {
                                return ctx.dataset.yAxisID === 'y1' ? 'top' : 'top';
                            },
                            offset: 0,
                            formatter: function(value, context) {
                                if (!value || value === 0) return '';
                                var ds = context?.dataset;
                                // Nếu là trục tiền thì format VND
                                if (ds && ds.yAxisID === 'y1') {
                                    return new Intl.NumberFormat('vi-VN', {
                                        style: 'currency',
                                        currency: 'VND',
                                        maximumFractionDigits: 0
                                    }).format(value);
                                }
                                return value;
                            }
                        }
                    }
                }
            });
        }

        function renderPaymentChart() {
            if (!$('#paymentChart').length || labels.length === 0) return;
            if (typeof Chart === 'undefined') return setTimeout(renderPaymentChart, 100);
            if (typeof ChartDataLabels !== 'undefined') Chart.register(ChartDataLabels);
            new Chart(document.getElementById('paymentChart'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Số phiếu chi',
                            type: 'bar',
                            data: paymentsCount,
                            backgroundColor: 'rgba(255, 159, 64, 0.5)',
                            borderColor: 'rgba(255, 159, 64, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Tiền Chi (đ)',
                            type: 'line',
                            data: paymentsAmount,
                            backgroundColor: 'rgba(220, 53, 69, 0.2)',
                            borderColor: '#dc3545',
                            borderWidth: 3,
                            pointRadius: 5,
                            yAxisID: 'y1',
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left'
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('vi-VN', {
                                        style: 'currency',
                                        currency: 'VND',
                                        maximumFractionDigits: 0
                                    }).format(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            position: 'nearest', // đảm bảo tooltip luôn nằm gần điểm hover
                            yAlign: 'center', // căn giữa theo trục y
                            xAlign: 'right', // HIỂN THỊ TOOLTIP BÊN PHẢI
                            callbacks: {
                                label: function(context) {
                                    var val = context.raw || 0;
                                    if (context.dataset.yAxisID === 'y1') {
                                        return context.dataset.label + ': ' + new Intl.NumberFormat('vi-VN', {
                                            style: 'currency',
                                            currency: 'VND',
                                            maximumFractionDigits: 0
                                        }).format(val);
                                    }
                                    return context.dataset.label + ': ' + val;
                                }
                            }
                        },
                        datalabels: {
                            color: '#000',
                            font: {
                                weight: 'bold',
                                size: 12
                            },
                            anchor: function(ctx) {
                                return ctx.dataset.yAxisID === 'y1' ? 'right' : 'right';
                            },
                            align: function(ctx) {
                                return ctx.dataset.yAxisID === 'y1' ? 'top' : 'top';
                            },
                            offset: 0,
                            formatter: function(value, ctx) {
                                if (!value || value === 0) return '';
                                var ds = ctx.chart.data.datasets[ctx.datasetIndex];
                                if (ds && ds.yAxisID === 'y1') return new Intl.NumberFormat('vi-VN', {
                                    maximumFractionDigits: 0
                                }).format(value) + ' đ';
                                return value;
                            }
                        }
                    }
                }
            });
        }

        function renderProfitChart() {
            if (!$('#profitChart').length || labels.length === 0) return;
            if (typeof Chart === 'undefined') return setTimeout(renderProfitChart, 100);
            if (typeof ChartDataLabels !== 'undefined') Chart.register(ChartDataLabels);
            new Chart(document.getElementById('profitChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Lợi nhuận (đ)',
                        data: profitAmount,
                        backgroundColor: 'rgba(13, 202, 240, 0.2)',
                        borderColor: '#0dcaf0',
                        borderWidth: 3,
                        pointRadius: 5,
                        yAxisID: 'y1',
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y1: {
                            beginAtZero: true,
                            position: 'left',
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('vi-VN', {
                                        style: 'currency',
                                        currency: 'VND',
                                        maximumFractionDigits: 0
                                    }).format(value);
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            position: 'nearest', // đảm bảo tooltip luôn nằm gần điểm hover
                            yAlign: 'center', // căn giữa theo trục y
                            xAlign: 'right', // HIỂN THỊ TOOLTIP BÊN PHẢI
                            callbacks: {
                                label: function(context) {
                                    var val = context.raw || 0;
                                    if (context.dataset.yAxisID === 'y1') {
                                        return context.dataset.label + ': ' + new Intl.NumberFormat('vi-VN', {
                                            style: 'currency',
                                            currency: 'VND',
                                            maximumFractionDigits: 0
                                        }).format(val);
                                    }
                                    return context.dataset.label + ': ' + val;
                                }
                            }
                        },
                        datalabels: {
                            color: '#000',
                            font: {
                                weight: 'bold',
                                size: 12
                            },
                            anchor: 'end',
                            align: 'top',
                            offset: -5,
                            formatter: function(value) {
                                return value ? new Intl.NumberFormat('vi-VN', {
                                    maximumFractionDigits: 0
                                }).format(value) + ' đ' : '';
                            }
                        }
                    }
                }
            });
        }

        renderReceiptChart();
        renderPaymentChart();
        renderProfitChart();
    });
</script>


<?php
$content = ob_get_clean();
$title = 'Báo cáo kế toán (tổng hợp)';
include(AERP_HRM_PATH . 'frontend/dashboard/layout.php');
