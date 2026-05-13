document.getElementById("profile").addEventListener("change", function (e) {
    const [file] = this.files;
    if (file) {
        document.getElementById("profilePreview").src =
            URL.createObjectURL(file);
    }
});

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
    console.log('files', files)
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

document.querySelector('form').addEventListener('submit', function () {
    // No need for this anymore as we're keeping the input updated in real-time
    // updateInputFiles();
});


document.getElementById('providerForm').addEventListener('submit', function (event) {
    console.log('Form submit event triggered'); // Debugging

    let isValid = true;
    let errors = [];

    // Name
    const name = document.getElementById('name').value;
    if (!name) {
        errors.push({ field: 'name', message: 'Please enter the full name.' });
        isValid = false;
    } else if (name.length > 255) {
        errors.push({ field: 'name', message: 'Name cannot exceed 255 characters.' });
        isValid = false;
    }

    // Phone
    const phone = document.getElementById('phone').value;
    if (!phone) {
        errors.push({ field: 'phone', message: 'Please enter a phone number.' });
        isValid = false;
    } else if (phone.length > 20) {
        errors.push({ field: 'phone', message: 'Phone number cannot exceed 20 characters.' });
        isValid = false;
    }

    // Service Type
    const serviceType = document.getElementById('service_category').value;
    if (!serviceType) {
        errors.push({ field: 'service_category', message: 'Please select a service Category.' });
        isValid = false;
    }

    // Experience
    // const experience = document.getElementById('experience').value;
    // if (!experience) {
    //     errors.push({ field: 'experience', message: 'Please select your experience.' });
    //     isValid = false;
    // }

    // Address
    const address = document.getElementById('address').value;
    if (!address) {
        errors.push({ field: 'address', message: 'Please enter an address.' });
        isValid = false;
    } else if (address.length > 1000) {
        errors.push({ field: 'address', message: 'Address cannot exceed 1000 characters.' });
        isValid = false;
    }

    // Date of Birth
    // const dob = document.getElementById('dob').value;
    // const today = new Date();
    // const minDate = new Date('1900-01-01');
    // const maxDate = new Date();
    // maxDate.setFullYear(today.getFullYear() - 18); // Minimum age 18
    // const dobDate = new Date(dob);
    // if (!dob) {
    //     errors.push({ field: 'dob', message: 'Please enter a date of birth.' });
    //     isValid = false;
    // } else if (dobDate > today) {
    //     errors.push({ field: 'dob', message: 'Date of birth cannot be in the future.' });
    //     isValid = false;
    // } else if (dobDate > maxDate) {
    //     errors.push({ field: 'dob', message: 'You must be at least 18 years old.' });
    //     isValid = false;
    // } else if (dobDate < minDate) {
    //     errors.push({ field: 'dob', message: 'Date of birth is too far in the past.' });
    //     isValid = false;
    // }

    // // From and To Time
    // const fromTime = document.getElementById('from_time').value;
    // const toTime = document.getElementById('to_time').value;
    // if (!fromTime) {
    //     errors.push({ field: 'from_time', message: 'Please select a start time.' });
    //     isValid = false;
    // }
    // if (!toTime) {
    //     errors.push({ field: 'to_time', message: 'Please select an end time.' });
    //     isValid = false;
    // } else if (fromTime && toTime && toTime <= fromTime) {
    //     errors.push({ field: 'to_time', message: 'End time must be later than start time.' });
    //     isValid = false;
    // }

    // Pricing Type
    const pricingType = document.getElementById('pricing_type').value;
    if (!pricingType) {
        errors.push({ field: 'pricing_type', message: 'Please select a pricing type.' });
        isValid = false;
    } else if (!['hourly', 'fixed'].includes(pricingType)) {
        errors.push({ field: 'pricing_type', message: 'Please select a valid pricing type (Hourly or Fixed).' });
        isValid = false;
    }

    // Price Amount
    // const priceAmount = document.getElementById('price_amount').value;
    // if (!priceAmount) {
    //     errors.push({ field: 'price_amount', message: 'Please enter an amount.' });
    //     isValid = false;
    // } else if (isNaN(priceAmount) || Number(priceAmount) < 0) {
    //     errors.push({ field: 'price_amount', message: 'Please enter a valid non-negative amount.' });
    //     isValid = false;
    // }

    // Bio (optional)
    const bio = document.getElementById('bio').value;
    if (bio.length > 2000) {
        errors.push({ field: 'bio', message: 'Bio cannot exceed 2000 characters.' });
        isValid = false;
    }

    // Profile Image
    const profileImage = document.getElementById('profile').files[0];
    if (profileImage && !['image/jpeg', 'image/png'].includes(profileImage.type)) {
        errors.push({ field: 'profile', message: 'Please upload a valid image file (JPEG, PNG).' });
        isValid = false;
    }
    if (profileImage && profileImage.size > 8388608) {
        errors.push({ field: 'profile', message: 'Profile image cannot exceed 8MB.' });
        isValid = false;
    }

    // License File
    const licenseFile = document.getElementById('license_file').files[0];
    if (!licenseFile) {
        errors.push({ field: 'license_file', message: 'Please upload a service license.' });
        isValid = false;
    } else if (!['image/jpeg', 'image/png', 'application/pdf'].includes(licenseFile.type)) {
        errors.push({ field: 'license_file', message: 'Please upload a valid file (PDF, JPEG, PNG).' });
        isValid = false;
    } else if (licenseFile.size > 8388608) {
        errors.push({ field: 'license_file', message: 'License file cannot exceed 8MB.' });
        isValid = false;
    }

    // Certification File
    const certificationFile = document.getElementById('certification_file').files[0];
    if (!certificationFile) {
        errors.push({ field: 'certification_file', message: 'Please upload a certification file.' });
        isValid = false;
    } else if (!['image/jpeg', 'image/png', 'application/pdf'].includes(certificationFile.type)) {
        errors.push({ field: 'certification_file', message: 'Please upload a valid file (PDF, JPEG, PNG).' });
        isValid = false;
    } else if (certificationFile.size > 8388608) {
        errors.push({ field: 'certification_file', message: 'Certification file cannot exceed 8MB.' });
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
