@extends('components.header', ['breadcrumbs' => $worker])

@section('title', __('yokakit.target_edit', ['target' => __('yokakit.worker')]))

@section('content')
    <x-form-edit action="{{ route('worker.update', ['worker' => $worker]) }}" back="{{ route('worker.index') }}">
        <x-input name="identification_number" value="{!! $worker->identification_number !!}" label="{{ __('yokakit.identification_number') }}" icon="id-card" required />
        <x-input name="worker_name" value="{!! $worker->worker_name !!}" label="{{ __('yokakit.target_name', ['target' => __('yokakit.worker')]) }}" icon="person"
            required />
        <x-input name="mac_address" value="{!! $worker->mac_address !!}" label="{{ __('yokakit.barcode_reader_mac_address') }}" icon="qrcode" />
    </x-form-edit>
@endsection
