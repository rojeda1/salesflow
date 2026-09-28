# SalesFlow

[![Backend CI](https://github.com/rojeda1/salesflow/actions/workflows/backend-ci.yml/badge.svg)](https://github.com/rojeda1/salesflow/actions/workflows/backend-ci.yml)

Sistema demostrativo de ventas e inventario, desarrollado de forma
incremental para mostrar prácticas de ingeniería de software.

## Estado actual

- Backend Laravel 13 con PHP 8.4.
- PostgreSQL 18.
- Entorno de desarrollo con Docker Compose.
- Catálogo público de productos activos con paginación.
- Restricciones de SKU único y precio no negativo.
- Pruebas de integración con PostgreSQL.
- CI con GitHub Actions: formato, pruebas y auditoría de dependencias.

## Próximas funcionalidades

- Autenticación y autorización.
- Administración de productos.
- Movimientos de inventario.
- Ventas con control transaccional de existencias.
- Interfaz React con TypeScript.
- Reportes con Python.
- Despliegue automatizado.

## Arquitectura actual

La API utiliza controladores para atender solicitudes HTTP,
Eloquent para persistencia y Resources para definir las respuestas JSON.

PostgreSQL protege la unicidad de los SKU y rechaza precios negativos.
Los precios se almacenan como valores decimales y se exponen como cadenas.

## Endpoint disponible

GET /api/v1/products

Devuelve productos activos, ordenados por ID, con 15 resultados por página.

## Verificaciones locales

Con el entorno configurado y los servicios en ejecución:

    docker compose exec backend php artisan test
    docker compose exec backend ./vendor/bin/pint --test

Las pruebas de integración utilizan la base exclusiva salesflow_testing.
La suite comprueba el entorno y el nombre de la base antes de reconstruir
sus tablas.

## Integración continua

El workflow Backend CI:

1. Construye la imagen PHP.
2. Inicia PostgreSQL.
3. Instala las dependencias de composer.lock.
4. Prepara la base de pruebas.
5. Comprueba el formato con Laravel Pint.
6. Ejecuta las pruebas.
7. Audita las dependencias con Composer.

## Alcance

Proyecto de demostración en desarrollo. Todavía no está preparado
para producción. React, Python y el despliegue automatizado están
planificados y aún no implementados.