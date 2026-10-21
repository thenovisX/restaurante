# Web y app de reservas para restaurante

Web pública (diseño nocturno, restaurante de ejemplo «Novia») con:

- **Reserva en 3 pasos**: personas y día, hora libre, datos. Al final hay que aceptar las políticas.
- **Cuentas de clientes**: registro y acceso. El cliente ve sus reservas, las cancela él mismo hasta X horas antes, edita sus datos y puede borrar su cuenta.
- **Políticas de reserva**: plazo de cancelación, minutos de cortesía y normas propias (grupos, alergias, niños, mascotas…), editables desde la app.
- **Instalable como app desde el navegador** (PWA): botón «Instalar la app» en Android y escritorio, e instrucciones para iPhone. El icono se genera solo con la inicial del restaurante.
- **Carta como un librito de papel** que se hojea (flechas, deslizando el dedo o tocando la esquina), con portada, pestañas de índice y una página por sección.
- **Alérgenos con iconos de colores** (dibujo propio, `web/assets/alergenos.svg`) junto a cada plato y la explicación de los 14 en la última página de la carta.
- **Aviso de cookies** al entrar (Aceptar / Rechazar / Configurar). Google Analytics, Tag Manager y el píxel de Meta solo se cargan si el visitante acepta (modo de consentimiento v2 de Google).
- **SEO**: descripción para Google, verificación de Search Console, `sitemap.xml`, `robots.txt` y datos estructurados de restaurante con horario y redes. Todo se configura en el panel → Ajustes → SEO y estadísticas.
- Horario, contacto y textos legales.
- Las fotos de ejemplo son de Unsplash (licencia gratuita, se muestran los autores en el pie). La web las descarga una vez y las sirve desde el propio hosting (`foto.php` → `uploads/cache/`), así el navegador del visitante no se conecta a Unsplash. Las fotos propias se cambian desde la app.

Más una **app de gestión** para el móvil con tres secciones:

- **Hoy**: las reservas del día separadas en comida y cena, con botones para confirmar, rechazar o llamar. Arriba salen las reservas de otros días que faltan por confirmar.
- **Calendario**: el mes entero con las personas reservadas cada día. Al tocar un día ves sus reservas. Desde aquí también se cierran días sueltos (festivos o vacaciones).
- **Carta**: platos, precios, fotos y alérgenos. Un interruptor marca un plato como agotado y lo quita de la web al momento.
- **Usuarios**: en Ajustes → Usuarios el administrador crea usuarios para el personal. Todos ven las reservas y la carta; a cada uno se le activa o no gestionar reservas (aceptar, rechazar, apuntar y borrar), cambiar la carta y cambiar los ajustes. También se pueden desactivar o borrar.
- **Horario de cocina** (Ajustes → Horario de cocina): si se activa, las reservas solo se ofrecen con la cocina abierta y la web lo muestra debajo del horario.
- **Buscador en Ajustes** para encontrar cualquier opción.
- **Enlace de confirmación**: el cliente ve sus datos y pulsa «Confirmar mi reserva» (abrir el enlace no confirma, porque WhatsApp lo abre solo para la vista previa). Desde el mismo enlace puede cancelarla. Si el restaurante la cancela o la borra, el enlace dice «Reserva cancelada».
- **Aforo con tiempo de mesa**: en Ajustes → Reservas online se indica cuántas personas caben a la vez y cuánto dura una comida (por defecto 90 minutos). Una reserva a las 14:00 ocupa sitio hasta las 15:30, y las horas libres se calculan con todas las reservas que se solapan.
- **Ajustes**, en secciones plegables: datos y presentación, contacto y ubicación, redes sociales (Instagram, Facebook, TikTok, TripAdvisor), fotos de la web, textos de la cinta y del pie, horario, reservas online, políticas, datos legales, avisos y contraseña. Todo se ve en la web al momento.

Cuando entra una reserva, la app avisa con una notificación y llega también un email al restaurante. Si el cliente dejó su email, recibe la confirmación o el rechazo.

