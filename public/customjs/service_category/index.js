$(document).ready(function () {
    const addImgInput = document.getElementById("category_img");
    if (addImgInput) {
        addImgInput.addEventListener("change", function (e) {
            const [file] = this.files;
            if (file) {
                document.getElementById("category_imgPreview").src = URL.createObjectURL(file);
            }
        });
    }

    const editImgInput = document.getElementById("editcategory_img");
    if (editImgInput) {
        editImgInput.addEventListener("change", function (e) {
            const [file] = this.files;
            if (file) {
                document.getElementById("editcategory_imgPreview").src = URL.createObjectURL(file);
            }
        });
    }

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
                    return `<img src="${data}" width="50" height="50" class="rounded border object-fit-contain">`;
                }
            },
            { data: "name", name: "name" },
            { data: "created_at", name: "created_at" },
            {
                data: "action",
                name: "action",
                orderable: false,
                searchable: false,
                className: "text-center",
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

    // Helper: Translate text to target language via Google free client endpoint
    async function translateText(text, targetLang) {
        const url = `https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=${targetLang}&dt=t&q=${encodeURIComponent(text)}`;
        const response = await fetch(url);
        if (!response.ok) throw new Error("Translation failed");
        const json = await response.json();
        let translated = "";
        if (json && json[0]) {
            json[0].forEach(item => {
                if (item && item[0]) translated += item[0];
            });
        }
        return translated.trim();
    }

    // Auto-Translate button for Add Modal
    $("#btnAutoTranslateAdd").click(async function () {
        const enName = $("#categoryName").val().trim();
        if (!enName) {
            toastr.warning("Please enter Category Name (English) first to translate.", "", { timeOut: 3000 });
            $("#categoryName").focus();
            return;
        }

        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> Translating...');

        try {
            const [arResult, urResult] = await Promise.all([
                translateText(enName, "ar"),
                translateText(enName, "ur")
            ]);

            if (arResult) $("#categoryNameAr").val(arResult);
            if (urResult) $("#categoryNameUr").val(urResult);

            toastr.success("Translated to Arabic & Urdu! You can edit if needed.", "", { timeOut: 3000 });
        } catch (err) {
            toastr.error("Could not auto-translate. Please type Arabic and Urdu manually.", "", { timeOut: 3000 });
        } finally {
            $btn.prop("disabled", false).html(originalHtml);
        }
    });

    // Auto-Translate button for Edit Modal
    $("#btnAutoTranslateEdit").click(async function () {
        const enName = $("#editCategoryName").val().trim();
        if (!enName) {
            toastr.warning("Please enter Category Name (English) first to translate.", "", { timeOut: 3000 });
            $("#editCategoryName").focus();
            return;
        }

        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> Translating...');

        try {
            const [arResult, urResult] = await Promise.all([
                translateText(enName, "ar"),
                translateText(enName, "ur")
            ]);

            if (arResult) $("#editCategoryNameAr").val(arResult);
            if (urResult) $("#editCategoryNameUr").val(urResult);

            toastr.success("Translated to Arabic & Urdu! You can edit if needed.", "", { timeOut: 3000 });
        } catch (err) {
            toastr.error("Could not auto-translate. Please type Arabic and Urdu manually.", "", { timeOut: 3000 });
        } finally {
            $btn.prop("disabled", false).html(originalHtml);
        }
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
        $("#category_imgPreview").attr("src", "/assets/img/default.jpg");
        $("#uiBlocker").hide();
        $("#categoryTable").DataTable().ajax.reload(null, false);
    } else {
        var error = response.responseJSON?.message || response.message || "An error occurred";
        var is_invalid = response.responseJSON?.errors;

        if (is_invalid) {
            $.each(is_invalid, function (key) {
                var inputField = $('[name="' + key + '"]');
                inputField.addClass("is-invalid");
            });
        }
        toastr.error(error, "", {
            timeOut: 3000,
        });
    }
}
// add category logic end

// edit category logic start
$(document).on("click", ".editCategoryBtn", function () {
    const id = $(this).data("id");
    const name = $(this).data("name") || "";
    const name_ar = $(this).data("name_ar") || "";
    const name_ur = $(this).data("name_ur") || "";
    const path = $(this).data("path");

    $("#editCategoryId").val(id);
    $("#editCategoryName").val(name);
    $("#editCategoryNameAr").val(name_ar);
    $("#editCategoryNameUr").val(name_ur);
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
    } else {
        var error = response.responseJSON?.message || response.message || "An error occurred";
        var is_invalid = response.responseJSON?.errors;

        if (is_invalid) {
            $.each(is_invalid, function (key) {
                var inputField = $('[name="' + key + '"]');
                inputField.addClass("is-invalid");
            });
        }
        toastr.error(error, "", { timeOut: 3000 });
    }
}
// edit category logic end

// delete logic start
$(document).on("click", ".deleteCategoryBtn", function () {
    const id = $(this).data("id");
    $("#deleteCategoryId").val(id);
    $("#deleteCategoryModal").modal("show");
});

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
