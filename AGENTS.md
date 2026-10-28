# BUKO: reglas del proyecto

Estas instrucciones aplican a la aplicación completa y a todos sus módulos actuales y futuros. Mantener el código existente y estas reglas como referencia; no introducir una segunda forma de resolver el mismo problema sin una necesidad concreta.

## Producto y plataforma

- Gestión empresarial modular. Laravel 12, Blade, PostgreSQL 18, Tailwind CSS 4 y JavaScript nativo con fetch. Sin Vue, React ni un router de frontend que replique la lógica de Laravel.
- Un usuario pertenece a una empresa. El superadministrador global no pertenece a ninguna. Los administradores de empresa jamás administran otras empresas ni la configuración global.
- Distinguir permisos del sistema, módulos habilitados a la empresa y permisos de operación. Un permiso directo o de rol nunca habilita un módulo desactivado.
- No ejecutar migraciones ni seeders sobre la base de desarrollo sin una instrucción expresa. Las pruebas usan una base aislada.
- Las migraciones existentes son inmutables. TODO cambio de esquema o actualización de catálogos para instalaciones existentes va en una migración nueva e incremental. Nunca editar las ya entregadas o ejecutadas.
- El entorno actual se abre en http://localhost/buko/public/ mediante Wamp. Respetar la ruta y la configuración existente. No modificar Wamp, Apache, PHP, PostgreSQL, puertos, extensiones o configuración del servidor sin petición expresa.
- No instalar ni añadir dependencias, formateadores, frameworks o herramientas sin solicitud expresa. Usar los recursos ya disponibles. No añadir Prettier ni sustituir el flujo de Pint para PHP. Los comandos de instalación documentados para una copia nueva no autorizan ejecutarlos sobre este entorno.
- Mantener .env fuera de Git. .env.example debe reflejar las variables necesarias sin secretos reales. No sobrescribir configuraciones locales al documentar o corregir un módulo.
- El alcance actual es la base administrativa. Nómina y otras funciones de negocio se incorporan cuando se soliciten; no presentar funciones pendientes, métricas ni datos ficticios como existentes.

## Arquitectura

- MVC idiomático, organizado por módulo: Controllers, Requests y Services. Controladores coordinan HTTP; Form Requests validan y autorizan; servicios realizan operaciones y transacciones.
- No añadir repositorios, interfaces, DTOs o capas genéricas sin una necesidad concreta. Mantener funciones pequeñas, retornos tipados, imports ordenados y espacio entre métodos. Aplicar Laravel Best Practices y Laravel Simplifier a los cambios.
- Eloquent: comentarios en español que expliquen el modelo y cada relación, tipos de retorno concretos, fillable explícito, casts y eager loading deliberado.
- TODAS las relaciones usan claves ID y restricciones de clave foránea. Nunca relacionar por nombre, email, slug, código o texto. Los códigos son identificadores funcionales del propio registro, no claves foráneas.
- Validar pertenencia a empresa en lectura, escritura y asignación de relaciones. Nunca confiar en company_id enviado por un administrador de empresa.
- Estados dinámicos en catálogo por tipo. Las entidades guardan status_id; no strings de estado ni enums cerrados de negocio. No borrar estados referenciados; desactivar mediante su configuración.
- Borrado lógico obligatorio para todos los registros administrables actuales y futuros. Usar SoftDeletes de Laravel con deleted_at; nunca forceDelete ni cascadas físicas desde el backoffice. Empresas, sucursales, usuarios, roles, estados y menús tienen acción Eliminar con confirmación Blade local. Conservar relaciones y pivotes; los eliminados no aparecen en consultas operativas, opciones, permisos ni autenticación.
- Estado base del registro: 1 activo, 0 inactivo y -1 eliminado. lifecycle_status es una lectura derivada: deleted_at prevalece (-1); en los registros con estado se utiliza su disponibilidad is_active (1/0). Los roles vigentes son activos. No almacenar -1, 0 o 1 como si fueran IDs de estados. Mantener status_id como FK por ID, separada de los futuros flujos de facturas/nómina y sus estados de negocio.
- Un estado referenciado por registros vigentes o eliminados no se puede eliminar; los tipos de estados existentes tampoco se cambian desde edición. Eliminar una empresa conserva sus registros y bloquea el acceso de sus usuarios. Eliminar un rol conserva sus asignaciones históricas y deja de conceder permisos. Proteger el superadministrador, la propia cuenta y cuentas/roles fuera del alcance del actor también al eliminar.
- Auditar creación, modificaciones y eliminación lógica en la misma transacción que la operación. Guardar actor_id, empresa y registro mediante FKs por ID, fecha y diferencias antes/después de campos permitidos, incluidos cambios de asignaciones. No registrar contraseñas, hashes, tokens ni secretos; un cambio de contraseña se representa solo mediante un indicador. Los guardados sin cambios no generan eventos ficticios.
- Mostrar historial reciente en los formularios de edición y un historial paginado del registro, accesible también tras su eliminación con la misma autorización y aislamiento de empresa. No inventar eventos previos a la incorporación de auditoría ni permitir editar o borrar el historial desde la interfaz.
- Roles de empresa separados de roles globales. La delegación solo concede permisos que el actor ya posee. is_super_admin nunca es asignable desde formularios.
- Un administrador no puede editar credenciales ni accesos de una cuenta con permisos que él no pueda delegar. Proteger el superadministrador y la edición de los propios accesos.
- Mutaciones que coordinan modelos/pivotes usan transacciones. Secretos solo en .env, acceso con config(), nunca imprimirlos ni incluirlos en commits.

