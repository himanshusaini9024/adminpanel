<!DOCTYPE html>
<html lang="en">

@include('backend.layouts.head')

<body id="page-top">

  <div id="wrapper">
    @include('backend.layouts.sidebar')

    <div id="content-wrapper">
      <div id="content">
        @include('backend.layouts.header')
        @yield('main-content')
      </div>

      @include('backend.layouts.footer')
