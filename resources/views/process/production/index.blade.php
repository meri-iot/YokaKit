@extends('components.header', ['breadcrumbs' => $process])

@section('title', $process->process_name . '：' . __('yokakit.production_history'))

@section('content')
    @include('adminlte::partials.common.preloader')
    <x-adminlte-card body-class="p-0">

        <form action="{{ route('production.destroy', ['process' => $process]) }}" method="POST">
            @csrf
            @method('DELETE')

            <div class="row p-3">
                <!-- 左側（削除ボタン） -->
                <div class="d-flex flex-column justify-content-end col-auto">
                    <button class="btn btn-primary mb-3" id="delete-modal" data-target="#delete-dialog" data-toggle="modal" type="button" role="button"
                        disabled>
                        <i class="fa-solid fa-lg fa-trash"></i>
                        {{ __('yokakit.delete') }}
                    </button>
                    <x-adminlte-modal id="delete-dialog" title="{{ __('yokakit.confirm') }}" theme="danger" icon="fa-solid fa-fw fa-triangle-exclamation"
                        v-centered>
                        <x-slot name="footerSlot">
                            <x-adminlte-button type="submit" theme="danger" label="{{ __('yokakit.delete') }}" icon="fa-solid fa-fw fa-trash" />
                        </x-slot>
                        <strong>{{ __('yokakit.confirm_delete', ['target' => __('yokakit.production_history')]) }}</strong>
                    </x-adminlte-modal>
                </div>

                <!-- 右側（検索条件グループ） -->
                <div class="col d-flex justify-content-end">
                    <div class="d-flex justify-content-end flex-wrap">
                        <div class="align-self-end col-auto">
                            <x-select name="part_number_name" label="{{ __('yokakit.part_number') }}" :options="$partNumbers" icon="hammer" empty="true"
                                selected="{{ request('partNumberName') }}" />
                        </div>
                        <div class="align-self-end col-auto">
                            <x-daterange name="date-range"></x-daterange>
                        </div>
                        <div class="align-self-end col-auto">
                            <button class="btn btn-default mb-3" id="search-button" type="button" role="button">
                                <i class="fa-solid fa-lg fa-search"></i>
                                {{ __('yokakit.search') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th class="border-bottom-0 icheck-primary w-1">
                            <input id="all-checkbox" name="all-checkbox" type="checkbox">
                            <label for="all-checkbox"></label>
                        </th>
                        <th class="border-bottom-0">{{ __('yokakit.target_name', ['target' => __('yokakit.part_number')]) }}</th>
                        <th class="border-bottom-0">{{ __('yokakit.start') }}</th>
                        <th class="border-bottom-0">{{ __('yokakit.end') }}</th>
                        <th class="border-bottom-0">{{ __('yokakit.period') }}</th>
                        <th class="border-bottom-0">{{ __('yokakit.number_of_production') }}</th>
                        <th class="border-bottom-0">CT {{ __('yokakit.unit_sec') }}</th>
                        <th class="border-bottom-0">{{ __('yokakit.breakdown_count') }}</th>
                        <th class="border-bottom-0 w-1"></th>
                        <th class="border-bottom-0 w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($histories as $history)
                        @php
                            $breakDownCount = $history->breakDownCount();
                        @endphp
                        <tr data-widget="{{ $breakDownCount == 0 ? '' : 'expandable-table' }}"
                            aria-expanded="{{ $breakDownCount == 0 ? '' : 'false' }}">
                            <td class="icheck-primary align-middle" onclick="event.stopPropagation()">
                                <input id="checkbox-{{ $history->production_history_id }}" name="checkbox[]" type="checkbox"
                                    value="{{ $history->production_history_id }}">
                                <label for="checkbox-{{ $history->production_history_id }}"> </label>
                            </td>
                            <td class="align-middle">{{ $history->part_number_name }}</td>
                            <td class="align-middle">{{ $history->start }}</td>
                            <td class="align-middle">{{ $history->stop }}</td>
                            <td class="align-middle">{{ $history->period() }}</td>
                            <td class="align-middle">{{ $history->lastProductCount() }}</td>
                            <td class="align-middle">{{ $history->cycle_time }}</td>
                            <td class="align-middle">{{ $breakDownCount }}</td>
                            <td class="text-nowrap pr-0 align-middle" onclick="event.stopPropagation()">
                                <a class="btn btn-tool" href="{{ route('production.download', ['process' => $process, 'history' => $history]) }}">
                                    <i class="fa-solid fa-lg fa-download pr-1"></i>
                                    {{ __('yokakit.excel') }}
                                </a>
                            </td>
                            <td class="text-nowrap pr-1 align-middle" onclick="event.stopPropagation()">
                                <a class="btn btn-tool" href="{{ route('production.show', ['process' => $process, 'history' => $history]) }}">
                                    <i class="fa-solid fa-lg fa-chart-line pr-1"></i>
                                    {{ __('yokakit.display') }}
                                </a>
                            </td>
                        </tr>
                        @if ($breakDownCount != 0)
                            <tr class="expandable-body">
                                <td colspan="10">
                                    <ul class="list-group list-group-flush text-warning ml-4">
                                        <p class="mb-0 mt-2">{{ __('yokakit.breakdown_section') }}</p>
                                        @foreach ($history->breakDownSections() as $section)
                                            @if ($history->status() == \App\Enums\ProductionStatus::BREAKDOWN())
                                                <li class="list-group-item pb-1 pt-1">
                                                    {{ $loop->index + 1 }}. {{ $section->from }} ~
                                                </li>
                                            @else
                                                <li class="list-group-item pb-1 pt-1">
                                                    {{ $loop->index + 1 }}. {{ $section->from }} ~ {{ $section->to }} ({{ $section->span() / 1000 }}
                                                    {{ __('yokakit.sec') }})
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>

            <div class="d-flex justify-content-center mt-4">
                {{ $histories->links() }}
            </div>
        </form>
    </x-adminlte-card>
@endsection

@section('js')
    @include('components.toast')
    <script>
        const partNumbers = new Set(Object.keys(@json($partNumbers)));
        const processId = @json($process->id);
        const baseUrl = @json(route('production.index', ['process' => $process]));
        const url = new URL(window.location.href);
        const queryPartNumberName = url.searchParams.get('partNumberName');
        const queryStartDate = url.searchParams.get('startDate');
        const queryEndDate = url.searchParams.get('endDate');
        let partNumberName = partNumbers.has(queryPartNumberName) ? queryPartNumberName : '';
        let startDate = moment(queryStartDate, 'YYYY-MM-DD', true).isValid() ? queryStartDate : '';
        let endDate = moment(queryEndDate, 'YYYY-MM-DD', true).isValid() ? queryEndDate : '';
        $('#part_number_name').on('change', function() {
            partNumberName = this.value;
        });
        $('#search-button').on('click', function() {
            const params = new URLSearchParams();
            params.set('partNumberName', partNumberName);
            params.set('startDate', startDate);
            params.set('endDate', endDate);
            const urlWithQuery = `${baseUrl}?${params.toString()}`;
            window.location.href = urlWithQuery;
        });
        $(function() {
            const dateRange = $('#date-range');
            const picker = dateRange.data('daterangepicker');
            if (startDate && endDate) {
                picker.setStartDate(moment(startDate).startOf('day'));
                picker.setEndDate(moment(endDate).endOf('day'));
            } else {
                picker.setStartDate(moment().subtract(6, 'days').startOf('day'));
                picker.setEndDate(moment().endOf('day'));
            }
            dateRange.on('change', function() {
                startDate = picker.startDate.format('YYYY-MM-DD');
                endDate = picker.endDate.format('YYYY-MM-DD');
            });

            const allCheckbox = $('#all-checkbox');
            const checkboxes = $('input[id^="checkbox"]');
            const deleteModal = $('#delete-modal');
            allCheckbox.on('change', function(event) {
                const isChecked = $(this).prop('checked');
                checkboxes.prop('checked', isChecked);
                if (isChecked) {
                    deleteModal.prop('disabled', false);
                } else {
                    deleteModal.prop('disabled', true);
                }
            });
            checkboxes.on('change', function(event) {
                const isChecked = $(this).prop('checked');
                if (isChecked) {
                    allCheckbox.prop('checked', isChecked);
                    deleteModal.prop('disabled', false);
                } else {
                    const checkedCount = checkboxes.filter(':checked').length;
                    if (checkedCount == 0) {
                        allCheckbox.prop('checked', false);
                        deleteModal.prop('disabled', true);
                    }
                }
            });
        });
    </script>
@endsection
