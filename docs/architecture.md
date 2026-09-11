# Arquitectura

## Contexto

El portal utiliza PrestaShop 1.7.8.11. La integración SOAP actual está
distribuida entre módulos antiguos y `ws_integracion`. La nueva integración
se implementará de forma paralela y podrá activarse gradualmente.

PrestaShop 1.7 permite registrar servicios del módulo en el contenedor Symfony.
Se utilizará esa capacidad para composición, controladores de Back Office y
comandos, manteniendo el dominio independiente del framework. Los hooks y las
zonas legacy consumirán los mismos servicios mediante un adaptador del módulo.

## Límites

```text
Tema y módulos B2B existentes
           |
           v
Adaptadores PrestaShop (hooks, Customer, Group, Configuration)
           |
           v
Aplicación (casos de uso y políticas de acceso)
           |
           v
Dominio (cliente, precio, catálogo, pedido, documento, pago)
           |
           v
Infraestructura (HTTP REST, persistencia, logs y alertas)
           |
           v
https://apib2b.hoffens.com/api/v1/
```

Las clases de dominio y aplicación no deben usar `Context`, `Db`, `Tools`,
`Customer` ni otras clases globales de PrestaShop. Esa dependencia queda en
`src/PrestaShop` e `Infrastructure`.

## Política comercial

- Invitado: catálogo visible, compra no autorizada.
- Cliente B2C autenticado: catálogo visible, compra no autorizada.
- Cliente B2B autenticado y activo: catálogo y compra autorizados.
- La pertenencia B2B se resuelve por grupo, no por correo, dominio o nombre.
- La autorización debe comprobarse nuevamente en servidor al modificar el
  carrito y antes de crear una orden. Ocultar botones no es seguridad.

Todos los clientes autenticados pueden generar telemetría de login. Un `cardCode`
válido identifica al cliente que participa en la integración SAP, aunque todavía
no se hayan configurado grupos B2B; para él se validan y actualizan los precios.
Los grupos quedan reservados para autorizar la compra. Un cliente sin `cardCode`
permanece como observador, salvo que pertenezca explícitamente a un grupo B2B, en
cuyo caso la falta del identificador es un error crítico.

El scaffold todavía no registra hooks que cambien el comportamiento del sitio.
Eso se hará después de mapear el flujo B2B actual, para no duplicar ni romper
las restricciones existentes.

## Configuración y secretos

Orden de resolución propuesto:

1. Variable de entorno.
2. Archivo local `modules/hoffensb2b/config/local.php` no versionado.
3. `Configuration` de PrestaShop para valores no sensibles.

El token REST no debe guardarse en el repositorio. En producción se recomienda
un archivo PHP fuera del document root o variables suministradas por el hosting.

## Activación progresiva

1. `disabled`: no se llama a REST.
2. `shadow`: REST se consulta y compara, pero SOAP sigue gobernando.
3. `rest`: REST gobierna el dominio habilitado.

Cada dominio debe tener su propia bandera para permitir migrar catálogo,
clientes, precios, documentos y operaciones transaccionales por separado.

## Precios

El contrato actual devuelve el conjunto completo de precios positivos del
cliente y no define versión, checksum ni delta. En las mediciones iniciales, un
cliente técnico recibió 1.749 precios en unos 0,84 segundos. Perfil y precios se
solicitan concurrentemente durante el login para evitar sumar sus latencias.

El TTL predeterminado es cero: cada login B2B obtiene información en tiempo real.
La caché local solo puede habilitarse cuando negocio apruebe explícitamente una
ventana de vigencia.

Antes de escribir precios para un B2B se valida que el perfil corresponda al
`cardCode` solicitado y contenga razón social, crédito, deuda, cobranza,
direcciones y cuenta corriente con los tipos definidos en el contrato. Una
respuesta incompleta se trata como indisponibilidad y no deja una actualización
parcial.

La sincronización de login reemplaza los `specific_price` del cliente dentro de
una transacción InnoDB. Se utiliza `id_customer` con `id_group = 0`, porque el
endpoint ya entrega el precio personalizado por `cardCode`; así la regla sigue
aplicando aunque el cliente pertenezca a varios grupos. Un bloqueo por cliente
evita escrituras concurrentes. Los SKU sin correspondencia local se omiten y
contabilizan; deben resolverse mediante la sincronización del catálogo.

