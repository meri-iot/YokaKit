@extends('components.header', ['breadcrumbs' => $process])

@section('title', __('yokakit.target_edit', ['target' => __('yokakit.line')]))

@section('content')
    <x-form-edit action="{{ route('line.update', ['process' => $process, 'line' => $line]) }}"
        back="{{ route('process.show', ['process' => $process, 'tab' => 'line']) }}">
        {{-- valueは{!! !!}で非エスケープ出力。adminlte-input側でエスケープされるためXSSリスクなし --}}
        <x-input name="line_name" value="{!! $line->line_name !!}" label="{{ __('yokakit.target_name', ['target' => __('yokakit.line')]) }}" icon="people-line"
            required />
        <x-input-color name="chart_color" value="{{ $line->chart_color }}" label="{{ __('yokakit.color') }}" init="{{ $line->chart_color }}" required />
        <x-select name="raspberry_pi_id" label="{{ __('yokakit.raspberry_pi') }}" :options="$raspberryPiOptions" icon="raspberry-pi"
            selected="{{ $line->raspberry_pi_id }}" required />
        <x-select name="pin_number" label="{{ __('yokakit.pin_number') }}" :options="$pinOptions" icon="map-pin" selected="{{ $line->pin_number }}" required />
        <x-input-switch name="defective" label="{{ __('yokakit.defective') }}" checked="{{ $line->defective }}" />
        <x-select name="worker_id" label="{{ __('yokakit.worker') }}" :options="$workerOptions" icon="person" hide
            selected="{{ $line->worker?->worker_id }}" />
        <x-select name="parent_id" label="{{ __('yokakit.target_line') }}" :options="$nonDefectiveLines" icon="people-line" hide
            selected="{{ $line->parentLine?->line_id }}" required />
    </x-form-edit>
@endsection

@section('js')
    @include('process.line.toggle')
@endsection