## HTTP y frontend

- Toda respuesta fetch usa { success, message, data, errors, meta }. Conservar códigos HTTP: 401, 403, 404, 419, 422, 429 y 500. Errores por campo en errors; errores inesperados sin detalles internos.
- Usar resources/js/shared/http.js para fetch con Accept application/json, CSRF y same-origin. Nunca duplicar protocolos o interpretar HTTP 200 como éxito cuando success es falso.
- JavaScript por módulo, cargado solo por su vista mediante entradas Vite independientes. Solo transporte, formularios y shell pueden ser compartidos. No importar todos los módulos desde app.js.
- Navegación normal de Laravel/Blade entre módulos y formularios largos. No introducir router de JavaScript, pushState/popstate, renderizado del shell por fetch, cargas dinámicas de módulos ni un núcleo que replique rutas o ciclo de vida de Laravel. Sidebar, topbar y contenido se renderizan en Blade.
- Cada entrada JavaScript se carga directamente desde su vista y solo inicializa las interacciones de ese módulo. No ejecutar scripts recibidos como datos ni usar eval. No guardar permisos o pantallas en una caché propia de cliente.
- Declarar la entrada interactiva con @section('module', 'resources/js/modules/{modulo}/index.js'). Reutilizar shared/management.js, forms.js, dialogs.js y http.js cuando corresponda. Un listado sin interacción propia no necesita un archivo JavaScript vacío.
- Preservar la navegación nativa del navegador: URLs directas, Atrás/Adelante, Ctrl/Cmd+clic, enlaces externos, descargas y anclas. Las búsquedas cancelan respuestas superadas y mantienen los resultados visibles si hay un fallo.
- Compilar assets con npm run build para Wamp; no depender de un servidor Vite para visitar la aplicación.
- Mantener base relativa './' en Vite para que las fuentes e imports compilados se resuelvan desde el CSS/JS y respeten subcarpetas como /buko/public/. No introducir /build/ absoluto ni enlaces localhost fuera de APP_URL. Verificar también los recursos internos de los assets, no solo las etiquetas @vite.
- Todas las vistas en Blade. Layouts y partials identificables: head, header, navbar, topbar, footer, content. Componentes reutilizables para controles y estados.
- Formularios largos en create/edit propios; formularios breves en modal nativo accesible con foco y Escape, tanto al crear como al editar. No enviar estados ni sucursales a una vista de formulario independiente. Las rutas HTML alternativas abren el modal dentro del listado.
- Los formularios modales son HTML Blade incluido desde resources/views/modules/{modulo}/partials/ en templates locales del listado. Blade fija action, _method, valores, selects y estados de edición para cada registro. Abrir crear/editar NO hace fetch ni hidrata campos desde JSON: solo clona el template HTML correspondiente en el modal. No solicitar HTML del formulario al abrir, no mostrar skeleton ni construir campos o formularios con cadenas/createElement en JavaScript. Las confirmaciones se renderizan del mismo modo.
- Los datos de usuario se imprimen con {{ }} escapado en Blade, incluso dentro de valores y atributos. Nunca usar {!! !!} con datos editables ni concatenarlos en HTML o JavaScript. Las URLs de acciones se generan con rutas del servidor. Fetch de búsqueda recibe solo el fragmento Blade propio de resultados; no reemplaza el layout ni ejecuta scripts. Añadir pruebas de escape XSS cuando cambie ese renderizado.
- Menús también se crean y editan en modal. Empresas, usuarios y roles usan formularios propios. Las rutas GET alternativas redirigen al listado para abrir el modal; no devuelven data.html. Las confirmaciones de borrado también tienen su partial Blade local. La apertura local nunca sustituye autorización y validación del guardado en el servidor.
- Etiquetas visibles; errores debajo del campo y asociados mediante aria-describedby. No errores mientras un campo está intacto; primer blur y corrección posterior. Estados de carga, fallo de red, validación y vacío explícitos.

