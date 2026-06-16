@extends('components.header')

@section('title', __('yokakit.target_list', ['target' => __('yokakit.planned_outage')]))

@section('content')
    @include('adminlte::partials.common.preloader')
    <div class="row">
        @php
            $heads = ['#', __('yokakit.target_name', ['target' => __('yokakit.planned_outage')]), __('yokakit.start_time'), __('yokakit.end_time')];
            $columns = [['visible' => false], null, null, null];
            if (Gate::allows('admin')) {
                array_push($heads, ['label' => '', 'no-export' => true, 'width' => 5]);
                array_push($columns, ['orderable' => false, 'searchable' => false]);
            }
            $config = [
                'order' => [[2, 'asc']],
                'columns' => $columns,
                'language' => ['url' => route('datatables')],
            ];
        @endphp
        <x-datatable-index href="{{ route('planned-outage.create') }}" add="{{ __('yokakit.target_add', ['target' => __('yokakit.planned_outage')]) }}"
            :heads="$heads" :config="$config">
            @foreach ($plannedOutages as $plannedOutage)
                <tr>
                    <td></td>
                    <td class="align-middle">{{ $plannedOutage->planned_outage_name }}</td>
                    <td class="align-middle">{{ $plannedOutage->formatStartTime() }}</td>
                    <td class="align-middle">{{ $plannedOutage->formatEndTime() }}</td>
                    @can('admin')
                        <td class="text-nowrap text-right align-middle">
                            <x-button-edit href="{{ route('planned-outage.edit', ['plannedOutage' => $plannedOutage]) }}" />
                            <x-button-delete target="planned_outage_{{ $plannedOutage->planned_outage_id }}" />
                        </td>
                        {{-- 削除ダイアログ --}}
                        <x-modal-delete id="planned_outage_{{ $plannedOutage->planned_outage_id }}"
                            action="{{ route('planned-outage.destroy', ['plannedOutage' => $plannedOutage]) }}">
                            <strong>{{ __('yokakit.confirm_delete', ['target' => __('yokakit.planned_outage')]) }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.planned_outage')]) }}</strong>
                                <p class="mb-0 ml-2 mt-1">{{ $plannedOutage->planned_outage_name }}</p>
                                <hr>
                                <strong>{{ __('yokakit.start_time') }}</strong>
                                <p class="ml-2 mt-1">{{ $plannedOutage->formatStartTime() }}</p>
                                <hr>
                                <strong>{{ __('yokakit.end_time') }}</strong>
                                <p class="mb-0 ml-2 mt-1">{{ $plannedOutage->formatEndTime() }}</p>
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
