jQuery(document).ready(function ($) {
    // Handle delete attachment
    $("#existing-attachments-container").on("click", ".delete-attachment", function () {
        const attachmentId = $(this).data("attachment-id");
        const $attachmentDiv = $(this).closest(".d-flex");

        if (confirm("Bạn có chắc chắn muốn xóa file đính kèm này không?")) {
            $.ajax({
                url: aerp_order_ajax.ajaxurl,
                type: "POST",
                data: {
                    action: "aerp_delete_order_attachment",
                    attachment_id: attachmentId,
                    _wpnonce: aerp_order_ajax._wpnonce_delete_attachment,
                },
                success: function (response) {
                    if (response.success) {
                        $attachmentDiv.remove();
                        alert(response.data);
                    } else {
                        alert("Lỗi: " + response.data);
                    }
                },
                error: function () {
                    alert("Đã xảy ra lỗi khi xóa file.");
                },
            });
        }
    });
});

// Handle add order item by select2
(function ($) {
    let itemIndex = $("#order-items-container .order-item-row").length;

    function renderOrderItemRow(idx) {
        return `<div class="row mb-2 order-item-row">
            <div class="col-md-3 mb-2">
                <input type="text" class="form-control product-name-input" name="order_items[${idx}][product_name]" placeholder="Tên sản phẩm/dịch vụ" required style="display:none">
                <select class="form-select product-select" name="order_items[${idx}][product_id]" style="display:none;width:100%"></select>
                <input type="hidden" name="order_items[${idx}][product_id]" class="product-id-input">
            </div>
            <div class="col-md-3 mb-2 d-flex align-items-center">
                <input type="number" class="form-control" name="order_items[${idx}][quantity]" placeholder="Số lượng" min="0.01" step="0.01" required>
                <span class="unit-label ms-2"></span>
                <input type="hidden" name="order_items[${idx}][unit_name]" class="unit-name-input">
            </div>
            <div class="col-md-1 mb-2">
                <input type="number" class="form-control" name="order_items[${idx}][vat_percent]" placeholder="VAT (%)" min="0" max="100" step="0.01">
            </div>
            <div class="col-md-2 mb-2"><input type="number" class="form-control" name="order_items[${idx}][unit_price]" placeholder="Đơn giá" min="0" step="0.01" required></div>
            <div class="col-md-2 mb-2"><input type="text" class="form-control total-price-field" placeholder="Thành tiền" readonly></div>
            <div class="col-md-1 mb-2"><button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button></div>
        </div>`;
    }
    $("#add-order-item")
        .off("click")
        .on("click", function () {
            $("#order-items-container").append(renderOrderItemRow(itemIndex));
            itemIndex++;
            initSelect2();
            toggleProductInput();
        });
    $(document).on("click", ".remove-order-item", function () {
        $(this).closest(".order-item-row").remove();
    });
    $(document).on(
        "input",
        'input[name*="[quantity]"], input[name*="[unit_price]"], input[name*="[vat_percent]"], input[name*="[product_name]"]',
        function () {
            let row = $(this).closest(".order-item-row");
            let qty = parseFloat(row.find('input[name*="[quantity]"]').val().replace(",", ".")) || 0;
            let price = parseFloat(row.find('input[name*="[unit_price]"]').val().replace(",", ".")) || 0;
            let vat = parseFloat(row.find('input[name*="[vat_percent]"]').val().replace(",", ".")) || 0;
            let total = qty * price;
            if (vat > 0) {
                total = total + (total * vat) / 100;
            }
            row.find(".total-price-field").val(total.toLocaleString("vi-VN"));
        }
    );

    function toggleProductInput() {
        let type = $("#order_type").val();
        $("#order-items-container .order-item-row").each(function () {
            let $nameInput = $(this).find(".product-name-input");
            let $select = $(this).find(".product-select");
            let $hiddenProductId = $(this).find(".product-id-input");
            if (type === "product") {
                $nameInput.hide();
                $select.show();
                if (!$select.hasClass("select2-hidden-accessible")) {
                    window.initAerpProductSelect2($select);
                }
            } else {
                $nameInput.show();
                if ($select.hasClass("select2-hidden-accessible")) {
                    $select.select2("destroy");
                }
                $select.hide();
            }
        });
    }
    $("#order_type").on("change", function () {
        toggleProductInput();
    });

    function initSelect2() {
        $(".product-select").each(function () {
            window.initAerpProductSelect2(this);
        });
        $(".product-select").on("select2:select", function (e) {
            let data = e.params.data;
            let row = $(this).closest(".order-item-row");
            row.find('input[name*="[product_name]"]').val(data.text);
            row.find('input[name*="[unit_price]"]').val(data.price);
            row.find(".unit-label").text(data.unit_name || "");
            row.find(".unit-name-input").val(data.unit_name || "");
            row.find(".product-id-input").val(data.id || "");
        });
    }
    $(document).ready(function () {
        initSelect2();
        toggleProductInput();
        // Select2 cho khách hàng
        $(".customer-select").select2({
            placeholder: "Chọn khách hàng",
            allowClear: true,
            ajax: {
                url: aerp_order_ajax.ajaxurl,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        action: "aerp_order_search_customers",
                        q: params.term,
                    };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true,
            },
            minimumInputLength: 0,
        });
        // Select2 cho nhân viên
        $(".employee-select").select2({
            placeholder: "Chọn nhân viên",
            allowClear: true,
            ajax: {
                url: aerp_order_ajax.ajaxurl,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        action: "aerp_order_search_employees",
                        q: params.term,
                    };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true,
            },
            minimumInputLength: 0,
        });
        // Select2 cho kho
        $(".warehouse-select").select2({
            placeholder: "Chọn kho",
            allowClear: true,
            ajax: {
                url: aerp_order_ajax.ajaxurl,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        action: "aerp_order_search_warehouses",
                        q: params.term,
                    };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true,
            },
            minimumInputLength: 0,
        });
        // Select2 cho nhà cung cấp
        $(".supplier-select").select2({
            placeholder: "Chọn nhà cung cấp",
            allowClear: true,
            ajax: {
                url: aerp_order_ajax.ajaxurl,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        action: "aerp_order_search_suppliers",
                        q: params.term,
                    };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true,
            },
            minimumInputLength: 0,
        });
    });
})(jQuery);

window.initAerpProductSelect2 = function (selector, options = {}) {
    jQuery(selector).select2(
        Object.assign(
            {
                placeholder: "Chọn sản phẩm kho",
                allowClear: true,
                ajax: {
                    url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                    dataType: "json",
                    delay: 250,
                    data: function (params) {
                        return {
                            action: "aerp_order_search_products",
                            q: params.term,
                        };
                    },
                    processResults: function (data) {
                        return { results: data };
                    },
                    cache: true,
                },
                minimumInputLength: 0,
            },
            options
        )
    );
};
