@extends('components.header', ['breadcrumbs' => $process])

@section('title', __('yokakit.target_add', ['target' => __('yokakit.line')]))

@section('content')
    <x-form-create action="{{ route('line.store', ['process' => $process]) }}" back="{{ route('process.show', ['process' => $process, 'tab' => 'line']) }}">
        <x-input name="line_name" label="{{ __('yokakit.target_name', ['target' => __('yokakit.line')]) }}" icon="people-line" required />
        <x-input-color name="chart_color" label="{{ __('yokakit.color') }}" required />
        <x-select name="raspberry_pi_id" label="{{ __('yokakit.raspberry_pi') }}" :options="$raspberryPiOptions" icon="raspberry-pi" required />
        <x-select name="pin_number" label="{{ __('yokakit.pin_number') }}" :options="$pinOptions" icon="map-pin" required />
        <x-input-switch name="defective" label="{{ __('yokakit.defective') }}" />
        <x-select name="worker_id" label="{{ __('yokakit.worker') }}" :options="$workerOptions" icon="person" hide />
        <x-select name="parent_id" label="{{ __('yokakit.target_line') }}" :options="$nonDefectiveLines" icon="people-line" hide required />
    </x-form-create>
@endsection

@section('js')
    @include('process.line.toggle')
@endsection
