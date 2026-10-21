# Seguridad y plan de respuesta a incidentes

Documento interno. Rellena los datos de contacto y guárdalo donde lo encuentres rápido.

## Qué protege la web

| Riesgo | Medida |
|---|---|
| Tráfico interceptado | HTTPS obligatorio (redirección en `.htaccess`) y HSTS |
| Contraseñas robadas | Nunca se guardan: solo huella `password_hash` (bcrypt) con sal |
| Copia de la base de datos | Nombre, teléfono, email, notas e IP cifrados (libsodium, clave de 256 bits derivada del `secret` de `config.php`) |
| Datos que sobran | Los datos personales de cada reserva se borran a las 24 h |
| Fuerza bruta | Bloqueo de 15 min tras 10 intentos (panel y clientes) |
| Cuentas con emails ajenos | Doble opt-in: la cuenta no funciona hasta abrir el enlace del email |
| Peticiones desde otras webs | Comprobación de origen y cookies `SameSite=Lax`, `HttpOnly`, `Secure` |
| Inyección SQL | Todas las consultas usan sentencias preparadas |
| Archivos subidos | Solo imágenes; se recodifican a JPG y en `uploads/` no se ejecuta código |
| Demostrar consentimientos | Tabla `audit_log`: registro, reservas, cambios de contraseña y datos (fecha, IP cifrada, navegador) |

## Si hay una brecha de seguridad (RGPD art. 33 y 34)

1. **Contener (primera hora).** Cambia la contraseña del panel y la de hPanel. Si alguien ha entrado al hosting, cambia también la de la base de datos (si usas MySQL) y revisa los archivos modificados en el administrador de archivos.
2. **Evaluar.** Qué datos se han visto afectados (reservas, cuentas), desde cuándo y a cuántas personas afecta. Recuerda que los datos personales están cifrados: si **no** se ha filtrado `config.php`, una copia de la base de datos no es legible.
3. **Notificar a la AEPD en menos de 72 horas** desde que lo sepas, si hay riesgo para las personas: sede electrónica de la AEPD → «Notificación de brechas de datos personales».
4. **Avisar a los clientes afectados sin demora** si el riesgo es alto: qué ha pasado, qué datos, qué hemos hecho y qué pueden hacer (por ejemplo, cambiar su contraseña).
5. **Documentar** todo (fechas, decisiones, medidas), aunque no haga falta notificar.
6. **Corregir** la causa y revisar que no vuelva a pasar.

## Contactos

- Responsable del tratamiento: ______________________
- Técnico de la web: ______________________
- Soporte de Hostinger: hPanel → Ayuda
- AEPD: www.aepd.es · 901 100 099

## Copias de seguridad

- Hostinger hace copias automáticas (hPanel → Archivos → Copias de seguridad).
- Guarda aparte una copia de `config.php`: **sin él no se pueden leer los datos cifrados**.