## Diseño

- Paleta base: #c0e6fd, #80aad3, #5b86b6, #3f6593, #1b3554, #000f22. Tokens semánticos centralizados en resources/css/app.css.
- Fuentes autoalojadas exclusivamente; IBM Plex Sans incluida desde paquete local con su licencia. Ninguna fuente, script o estilo desde CDN en tiempo de ejecución.
- Interfaz empresarial espaciosa, jerarquía clara y sin datos inventados, gráficos decorativos, fotografía de relleno o texto promocional en el login.
- La pantalla inicial es el login, dividido en escritorio y adaptado a móvil. Las credenciales iniciales se muestran solo con APP_ENV=local y BUKO_SHOW_LOCAL_CREDENTIALS=true; nunca en producción. No restablecer una contraseña existente al repetir el seeder.
- Motion: Emil principal, Jakub secundario; feedback de 160ms, entrada de modales de 230-260ms y salida de 130-180ms. La navegación nativa solicita el destino inmediatamente: prohibidos fades de página, esperas previas y recargas simuladas. Animar solo interacciones concretas como sidebar y modal. Sin bucles ni demoras de teclado. Respetar prefers-reduced-motion.
- Menú administrable desde backoffice: padres sin acción e hijos por parent_id. module_id, permission_id y status_id llevan FK. El destino se elige del catálogo de acciones registradas, nunca PHP o URLs arbitrarias. No confundir visibilidad del menú con autorización del endpoint.
- module_id y permission_id del menú se derivan de NavigationRegistry en el servidor; no aceptar su asignación desde el cliente. Ocultar padres sin hijos visibles y entradas inactivas o no autorizadas. Un padre con hijos no se elimina ni se convierte en hoja hasta resolver sus hijos. Crear/eliminar un menú no crea/elimina código, permisos ni datos de negocio.
- Iconos SVG locales de Tabler con licencia conservada, sin paquetes adicionales ni llamadas externas en ejecución. Controles solo con icono llevan aria-label y title.
- La edición de los registros de un CRUD se abre haciendo clic en su nombre en el listado, mediante un enlace con la clase compartida row-action. No usar un lápiz de edición. Conservar las rutas y los modales locales existentes; los registros protegidos muestran su nombre sin enlace de edición.
- En todos los CRUD, record-form-header alinea las opciones de tres puntos con la primera fila de campos en la esquina derecha del panel de edición, también en modales. Mantener el mismo ancho de columnas en todas las filas. Situar el menú en la zona superior de la última etiqueta de la primera fila (la primera etiqueta en móvil), reservando espacio para su texto sin reducir el ancho de los controles ni superponerlos. Incluyen ver el historial, imprimir el detalle guardado y eliminar cuando la operación está permitida. Los listados no muestran iconos de historial ni papeleras, ni columnas de acciones vacías. Reutilizar record-options con historyUrl generada por el servidor, record-print y el diálogo compartido de confirmación con partial Blade local; cancelar la eliminación conserva la edición abierta y sus cambios pendientes. La impresión usa campos explícitos autorizados, nunca contraseñas, secretos ni campos ocultos. No añadir eliminación a flujos que no la admiten.
- Responsive explícito, foco visible, contraste AA, teclado y touch. Tablas semánticas, números alineados, acciones accesibles sin hover obligatorio.
- Solo la zona de etiquetas de la primera fila reserva espacio para las opciones de tres puntos; los controles mantienen el mismo ancho en todas las filas. Las siguientes se renderizan fuera de record-form-header y aprovechan todo el ancho, también al reorganizarse en móvil. El bloque de historial reciente no repite la acción Ver historial del menú de opciones.
- Armonía visual obligatoria en TODOS los módulos: reutilizar componentes y dimensiones de controles, badges, acciones y paneles. Sí/No comparten exactamente ancho, alto, padding y borde mediante boolean-badge; el texto no determina el tamaño del indicador. Los estados de una misma columna mantienen una medida común y las acciones conservan espacio y alineación estables. Verificar variantes activas/inactivas, nombres largos y móvil.
- Mantener la barra lateral y el contenido utilizables en pantallas estrechas; limitar el scroll horizontal al contenedor de la tabla y conservar accesibles sus acciones. Los modales largos deben permitir desplazamiento y mantener disponibles cierre y acciones mediante teclado.
- Todos los listados administrativos incluyen buscador, filtros pertinentes, selector de filas, total y controles de paginación visibles incluso con una sola página o sin resultados. Búsqueda, filtros y páginas se solicitan mediante fetch y actualizan solo data-list-results sin cambiar la dirección del navegador ni manipular su historial. Conservar filtros al paginar; aplicar filtros en servidor sin ampliar el ámbito de empresa. Los enlaces/formularios HTML son la alternativa sin JavaScript. Agrupar estados por su módulo/contexto mediante status_type_id, con encabezados identificables.
- Gráficos futuros: escala acorde al dato, cero en barras de magnitud, unidades/período visibles, ausencia distinta de cero y valores accesibles. No añadir gráficos sin una pregunta real que responder.

