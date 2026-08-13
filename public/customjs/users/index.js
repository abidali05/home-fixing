$(document).ready(function () {
    if (!$.fn.DataTable.isDataTable('#UsersTable')) {
        $("#UsersTable").DataTable({
            processing: true,
            serverSide: true,
            pagingType: "simple_numbers",
            scrollX: false,
            responsive: true,
            ajax: SystemUsersDataUrl,
            columns: [
                { data: "DT_RowIndex", name: "DT_RowIndex" },
                { data: "profile_image", name: "profile_image" },
                { data: "name", name: "name" },
                { data: "user_code", name: "user_code" },
                { data: "phone", name: "phone" },
                { data: "status", name: "status" },
                {
                    data: "action",
                    name: "action",
                    orderable: false,
                    searchable: false,
                },
            ],
            language: {
                emptyTable: "No users found",
                zeroRecords: "No matching users found",
                processing: "Loading...",
                paginate: {
                    previous: '<i class="bi bi-chevron-left"></i>',
                    next: '<i class="bi bi-chevron-right"></i>',
                },
            },
        });
    }
});




// delete logic start
// open delete modal
$(document).on("click", ".deleteUserBtn", function () {
    const id = $(this).data("id");
    $("#deleteUserId").val(id);
    $("#deleteUserModal").modal("show");
});

// confirm delete
$("#confirmDeleteUserBtn").click(function () {
    const id = $("#deleteUserId").val();
    let url = `/users/delete/${id}`;
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
        $("#deleteUserModal").modal("hide");
        $("#UsersTable").DataTable().ajax.reload(null, false);
    } else {
        toastr.error(response.message || "Failed to delete", "", {
            timeOut: 3000,
        });
    }
}

// delete logic end
