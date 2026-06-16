@extends('components.header', ['breadcrumbs' => $raspberryPi])

@section('title', __('yokakit.target_edit', ['target' => __('yokakit.raspberry_pi')]))

@section('content')
    <x-form-edit action="{{ route('raspberry-pi.update', ['raspberryPi' => $raspberryPi]) }}" back="{{ route('raspberry-pi.index') }}">
        <x-input name="raspberry_pi_name" value="{!! $raspberryPi->raspberry_pi_name !!}" label="{{ __('yokakit.target_name', ['target' => __('yokakit.raspberry_pi')]) }}"
            icon="raspberry-pi" required />
        <x-input name="ip_address" value="{{ $raspberryPi->ip_address }}" label="{{ __('yokakit.ip_address') }}" icon="at" required />
    </x-form-edit>
@endsection
