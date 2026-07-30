# AlMac
Aplicacion web para gestionar un almacen y su stock

No necesita base de datos ya que almacena todo en json

## Credenciales iniciales:
Usuario:Contraseña (SuperAdmin) admin:admin
Se recomienda cambiar la contraseña tras el primer inicio y crear usuarios para operar el almacen.

## Instrucciones de instalación:
Descargar los archivos y guardarlos en una carpeta llamada almac dentro de la carpeta publica, ejemplo linux: /var/www/html/almac

## Requisitos:
PHP y Apache/Nginx

<img src="https://github.com/X43K/AlMac/blob/87c326cdf751e6985176d8685ec61ce8da022620/images/ejemplo1.webp">

## Tipos de usuarios:
- Lector: Puede consultar en el almacen al que pertenece.
- Operario: Puede hacer todo lo anterior y tambien generar entradas y salidas de productos, asi como crear nuevos productos en el almacen al que pertenece.
- Admin: Puede hacer todo lo anterior y crear/editar/eliminar usuarios en el almacen al que pertenece. Es el administrador del almacen al que ha sido asignado.
- SuperAdmin: Puede hacer todo lo anterior pero en cualquier almacen, ademas de poder crear nuevos almacenes y asignarles administradores. Es el administrador general de la WebApp.

## Cómo contribuir
- Reporta errores en la sección Issues.
- Sugiere mejoras.
- Prueba la aplicación y envía feedback.
