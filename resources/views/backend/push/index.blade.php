@extends('backend.layouts.master')

@section('title', 'Send Push Notification')

@section('main-content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Send Push Notification</h1>
    </div>

    @include('backend.layouts.notification')

    <div class="row">
        <div class="col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Compose rich push (title + message + image)</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Active browser subscriptions: <strong>{{ $activeCount }}</strong>
                    </p>

                    <form method="post" action="{{ route('push.send') }}" id="push-form">
                        @csrf

                        <div class="form-group">
                            <label>Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="push-title" class="form-control"
                                   value="{{ old('title', 'Aprons') }}"
                                   maxlength="120" required>
                            @error('title')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label>Message <span class="text-danger">*</span></label>
                            <textarea name="body" id="push-body" class="form-control" rows="3" maxlength="500" required>{{ old('body', 'At Rs. 121!') }}</textarea>
                            @error('body')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label>Large image URL (banner)</label>
                            <input type="url" name="image" id="push-image" class="form-control"
                                   value="{{ old('image') }}"
                                   placeholder="https://images.dhirago.com/ecommerce/.../product.webp">
                            @error('image')<span class="text-danger">{{ $message }}</span>@enderror
                            <small class="text-muted">Full HTTPS image URL — shown as big banner (like Meesho).</small>
                        </div>

                        <div class="form-group">
                            <label>Small icon URL (optional)</label>
                            <input type="url" name="icon" id="push-icon" class="form-control"
                                   value="{{ old('icon') }}"
                                   placeholder="Leave empty to use large image / logo">
                            @error('icon')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label>Click URL (optional)</label>
                            <input type="url" name="url" class="form-control"
                                   value="{{ old('url', env('STOREFRONT_URL', 'https://dhirago.com')) }}"
                                   placeholder="https://dhirago.com/product/...">
                            @error('url')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label>Send to <span class="text-danger">*</span></label>
                            <select name="audience" id="push-audience" class="form-control" required>
                                <option value="all" {{ old('audience', 'all') === 'all' ? 'selected' : '' }}>
                                    All subscribed browsers
                                </option>
                                <option value="customer" {{ old('audience') === 'customer' ? 'selected' : '' }}>
                                    One customer
                                </option>
                            </select>
                        </div>

                        <div class="form-group" id="customer-wrap" style="display:none;">
                            <label>Customer</label>
                            <select name="customer_id" class="form-control">
                                <option value="">Select customer</option>
                                @foreach($customers as $customer)
                                    @php
                                        $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Customer';
                                    @endphp
                                    <option value="{{ $customer->customer_id }}"
                                        {{ (string) old('customer_id') === (string) $customer->customer_id ? 'selected' : '' }}>
                                        #{{ $customer->customer_id }} — {{ $name }}
                                        @if($customer->phone) ({{ $customer->phone }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary"
                                onclick="return confirm('Send this push notification now?')">
                            <i class="fas fa-bell"></i> Send Push Now
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Preview</h6>
                </div>
                <div class="card-body">
                    <div style="background:#f1f3f4;border-radius:12px;padding:12px;max-width:360px;">
                        <div style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.12);">
                            <div style="display:flex;gap:10px;padding:12px 12px 8px;">
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:11px;color:#5f6368;margin-bottom:4px;">Dhirago · now</div>
                                    <div id="preview-title" style="font-weight:700;font-size:14px;color:#202124;">Aprons</div>
                                    <div id="preview-body" style="font-size:13px;color:#3c4043;margin-top:2px;">At Rs. 121!</div>
                                </div>
                                <img id="preview-icon" src="" alt=""
                                     style="width:48px;height:48px;border-radius:6px;object-fit:cover;background:#eee;display:none;">
                            </div>
                            <img id="preview-image" src="" alt=""
                                 style="width:100%;height:160px;object-fit:cover;display:none;background:#eee;">
                        </div>
                    </div>
                    <ul class="mt-3 mb-0 pl-3 text-muted small">
                        <li class="mb-2">Large image shows best on <strong>Chrome / Android</strong>.</li>
                        <li class="mb-2">Use a wide product/banner image (HTTPS).</li>
                        <li>After updating, refresh the storefront once so the service worker updates.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var audience = document.getElementById('push-audience');
  var wrap = document.getElementById('customer-wrap');
  if (audience && wrap) {
    function syncAudience() {
      wrap.style.display = audience.value === 'customer' ? '' : 'none';
    }
    audience.addEventListener('change', syncAudience);
    syncAudience();
  }

  var titleEl = document.getElementById('push-title');
  var bodyEl = document.getElementById('push-body');
  var imageEl = document.getElementById('push-image');
  var iconEl = document.getElementById('push-icon');
  var pTitle = document.getElementById('preview-title');
  var pBody = document.getElementById('preview-body');
  var pImage = document.getElementById('preview-image');
  var pIcon = document.getElementById('preview-icon');

  function syncPreview() {
    if (pTitle) pTitle.textContent = titleEl.value || 'Dhirago';
    if (pBody) pBody.textContent = bodyEl.value || '';
    var image = (imageEl && imageEl.value) ? imageEl.value.trim() : '';
    var icon = (iconEl && iconEl.value) ? iconEl.value.trim() : image;
    if (pImage) {
      if (image) {
        pImage.src = image;
        pImage.style.display = 'block';
      } else {
        pImage.removeAttribute('src');
        pImage.style.display = 'none';
      }
    }
    if (pIcon) {
      if (icon) {
        pIcon.src = icon;
        pIcon.style.display = 'block';
      } else {
        pIcon.removeAttribute('src');
        pIcon.style.display = 'none';
      }
    }
  }

  [titleEl, bodyEl, imageEl, iconEl].forEach(function (el) {
    if (el) el.addEventListener('input', syncPreview);
  });
  syncPreview();
})();
</script>
@endpush
