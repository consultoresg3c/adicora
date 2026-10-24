<x-delete-confirmation :action="route('menus.destroy', $record)" :name="$record->name" label="menú" description="Esta acción no elimina el código, los permisos ni los datos del módulo." />
