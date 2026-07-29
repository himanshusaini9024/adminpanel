@extends('backend.layouts.master')
@section('title','E-SHOP || File Manager')
@section('main-content')
<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-folder-open"></i> S3 File Manager
            </h6>
        </div>
        <div class="card-body p-0">
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
