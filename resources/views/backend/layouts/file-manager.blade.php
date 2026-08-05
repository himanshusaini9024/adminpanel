@extends('backend.layouts.master')
@section('title','E-SHOP || File Manager')
@section('main-content')
<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-folder-open"></i> S3 File Manager
            </h6>
            <div class="mt-2 mt-md-0">
                <button type="button" class="btn btn-sm btn-primary" onclick="S3FileManager.load('ecommerce')">All Files</button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="S3FileManager.load('ecommerce/categories')">Categories</button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="S3FileManager.load('ecommerce/product')">Products</button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="S3FileManager.load('ecommerce/banners')">Banners</button>
            </div>
        </div>
        <div class="card-body p-2">
            <p class="small text-muted mb-2 px-2">
                Images live in one folder per item (e.g. <code>categories/slug/images</code>, <code>product/slug/images</code>).
                Name desktop files with a <strong>desk-</strong> prefix and mobile files with <strong>mob-</strong>
                (e.g. <code>desk-hero.jpg</code>, <code>mob-hero.jpg</code>). Uploading from Desktop / Mobile pickers applies this automatically.
            </p>
            <div id="s3fm-page-container" class="s3fm-page-mode"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    initEmbeddedFileManager('s3fm-page-container', 'ecommerce');
});
</script>
@endpush
