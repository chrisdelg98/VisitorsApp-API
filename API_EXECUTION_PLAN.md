# Visitor App — Plan de Ejecución: API REST Backend

**Fecha:** Marzo 2026
**Tecnología:** Laravel 12 + Eloquent ORM + MySQL
**Consumidores:** App Android (tablets) · Panel de administración web (futuro)
**Estado:** Plan técnico — previo a implementación

---

## 1. Stack Tecnológico

| Capa | Tecnología | Razón |
|---|---|---|
| Framework | Laravel 12 | Versión activa con soporte hasta febrero 2027, ORM incluido, ecosistema enorme |
| ORM | Eloquent (incluido en Laravel) | Abstrae la base de datos, cambio con 1 línea |
| Base de datos (dev) | MySQL | Ya disponible en servidor actual |
| Base de datos (prod) | MySQL / SQL Server / PostgreSQL | Solo cambia `.env`, el código no se toca |
| Autenticación | Laravel Sanctum | Tokens por dispositivo, liviano, ideal para APIs móviles |
| Almacenamiento imágenes | Sistema de archivos local (con abstracción Laravel Storage) | Migración a S3/Azure Blob solo cambiando config |
| Servidor web | Apache (ya instalado) + mod_rewrite | Sin instalación adicional |
| PHP | 8.2+ | Requerido por Laravel 12 |

---

## 2. Principio Clave: Portabilidad Total

Todo lo que pueda cambiar entre ambientes vive **únicamente** en el archivo `.env`.
El código nunca tiene credenciales, IPs ni nombres de base de datos escritos directamente.

```
.env (desarrollo — tu servidor)          .env (producción — Azure)
──────────────────────────────           ──────────────────────────────
DB_CONNECTION=mysql                      DB_CONNECTION=sqlsrv
DB_HOST=localhost                        DB_HOST=servidor-azure.database.windows.net
DB_PORT=3306                             DB_PORT=1433
DB_DATABASE=visitors_dev                 DB_DATABASE=visitors_prod
DB_USERNAME=usuario_dev                  DB_USERNAME=usuario_prod
DB_PASSWORD=password_dev                 DB_PASSWORD=password_prod

APP_URL=https://visitors-api.midominio.com    APP_URL=https://api.eflglobal.com

FILESYSTEM_DISK=local                    FILESYSTEM_DISK=azure   ← o s3
```

**Migrar de tu servidor a Azure = editar el `.env` + importar la base de datos. Nada más.**

---

## 3. Arquitectura del Proyecto

### Estructura de carpetas (dentro de Laravel)

```
visitors-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/                   ← versionado desde el inicio
│   │   │           ├── AuthController
│   │   │           ├── StationController
│   │   │           ├── VisitorController
│   │   │           ├── VisitController
│   │   │           └── AdminController
│   │   ├── Middleware/
│   │   │   ├── ValidateApiKey            ← autenticación por API key (tablets)
│   │   │   ├── ForceHttps               ← redirigir a HTTPS siempre
│   │   │   └── SecurityHeaders          ← headers de seguridad en toda respuesta
│   │   └── Requests/                    ← validación de inputs (previene inyecciones)
│   │       ├── StoreVisitorRequest
│   │       └── StoreVisitRequest
│   ├── Models/                          ← Eloquent ORM (agnóstico a la DB)
│   │   ├── Station
│   │   ├── Visitor
│   │   ├── Visit
│   │   └── VisitImage
│   ├── Repositories/                    ← capa de acceso a datos (patrón Repository)
│   │   ├── VisitorRepository
│   │   └── VisitRepository
│   ├── Services/                        ← lógica de negocio separada del controlador
│   │   ├── VisitorService
│   │   ├── VisitService
│   │   └── ImageService
│   └── Resources/                       ← transforma los datos antes de responder
│       ├── VisitorResource
│       └── VisitResource
├── database/
│   ├── migrations/                      ← estructura de tablas versionada
│   └── seeders/                         ← datos de prueba
├── routes/
│   └── api.php                          ← todas las rutas del API
├── storage/
│   └── app/
│       └── visitors/                    ← imágenes (local en dev, cloud en prod)
└── .env                                 ← NUNCA se sube a Git
```

---

## 4. Modelo de Base de Datos

### Tablas principales

