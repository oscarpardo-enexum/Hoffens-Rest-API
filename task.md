# Tareas — Integración REST Hoffens B2B

Estados: `[x]` terminado · `[~]` en curso · `[ ]` pendiente

## Regla de trabajo

1. Implementar y revisar en local.
2. Validar sintaxis, pruebas y compatibilidad con PrestaShop 1.7.8.11 / PHP 7.1.
3. Subir directamente únicamente los archivos modificados de `hoffensb2b` al ambiente de pruebas.
4. Ejecutar smoke test y registrar el resultado.

No crear ni subir ZIP, TAR ni respaldos durante el despliegue del módulo.

## 1. Base técnica

- [x] Crear módulo independiente `hoffensb2b`, sin modificar el core ni `ws_integracion`.
- [x] Separar aplicación, dominio, puertos, adaptadores e infraestructura.
- [x] Configurar URL, Bearer token, timeouts, reintentos, caché y modos `disabled/shadow/rest`.
- [x] Usar `cardCode` local como única identidad B2B, sin selector ni dependencia de grupos.
- [x] Implementar transporte HTTP con TLS, compresión, reintentos y llamadas concurrentes.
- [x] Mantener secretos y payloads fuera del repositorio y los logs.

## 2. Diagnóstico y observabilidad

- [x] Incorporar healthcheck y verificador seguro de endpoints GET en Back Office.
- [x] Medir login: total, perfil, precios, registros, tamaño, caché y resultado.
- [x] Consultar perfil y precios en paralelo.
- [x] Medir a todo cliente autenticado, sin filtrar por grupo.
- [x] Resincronizar al reanudar una sesión persistente, sin consultar SAP en cada página.
- [x] Alertas a TI con deduplicación y recuperación; el agendamiento queda a cargo del administrador.
- [x] Definir umbrales operativos y política de retención de métricas.
- [ ] Programar en hPanel el cron de monitoreo cada 5 minutos. *(Responsable: administrador Hostinger)*

## 3. Migración funcional REST

- [~] Perfil del cliente, crédito/deuda, direcciones y equipo de ventas. *(Consulta y validación contractual listas; falta uso completo en el portal)*
- [x] Actualizar atómicamente los precios del `cardCode` y su lista durante el login.
- [x] Evitar escrituras de precios sin cambios mediante checksum de fuente y mapeo.
- [~] Reportar y resolver con negocio los SKU REST ausentes del catálogo local.
- [x] Almacenar el catálogo maestro y actualizar múltiplos de SKU existentes.
- [x] Evitar escrituras del catálogo sin cambios y detectar deriva local.
- [x] No crear productos automáticamente desde la integración REST.
- [x] Historial paginado y estado de pedidos en “Su cuenta”.
- [x] Cartola de documentos, deuda y saldos pendientes.
- [x] Detalle documental, trazabilidad y respaldo digitalizado seguro.
- [x] Levantar el flujo heredado de órdenes/pagos y contrastarlo con el contrato REST.
- [x] Definir mapeo, estados, idempotencia, convivencia y corte de SOAP (`docs/order-payment-migration.md`).
- [~] Crear outbox transaccional común para solicitudes de pedido y pagos. *(Esquema, dominio y encolado listos; falta trabajador)*
- [x] Construir y validar el payload contractual de solicitud de pedido, sin precios ni impuestos.
- [~] Resolver `direccionDespacho` desde `address.correlativo_sap` y bloquear códigos ausentes/obsoletos. *(Resolvedor listo; falta conectarlo al evento de pedido)*
- [ ] Acordar el mapeo a `glosa` de comentarios, retiro por tercero y referencias adicionales.
- [ ] Confirmar el estado/evento local que habilita el envío; no asumir los IDs personalizados `1` y `3`.
- [ ] Implementar envío de pedidos con clave estable y seguimiento hasta `creadaSap`/`observada`.
- [ ] Persistir `DocEntry` y tipo documental desde la selección de deuda antes de iniciar el pago.
- [x] Construir y validar el payload de pago: documentos únicos, `DocEntry` y suma FC menos NC.
- [ ] Encolar pagos solo después de la confirmación del proveedor y validar la suma FC/ND menos NC.
- [ ] Implementar envío de pagos con clave estable, conciliación y visualización de observados.
- [ ] Probar pedidos y pagos en modo sombra antes de habilitar cualquier POST real.
- [ ] Mapear y retirar gradualmente las 13 operaciones SOAP reemplazadas.

## 4. Reglas del portal

- [x] Validar información crítica de SAP antes de habilitar la operación B2B.
- [x] Bloquear solamente al cliente integrado con `cardCode` tras reintentos fallidos; quien no tiene `cardCode` permanece observador.
- [x] Mantener catálogo visible para B2C y compra exclusiva para clientes con `cardCode`.
- [x] Revalidar `cardCode` local antes de modificar carrito, entrar al checkout o ejecutar un controlador de pago.
- [x] Rechazar perfil/precios incompletos o ajenos al `cardCode` sin escrituras parciales; pedidos/documentos fallan de forma aislada.

