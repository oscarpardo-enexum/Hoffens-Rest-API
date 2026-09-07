{extends file='page.tpl'}

{block name='page_title'}{l s='Detalle y trazabilidad del documento' mod='hoffensb2b'}{/block}

{block name='page_content'}
  {if $hoffens_error}<div class="alert alert-danger">{$hoffens_error|escape:'htmlall':'UTF-8'}</div>{/if}
  {if $hoffens_document}
    <div class="card card-block">
      <h3>{$hoffens_document.tipoDocumento|escape:'htmlall':'UTF-8'} #{$hoffens_document.docNum|escape:'htmlall':'UTF-8'}</h3>
      <p>{l s='Estado' mod='hoffensb2b'}: <strong>{$hoffens_document.estado|escape:'htmlall':'UTF-8'}</strong></p>
      <p>{l s='Fecha' mod='hoffensb2b'}: {$hoffens_document.fechaDocumento|escape:'htmlall':'UTF-8'} · {l s='Vencimiento' mod='hoffensb2b'}: {$hoffens_document.fechaVencimiento|escape:'htmlall':'UTF-8'}</p>
      <p>{l s='Total' mod='hoffensb2b'}: <strong>{$hoffens_document.totalFormatted|escape:'htmlall':'UTF-8'}</strong></p>
      {if $hoffens_document.digitalizacion.disponible && $hoffens_document.digitalizacion.url}
        <p><a class="btn btn-primary" rel="noopener noreferrer" target="_blank" href="{$hoffens_document.digitalizacion.url|escape:'htmlall':'UTF-8'}">{l s='Ver documento digitalizado' mod='hoffensb2b'}</a></p>
      {/if}
    </div>

    <h3>{l s='Línea de tiempo' mod='hoffensb2b'}</h3>
    {if $hoffens_document.lineaTiempo}
      <ol>{foreach from=$hoffens_document.lineaTiempo item=event}<li><strong>{$event.estado|escape:'htmlall':'UTF-8'}</strong> · {$event.fecha|escape:'htmlall':'UTF-8'}{if $event.documento} · #{$event.documento|escape:'htmlall':'UTF-8'}{/if}</li>{/foreach}</ol>
    {else}<p>{l s='No existe trazabilidad disponible.' mod='hoffensb2b'}</p>{/if}

    <h3>{l s='Ítems' mod='hoffensb2b'}</h3>
    <div class="table-responsive"><table class="table table-striped">
      <thead><tr><th>{l s='SKU' mod='hoffensb2b'}</th><th>{l s='Artículo' mod='hoffensb2b'}</th><th>{l s='Cantidad' mod='hoffensb2b'}</th><th>{l s='Despachada' mod='hoffensb2b'}</th><th>{l s='Pendiente' mod='hoffensb2b'}</th><th>{l s='Precio' mod='hoffensb2b'}</th><th>{l s='Total' mod='hoffensb2b'}</th></tr></thead>
      <tbody>{foreach from=$hoffens_document.items item=item}<tr>
        <td>{$item.codigoArticulo|escape:'htmlall':'UTF-8'}</td><td>{$item.nombreArticulo|escape:'htmlall':'UTF-8'}</td>
        <td>{$item.cantidad|escape:'htmlall':'UTF-8'}</td><td>{$item.cantidadDespachada|escape:'htmlall':'UTF-8'}</td><td>{$item.cantidadPendiente|escape:'htmlall':'UTF-8'}</td>
        <td>{$item.unitPriceFormatted|escape:'htmlall':'UTF-8'}</td><td>{$item.lineTotalFormatted|escape:'htmlall':'UTF-8'}</td>
      </tr>{/foreach}</tbody>
    </table></div>
  {/if}
{/block}
