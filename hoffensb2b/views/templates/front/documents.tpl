{extends file='page.tpl'}

{block name='page_title'}{l s='Cartola de deuda' mod='hoffensb2b'}{/block}

{block name='page_content'}
  {if $hoffens_error}<div class="alert alert-danger">{$hoffens_error|escape:'htmlall':'UTF-8'}</div>{/if}
  {if !$hoffens_error && !$hoffens_documents}<div class="alert alert-info">{l s='No existen documentos pendientes.' mod='hoffensb2b'}</div>{/if}
  {if $hoffens_documents}<div class="table-responsive"><table class="table table-striped">
    <thead><tr><th>{l s='Tipo' mod='hoffensb2b'}</th><th>{l s='Folio' mod='hoffensb2b'}</th><th>{l s='Fecha' mod='hoffensb2b'}</th><th>{l s='Vencimiento' mod='hoffensb2b'}</th><th>{l s='Total' mod='hoffensb2b'}</th><th>{l s='Saldo' mod='hoffensb2b'}</th><th></th></tr></thead>
    <tbody>{foreach from=$hoffens_documents item=document}<tr>
      <td>{$document.tipoDocumento|escape:'htmlall':'UTF-8'}</td><td>{$document.folio|escape:'htmlall':'UTF-8'}</td>
      <td>{$document.fecha|escape:'htmlall':'UTF-8'}</td><td>{$document.fechaVencimiento|escape:'htmlall':'UTF-8'}</td>
      <td>{$document.totalFormatted|escape:'htmlall':'UTF-8'}</td><td><strong>{$document.balanceFormatted|escape:'htmlall':'UTF-8'}</strong></td>
      <td><a class="btn btn-primary" href="{$document.detailUrl|escape:'htmlall':'UTF-8'}">{l s='Ver detalle' mod='hoffensb2b'}</a></td>
    </tr>{/foreach}</tbody>
  </table></div>{/if}
{/block}
