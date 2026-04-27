function initCloudinary(inputId, folderBase = "ecommerce") {

    const uploadBtn = document.getElementById(inputId + "_upload");

    if (!uploadBtn) {
        console.warn('[Cloudinary] Upload button not found: #' + inputId + '_upload');
        return;
    }

    uploadBtn.onclick = function () {
   console.log("Cloudinary button clicked");
        // ── Get product name from the actual input name in the blade ──
        // Supports both old name="title" and new name="product_description[1][name]"
        const titleInput =
            document.getElementById('inputTitle') ||
            document.querySelector('[name="product_description[1][name]"]') ||
            document.querySelector('[name="title"]');

        const title = titleInput ? titleInput.value.trim() : 'product';

        // Convert title to a URL-safe slug  e.g. "Black Shirt!" → "blackshirt"
        const slug = title.toLowerCase().replace(/[^a-z0-9]/g, '').trim() || 'product';

        const folder = folderBase + '/' + slug;

        fetch(`/admin/cloudinary-signature?folder=${encodeURIComponent(folder)}`)
            .then(res => res.json())
            .then(data => {

                var widget = cloudinary.createUploadWidget({
                    cloudName: data.cloudName,
                    apiKey: data.apiKey,
                    uploadSignature: data.signature,
                    uploadSignatureTimestamp: data.timestamp,
                    folder: folder,
                    multiple: true,
                    use_filename: true,
                    unique_filename: false,
                }, (error, result) => {

                    if (error) {
                        console.error('[Cloudinary] Widget error:', error);
                        return;
                    }

                    if (result && result.event === "success") {
                        addImageField(result.info.secure_url);
                    }
                });

                widget.open();
            })
            .catch(err => console.error('[Cloudinary] Signature fetch failed:', err));
    };

    // ── Gallery open ──────────────────────────────────────────────────
    window["openGallery_" + inputId] = function () {
        const modal = document.getElementById('galleryModal_' + inputId);
        if (modal) modal.classList.add('open');

        fetch('/admin/cloudinary-images')
            .then(res => res.json())
            .then(data => {
                let html = '';
                data.resources.forEach(img => {
                    html += `<img src="${img.secure_url}"
                                  style="width:120px; cursor:pointer;"
                                  onclick="selectImage('${inputId}','${img.secure_url}')">`;
                });
                const galleryEl = document.getElementById('galleryImages_' + inputId);
                if (galleryEl) galleryEl.innerHTML = html;
            })
            .catch(err => console.error('[Cloudinary] Gallery fetch failed:', err));
    };

    // ── Gallery close ─────────────────────────────────────────────────
    window["closeGallery_" + inputId] = function () {
        const modal = document.getElementById('galleryModal_' + inputId);
        if (modal) modal.classList.remove('open');
    };
}

// ── Set single image (used by gallery select) ─────────────────────────
function setImage(inputId, url) {
    const input = document.getElementById(inputId);
    if (input) input.value = url;

    const holder = document.getElementById("holder_" + inputId);
    if (holder) {
        holder.innerHTML = `<img src="${url}" style="height:100px; border-radius:6px;">`;
    }
}

// ── Select image from gallery ─────────────────────────────────────────
function selectImage(inputId, url) {
    setImage(inputId, url);
    const modal = document.getElementById('galleryModal_' + inputId);
    if (modal) modal.classList.remove('open');
}

// ── Add a new image row to the form ──────────────────────────────────
function addImageField(url) {
    const container = document.getElementById('image_container');
    if (!container) return;

    // Count existing rows in BOTH the static holder AND the dynamic container
    // so indexes never collide on re-upload or after remove
    const existingCount = document.querySelectorAll('#holder .image-item').length;
    const addedCount    = container.querySelectorAll('.image-item').length;
    const index         = existingCount + addedCount;

    const div = document.createElement('div');
    div.className = 'image-item';  // reuses your blade's .image-item styles

    div.innerHTML = `
        <img src="${url}" alt="preview">
        <input type="text"  name="photo[${index}][url]" value="${url}" hidden>
        <input type="text"  name="photo[${index}][alt]" value=""
               class="form-control" placeholder="Alt text" style="max-width:260px;">
        <button type="button" class="remove-btn"
                onclick="this.closest('.image-item').remove()">
            ✕ Remove
        </button>
    `;

    container.appendChild(div);
}