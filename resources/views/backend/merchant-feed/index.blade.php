@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="row">
        <div class="col-md-12">
            @include('backend.layouts.notification')
        </div>
    </div>
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Google Merchant Center Feed</h6>
    </div>
    <div class="card-body">
        @if(!$configured)
            <div class="alert alert-warning">
                <strong>Feed token not set.</strong>
                Add this line to your Laravel <code>.env</code>, then run <code>php artisan config:clear</code>:
                <pre class="mb-0 mt-2 bg-light p-2">MERCHANT_FEED_TOKEN={{ $suggestedToken }}</pre>
            </div>
        @else
            <div class="alert alert-success">
                Feed is ready. Google Merchant Center can fetch it on a schedule so large catalogs and frequent product edits stay in sync.
            </div>
        @endif

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="h3 mb-0 text-primary">{{ $feedCount }}</div>
                    <small class="text-muted">Products in feed</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="h3 mb-0">{{ $activeCount }}</div>
                    <small class="text-muted">Active products</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="h3 mb-0 text-warning">{{ $skipped }}</div>
                    <small class="text-muted">Skipped (no image / slug)</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="h5 mb-0">{{ $country }} / {{ $currency }}</div>
                    <small class="text-muted">{{ $brand }} · {{ $storefront }}</small>
                </div>
            </div>
        </div>

        <h6 class="font-weight-bold">Feed URLs</h6>
        <p class="text-muted small">Use the XML feed for Google scheduled fetch. TSV is optional (manual upload). JSON is for debugging.</p>

        <div class="form-group">
            <label>XML feed (recommended)</label>
            <div class="input-group">
                <input type="text" class="form-control" id="xml-url" readonly value="{{ $xmlUrl ?: 'Set MERCHANT_FEED_TOKEN first' }}">
                @if($xmlUrl)
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary js-copy" data-target="#xml-url">Copy</button>
                        <a class="btn btn-outline-primary" href="{{ $xmlUrl }}" target="_blank" rel="noopener">Open</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="form-group">
            <label>TSV feed</label>
            <div class="input-group">
                <input type="text" class="form-control" id="tsv-url" readonly value="{{ $tsvUrl ?: 'Set MERCHANT_FEED_TOKEN first' }}">
                @if($tsvUrl)
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary js-copy" data-target="#tsv-url">Copy</button>
                        <a class="btn btn-outline-primary" href="{{ $tsvUrl }}" target="_blank" rel="noopener">Open</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="form-group">
            <label>JSON preview</label>
            <div class="input-group">
                <input type="text" class="form-control" id="json-url" readonly value="{{ $jsonUrl ?: 'Set MERCHANT_FEED_TOKEN first' }}">
                @if($jsonUrl)
                    <div class="input-group-append">
                        <button type="button" class="btn btn-outline-secondary js-copy" data-target="#json-url">Copy</button>
                        <a class="btn btn-outline-primary" href="{{ $jsonUrl }}" target="_blank" rel="noopener">Open</a>
                    </div>
                @endif
            </div>
        </div>

        <hr>

        <h6 class="font-weight-bold">Connect in Google Merchant Center</h6>
        <ol class="mb-0">
            <li>Open <a href="https://merchants.google.com/" target="_blank" rel="noopener">Google Merchant Center</a> → <strong>Products</strong> → <strong>Feeds</strong>.</li>
            <li>Click <strong>Add feed</strong> (or <strong>Add primary feed</strong>).</li>
            <li>Country: <strong>India</strong>, Language: <strong>English</strong>.</li>
            <li>Input method: <strong>Scheduled fetch</strong>.</li>
            <li>Paste the <strong>XML feed URL</strong> above (keep the <code>token=</code> query string).</li>
            <li>Fetch frequency: daily (or more often if available) so price, stock and title edits sync automatically.</li>
            <li>Save, then use <strong>Fetch now</strong> to test. Check Diagnostics for any attribute issues.</li>
        </ol>

        <hr>
        <p class="small text-muted mb-0">
            Only <strong>active</strong> products with a slug and at least one image are included.
            Selling price follows your admin pricing rules (discount / special price).
            Products without GTIN are sent with <code>identifier_exists=no</code> and MPN = SKU.
            After product edits, Google picks them up on the next feed fetch — no manual re-upload needed.
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('.js-copy').on('click', function () {
        var $input = $($(this).data('target'));
        $input.trigger('select');
        document.execCommand('copy');
        var $btn = $(this);
        var old = $btn.text();
        $btn.text('Copied');
        setTimeout(function () { $btn.text(old); }, 1200);
    });
});
</script>
@endpush
