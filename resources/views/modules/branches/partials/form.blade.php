<form class="dialog-form" method="post" action="{{ $record->exists ? route('branches.update', $record) : route('branches.store') }}" data-async-form novalidate>
    @csrf
    @if($record->exists) @method('PUT') @endif
    @include('modules.branches.partials.fields')
    <x-form-actions :cancel="route('branches.index')" modal />
</form>
@if($record->exists)
    <x-record-history :record="$record" resource="sucursales" />
@endif
