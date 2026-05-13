$("#rolesTable").DataTable({
    processing: true,
    serverSide: true,
    ajax: RolesDataUrl,
    columns: [
        {
            data: "DT_RowIndex",
            name: "DT_RowIndex",
            orderable: false,
            searchable: false,
        },
        { data: "name", name: "name" },
        { data: "created_at", name: "created_at" },
        {
            data: "action",
            name: "action",
            orderable: false,
            searchable: false,
        },
    ],
    language: {
        emptyTable: "No roles found",
        processing: "Loading...",
        paginate: {
            previous: '<i class="bi bi-chevron-left"></i>',
            next: '<i class="bi bi-chevron-right"></i>',
        },
    },
    pagingType: "simple_numbers",
});

// add role logic start
$("#addRoleButton").click(function (e) {
    e.preventDefault();
    let form = document.getElementById("addRoleForm");
    let data = new FormData(form);
    let type = "POST";
    let url = "/roles/store";
    SendAjaxRequestToServer(type, url, data, "", addRoleResponse, "", "");
});

function addRoleResponse(response) {
    if (response.status == 200) {
        toastr.success(response.message, "", {
            timeOut: 3000,
        });
        $("#cancelAddRoleButton").trigger("click");
        $("#addRoleForm")[0].reset();
        $("#uiBlocker").hide();
        $("#rolesTable").DataTable().ajax.reload(null, false);
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

// add role logic end

// Open Edit Modal
$(document).on("click", ".editRoleBtn", function () {
    const id = $(this).data("id");
    const name = $(this).data("name");

    $("#editRoleId").val(id);
    $("#editRoleName").val(name);
    $("#editRoleModal").modal("show");
});

$("#updateRoleButton").click(function (e) {
    e.preventDefault();
    let form = document.getElementById("editRoleForm");
    let data = new FormData(form);
    let id = $("#editRoleId").val();
    let type = "POST";
    let url = `/roles/update/${id}`;
    SendAjaxRequestToServer(type, url, data, "", updateRolesResponse, "", "");
});

function updateRolesResponse(response) {
    if (response.status == 200) {
        toastr.success(response.message, "", { timeOut: 3000 });
        $("#editRoleModal").modal("hide");
        $("#rolesTable").DataTable().ajax.reload(null, false);
        $("#uiBlocker").hide();
    }

    if (response.status == 402) {
        error = response.message;
    } else {
        error = response.responseJSON.message;
        var is_invalid = response.responseJSON.errors;

        $.each(is_invalid, function (key) {
            var inputField = $('[name="' + key + '"]');
            inputField.addClass("is-invalid");
        });
    }

    toastr.error(error, "", { timeOut: 3000 });
}

// edit category logic end

// delete logic start

// open delete modal
$(document).on("click", ".deleteRoleBtn", function () {
    const id = $(this).data("id");
    $("#deleteRoleId").val(id);
    $("#deleteRoleModal").modal("show");
});

// confirm delete
$("#confirmDeleteRoleBtn").click(function () {
    const id = $("#deleteRoleId").val();
    let url = `/roles/delete/${id}`;
    let type = "DELETE";

    SendAjaxRequestToServer(type, url, null, "", deleteRoleResponse, "", "");
});

function deleteRoleResponse(response) {
    if (response.status === 200) {
        toastr.success(response.message, "", { timeOut: 3000 });
        $("#deleteRoleModal").modal("hide");
        $("#rolesTable").DataTable().ajax.reload(null, false);
    } else {
        toastr.error(response.message || "Failed to delete", "", {
            timeOut: 3000,
        });
    }
}

// delete logic end
