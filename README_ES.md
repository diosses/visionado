# Visionado - Guía rápida (ES)

Esta guía resume el flujo de trabajo y cómo operar la plataforma.

## Importación de emisiones (XLSX)
- Ubicación: Dashboard > pestaña "Material sin asignar" > botón "Importar Emisiones (XLSX)".
- Requisitos del servidor: php-zip, php-xml y PhpSpreadsheet (vendor instalado).
- Estructura del archivo:
  - Hoja "Resumen": se consideran filas cuyo campo Visionado contenga "PARA VISIONAR".
  - Hoja "Programas": se importan solamente filas cuyo título coincida exactamente con los de "Resumen".
- El import detecta el encabezado de forma robusta (no requiere fila 2 exacta) y normaliza claves.

## Asignación y visionado
- Material sin asignar se agrupa por "Título Emisión". Desde allí puedes:
  - Asignar una obra a una emisión (individual o masivo).
  - Crear una obra rápida desde el modal si no existe.
  - Auto-sugerencias por similitud de título.
- Asignaciones crean un "Visionado" en estado pendiente. Las visionadoras lo inician desde su dashboard.

## Obras
- Catálogo con filtros por tipo, género, país y año. Soporta series con capítulos.

## Mantenimiento
- En Admin > botón "Reiniciar datos" (sólo en desarrollo) borra datos principales excepto usuarios/roles.

## Índices de base de datos
- Migración `2025_09_02_130000_add_indexes_for_filters.php` agrega índices útiles:
  - emisiones(canal_id, fecha_emision)
  - emisiones(TituloEmision)
  - obras(TituloObra)

## Notas técnicas
- Importación usa PhpSpreadsheet directamente para evitar errores del iterador.
- Comentarios y controladores están documentados en español.
