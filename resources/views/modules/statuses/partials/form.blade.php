<form class="dialog-form" method="post" action="{{ $record->exists ? route('statuses.update', $record) : route('statuses.store') }}" data-async-form novalidate>
    @csrf
    <input type="hidden" name="_method" value="{{ $record->exists ? 'PUT' : 'POST' }}">
    @include('modules.statuses.partials.fields')
    <x-form-actions :cancel="route('statuses.index')" modal />
</form>
@if($record->exists)
    <x-record-history :record="$record" resource="estados" />
@endif
