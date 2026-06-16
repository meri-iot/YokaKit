@extends('components.header')

@section('title', __('yokakit.about'))

@section('content')
    <div class="row">
        <div class="col-md-12">
            <x-adminlte-card>
                <x-text-item label="{{ __('yokakit.version') }}" text="{{ config('yokakit.version', '1.0.0') }}" />
                <x-text-item label="{{ __('yokakit.copyright') }}" text="{{ config('yokakit.copyright', '© YokaKit') }}" />
            </x-adminlte-card>
        </div>
    </div>
@endsection
