<form class="dialog-form" method="post" action="{{ $record->exists ? route('suppliers.update', $record) : route('suppliers.store') }}" data-async-form novalidate>
    @csrf
    @if($record->exists) @method('PUT') @endif
    @include('modules.suppliers.partials.fields')
    <x-form-actions :cancel="route('suppliers.index')" modal />
</form>
@if($record->exists)
    <x-record-history :record="$record" resource="proveedores" />
@endif
