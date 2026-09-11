# Hoffens B2B Integration

Base local para la nueva integración REST entre el portal B2B Hoffens,
PrestaShop 1.7.8.11 y SAP.

El código productivo vivirá en un módulo nuevo. No depende de
`ws_integracion` ni modifica la integración SOAP existente.

## Decisiones iniciales

- Un módulo desplegable: `hoffensb2b`.
- Núcleo desacoplado mediante interfaces y servicios PSR-4.
- Compatibilidad con PrestaShop 1.7.8.11 y PHP 7.1+.
- B2B puede comprar; B2C solo puede consultar el catálogo.
- La identidad B2B y el permiso de compra se determinan por un `cardCode` local válido.
- REST puede permanecer desactivado hasta recibir el token `b2b_...`.
- Credenciales fuera del repositorio y nunca incorporadas a URLs o logs.

La arquitectura y el proceso de evolución están documentados en
[`docs/architecture.md`](docs/architecture.md).

## Estado del scaffold

- Configuración de URL, Bearer token, timeouts y reintentos.
- Botón de healthcheck desde el Back Office.
- Consola segura para verificar cada endpoint GET con tiempos y conteos.
- Modo desactivado, sombra y REST.
- Hook de autenticación inactivo mientras el modo sea `disabled`.
- Perfil y precios concurrentes durante el login autenticado.
- Resincronización al regresar con una sesión persistente, sin llamar a SAP en cada página.
- Validación contractual de los datos críticos antes de operar como B2B.
- El cliente sin `cardCode` se mide como observador; un `cardCode` válido activa la integración SAP.
- Reemplazo atómico de precios específicos por cliente y lista, con rollback.
- Los clientes sin `cardCode` no generan llamadas a SAP.
- Caché comprimida opcional; TTL predeterminado en cero (tiempo real).
- `cardCode` leído desde `{prefix}customer.card_code`, igual que el portal actual.
- Payloads validados de pedidos y pagos preparados sin activar todavía los POST.

No se almacena ningún token real en este repositorio. Debe configurarse mediante
el formulario del módulo, `config/local.php` o `HOFFENS_B2B_API_TOKEN`.

La consola de diagnóstico no permite ejecutar `POST /solicitudes-pedido` ni
`POST /pagos`: esas rutas generan operaciones y requieren escenarios controlados
de integración, no un botón genérico de Back Office.
