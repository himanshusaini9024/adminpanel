@extends('backend.layouts.master')
@section('title','E-SHOP || Edit Customer')

@section('main-content')
@php
$waPhone = preg_replace('/\D+/', '', (string) $customer->phone);
$waPhone = ltrim($waPhone, '0');
if (strlen($waPhone) === 10) { $waPhone = '91' . $waPhone; }
$defaultMsg = "Hi {$customer->full_name},\n\n"
. "You have items waiting in your cart. Complete your order before they're gone.\n\n"
. "Shop now: https://www.dhirago.com/";

@endphp

<div class="row">
    <div class="col-md-12">
        @include('backend.layouts.notification')
    </div>
</div>

<div class="mb-3">
    <a href="{{ route('customer.index') }}" class="btn btn-sm btn-secondary">&larr; Back to Customers</a>
</div>

<div class="row">
    {{-- Customer details --}}
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Customer Details #{{ $customer->customer_id }}</h6>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('customer.update', $customer->customer_id) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="{{ old('first_name', $customer->first_name) }}" required>
                            @error('first_name')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name', $customer->last_name) }}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $customer->email) }}">
                            @error('email')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            @php $st = old('status', $customer->status); @endphp
                            <option value="active" {{ $st === 'active' || $st === '1' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ $st === 'inactive' || $st === '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2">{{ old('address', $customer->address) }}</textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>City</label>
                            <input type="text" name="city" class="form-control" value="{{ old('city', $customer->city) }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label>State</label>
                            <input type="text" name="state" class="form-control" value="{{ old('state', $customer->state) }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label>ZIP</label>
                            <input type="text" name="zip" class="form-control" value="{{ old('zip', $customer->zip) }}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>New Password</label>
                            <input type="password" name="password" class="form-control" autocomplete="new-password">
                            @error('password')<span class="text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="form-group col-md-6">
                            <label>Confirm Password</label>
                            <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">Update Customer</button>
                </form>
            </div>
        </div>

        {{-- Saved addresses --}}
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Saved Addresses</h6>
            </div>
            <div class="card-body">
                @forelse($customer->addresses as $addr)
                <div class="border rounded p-3 mb-2">
                    <strong>{{ $addr->name }}</strong>
                    @if($addr->is_default)<span class="badge badge-primary">Default</span>@endif
                    <div class="small text-muted">{{ $addr->type }}</div>
                    <div>{{ $addr->address1 }} {{ $addr->address2 }}</div>
                    <div>{{ $addr->city }}, {{ $addr->state }} {{ $addr->pincode }}</div>
                    <div>{{ $addr->phone }}</div>
                </div>
                @empty
                <p class="text-muted mb-0">No saved addresses.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        {{-- Cart --}}
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Customer Cart</h6>
                @if(count($cartItems))
                <span class="badge badge-info">{{ count($cartItems) }} item(s)</span>
                @endif
            </div>
            <div class="card-body">
                @if(count($cartItems))
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $cartTotal = 0; @endphp
                            @foreach($cartItems as $item)
                            @php
                            $line = ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
                            $cartTotal += $line;
                            $img = $item['image'] ?? null;
                            $imgUrl = $img ? media_url(is_string($img) ? $img : '') : asset('backend/img/thumbnail-default.jpg');
                            @endphp
                            <tr>
                                <td style="width:70px;">
                                    <img src="{{ $imgUrl }}" alt="" style="max-width:60px;max-height:60px;object-fit:cover;">
                                </td>
                                <td>
                                    <strong>{{ $item['name'] }}</strong>
                                    @if(!empty($item['sku']))<div class="small text-muted">SKU: {{ $item['sku'] }}</div>@endif
                                    @if(!empty($item['size']))<div class="small">Size: {{ $item['size'] }}</div>@endif
                                    @if(!empty($item['color']))<div class="small">Color: {{ $item['color'] }}</div>@endif
                                </td>
                                <td>{{ $item['quantity'] }}</td>
                                <td>₹{{ number_format($line, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right">Cart Total</th>
                                <th>₹{{ number_format($cartTotal, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @else
                <p class="text-muted mb-0">No products in this customer's cart.</p>
                @endif
            </div>
        </div>

        {{-- Send message --}}
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Send Reminder / Offer</h6>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('customer.message', $customer->customer_id) }}" id="customer-message-form">
                    @csrf
                    <div class="form-group">
                        <label>Channel <span class="text-danger">*</span></label>
                        <select name="channel" id="msg-channel" class="form-control" required>
                            <option value="email">Email only</option>
                            <option value="whatsapp">WhatsApp only</option>
                            <option value="both" selected>Email + WhatsApp</option>
                        </select>
                    </div>
                    <div class="form-group" id="subject-wrap">
                        <label>Email Subject</label>
                        <input type="text" name="subject" class="form-control" value="{{ old('subject', 'Complete your cart — special offer inside') }}">
                        @error('subject')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Message <span class="text-danger">*</span></label>
                        <textarea name="message" id="msg-body" class="form-control" rows="6" required>{{ old('message', $defaultMsg) }}</textarea>
                        @error('message')<span class="text-danger">{{ $message }}</span>@enderror
                     
                    </div>
                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                        @if($waPhone)
                        <a id="wa-open-link"
                            class="btn btn-success"
                            style="background:#25D366;border-color:#25D366;"
                            target="_blank"
                            rel="noopener"
                            href="https://wa.me/{{ $waPhone }}?text={{ rawurlencode($defaultMsg) }}">
                            <i class="fab fa-whatsapp"></i> Open WhatsApp
                        </a>
                        @else
                        <button type="button" class="btn btn-secondary" disabled>No phone for WhatsApp</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- Send browser push --}}
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Send Browser Push</h6>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('customer.push', $customer->customer_id) }}">
                    @csrf
                    <div class="form-group">
                        <label>Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="{{ old('title', 'Dhirago') }}" maxlength="120" required>
                    </div>
                    <div class="form-group">
                        <label>Message <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control" rows="3" maxlength="500" required>{{ old('body', 'We have a special update for you.') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Large image URL (optional)</label>
                        <input type="url" name="image" class="form-control" value="{{ old('image') }}" placeholder="https://images.dhirago.com/...">
                    </div>
                    <div class="form-group">
                        <label>Click URL (optional)</label>
                        <input type="url" name="url" class="form-control" value="{{ old('url', config('services.webpush.storefront_url', 'https://dhirago.com')) }}">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-bell"></i> Send Push
                    </button>
                </form>
            </div>
        </div>

        {{-- Recent orders --}}
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
            </div>
            <div class="card-body">
                @if($orders->count())
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                            <tr>
                                <td>{{ $order->order_number }}</td>
                                <td>₹{{ number_format($order->total_amount, 2) }}</td>
                                <td><span class="badge badge-secondary">{{ $order->status }}</span></td>
                                <td>
                                    <a href="{{ route('order.show', $order->id) }}" class="btn btn-sm btn-link">View</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted mb-0">No orders yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function() {
        var channel = document.getElementById('msg-channel');
        var subjectWrap = document.getElementById('subject-wrap');
        var body = document.getElementById('msg-body');
        var waLink = document.getElementById('wa-open-link');
        var waPhone = @json($waPhone ? : '');

        function syncSubject() {
            if (!channel || !subjectWrap) return;
            subjectWrap.style.display = (channel.value === 'whatsapp') ? 'none' : '';
        }

        function syncWa() {
            if (!waLink || !waPhone || !body) return;
            waLink.href = 'https://wa.me/' + waPhone + '?text=' + encodeURIComponent(body.value || '');
        }
        if (channel) channel.addEventListener('change', syncSubject);
        if (body) body.addEventListener('input', syncWa);
        syncSubject();
        syncWa();
    })();
</script>
@endpush