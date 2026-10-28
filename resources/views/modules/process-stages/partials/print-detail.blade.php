<x-record-print :id="'print-processStage-'.$record->id" :title="$record->name" :fields="['Registro' => $record->id, 'Estatus' => $record->status?->name]" />
