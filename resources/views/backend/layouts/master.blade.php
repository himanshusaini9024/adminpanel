<!DOCTYPE html>
<html lang="en">

@include('backend.layouts.head')
{{-- before closing </body> --}}
<script src="https://upload-widget.cloudinary.com/global/all.js"></script>
<script src="https://media-library.cloudinary.com/global/all.js"></script>
<script src="{{ asset('js/cloudinary.js') }}"></script>

@stack('scripts')
<body id="page-top">

  <!-- Page Wrapper -->
  <div id="wrapper">

    <!-- Sidebar -->
       @include('backend.layouts.sidebar')
    <!-- End of Sidebar -->

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

      <!-- Main Content -->
      <div id="content">

        <!-- Topbar -->
        @include('backend.layouts.header')
        <!-- End of Topbar -->

        <!-- Begin Page Content -->
        @yield('main-content')
        <!-- /.container-fluid -->

      </div>
      <!-- End of Main Content -->
      @include('backend.layouts.footer')

</body>

</html>