## 5. Calidad y despliegue

- [~] Pruebas unitarias de casos de uso, políticas y adaptadores.
- [ ] Pruebas de integración para cada endpoint y flujo completo.
- [ ] Pruebas de carga y concurrencia en login.
- [ ] Pruebas de regresión del portal y continuidad operativa.
- [ ] UAT, correcciones y aprobación del cliente.
- [ ] Paso controlado de `shadow` a `rest`.
- [ ] Despliegue productivo, validación posterior y plan de reversa.

## Última validación

- Ambiente: pruebas (`hoffensdesa.enexum.cl`).
- Resultado login: `b2b_ready`, sin fallos.
- Versión desplegada: `0.11.0`.
- Muestra integral: 884 ms total y 335 ms de escritura.
- Precios: 2.029 recibidos, 1.630 escritos, 399 sin SKU local y 0 duplicados.
- Precio específico validado para el cliente independientemente de su grupo activo.
- Checksum validado: segunda sincronización sin cambios, 0 escrituras en 25 ms.
- Catálogo: 3.224 recibidos, 1.894 vinculados y 1.330 SKU no existentes reportados.
- Múltiplos: 866 SKU existentes corregidos; deriva final 0 en productos y combinaciones.
- Segunda sincronización de catálogo: 0 escrituras en 1.273 ms, incluida la consulta REST.
- SKU `80430X` y `80430`–`80435`: ausentes del catálogo REST; no se alteraron ni crearon productos.
- Smoke test posterior al despliegue: portada HTTP 200.
- Monitoreo: API y base de datos verificadas; umbral 2.000 ms y retención 30 días configurables.
- Cron protegido: sin token HTTP 403; con token HTTP 200 y healthcheck en 381 ms.
- Deduplicación validada: dos fallos generan una incidencia con 2 ocurrencias; recuperación la cierra.
- Las alertas se encolan para no sumar el envío de correo al tiempo del login.
- El hosting bloquea la gestión de `crontab` por SSH; el agendamiento debe realizarse en hPanel.
- Pedidos REST validados: respuesta paginada y enlace al detalle `NV`.
- Documentos REST validados: 12 registros `FC` para el cliente de prueba.
- Detalle `NV/FC`: ítems, cantidades, trazabilidad y URL digitalizada; acceso protegido por `cardCode`.
- Nuevas vistas registradas en `displayCustomerAccount`; revisión visual autenticada queda para UAT.
- Flujo heredado revisado: marca órdenes como enviadas antes de confirmación SAP; no se reutilizará esa señal en REST.
- Contrato transaccional revisado: pedidos y pagos responden `202` y requieren outbox, idempotencia y conciliación.
- Brecha de direcciones: `direccionDespacho` debe usar `address.correlativo_sap`; hay registros locales sin código SAP.
- Brecha de pagos: el flujo antiguo conserva folio, pero REST exige persistir `DocEntry` y tipo documental.
- Outbox 0.8.0 instalada y vacía; todavía no existen hooks ni trabajadores que ejecuten POST.
- Validación posterior: sintaxis PHP correcta, módulo activo, tabla creada y portada HTTP 200.
- Versión 0.9.0 desplegada: validación crítica del perfil/precios y separación entre cliente integrado y observador.
- Reintentos ampliados a timeout HTTP 408, límite 429 y errores transitorios 500/502/503/504.
- Validadores de payload para pedidos y pagos preparados sin activar llamadas POST.
- Contrato real validado: 2.029 precios y dirección local contrastada correctamente con SAP.
- Caso de login ejecutado: `b2b_ready`, 1.630 precios escritos y sin llamadas POST.
- Portada responde HTTP 503 porque PrestaShop está en modo mantenimiento; no corresponde a un error del módulo.
- Ruta de pedidos sin sesión responde HTTP 302 hacia autenticación, según lo esperado.
- Versión 0.10.0 desplegada: reanudación tras 30 minutos de inactividad y actualización máxima cada 24 horas.
- La reanudación aplica backoff de 5 minutos ante fallo para evitar reintentos en cada navegación.
- Hook `actionFrontControllerInitAfter` registrado una sola vez; intervalos 1800/86400 segundos verificados.
- Política validada: regreso inactivo y antigüedad máxima sincronizan; navegación activa y backoff no sincronizan.
- Versión 0.11.0 desplegada: `cardCode` local como única identidad B2B y selector de grupos retirado.
- En modo `rest`, carrito, checkout y controladores frontales de pago revalidan el permiso antes de procesar la solicitud.
- El caché de login se descarta si está incompleto o corresponde a un `cardCode` distinto.
- Upgrade validado: versión de base de datos 0.11.0, configuración de grupos eliminada y hook frontal registrado una vez.
- Sintaxis PHP validada en la VPS; portada mantiene HTTP 503 por el modo mantenimiento ya identificado.
