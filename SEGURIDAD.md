# Seguridad y plan de respuesta a incidentes

Documento interno. Rellena los datos de contacto y guárdalo donde lo encuentres rápido.

## Qué protege la web

| Riesgo | Medida |
|---|---|
| Tráfico interceptado | HTTPS obligatorio (redirección en `.htaccess`) y HSTS |
| Contraseñas robadas | Nunca se guardan: solo huella `password_hash` (bcrypt) con sal |
| Copia de la base de datos | Nombre, teléfono, email, notas e IP cifrados (libsodium, clave de 256 bits derivada del `secret` de `config.php`) |
| Datos que sobran | Los datos personales de cada reserva se borran a las 24 h; cuentas sin activar a los 7 días; registro de auditoría a los 3 años. El aviso por email al restaurante no lleva teléfono ni email |
| Terceros sin necesidad | Tipografías y fotos servidas desde el propio hosting; Google Maps solo al pulsar «Mostrar el mapa»; analítica y píxeles solo con consentimiento |
| Contraseña del correo (SMTP) | Cifrada en la base de datos y nunca enviada a la app |
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

## Revisión legal (octubre 2026)

- Normas aplicadas: RGPD, LOPDGDD (información por capas del art. 11), LSSI-CE (aviso legal y cookies, art. 10 y 22.2) y Guía de cookies de la AEPD (actualizada en 2023 y 2024).
- La reforma europea de cookies y RGPD («Digital Omnibus», propuesta de noviembre de 2025) **no está aprobada**. Hasta que se publique y entre en vigor, se aplican las normas actuales; cuando se apruebe, habrá que revisar el plazo para volver a preguntar tras un rechazo y las excepciones de analítica.
- Transferencias a EE. UU. (WhatsApp, Google, Meta): Marco de Privacidad de Datos UE-EE. UU., confirmado por el Tribunal General en septiembre de 2025 y recurrido ante el TJUE. Si el TJUE lo anulara, habría que revisar la política de privacidad.
- Ley 11/2023 de accesibilidad: las microempresas (menos de 10 trabajadores y hasta 2 M€) están exentas en servicios. Aun así, la web sigue buenas prácticas (contraste, etiquetas, teclado).
- Pendiente del titular: rellenar en la app el titular, el NIF, el email de contacto y, si es una sociedad, los datos del Registro Mercantil. Borrar periódicamente los chats de WhatsApp de confirmación.

## Contactos

- Responsable del tratamiento: ______________________
- Técnico de la web: ______________________
- Soporte de Hostinger: hPanel → Ayuda
- AEPD: www.aepd.es · 901 100 099

## Copias de seguridad

- Hostinger hace copias automáticas (hPanel → Archivos → Copias de seguridad).
- Guarda aparte una copia de `config.php`: **sin él no se pueden leer los datos cifrados**.
