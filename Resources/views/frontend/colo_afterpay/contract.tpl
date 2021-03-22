{extends file="parent:frontend/index/index.tpl"}

{block name="frontend_index_body_classes" append} is--minimal-header{/block}

{* Back to the shop button *}
{block name='frontend_index_logo_trusted_shops' append}
    {if $theme.checkoutHeader}
        <a href="{url controller='index'}"
           class="btn is--small btn--back-top-shop is--icon-left"
           title="{"{s name='FinishButtonBackToShop' namespace='frontend/checkout/finish'}{/s}"|escape}"
           xmlns="http://www.w3.org/1999/html">
            <i class="icon--arrow-left"></i>
            {s name="FinishButtonBackToShop" namespace="frontend/checkout/finish"}{/s}
        </a>
    {/if}
{/block}

{* Hide sidebar left *}
{block name='frontend_index_content_left'}
    {if !$theme.checkoutHeader}
        {$smarty.block.parent}
    {/if}
{/block}

{* Hide breadcrumb *}
{block name='frontend_index_breadcrumb'}{/block}

{* Hide shop navigation *}
{block name='frontend_index_shop_navigation'}
    {if !$theme.checkoutHeader}
        {$smarty.block.parent}
    {/if}
{/block}

{* Step box *}
{block name='frontend_index_navigation_categories_top'}
    {if !$theme.checkoutHeader}
        {$smarty.block.parent}
    {/if}
{/block}

{* Hide top bar *}
{block name='frontend_index_top_bar_container'}
    {if !$theme.checkoutHeader}
        {$smarty.block.parent}
    {/if}
{/block}

{* Footer *}
{block name="frontend_index_footer"}
    {if !$theme.checkoutFooter}
        {$smarty.block.parent}
    {else}
        {block name='frontend_index_checkout_confirm_footer'}
            {include file="frontend/index/footer_minimal.tpl"}
        {/block}
    {/if}
{/block}

{block name='frontend_index_content'}
    <div class="content-main--inner-bottomspacer"></div>
    <form action="{url controller=ColoAfterpay action=authorize}" method="post">
        <div class="main--actions">
            <button type="submit" class="btn btn--checkout-proceed is--primary right is--icon-right is--large">{s name="AllowDebitingBtnText" namespace="frontend/colo_afterpay/index"}{/s} <i
                        class="icon--arrow-right"></i></button>
        </div>
    </form>
    <div class="afterpay_contractdetails">
        {$contractDetails.contract}
    </div>
    <form action="{url controller=ColoAfterpay action=authorize}" method="post">
        <div class="main--actions">
            <button type="submit" class="btn btn--checkout-proceed is--primary right is--icon-right is--large">{s name="AllowDebitingBtnText" namespace="frontend/colo_afterpay/index"}{/s} <i
                        class="icon--arrow-right"></i></button>
        </div>
    </form>
    <div class="content-main--inner-bottomspacer"></div>
{/block}