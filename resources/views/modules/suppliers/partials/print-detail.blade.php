<x-record-print :id="'print-supplier-'.$record->id" :title="$record->name" :fields="['Registro' => $record->id, 'Empresa' => $record->company?->name, 'Estado' => $record->status?->name]" />
