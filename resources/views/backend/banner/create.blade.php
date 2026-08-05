@extends('backend.layouts.master')

@section('title','E-SHOP || Banner Create')

@section('main-content')

<div class="card">
    <h5 class="card-header">Add Banner</h5>
    <div class="card-body">
        <form method="post" action="{{route('banner.store')}}">
            {{csrf_field()}}
            <div class="form-group">
                <label for="inputTitle" class="col-form-label">Title <span class="text-danger">*</span></label>
                <input id="inputTitle" type="text" name="title" placeholder="Enter title" value="{{old('title')}}"
                    class="form-control">
                @error('title')
                <span class="text-danger">{{$message}}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="inputDesc" class="col-form-label">Description</label>
                <textarea class="form-control" id="description" name="description">{{old('description')}}</textarea>
                @error('description')
                <span class="text-danger">{{$message}}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="inputPhoto" class="col-form-label">Desktop Photo <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-btn">
                        <button type="button" id="upload_widget" class="btn btn-primary">
                            <i class="fa fa-desktop"></i> Choose Desktop
                        </button>
                    </span>
                    <input id="thumbnail" class="form-control" type="text" name="photo" value="{{old('photo')}}">
                </div>
                <div id="holder" style="margin-top:15px;max-height:100px;"></div>
                @error('photo')
                <span class="text-danger">{{$message}}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="inputPhotoMobile" class="col-form-label">Mobile Photo</label>
                <div class="input-group">
                    <span class="input-group-btn">
                        <button type="button" id="upload_widget_mobile" class="btn btn-info">
                            <i class="fa fa-mobile"></i> Choose Mobile
                        </button>
                    </span>
                    <input id="thumbnail_mobile" class="form-control" type="text" name="photo_mobile" value="{{old('photo_mobile')}}">
                </div>
                <div id="holder_mobile" style="margin-top:15px;max-height:100px;"></div>
                @error('photo_mobile')
                <span class="text-danger">{{$message}}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="status" class="col-form-label">Status <span class="text-danger">*</span></label>
                <select name="status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                @error('status')
                <span class="text-danger">{{$message}}</span>
                @enderror
            </div>
            <div class="form-group mb-3">
                <button type="reset" class="btn btn-warning">Reset</button>
                <button class="btn btn-success" type="submit">Submit</button>
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

    initS3SingleUpload({
        buttonId: 'upload_widget',
        inputId: 'thumbnail',
        holderId: 'holder',
        namePrefix: 'desk',
        folderBase: function () {
            var titleInput = document.getElementById('inputTitle');
            var title = titleInput ? titleInput.value.trim() : 'banner';
            return 'ecommerce/banners/' + slugifyName(title, 'banner') + '/images';
        },
        multiple: false
    });

    initS3SingleUpload({
        buttonId: 'upload_widget_mobile',
        inputId: 'thumbnail_mobile',
        holderId: 'holder_mobile',
        namePrefix: 'mob',
        folderBase: function () {
            var titleInput = document.getElementById('inputTitle');
            var title = titleInput ? titleInput.value.trim() : 'banner';
            return 'ecommerce/banners/' + slugifyName(title, 'banner') + '/images';
        },
        multiple: false
    });
</script>
@endpush
