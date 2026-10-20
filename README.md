# Web y app de reservas para restaurante

Web pública con **carta**, **reservas online** y horario, más una **app de gestión** para el móvil con tres secciones:

- **Hoy**: las reservas del día separadas en comida y cena, con botones para confirmar, rechazar o llamar. Arriba salen las reservas de otros días que faltan por confirmar.
- **Calendario**: el mes entero con las personas reservadas cada día. Al tocar un día ves sus reservas. Desde aquí también se cierran días sueltos (festivos o vacaciones).
- **Carta**: platos, precios, fotos y alérgenos. Un interruptor marca un plato como agotado y lo quita de la web al momento.
- **Ajustes**: datos del restaurante, horario, aforo, confirmación automática y contraseña.

Cuando entra una reserva, la app avisa con una notificación y llega también un email al restaurante. Si el cliente dejó su email, recibe la confirmación o el rechazo.

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

> Cada vez que se sube un cambio a la rama `main`, GitHub compila automáticamente el APK y la web (pestaña **Actions**) y los publica en **Releases**.

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

- Las contraseñas se guardan con `password_hash`, y los tokens de sesión como hash SHA-256 que caduca a los 90 días.
- El login se bloquea durante 15 minutos tras 10 intentos fallidos.
- Las reservas tienen protección contra bots (campo trampa) y un límite de 5 por hora desde la misma conexión.
- `config.php`, `lib/` y `data/` no son accesibles desde el navegador, y en `uploads/` no se puede ejecutar código.
- Las fotos se recodifican a JPG (máximo 1200 px) antes de guardarlas.
- Todas las consultas a la base de datos usan sentencias preparadas.
