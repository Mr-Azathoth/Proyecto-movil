# Plan de Implementación Cloudflare — Reparo SaaS

**Preparado:** 2026-06-25
**Aplicar cuando:** Se contrate hosting con dominio público

---

## Estado actual (etapa de pruebas local)

- Servidor: XAMPP local (Windows 11)
- Sin dominio público → Cloudflare no puede interceptar tráfico aún
- Lo que se puede hacer ahora: preparar código (Turnstile, rate limiting PHP)

---

## Fase 1 — Día 1 del hosting: Conectar dominio

**Objetivo:** Tráfico enrutado por Cloudflare antes de que cualquier usuario acceda.

- [ ] Apuntar nameservers del dominio a los servidores de Cloudflare
- [ ] Crear registro A apuntando al IP del servidor, con proxy activado (nube naranja)
- [ ] SSL/TLS → modo **Full** (o Full Strict si el hosting incluye certificado válido)
- [ ] SSL/TLS → Edge Certificates → activar **HSTS**
  - max-age: 15768000 (6 meses)
  - includeSubDomains: sí
  - Preload: no (hasta confirmar que todo funciona)

---

## Fase 2 — Día 1-2: Seguridad base

**Objetivo:** Bloquear ataques automatizados sin configuración compleja.

- [ ] Security → WAF → Managed Rules → activar **Cloudflare Free Managed Ruleset** en modo Block
- [ ] Security → Settings → Security Level: **Medium**
- [ ] Security → Settings → Challenge Passage: **30 minutos**
- [ ] Security → Settings → **Bot Fight Mode**: activar
- [ ] Rules → Transform Rules → Modify Response Headers → agregar headers de seguridad:

  | Header | Valor |
  |---|---|
  | `X-Frame-Options` | `DENY` |
  | `X-Content-Type-Options` | `nosniff` |
  | `Referrer-Policy` | `strict-origin-when-cross-origin` |
  | `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |

---

## Fase 3 — Día 2-3: Proteger panel de super admin

**Objetivo:** El panel `/admin*` solo accesible desde Chile y con segunda capa de autenticación.

- [ ] Security → WAF → Firewall Rules → crear regla:
  ```
  (http.request.uri.path contains "/admin") AND (ip.geoip.country ne "CL")
  → Acción: Block
  ```
  *(Si se necesita acceso desde otro país, cambiar a Challenge)*

- [ ] Security → WAF → Rate Limiting → crear regla:
  ```
  URL: /admin_login.php
  Método: POST
  Límite: 5 requests por 60 segundos por IP
  Acción: Block por 10 minutos
  ```

- [ ] Zero Trust → Access → Applications → agregar aplicación:
  ```
  Nombre: Reparo Admin
  Ruta: /admin*
  Política: Allow — Email equals jorgealcayagaperez@gmail.com
  Método: One-time PIN (OTP al correo)
  ```
  *Esto agrega una pantalla de verificación de Cloudflare ANTES del login PHP.*

---

## Fase 4 — Semana 1: Performance y caché

**Objetivo:** Reducir carga del servidor cacheando assets estáticos en el edge de Cloudflare.

- [ ] Caching → Cache Rules → crear regla para **cachear assets**:
  ```
  Condición: URI Path starts with "/reparo/assets/"
  Cache TTL edge: 7 días
  Cache TTL browser: 1 día
  ```

- [ ] Caching → Cache Rules → crear regla para **NO cachear PHP**:
  ```
  Condición: URI Path ends with ".php"
  Cache: Bypass
  ```

- [ ] Scrape Shield → **Email Address Obfuscation**: activar
  *(Oculta emails visibles en HTML de los tenants a bots de spam)*

- [ ] Activar **Turnstile** en `/admin_login.php`
  *(El código ya estará integrado desde la etapa de pruebas)*

---

## Fase 5 — Mes 1: Monitoreo y ajuste fino

**Objetivo:** Validar que las reglas no bloqueen usuarios legítimos y ajustar umbrales.

- [ ] Revisar Security → Events durante los primeros 7 días en modo observación
- [ ] Ajustar geoblocking si hay clientes fuera de Chile
- [ ] Revisar rate limiting: si usuarios legítimos son bloqueados, subir umbral a 10 req/min
- [ ] Analytics → Traffic: confirmar que el caché tiene un hit rate > 60% en assets
- [ ] HSTS Preload: agregar el dominio a la lista preload de browsers una vez estable 30 días

---

## Implementación pendiente en código (hacer antes del hosting)

Estas tareas se pueden completar ahora en la etapa local y estarán listas para producción:

- [ ] **Turnstile en admin_login.php**: integrar widget JS + verificación server-side PHP con claves de test
- [ ] **Rate limiting en PHP** (stopgap): bloquear IP tras 5 intentos fallidos usando APCu o archivo temporal

---

## Notas

- Cloudflare Free Plan es suficiente para todas las fases 1-4.
- Zero Trust Access (Fase 3) tiene un free tier de hasta 50 usuarios.
- Rate Limiting en Free Plan tiene límite de 10.000 requests al mes; suficiente para el panel admin.
- Si el hosting usa un IP compartido, verificar con el proveedor antes de activar HSTS.