En **Hoy** hay un buscador de reservas (nombre o teléfono) y una tira con los próximos 14 días. Se actualiza deslizando hacia abajo. En el ordenador o la tablet el menú pasa al lateral.

### Enviar los emails desde Gmail

Por defecto los emails salen del correo del hosting y a veces llegan a spam. Para enviarlos desde una cuenta de Gmail:

1. En la cuenta de Google, activa la **verificación en 2 pasos** (myaccount.google.com/security).
2. Entra en **myaccount.google.com/apppasswords**, escribe «Web restaurante» y pulsa **Crear**. Copia la contraseña de 16 letras.
3. En la app: **Ajustes → Envío de emails**. Pon la dirección de Gmail y esa contraseña, activa el interruptor y pulsa **Guardar**.
4. Pulsa **Enviar un email de prueba**.

La contraseña se guarda cifrada y nunca vuelve a la app. Si Gmail falla, el email se envía con el correo del hosting y el error se muestra en esa sección. Sirve igual para otros proveedores (por ejemplo, el correo de Hostinger: smtp.hostinger.com, puerto 465).

```
web/        → lo que se sube a Hostinger (PHP + MySQL)
  api/      → API que usan la web y la app
  panel/    → la app en versión web (para iPhone); es una copia de app/www
app/        → la app Android (Capacitor)
  www/      → código de la app (HTML, CSS y JS)
  android/  → proyecto Android (se abre con Android Studio)
scripts/    → utilidades (copiar el panel, generar iconos)
```

---

## 1. Subir la web a Hostinger

### Opción A: con Git (se actualiza sola)

La rama **`hostinger`** contiene solo la web, con `index.php` en la raíz. GitHub la actualiza sola cada vez que cambia `main`. **No conectes `main`**: ahí la web está dentro de `web/` y Hostinger daría error 404.

1. En hPanel → **Avanzado → Git**, borra el repositorio si ya lo habías añadido con `main`.
2. En **Archivos → Administrador de archivos**, vacía `public_html` (Hostinger exige que esté vacía).
3. En **Git → Crear un nuevo repositorio**, rellena:
   - Repositorio: `https://github.com/thenovisX/restaurante.git`
   - Rama: **`hostinger`**
   - Directorio: déjalo **vacío** (así se instala en `public_html`).
4. Pulsa **Crear** y luego **Desplegar**. Si activas el **despliegue automático**, Hostinger te da una URL de webhook: pégala en GitHub → Settings → Webhooks.
5. Sigue con el paso 4 de la opción B (`install.php`).

### Opción B: subiendo el ZIP

1. En **hPanel → Sitios web**, elige el dominio y abre **Bases de datos → Bases de datos MySQL**. Crea una base de datos con su usuario y contraseña, y apúntalos.
2. Descarga **`web-restaurante.zip`** desde la sección [Releases](../../releases/latest) del repositorio.
3. En **Archivos → Administrador de archivos**, entra en `public_html`, sube el ZIP y descomprímelo ahí. Tienen que quedar `index.php`, `api/`, `panel/`… directamente dentro de `public_html`.
4. Abre `https://tudominio.com/install.php` y rellena:
   - los datos de la base de datos (el servidor es `localhost`);
   - el nombre del restaurante y el email donde quieres recibir las reservas;
   - el usuario y la contraseña con los que entrarás en la app.
5. Cuando termine, **borra `install.php`** desde el administrador de archivos.
6. En hPanel → **Seguridad → SSL**, comprueba que el certificado está activo. Es gratis, y la app necesita `https`.

Listo: la web ya funciona en `https://tudominio.com`.

## 2. Instalar la app en Android

1. En el móvil, abre la sección [Releases](../../releases/latest) y descarga **`Reservas-restaurante.apk`**.
2. Ábrelo. Android pedirá permiso para instalar apps de fuera de Play Store: acéptalo.
3. Abre la app **Reservas** y escribe la dirección de la web (`tudominio.com`). Después entra con el usuario y la contraseña que creaste en `install.php`.
4. Acepta el permiso de **notificaciones** para recibir los avisos de reservas nuevas.

Las nuevas versiones se instalan encima de la anterior sin perder nada.

