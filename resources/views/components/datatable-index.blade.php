@props(['href', 'add', 'heads' => [], 'config' => [], 'id' => null])

<div class="col-md-12">
    <x-adminlte-card>
        <x-adminlte-datatable id="{{ $id ?? Str::random(16) }}" :heads="$heads" :config="$config">
            {{ $slot }}
        </x-adminlte-datatable>
        @can('admin')
            <x-slot name="footerSlot">
                <a href="{{ $href }}" role="button" {{ $attributes->merge(['class' => 'btn btn-primary']) }}>
                    <i class="fa-solid fa-lg fa-add"></i>
                    {{ $add }}
                </a>
            </x-slot>
        @endcan
    </x-adminlte-card>
</div>
