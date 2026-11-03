@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="row">
        <div class="col-md-12">
            @include('backend.layouts.notification')
        </div>
    </div>
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Roles &amp; Access</h6>
    </div>
    <div class="card-body">
        <p class="text-muted">
            Assign which admin modules each role can open. Changes apply immediately.
            Assign a person to a role under <a href="{{ route('users.index') }}">Users</a>.
        </p>

        <ul class="nav nav-tabs" role="tablist">
            @foreach($labels as $role => $label)
                <li class="nav-item">
                    <a class="nav-link {{ $loop->first ? 'active' : '' }}"
                       id="tab-{{ $role }}"
                       data-toggle="tab"
                       href="#pane-{{ $role }}"
                       role="tab">{{ $label }}</a>
                </li>
            @endforeach
        </ul>

        <div class="tab-content border border-top-0 p-3">
            @foreach($labels as $role => $label)
                @php
                    $selected = $permissions[$role] ?? [];
                    $isAdmin = $role === 'admin';
                @endphp
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                     id="pane-{{ $role }}"
                     role="tabpanel">
                    @if($isAdmin)
                        <div class="alert alert-info mb-3">
                            <strong>Admin</strong> always has full access. This cannot be restricted.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('roles.update', $role) }}">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            @foreach($modules as $key => $name)
                                <div class="col-md-4 col-lg-3 mb-2">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox"
                                               class="custom-control-input"
                                               id="{{ $role }}_{{ $key }}"
                                               name="modules[]"
                                               value="{{ $key }}"
                                               {{ $isAdmin || in_array($key, $selected, true) ? 'checked' : '' }}
                                               {{ $isAdmin ? 'disabled' : '' }}>
                                        <label class="custom-control-label" for="{{ $role }}_{{ $key }}">
                                            {{ $name }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @unless($isAdmin)
                            <div class="mt-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary js-check-all" data-role="{{ $role }}">Select all</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary js-uncheck-all" data-role="{{ $role }}">Clear</button>
                                <button type="submit" class="btn btn-primary btn-sm ml-2">
                                    <i class="fas fa-save"></i> Save {{ $label }} access
                                </button>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Dashboard and Profile stay enabled so the user can log in and change their password.
                            </small>
                        @endunless
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    $('.js-check-all').on('click', function () {
        var role = $(this).data('role');
        $('#pane-' + role + ' input[type=checkbox]:not(:disabled)').prop('checked', true);
    });
    $('.js-uncheck-all').on('click', function () {
        var role = $(this).data('role');
        $('#pane-' + role + ' input[type=checkbox]:not(:disabled)').each(function () {
            var v = $(this).val();
            if (v !== 'dashboard' && v !== 'profile') {
                $(this).prop('checked', false);
            }
        });
    });
});
</script>
@endpush
