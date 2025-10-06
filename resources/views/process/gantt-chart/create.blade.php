@extends('components.header', ['breadcrumbs' => $process])

@section('title', __('yokakit.target_add', ['target' => __('yokakit.gantt_chart')]))

@section('content')
    <x-form-create action="{{ route('gantt-chart.store', ['process' => $process]) }}"
        back="{{ route('process.show', ['process' => $process, 'tab' => 'gantt-chart']) }}">
        <x-input name="chart_name" label="{{ __('yokakit.chart_name') }}" icon="chart-gantt" required />
        <x-select name="raspberry_pi_id" label="{{ __('yokakit.raspberry_pi') }}" :options="$raspberryPiOptions" icon="raspberry-pi" required />
        <x-select name="pin_number" label="{{ __('yokakit.pin_number') }}" :options="$pinOptions" icon="map-pin" required />
        <x-input-color name="chart_color" label="{{ __('yokakit.color') }}" required />
        <x-select name="chart_type" label="{{ __('yokakit.gantt_chart_type') }}" :options="$chatTypes" icon="wave-square" required />
        <x-input-switch name="trigger" label="{{ __('yokakit.trigger') }}" on="HIGH" off="LOW" />
    </x-form-create>
@endsection