```
stations
─────────────────────────────────────────
id              UUID (PK)
name            VARCHAR(100)
code            VARCHAR(20) UNIQUE       ← código que ingresa el admin en la tablet
api_key         VARCHAR(64) UNIQUE       ← token de autenticación de esa estación
is_active       BOOLEAN
created_at      TIMESTAMP
updated_at      TIMESTAMP

visitors
─────────────────────────────────────────
id              UUID (PK)
first_name      VARCHAR(100)
last_name       VARCHAR(100)
document_number VARCHAR(50) NULLABLE
document_type   ENUM(DUI, PASSPORT, LICENSE, OTHER)
email           VARCHAR(150) NULLABLE
phone           VARCHAR(30) NULLABLE
company         VARCHAR(150) NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP

visits
─────────────────────────────────────────
id              UUID (PK)
station_id      UUID (FK → stations)
visitor_id      UUID (FK → visitors)
visitor_type    VARCHAR(50)              ← Visitor, Contractor, Driver, etc.
visit_reason    VARCHAR(100)             ← Interview, Delivery, Technical Service, etc.
visit_reason_custom  VARCHAR(255) NULLABLE  ← solo si visit_reason = Other
visiting_person VARCHAR(150)
check_in        TIMESTAMP
check_out       TIMESTAMP NULLABLE
status          ENUM(active, completed)
badge_printed   BOOLEAN DEFAULT false
notes           TEXT NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP

visit_images
─────────────────────────────────────────
id              UUID (PK)
visit_id        UUID (FK → visits)
type            ENUM(personal_photo, doc_front, doc_back)
file_path       VARCHAR(500)             ← ruta relativa, nunca absoluta
file_hash       VARCHAR(64)              ← SHA-256 para detectar duplicados
created_at      TIMESTAMP
```

### Por qué UUID en lugar de IDs numéricos

- No expone cuántos registros existen en la base de datos
- No se pueden adivinar IDs de otros registros
- Portables entre bases de datos sin conflictos

---

## 5. Endpoints del API (V1)

### Base URL
```
https://visitors-api.tudominio.com/api/v1/
```

### Autenticación y Estaciones

| Método | Endpoint | Descripción | Auth |
|---|---|---|---|
| `POST` | `/auth/validate-station` | Valida código de estación, retorna API key | Pública* |
| `GET` | `/station/me` | Info de la estación autenticada | API Key |

> *Solo pública en la primera llamada — con rate limit muy estricto (5 intentos/hora/IP)

### Visitantes

| Método | Endpoint | Descripción | Auth |
|---|---|---|---|
| `GET` | `/visitors/search?q=nombre` | Busca visitantes para visita recurrente | API Key |
| `GET` | `/visitors/{id}/latest-visit` | Último registro de un visitante | API Key |
| `POST` | `/visitors` | Crea nuevo visitante | API Key |
| `PUT` | `/visitors/{id}` | Actualiza datos de visitante | API Key |

### Visitas

| Método | Endpoint | Descripción | Auth |
|---|---|---|---|
| `POST` | `/visits` | Registra nueva visita (check-in) | API Key |
| `PATCH` | `/visits/{id}/checkout` | Registra salida (check-out) | API Key |
| `GET` | `/visits/{id}` | Detalle de una visita | API Key |
| `GET` | `/visits/active` | Visitas activas en esta estación | API Key |

### Imágenes

| Método | Endpoint | Descripción | Auth |
|---|---|---|---|
| `POST` | `/visits/{id}/images` | Sube imagen (foto, doc frente, doc reverso) | API Key |
| `GET` | `/visits/{id}/images/{type}` | Obtiene imagen por tipo | API Key |

### Admin Panel (autenticación separada)

| Método | Endpoint | Descripción | Auth |
|---|---|---|---|
| `POST` | `/admin/login` | Login administrador | Pública* |
| `GET` | `/admin/visits` | Lista de visitas con filtros y paginación | Bearer Token |
| `GET` | `/admin/visits/{id}` | Detalle completo de visita | Bearer Token |
| `PATCH` | `/admin/visits/{id}/status` | Cambiar estado de visita | Bearer Token |
| `GET` | `/admin/stats` | Estadísticas por estación y periodo | Bearer Token |
| `GET` | `/admin/stations` | Lista de estaciones | Bearer Token |
| `POST` | `/admin/stations` | Crear estación | Bearer Token |

> *Login admin también con rate limit estricto (10 intentos/hora/IP)

### Formato de respuesta estándar (siempre consistente)

```json
// Éxito
{
  "success": true,
  "data": { ... },
  "message": "Visit registered successfully",
  "meta": {                         ← solo en listas paginadas
    "current_page": 1,
    "per_page": 20,
    "total": 150
  }
}

// Error
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "first_name": ["The first name field is required."]
  },
  "code": "VALIDATION_ERROR"
}
```

---

## 6. Sistema de Autenticación (dos capas)

### Capa 1 — Tablets Android (API Key por estación)

Cada tablet/estación tiene su propia API Key única generada al registrarse.

```
Flujo:
1. Admin registra estación en panel web → genera API Key
2. Admin ingresa código de estación en la tablet
3. Tablet llama POST /auth/validate-station → recibe API Key
4. API Key se guarda en DataStore de la tablet
5. Cada request lleva header: X-API-Key: {api_key}
6. Middleware verifica API Key en cada request
7. Si la estación se desactiva → todas sus requests fallan automáticamente
```

