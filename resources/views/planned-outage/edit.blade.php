@extends('components.header')

@section('title', __('yokakit.target_edit', ['target' => __('yokakit.planned_outage')]))

@section('content')
    <x-form-edit action="{{ route('planned-outage.update', ['plannedOutage' => $plannedOutage]) }}" back="{{ route('planned-outage.index') }}">
        <x-input name="planned_outage_name" value="{!! $plannedOutage->planned_outage_name !!}"
            label="{{ __('yokakit.target_name', ['target' => __('yokakit.planned_outage')]) }}" icon="stop" required />
        <x-input-time name="start_time" value="{{ $plannedOutage->formatStartTime() }}" label="{{ __('yokakit.start_time') }}" />
        <x-input-time name="end_time" value="{{ $plannedOutage->formatEndTime() }}" label="{{ __('yokakit.end_time') }}" />
    </x-form-edit>
@endsection
