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