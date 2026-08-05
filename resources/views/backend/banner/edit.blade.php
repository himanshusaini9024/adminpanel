@extends('backend.layouts.master')
@section('title','E-SHOP || Banner Edit')
@section('main-content')

<div class="card">
  <h5 class="card-header">Edit Banner</h5>
  <div class="card-body">
    <form method="post" action="{{route('banner.update',$banner->id)}}">
      @csrf
      @method('PATCH')
      <div class="form-group">
        <label for="inputTitle" class="col-form-label">Title <span class="text-danger">*</span></label>
        <input id="inputTitle" type="text" name="title" placeholder="Enter title" value="{{$banner->title}}" class="form-control">
        @error('title')
        <span class="text-danger">{{$message}}</span>
        @enderror
      </div>

      <div class="form-group">
        <label for="inputDesc" class="col-form-label">Description</label>
        <textarea class="form-control" id="description" name="description">{{$banner->description}}</textarea>
        @error('description')
        <span class="text-danger">{{$message}}</span>
        @enderror
      </div>

      <div class="form-group">
        <label class="col-form-label">Desktop Images <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-btn">
            <button type="button" id="upload_widget" class="btn btn-primary">
              <i class="fa fa-desktop"></i> Add Desktop Image
            </button>
          </span>
        </div>
        <div id="holder" style="margin-top:15px;">
          @php
            $photos = is_array($banner->photo) ? $banner->photo : json_decode($banner->photo, true);
            if (!is_array($photos)) $photos = $banner->photo ? [$banner->photo] : [];
          @endphp
          @foreach($photos as $img)
            <div class="banner-img-item" style="display:inline-block;margin-right:10px;position:relative;">
              <img src="{{ media_url($img) }}" style="max-height:100px;">
              <button type="button" onclick="this.parentElement.remove()" style="position:absolute;top:0;right:0;background:#ef4444;color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:12px;">✕</button>
              <input type="hidden" name="photo[]" value="{{ media_path($img) }}">
            </div>
          @endforeach
        </div>
        @error('photo')
        <span class="text-danger">{{$message}}</span>
        @enderror
      </div>

      <div class="form-group">
        <label class="col-form-label">Mobile Images</label>
        <div class="input-group">
          <span class="input-group-btn">
            <button type="button" id="upload_widget_mobile" class="btn btn-info">
              <i class="fa fa-mobile"></i> Add Mobile Image
            </button>
          </span>
        </div>
        <div id="holder_mobile" style="margin-top:15px;">
          @php
            $mobilePhotos = is_array($banner->photo_mobile) ? $banner->photo_mobile : json_decode($banner->photo_mobile, true);
            if (!is_array($mobilePhotos)) $mobilePhotos = $banner->photo_mobile ? [$banner->photo_mobile] : [];
          @endphp
          @foreach($mobilePhotos as $img)
            <div class="banner-img-item" style="display:inline-block;margin-right:10px;position:relative;">
              <img src="{{ media_url($img) }}" style="max-height:100px;">
              <button type="button" onclick="this.parentElement.remove()" style="position:absolute;top:0;right:0;background:#ef4444;color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:12px;">✕</button>
              <input type="hidden" name="photo_mobile[]" value="{{ media_path($img) }}">
            </div>
          @endforeach
        </div>
        @error('photo_mobile')
        <span class="text-danger">{{$message}}</span>
        @enderror
      </div>

      <div class="form-group">
        <label for="status" class="col-form-label">Status <span class="text-danger">*</span></label>
        <select name="status" class="form-control">
          <option value="active" {{(($banner->status=='active') ? 'selected' : '')}}>Active</option>
          <option value="inactive" {{(($banner->status=='inactive') ? 'selected' : '')}}>Inactive</option>
        </select>
        @error('status')
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
@endpush
@push('scripts')
<script src="{{asset('backend/summernote/summernote.min.js')}}"></script>
<script>
  $(document).ready(function() {
    $('#description').summernote({
      placeholder: "Write short description.....",
      tabsize: 2,
      height: 150
    });
  });

  function addBannerImage(url, holderId, inputName) {
    var container = document.getElementById(holderId);
    var wrapper = document.createElement('div');
    wrapper.className = 'banner-img-item';
    wrapper.style.cssText = 'display:inline-block;margin-right:10px;position:relative;';
    wrapper.innerHTML =
      '<img src="' + toPublicUrl(url) + '" style="max-height:100px;">' +
      '<button type="button" onclick="this.parentElement.remove()" style="position:absolute;top:0;right:0;background:#ef4444;color:#fff;border:none;border-radius:50%;width:22px;height:22px;cursor:pointer;font-size:12px;">✕</button>' +
      '<input type="hidden" name="' + inputName + '" value="' + toStoragePath(url) + '">';
    container.appendChild(wrapper);
  }

  function bannerFolder() {
    var titleInput = document.getElementById('inputTitle');
    var title = titleInput ? titleInput.value.trim() : 'banner';
    return 'ecommerce/banners/' + slugifyName(title, 'banner') + '/images';
  }

  document.getElementById('upload_widget').addEventListener('click', function(e) {
    e.preventDefault();
    openS3FileManager({
      path: bannerFolder(),
      multiple: true,
      namePrefix: 'desk',
      onSelect: function(items) {
        (items || []).forEach(function(item) {
          addBannerImage(item.path || item.url, 'holder', 'photo[]');
        });
      }
    });
  });

  document.getElementById('upload_widget_mobile').addEventListener('click', function(e) {
    e.preventDefault();
    openS3FileManager({
      path: bannerFolder(),
      multiple: true,
      namePrefix: 'mob',
      onSelect: function(items) {
        (items || []).forEach(function(item) {
          addBannerImage(item.path || item.url, 'holder_mobile', 'photo_mobile[]');
        });
      }
    });
  });
</script>
@endpush
