@props(['name', 'label', 'required' => null, 'icon' => null, 'selected' => null, 'hide' => false, 'empty' => false])

@php
    $fgroup = '';
    if (!is_null($required)) {
        $fgroup .= 'required';
    }
    if ($hide) {
        $fgroup .= ' d-none';
    }
@endphp
<x-adminlte-select name="{{ $name }}" label="{{ $label }}" fgroup-class="{{ $fgroup }}"
    label-class="{{ is_null($required) ? '' : 'control-label' }}" enable-old-support>
    @isset($icon)
        <x-slot name="prependSlot">
            <div class="input-group-text bg-light">
                @if ($icon == 'raspberry-pi')
                    <i class="fab fa-fw fa-{{ $icon }}"></i>
                @else
                    <i class="fa-solid fa-fw fa-{{ $icon }}"></i>
                @endif
            </div>
        </x-slot>
    @endisset
    @if ($empty)
        <x-adminlte-options placeholder="" selected="{{ $selected }}" empty-option="　" />
    @else
        <x-adminlte-options placeholder="" selected="{{ $selected }}" />
    @endif
</x-adminlte-select>
