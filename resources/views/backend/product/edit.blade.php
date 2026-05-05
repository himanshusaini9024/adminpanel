@extends('backend.layouts.master')

@section('main-content')

<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --danger: #ef4444;
        --success: #22c55e;
        --warning: #f59e0b;
        --bg: #f1f5f9;
        --card-bg: #ffffff;
        --border: #e2e8f0;
        --text: #1e293b;
        --muted: #64748b;
        --tab-active: #2563eb;
        --tab-hover: #eff6ff;
    }

    * { box-sizing: border-box; }

    body { background: var(--bg); font-family: 'Segoe UI', sans-serif; color: var(--text); }

    .page-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
        padding: 22px 30px;
        border-radius: 12px;
        margin-bottom: 24px;
        color: #fff;
    }
    .page-header h4 { margin: 0; font-size: 1.4rem; font-weight: 700; }
    .page-header p  { margin: 4px 0 0; opacity: .75; font-size: .85rem; }

    /* ── Tabs ─────────────────────────────────── */
    .tab-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 10px 10px 0 0;
        padding: 10px 14px 0;
    }
    .tab-nav .tab-btn {
        padding: 9px 18px;
        border: none;
        background: transparent;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-size: .85rem;
        font-weight: 600;
        color: var(--muted);
        border-radius: 6px 6px 0 0;
        transition: all .2s;
        white-space: nowrap;
    }
    .tab-nav .tab-btn:hover   { background: var(--tab-hover); color: var(--primary); }
    .tab-nav .tab-btn.active  { color: var(--tab-active); border-bottom-color: var(--tab-active); background: var(--tab-hover); }

    /* ── Tab panels ──────────────────────────── */
    .tab-panels { background: var(--card-bg); border: 1px solid var(--border); border-top: none; border-radius: 0 0 10px 10px; padding: 28px; }
    .tab-panel  { display: none; }
    .tab-panel.active { display: block; }

    /* ── Form helpers ─────────────────────────── */
    .form-section { margin-bottom: 28px; }
    .section-title {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--muted);
        border-bottom: 1px solid var(--border);
        padding-bottom: 8px;
        margin-bottom: 16px;
    }
    .form-row   { display: flex; flex-wrap: wrap; gap: 16px; }
    .form-col   { flex: 1 1 220px; }
    .form-col-2 { flex: 1 1 460px; }
    .form-col-full { flex: 1 1 100%; }

    .form-group { margin-bottom: 18px; }
    .form-group label {
        display: block;
        font-size: .82rem;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 6px;
    }
    .form-group label .req { color: var(--danger); margin-left: 2px; }

    .form-control {
        width: 100%;
        padding: 9px 12px;
        border: 1.5px solid var(--border);
        border-radius: 7px;
        font-size: .9rem;
        color: var(--text);
        background: #fff;
        transition: border-color .2s, box-shadow .2s;
        outline: none;
    }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
    .form-control.is-invalid { border-color: var(--danger); }

    select.form-control { cursor: pointer; }
    textarea.form-control { resize: vertical; min-height: 110px; }

    .invalid-feedback { color: var(--danger); font-size: .78rem; margin-top: 4px; }

    /* ── Checkbox / Radio ─────────────────────── */
    .check-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-size: .9rem;
        font-weight: 500;
    }
    .check-label input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--primary); }

    /* ── Color picker ─────────────────────────── */
    .custom-color-select { position: relative; }
    .color-select-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border: 1.5px solid var(--border);
        border-radius: 7px;
        background: #fff;
        cursor: pointer;
        font-size: .9rem;
        user-select: none;
    }
    .color-select-btn:hover { border-color: var(--primary); }
    .color-preview { width: 18px; height: 18px; border-radius: 4px; border: 1px solid #ccc; }
    .color-options {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 8px;
        max-height: 210px;
        overflow-y: auto;
        z-index: 500;
        box-shadow: 0 4px 16px rgba(0,0,0,.1);
    }
    .color-options.open { display: block; }
    .color-option {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        cursor: pointer;
        font-size: .88rem;
    }
    .color-option:hover { background: var(--tab-hover); }
    .color-box { width: 16px; height: 16px; border-radius: 3px; border: 1px solid #ccc; flex-shrink: 0; }

    /* ── Image gallery ────────────────────────── */
    .image-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-bottom: 10px;
    }
    .image-item img { height: 72px; width: 72px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); }
    .image-item .remove-btn {
        margin-left: auto;
        padding: 5px 12px;
        background: #fee2e2;
        color: var(--danger);
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: .82rem;
        font-weight: 600;
    }
    .image-item .remove-btn:hover { background: var(--danger); color: #fff; }

    /* ── Buttons ──────────────────────────────── */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 22px;
        border: none;
        border-radius: 8px;
        font-size: .88rem;
        font-weight: 600;
        cursor: pointer;
        transition: all .18s;
    }
    .btn-primary  { background: var(--primary);  color: #fff; }
    .btn-primary:hover  { background: var(--primary-dark); }
    .btn-success  { background: var(--success);  color: #fff; }
    .btn-success:hover  { background: #16a34a; }
    .btn-warning  { background: var(--warning);  color: #fff; }
    .btn-danger   { background: var(--danger);   color: #fff; }
    .btn-outline  { background: transparent; border: 1.5px solid var(--border); color: var(--muted); }
    .btn-outline:hover { border-color: var(--primary); color: var(--primary); }

    .form-actions {
        display: flex;
        gap: 12px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        margin-top: 10px;
    }

    /* ── SAP toggle ───────────────────────────── */
    .sap-section {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 8px;
        padding: 20px;
        margin-top: 16px;
    }

    /* ── Gallery Modal ────────────────────────── */
    .gallery-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.55);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    .gallery-modal-overlay.open { display: flex; }
    .gallery-modal {
        background: #fff;
        border-radius: 12px;
        padding: 28px;
        width: 85%;
        max-width: 820px;
        max-height: 85vh;
        overflow-y: auto;
    }
    .gallery-modal h5 { margin: 0 0 16px; }
    .gallery-grid { display: flex; flex-wrap: wrap; gap: 12px; }
    .gallery-grid img { height: 90px; width: 90px; object-fit: cover; border-radius: 6px; cursor: pointer; border: 2px solid transparent; }
    .gallery-grid img:hover { border-color: var(--primary); }

    /* ── Rating stars ─────────────────────────── */
    .star-display { color: #f59e0b; font-size: 1rem; }

    @media (max-width: 768px) {
        .tab-nav .tab-btn { padding: 7px 12px; font-size: .78rem; }
        .tab-panels { padding: 18px; }
    }
</style>

<div class="page-header">
    <h4>✏️ Edit Product</h4>
    <p>Update product details, pricing, images, dimensions, and more.</p>
</div>

{{-- ════════════════════════════════════════════
     TAB NAVIGATION
════════════════════════════════════════════ --}}
<div class="tab-nav">
    <button class="tab-btn active" data-tab="general">General</button>
    <button class="tab-btn" data-tab="seo">SEO</button>
    <button class="tab-btn" data-tab="data">Data</button>
   
    <button class="tab-btn" data-tab="price">Price</button>
    <button class="tab-btn" data-tab="image">Image</button>
    <button class="tab-btn" data-tab="dimension">Dimension</button>
  
    <button class="tab-btn" data-tab="reviews">Reviews</button>
    <button class="tab-btn" data-tab="faq">FAQ</button>
</div>

<form method="POST" action="{{ route('product.update', $product->id) }}" enctype="multipart/form-data" id="formproduct" onsubmit="return validateProductForm()">
    @csrf
    @method('PATCH')

    <div class="tab-panels">

        {{-- ══════════════════════════════════
             TAB 1 — GENERAL
        ══════════════════════════════════ --}}
        <div id="tab-general" class="tab-panel active">

            <div class="form-section">
                <div class="section-title">Basic Information</div>
                <div class="form-row">
                    <div class="form-col-2">
                        <div class="form-group">
                            <label>Product Name <span class="req">*</span></label>
                            <input type="text" name="product_description[1][name]" class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}"
                                   placeholder="Enter product name" value="{{ old('title', $product->title) }}" maxlength="255">
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="form-col-2">
                        <div class="form-group">
                            <label>Display Set Name</label>
                            <input type="text" name="product_description[1][displaysetname]" class="form-control"
                                   placeholder="Display set name" value="{{ old('displaysetname', $product->title) }}">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Product Type</label>
                            <select name="furniture_type" class="form-control">
                                <option value="">Select Main Category</option>
                                <option value="1" {{ $product->furniture_type == 1 ? 'selected' : '' }}>Top </option>
                                <option value="2" {{ $product->furniture_type == 2 ? 'selected' : '' }}>Bottom </option>
                                <option value="3" {{ $product->furniture_type == 3 ? 'selected' : '' }}>Dresses</option>
                                <option value="4" {{ $product->furniture_type == 4 ? 'selected' : '' }}>outerwear </option>
                                <option value="5" {{ $product->furniture_type == 5 ? 'selected' : '' }}>loungewear</option>
                             
                              
                            </select>
                        </div>
                    </div>
                   
                    <div class="form-col">
                        <div class="form-group">
                            <label>Category <span class="req">*</span></label>
                            <select name="cat_id" id="cat_id" class="form-control">
                                <option value="">-- Select Category --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ $product->cat_id == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col" id="child_cat_div" style="{{ $product->child_cat_id ? '' : 'display:none' }}">
                        <div class="form-group">
                            <label>Sub Category</label>
                            <select name="child_cat_id" id="child_cat_id" class="form-control">
                                <option value="">-- Select Sub Category --</option>
                            </select>
                        </div>
                    </div>
                      <div class="form-col">
                        <div class="form-group">
                            <label>Added Date</label>
                            <input type="date" name="date_added" class="form-control"
                                   value="{{ old('date_added', $product->date_added ?? now()->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Condition</label>
                            <select name="condition" class="form-control">
                                <option value="">-- Select Condition --</option>
                                <option value="default" {{ $product->condition == 'default' ? 'selected' : '' }}>Default</option>
                                <option value="new"     {{ $product->condition == 'new'     ? 'selected' : '' }}>New</option>
                                <option value="hot"     {{ $product->condition == 'hot'     ? 'selected' : '' }}>Hot</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Status <span class="req">*</span></label>
                            <select name="status" class="form-control">
                                <option value="active"   {{ $product->status == 'active'   ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $product->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

          

         
        </div>{{-- /tab-general --}}


        {{-- ══════════════════════════════════
             TAB 2 — SEO
        ══════════════════════════════════ --}}
        <div id="tab-seo" class="tab-panel">
            <div class="form-section">
                <div class="section-title">Meta Information</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Meta Title</label>
                            <input type="text" name="product_description[1][meta_title]" class="form-control"
                                   value="{{ old('meta_title', $product->meta_title ?? '') }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Meta Description</label>
                            <input type="text" name="product_description[1][meta_description]" class="form-control"
                                   value="{{ old('meta_description', $product->meta_description ?? '') }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Alt Text</label>
                            <input type="text" name="alt" class="form-control"
                                   value="{{ old('alt', $product->alt ?? $product->title) }}">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Meta Keywords</label>
                            <textarea name="product_description[1][meta_keyword]" class="form-control" rows="3"
                                      placeholder="Comma separated keywords">{{ old('meta_keyword', $product->meta_keyword ?? '') }}</textarea>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Search Tags</label>
                            <input type="text" name="search_tags" class="form-control"
                                   placeholder="Comma separated tags"
                                   value="{{ old('search_tags', $product->search_tags ?? '') }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>No Follow</label>
                            <input type="text" name="no_follow" class="form-control"
                                   value="{{ old('no_follow', $product->no_follow ?? '') }}">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Rating (count)</label>
                            <input type="number" name="rating" class="form-control" min="0"
                                   value="{{ old('rating', $product->rating ?? '') }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Star (e.g. 4.6)</label>
                            <input type="number" name="star" class="form-control" step="0.1" min="0" max="5"
                                   value="{{ old('star', $product->star ?? '') }}">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col-full">
                        <div class="form-group">
                            <label>Schema / GTM JSON</label>
                            <textarea name="product_description[1][schema_gtm]" class="form-control" rows="6"
                                      placeholder='[{"@context":"http://schema.org","@type":"VideoObject",...}]'>{{ old('schema_gtm', $product->schema_gtm ?? '') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col-full">
                        <div class="form-group">
                            <label>Product Description </label>
                             <textarea name="product_description[1][description]" id="description"
                                      class="form-control" rows="8">{{ old('description', $product->description ?? '') }}</textarea>
                                      
                @error('description')
                <span class="text-danger">{{$message}}</span>
                @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>{{-- /tab-seo --}}


        {{-- ══════════════════════════════════
             TAB 3 — DATA
        ══════════════════════════════════ --}}
        <div id="tab-data" class="tab-panel">
            <div class="form-section">
                <div class="section-title">Product Data</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Summary <span class="req">*</span></label>
                            <textarea name="summary" id="summary" class="form-control" rows="4"
                                      placeholder="Short summary...">{{ old('summary', $product->summary) }}</textarea>
                            @error('summary') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>SKU / Model</label>
                            <input type="text" name="sku" class="form-control" placeholder="SKU"
                                   value="{{ old('sku', $product->sku ?? '') }}">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Brand</label>
                            <select name="brand_id" class="form-control">
                                <option value="">-- Select Brand --</option>
                                @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ $product->brand_id == $brand->id ? 'selected' : '' }}>
                                        {{ $brand->title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Size(s)</label>
                            <select name="size[]" class="form-control" multiple>
                                @php $sizes = explode(',', $product->size ?? ''); @endphp
                                <option value="S"  {{ in_array('S',  $sizes) ? 'selected' : '' }}>Small (S)</option>
                                <option value="M"  {{ in_array('M',  $sizes) ? 'selected' : '' }}>Medium (M)</option>
                                <option value="L"  {{ in_array('L',  $sizes) ? 'selected' : '' }}>Large (L)</option>
                                <option value="XL" {{ in_array('XL', $sizes) ? 'selected' : '' }}>Extra Large (XL)</option>
                            </select>
                            <small style="color:var(--muted);">Hold Ctrl/Cmd to select multiple</small>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Color</label>
                            <div class="custom-color-select" id="colorDropdown">
                                <div class="color-select-btn">
                                    <span class="color-preview" id="colorPreview"
                                          style="background: {{ $product->color ?? '#ccc' }}"></span>
                                    <span id="colorLabel">{{ ucfirst($product->color ?? 'Select Color') }}</span>
                                    <span style="margin-left:auto">▾</span>
                                </div>
                                <div class="color-options" id="colorOptions">
                                    @php
                                    $colors = ['red','blue','yellow','green','orange','purple','cyan','magenta',
                                               'lime','teal','indigo','violet','black','white','gray','silver',
                                               'charcoal','beige','ivory','pink','brown','gold','turquoise',
                                               'tan','olive','rust','sage','navy','maroon','coral','plum','lavender'];
                                    @endphp
                                    @foreach($colors as $color)
                                    <div class="color-option" data-value="{{ $color }}">
                                        <span class="color-box" style="background:{{ $color }}"></span>
                                        {{ ucfirst($color) }}
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <input type="hidden" name="color" id="selectedColor" value="{{ $product->color ?? '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>{{-- /tab-data --}}


       



        {{-- ══════════════════════════════════
             TAB 7 — PRICE
        ══════════════════════════════════ --}}
        <div id="tab-price" class="tab-panel">
            <div class="form-section">
                <div class="section-title">Pricing</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Price (NRS) <span class="req">*</span></label>
                            <input type="number" name="price" class="form-control {{ $errors->has('price') ? 'is-invalid' : '' }}"
                                   placeholder="Enter price" value="{{ old('price', $product->price) }}" min="0">
                            @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Discount (%)</label>
                            <input type="number" name="discount" class="form-control"
                                   placeholder="0–100" min="0" max="100"
                                   value="{{ old('discount', $product->discount ?? 0) }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Special Price</label>
                            <input type="number" name="special_price" class="form-control"
                                   placeholder="Sale price"
                                   value="{{ old('special_price', $product->special_price ?? '') }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Stock / Quantity <span class="req">*</span></label>
                            <input type="number" name="stock" class="form-control {{ $errors->has('stock') ? 'is-invalid' : '' }}"
                                   placeholder="Quantity" min="0"
                                   value="{{ old('stock', $product->stock) }}">
                            @error('stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>{{-- /tab-price --}}


        {{-- ══════════════════════════════════
             TAB 8 — IMAGE
        ══════════════════════════════════ --}}
        <div id="tab-image" class="tab-panel">
    <div class="form-section">
        <div class="section-title">Product Images</div>

        {{-- Top controls --}}
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap;">
            <label class="check-label" style="font-size:.85rem;">
                <input type="checkbox" name="front_image" value="1"
                       {{ $product->front_image ? 'checked' : '' }}> front image
            </label>

            <button type="button" id="thumbnail_upload" class="btn btn-success" style="font-size:.82rem;padding:7px 16px;">
                + Add Image
            </button>

            <button type="button" onclick="openGallery_thumbnail()" class="btn btn-outline" style="font-size:.82rem;padding:7px 16px;">
                Open Gallery
            </button>

            {{-- "Create Sizes / Please Select Image to Sync" badge --}}
          
        </div>

        {{-- ── Image Table ── --}}
        <div style="overflow-x:auto;">
            <table id="imageTable" style="width:100%;border-collapse:collapse;font-size:.85rem;">
                <thead>
                    <tr style="background:#f1f5f9;border-bottom:2px solid var(--border);">
                        <th style="padding:10px 8px;text-align:left;font-weight:700;color:var(--muted);width:32px;">
                            <input type="checkbox" id="selectAllImages" title="Select all" style="width:15px;height:15px;">
                        </th>
                        <th style="padding:10px 8px;text-align:left;font-weight:700;color:var(--muted);">Image:</th>
                        <th style="padding:10px 8px;text-align:left;font-weight:700;color:var(--muted);">Type:</th>
                        <th style="padding:10px 8px;text-align:left;font-weight:700;color:var(--muted);">Alt:</th>
                        <th style="padding:10px 8px;text-align:left;font-weight:700;color:var(--muted);">Sort Order:</th>
                        <th style="padding:10px 8px;text-align:center;font-weight:700;color:var(--muted);">New Size:</th>
                        <th style="padding:10px 8px;text-align:left;font-weight:700;color:var(--muted);width:90px;"></th>
                    </tr>
                </thead>
                <tbody id="imageTableBody">

                    {{-- ── Existing images from DB ── --}}
                    @if($product->photo)
                        @php $images = json_decode($product->photo); @endphp
                        @foreach($images as $index => $img)
                        <tr class="image-row" data-index="{{ $index }}" style="border-bottom:1px solid var(--border);">
                            <td style="padding:12px 8px;vertical-align:middle;">
                                <input type="checkbox" name="selected_images[]" value="{{ $index }}"
                                       style="width:15px;height:15px;accent-color:var(--primary);">
                            </td>
                            <td style="padding:12px 8px;vertical-align:middle;">
                                <div style="display:flex;flex-direction:column;align-items:flex-start;gap:6px;">
                                    <img id="img-preview-{{ $index }}"
                                         src="{{ config('app.cloud_url') . $img->url }}"
                                         alt="{{ $img->alt ?? '' }}"
                                         style="height:90px;width:90px;object-fit:cover;border-radius:6px;border:1px solid var(--border);cursor:pointer;"
                                         onclick="changeImage({{ $index }})">
                                    <input type="hidden" name="photo[{{ $index }}][url]"
                                           id="img-url-{{ $index }}" value="{{ $img->url }}">
                                    <a href="#" onclick="changeImage({{ $index }});return false;"
                                       style="font-size:.75rem;color:var(--primary);text-decoration:underline;">
                                        *Change Image
                                    </a>
                                </div>
                            </td>
                            <td style="padding:12px 8px;vertical-align:middle;">
                                <select name="photo[{{ $index }}][type]" class="form-control" style="min-width:140px;">
                                    <option value="Front Image"  {{ ($img->type ?? '') == 'Front Image'   ? 'selected' : '' }}>Front Image</option>
                                    <option value="Product Image"{{ ($img->type ?? '') == 'Product Image' ? 'selected' : '' }}>Product Image</option>
                                    <option value="Back Image"   {{ ($img->type ?? '') == 'Back Image'    ? 'selected' : '' }}>Back Image</option>
                                    <option value="Side Image"   {{ ($img->type ?? '') == 'Side Image'    ? 'selected' : '' }}>Side Image</option>
                                    <option value="Detail Image" {{ ($img->type ?? '') == 'Detail Image'  ? 'selected' : '' }}>Detail Image</option>
                                    <option value="Zoom Image"   {{ ($img->type ?? '') == 'Zoom Image'    ? 'selected' : '' }}>Zoom Image</option>
                                </select>
                            </td>
                            <td style="padding:12px 8px;vertical-align:middle;">
                                <input type="text" name="photo[{{ $index }}][alt]"
                                       class="form-control"
                                       placeholder="Alt text"
                                       value="{{ $img->alt ?? '' }}"
                                       style="min-width:200px;">
                            </td>
                            <td style="padding:12px 8px;vertical-align:middle;">
                                <input type="number" name="photo[{{ $index }}][sort_order]"
                                       class="form-control"
                                       placeholder="0"
                                       value="{{ $img->sort_order ?? '' }}"
                                       style="width:80px;">
                            </td>
                            <td style="padding:12px 8px;vertical-align:middle;text-align:center;">
                                <input type="checkbox" name="photo[{{ $index }}][new_size]"
                                       value="1"
                                       {{ ($img->new_size ?? false) ? 'checked' : '' }}
                                       style="width:16px;height:16px;accent-color:var(--primary);">
                            </td>
                            <td style="padding:12px 8px;vertical-align:middle;">
                                <button type="button" class="btn btn-danger"
                                        style="padding:5px 14px;font-size:.8rem;"
                                        onclick="removeImageRow(this)">
                                    Remove
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    @endif

                </tbody>
            </table>
        </div>

        {{-- new uploads land here (JS appends rows to tbody) --}}
        @error('photo') <div class="invalid-feedback" style="display:block;margin-top:8px;">{{ $message }}</div> @enderror
    </div>

    {{-- ── Gallery Modal ── --}}
    <div id="galleryModal_thumbnail" class="gallery-modal-overlay">
        <div class="gallery-modal">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h5 style="margin:0;">Select Image from Gallery</h5>
                <button type="button" class="btn btn-danger" style="padding:5px 14px;font-size:.82rem;"
                        onclick="closeGallery_thumbnail()">✕ Close</button>
            </div>
            <div id="galleryImages_thumbnail" class="gallery-grid"></div>
        </div>
    </div>
</div>{{-- /tab-image --}}


        {{-- ══════════════════════════════════
             TAB 9 — DIMENSION
        ══════════════════════════════════ --}}
        <div id="tab-dimension" class="tab-panel">
            <div class="form-section">
                <div class="section-title">Product Dimensions</div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Chest (cm)</label>
                            <input type="number" name="chest" class="form-control" step="0.1"
                       value="{{ old('chest', $measurment->chest ?? '') }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Length (cm)</label>
                <input type="number" name="length" class="form-control" step="0.1"
                       value="{{ old('length', $measurment->length ?? '') }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                           <label>Shoulder (cm)</label>
                <input type="number" name="shoulder" class="form-control" step="0.1"
                       value="{{ old('shoulder', $measurment->shoulder ?? '') }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label>Sleeve Length (cm)</label>
                <input type="number" name="sleeve_length" class="form-control" step="0.1"
                       value="{{ old('sleeve_length', $measurment->sleeve_length ?? '') }}">
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label>Waist (cm)</label>
                <input type="number" name="waist" class="form-control" step="0.1"
                       value="{{ old('waist', $measurment->waist ?? '') }}">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                                <label>Hip (cm)</label>
                <input type="number" name="hip" class="form-control" step="0.1"
                       value="{{ old('hip', $measurment->hip ?? '') }}">
                        </div>
                    </div>
                   
                </div>
            </div>
        </div>{{-- /tab-dimension --}}


     


        


        {{-- ══════════════════════════════════
             TAB 13 — REVIEWS
        ══════════════════════════════════ --}}
        <div id="tab-reviews" class="tab-panel">
            <div class="form-section">
                <div class="section-title">Product Reviews</div>
                @if(isset($product->reviews) && $product->reviews->count())
                    <table style="width:100%;border-collapse:collapse;font-size:.88rem;">
                        <thead>
                            <tr style="background:#f1f5f9;">
                                <th style="padding:10px;border:1px solid var(--border);text-align:left;">Reviewer</th>
                                <th style="padding:10px;border:1px solid var(--border);text-align:left;">Rating</th>
                                <th style="padding:10px;border:1px solid var(--border);text-align:left;">Comment</th>
                                <th style="padding:10px;border:1px solid var(--border);text-align:left;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($product->reviews as $review)
                            <tr>
                                <td style="padding:9px;border:1px solid var(--border);">{{ $review->reviewer_name ?? 'Anonymous' }}</td>
                                <td style="padding:9px;border:1px solid var(--border);">
                                    <span class="star-display">{{ str_repeat('★', $review->rating ?? 0) }}</span>
                                </td>
                                <td style="padding:9px;border:1px solid var(--border);">{{ $review->comment }}</td>
                                <td style="padding:9px;border:1px solid var(--border);">{{ $review->created_at->format('d M Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p style="color:var(--muted);padding:20px 0;">No reviews yet for this product.</p>
                @endif
            </div>
        </div>{{-- /tab-reviews --}}


        {{-- ══════════════════════════════════
             TAB 14 — FAQ
        ══════════════════════════════════ --}}
        <div id="tab-faq" class="tab-panel">
            <div class="form-section">
                <div class="section-title">Frequently Asked Questions</div>
                <div id="faq-container">
                    @if(!empty($product->faqs))
                        @foreach(json_decode($product->faqs, true) as $fi => $faq)
                        <div class="faq-item" style="background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:12px;">
                            <div class="form-row">
                                <div class="form-col-full">
                                    <div class="form-group">
                                        <label>Question {{ $fi + 1 }}</label>
                                        <input type="text" name="faqs[{{ $fi }}][question]" class="form-control"
                                               placeholder="Enter question" value="{{ $faq['question'] ?? '' }}">
                                    </div>
                                </div>
                                <div class="form-col-full">
                                    <div class="form-group">
                                        <label>Answer</label>
                                        <textarea name="faqs[{{ $fi }}][answer]" class="form-control" rows="3"
                                                  placeholder="Enter answer">{{ $faq['answer'] ?? '' }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-danger" style="padding:6px 14px;font-size:.8rem;"
                                    onclick="this.closest('.faq-item').remove()">✕ Remove</button>
                        </div>
                        @endforeach
                    @endif
                </div>
                <button type="button" class="btn btn-outline" id="addFaqBtn" style="margin-top:8px;">
                    + Add FAQ
                </button>
            </div>
        </div>{{-- /tab-faq --}}


        {{-- ══════════════════════════════════
             FORM ACTIONS (always visible)
        ══════════════════════════════════ --}}
        <div class="form-actions">
            <button type="submit" class="btn btn-success">💾 Update Product</button>
            <a href="{{ route('product.index') }}" class="btn btn-outline">← Back to Products</a>
        </div>

    </div>{{-- /tab-panels --}}
</form>

@endsection


@push('styles')
<link rel="stylesheet" href="{{ asset('backend/summernote/summernote.min.css') }}">
@endpush


@push('scripts')
<script src="{{ asset('backend/summernote/summernote.min.js') }}"></script>

<script>
/* ── Select-all checkbox ──────────────────────────────────────────── */
document.getElementById('selectAllImages').addEventListener('change', function () {
    document.querySelectorAll('#imageTableBody input[type="checkbox"]')
            .forEach(cb => cb.checked = this.checked);
});

/* ── Remove a table row ───────────────────────────────────────────── */
function removeImageRow(btn) {
    btn.closest('tr').remove();
}




var _mlWidget        = null;
var _mlTargetIndex   = null;

function changeImage(index) {
    _mlTargetIndex = index;

    // Build folder from product name (same logic as your upload widget)
    const titleInput =
        document.querySelector('[name="product_description[1][name]"]') ||
        document.querySelector('[name="title"]');
    const title  = titleInput ? titleInput.value.trim() : 'product';
    const slug   = title.toLowerCase().replace(/[^a-z0-9]/g, '') || 'product';
    const folder = 'ecommerce/' + slug;

    fetch('/admin/cloudinary-ml-auth')
        .then(r => r.json())
        .then(auth => {

            // Reuse widget if already created (avoids memory leaks)
            if (!_mlWidget) {
                _mlWidget = cloudinary.createMediaLibrary(
                    {
                        cloud_name : auth.cloud_name,
                        api_key    : auth.api_key,
                        username   : auth.username,
                        timestamp  : auth.timestamp,
                        signature  : auth.signature,
                        multiple   : false,
                        max_files  : 1,
                        insert_caption: 'Select Image',
                    },
                    {
                        insertHandler: function(data) {
                            if (data && data.assets && data.assets[0]) {
                                const url      = data.assets[0].secure_url;
                                const preview  = document.getElementById('img-preview-' + _mlTargetIndex);
                                const urlInput = document.getElementById('img-url-'     + _mlTargetIndex);
                                if (preview)  preview.src    = url;
                                if (urlInput) urlInput.value = url;
                            }
                        }
                    }
                );
            }

            // ✅ .show() with folder opens directly inside that folder
            _mlWidget.show({
                folder: {
                    path         : folder,
                    resource_type: 'image'
                }
            });
        })
        .catch(err => {
            console.error('[ML Auth Error]', err);
            alert('Could not open media library. See console.');
        });
}
/* ── Add NEW image row (called by initCloudinary on upload success) ── */
function addImageField(url) {
    const tbody = document.getElementById('imageTableBody');
    const index = tbody.querySelectorAll('tr.image-row').length;

    const tr = document.createElement('tr');
    tr.className = 'image-row';
    tr.dataset.index = index;
    tr.style.borderBottom = '1px solid #e2e8f0';

    tr.innerHTML = `
        <td style="padding:12px 8px;vertical-align:middle;">
            <input type="checkbox" name="selected_images[]" value="${index}"
                   style="width:15px;height:15px;accent-color:#2563eb;">
        </td>
        <td style="padding:12px 8px;vertical-align:middle;">
            <div style="display:flex;flex-direction:column;align-items:flex-start;gap:6px;">
                <img id="img-preview-${index}" src="${url}" alt="preview"
                     style="height:90px;width:90px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;cursor:pointer;"
                     onclick="changeImage(${index})">
                <input type="hidden" name="photo[${index}][url]" id="img-url-${index}" value="${url}">
                <a href="#" onclick="changeImage(${index});return false;"
                   style="font-size:.75rem;color:#2563eb;text-decoration:underline;">*Change Image</a>
            </div>
        </td>
        <td style="padding:12px 8px;vertical-align:middle;">
            <select name="photo[${index}][type]" class="form-control" style="min-width:140px;">
                <option value="Front Image">Front Image</option>
                <option value="Product Image">Product Image</option>
                <option value="Back Image">Back Image</option>
                <option value="Side Image">Side Image</option>
                <option value="Detail Image">Detail Image</option>
                <option value="Zoom Image">Zoom Image</option>
            </select>
        </td>
        <td style="padding:12px 8px;vertical-align:middle;">
            <input type="text" name="photo[${index}][alt]" class="form-control"
                   placeholder="Alt text" style="min-width:200px;">
        </td>
        <td style="padding:12px 8px;vertical-align:middle;">
            <input type="number" name="photo[${index}][sort_order]" class="form-control"
                   placeholder="0" style="width:80px;">
        </td>
        <td style="padding:12px 8px;vertical-align:middle;text-align:center;">
            <input type="checkbox" name="photo[${index}][new_size]" value="1"
                   style="width:16px;height:16px;accent-color:#2563eb;">
        </td>
        <td style="padding:12px 8px;vertical-align:middle;">
            <button type="button" class="btn btn-danger"
                    style="padding:5px 14px;font-size:.8rem;"
                    onclick="removeImageRow(this)">Remove</button>
        </td>
    `;

    tbody.appendChild(tr);
}
</script>

<script>

       $(document).ready(function() {
        $('#summary').summernote({
            placeholder: "Write short description.....",
            tabsize: 2,
            height: 150
        });
    });
    $(document).ready(function() {
        $('#description').summernote({
            placeholder: "Write detail Description.....",
            tabsize: 2,
            height: 150
        });
    });

    
/* ─── Tab switching ─────────────────────────────────────────────── */
document.addEventListener("DOMContentLoaded", function () {
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const target = this.dataset.tab;

        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));

        this.classList.add('active');
        document.getElementById('tab-' + target).classList.add('active');
    });
});


  if (typeof initCloudinary === 'function') {
        initCloudinary('thumbnail', 'ecommerce');
    } else {
        console.error("Cloudinary JS not loaded");
    }
    });

/* ─── Color picker ───────────────────────────────────────────────── */
(function () {
    const dropdown    = document.getElementById('colorDropdown');
    if (!dropdown) return;
    const btn         = dropdown.querySelector('.color-select-btn');
    const optionsBox  = document.getElementById('colorOptions');
    const hiddenInput = document.getElementById('selectedColor');
    const preview     = document.getElementById('colorPreview');
    const label       = document.getElementById('colorLabel');

    btn.addEventListener('click', () => optionsBox.classList.toggle('open'));

    dropdown.querySelectorAll('.color-option').forEach(opt => {
        opt.addEventListener('click', function () {
            const value = this.dataset.value;
            hiddenInput.value = value;
            preview.style.background = value;
            label.textContent = this.textContent.trim();
            optionsBox.classList.remove('open');
        });
    });

    document.addEventListener('click', e => {
        if (!dropdown.contains(e.target)) optionsBox.classList.remove('open');
    });
})();

/* ─── Sub-category AJAX ──────────────────────────────────────────── */
var child_cat_id = '{{ $product->child_cat_id }}';

$('#cat_id').on('change', function () {
    var cat_id = $(this).val();
    if (!cat_id) { $('#child_cat_div').hide(); return; }

    $.ajax({
        url: '/admin/category/' + cat_id + '/child',
        type: 'POST',
        data: { _token: '{{ csrf_token() }}' },
        success: function (response) {
            if (typeof response !== 'object') response = $.parseJSON(response);
            var html = "<option value=''>-- Select Sub Category --</option>";
            if (response.status && response.data) {
                $('#child_cat_div').show();
                $.each(response.data, function (id, title) {
                    html += "<option value='" + id + "'" + (child_cat_id == id ? ' selected' : '') + ">" + title + "</option>";
                });
            } else {
                $('#child_cat_div').hide();
            }
            $('#child_cat_id').html(html);
        }
    });
});

if (child_cat_id) { $('#cat_id').trigger('change'); }

/* ─── Summernote rich editors ────────────────────────────────────── */
$(document).ready(function () {
    $('#summary').summernote({ placeholder: 'Write short description...', tabsize: 2, height: 150 });
    $('#product_description').summernote({ placeholder: 'Write detailed description...', tabsize: 2, height: 200 });
});


// document.addEventListener("DOMContentLoaded", function () {
//     initCloudinary('thumbnail', 'ecommerce');
// });


function closeGallery_thumbnail() {
    document.getElementById('galleryModal_thumbnail').classList.remove('open');
}


/* ─── FAQ builder ────────────────────────────────────────────────── */
var faqIndex = {{ !empty($product->faqs) ? count(json_decode($product->faqs, true)) : 0 }};

document.getElementById('addFaqBtn').addEventListener('click', function () {
    const container = document.getElementById('faq-container');
    const div = document.createElement('div');
    div.className = 'faq-item';
    div.style.cssText = 'background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:12px;';
    div.innerHTML = `
        <div class="form-group">
            <label>Question ${faqIndex + 1}</label>
            <input type="text" name="faqs[${faqIndex}][question]" class="form-control" placeholder="Enter question">
        </div>
        <div class="form-group">
            <label>Answer</label>
            <textarea name="faqs[${faqIndex}][answer]" class="form-control" rows="3" placeholder="Enter answer"></textarea>
        </div>
        <button type="button" class="btn btn-danger" style="padding:6px 14px;font-size:.8rem;"
                onclick="this.closest('.faq-item').remove()">✕ Remove</button>
    `;
    container.appendChild(div);
    faqIndex++;
});

/* ─── Form validation ────────────────────────────────────────────── */
function validateProductForm() {
    const name  = document.querySelector('[name="product_description[1][name]"]');
    const price = document.querySelector('[name="price"]');
    const stock = document.querySelector('[name="stock"]');
    let valid = true;

    [name, price, stock].forEach(field => {
        if (field && !field.value.trim()) {
            field.classList.add('is-invalid');
            valid = false;
        } else if (field) {
            field.classList.remove('is-invalid');
        }
    });

    if (!valid) {
        // Switch to the relevant tab automatically
        if (name && !name.value.trim()) {
            document.querySelector('[data-tab="general"]').click();
        } else if (price && !price.value.trim()) {
            document.querySelector('[data-tab="price"]').click();
        }
        alert('Please fill in all required fields.');
    }
    return valid;
}
</script>
@endpush