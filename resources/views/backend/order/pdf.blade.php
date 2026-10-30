<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Tax Invoice #{{ $order->order_number }}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 18px; }
    .center { text-align: center; }
    .brand { font-size: 22px; font-weight: bold; letter-spacing: 1px; margin: 0 0 4px; }
    .title-wrap { border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 6px 0; margin: 8px 0 14px; }
    .title { font-size: 14px; font-weight: bold; margin: 0; letter-spacing: 1px; }
    table { width: 100%; border-collapse: collapse; }
    .meta td { vertical-align: top; padding: 0 8px 0 0; width: 33.33%; }
    .box-title { font-size: 10px; font-weight: bold; text-transform: uppercase; margin: 0 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
    .line { margin: 1px 0; line-height: 1.35; }
    .strong { font-weight: bold; }
    .items { margin-top: 14px; }
    .items th { border: 1px solid #000; background: #f3f3f3; padding: 5px 4px; font-size: 9px; text-transform: uppercase; }
    .items td { border: 1px solid #000; padding: 5px 4px; font-size: 9px; vertical-align: top; }
    .right { text-align: right; }
    .net { margin-top: 10px; font-size: 12px; font-weight: bold; text-align: right; }
    .note { margin-top: 8px; font-size: 9px; }
    .sign { margin-top: 28px; width: 220px; border: 1px solid #000; height: 70px; text-align: center; padding-top: 48px; font-size: 9px; }
    .small { font-size: 9px; color: #333; }
  </style>
</head>
<body>
@php
  use App\Support\IndianStateCode;

  $company = $company ?? config('invoice');
  $items = $order->items ?? collect();
  $gstRate = (float) ($company['gst_rate'] ?? 5);
  $hsn = $company['hsn'] ?? '6205';
  $sellerStateCode = (string) ($company['state_code'] ?? '08');
  $buyerStateCode = IndianStateCode::fromState($order->state);
  $isInterState = $buyerStateCode === '' || $buyerStateCode !== $sellerStateCode;

  $discountTotal = (float) ($order->coupon ?? 0);
  $grossItems = (float) $items->sum(fn ($i) => (float) $i->price * (int) $i->quantity);
  $itemCount = max(1, (int) $items->sum('quantity'));

  $rows = [];
  $sumTaxable = 0;
  $sumTax = 0;
  $sumTotal = 0;

  foreach ($items as $index => $item) {
      $qty = max(1, (int) $item->quantity);
      $unitPrice = (float) $item->price;
      $lineGross = $unitPrice * $qty;
      $lineDiscount = $grossItems > 0 ? round($discountTotal * ($lineGross / $grossItems), 2) : 0;
      $unitDiscount = $qty > 0 ? round($lineDiscount / $qty, 2) : 0;
      $lineNet = max(0, $lineGross - $lineDiscount);
      $taxable = $gstRate > 0 ? round($lineNet / (1 + ($gstRate / 100)), 2) : $lineNet;
      $tax = round($lineNet - $taxable, 2);

      $rows[] = [
          'sno' => $index + 1,
          'name' => $item->name,
          'sku' => $item->sku,
          'hsn' => $hsn,
          'qty' => $qty,
          'unit_price' => $unitPrice,
          'unit_discount' => $unitDiscount,
          'taxable' => $taxable,
          'tax' => $tax,
          'total' => $lineNet,
      ];

      $sumTaxable += $taxable;
      $sumTax += $tax;
      $sumTotal += $lineNet;
  }

  $invoiceNo = 'INV-' . $order->order_number;
  $paymentLabel = $order->payment_method_label ?? strtoupper((string) $order->payment_method);
  $country = $order->country;
  if (!$country || strtoupper((string) $country) === 'IND') {
      $country = 'India';
  }
@endphp

  <div class="center">
    <p class="brand">{{ $company['company_name'] ?? 'Dhirago' }}</p>
  </div>
  <div class="title-wrap center">
    <p class="title">TAX INVOICE</p>
  </div>

  <table class="meta">
    <tr>
      <td>
        <p class="box-title">Shipping Address</p>
        <p class="line strong">{{ trim($order->first_name . ' ' . $order->last_name) }}</p>
        @if($order->address1)<p class="line">{{ $order->address1 }}</p>@endif
        @if($order->address2)<p class="line">{{ $order->address2 }}</p>@endif
        <p class="line">{{ collect([$order->city, $order->post_code])->filter()->implode(' ') }}</p>
        <p class="line">{{ collect([$order->state, $country])->filter()->implode(', ') }}</p>
        @if($buyerStateCode)<p class="line"><span class="strong">State Code:</span> {{ $buyerStateCode }}</p>@endif
        @if($order->phone)<p class="line small">Ph: {{ $order->phone }}</p>@endif
        @if($order->email)<p class="line small">{{ $order->email }}</p>@endif
      </td>
      <td>
        <p class="box-title">Sold By</p>
        <p class="line strong">{{ $company['company_name'] ?? 'Dhirago' }}</p>
        <p class="line">{{ $company['address'] ?? '' }}</p>
        <p class="line">{{ collect([$company['state'] ?? null, $company['country'] ?? 'India'])->filter()->implode(', ') }}</p>
        <p class="line"><span class="strong">State Code:</span> {{ $sellerStateCode }}</p>
        @if(!empty($company['phone']))<p class="line"><span class="strong">Ph:</span> {{ $company['phone'] }}</p>@endif
        <p class="line"><span class="strong">GSTIN No:</span> {{ $company['gstin'] ?: '—' }}</p>
        @if(!empty($company['email']))<p class="line"><span class="strong">Email:</span> {{ $company['email'] }}</p>@endif
      </td>
      <td>
        <p class="box-title">Invoice Details</p>
        <p class="line"><span class="strong">Invoice No:</span> {{ $invoiceNo }}</p>
        <p class="line"><span class="strong">Invoice Date:</span> {{ optional($order->created_at)->format('d/m/Y') }}</p>
        <p class="line"><span class="strong">Order No:</span> {{ $order->order_number }}</p>
        <p class="line"><span class="strong">Order Date:</span> {{ optional($order->created_at)->format('d/m/Y') }}</p>
        <p class="line"><span class="strong">Channel:</span> {{ $order->order_source ? strtoupper($order->order_source) : 'WEBSITE' }}</p>
        @if($order->courier_name)
          <p class="line"><span class="strong">Shipped By:</span> {{ $order->courier_name }}</p>
        @endif
        @if($order->awb_code)
          <p class="line"><span class="strong">AWB No:</span> {{ $order->awb_code }}</p>
        @endif
        <p class="line"><span class="strong">Payment Method:</span> {{ $paymentLabel }}</p>
        <p class="line"><span class="strong">Payment Status:</span> {{ strtoupper((string) $order->payment_status) }}</p>
        @if($order->admin_note)
          <p class="line"><span class="strong">Remark:</span> {{ $order->admin_note }}</p>
        @endif
      </td>
    </tr>
  </table>

  <table class="items">
    <thead>
      <tr>
        <th style="width:4%">S.No</th>
        <th style="width:28%">Product Name</th>
        <th style="width:8%">HSN</th>
        <th style="width:6%" class="right">Qty</th>
        <th style="width:10%" class="right">Unit Price</th>
        <th style="width:10%" class="right">Unit Discount</th>
        <th style="width:11%" class="right">Taxable Value</th>
        <th style="width:11%" class="right">{{ $isInterState ? 'IGST' : 'CGST+SGST' }} ({{ number_format($gstRate, 2) }}%)</th>
        <th style="width:12%" class="right">Total (Incl. GST)</th>
      </tr>
    </thead>
    <tbody>
      @foreach($rows as $row)
        <tr>
          <td>{{ $row['sno'] }}</td>
          <td>
            <span class="strong">{{ $row['name'] }}</span><br>
            <span class="small">SKU: {{ $row['sku'] }}</span>
          </td>
          <td>{{ $row['hsn'] }}</td>
          <td class="right">{{ $row['qty'] }}</td>
          <td class="right">{{ number_format($row['unit_price'], 2) }}</td>
          <td class="right">{{ number_format($row['unit_discount'], 2) }}</td>
          <td class="right">{{ number_format($row['taxable'], 2) }}</td>
          <td class="right">{{ number_format($row['tax'], 2) }}</td>
          <td class="right">{{ number_format($row['total'], 2) }}</td>
        </tr>
      @endforeach
      <tr>
        <td colspan="6" class="right strong">TOTAL</td>
        <td class="right strong">{{ number_format($sumTaxable, 2) }}</td>
        <td class="right strong">{{ number_format($sumTax, 2) }}</td>
        <td class="right strong">{{ number_format((float) $order->total_amount, 2) }}</td>
      </tr>
    </tbody>
  </table>

  <p class="net">NET TOTAL : Rs. {{ number_format((float) $order->total_amount, 2) }}</p>
  <p class="note">Whether tax is payable under reverse charge - No</p>

  <div class="sign">
    Authorized Signature for {{ $company['company_name'] ?? 'Dhirago' }}
  </div>
</body>
</html>
