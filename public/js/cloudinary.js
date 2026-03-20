function initCloudinary(inputId, folderBase = "ecommerce") {

    // Upload button
    document.getElementById(inputId + "_upload").onclick = function () {


        let title = document.getElementById('inputTitle').value;

        // convert to slug (black shirt → blackshirt)
        let slug = title.toLowerCase()
            .replace(/[^a-z0-9]/g, '')
            .trim();

        let folder = folderBase + '/' + slug;

        fetch(`/admin/cloudinary-signature?folder=${encodeURIComponent(folder)}`)
            .then(res => res.json())
            .then(data => {

                var widget = cloudinary.createUploadWidget({
                    cloudName: data.cloudName,
                    apiKey: data.apiKey,

                    uploadSignature: data.signature,
                    uploadSignatureTimestamp: data.timestamp,

                    folder: folder,
                     multiple:true,

                    use_filename: true,
                    unique_filename: false

                }, (error, result) => {
                    if (!error && result && result.event === "success") {
                         let url = result.info.secure_url;

                        // 🔥 ADD NEW INPUT FIELD
                        addImageField(url);
                    }
                });

                widget.open();
            });
    };

    // Gallery open
    window["openGallery_" + inputId] = function () {
        document.getElementById('galleryModal_' + inputId).style.display = 'block';

        fetch('/admin/cloudinary-images')
            .then(res => res.json())
            .then(data => {
                let html = '';
                data.resources.forEach(img => {
                    html += `<img src="${img.secure_url}" style="width:120px;cursor:pointer"
                    onclick="selectImage('${inputId}','${img.secure_url}')">`;
                });

                document.getElementById('galleryImages_' + inputId).innerHTML = html;
            });
    };

    // Close gallery
    window["closeGallery_" + inputId] = function () {
        document.getElementById('galleryModal_' + inputId).style.display = 'none';
    };
}

// Set image (global)
function setImage(inputId, url) {
    document.getElementById(inputId).value = url;

    let holder = document.getElementById("holder_" + inputId);
    if (holder) {
        holder.innerHTML = `<img src="${url}" style="height:100px;">`;
    }
}

// Select image
function selectImage(inputId, url) {
    setImage(inputId, url);
    document.getElementById('galleryModal_' + inputId).style.display = 'none';
}


function addImageField(url) {

    let container = document.getElementById('image_container');

    let html = `
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
            
            <!-- Hidden input -->
            <input type="text" name="photo[]" value="${url}" class="form-control">

            <!-- Preview -->
            <img src="${url}" style="height:60px;">

            <!-- Remove button -->
            <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger">
                Remove
            </button>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', html);
}