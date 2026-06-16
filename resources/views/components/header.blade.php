@extends('adminlte::page')

@section('content_header')
    <div class="row">
        <div class="col-sm-4">
            <h1 class="text-dark m-0">
                @yield('title')
            </h1>
        </div>
        <div class="col-sm-8">
            <div class="float-right">
                @if (isset($breadcrumbs))
                    {{ Breadcrumbs::render(Route::currentRouteName(), $breadcrumbs) }}
                @else
                    {{ Breadcrumbs::render(Route::currentRouteName()) }}
                @endif
            </div>
        </div>
    </div>
@endsection
