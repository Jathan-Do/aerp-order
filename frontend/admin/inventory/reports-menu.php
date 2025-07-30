<?php
if (!defined('ABSPATH')) exit;

// Get current page for active menu highlighting
$current_page = '';
if (strpos($_SERVER['REQUEST_URI'], 'aerp-stock-timeline') !== false) {
    $current_page = 'stock_timeline';
} elseif (strpos($_SERVER['REQUEST_URI'], 'aerp-movement-report') !== false) {
    $current_page = 'movement_report';
} elseif (strpos($_SERVER['REQUEST_URI'], 'aerp-low-stock-alert') !== false) {
    $current_page = 'low_stock_alert';
}

?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-bar"></i> Báo cáo & Cảnh báo kho
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <a href="<?php echo home_url('/aerp-stock-timeline'); ?>" 
                   class="btn btn-outline-primary w-100 <?php echo $current_page === 'stock_timeline' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <div class="mt-2">
                        <strong>Báo cáo tồn kho</strong>
                        <br>
                        <small class="">Theo dõi tồn kho theo thời gian</small>
                    </div>
                </a>
            </div>
            
            <div class="col-md-4 mb-3">
                <a href="<?php echo home_url('/aerp-movement-report'); ?>" 
                   class="btn btn-outline-success w-100 <?php echo $current_page === 'movement_report' ? 'active' : ''; ?>">
                    <i class="fas fa-exchange-alt"></i>
                    <div class="mt-2">
                        <strong>Báo cáo chuyển động</strong>
                        <br>
                        <small class="">Nhập xuất kho theo thời gian</small>
                    </div>
                </a>
            </div>
            
            <div class="col-md-4 mb-3">
                <a href="<?php echo home_url('/aerp-low-stock-alert'); ?>" 
                   class="btn btn-outline-warning w-100 <?php echo $current_page === 'low_stock_alert' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="mt-2">
                        <strong>Cảnh báo tồn kho thấp</strong>
                        <br>
                        <small class="">Quản lý cảnh báo tự động</small>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.btn-outline-primary.active,
.btn-outline-success.active,
.btn-outline-warning.active {
    color: white;
}

.btn-outline-primary.active {
    background-color: #007bff;
    border-color: #007bff;
}

.btn-outline-success.active {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-outline-warning.active {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #212529;
}

.btn {
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style> 