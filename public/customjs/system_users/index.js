$(document).ready(function () {
    $("#SystemUsersTable").DataTable({
        processing: true,
        serverSide: true,
        pagingType: "simple_numbers",
        scrollX: false,
        responsive: true,
        ajax: SystemUsersDataUrl,
        columns: [
            { data: "DT_RowIndex", name: "DT_RowIndex" },
            { data: "name", name: "name" },
            { data: "email", name: "email" },
            { data: "phone", name: "phone" },
            { data: "rolename", name: "rolename" },
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
});


// add category logic start
$("#addCategoryButton").click(function (e) {
    e.preventDefault();
    let form = document.getElementById("addCategoryForm");
    let data = new FormData(form);
    let type = "POST";
    let url = "/service-category/store";
    SendAjaxRequestToServer(type, url, data, "", addCategoryResponse, "", "");
});

function addCategoryResponse(response) {
    if (response.status == 200) {
        toastr.success(response.message, "", {
            timeOut: 3000,
        });
        $("#addCategoryClose").trigger("click");
        $("#addCategoryForm")[0].reset();
        $("#uiBlocker").hide();
        $("#categoryTable").DataTable().ajax.reload(null, false);
    }

    if (response.status == 402) {
        error = response.message;
    } else {
        error = response.responseJSON.message;
        var is_invalid = response.responseJSON.errors;

        $.each(is_invalid, function (key) {
            // Assuming 'key' corresponds to the form field name
            var inputField = $('[name="' + key + '"]');
            // Add the 'is-invalid' class to the input field's parent or any desired container
            inputField.addClass("is-invalid");
        });
    }
    toastr.error(error, "", {
        timeOut: 3000,
    });
}


// delete logic start
// open delete modal
$(document).on("click", ".deleteUserBtn", function () {
    const id = $(this).data("id");
    $("#deleteUserId").val(id);
    $("#deleteSystemUserModal").modal("show");
});

// confirm delete
$("#confirmDeleteSystemUserBtn").click(function () {
    const id = $("#deleteUserId").val();
    let url = `/system-users/delete/${id}`;
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
        $("#deleteSystemUserModal").modal("hide");
        $("#SystemUsersTable").DataTable().ajax.reload(null, false);
    } else {
        toastr.error(response.message || "Failed to delete", "", {
            timeOut: 3000,
        });
    }
}

// delete logic end
