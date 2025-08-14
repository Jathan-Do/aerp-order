jQuery(function($) {
    // Từ chối đơn
    $(document).on('click', '.reject-order-btn', function() {
        const orderId = $(this).data('order-id');
        const orderCode = $(this).data('order-code');
        
        const reason = prompt(`Lý do từ chối đơn ${orderCode}:`);
        
        if (reason !== null) {
            $.ajax({
                url: aerp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'aerp_reject_order',
                    order_id: orderId,
                    reason: reason,
                    _wpnonce: aerp_ajax.reject_order_nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Lỗi: ' + response.data);
                    }
                },
                error: function() {
                    alert('Có lỗi xảy ra khi từ chối đơn!');
                }
            });
        }
    });

    // Hoàn thành đơn
    $(document).on('click', '.complete-order-btn', function() {
        const orderId = $(this).data('order-id');
        const orderCode = $(this).data('order-code');
        
        if (confirm(`Xác nhận hoàn thành đơn ${orderCode}?`)) {
            $.ajax({
                url: aerp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'aerp_complete_order',
                    order_id: orderId,
                    _wpnonce: aerp_ajax.complete_order_nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Lỗi: ' + response.data);
                    }
                },
                error: function() {
                    alert('Có lỗi xảy ra khi hoàn thành đơn!');
                }
            });
        }
    });

    // Thu tiền
    $(document).on('click', '.mark-paid-btn', function() {
        const orderId = $(this).data('order-id');
        const orderCode = $(this).data('order-code');
        
        if (confirm(`Xác nhận đã thu tiền đơn ${orderCode}?`)) {
            $.ajax({
                url: aerp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'aerp_mark_paid',
                    order_id: orderId,
                    _wpnonce: aerp_ajax.mark_paid_nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Lỗi: ' + response.data);
                    }
                },
                error: function() {
                    alert('Có lỗi xảy ra khi thu tiền!');
                }
            });
        }
    });

    // Hủy đơn
    $(document).on('click', '.cancel-order-btn', function() {
        const orderId = $(this).data('order-id');
        const orderCode = $(this).data('order-code');
        
        const reason = prompt(`Lý do hủy đơn ${orderCode}:`);
        
        if (reason !== null) {
            $.ajax({
                url: aerp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'aerp_cancel_order',
                    order_id: orderId,
                    reason: reason,
                    _wpnonce: aerp_ajax.cancel_order_nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Lỗi: ' + response.data);
                    }
                },
                error: function() {
                    alert('Có lỗi xảy ra khi hủy đơn!');
                }
            });
        }
    });
});
