{extends file="parent:frontend/checkout/finish.tpl"}

{block name="frontend_index_after_body"}
    {$smarty.block.parent}
    {include file='frontend/colo_afterpay/checkout/tracking.tpl'}
{/block}