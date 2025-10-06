@props(['name'])
@php
    $config = [
        'timePicker' => false,
        'locale' => [
            'format' => 'YYYY-MM-DD',
            'customRangeLabel' => __('yokakit.custom_range'),
            'applyLabel' => __('yokakit.apply'),
            'cancelLabel' => __('yokakit.cancel'),
            'separator' => ' ~ ',
        ],
        'ranges' => [
            __('yokakit.today') => ["js:moment().startOf('day')", "js:moment().endOf('day')"],
            __('yokakit.yesterday') => ["js:moment().subtract(1, 'days').startOf('day')", "js:moment().subtract(1, 'days').endOf('day')"],
            __('yokakit.last_7_days') => ["js:moment().subtract(6, 'days')", "js:moment().endOf('day')"],
            __('yokakit.last_30_days') => ["js:moment().subtract(29, 'days')", "js:moment().endOf('day')"],
        ],
    ];
@endphp
<x-adminlte-date-range name="{{ $name }}" label="{{ __('yokakit.date_range') }}" :config="$config">
    <x-slot name="prependSlot">
        <div class="input-group-text bg-light">
            <i class="fas fa-calendar-alt"></i>
        </div>
    </x-slot>
</x-adminlte-date-range>
