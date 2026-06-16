@extends('components.header')

@section('title', __('yokakit.target_list', ['target' => __('yokakit.raspberry_pi')]))

@section('content')
    @include('adminlte::partials.common.preloader')
    <div class="row">
        @php
            $heads = [
                '#',
                __('yokakit.target_name', ['target' => __('yokakit.raspberry_pi')]),
                __('yokakit.ip_address'),
                __('yokakit.cpu_temperature') . __('yokakit.unit_temperature'),
                __('yokakit.cpu_utilization') . __('yokakit.unit_rate'),
                __('yokakit.update_date'),
            ];
            $columns = [['visible' => false], null, null, null, null, null];
            if (Gate::allows('admin')) {
                array_push($heads, ['label' => '', 'no-export' => true, 'width' => 5]);
                array_push($columns, ['orderable' => false, 'searchable' => false]);
            }
            $config = [
                'columns' => $columns,
                'language' => ['url' => route('datatables')],
            ];
        @endphp
        <x-datatable-index href="{{ route('raspberry-pi.create') }}" add="{{ __('yokakit.target_add', ['target' => __('yokakit.raspberry_pi')]) }}"
            :heads="$heads" :config="$config">
            @foreach ($raspberryPis as $raspberryPi)
                <tr>
                    <td></td>
                    <td class="align-middle">{{ $raspberryPi->raspberry_pi_name }}</td>
                    <td class="align-middle">{{ $raspberryPi->ip_address }}</td>
                    <td class="align-middle">{{ $raspberryPi->cpu_temperature }}</td>
                    <td class="align-middle">{{ $raspberryPi->cpu_utilization }}</td>
                    <td class="align-middle">{{ $raspberryPi->updated_at }}</td>
                    @can('admin')
                        <td class="text-nowrap text-right align-middle">
                            <x-button-edit href="{{ route('raspberry-pi.edit', ['raspberryPi' => $raspberryPi]) }}" />
                            <x-button-delete target="raspberry_pi_{{ $raspberryPi->raspberry_pi_id }}" />
                        </td>
                        {{-- 削除ダイアログ --}}
                        <x-modal-delete id="raspberry_pi_{{ $raspberryPi->raspberry_pi_id }}"
                            action="{{ route('raspberry-pi.destroy', ['raspberryPi' => $raspberryPi]) }}">
                            <strong>{{ __('yokakit.confirm_delete', ['target' => __('yokakit.raspberry_pi')]) }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.ip_address') }}</strong>
                                <p class="mb-0 ml-2 mt-1">{{ $raspberryPi->ip_address }}</p>
                            </x-adminlte-card>
                        </x-modal-delete>
                    @endcan
                </tr>
            @endforeach
        </x-datatable-index>
    </div>
@endsection

@section('js')
    @include('components.toast')
@endsection
