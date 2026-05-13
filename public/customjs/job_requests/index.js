$(document).ready(function () {
    $("#JobRequestsTable").DataTable({
        processing: true,
        serverSide: true,
        pagingType: "simple_numbers",
        scrollX: false,
        responsive: true,
        ajax: JobRequestsDataUrl,
        columns: [
            { data: "DT_RowIndex", name: "DT_RowIndex" },
            { data: "user_name", name: "user_name" },
            { data: "service_name", name: "service_name" },
            { data: "price", name: "price" },
            { data: "job_date", name: "job_date" },
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
            emptyTable: "No Requests found",
            zeroRecords: "No matching requests found",
            processing: "Loading...",
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>',
            },
        },
    });
});




// delete logic start
// open delete modal
$(document).on("click", ".deleteServiceRequestBtn", function () {
    const id = $(this).data("id");
    $("#deleteRequestId").val(id);
    $("#deleteServiceRequestModal").modal("show");
});

// confirm delete
$("#confirmdeleteServiceRequestBtn").click(function () {
    const id = $("#deleteRequestId").val();
    let url = `/job-requests/delete/${id}`;
    let type = "DELETE";

    SendAjaxRequestToServer(
        type,
        url,
        null,
        "",
        deleteCategoryResponse,
        "",
        ""
    );
});

function deleteCategoryResponse(response) {
    if (response.status === 200) {
        toastr.success(response.message, "", { timeOut: 3000 });
        $("#deleteServiceRequestModal").modal("hide");
        $("#JobRequestsTable").DataTable().ajax.reload(null, false);
    } else {
        toastr.error(response.message || "Failed to delete", "", {
            timeOut: 3000,
        });
    }
}

// delete logic end
