{extends file='page.tpl'}

{block name='page_title'}{l s='Estado de entrega por pedidos' mod='hoffensb2b'}{/block}

{block name='page_content'}
  {if $hoffens_error}<div class="alert alert-danger">{$hoffens_error|escape:'htmlall':'UTF-8'}</div>{/if}
  {if !$hoffens_error && !$hoffens_orders}<div class="alert alert-info">{l s='No existen pedidos para mostrar.' mod='hoffensb2b'}</div>{/if}
  {if $hoffens_orders}
    <div class="table-responsive"><table class="table table-striped">
      <thead><tr><th>{l s='Pedido' mod='hoffensb2b'}</th><th>{l s='Orden de compra' mod='hoffensb2b'}</th><th>{l s='Fecha' mod='hoffensb2b'}</th><th>{l s='Entrega' mod='hoffensb2b'}</th><th>{l s='Artículos' mod='hoffensb2b'}</th><th>{l s='Pendientes' mod='hoffensb2b'}</th><th>{l s='Total' mod='hoffensb2b'}</th><th></th></tr></thead>
      <tbody>{foreach from=$hoffens_orders item=order}<tr>
        <td>{$order.numeroPedido|escape:'htmlall':'UTF-8'}</td><td>{$order.ordenCompra|escape:'htmlall':'UTF-8'}</td>
        <td>{$order.fechaPedido|escape:'htmlall':'UTF-8'}</td><td>{$order.fechaEntrega|escape:'htmlall':'UTF-8'}</td>
        <td>{$order.cantidadArticulos|escape:'htmlall':'UTF-8'}</td><td>{$order.cantidadArticulosPendientes|escape:'htmlall':'UTF-8'}</td>
        <td>{$order.totalFormatted|escape:'htmlall':'UTF-8'}</td><td><a class="btn btn-primary" href="{$order.detailUrl|escape:'htmlall':'UTF-8'}">{l s='Ver detalle' mod='hoffensb2b'}</a></td>
      </tr>{/foreach}</tbody>
    </table></div>
    <nav class="clearfix">
      {if $hoffens_pagination.page > 1}<a class="btn btn-secondary pull-left" href="{$hoffens_prev_url|escape:'htmlall':'UTF-8'}">{l s='Anterior' mod='hoffensb2b'}</a>{/if}
      {if $hoffens_pagination.page < $hoffens_pagination.totalPages}<a class="btn btn-secondary pull-right" href="{$hoffens_next_url|escape:'htmlall':'UTF-8'}">{l s='Siguiente' mod='hoffensb2b'}</a>{/if}
    </nav>
  {/if}
{/block}
