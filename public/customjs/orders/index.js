$(document).ready(function () {
    // Initialize empty DataTables
    const userOrdersTable = $("#userOrdersTable").DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('orders.index') }}",
            data: function (d) {
                d.user_orders = true;
                d.user_id = $("#user_id").val();
            },
        },
        columns: [
            {
                data: "DT_RowIndex",
                name: "DT_RowIndex",
                orderable: false,
                searchable: false,
            },
            { data: "user", name: "user" },
            { data: "provider", name: "provider" },
            { data: "created_at", name: "created_at" },
            { data: "status", name: "status" },
            {
                data: "action",
                name: "action",
                orderable: false,
                searchable: false,
            },
        ],
        language: {
            emptyTable: "No orders found. Please apply filters to see results.",
            zeroRecords: "No matching orders found",
            processing: '<i class="fa fa-spinner fa-spin fa-3x fa-fw"></i>',
        },
    });

    const providerOrdersTable = $("#providerOrdersTable").DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('orders.index') }}",
            data: function (d) {
                d.provider_orders = true;
                d.provider_id = $("#provider_id").val();
            },
        },
        columns: [
            {
                data: "DT_RowIndex",
                name: "DT_RowIndex",
                orderable: false,
                searchable: false,
            },
            { data: "user", name: "user" },
            { data: "provider", name: "provider" },
            { data: "created_at", name: "created_at" },
            { data: "status", name: "status" },
            {
                data: "action",
                name: "action",
                orderable: false,
                searchable: false,
            },
        ],
        language: {
            emptyTable: "No orders found. Please apply filters to see results.",
            zeroRecords: "No matching orders found",
            processing: '<i class="fa fa-spinner fa-spin fa-3x fa-fw"></i>',
        },
    });

    // Handle form submissions
    $("form").on("submit", function (e) {
        e.preventDefault();

        if ($(this).closest(".tab-pane").attr("id") === "userOrders") {
            userOrdersTable.ajax.reload();
        } else {
            providerOrdersTable.ajax.reload();
        }
    });

    // Clear filters
    $(".btn-secondary").on("click", function () {
        $(this).closest("form").find("select").val("").trigger("change");
        if ($(this).closest(".tab-pane").attr("id") === "userOrders") {
            userOrdersTable.ajax.reload();
        } else {
            providerOrdersTable.ajax.reload();
        }
        return false;
    });
});