Cada respuesta genera un checksum del payload y otro del mapeo SKU-producto. Si
ambos coinciden con el último estado y la cantidad persistida es correcta, el
login evita el reemplazo masivo. La API se sigue consultando en tiempo real; el
checksum funciona como control de escritura, no como caché de precios obsoletos.

### Medición inicial (ambiente de pruebas)

| Recurso | Resultado observado |
|---|---:|
| Perfil | 1,50 s; 1.724 bytes descomprimidos |
| Precios | 0,84 s; 1.749 registros; 94 KB descomprimidos |
| Perfil + precios concurrentes | 0,86 s en smoke test con conexión caliente |
| Catálogo | 1,49 s; 3.224 registros; 1,56 MB descomprimidos |

La sincronización del catálogo es una operación explícita de Back Office, separada del login. Guarda una copia técnica del recurso y actualiza `minimal_quantity` solamente cuando existe una coincidencia exacta con `product.reference` o `product_attribute.reference`. Las referencias ausentes se reportan y nunca originan productos automáticamente.

El monitoreo periódico utiliza una URL cron protegida por un token independiente del Bearer de SAP. Un fallo de login solo encola la incidencia en base de datos; el cron ejecuta el healthcheck, deduplica notificaciones, envía alertas o recuperaciones y elimina métricas fuera de retención. Una respuesta saludable que supera el umbral se informa como latencia alta, pero no se considera por sí sola una caída confirmada.

Las vistas de pedidos, cartola y detalle son controladores autenticados del módulo. Consultan REST bajo demanda y no duplican documentos financieros en PrestaShop. Antes de mostrar un detalle, el módulo contrasta su `cardCode` con el cliente de la sesión; las URLs digitalizadas se publican únicamente cuando usan HTTPS.

Estas cifras son muestras, no un SLA. El catálogo se procesa fuera del login.
El login no llama previamente a `/health`: hacerlo agregaría otra ida de red sin
garantizar que los recursos funcionales respondan correctamente.

En la muestra de precios, `moneda` llegó vacía en 1.666 registros y como `$` en
83. Dado que el contrato limita la primera versión a moneda local, el adaptador
normaliza ambos valores a `CLP`. Esta decisión debe incluirse en las pruebas de
aceptación del contrato.

## Operaciones transaccionales

`solicitudes-pedido` y `pagos` responden 202. El módulo debe persistir la
solicitud y su `Idempotency-Key`, consultar el estado posteriormente y nunca
repetir una operación con una clave distinta por un timeout ambiguo.

La versión 0.8.0 incorpora una outbox común con referencia local y clave de
idempotencia únicas, hash canónico del payload, planificación de reintentos y
campos de conciliación remota. Aún no está conectada a hooks ni trabajadores:
su despliegue no crea pedidos ni pagos. El mapeo y el plan de activación están
detallados en `docs/order-payment-migration.md`.

## Diagnóstico en Back Office

El módulo permite ejecutar una lista cerrada de endpoints GET y presenta código
funcional, duración y resumen de conteos. No muestra ni persiste el payload. Los
parámetros `cardCode`, tipo documental y `DocEntry` se ingresan según el recurso.

Los endpoints POST se excluyen deliberadamente: una prueba de pedido o pago debe
ejecutarse como caso de integración controlado, con idempotencia, datos acordados
y confirmación explícita.

## Observabilidad del login

El Back Office muestra las últimas 50 ejecuciones B2B y un resumen de 24 horas.
Por cada ejecución se almacenan solamente:

- ID y nombre actual del cliente (el nombre se obtiene con un JOIN, no se copia).
- Modo y resultado de la integración.
- Tiempo total, lote paralelo, perfil y precios.
- Cantidad y tamaño descomprimido de precios.
- Uso de caché.

No se almacenan respuestas JSON, valores de precios, RUT, correo ni token. Las
métricas se conservan 30 días y su limpieza se amortiza para evitar ejecutar un
`DELETE` en cada login. Un fallo de telemetría nunca bloquea al cliente.