**Ventajas:**
- Si se pierde o roba una tablet, se revoca solo su API Key sin afectar las demás
- Cada request queda registrado con qué estación lo hizo
- Sin sesiones que vencen ni re-login

### Capa 2 — Panel de Administración (Bearer Token / Sanctum)

```
Flujo:
1. Admin hace login con email + password
2. API retorna Bearer Token con expiración (24 horas)
3. Panel envía header: Authorization: Bearer {token}
4. Al cerrar sesión, el token se invalida en la base de datos
```

---

## 7. Políticas de Seguridad y Protección contra Ataques

### 7.1 Rate Limiting (límites por IP)

| Endpoint / Grupo | Límite | Ventana | Consecuencia |
|---|---|---|---|
| `/auth/validate-station` | 5 intentos | 1 hora | Bloqueo 1 hora |
| `/admin/login` | 10 intentos | 1 hora | Bloqueo 1 hora |
| API general (tablets autenticadas) | 120 requests | 1 minuto | HTTP 429 |
| Subida de imágenes | 30 uploads | 1 minuto | HTTP 429 |
| Admin panel autenticado | 200 requests | 1 minuto | HTTP 429 |
| Global por IP (sin autenticar) | 30 requests | 1 minuto | HTTP 429 |

Implementación: **middleware nativo de Laravel** (`ThrottleRequests`) — sin librerías adicionales.

### 7.2 Headers de Seguridad (en cada respuesta)

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'none'
```

### 7.3 CORS (Cross-Origin Resource Sharing)

Solo se permiten requests desde orígenes autorizados.
La app Android no usa CORS (no es un navegador), pero el panel web futuro sí.

```
Orígenes permitidos:  lista blanca definida en .env
Métodos permitidos:   GET, POST, PUT, PATCH, DELETE
Headers permitidos:   Content-Type, Authorization, X-API-Key
Credenciales:         Solo para rutas de admin
```

### 7.4 Validación de Inputs (previene SQL Injection y XSS)

- Eloquent ORM usa **prepared statements** en todos los queries — SQL Injection imposible por diseño
- Todos los inputs pasan por **Form Requests** de Laravel antes de llegar al controlador
- Se valida tipo, longitud máxima, formato y caracteres permitidos en cada campo
- Ningún input del usuario se usa directamente en queries crudos

### 7.5 Protección de Archivos (imágenes)

- Las imágenes **nunca** son accesibles por URL directa pública
- Se sirven a través del API con autenticación: `GET /visits/{id}/images/{type}`
- Se almacenan fuera del `public/` con nombres de archivo generados aleatoriamente (UUID)
- Se valida tipo MIME real del archivo (no solo la extensión)
- Tamaño máximo por imagen: **5 MB**
- Tipos permitidos: `image/jpeg`, `image/png`, `image/webp`

### 7.6 HTTPS Obligatorio

- Middleware `ForceHttps` redirige cualquier request HTTP a HTTPS
- SSL via Let's Encrypt (ya disponible en cPanel) en el subdominio del API
- Sin excepción: toda comunicación cifrada

### 7.7 Protección contra Fuerza Bruta

- Rate limiting estricto en login y validación de estación (detallado en 7.1)
- Después de X intentos fallidos: bloqueo temporal por IP
- Mensajes de error genéricos en login — nunca especificar si el email existe o no

### 7.8 Logging y Auditoría

- Cada request queda registrado: IP, endpoint, estación, timestamp, código de respuesta
- Intentos de autenticación fallidos generan alerta en log
- Los logs se rotan automáticamente (no crecen infinitamente)
- Los logs **nunca** contienen datos sensibles (passwords, tokens completos)

### 7.9 Variables de Entorno

- El `.env` se agrega a `.gitignore` — nunca se sube al repositorio
- Las credenciales de producción solo las conoce quien hace el deploy
- En el repo existe `.env.example` con las claves pero sin valores reales

---

## 8. Patrón de Arquitectura Interna

### Por qué Repository + Service + Resource

```
Request  →  Controller  →  Service  →  Repository  →  Eloquent (ORM)  →  DB
                ↓
            Resource  →  Response JSON

