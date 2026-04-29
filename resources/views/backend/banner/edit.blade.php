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
        <label for="inputPhoto" class="col-form-label">Photo <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-btn">
            <button type="button" id="upload_widget" class="btn btn-primary">
              <i class="fa fa-cloud-upload"></i> Upload Image
            </button>
          </span>
          <input id="thumbnail" class="form-control" type="text" name="photo[]" value="{{$banner->photo}}">
        </div>
        <div id="holder" style="margin-top:15px;"></div>
        @php
        $photos = json_decode($banner->photo, true);
        @endphp

        @if($photos)
        @foreach($photos as $img)
        <div style="display:inline-block;margin-right:10px;">
          <img src="{{$img}}" style="max-height:100px;">
          <input type="hidden" name="photo[]" value="{{$img}}">
        </div>
        @endforeach
        @endif
        @error('photo')
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
<script src="https://widget.cloudinary.com/v2.0/global/all.js"></script>
<script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
<script src="{{asset('backend/summernote/summernote.min.js')}}"></script>
<script>
  // $('#lfm').filemanager('image');

  $(document).ready(function() {
    $('#description').summernote({
      placeholder: "Write short description.....",
      tabsize: 2,
      height: 150
    });
  });
</script>

<script>
  document.addEventListener("DOMContentLoaded", function() {

    const btn = document.getElementById("upload_widget");

    if (!btn) {
      console.error("Button not found");
      return;
    }

    btn.addEventListener("click", function() {

      if (typeof cloudinary === "undefined") {
        console.error("Cloudinary not loaded");
        return;
      }

      const titleInput =
        document.getElementById('inputTitle') ||
        document.querySelector('[name="title"]');

      const title = titleInput ? titleInput.value.trim() : 'banner';
      const slug = title.toLowerCase().replace(/[^a-z0-9]/g, '') || 'banner';
      const folder = "ecommerce/" + slug;

      fetch(`/admin/cloudinary-signature?folder=${encodeURIComponent(folder)}`)
        .then(res => res.json())
        .then(data => {

          const widget = cloudinary.createUploadWidget({
            cloudName: data.cloudName,
            apiKey: data.apiKey,
            uploadSignature: data.signature,
            uploadSignatureTimestamp: data.timestamp,
            folder: folder,
            multiple: true,
            use_filename: true,
            unique_filename: false,
          }, (error, result) => {

            if (error) {
              console.error(error);
              return;
            }

            if (result.event === "success") {
              addImageField(result.info.secure_url);
            }
          });

          widget.open();
        })
        .catch(err => console.error(err));
    });

  });

  function addImageField(url) {
    const container = document.getElementById('holder');

    // create image preview
    const imgWrapper = document.createElement('div');
    imgWrapper.style.display = "inline-block";
    imgWrapper.style.marginRight = "10px";

    imgWrapper.innerHTML = `
        <img src="${url}" style="max-height:100px; display:block;">
        <button type="button" onclick="this.parentElement.remove()" style="margin-top:5px;">Remove</button>
        <input type="hidden" name="photo[]" value="${url}">
    `;

    container.appendChild(imgWrapper);
  }
</script>
@endpush