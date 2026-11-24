# Corte a producción: de Vercel + Supabase al servidor propio

Todo el procedimiento se **ensaya antes** con `pruebas/ensayo_corte.sh` (exporta Postgres, importa a una base MySQL temporal y valida contra el origen:
hoy da *Cuadre perfecto: 40 comparaciones iguales*). El día del corte se hace lo mismo, con la base definitiva y la captura congelada.

## Antes (días previos)
- [ ] Servidor listo según **docs/DESPLIEGUE-IIS.md**: verificador sin fallas, prueba de humo OK contra el nombre temporal (ej. `nuevo.lebleu.mx` o la IP), respaldo y restauración ensayados.
- [ ] Ensayo de corte con los datos de ese día: `PG_URL=... bash pruebas/ensayo_corte.sh` termina en `ENSAYO DE CORTE OK`.
- [ ] Un día antes: bajar el **TTL** del registro `A` de `app.lebleu.mx` a 300 s en Hostinger.
- [ ] Avisar a los usuarios (administrador, capitán y socios) la hora del corte y que **recibirán contraseña nueva**.
- [ ] Elegir el momento: de preferencia con el periodo recién cerrado o sin capturas pendientes (el corte a media captura también funciona, solo exige congelar).

## El día (ventana de ~1 hora)
1. **Congelar** la captura en Vercel (avisar; o poner la app en mantenimiento). Anotar la hora.
2. **Exportar** de Supabase (desde un equipo con el repositorio y Node):
   `set DATABASE_URL=postgres://...` y `node scripts/exportar-respaldo.mjs > respaldo.sql`. Copiar `respaldo.sql` al servidor.
3. **Respaldo del destino** (por si se repite): `herramientas\respaldo_lebleu.bat`.
4. **Importar** en el servidor (base `lebleu` vacía, esquema aplicado con `migrar.php`):
   `php -d memory_limit=1G scripts\importar_respaldo.php respaldo.sql` → imprime el número de filas por tabla; compararlas con las de la exportación.
5. **Validar:** `php scripts\verificar_servidor.php` sin fallas. Abrir en el navegador (por el nombre temporal) el estado de cuenta del último periodo y **comparar los totales con los de Vercel**
   (balance operativo de cada socio y totales de gastos). Deben ser idénticos.
6. **Contraseñas:** `php scripts\restablecer_claves.php`. Entrega cada contraseña temporal a su dueño por un canal privado (se muestran una sola vez). Cada quien la cambia al entrar.
7. **DNS:** cambiar el registro `A` de `app.lebleu.mx` a la IP del Lightsail. Con TTL de 300 s propaga en minutos.
8. Cuando `app.lebleu.mx` ya abra el sitio nuevo, activar la redirección HTTPS (si no se hizo) y correr
   `php scripts\prueba_humo.php https://app.lebleu.mx ... --http=http://app.lebleu.mx --capitan=...` → `PRUEBA DE HUMO OK`.
9. Cada usuario entra, cambia su contraseña y confirma que ve lo suyo (administrador: todo; capitán: calendario, bitácora, caja chica; socio: estado de cuenta, reportes, análisis, calendario).
10. Dejar corriendo las tareas `LeBleu-Recurrentes` (07:00) y `LeBleu-Respaldo` (02:00); confirmar al día siguiente sus bitácoras.

## Reversa (si algo grave aparece en la primera hora)
- Volver el registro `A` a Vercel (TTL 300 s: unos minutos). Vercel y Supabase **siguen intactos**: no se apagan.
- Lo capturado en el servidor nuevo después del corte **no** está en Supabase. Si hubo capturas, anotarlas o exportarlas del servidor nuevo (`mysqldump`) antes de revertir y recapturarlas.

## Después
- Mantener Vercel y Supabase **2 semanas** como respaldo, sin capturar en ellos. Pasado ese plazo y con dos respaldos diarios y un ensayo de restauración mensual exitosos, retirarlos.
- Subir el TTL del DNS a su valor normal.
