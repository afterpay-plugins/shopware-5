{extends file="parent:frontend/checkout/cart_footer.tpl"}

{block name='frontend_checkout_cart_footer_field_labels'}
    <ul class="aggregation--list">

        {* Basket sum *}
        {block name='frontend_checkout_cart_footer_field_labels_sum'}
            <li class="list--entry block-group entry--sum">

                {block name='frontend_checkout_cart_footer_field_labels_sum_label'}
                    <div class="entry--label block">
                        {s name="CartFooterLabelSum"}{/s}
                    </div>
                {/block}

                {block name='frontend_checkout_cart_footer_field_labels_sum_value'}
                    <div class="entry--value block">
                        {$sBasket.Amount|currency}{s name="Star" namespace="frontend/listing/box_article"}{/s}
                    </div>
                {/block}
            </li>
        {/block}

        {* Shipping costs *}
        {block name='frontend_checkout_cart_footer_field_labels_shipping'}
            <li class="list--entry block-group entry--shipping">

                {block name='frontend_checkout_cart_footer_field_labels_shipping_label'}
                    <div class="entry--label block">
                        {s name="CartFooterLabelShipping"}{/s}
                    </div>
                {/block}

                {block name='frontend_checkout_cart_footer_field_labels_shipping_value'}
                    <div class="entry--value block">
                        {$sShippingcosts|currency}{s name="Star" namespace="frontend/listing/box_article"}{/s}
                    </div>
                {/block}
            </li>
        {/block}

        {* Total sum *}
        {block name='frontend_checkout_cart_footer_field_labels_total'}
            <li class="list--entry block-group entry--total">

                {block name='frontend_checkout_cart_footer_field_labels_total_label'}
                    <div class="entry--label block">
                        {s name="CartFooterLabelTotal"}{/s}
                    </div>
                {/block}

                {block name='frontend_checkout_cart_footer_field_labels_total_value'}
                    <div class="entry--value block is--no-star">
                        {if $sAmountWithTax && $sUserData.additional.charge_vat}{$sAmountWithTax|currency}{else}{$sAmount|currency}{/if}
                    </div>
                {/block}
            </li>
        {/block}

        {* Total net *}
        {block name='frontend_checkout_cart_footer_field_labels_totalnet'}
            {if $sUserData.additional.charge_vat}
                <li class="list--entry block-group entry--totalnet">

                    {block name='frontend_checkout_cart_footer_field_labels_totalnet_label'}
                        <div class="entry--label block">
                            {s name="CartFooterTotalNet"}{/s}
                        </div>
                    {/block}

                    {block name='frontend_checkout_cart_footer_field_labels_totalnet_value'}
                        <div class="entry--value block is--no-star">
                            {$sAmountNet|currency}
                        </div>
                    {/block}
                </li>
            {/if}
        {/block}

        {* Taxes *}
        {block name='frontend_checkout_cart_footer_field_labels_taxes'}
            {if $sUserData.additional.charge_vat}
                {foreach $sBasket.sTaxRates as $rate => $value}
                    {block name='frontend_checkout_cart_footer_field_labels_taxes_entry'}
                        <li class="list--entry block-group entry--taxes">

                            {block name='frontend_checkout_cart_footer_field_labels_taxes_label'}
                                <div class="entry--label block">
                                    {s name="CartFooterTotalTax"}{/s}
                                </div>
                            {/block}

                            {block name='frontend_checkout_cart_footer_field_labels_taxes_value'}
                                <div class="entry--value block is--no-star">
                                    {$value|currency}
                                </div>
                            {/block}
                        </li>
                    {/block}
                {/foreach}
            {/if}
        {/block}
        {block name='frontend_checkout_cart_footer_field_labels_interest_rate_total'}
        {foreach from=$coloAfterpayInstallments item=coloAfterpayInstallment name=installments}
        {if (!$coloAfterpaySelectedInstallment && $smarty.foreach.installments.first) || ($coloAfterpaySelectedInstallment == $coloAfterpayInstallment['installmentProfileNumber'])}
            {$coloSelectedInstallment = $coloAfterpayInstallment}
        {/if}
        {/foreach}
        {if $coloSelectedInstallment['totalAmount'] > 0}

                {if $sUserData.additional.payment.name === "colo_afterpay_installment"}

            <li class="list--entry block-group entry--totalInterestRate">

                {block name='frontend_checkout_cart_footer_field_labels_interest_rate_tota_label'}
                    <div class="entry--label block">
                            {s name="InstallmentTotalInterestAmount" namespace="frontend/colo_afterpay/index"}{/s}
                    </div>
                {/block}
                {block name='frontend_checkout_cart_footer_field_labels_interest_rate_total_value'}
                    <div class="entry--value block is--no-star">
                            {$coloSelectedInstallment['totalInterestAmount']|currency}
                    </div>
                {/block}
            </li>
                {/if}

        {/if}

        {/block}


    </ul>
{/block}

