@props(['input', 'value' => null])
<div class="cloudinary-wrapper">
    <div class="mb-2">
        <button type="button" id="{{ $input }}_upload" class="btn btn-primary">
            Upload Image
        </button>

        <button type="button" id="{{ $input }}_library" class="btn btn-success">
            Choose From Library
        </button>
    </div>

    <input type="hidden" id="{{ $input }}" name="{{ $input }}" value="{{ $value ?? '' }}">

    <div id="{{ $input }}_preview" style="margin-top:10px;">
        @if(!empty($value))
            <img src="{{ $value }}" style="height:80px;">
        @endif
    </div>
</div>

@push('scripts')
<script>
(function () {

    function setImage_{{ $input }}(url) {
        document.getElementById('{{ $input }}').value = url;
        document.getElementById('{{ $input }}_preview').innerHTML =
            '<img src="' + url + '" style="height:80px;">';
    }

    // Upload Widget
    var uploadWidget_{{ $input }} = cloudinary.createUploadWidget({
        cloudName: 'ds48lk80f',
        uploadPreset: 'ecommerce_upload'
    }, (error, result) => {
        if (!error && result && result.event === "success") {
            setImage_{{ $input }}(result.info.secure_url);
        }
    });

    document.getElementById("{{ $input }}_upload").onclick = function () {
        uploadWidget_{{ $input }}.open();
    };

    // Media Library
    document.getElementById("{{ $input }}_library").onclick = function () {
        cloudinary.openMediaLibrary(
            {
                cloud_name: 'ds48lk80f',
                api_key: '867116123869985',
                multiple: false
            },
            {
                insertHandler: function (data) {
                    const url = data.assets[0].secure_url;
                    setImage_{{ $input }}(url);
                }
            }
        );
    };

})();
</script>
@endpush