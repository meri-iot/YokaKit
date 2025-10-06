@extends('components.header', ['breadcrumbs' => $process])

@section('title', __('yokakit.target_add', ['target' => __('yokakit.alarm')]))

@section('content')
    <x-form-create action="{{ route('alarm.store', ['process' => $process]) }}"
        back="{{ route('process.show', ['process' => $process, 'tab' => 'alarm']) }}">
        <x-input name="alarm_text" label="{{ __('yokakit.alarm_text') }}" icon="comment" required />
        <x-select name="raspberry_pi_id" label="{{ __('yokakit.raspberry_pi') }}" :options="$raspberryPiOptions" icon="raspberry-pi" required />
        <x-select name="identification_number" label="{{ __('yokakit.pin_number') }}" :options="$pinOptions" icon="map-pin" required />
        <x-input-switch name="trigger" label="{{ __('yokakit.trigger') }}" on="HIGH" off="LOW" />
    </x-form-create>
@endsection
