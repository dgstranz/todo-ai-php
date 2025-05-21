# Aplicación de Gestión de Tareas

Esta es una aplicación web de gestión de tareas (to-do list) desarrollada con PHP y MySQL. La aplicación permite gestionar tareas y subtareas, con funcionalidades para organización y seguimiento de actividades pendientes.

## Características

- Creación, edición y eliminación de tareas
- Marcado de tareas como completadas
- Gestión de fechas de vencimiento con indicadores visuales
- Sistema de subtareas (checklist)
- Filtrado por etiquetas
- Exportación de tareas a formato JSON
- Gestión automática de la base de datos

## Estructura de las Tareas

Cada tarea incluye:
- Título (obligatorio)
- Descripción (opcional)
- Fecha de inicio (automática)
- Fecha de vencimiento
- Subtareas (opcional, un nivel)
- Etiquetas (opcional)

## Requisitos

- XAMPP (Apache y MySQL)
- PHP 7.4 o superior
- Navegador web moderno

## Instalación

1. Clonar o descargar este repositorio en la carpeta htdocs de XAMPP
2. Iniciar los servicios de Apache y MySQL en XAMPP
3. Acceder a la aplicación a través de: http://localhost/todo

La base de datos se creará automáticamente en el primer acceso.

## Funcionalidades Especiales

- Indicador visual para tareas próximas a vencer (menos de 24 horas)
- Indicador visual para tareas vencidas
- Opción para reiniciar la base de datos
- Exportación de tareas en formato JSON 

## Sinopsis de la creación de la aplicación

| Yo | Cursor |
|----|--------|
| Definición de requisitos y funcionalidades | Generación del código base y estructura |
| Revisión y ajuste del código generado | Implementación de funcionalidades principales |
| Pruebas de integración y corrección de errores | Optimización y mejoras de rendimiento |
| Documentación y comentarios del código | Sugerencias de mejoras y características adicionales |

