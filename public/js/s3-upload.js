function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    const input = document.querySelector('input[name="_token"]');
    return input ? input.value : '';
}

function toStoragePath(pathOrUrl) {
    if (!pathOrUrl) return '';
    pathOrUrl = String(pathOrUrl).replace(/\?.*$/, '');
    if (!/^https?:\/\//i.test(pathOrUrl)) {
        return pathOrUrl.startsWith('/') ? pathOrUrl : '/' + String(pathOrUrl).replace(/^\/+/, '');
    }
    const base = window.S3_MEDIA_BASE || '';
    if (base && pathOrUrl.indexOf(base) === 0) {
        return '/' + pathOrUrl.slice(base.length).replace(/^\/+/, '');
    }
    const cloudinary = 'https://res.cloudinary.com/ds48lk80f';
    if (pathOrUrl.indexOf(cloudinary) === 0) {
        return '/' + pathOrUrl.slice(cloudinary.length).replace(/^\/+/, '');
    }
    try { return new URL(pathOrUrl).pathname; } catch (e) { return pathOrUrl; }
}

function toPublicUrl(pathOrUrl) {
    if (!pathOrUrl) return '';
    if (/^https?:\/\//i.test(pathOrUrl)) return pathOrUrl;
    const base = window.S3_MEDIA_BASE || '';
    return base + '/' + String(pathOrUrl).replace(/^\/+/, '');
}

function folderFromImagePath(pathOrUrl) {
    const path = toStoragePath(pathOrUrl).replace(/^\/+/, '');
    if (!path) return '';
    const parts = path.split('/');
    if (parts.length < 2) return parts[0] || '';
    parts.pop();
    return parts.join('/');
}

function slugifyName(title, fallback) {
    const slug = (title || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').trim();
    return slug || fallback || 'product';
}

function getProductImageFolder() {
    const titleInput =
        document.querySelector('[name="product_description[1][name]"]') ||
        document.getElementById('inputTitle') ||
        document.querySelector('[name="title"]');
    const title = titleInput ? titleInput.value.trim() : '';
    return 'ecommerce/product/' + slugifyName(title, 'product');
}

function resolveFolderPath(folderBase) {
    if (typeof folderBase === 'function') {
        try { return folderBase() || 'ecommerce'; } catch (e) { return 'ecommerce'; }
    }
    return folderBase || 'ecommerce';
}

/* ─── file type helpers ─────────────────────────────────────────── */
const FILE_TYPE_ICONS = {
    video:    '🎬',
    document: '📄',
    archive:  '📦',
    other:    '📎',
};
const IMAGE_EXTS = ['jpg','jpeg','png','gif','webp','svg'];

function fileTypeFromName(name) {
    const ext = (name || '').split('.').pop().toLowerCase();
    if (IMAGE_EXTS.indexOf(ext) >= 0) return 'image';
    if (['mp4','webm','mov','avi','mkv','flv','wmv','m4v'].indexOf(ext) >= 0) return 'video';
    if (['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','rtf','odt','ods'].indexOf(ext) >= 0) return 'document';
    if (['zip','rar','7z','tar','gz'].indexOf(ext) >= 0) return 'archive';
    return 'other';
}

/* ─── S3 File Manager Module ────────────────────────────────────── */
const S3FileManager = (function () {
    let overlay = null;
    let state = {
        path: 'ecommerce',
        multiple: false,
        onSelect: null,
        selected: new Map(),
        loading: false,
        acceptFilter: null, // null = all, 'image', 'video', 'document', etc.
        embedded: false,    // true when rendered inside a page container
    };

    function ensureDom() {
        if (overlay) return;

        overlay = document.createElement('div');
        overlay.id = 's3fm-overlay';
        overlay.innerHTML = buildHtml();
        document.body.appendChild(overlay);

        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        bindButtons();
    }

    function ensureEmbedded(container) {
        if (overlay) return;
        overlay = container;
        overlay.innerHTML = '<div class="s3fm-modal s3fm-embedded">' + buildInner() + '</div>';
        overlay.classList.add('open');
        state.embedded = true;
        bindButtons();
    }

    function buildHtml() {
        return '<div class="s3fm-modal" role="dialog" aria-modal="true">' + buildInner() + '</div>';
    }

    function buildInner() {
        return `
                <div class="s3fm-top">
                    <div class="s3fm-breadcrumbs" id="s3fm-breadcrumbs"></div>
                    <div class="s3fm-actions">
                        <button type="button" class="btn" id="s3fm-refresh" title="Refresh">↻ Refresh</button>
                        <button type="button" class="btn" id="s3fm-checkall">Check All</button>
                        <button type="button" class="btn btn-upload" id="s3fm-upload">⬆ Upload</button>
                        <button type="button" class="btn" id="s3fm-addfolder">📁 Add Folder</button>
                        <button type="button" class="btn btn-delete" id="s3fm-delete" style="background:#ef4444;border-color:#ef4444;color:#fff;" disabled>🗑 Delete</button>
                        <button type="button" class="btn btn-close" id="s3fm-close">✕</button>
                    </div>
                </div>
                <div class="s3fm-body">
                    <aside class="s3fm-sidebar" id="s3fm-sidebar"></aside>
                    <main class="s3fm-main">
                        <div id="s3fm-grid" class="s3fm-grid"></div>
                        <div class="s3fm-prompt" id="s3fm-prompt">
                            <div class="s3fm-prompt-box">
                                <h5>Add New Folder</h5>
                                <input type="text" id="s3fm-folder-name" placeholder="Folder name">
                                <div class="s3fm-prompt-actions">
                                    <button type="button" class="btn-submit" id="s3fm-folder-submit">Submit</button>
                                    <button type="button" class="btn-cancel" id="s3fm-folder-cancel">Close</button>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>
                <div class="s3fm-footer">
                    <div class="hint" id="s3fm-hint">Browse folders, upload files, then select items.</div>
                    <button type="button" class="btn-use" id="s3fm-use" disabled>Use Selected</button>
                </div>`;
    }

    function bindButtons() {
        var closeBtn = document.getElementById('s3fm-close');
        if (closeBtn) closeBtn.onclick = close;
        document.getElementById('s3fm-refresh').onclick = function () { load(state.path); };
        document.getElementById('s3fm-upload').onclick = uploadFiles;
        document.getElementById('s3fm-addfolder').onclick = function () {
            document.getElementById('s3fm-folder-name').value = '';
            document.getElementById('s3fm-prompt').classList.add('open');
            document.getElementById('s3fm-folder-name').focus();
        };
        document.getElementById('s3fm-folder-cancel').onclick = function () {
            document.getElementById('s3fm-prompt').classList.remove('open');
        };
        document.getElementById('s3fm-folder-submit').onclick = createFolder;
        document.getElementById('s3fm-checkall').onclick = checkAll;
        document.getElementById('s3fm-delete').onclick = deleteSelected;
        var useBtn = document.getElementById('s3fm-use');
        if (useBtn) useBtn.onclick = useSelected;
    }

    function open(options) {
        ensureDom();
        state.multiple = !!(options && options.multiple);
        state.onSelect = options && options.onSelect ? options.onSelect : null;
        state.acceptFilter = options && options.accept ? options.accept : null;
        state.selected.clear();
        state.path = resolveFolderPath(options && options.path);
        updateUseButton();
        overlay.classList.add('open');
        load(state.path);
    }

    function openEmbedded(container, options) {
        ensureEmbedded(container);
        state.multiple = true;
        state.onSelect = null;
        state.acceptFilter = null;
        state.selected.clear();
        state.path = resolveFolderPath(options && options.path);
        // Hide "Use Selected" and close buttons in embedded mode
        var useBtn = document.getElementById('s3fm-use');
        if (useBtn) useBtn.style.display = 'none';
        var closeBtn = document.getElementById('s3fm-close');
        if (closeBtn) closeBtn.style.display = 'none';
        updateUseButton();
        load(state.path);
    }

    function close() {
        if (!overlay) return;
        if (state.embedded) return; // embedded mode cannot be closed
        overlay.classList.remove('open');
        document.getElementById('s3fm-prompt').classList.remove('open');
    }

    function updateUseButton() {
        var btn = document.getElementById('s3fm-use');
        var delBtn = document.getElementById('s3fm-delete');
        var count = state.selected.size;
        if (btn) {
            btn.disabled = count === 0;
            btn.textContent = count ? ('Use Selected (' + count + ')') : 'Use Selected';
        }
        if (delBtn) {
            delBtn.disabled = count === 0;
            delBtn.textContent = count ? ('🗑 Delete (' + count + ')') : '🗑 Delete';
        }
        var hint = document.getElementById('s3fm-hint');
        if (hint) {
            hint.textContent = state.multiple
                ? 'Select one or more files, then click Use Selected.'
                : 'Click a file to select it, or use Use Selected.';
        }
    }

    function load(path) {
        state.path = path || 'ecommerce';
        state.selected.clear();
        updateUseButton();
        var grid = document.getElementById('s3fm-grid');
        grid.innerHTML = '<div class="s3fm-loading">Loading...</div>';

        fetch('/admin/s3-browse?path=' + encodeURIComponent(state.path), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(async function (r) {
                var data = await r.json().catch(function () { return {}; });
                if (!r.ok || data.success === false) {
                    throw new Error(data.message || ('Failed to load S3 (HTTP ' + r.status + ')'));
                }
                return data;
            })
            .then(function (data) { render(data); })
            .catch(function (err) {
                console.error(err);
                grid.innerHTML = '<div class="s3fm-empty" style="color:#b91c1c;max-width:560px;margin:0 auto;">' +
                    '<strong>Could not load S3 files</strong><br><br>' +
                    '<code style="font-size:12px;word-break:break-word;">' + escapeHtml(err.message || 'Unknown error') + '</code><br><br>' +
                    'Check production <code>.env</code> has AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET, AWS_DEFAULT_REGION, AWS_URL then run <code>php artisan config:clear</code>.' +
                    '</div>';
            });
    }

    function render(data) {
        // Breadcrumbs
        var crumbs = document.getElementById('s3fm-breadcrumbs');
        crumbs.innerHTML = (data.breadcrumbs || [])
            .map(function (c, i, arr) {
                var link = '<a data-path="' + c.path + '">' + escapeHtml(c.name) + '</a>';
                return i < arr.length - 1
                    ? link + '<span class="sep">›</span>'
                    : '<strong>' + escapeHtml(c.name) + '</strong>';
            }).join('');
        crumbs.querySelectorAll('a[data-path]').forEach(function (a) {
            a.onclick = function () { load(a.getAttribute('data-path')); };
        });

        // Sidebar
        var sidebar = document.getElementById('s3fm-sidebar');
        var rootPath = data.root || 'ecommerce';
        var sideHtml = '<button type="button" class="s3fm-sidebar-item ' +
            (data.path === rootPath ? 'active' : '') +
            '" data-path="' + rootPath + '">📂 Data</button>';
        (data.sidebar || []).forEach(function (f) {
            var active = data.path === f.path || String(data.path).indexOf(f.path + '/') === 0 ? 'active' : '';
            sideHtml += '<button type="button" class="s3fm-sidebar-item ' + active +
                '" data-path="' + escapeAttr(f.path) + '">📁 ' + escapeHtml(f.name) + '</button>';
        });
        sidebar.innerHTML = sideHtml;
        sidebar.querySelectorAll('[data-path]').forEach(function (el) {
            el.onclick = function () { load(el.getAttribute('data-path')); };
        });

        // Grid
        var grid = document.getElementById('s3fm-grid');
        var folders = data.folders || [];
        var files = data.files || [];

        // Filter files by accept type if set
        if (state.acceptFilter) {
            files = files.filter(function (f) {
                return (f.type || fileTypeFromName(f.name)) === state.acceptFilter;
            });
        }

        if (!folders.length && !files.length) {
            grid.innerHTML = '<div class="s3fm-empty">This folder is empty. Use Upload or Add Folder.</div>';
            return;
        }

        var html = '';
        folders.forEach(function (f) {
            html +=
                '<div class="s3fm-item s3fm-folder" data-folder="' + escapeAttr(f.path) + '" title="' + escapeAttr(f.name) + '">' +
                '<div class="s3fm-folder-icon"></div>' +
                '<div class="s3fm-name">' + escapeHtml(f.name) + '</div></div>';
        });

        files.forEach(function (f) {
            var fType = f.type || fileTypeFromName(f.name);
            var selected = state.selected.has(f.path) ? 'selected' : '';
            var checked = state.selected.has(f.path) ? 'checked' : '';
            var thumb = '';

            if (fType === 'image') {
                thumb = '<img class="s3fm-thumb" src="' + escapeAttr(f.url) + '" alt="' + escapeAttr(f.name) + '" loading="lazy">';
            } else if (fType === 'video') {
                thumb = '<div class="s3fm-type-icon s3fm-type-video"><span>🎬</span></div>';
            } else if (fType === 'document') {
                var ext = f.ext || f.name.split('.').pop().toLowerCase();
                var icon = ext === 'pdf' ? '📕' : '📄';
                thumb = '<div class="s3fm-type-icon s3fm-type-doc"><span>' + icon + '</span></div>';
            } else if (fType === 'archive') {
                thumb = '<div class="s3fm-type-icon s3fm-type-archive"><span>📦</span></div>';
            } else {
                thumb = '<div class="s3fm-type-icon s3fm-type-other"><span>📎</span></div>';
            }

            var sizeLabel = f.size_human ? '<div class="s3fm-size">' + escapeHtml(f.size_human) + '</div>' : '';

            html +=
                '<div class="s3fm-item s3fm-file ' + selected + '" data-url="' + escapeAttr(f.url) +
                '" data-path="' + escapeAttr(f.path) + '" data-name="' + escapeAttr(f.name) +
                '" data-type="' + escapeAttr(fType) +
                '" title="' + escapeAttr(f.name) + '">' +
                thumb +
                '<input type="checkbox" class="s3fm-check" ' + checked + '>' +
                '<div class="s3fm-name">' + escapeHtml(f.name) + '</div>' +
                sizeLabel + '</div>';
        });

        grid.innerHTML = html;

        grid.querySelectorAll('.s3fm-folder').forEach(function (el) {
            el.onclick = function () { load(el.getAttribute('data-folder')); };
        });

        grid.querySelectorAll('.s3fm-file').forEach(function (el) {
            el.onclick = function (e) {
                if (e.target.classList.contains('s3fm-check')) return;
                toggleSelect(el);
            };
            el.ondblclick = function () {
                state.selected.clear();
                toggleSelect(el);
                if (!state.embedded) useSelected();
            };
            var check = el.querySelector('.s3fm-check');
            if (check) {
                check.onclick = function (e) { e.stopPropagation(); toggleSelect(el); };
            }
        });
    }

    function toggleSelect(el) {
        var path = el.getAttribute('data-path');
        var item = {
            url: el.getAttribute('data-url'),
            path: toStoragePath(path),
            name: el.getAttribute('data-name'),
            type: el.getAttribute('data-type') || 'other',
        };

        if (!state.multiple) {
            state.selected.clear();
            document.querySelectorAll('.s3fm-file.selected').forEach(function (n) {
                n.classList.remove('selected');
                var c = n.querySelector('.s3fm-check');
                if (c) c.checked = false;
            });
        }

        if (state.selected.has(path)) {
            state.selected.delete(path);
            el.classList.remove('selected');
            var c = el.querySelector('.s3fm-check');
            if (c) c.checked = false;
        } else {
            state.selected.set(path, item);
            el.classList.add('selected');
            var c2 = el.querySelector('.s3fm-check');
            if (c2) c2.checked = true;
        }
        updateUseButton();
    }

    function checkAll() {
        var files = document.querySelectorAll('.s3fm-file');
        if (!files.length) return;
        if (!state.multiple) { toggleSelect(files[0]); return; }

        var allSelected = Array.prototype.every.call(files, function (el) {
            return state.selected.has(el.getAttribute('data-path'));
        });

        if (allSelected) {
            state.selected.clear();
            files.forEach(function (el) {
                el.classList.remove('selected');
                var c = el.querySelector('.s3fm-check'); if (c) c.checked = false;
            });
        } else {
            files.forEach(function (el) {
                var path = el.getAttribute('data-path');
                state.selected.set(path, {
                    url: el.getAttribute('data-url'),
                    path: toStoragePath(path),
                    name: el.getAttribute('data-name'),
                    type: el.getAttribute('data-type') || 'other',
                });
                el.classList.add('selected');
                var c = el.querySelector('.s3fm-check'); if (c) c.checked = true;
            });
        }
        updateUseButton();
    }

    function useSelected() {
        var items = Array.from(state.selected.values());
        if (!items.length) return;
        if (typeof state.onSelect === 'function') {
            state.onSelect(state.multiple ? items : items[0]);
        }
        close();
    }

    async function deleteSelected() {
        var items = Array.from(state.selected.values());
        if (!items.length) return;
        if (!confirm('Delete ' + items.length + ' file(s)? This cannot be undone.')) return;

        var paths = items.map(function (item) { return item.path; });
        var grid = document.getElementById('s3fm-grid');
        grid.innerHTML = '<div class="s3fm-loading">Deleting ' + items.length + ' file(s)...</div>';

        try {
            var res = await fetch('/admin/s3-delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ paths: paths, _token: getCsrfToken() }),
            });
            var data = await res.json().catch(function () { return {}; });
            if (!res.ok) throw new Error(data.message || 'Delete failed');
            state.selected.clear();
            updateUseButton();
            load(state.path);
        } catch (err) {
            console.error(err);
            alert(err.message || 'Delete failed');
            load(state.path);
        }
    }

    function uploadFiles() {
        var input = document.createElement('input');
        input.type = 'file';
        // Accept all files — images, videos, PDFs, documents, etc.
        input.accept = '*/*';
        input.multiple = true;
        input.style.display = 'none';
        document.body.appendChild(input);

        input.onchange = async function () {
            var files = Array.from(input.files || []);
            document.body.removeChild(input);
            if (!files.length) return;

            var grid = document.getElementById('s3fm-grid');
            grid.innerHTML = '<div class="s3fm-loading">Uploading ' + files.length + ' file(s)...</div>';

            try {
                for (var i = 0; i < files.length; i++) {
                    var formData = new FormData();
                    formData.append('file', files[i]);
                    formData.append('folder', state.path);
                    formData.append('_token', getCsrfToken());

                    var res = await fetch('/admin/s3-upload', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
                        credentials: 'same-origin',
                        body: formData,
                    });
                    var data = await res.json().catch(function () { return {}; });
                    if (!res.ok) throw new Error(data.message || 'Upload failed');
                }
                load(state.path);
            } catch (err) {
                console.error(err);
                alert(err.message || 'Upload failed');
                load(state.path);
            }
        };
        input.click();
    }

    function createFolder() {
        var name = document.getElementById('s3fm-folder-name').value.trim();
        if (!name) { alert('Enter a folder name'); return; }

        fetch('/admin/s3-folder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ path: state.path, name: name, _token: getCsrfToken() }),
        })
            .then(async function (res) {
                var data = await res.json().catch(function () { return {}; });
                if (!res.ok) throw new Error(data.message || 'Could not create folder');
                document.getElementById('s3fm-prompt').classList.remove('open');
                load(state.path);
            })
            .catch(function (err) {
                console.error(err);
                alert(err.message || 'Could not create folder');
            });
    }

    function escapeHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escapeAttr(str) {
        return escapeHtml(str).replace(/'/g, '&#39;');
    }

    return { open: open, openEmbedded: openEmbedded, close: close, load: load };
})();

function openS3FileManager(options) {
    S3FileManager.open(options || {});
}

function initS3Upload(inputId, folderBase) {
    var uploadBtn = document.getElementById(inputId + '_upload');
    if (!uploadBtn) {
        console.warn('[S3] Upload button not found: #' + inputId + '_upload');
        return;
    }

    function openForProduct() {
        openS3FileManager({
            path: resolveFolderPath(folderBase || getProductImageFolder),
            multiple: true,
            onSelect: function (items) {
                (items || []).forEach(function (item) {
                    if (typeof addImageField === 'function') {
                        addImageField(item.url, item.path);
                    }
                });
            },
        });
    }

    uploadBtn.onclick = function (e) { e.preventDefault(); openForProduct(); };
    window['openGallery_' + inputId] = function () { openForProduct(); };
    window['closeGallery_' + inputId] = function () {};
}

function initS3SingleUpload(options) {
    options = options || {};
    var buttonId = options.buttonId || 'upload_widget';
    var inputId = options.inputId || 'thumbnail';
    var holderId = options.holderId || 'holder';
    var btn = document.getElementById(buttonId);
    if (!btn) return;

    btn.onclick = function (e) {
        e.preventDefault();
        openS3FileManager({
            path: resolveFolderPath(options.folderBase || 'ecommerce'),
            multiple: false,
            accept: options.accept || null,
            onSelect: function (item) {
                var storedPath = toStoragePath(item.path || item.url);
                var previewUrl = toPublicUrl(item.url || item.path);

                if (typeof options.onSuccess === 'function') {
                    options.onSuccess(storedPath, previewUrl);
                    return;
                }
                var input = document.getElementById(inputId);
                if (input) input.value = storedPath;
                var holder = document.getElementById(holderId);
                if (holder) {
                    var type = item.type || fileTypeFromName(item.name || storedPath);
                    if (type === 'image') {
                        holder.innerHTML = '<img src="' + previewUrl + '" style="height:80px;">';
                    } else {
                        var icon = FILE_TYPE_ICONS[type] || '📎';
                        holder.innerHTML = '<div style="padding:8px;font-size:14px;">' + icon + ' ' + escapeHtmlGlobal(item.name || storedPath.split('/').pop()) + '</div>';
                    }
                }
            },
        });
    };
}

function changeImageWithS3(index, folderBase) {
    openS3FileManager({
        path: resolveFolderPath(folderBase || getProductImageFolder),
        multiple: false,
        onSelect: function (item) {
            var storedPath = toStoragePath(item.path || item.url);
            var previewUrl = toPublicUrl(item.url || item.path);
            var preview = document.getElementById('img-preview-' + index);
            var urlInput = document.getElementById('img-url-' + index);
            if (preview) preview.src = previewUrl;
            if (urlInput) urlInput.value = storedPath;
        },
    });
}

function setImage(inputId, url) {
    var input = document.getElementById(inputId);
    if (input) input.value = toStoragePath(url);
    var holder = document.getElementById('holder_' + inputId);
    if (holder) {
        holder.innerHTML = '<img src="' + toPublicUrl(url) + '" style="height:100px; border-radius:6px;">';
    }
}

function escapeHtmlGlobal(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ─── Embedded full-page file manager ───────────────────────────── */
function initEmbeddedFileManager(containerId, startPath) {
    var container = document.getElementById(containerId);
    if (!container) return;
    // Set the ID so CSS selectors work, and add the page-mode class for embedded layout
    container.id = 's3fm-overlay';
    container.classList.add('open', 's3fm-page-mode');
    S3FileManager.openEmbedded(container, { path: startPath || 'ecommerce' });
}
