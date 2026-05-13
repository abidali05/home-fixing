const galleryInput = document.getElementById('galleryInput');
const previewContainer = document.getElementById('gallery-preview');
const uploadGalleryBtn = document.getElementById('uploadGalleryBtn');
const dropzone = document.getElementById('gallery-dropzone');

let selectedFiles = [];

uploadGalleryBtn.addEventListener('click', () => galleryInput.click());

galleryInput.addEventListener('change', function () {
    addFiles(this.files);
});

dropzone.addEventListener('dragover', function (e) {
    e.preventDefault();
    dropzone.classList.add('dragover');
});

dropzone.addEventListener('dragleave', function () {
    dropzone.classList.remove('dragover');
});

dropzone.addEventListener('drop', function (e) {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    addFiles(e.dataTransfer.files);
});

function addFiles(files) {
    Array.from(files).forEach((file) => {
        if (!file.type.startsWith('image/')) return;

        const uniqueId = file.name + '_' + file.size;
        if (selectedFiles.find(f => f.id === uniqueId)) return; // skip duplicates

        const reader = new FileReader();
        reader.onload = function (event) {
            const wrapper = document.createElement('div');
            wrapper.className = 'preview-wrapper';
            wrapper.setAttribute('data-id', uniqueId);

            const img = document.createElement('img');
            img.src = event.target.result;

            const removeBtn = document.createElement('button');
            removeBtn.className = 'remove-btn';
            removeBtn.type = 'button';
            removeBtn.innerHTML = '&times;';
            removeBtn.onclick = () => {
                // Remove from selectedFiles array
                selectedFiles = selectedFiles.filter((f) => f.id !== uniqueId);
                // Update the input files
                updateInputFiles();
                // Remove the preview
                wrapper.remove();
            };

            wrapper.appendChild(img);
            wrapper.appendChild(removeBtn);
            previewContainer.appendChild(wrapper);
        };
        reader.readAsDataURL(file);

        selectedFiles.push({ file, id: uniqueId });
    });

    // Update the input files after adding new files
    updateInputFiles();
}

function updateInputFiles() {
    const dt = new DataTransfer();
    selectedFiles.forEach(({ file }) => dt.items.add(file));
    galleryInput.files = dt.files;
}

            document.getElementById('serviceRequestForm').addEventListener('submit', function (event) {
                    console.log('Form submit event triggered');

                    let isValid = true;
                    let errors = [];

                    // User ID
                    const userId = document.getElementById('user_id').value;
                    if (!userId) {
                        errors.push({ field: 'user_id', message: 'Please select a user.' });
                        isValid = false;
                    }

                    // Service ID
                    const serviceId = document.getElementById('service_id').value;
                    if (!serviceId) {
                        errors.push({ field: 'service_id', message: 'Please select a service.' });
                        isValid = false;
                    }

                    // Instructions
                    const instructions = document.getElementById('instructions').value;
                    if (!instructions) {
                        errors.push({ field: 'instructions', message: 'Please provide specific instructions.' });
                        isValid = false;
                    } else if (instructions.length > 2000) {
                        errors.push({ field: 'instructions', message: 'Instructions cannot exceed 2000 characters.' });
                        isValid = false;
                    }

                    // Date
                    const date = document.getElementById('date').value;
                    const today = new Date();
                    today.setHours(0, 0, 0, 0); // Normalize to start of day
                    const maxDate = new Date();
                    maxDate.setFullYear(today.getFullYear() + 1); // Allow up to 1 year in the future
                    const selectedDate = new Date(date);
                    if (!date) {
                        errors.push({ field: 'date', message: 'Please select a date.' });
                        isValid = false;
                    } else if (selectedDate < today) {
                        errors.push({ field: 'date', message: 'Date cannot be in the past.' });
                        isValid = false;
                    } else if (selectedDate > maxDate) {
                        errors.push({ field: 'date', message: 'Date cannot be more than one year in the future.' });
                        isValid = false;
                    }

                    // Time
                    const time = document.getElementById('time').value;
                    if (!time) {
                        errors.push({ field: 'time', message: 'Please select a time.' });
                        isValid = false;
                    } else if (!/^\d{2}:\d{2}$/.test(time)) {
                        errors.push({ field: 'time', message: 'Please select a valid time.' });
                        isValid = false;
                    }

                    // Price
                    const price = document.getElementById('price').value;
                    if (!price) {
                        errors.push({ field: 'price', message: 'Please enter a price.' });
                        isValid = false;
                    } else if (isNaN(price) || Number(price) < 0) {
                        errors.push({ field: 'price', message: 'Please enter a valid non-negative price.' });
                        isValid = false;
                    }

                    // Address
                    const address = document.getElementById('address').value;
                    if (!address) {
                        errors.push({ field: 'address', message: 'Please enter an address.' });
                        isValid = false;
                    } else if (address.length > 255) {
                        errors.push({ field: 'address', message: 'Address cannot exceed 255 characters.' });
                        isValid = false;
                    }

                    // Gallery (optional)
                    if (selectedFiles.length > 0) {
                        selectedFiles.forEach(({ file }, index) => {
                            if (!['image/jpeg', 'image/png'].includes(file.type)) {
                                errors.push({ field: 'galleryInput', message: `Gallery image ${index + 1} must be a valid image file (JPEG, PNG).` });
                                isValid = false;
                            } else if (file.size > 8388608) {
                                errors.push({ field: 'galleryInput', message: `Gallery image ${index + 1} cannot exceed 8MB.` });
                                isValid = false;
                            }
                        });
                    }

                    // Display errors
                    document.querySelectorAll('.text-danger.client-side').forEach(el => el.remove());
                    errors.forEach(error => {
                        const field = document.getElementById(error.field);
                        const errorElement = document.createElement('small');
                        errorElement.className = 'text-danger client-side';
                        errorElement.textContent = error.message;
                        field.parentElement.appendChild(errorElement);
                    });

                    if (!isValid) {
                        event.preventDefault(); // Prevent form submission
                    }
                });
