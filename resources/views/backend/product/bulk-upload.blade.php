@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="row">
        <div class="col-md-12">
            @include('backend.layouts.notification')
        </div>
    </div>
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary float-left">Bulk Upload Products</h6>
        <a href="{{ route('product.bulk.template') }}" class="btn btn-success btn-sm float-right">
            <i class="fas fa-download"></i> Download CSV Template
        </a>
    </div>
    <div class="card-body">
        @if(session('bulk_errors'))
            <div class="alert alert-danger">
                <strong>Fix these rows and upload the file again:</strong>
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-bordered bg-white mb-0">
                        <thead>
                            <tr><th style="width:80px">Row</th><th>Title</th><th>Problems</th></tr>
                        </thead>
                        <tbody>
                            @foreach(session('bulk_errors') as $line => $err)
                                <tr>
                                    <td>{{ $line }}</td>
                                    <td>{{ $err['title'] }}</td>
                                    <td>
                                        <ul class="mb-0 pl-3">
                                            @foreach($err['messages'] as $msg)
                                                <li>{{ $msg }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('product.bulk.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="file">CSV file <span class="text-danger">*</span></label>
                <input type="file" name="file" id="file" accept=".csv,text/csv" class="form-control-file" required>
                @error('file')<span class="text-danger d-block">{{ $message }}</span>@enderror
                <small class="form-text text-muted">
                    Up to 500 products per file. In Excel use <em>File &rarr; Save As &rarr; CSV UTF-8</em>.
                    Every row is checked first; if any row has a problem, nothing is imported.
                </small>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload &amp; Import</button>
        </form>

        <hr>

        <h6 class="font-weight-bold">Columns</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead><tr><th>Column</th><th>Required</th><th>Notes</th></tr></thead>
                <tbody>
                    <tr><td>title</td><td>Yes</td><td>Product name. The URL slug is generated from it.</td></tr>
                    <tr><td>sku</td><td>Yes</td><td>Must be unique (not already used, not repeated in the file).</td></tr>
                    <tr><td>category</td><td>Yes</td><td>Parent category title, slug or ID.</td></tr>
                    <tr><td>child_category</td><td>No</td><td>Sub-category under the chosen category.</td></tr>
                    <tr><td>brand</td><td>No</td><td>Brand title, slug or ID.</td></tr>
                    <tr><td>summary</td><td>Yes</td><td>Short description.</td></tr>
                    <tr><td>description</td><td>No</td><td>Long description (plain text or HTML).</td></tr>
                    <tr><td>color</td><td>Yes</td><td>e.g. blue</td></tr>
                    <tr><td>sizes</td><td>No</td><td>Separated by <code>|</code>, e.g. <code>S|M|L|XL</code>. Allowed: S, M, L, XL.</td></tr>
                    <tr><td>stock</td><td>Yes</td><td>Whole number, 0 or more.</td></tr>
                    <tr><td>price</td><td>Yes</td><td>MRP.</td></tr>
                    <tr><td>discount</td><td>No</td><td>Percent (0-100). Same pricing rules as the Add Product form.</td></tr>
                    <tr><td>special_price</td><td>No</td><td>Selling price, used when discount is empty.</td></tr>
                    <tr><td>condition</td><td>No</td><td><code>default</code> (default), <code>new</code> or <code>hot</code>.</td></tr>
                    <tr><td>status</td><td>No</td><td><code>inactive</code> (default) or <code>active</code>.</td></tr>
                    <tr><td>is_featured</td><td>No</td><td><code>1</code> or <code>0</code>.</td></tr>
                    <tr><td>sort_order</td><td>No</td><td>Whole number.</td></tr>
                    <tr><td>images</td><td>No</td><td>Already-uploaded image paths or URLs separated by <code>|</code>; the first one is the main image. Upload images first via the file manager, then copy their paths.</td></tr>
                    <tr><td>image_alt</td><td>No</td><td>Alt text for the images (defaults to title).</td></tr>
                </tbody>
            </table>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h6 class="font-weight-bold">Categories</h6>
                <ul class="small">
                    @foreach($categories as $cat)
                        <li>{{ $cat->title }} <span class="text-muted">({{ $cat->slug }})</span>
                            @if($cat->child_cat->count())
                                <ul>
                                    @foreach($cat->child_cat as $child)
                                        <li>{{ $child->title }} <span class="text-muted">({{ $child->slug }})</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="col-md-6">
                <h6 class="font-weight-bold">Brands</h6>
                <ul class="small">
                    @foreach($brands as $brand)
                        <li>{{ $brand->title }} <span class="text-muted">({{ $brand->slug }})</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
