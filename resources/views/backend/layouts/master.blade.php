<!DOCTYPE html>
<html lang="en">

@include('backend.layouts.head')
{{-- before closing </body> --}}


<body id="page-top">

  <div id="wrapper">
    @include('backend.layouts.sidebar')

    <div id="content-wrapper">
      <div id="content">
        @include('backend.layouts.header')
        @yield('main-content')
      </div>

      @include('backend.layouts.footer')
    </div>
  </div>

  <!-- ✅ Load Cloudinary FIRST -->
  <script src="https://upload-widget.cloudinary.com/global/all.js"></script>
  <script src="https://media-library.cloudinary.com/global/all.js"></script>
  <script src="{{ asset('js/cloudinary.js') }}"></script>

  <!-- ✅ Then page scripts -->
  @stack('scripts')

</body>

</html>