## Incorporar o modificar un módulo

- Definir su alcance: configuración global o datos de empresa, operaciones admitidas y permiso requerido. Habilitar un módulo y conceder permisos son decisiones separadas.
- Si cambia persistencia, crear una migración nueva con FK por ID, índices y restricciones apropiadas; no modificar migraciones existentes ni usar el seeder para corregir silenciosamente instalaciones ya preparadas.
- Implementar rutas autorizadas, Form Request, servicio cuando corresponda y modelos documentados. Comprobar empresa, estado y relaciones en servidor; el sidebar y los formularios no son una barrera de seguridad.
- Crear vistas en resources/views/modules/{modulo}/ y sus partials/; elegir modal o formulario propio según tamaño. Reutilizar componentes, tokens y controles existentes.
- Usar view() para páginas normales, ListingResponse para el fragmento list-results de búsquedas/paginación y AsyncResponse para operaciones asíncronas. Las entradas Vite son independientes y se incluyen directamente desde Blade, sin ciclo de montaje de un router propio.
- Para un módulo operativo nuevo, registrar módulo y permisos con IDs mediante la migración incremental correspondiente. Registrar su acción GET en NavigationRegistry y luego administrar su ubicación en el backoffice; no insertar destinos arbitrarios desde la interfaz.
- Verificar acceso permitido y denegado, pertenencia a empresa, validación y contrato JSON. Comprobar visitas directas, navegación repetida, Atrás/Adelante, modales y responsive cuando la interfaz cambie. Actualizar documentación afectada.

## Verificación

- Probar autenticación, aislamiento de empresa, escalamiento de privilegios, validación y contrato JSON. Ejecutar Pint, pruebas y build apropiados al cambio.
- La suite automática usa SQLite en memoria. Las comprobaciones explícitas contra PostgreSQL local deben usar transacciones revertidas y no dejar fixtures. Nunca ejecutar RefreshDatabase, migrate:fresh, migrate:refresh, db:wipe ni operaciones equivalentes contra desarrollo.
- Migraciones y seeders locales los ejecuta el usuario salvo petición expresa. No ejecutar composer run setup: el script heredado incluye migrate --force. No formatear migraciones existentes al ejecutar Pint; limitarlo a los archivos PHP modificados.
- No añadir pruebas que solo repitan la implementación ni ejecutar suite/build por cambios exclusivamente documentales. Para documentación, verificar enlaces, capturas, coherencia y ausencia de secretos.
- No afirmar validación real en PostgreSQL o navegador sin haberla ejecutado. Documentar límites y pasos manuales.

## Documentación y archivos del repositorio

- README.md describe el alcance real, requisitos, instalación, arquitectura y verificación. Mantenerlo actualizado cuando esos aspectos cambien; docs/BASE_BUKO.md amplía las decisiones técnicas y AGENTS.md define reglas de trabajo.
- Capturas seleccionadas para documentación en docs/images/, con rutas relativas desde Markdown, datos de demostración y sin credenciales ni información privada visible. No requieren alojamiento externo. Mantenerlas sincronizadas con la interfaz que describen.
- Capturas temporales y pruebas visuales locales permanecen en docs/previews/, ignorado por Git. Versionar solo imágenes útiles para la documentación; no añadir capturas, logs, cachés, dependencias o resultados de pruebas de forma indiscriminada. Conservar los lockfiles del proyecto.

## CodeGraph

- Resolver raíz y comprobar .codegraph antes de búsquedas estructurales. Si está disponible pero falta el índice, inicializar una vez en este proyecto. Usar codegraph_explore antes de exploraciones amplias. Si falla, explicar brevemente y usar herramientas normales.