> El APK ya no se compila solo: se genera a mano desde GitHub → **Actions** → «Compilar app Android y web» → **Run workflow**, y se publica en **Releases**. La web sí se publica sola en la rama `hostinger`.

## 3. iPhone

Apple solo deja instalar apps propias con un Mac y una cuenta de desarrollador (99 €/año). Sin eso, la app se usa igual desde Safari:

1. En el iPhone, abre **Safari** y entra en `https://tudominio.com/panel/`.
2. Pulsa el botón **Compartir** y luego **Añadir a pantalla de inicio**.
3. Aparece el icono **Reservas**. Se abre a pantalla completa, como una app.

Para que lleguen las notificaciones en iPhone (iOS 16.4 o posterior), abre la app desde ese icono y pulsa **Ajustes → Activar avisos en este móvil**.

## Cómo llegan las reservas

| Cuándo | Cómo se avisa |
|---|---|
| App abierta o en segundo plano | Notificación en el móvil y aviso dentro de la app (revisa cada 30 segundos) |
| Siempre | Email al restaurante con los datos de la reserva |
| Al confirmar o rechazar | Email al cliente, si dejó su dirección |

Si Android cierra la app del todo para ahorrar batería, siguen llegando los emails. Para avisos instantáneos con la app cerrada habría que añadir Firebase Cloud Messaging; está pendiente como mejora.

---

## Desarrollo

**Probar en tu ordenador** (necesitas PHP 8.1 o superior):

```bash
cd web && php -S localhost:8080
# Abre http://localhost:8080/install.php y elige "SQLite (solo para pruebas)"
```

**Cambiar la app**: edita `app/www/`. Después ejecuta `./scripts/build-panel.sh` para copiarla también a `web/panel/`.

**Compilar el APK en tu PC** (necesitas Android Studio o el Android SDK y JDK 21):

```bash
cd app
npm install
npm run apk     # crea app/android/app/build/outputs/apk/debug/app-debug.apk
```

O abre la carpeta `app/android` con Android Studio y pulsa **Run**.

**Google Play**: crea tu propia clave de firma, guárdala como secreto de GitHub (`ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_PASSWORD`) y sustituye `app/android/keystore/app.keystore`. La clave incluida sirve para instalar el APK a mano, pero es pública y no vale para Play Store.

### API

Todas las rutas son `api/?r=<ruta>`. Las de gestión necesitan la cabecera `X-Token`, que se obtiene con `login`.

| Pública | Gestión |
|---|---|
| `info`, `menu`, `slots&date=`, `reserve` (POST), `login` (POST) | `reservations&from=&to=`, `month&month=`, `changes&since=`, `reservation_status`, `reservation_save`, `reservation_delete`, `admin_menu`, `dish_save` (multipart con foto), `dish_toggle`, `dish_move`, `dish_delete`, `category_save`, `category_move`, `category_delete`, `settings`, `settings_save`, `password`, `logout` |

### Seguridad incluida

Resumen y plan de respuesta a brechas de seguridad: [SEGURIDAD.md](SEGURIDAD.md).


- Datos personales (nombre, teléfono, email, notas, IP) cifrados en la base de datos, y borrado automático de los datos de cada reserva a las 24 h.
- Cuentas de cliente con doble opt-in (activación por email), descarga de datos (portabilidad) y registro de auditoría de consentimientos.
- Aviso de cookies con «Rechazar todas» al mismo nivel que «Aceptar todas»; respeta la señal GPC del navegador.
- Las contraseñas se guardan con `password_hash`, y los tokens de sesión como hash SHA-256 que caduca a los 90 días.
- El login se bloquea durante 15 minutos tras 10 intentos fallidos.
- Las reservas tienen protección contra bots (campo trampa) y un límite de 5 por hora desde la misma conexión.
- `config.php`, `lib/` y `data/` no son accesibles desde el navegador, y en `uploads/` no se puede ejecutar código.
- Las fotos se recodifican a JPG (máximo 1200 px) antes de guardarlas.
- Todas las consultas a la base de datos usan sentencias preparadas.
