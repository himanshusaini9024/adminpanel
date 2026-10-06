@extends('backend.layouts.master')

@section('title','Order Detail')

@section('main-content')
<div class="card">
  <h5 class="card-header">Order Edit</h5>
  <div class="card-body">
    <form action="{{route('order.update',$order->id)}}" method="POST">
      @csrf
      @method('PATCH')
      <div class="form-group">
        <label for="status">Status :</label>
        <select name="status" id="status" class="form-control">
          @foreach(\App\Models\Order::STATUS_LABELS as $value => $label)
            <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
          @endforeach
        </select>
        <small class="form-text text-muted">
          Shiprocket updates this automatically. Change it by hand only to correct it.
          Setting "Delivery attempt failed" or "Returning to warehouse (RTO)" notifies the customer.
        </small>
      </div>
      <button type="submit" class="btn btn-primary">Update</button>
    </form>
  </div>
</div>
@endsection

@push('styles')
<style>
    .order-info,.shipping-info{
        background:#ECECEC;
        padding:20px;
    }
    .order-info h4,.shipping-info h4{
        text-decoration: underline;
    }

</style>
@endpush
