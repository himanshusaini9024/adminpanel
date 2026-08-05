{{--
  Dynamic Size Guide builder (inch + cm) with live preview matching storefront chart:
  two tables — Inches and Cm — rows = measurements, columns = sizes.
--}}
@php
    $sg = $sizeGuide ?? ['type' => 'size_guide', 'dimensions' => []];
    $sgDims = $sg['dimensions'] ?? [];
@endphp

<style>
.size-guide-wrap { margin-top: 4px; }
.size-guide-toolbar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:14px; }
.size-guide-table { width:100%; border-collapse:collapse; font-size:.85rem; background:#fff; }
.size-guide-table th, .size-guide-table td { border:1px solid #e2e8f0; padding:8px; vertical-align:middle; }
.size-guide-table th { background:#f8fafc; font-weight:700; color:#475569; text-align:center; white-space:nowrap; }
.size-guide-table .dim-name { min-width:140px; }
.size-guide-table .size-cell { min-width:120px; }
.size-guide-table .unit-pair { display:flex; gap:4px; align-items:center; }
.size-guide-table .unit-pair input { width:58px; padding:4px 6px; font-size:.8rem; }
.size-guide-table .unit-pair span { font-size:.7rem; color:#94a3b8; }
.size-guide-preview { margin-top:20px; border:1px solid #e2e8f0; border-radius:8px; padding:16px; background:#f8fafc; }
.size-guide-preview h6 { margin:0 0 14px; font-weight:700; color:#0f172a; }
.sg-preview-grid { display:grid; grid-template-columns:1fr; gap:18px; }
@media (min-width: 900px) { .sg-preview-grid { grid-template-columns:1fr 1fr; } }
.sg-chart { background:#fff; border:1px solid #cbd5e1; border-radius:6px; overflow:hidden; }
.sg-chart-label { background:#f1f5f9; padding:8px 12px; font-weight:700; font-size:.85rem; color:#334155; border-bottom:1px solid #cbd5e1; text-align:right; }
.sg-chart table { width:100%; border-collapse:collapse; font-size:.88rem; }
.sg-chart th, .sg-chart td { border:1px solid #e2e8f0; padding:10px 12px; text-align:center; }
.sg-chart th { background:#fff; font-weight:700; color:#0f172a; }
.sg-chart td:first-child, .sg-chart th:first-child { text-align:left; font-weight:600; }
.size-guide-empty { color:#94a3b8; font-size:.9rem; padding:12px 0; }
.btn-sg-add { background:#2563eb; color:#fff; border:none; border-radius:6px; padding:7px 14px; font-size:.82rem; cursor:pointer; }
.btn-sg-add:hover { background:#1d4ed8; }
.btn-sg-sample { background:#0f172a; color:#fff; border:none; border-radius:6px; padding:7px 14px; font-size:.82rem; cursor:pointer; }
.btn-sg-sample:hover { background:#1e293b; }
.btn-sg-remove { background:#ef4444; color:#fff; border:none; border-radius:4px; width:26px; height:26px; cursor:pointer; font-size:12px; }
.sg-hint { font-size:.8rem; color:#64748b; margin-bottom:10px; }
</style>

<div class="size-guide-wrap" id="sizeGuideBuilder">
    <p class="sg-hint">
        Add measurement rows (Length, Chest, Shoulder…). Enter <strong>inch</strong> or <strong>cm</strong> — the other converts automatically.
        Size columns are fixed: <strong>S · M · L · XL</strong>. Preview shows two charts: <strong>Inches</strong> and <strong>Cm</strong>.
    </p>

    <div class="size-guide-toolbar">
        <button type="button" class="btn-sg-add" id="sgAddDimension">+ Add Dimension</button>
        <button type="button" class="btn-sg-sample" id="sgLoadSample" title="Load Length/Chest/Shoulder/Waist/Collar sample">Load Sample Chart</button>
    </div>

    <div style="overflow-x:auto;">
        <table class="size-guide-table" id="sgEditTable">
            <thead id="sgEditHead"></thead>
            <tbody id="sgEditBody"></tbody>
        </table>
    </div>
    <p class="size-guide-empty" id="sgEmptyMsg" style="display:none;">No dimensions yet. Click “Add Dimension” or “Load Sample Chart”.</p>

    <div class="size-guide-preview" id="sgPreview">
        <h6>Size Guide Preview</h6>
        <div id="sgPreviewBody">
            <p class="size-guide-empty">Add dimensions to preview the size guide.</p>
        </div>
    </div>

    <input type="hidden" name="size_guide_json" id="size_guide_json" value="">
</div>

<script>
(function () {
    var ALL_SIZES = ['S', 'M', 'L', 'XL'];
    var INCH_TO_CM = 2.54;
    var state = { dimensions: [] };

    // Sample chart from your size guide (Inches + Cm)
    var SAMPLE_CHART = [
        {
            name: 'Length',
            sizes: {
                S:  { inch: 27.3, cm: 69.5 },
                M:  { inch: 27.9, cm: 71 },
                L:  { inch: 28.5, cm: 72.5 },
                XL: { inch: 29.2, cm: 74 }
            }
        },
        {
            name: 'Chest',
            sizes: {
                S:  { inch: 22,   cm: 56 },
                M:  { inch: 23.2, cm: 59 },
                L:  { inch: 24.4, cm: 62 },
                XL: { inch: 25.5, cm: 65 }
            }
        },
        {
            name: 'Shoulder',
            sizes: {
                S:  { inch: 18.7, cm: 47.5 },
                M:  { inch: 19.3, cm: 49 },
                L:  { inch: 19.9, cm: 50.5 },
                XL: { inch: 20.5, cm: 52 }
            }
        },
        {
            name: 'Waist',
            sizes: {
                S:  { inch: 21.6, cm: 55 },
                M:  { inch: 22.8, cm: 58 },
                L:  { inch: 24,   cm: 61 },
                XL: { inch: 25.2, cm: 64 }
            }
        },
        {
            name: 'Collar',
            sizes: {
                S:  { inch: 14.6, cm: 37 },
                M:  { inch: 15.4, cm: 39 },
                L:  { inch: 16,   cm: 41 },
                XL: { inch: 16.5, cm: 42 }
            }
        }
    ];

    @php
        $seedJson = json_encode($sgDims, JSON_UNESCAPED_UNICODE);
    @endphp
    try {
        var seed = {!! $seedJson ?: '[]' !!};
        if (Array.isArray(seed) && seed.length) {
            state.dimensions = seed.map(function (d) {
                return { name: d.name || '', sizes: d.sizes || {} };
            });
        }
    } catch (e) {}

    function selectedSizes() {
        // Fixed size columns matching the storefront chart
        return ALL_SIZES.slice();
    }

    function round1(n) {
        return Math.round(n * 10) / 10;
    }

    function toCm(inch) {
        if (inch === '' || inch === null || isNaN(inch)) return '';
        return round1(parseFloat(inch) * INCH_TO_CM);
    }

    function toInch(cm) {
        if (cm === '' || cm === null || isNaN(cm)) return '';
        return round1(parseFloat(cm) / INCH_TO_CM);
    }

    function ensureSizeKeys(dim, sizes) {
        dim.sizes = dim.sizes || {};
        sizes.forEach(function (sz) {
            if (!dim.sizes[sz]) dim.sizes[sz] = { inch: '', cm: '' };
            else {
                dim.sizes[sz].inch = dim.sizes[sz].inch != null ? dim.sizes[sz].inch : '';
                dim.sizes[sz].cm = dim.sizes[sz].cm != null ? dim.sizes[sz].cm : '';
            }
        });
    }

    function cloneSample() {
        return SAMPLE_CHART.map(function (d) {
            var sizes = {};
            Object.keys(d.sizes).forEach(function (sz) {
                sizes[sz] = { inch: d.sizes[sz].inch, cm: d.sizes[sz].cm };
            });
            return { name: d.name, sizes: sizes };
        });
    }

    function renderEditor() {
        var sizes = selectedSizes();
        var head = document.getElementById('sgEditHead');
        var body = document.getElementById('sgEditBody');
        var empty = document.getElementById('sgEmptyMsg');

        state.dimensions.forEach(function (d) { ensureSizeKeys(d, sizes); });

        var th = '<tr><th class="dim-name">Dimension</th>';
        sizes.forEach(function (sz) {
            th += '<th class="size-cell">' + sz + '<br><span style="font-weight:400;font-size:.7rem;color:#94a3b8;">inch / cm</span></th>';
        });
        th += '<th style="width:40px;"></th></tr>';
        head.innerHTML = th;

        if (!state.dimensions.length) {
            body.innerHTML = '';
            empty.style.display = 'block';
            updateHidden();
            renderPreview();
            return;
        }
        empty.style.display = 'none';

        var html = '';
        state.dimensions.forEach(function (dim, di) {
            html += '<tr data-di="' + di + '">';
            html += '<td><input type="text" class="form-control form-control-sm sg-name" data-di="' + di + '" placeholder="e.g. Chest" value="' + escapeAttr(dim.name) + '"></td>';
            sizes.forEach(function (sz) {
                var cell = dim.sizes[sz] || { inch: '', cm: '' };
                html += '<td><div class="unit-pair">' +
                    '<input type="number" step="0.1" min="0" class="form-control form-control-sm sg-inch" data-di="' + di + '" data-sz="' + sz + '" value="' + escapeAttr(cell.inch) + '" placeholder="in">' +
                    '<span>in</span>' +
                    '<input type="number" step="0.1" min="0" class="form-control form-control-sm sg-cm" data-di="' + di + '" data-sz="' + sz + '" value="' + escapeAttr(cell.cm) + '" placeholder="cm">' +
                    '<span>cm</span>' +
                    '</div></td>';
            });
            html += '<td><button type="button" class="btn-sg-remove sg-remove" data-di="' + di + '" title="Remove">✕</button></td>';
            html += '</tr>';
        });
        body.innerHTML = html;
        bindEditorEvents();
        updateHidden();
        renderPreview();
    }

    function bindEditorEvents() {
        document.querySelectorAll('.sg-name').forEach(function (el) {
            el.addEventListener('input', function () {
                var di = +el.getAttribute('data-di');
                state.dimensions[di].name = el.value;
                updateHidden();
                renderPreview();
            });
        });

        document.querySelectorAll('.sg-inch').forEach(function (el) {
            el.addEventListener('input', function () {
                var di = +el.getAttribute('data-di');
                var sz = el.getAttribute('data-sz');
                var inch = el.value;
                state.dimensions[di].sizes[sz].inch = inch;
                var cmVal = toCm(inch);
                state.dimensions[di].sizes[sz].cm = cmVal;
                var cmInput = document.querySelector('.sg-cm[data-di="' + di + '"][data-sz="' + sz + '"]');
                if (cmInput && document.activeElement !== cmInput) cmInput.value = cmVal;
                updateHidden();
                renderPreview();
            });
        });

        document.querySelectorAll('.sg-cm').forEach(function (el) {
            el.addEventListener('input', function () {
                var di = +el.getAttribute('data-di');
                var sz = el.getAttribute('data-sz');
                var cm = el.value;
                state.dimensions[di].sizes[sz].cm = cm;
                var inchVal = toInch(cm);
                state.dimensions[di].sizes[sz].inch = inchVal;
                var inchInput = document.querySelector('.sg-inch[data-di="' + di + '"][data-sz="' + sz + '"]');
                if (inchInput && document.activeElement !== inchInput) inchInput.value = inchVal;
                updateHidden();
                renderPreview();
            });
        });

        document.querySelectorAll('.sg-remove').forEach(function (el) {
            el.addEventListener('click', function () {
                var di = +el.getAttribute('data-di');
                state.dimensions.splice(di, 1);
                renderEditor();
            });
        });
    }

    function buildUnitTable(unit) {
        var sizes = selectedSizes();
        var dims = state.dimensions.filter(function (d) { return (d.name || '').trim() !== ''; });
        var label = unit === 'inch' ? 'Inches' : 'Cm';
        var key = unit === 'inch' ? 'inch' : 'cm';

        var html = '<div class="sg-chart"><div class="sg-chart-label">' + label + '</div><table><thead><tr><th></th>';
        sizes.forEach(function (sz) { html += '<th>' + sz + '</th>'; });
        html += '</tr></thead><tbody>';

        dims.forEach(function (dim) {
            html += '<tr><td>' + escapeHtml(dim.name) + '</td>';
            sizes.forEach(function (sz) {
                var cell = (dim.sizes && dim.sizes[sz]) ? dim.sizes[sz] : {};
                var val = cell[key];
                html += '<td>' + (val !== '' && val != null ? escapeHtml(String(val)) : '—') + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function renderPreview() {
        var box = document.getElementById('sgPreviewBody');
        var dims = state.dimensions.filter(function (d) { return (d.name || '').trim() !== ''; });

        if (!dims.length) {
            box.innerHTML = '<p class="size-guide-empty">Add dimensions to preview the size guide.</p>';
            return;
        }

        box.innerHTML = '<div class="sg-preview-grid">' +
            buildUnitTable('inch') +
            buildUnitTable('cm') +
            '</div>';
    }

    function updateHidden() {
        var payload = {
            type: 'size_guide',
            dimensions: state.dimensions.map(function (d) {
                var sizes = {};
                Object.keys(d.sizes || {}).forEach(function (sz) {
                    var c = d.sizes[sz] || {};
                    sizes[sz] = {
                        inch: c.inch === '' || c.inch == null ? null : parseFloat(c.inch),
                        cm: c.cm === '' || c.cm == null ? null : parseFloat(c.cm)
                    };
                });
                return { name: (d.name || '').trim(), sizes: sizes };
            }).filter(function (d) { return d.name !== ''; })
        };
        var input = document.getElementById('size_guide_json');
        if (input) input.value = JSON.stringify(payload);
    }

    function escapeHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escapeAttr(str) {
        return escapeHtml(str).replace(/'/g, '&#39;');
    }

    document.getElementById('sgAddDimension').addEventListener('click', function () {
        var sizes = selectedSizes();
        var dim = { name: '', sizes: {} };
        ensureSizeKeys(dim, sizes);
        state.dimensions.push(dim);
        renderEditor();
        var last = document.querySelector('#sgEditBody tr:last-child .sg-name');
        if (last) last.focus();
    });

    document.getElementById('sgLoadSample').addEventListener('click', function () {
        if (state.dimensions.length && !confirm('Replace current size guide with the sample chart (Length, Chest, Shoulder, Waist, Collar)?')) {
            return;
        }
        state.dimensions = cloneSample();
        renderEditor();
    });

    var form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function () { updateHidden(); });
    }

    // New product: start from your chart template
    if (!state.dimensions.length) {
        state.dimensions = cloneSample();
    }

    renderEditor();
})();
</script>
