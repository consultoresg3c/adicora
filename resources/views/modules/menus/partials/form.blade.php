<form class="dialog-form" method="post" action="{{ $record->exists ? route('menus.update', $record) : route('menus.store') }}" data-async-form novalidate>
    @csrf
    @if($record->exists) @method('PUT') @endif
    @include('modules.menus.partials.fields')
    <x-form-actions :cancel="route('menus.index')" modal />
</form>
@if($record->exists)
    <x-record-history :record="$record" resource="menus" />
@endif
