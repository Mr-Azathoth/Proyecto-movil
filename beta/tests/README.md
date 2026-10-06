# Pruebas de integracion (beta)

Pruebas contra el beta que esta corriendo (Apache local, base `centrotec_beta`). Verifican permisos entre empresas y
sucursales, stock por sucursal, traspasos, reservas, carreras en paralelo, boleta y el frontend (con un DOM simulado).

## Seguridad

- `seed.php` y `reset.php` solo corren por consola y **abortan si la base no es `centrotec_beta`**. Nunca tocan datos reales.
- Las claves de las cuentas de prueba son aleatorias y se guardan en `tests/.creds.json` (ignorado por git).
- `tests/.htaccess` bloquea todo acceso web a esta carpeta. Comprueba despues de desplegar:
  `https://beta.centrotec.cl/tests/.creds.json` debe responder 403.

## Uso

Desde `beta/` (PHP y Node en el PATH, o con `PHP_BIN`):

```
node tests/run_all.js          # reinicia datos antes de cada grupo y corre todo
```

Por separado:

```
php tests/reset.php            # borra las empresas de prueba (testsuca / testsucb)
php tests/seed.php             # crea empresas, sucursales, usuarios, reparaciones e inventario
node tests/suc.js              # permisos y aislamiento
node tests/stock.js            # stock, traspasos, importar/exportar, carreras
node tests/ui_inventario.js    # frontend real sobre DOM simulado (corre despues de stock.js)
node tests/fix.js              # regresion del code review
node tests/tel.js              # direccion/telefono y boleta
node tests/smoke.js            # flujos de reparacion
```

Cada suite imprime `PASS`/`FAIL` y termina con `N OK, M fallos`.

## Notas

- Las suites no son independientes entre si: `run_all.js` reinicia los datos antes de cada grupo.
- `ui_inventario.js` necesita el estado que deja `stock.js`.
- Variables: `TEST_HOST` (por defecto `beta.centrotec.cl`), `TEST_PORT` (8081), `PHP_BIN`.
- La prueba de esquema renombra temporalmente la tabla `traspasos` y quita/recrea `sucursales.telefono`: solo en beta.
