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
            <div class="col-md-2 mb-2">
                <select class="form-select item-type-select" name="order_items[${idx}][item_type]">
                    <option value="product">Sản phẩm</option>
                    <option value="service">Dịch vụ</option>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <input type="text" class="form-control product-name-input" name="order_items[${idx}][product_name]" placeholder="Tên sản phẩm/dịch vụ" required style="display:none">
                <select class="form-select product-select-all-warehouses" name="order_items[${idx}][product_id]" style="width:100%"></select>
                <input type="hidden" name="order_items[${idx}][product_id]" class="product-id-input">
            </div>
            <div class="col-md-2 mb-2 d-flex align-items-center">
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
            toggleProductInputRow($("#order-items-container .order-item-row").last());
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

    function initSelect2() {
        $(".order-item-row").each(function () {
            let $row = $(this);
            let $select = $row.find(".product-select-all-warehouses");
            let type = $row.find(".item-type-select").val();
            if (type === "product") {
                if (!$select.hasClass("select2-hidden-accessible")) {
                    $select.select2({
                        placeholder: "Chọn sản phẩm từ tất cả kho",
                        allowClear: true,
                        ajax: {
                            url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                            dataType: "json",
                            delay: 250,
                            data: function (params) {
                                return {
                                    action: "aerp_order_search_products_in_warehouse_in_worklocation",
                                    warehouse_id: 0,
                                    q: params.term || "",
                                };
                            },
                            processResults: function (data) {
                                return { results: data };
                            },
                            cache: true,
                        },
                        minimumInputLength: 0,
                    });
                }
                $select.show();
            } else {
                if ($select.hasClass("select2-hidden-accessible")) {
                    $select.select2("destroy");
                }
                $select.hide();
            }
        });
    }
    function toggleProductInputRow($row) {
        let type = $row.find(".item-type-select").val();
        let $nameInput = $row.find(".product-name-input");
        let $select = $row.find(".product-select-all-warehouses");
        if (type === "product") {
            $nameInput.hide();
            if (!$select.hasClass("select2-hidden-accessible")) {
                $select.select2({
                    placeholder: "Chọn sản phẩm từ tất cả kho",
                    allowClear: true,
                    ajax: {
                        url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                        dataType: "json",
                        delay: 250,
                        data: function (params) {
                            return {
                                action: "aerp_order_search_products_in_warehouse_in_worklocation",
                                warehouse_id: 0,
                                q: params.term || "",
                            };
                        },
                        processResults: function (data) {
                            return { results: data };
                        },
                        cache: true,
                    },
                    minimumInputLength: 0,
                });
            }
            $select.show();
        } else {
            $nameInput.show();
            if ($select.hasClass("select2-hidden-accessible")) {
                $select.select2("destroy");
            }
            $select.hide();
            $select.val(null).trigger("change");
        }
    }
    // Sự kiện thay đổi loại từng dòng
    $(document).on("change", ".item-type-select", function () {
        let $row = $(this).closest(".order-item-row");
        toggleProductInputRow($row);
    });
    // Khi thêm dòng mới, gọi toggleProductInputRow cho dòng đó
    $("#add-order-item")
        .off("click")
        .on("click", function () {
            $("#order-items-container").append(renderOrderItemRow(itemIndex));
            let $newRow = $("#order-items-container .order-item-row").last();
            initSelect2();
            toggleProductInputRow($newRow);
            itemIndex++;
        });
    // Khi load lại form, gọi toggleProductInputRow cho tất cả dòng
    $(document).ready(function () {
        $("#order-items-container .order-item-row").each(function () {
            toggleProductInputRow($(this));
        });
    });
    $(document).ready(function () {
        initSelect2();
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
        // Select2 cho nhân viên - filter theo branch của user hiện tại
        $(".employee-select").select2({
            placeholder: "Chọn nhân viên",
            allowClear: true,
            ajax: {
                url: aerp_order_ajax.ajaxurl,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        action: "aerp_get_users_by_work_location",
                        work_location_id: 0, // Sẽ filter theo branch của user hiện tại trong backend
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
        $(".employee-select-all").select2({
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
        $(".warehouse-select-by-user").select2({
            placeholder: "Chọn kho",
            allowClear: true,
            ajax: {
                url: aerp_order_ajax.ajaxurl,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        action: "aerp_order_search_warehouses_by_user",
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
            placeholder: "Chọn nhà cung cấp/ Đối tác",
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
        $(".product-select-all").select2({
            placeholder: "Chọn sản phẩm",
            allowClear: true,
            ajax: {
                url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        action: "aerp_order_search_all_products",
                        q: params.term || "",
                    };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true,
            },
            minimumInputLength: 0,
        });
        // Select2 cho sản phẩm trong kho cụ thể (form tạo đơn hàng)
        $(".product-select-by-warehouse").select2({
            placeholder: "Chọn sản phẩm trong kho",
            allowClear: true,
            ajax: {
                url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        action: "aerp_order_search_products_in_warehouse",
                        warehouse_id: $("select[name='warehouse_id']").val(), // Lấy kho được chọn
                        q: params.term || "",
                    };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true,
            },
            minimumInputLength: 0,
        });

        // Select2 cho tất cả sản phẩm từ tất cả kho user quản lý
        $(".product-select-all-warehouses").select2({
            placeholder: "Chọn sản phẩm từ tất cả kho",
            allowClear: true,
            ajax: {
                url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        action: "aerp_order_search_products_in_warehouse_in_worklocation",
                        warehouse_id: 0, // Tìm trong tất cả kho user quản lý
                        q: params.term || "",
                    };
                },
                processResults: function (data) {
                    return { results: data };
                },
                cache: true,
            },
            minimumInputLength: 0,
        });

        // Reload product dropdown khi warehouse thay đổi (chỉ cho form tạo đơn hàng)
        $("select[name='warehouse_id']").on("change", function () {
            $(".product-select-by-warehouse").each(function () {
                if ($(this).hasClass("select2-hidden-accessible")) {
                    $(this).val(null).trigger("change");
                }
            });
        });
        // Gọi toggleProductInputRow cho tất cả dòng để ẩn select2 nếu là dịch vụ
        $("#order-items-container .order-item-row").each(function () {
            toggleProductInputRow($(this));
        });
    });
    // Đảm bảo luôn gắn sự kiện select2:select sau khi khởi tạo select2
    $(document).on("select2:select", ".product-select-all-warehouses", function (e) {
        let data = e.params.data;
        let row = $(this).closest(".order-item-row");
        row.find('input[name*="[product_name]"]').val(data.text);
        row.find('input[name*="[unit_price]"]').val(data.price);
        row.find(".unit-label").text(data.unit_name || "");
        row.find(".unit-name-input").val(data.unit_name || "");
        row.find(".product-id-input").val(data.id || "");
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

// ========== AERP WAREHOUSE FORM: Load user theo chi nhánh ==========
(function ($) {
    function initUserSelect(workLocationId = 0, selectedUserIds = []) {
        var $userSelect = $(".user-select");
        // Destroy select2 nếu đã được init trước đó
        if ($userSelect.hasClass("select2-hidden-accessible")) {
            $userSelect.select2("destroy");
        }
        $userSelect.val(null).trigger("change");
        $userSelect.select2({
            placeholder: "Chọn người quản lý kho",
            allowClear: true,
            multiple: true,
            width: "100%",
            ajax: {
                url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : ajaxurl,
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        action: "aerp_order_search_employees",
                        work_location_id: workLocationId,
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
        // Nếu có user đã chọn (khi edit), load option vào select2
        if (selectedUserIds.length > 0) {
            $.each(selectedUserIds, function (i, user) {
                var option = new Option(user.text, user.id, true, true);
                $userSelect.append(option).trigger("change");
            });
        }
    }
    $(document).ready(function () {
        var $workLocationSelect = $(".work-location-select");
        var $userSelect = $(".user-select");
        var selectedUsers = window.selectedWarehouseManagers || [];
        // Lấy work_location_id đã chọn (nếu có)
        var initialWorkLocationId = $workLocationSelect.val() || 0;
        initUserSelect(initialWorkLocationId, selectedUsers);
        $workLocationSelect.on("change", function () {
            var workLocationId = $(this).val();
            initUserSelect(workLocationId, []);
        });
    });
})(jQuery);
