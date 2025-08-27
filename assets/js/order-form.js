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
                <label class="form-label">Loại</label>
                <select class="form-select shadow-sm item-type-select" name="order_items[${idx}][item_type]">
                    <option value="product">Sản phẩm</option>
                    <option value="service">Dịch vụ</option>
                </select>
            </div>
            <div class="col-md-2 mb-2 purchase-type-container">
                <label class="form-label">Nguồn mua</label>
                <select class="form-select shadow-sm purchase-type-select" name="order_items[${idx}][purchase_type]">
                    <option value="warehouse">Từ kho</option>
                    <option value="external">Mua ngoài</option>
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Sản phẩm trong đơn</label>
                <input type="text" class="form-control shadow-sm product-name-input" name="order_items[${idx}][product_name]" placeholder="Tên sản phẩm/dịch vụ" style="display:none">
                <select class="form-select shadow-sm product-select-all-warehouses" name="order_items[${idx}][product_id]" style="width:100%"></select>
                <input type="hidden" name="order_items[${idx}][product_id]" class="product-id-input">
            </div>
            <div class="col-md-2 mb-2 d-flex align-items-end">
                <div class="w-100">
                    <label class="form-label">Số lượng</label>
                    <input type="number" class="form-control shadow-sm" name="order_items[${idx}][quantity]" placeholder="Số lượng" min="0" step="0.01" >
                </div>
                <span class="unit-label ms-2"></span>
                <input type="hidden" name="order_items[${idx}][unit_name]" class="unit-name-input">
            </div>
            <div class="col-md-1 mb-2 vat-percent" style="display:none;">
                <label class="form-label">VAT %</label>
                <input type="number" class="form-control shadow-sm" name="order_items[${idx}][vat_percent]" placeholder="VAT (%)" min="0" max="100" step="0.01" value="0">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Đơn giá</label>
                <input type="number" class="form-control shadow-sm" name="order_items[${idx}][unit_price]" placeholder="Đơn giá" min="0" step="0.01" >
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Thành tiền</label>
                <input type="text" class="form-control shadow-sm total-price-field" placeholder="Thành tiền" readonly>
            </div>
            
            <!-- External purchase fields (hidden by default) -->
            <div class="col-md-2 mb-2 external-fields" style="display:none;">
                <label class="form-label">Nhà cung cấp</label>
                <input type="text" class="form-control shadow-sm external-supplier-input" name="order_items[${idx}][external_supplier_name]" placeholder="Tên nhà cung cấp">
            </div>
            <div class="col-md-2 mb-2 external-fields" style="display:none;">
                <label class="form-label">Chi phí mua ngoài</label>
                <input type="number" class="form-control shadow-sm external-cost-input" name="order_items[${idx}][external_cost]" placeholder="Chi phí" min="0" step="0.01">
            </div>
            <div class="col-md-1 mb-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-danger remove-order-item">Xóa</button>
            </div>
        </div>`;
    }
    $("#add-order-item")
        .off("click")
        .on("click", function () {
            $("#order-items-container").append(renderOrderItemRow(itemIndex));
            itemIndex++;

            // Khởi tạo Select2 cho dòng mới
            let $newRow = $("#order-items-container .order-item-row").last();
            let $productSelect = $newRow.find(".product-select-all-warehouses");
            let $productNameInput = $newRow.find(".product-name-input");
            let $purchaseTypeContainer = $newRow.find(".purchase-type-container");
            let $externalFields = $newRow.find(".external-fields");

            // Áp dụng logic hiển thị ban đầu cho dòng mới
            // Dòng mới mặc định: item_type = 'product', purchase_type = 'warehouse'
            $purchaseTypeContainer.show();
            $productNameInput.hide();
            $productSelect.show();
            $externalFields.hide();

            // Khởi tạo Select2 cho sản phẩm (vì mặc định là warehouse)
            $productSelect.select2({
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

            // Event handler for item type change for the new row
            $newRow.find(".item-type-select").on("change", function () {
                let itemType = $(this).val();
                let $purchaseTypeContainer = $newRow.find(".purchase-type-container");

                if (itemType === "service") {
                    // Ẩn trường nguồn mua cho dịch vụ
                    $purchaseTypeContainer.hide();

                    // Hiển thị input text, ẩn select2
                    $productSelect.hide();
                    $productNameInput.show();

                    if ($productSelect.hasClass("select2-hidden-accessible")) {
                        $productSelect.select2("destroy");
                    }
                    $productSelect.val(null).trigger("change");
                    $productNameInput.val("");

                    // Ẩn external fields nếu đang hiển thị
                    $newRow.find(".external-fields").hide();
                } else {
                    // Hiển thị trường nguồn mua cho sản phẩm
                    $purchaseTypeContainer.show();

                    // Hiển thị select2, ẩn input text
                    $productSelect.show();
                    $productNameInput.hide();

                    if (!$productSelect.hasClass("select2-hidden-accessible")) {
                        $productSelect.select2({
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

                    $productNameInput.val("");
                }
            });

            // Event handler for purchase type change for the new row
            $newRow.find(".purchase-type-select").on("change", function () {
                let purchaseType = $(this).val();
                let $externalFields = $newRow.find(".external-fields");

                if (purchaseType === "external") {
                    // Hiển thị external fields
                    $externalFields.show();

                    // Chuyển sang input text thủ công
                    $productNameInput.show();
                    $productSelect.hide();

                    if ($productSelect.hasClass("select2-hidden-accessible")) {
                        $productSelect.select2("destroy");
                    }
                    $productSelect.next(".select2").hide();

                    // Xóa giá trị đã chọn
                    $productSelect.val(null).trigger("change");
                    $newRow.find(".product-id-input").val("");
                    $newRow.find(".unit-label").text("");
                    $newRow.find(".unit-name-input").val("");
                } else {
                    // Ẩn external fields
                    $externalFields.hide();

                    // Chuyển về select2
                    $productNameInput.hide();
                    $productSelect.show();

                    if (!$productSelect.hasClass("select2-hidden-accessible")) {
                        $productSelect.select2({
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
                    } else {
                        $productSelect.next(".select2").show();
                    }

                    // Xóa giá trị input text
                    $productNameInput.val("");
                }
            });

            // Áp dụng trạng thái checkbox VAT toàn cục cho dòng mới
            var isVatOn = $("#toggle-vat").is(":checked");
            if (isVatOn) {
                $newRow.find(".vat-percent").css("display", "block");
            } else {
                $newRow.find(".vat-percent").css("display", "none");
                $newRow.find('input[name*="[vat_percent]"]').val(0).trigger("input");
            }
        });
    $(document).on("click", ".remove-order-item", function () {
        // Xóa toàn bộ dòng sản phẩm (bao gồm cả external-fields)
        $(this).closest(".order-item-row").remove();
    });
    $(document).on(
        "input",
        'input[name*="[quantity]"], input[name*="[unit_price]"], input[name*="[vat_percent]"], input[name*="[product_name]"]',
        function () {
            let row = $(this).closest(".order-item-row");
            let qtyVal = row.find('input[name*="[quantity]"]').val();
            let priceVal = row.find('input[name*="[unit_price]"]').val();
            let vatVal = row.find('input[name*="[vat_percent]"]').val();

            let qty = parseFloat(qtyVal ? qtyVal.replace(",", ".") : "0") || 0;
            let price = parseFloat(priceVal ? priceVal.replace(",", ".") : "0") || 0;
            let vat = parseFloat(vatVal ? vatVal.replace(",", ".") : "0") || 0;

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
            let itemType = $row.find(".item-type-select").val();
            let purchaseType = $row.find(".purchase-type-select").val();

            // Chỉ khởi tạo select2 khi item_type = 'product' VÀ purchase_type = 'warehouse'
            if (itemType === "product" && purchaseType === "warehouse") {
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
                // Nếu không phải product + warehouse, ẩn select2
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
            // Hiển thị select2, ẩn input text
            $nameInput.hide();
            $select.show();

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
            } else {
                // Nếu đã init rồi thì đảm bảo container hiển thị
                $select.next(".select2").show();
            }
        } else {
            // Hiển thị input text, ẩn select2
            $nameInput.show();
            $select.hide();

            if ($select.hasClass("select2-hidden-accessible")) {
                $select.select2("destroy");
            } else {
                // Nếu đã có container select2 (trường hợp init trước), ẩn container
                $select.next(".select2").hide();
            }
            $select.val(null).trigger("change");
            $nameInput.val("");
        }
    }
    // Sự kiện thay đổi loại từng dòng
    $(document).on("change", ".item-type-select", function () {
        let $row = $(this).closest(".order-item-row");
        let itemType = $(this).val();
        let $nameInput = $row.find(".product-name-input");
        let $select = $row.find(".product-select-all-warehouses");
        let $purchaseTypeContainer = $row.find(".purchase-type-container");

        if (itemType === "service") {
            // Ẩn trường nguồn mua cho dịch vụ
            $purchaseTypeContainer.hide();

            // Hiển thị input text, ẩn select2
            $nameInput.show();
            $select.hide();

            if ($select.hasClass("select2-hidden-accessible")) {
                $select.select2("destroy");
            }
            $select.val(null).trigger("change");
            $nameInput.val("");
            // Ẩn container select2 nếu còn
            $select.next(".select2").hide();

            // Ẩn external fields nếu đang hiển thị
            $row.find(".external-fields").hide();
        } else {
            // Hiển thị trường nguồn mua cho sản phẩm
            $purchaseTypeContainer.show();

            // Kiểm tra purchase_type để quyết định hiển thị gì
            let purchaseType = $row.find(".purchase-type-select").val();

            if (purchaseType === "external") {
                // Nếu là mua ngoài, hiển thị input text
                $nameInput.show();
                $select.hide();

                if ($select.hasClass("select2-hidden-accessible")) {
                    $select.select2("destroy");
                }
                $select.next(".select2").hide();

                // Hiển thị external fields
                $row.find(".external-fields").show();
            } else {
                // Nếu là từ kho, hiển thị select2
                $nameInput.hide();
                $select.show();

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
                } else {
                    $select.next(".select2").show();
                }

                // Ẩn external fields
                $row.find(".external-fields").hide();
            }
        }
    });

    // Xử lý thay đổi nguồn mua
    $(document).on("change", ".purchase-type-select", function () {
        let $row = $(this).closest(".order-item-row");
        let purchaseType = $(this).val();
        let $externalFields = $row.find(".external-fields");
        let $nameInput = $row.find(".product-name-input");
        let $select = $row.find(".product-select-all-warehouses");

        if (purchaseType === "external") {
            // Hiển thị external fields
            $externalFields.show();

            // Chuyển sang input text thủ công
            $nameInput.show();
            $select.hide();

            if ($select.hasClass("select2-hidden-accessible")) {
                $select.select2("destroy");
            }
            $select.next(".select2").hide();

            // Xóa giá trị đã chọn
            $select.val(null).trigger("change");
            $row.find(".product-id-input").val("");
            $row.find(".unit-label").text("");
            $row.find(".unit-name-input").val("");
        } else {
            // Ẩn external fields
            $externalFields.hide();

            // Chuyển về select2
            $nameInput.hide();
            $select.show();

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
            } else {
                $select.next(".select2").show();
            }

            // Xóa giá trị input text
            $nameInput.val("");
        }
    });
    // Khi thêm dòng mới, gọi toggleProductInputRow cho dòng đó
    // $("#add-order-item")
    //     .off("click")
    //     .on("click", function () {
    //         $("#order-items-container").append(renderOrderItemRow(itemIndex));
    //         let $newRow = $("#order-items-container .order-item-row").last();
    //         initSelect2();
    //         toggleProductInputRow($newRow);
    //         itemIndex++;
    //     });
    $(document).ready(function () {
        // Tabs loại đơn hàng: cập nhật hidden input và toggle section khi tab được click
        $(document).on("click", "#order-type-tabs .nav-link", function () {
            $("#order-type-tabs .nav-link").removeClass("active");
            $(this).addClass("active");
            var type = $(this).data("type");
            var other_type = $(this).data("other-type");
            $("#order_type").val(type);
            // Toggle UI theo loại đơn
            if (type === "device") {
                $("#device-list-section").show();
                $("#order-items-section").hide();
                $("#device-return-section").hide();
                $("#content-section").hide();
                $("#revenue-section").hide();
            } else if (type === "return") {
                $("#device-list-section").hide();
                $("#device-return-section").show();
                $("#order-items-section").hide();
                $("#revenue-section").hide();
                $("#content-section").hide();
            } else if (type === "content") {
                $("#device-list-section").hide();
                $("#device-return-section").hide();
                $("#order-items-section").hide();
                $("#revenue-section").hide();
                $("#content-section").show();
            } else if (other_type === "revenue") {
                $("#device-list-section").hide();
                $("#device-return-section").hide();
                $("#order-items-section").hide();
                $("#content-section").hide();
                $("#revenue-section").show();
            } else {
                $("#device-list-section").hide();
                $("#device-return-section").hide();
                $("#order-items-section").show();
                $("#content-section").hide();
                $("#revenue-section").hide();
            }
        });

        // Khởi tạo trạng thái ban đầu cho tất cả dòng
        $("#order-items-container .order-item-row").each(function () {
            let $row = $(this);
            let itemType = $row.find(".item-type-select").val();
            let purchaseType = $row.find(".purchase-type-select").val();
            let $nameInput = $row.find(".product-name-input");
            let $select = $row.find(".product-select-all-warehouses");
            let $purchaseTypeContainer = $row.find(".purchase-type-container");
            let $externalFields = $row.find(".external-fields");

            if (itemType === "service") {
                // Ẩn trường nguồn mua cho dịch vụ
                $purchaseTypeContainer.hide();

                $nameInput.show();
                $select.hide();
                // Ẩn container select2 nếu đã khởi tạo trước đó
                if ($select.hasClass("select2-hidden-accessible")) {
                    $select.select2("destroy");
                }
                $select.next(".select2").hide();

                // Ẩn external fields
                $externalFields.hide();
            } else {
                // Hiển thị trường nguồn mua cho sản phẩm
                $purchaseTypeContainer.show();

                if (purchaseType === "external") {
                    // Nếu là mua ngoài, hiển thị input text và external fields
                    $nameInput.show();
                    $select.hide();

                    if ($select.hasClass("select2-hidden-accessible")) {
                        $select.select2("destroy");
                    }
                    $select.next(".select2").hide();

                    // Hiển thị external fields
                    $externalFields.show();
                } else {
                    // Nếu là từ kho, hiển thị select2
                    $nameInput.hide();
                    $select.show();

                    // Khởi tạo Select2 cho dòng sản phẩm
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

                    // Ẩn external fields
                    $externalFields.hide();
                }
            }
        });

        // Thêm CSS để đảm bảo select2 bị ẩn hoàn toàn khi không cần thiết
        $("<style>")
            .prop("type", "text/css")
            .html(
                `
                .product-select-all-warehouses[style*="display: none"] + .select2-container,
                .product-select-all-warehouses[style*="display:none"] + .select2-container {
                    display: none !important;
                }
            `
            )
            .appendTo("head");

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

        // ====== Trả thiết bị ======
        function initReceivedDeviceSelect2($select, orderId) {
            $select.select2({
                placeholder: "Chọn thiết bị đã nhận",
                allowClear: true,
                ajax: {
                    url: typeof aerp_order_ajax !== "undefined" ? aerp_order_ajax.ajaxurl : typeof ajaxurl !== "undefined" ? ajaxurl : "",
                    dataType: "json",
                    delay: 250,
                    data: function (params) {
                        var oid = orderId || $("input[name='order_id']").val() || 0;
                        return { action: "aerp_order_search_received_devices", order_id: oid, q: params.term || "" };
                    },
                    processResults: function (data) {
                        var items = Array.isArray(data) ? data : data && data.data ? data.data : [];
                        return { results: items };
                    },
                    cache: true,
                },
                minimumInputLength: 0,
            });
        }

        // init cho các select đang có
        $(".received-device-select").each(function () {
            var $sel = $(this);
            // Luôn init để gắn AJAX URL đúng
            initReceivedDeviceSelect2($sel);
        });

        var deviceReturnIndex = $("#device-return-table .device-return-row").length;
        $(document).on("click", "#add-device-return-row", function () {
            var today = new Date().toISOString().slice(0, 10);
            var row = `<div class="row mb-2 device-return-row">
                <div class="col-md-4">
                    <label class="form-label">Thiết bị nhận</label>
                    <select class="form-select shadow-sm received-device-select shadow-sm" style="width:100%" name="device_returns[${deviceReturnIndex}][device_id]"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ngày trả</label>
                    <input type="date" class="form-control shadow-sm shadow-sm" name="device_returns[${deviceReturnIndex}][return_date]" value="${today}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Ghi chú</label>
                    <textarea type="text" class="form-control shadow-sm shadow-sm" name="device_returns[${deviceReturnIndex}][note]" placeholder="Ghi chú" rows="1"></textarea>
                </div>
                <div class="col-md-1 mt-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger remove-device-return-row">Xóa</button>
                </div>
            </div>`;
            $("#device-return-table").append(row);
            var $newSel = $("#device-return-table .device-return-row").last().find(".received-device-select");
            initReceivedDeviceSelect2($newSel);
            deviceReturnIndex++;
        });

        // Bỏ init tổng quát; việc init chỉ thực hiện cho dòng có loại 'product'

        // Reload product dropdown khi warehouse thay đổi (chỉ cho form tạo đơn hàng)
        $("select[name='warehouse_id']").on("change", function () {
            $(".product-select-by-warehouse").each(function () {
                if ($(this).hasClass("select2-hidden-accessible")) {
                    $(this).val(null).trigger("change");
                }
            });
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

