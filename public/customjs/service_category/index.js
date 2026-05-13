

$(document).ready(function () {
    document.getElementById("category_img").addEventListener("change", function (e) {
        const [file] = this.files;
        if (file) {
            document.getElementById("category_imgPreview").src =
                URL.createObjectURL(file);
        }
    });

    document.getElementById("editcategory_img").addEventListener("change", function (e) {
        const [file] = this.files;
        if (file) {
            document.getElementById("editcategory_imgPreview").src =
                URL.createObjectURL(file);
        }
    });

    $("#categoryTable").DataTable({
        processing: true,
        serverSide: true,
        pagingType: "simple_numbers",
        scrollX: false,
        responsive: true,
        ajax: categoryDataUrl,
        columns: [
            { data: "DT_RowIndex", name: "DT_RowIndex" },
            {
                data: "path",
                name: "path",
                render: function (data, type, row) {
                    return `<img src="${data}" width="50" height="50">`;
                }
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
            emptyTable: "No service categories found",
            zeroRecords: "No matching records found",
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

// add category logic end

// edit category logic start
$(document).on("click", ".editCategoryBtn", function () {
    const id = $(this).data("id");
    const name = $(this).data("name");
    const path = $(this).data("path");

    $("#editCategoryId").val(id);
    $("#editCategoryName").val(name);
    $("#editcategory_imgPreview").attr("src", path);
    $("#editCategoryModal").modal("show");
});

$("#editCategoryButton").click(function (e) {
    e.preventDefault();
    let form = document.getElementById("editCategoryForm");
    let data = new FormData(form);
    let id = $("#editCategoryId").val();
    let type = "POST";
    let url = `/service-category/update/${id}`;
    SendAjaxRequestToServer(
        type,
        url,
        data,
        "",
        updateCategoryResponse,
        "",
        ""
    );
});

function updateCategoryResponse(response) {
    if (response.status == 200) {
        toastr.success(response.message, "", { timeOut: 3000 });
        $("#editCategoryModal").modal("hide");
        $("#categoryTable").DataTable().ajax.reload(null, false);
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
$(document).on("click", ".deleteCategoryBtn", function () {
    const id = $(this).data("id");
    $("#deleteCategoryId").val(id);
    $("#deleteCategoryModal").modal("show");
});

// confirm delete
$("#confirmDeleteCategoryBtn").click(function () {
    const id = $("#deleteCategoryId").val();
    let url = `/service-category/delete/${id}`;
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
        $("#deleteCategoryModal").modal("hide");
        $("#categoryTable").DataTable().ajax.reload(null, false);
    } else {
        toastr.error(response.message || "Failed to delete", "", {
            timeOut: 3000,
        });
    }
}

// delete logic end
