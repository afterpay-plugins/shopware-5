{extends file='parent:frontend/register/payment_fieldset.tpl'}

{block name="frontend_register_payment_fieldset_input_radio"}
    <input type="radio"
           name="register[payment]"
            {if ($ColoGeneralRequirementsNotMet && in_array($payment_mean.name, $ColoAfterpayPaymentMethods)) || (!empty($sFormData.coloAfterpayPaymentDetails[$payment_mean.name]) && $sFormData.coloAfterpayPaymentDetails[$payment_mean.name]['disabled'])} disabled="disabled"{/if}
           class="radio auto_submit"
            {if $payment_mean.name === "colo_afterpay_installment"}data-installments-url='{url controller=ColoAfterpayCheckout action=getInstallments isXHR=1}'{/if}
           value="{$payment_mean.id}"
           id="payment_mean{$payment_mean.id}"
            {if $payment_mean.id eq $sFormData.payment or (!$sFormData && !$smarty.foreach.register_payment_mean.index)} checked="checked"{/if} />
{/block}

{block name="frontend_register_payment_fieldset_input_label"}
    {$smarty.block.parent}
    {block name='frontend_register_payment_fieldset_input_label_afterpay'}
        {if in_array($payment_mean.name, $ColoAfterpayPaymentMethods)}
            {block name='frontend_register_payment_fieldset_input_label_afterpay_logo'}
                <div class='payment--logo'>
                    <img src='{link file="frontend/_public/src/img/AfterPay_logo.svg"}' alt='AfterPay Logo'/>
                </div>
            {/block}
        {/if}
    {/block}
{/block}