# Migración de pedidos y pagos a REST

## Alcance revisado

Este diseño cruza el contrato REST con los flujos existentes de `ws_integracion`,
`integracion` y `cuentacorriente`. Esas integraciones se consideran fuentes de
levantamiento: no deben modificarse ni reutilizarse como capa de dominio del módulo.

Los planes de trabajo contemplan consumo REST, validaciones, errores, rendimiento,
concurrencia, pruebas y retiro gradual de SOAP.

## Hallazgos del flujo actual

- El SOAP de órdenes obtiene pedidos pendientes y cambia `estado_envio_ws` a `1`
  al entregar el listado, antes de confirmar su creación en SAP. Un fallo posterior
  puede dejar una orden sin reintento.
- La variante activa encontrada considera estados locales `1` y `3` e incluye datos
  que el nuevo contrato no recibe: medio de pago, autorización Webpay, destinatario
  alternativo, datos de facturación y precios de líneas.
- Las direcciones sincronizadas guardan el identificador SAP en
  `address.correlativo_sap`. Ese valor debe enviarse como `direccionDespacho`.
- El flujo de cuenta corriente conserva las facturas seleccionadas por folio. El
  nuevo `POST /pagos` exige `tipoDocumento` y `docEntry`, por lo que el `DocEntry`
  debe capturarse desde la cartola REST y persistirse antes de confirmar el pago.
- Webpay ya confirma o rechaza la transacción antes de registrar el pago local. El
  envío REST debe nacer solamente después de una confirmación exitosa del proveedor.

## Solicitud de pedido

Endpoint: `POST /solicitudes-pedido`.

Payload contractual:

```json
{
  "cardCode": "C...",
  "fechaEspera": "AAAA-MM-DD",
  "direccionDespacho": "CODIGO_CRD1",
  "glosa": "Referencia del portal",
  "lineas": [
    {"itemCode": "SKU", "cantidad": 1}
  ]
}
```

Reglas de implementación:

1. Crear una salida transaccional local (*outbox*) por pedido de PrestaShop.
2. Usar una clave estable, por ejemplo `ps-order-{idShop}-{idOrder}`; nunca generar
   una clave nueva al reintentar el mismo contenido.
3. Guardar el hash del payload. Una respuesta `409` por la misma clave con contenido
   distinto debe quedar como incidencia manual, no como reintento automático.
4. Validar `cardCode`, líneas, cantidades, fecha y `correlativo_sap` antes de encolar.
   Una dirección sin código SAP bloquea el envío y solicita corrección o resincronía.
5. No enviar precios ni impuestos: el contrato establece que SAP los determina.
6. Una respuesta `202` confirma recepción, no creación en SAP. Persistir
   `solicitudId`, `numAtCard`, `estadoUrl` y el estado informado.
7. Consultar el estado hasta uno terminal: `creadaSap` u `observada`. Los estados
   `pendiente`, `procesando` y `reintento` continúan en seguimiento.
8. Un timeout posterior al POST es ambiguo: repetir con la misma clave de idempotencia.
9. No usar `estado_envio_ws` como estado del nuevo flujo. Durante la convivencia,
   impedir que SOAP y REST creen el mismo documento mediante un corte por dominio.

### Campos que requieren acuerdo funcional

El contrato solo ofrece `glosa` para información adicional. Antes de activar el POST
se debe decidir qué ocurre con comentarios del cliente, retiro por tercero, nombre/RUT
del receptor y referencias de Webpay: formato, longitud máxima y cuáles son realmente
necesarios en SAP. No deben descartarse ni concatenarse silenciosamente.

También debe confirmarse cuál es el evento local que habilita el envío. Los estados
`1` y `3` observados son personalizados; no basta asumir su significado por el ID.

## Registro de pago

Endpoint: `POST /pagos`.

Reglas de implementación:

1. Al mostrar/seleccionar la deuda, conservar `tipoDocumento`, `docEntry`, saldo y
   `cardCode` devueltos por REST; no reconstruir el `DocEntry` desde el folio.
2. Revalidar antes del cobro que cada documento pertenece al cliente, aparece una
   sola vez y admite el monto aplicado.
3. Enviar solamente después de la confirmación servidor a servidor del proveedor.
4. Usar una clave estable derivada de la transacción confirmada y conservar el hash
   del payload. `numeroTransaccion` también debe ser único.
5. Validar `montoTransaccion = suma(FC/ND) - suma(NC)` según el contrato y moneda local.
6. Persistir la respuesta `202` y seguir `pendiente`, `procesando` y `reintento` hasta
   `creadaSap` u `observada`.
7. Mostrar en Back Office los pagos observados y si SAP aplicó el medio alternativo
   definido por contrato. La validación directa contra Transbank queda fuera de la
   API Hoffens hasta contar con su contrato y credenciales.

## Persistencia mínima del módulo

Una outbox común puede cubrir pedidos y pagos con estos datos:

- tipo de operación e identificador local;
- clave de idempotencia y hash del payload;
- payload JSON técnico protegido;
- identificador, URL y estado remoto;
- número de intentos, próxima ejecución y último error sanitizado;
- fechas de creación, envío, consulta y finalización.

Debe existir una restricción única por tipo e identificador local y otra por clave de
idempotencia. Los trabajadores tomarán lotes pequeños con bloqueo para evitar dobles
envíos concurrentes. El payload sí es necesario para recuperar la operación, pero no
debe aparecer en logs, métricas ni pantallas generales.

## Activación segura

1. Construir y probar validadores, payloads, outbox e idempotencia sin registrar hooks.
2. Activar captura local en modo sombra, sin ejecutar POST.
3. Comparar muestras contra el flujo antiguo y resolver campos pendientes.
4. Ejecutar casos controlados de pedido y pago con datos acordados.
5. Activar un solo dominio REST y deshabilitar su productor SOAP equivalente.
6. Verificar conciliación y plan de reversa antes de retirar código heredado.

El cron externo únicamente dispara el trabajador; la protección, los lotes, los
reintentos y la conciliación pertenecen al módulo. Su programación en Hostinger queda
a cargo del administrador del ambiente.

## Trabajador implementado

El trabajador común toma lotes pequeños mediante bloqueo transaccional recuperable,
valida el hash antes de usar el payload y conserva la misma clave de idempotencia en
cada reintento. Los errores HTTP permanentes (`400`, `401`, `403`, `404`, `409` y
`422`) pasan a revisión manual; los fallos transitorios usan espera exponencial con
un máximo de ocho intentos. Una aceptación queda programada para consulta hasta
`creadaSap` o `observada`.

Existen dos barreras independientes: el modo general debe ser `rest` y además debe
activarse explícitamente “Permitir POST de pedidos y pagos”. Mientras cualquiera de
ellas permanezca desactivada, el cron solo cuenta operaciones pendientes y no toma
lotes ni llama endpoints transaccionales. El Back Office muestra estados, referencias
y errores sanitizados, pero nunca payloads ni claves de idempotencia.
