@extends('backend.layouts.master')

@section('title', 'WhatsApp Notification')

@section('main-content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">WhatsApp Notification</h1>
    </div>

    @include('backend.layouts.notification')

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Bulk send offer / reminder</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Customers with phone:
                        <strong>{{ $withPhone }}</strong>
                        &nbsp;·&nbsp; Template:
                        <code>{{ $template }}</code>
                        @if(!$configOk)
                            <span class="badge badge-danger ml-2">WhatsApp not configured</span>
                        @endif
                    </p>

                    <form method="post" action="{{ route('whatsapp.send') }}" id="wa-bulk-form">
                        @csrf

                        <div class="form-group">
                            <label>Offer text (template @{{2}}) <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="4" maxlength="500" required>{{ old('message', 'Flat 20% off on new arrivals — shop today at dhirago.com') }}</textarea>
                            <small class="text-muted">Sent as: Hey @{{1}}! @{{2}} … Keep it short, no line breaks.</small>
                            @error('message')<span class="text-danger d-block">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label>Header image URL (JPEG/PNG)</label>
                            <input type="url" name="header_image" class="form-control"
                                   value="{{ old('header_image', $headerImage) }}"
                                   placeholder="https://images.dhirago.com/ecommerce/logo/logo.jpg">
                            <small class="text-muted">Public HTTPS image. Prefer <strong>JPEG/PNG</strong> — WebP often fails after Meta accepts the send.</small>
                            @error('header_image')<span class="text-danger d-block">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label>Send to <span class="text-danger">*</span></label>
                            <select name="audience" id="wa-audience" class="form-control" required>
                                <option value="all" {{ old('audience', 'selected') === 'all' ? 'selected' : '' }}>
                                    All customers with phone
                                </option>
                                <option value="selected" {{ old('audience', 'selected') === 'selected' ? 'selected' : '' }}>
                                    Selected customers
                                </option>
                                <option value="customer" {{ old('audience') === 'customer' ? 'selected' : '' }}>
                                    One customer
                                </option>
                            </select>
                        </div>

                        <div class="form-group" id="wa-one-wrap" style="display:none;">
                            <label>Customer</label>
                            <select name="customer_id" class="form-control">
                                <option value="">Select customer</option>
                                @foreach($customers as $customer)
                                    @php
                                        $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Customer';
                                    @endphp
                                    <option value="{{ $customer->customer_id }}"
                                        {{ (string) old('customer_id') === (string) $customer->customer_id ? 'selected' : '' }}
                                        @if(empty($customer->phone)) disabled @endif>
                                        #{{ $customer->customer_id }} — {{ $name }}
                                        @if($customer->phone)
                                            ({{ $customer->phone }})
                                        @else
                                            · no phone
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')<span class="text-danger d-block">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group" id="wa-multi-wrap" style="display:none;">
                            <label>Customers (hold Ctrl/Cmd to multi-select)</label>
                            <select name="customer_ids[]" class="form-control" multiple size="12">
                                @foreach($customers as $customer)
                                    @php
                                        $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Customer';
                                        $oldIds = old('customer_ids', []);
                                    @endphp
                                    <option value="{{ $customer->customer_id }}"
                                        {{ in_array((string) $customer->customer_id, array_map('strval', (array) $oldIds), true) ? 'selected' : '' }}
                                        @if(empty($customer->phone)) disabled @endif>
                                        #{{ $customer->customer_id }} — {{ $name }}
                                        @if($customer->phone) ({{ $customer->phone }}) @else · no phone @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_ids')<span class="text-danger d-block">{{ $message }}</span>@enderror
                        </div>

                        <button type="submit" class="btn btn-success"
                                onclick="return confirm('Send WhatsApp template to the selected audience now?')">
                            <i class="fab fa-whatsapp"></i> Send WhatsApp Now
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tips</h6>
                </div>
                <div class="card-body small text-muted">
                    <ul class="pl-3 mb-0">
                        <li class="mb-2">Uses Meta template <code>{{ $template }}</code> (marketing).</li>
                        <li class="mb-2">@{{1}} = customer name, @{{2}} = your offer text.</li>
                        <li class="mb-2">Accepted by API ≠ always delivered — check WhatsApp <strong>Updates</strong> tab.</li>
                        <li class="mb-2">In Meta Dev mode, only test numbers receive messages.</li>
                        <li>Bulk runs after the page responds; watch <code>storage/logs/laravel.log</code>.</li>
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
  var audience = document.getElementById('wa-audience');
  var oneWrap = document.getElementById('wa-one-wrap');
  var multiWrap = document.getElementById('wa-multi-wrap');
  if (!audience) return;
  function sync() {
    var v = audience.value;
    if (oneWrap) oneWrap.style.display = v === 'customer' ? '' : 'none';
    if (multiWrap) multiWrap.style.display = v === 'selected' ? '' : 'none';
  }
  audience.addEventListener('change', sync);
  sync();
})();
</script>
@endpush
