@extends('components.header')

@section('title', __('yokakit.target_add', ['target' => __('yokakit.raspberry_pi')]))

@section('content')
    <x-form-create action="{{ route('raspberry-pi.store') }}" back="{{ route('raspberry-pi.index') }}">
        <x-input name="raspberry_pi_name" label="{{ __('yokakit.target_name', ['target' => __('yokakit.raspberry_pi')]) }}" icon="raspberry-pi" required />
        <x-input name="ip_address" label="{{ __('yokakit.ip_address') }}" icon="at" required />
    </x-form-create>
@endsection
