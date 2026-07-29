@extends('backend.layouts.master')

@section('main-content')

<div class="card">
    <h5 class="card-header">Settings</h5>
    <div class="card-body">
    <form method="post" action="{{route('settings.update')}}">
        @csrf
        <div class="form-group">
          <label for="short_des" class="col-form-label">Short Description <span class="text-danger">*</span></label>
          <textarea class="form-control" id="quote" name="short_des">{{$data->short_des}}</textarea>
          @error('short_des')
          <span class="text-danger">{{$message}}</span>
          @enderror
        </div>
        <div class="form-group">
          <label for="description" class="col-form-label">Description <span class="text-danger">*</span></label>
          <textarea class="form-control" id="description" name="description">{{$data->description}}</textarea>
          @error('description')
          <span class="text-danger">{{$message}}</span>
          @enderror
        </div>

        <div class="form-group">
          <label for="inputPhoto" class="col-form-label">Logo <span class="text-danger">*</span></label>
          <div class="input-group">
              <span class="input-group-btn">
                  <button type="button" id="logo_btn" class="btn btn-primary">
                    <i class="fa fa-picture-o"></i> Choose
                  </button>
              </span>
          <input id="logo_input" class="form-control" type="text" name="logo" value="{{$data->logo}}">
        </div>
        <div id="logo_holder" style="margin-top:15px;max-height:100px;">
          @if($data->logo)
            <img src="{{ media_url($data->logo) }}" style="height:80px;">
          @endif
        </div>
          @error('logo')
          <span class="text-danger">{{$message}}</span>
          @enderror
        </div>

        <div class="form-group">
          <label for="inputPhoto" class="col-form-label">Photo <span class="text-danger">*</span></label>
          <div class="input-group">
              <span class="input-group-btn">
                  <button type="button" id="photo_btn" class="btn btn-primary">
                    <i class="fa fa-picture-o"></i> Choose
                  </button>
              </span>
          <input id="photo_input" class="form-control" type="text" name="photo" value="{{$data->photo}}">
        </div>
        <div id="photo_holder" style="margin-top:15px;max-height:100px;">
          @if($data->photo)
            <img src="{{ media_url($data->photo) }}" style="height:80px;">
          @endif
        </div>
          @error('photo')
          <span class="text-danger">{{$message}}</span>
          @enderror
        </div>

        <div class="form-group">
          <label for="address" class="col-form-label">Address <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="address" required value="{{$data->address}}">
          @error('address')
          <span class="text-danger">{{$message}}</span>
          @enderror
        </div>
        <div class="form-group">
          <label for="email" class="col-form-label">Email <span class="text-danger">*</span></label>
          <input type="email" class="form-control" name="email" required value="{{$data->email}}">
          @error('email')
          <span class="text-danger">{{$message}}</span>
          @enderror
        </div>
        <div class="form-group">
          <label for="phone" class="col-form-label">Phone Number <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="phone" required value="{{$data->phone}}">
          @error('phone')
          <span class="text-danger">{{$message}}</span>
          @enderror
        </div>

        <div class="form-group mb-3">
           <button class="btn btn-success" type="submit">Update</button>
        </div>
      </form>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{asset('backend/summernote/summernote.min.css')}}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/css/bootstrap-select.css" />
@endpush
@push('scripts')
<script src="{{asset('backend/summernote/summernote.min.js')}}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/js/bootstrap-select.min.js"></script>

<script>
    $(document).ready(function() {
      $('#quote').summernote({ placeholder: "Write short Quote.....", tabsize: 2, height: 100 });
      $('#description').summernote({ placeholder: "Write detail description.....", tabsize: 2, height: 150 });
    });

    // Logo uploader
    initS3SingleUpload({
        buttonId: 'logo_btn',
        inputId: 'logo_input',
        holderId: 'logo_holder',
        folderBase: 'ecommerce/settings',
        multiple: false
    });

    // Photo uploader
    initS3SingleUpload({
        buttonId: 'photo_btn',
        inputId: 'photo_input',
        holderId: 'photo_holder',
        folderBase: 'ecommerce/settings',
        multiple: false
    });
</script>
@endpush
