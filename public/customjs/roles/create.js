// Collapse control with uncheck on close
document.querySelectorAll(".module-toggle").forEach((toggle) => {
    toggle.addEventListener("change", function () {
        const target = document.querySelector(this.dataset.bsTarget);
        const checkboxes = target.querySelectorAll('input[type="checkbox"]');

        if (!this.checked) {
            target.classList.remove("show");
            checkboxes.forEach((c) => (c.checked = false));
        } else {
            target.classList.add("show");
        }
    });
});

// Select All functionality
document.querySelectorAll(".select_all").forEach((el) => {
    el.addEventListener("click", function () {
        const module = this.dataset.module;
        const checkboxes = document.querySelectorAll(`.${module}_permissions`);
        checkboxes.forEach((cb) => (cb.checked = true));

        const moduleToggle = document.querySelector(`#module_${module}`);
        if (moduleToggle && !moduleToggle.checked) {
            moduleToggle.checked = true;
            const target = document.querySelector(
                moduleToggle.dataset.bsTarget
            );
            if (target && !target.classList.contains("show")) {
                target.classList.add("show");
            }
        }
    });
});