Controller:   Solo recibe el request y retorna la respuesta. Sin lógica.
Service:      Lógica de negocio (crear visita, validar estado, etc.)
Repository:   Todo lo que toca la base de datos. Aislado del resto.
Eloquent:     Traduce a SQL según la base de datos configurada.
Resource:     Formatea el modelo antes de enviarlo como JSON.
```

**Beneficio principal:** Si mañana cambia la base de datos, solo se revisa el Repository. El Controller, Service y Resource no se tocan.

---

## 9. Versionado del API

Desde el inicio se versiona como `/api/v1/`.

- La app Android v1.x consume `/api/v1/`
- Si en el futuro hay cambios incompatibles, se crea `/api/v2/` sin romper la app existente
- Las tablets en campo seguirán funcionando con `/v1/` mientras se actualiza la app

---

## 10. Plan de Ejecución por Fases

### Fase 1 — Infraestructura base (Día 1–2)

```
[ ] Crear subdominio en cPanel: visitors-api.tudominio.com
[ ] Habilitar SSL (Let's Encrypt) en ese subdominio
[ ] Verificar PHP 8.2+ disponible
[ ] Instalar Composer (gestor de dependencias PHP)
[ ] Instalar Laravel 12 en el subdominio
[ ] Verificar que Apache responde en HTTPS
[ ] Crear base de datos MySQL exclusiva para el proyecto
[ ] Configurar .env con credenciales locales
[ ] Confirmar que Laravel conecta a MySQL correctamente
```

### Fase 2 — Base de datos y modelos (Día 3–4)

```
[ ] Crear migraciones: stations, visitors, visits, visit_images
[ ] Crear modelos Eloquent con relaciones definidas
[ ] Ejecutar migraciones en MySQL
[ ] Crear seeders con datos de prueba
[ ] Verificar relaciones entre modelos
```

### Fase 3 — Seguridad base (Día 5)

```
[ ] Instalar y configurar Laravel Sanctum
[ ] Configurar CORS
[ ] Implementar middleware de headers de seguridad
[ ] Implementar middleware ForceHttps
[ ] Configurar rate limiting por grupos de rutas
[ ] Crear sistema de API Keys para estaciones
```

### Fase 4 — Endpoints principales (Día 6–10)

```
[ ] Auth: validar estación → retornar API Key
[ ] Visitors: search, create, update, latest-visit
[ ] Visits: create (check-in), checkout, detail
[ ] Images: upload, retrieve
[ ] Implementar Form Requests con validaciones completas
[ ] Implementar API Resources para respuestas consistentes
```

### Fase 5 — Endpoints de Admin (Día 11–13)

```
[ ] Login admin con Bearer Token
[ ] Listado de visitas con filtros y paginación
[ ] Detalle de visita completo (con imágenes)
[ ] Cambio de estado de visita
[ ] Estadísticas básicas
[ ] Gestión de estaciones
```

### Fase 6 — Integración con Android (Día 14–16)

```
[ ] Integrar llamadas al API en la app Android (Retrofit)
[ ] Sincronización al confirmar nueva visita
[ ] Sincronización al registrar salida
[ ] Upload de imágenes post-confirmación
[ ] Manejo de errores y modo offline (guardar localmente si no hay red)
[ ] Pruebas end-to-end en tablet real
```

### Fase 7 — Pruebas y hardening (Día 17–20)

```
[ ] Pruebas de carga básicas
[ ] Verificar todos los rate limits
[ ] Revisar todos los headers de seguridad
[ ] Pruebas con red inestable (simular pérdida de conexión)
[ ] Documentar endpoints (Postman Collection)
[ ] Revisión final de logs
```

---

## 11. Dependencias Laravel a Instalar

| Paquete | Para qué sirve |
|---|---|
| `laravel/sanctum` (incluido) | Autenticación API por tokens |
| `spatie/laravel-query-builder` | Filtros y búsquedas avanzadas para admin panel |
| `intervention/image` | Procesamiento de imágenes (redimensionar, comprimir) |
| `league/flysystem-azure-blob-storage` | Soporte Azure Blob cuando se migre a Azure |

Sin librerías de terceros innecesarias. Menos dependencias = menos superficie de ataque.

---

## 12. Lo que NO hará este API (límites claros)

| Fuera del alcance | Razón |
|---|---|
| Envío de emails o notificaciones push | No es requerimiento actual |
| Autenticación OAuth (Google, etc.) | No aplica para tablets corporativas |
| Pagos o transacciones | No es el propósito |
| Lógica de impresión | Eso se queda en la app Android, el API no sabe de impresoras |
| Panel web de administración | Es un proyecto separado que consumirá este API |

---

## Resumen Ejecutivo

> **Una API REST versionada, stateless, 100% sobre HTTPS, con autenticación de dos capas** (API Key por estación para tablets + Bearer Token para administradores), rate limiting por IP en todos los endpoints sensibles, validación estricta de inputs, imágenes protegidas detrás de autenticación y arquitectura en capas que abstrae completamente la base de datos.
>
> **Cambiar de MySQL a SQL Server o PostgreSQL = editar 4 líneas en `.env` + importar datos. El código no se toca.**
>
> Construida para ser desplegada primero en el servidor Linux actual y migrada a Azure con el mínimo esfuerzo posible cuando IT Global confirme la infraestructura definitiva.

---

*Documento generado: Marzo 2026 — Visitor App Backend Plan · Laravel 12*





