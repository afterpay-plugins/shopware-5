{namespace name='frontend/plugins/payment/sepa'}

<div class="payment--form-group">
    {$maskedIban = ""}
    {if $form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sSepaIban}
        {if strlen($form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sSepaIban) > 10}
            {$ibanMaskStartIndex = strlen($form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sSepaIban) - 10}
            {$maskedIban = substr_replace($form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sSepaIban, "********", $ibanMaskStartIndex, 8)|escape}
        {else}
            {$maskedIban = $form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sSepaIban}
        {/if}
        <input type="hidden" name="colo_afterpay_payment[{$payment_mean.name}][sMaskedIban]" value="1"/>
    {/if}
    <input name="colo_afterpay_payment[{$payment_mean.name}][sSepaIban]"
           type="text"
           id="iban-installment"
           {if $payment_mean.id == $form_data.payment && !$maskedIban}required="required" aria-required="true"{/if}
           placeholder="{if $maskedIban}{$maskedIban}{else}{s name='PaymentSepaLabelIban'}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}{/if}"
           value=""
           class="payment--field is--required{if $maskedIban} masked{/if}{if $error_flags.sSepaIban} has--error{/if}"/>

    <input name="colo_afterpay_payment[{$payment_mean.name}][sSepaBic]"
           type="text"
           id="bic-installment"
           {if $payment_mean.id == $form_data.payment}required="required" aria-required="true"{/if}
           placeholder="{s name='PaymentSepaLabelBic'}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}"
           value="{$form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sSepaBic|escape}"
           class="payment--field is--required{if $error_flags.sSepaBic} has--error{/if}"/>

    {if $ColoAfterpayConfigs['colo_afterpay_birthday_check']}
        {include file='frontend/plugins/payment/afterpay_birthday_fieldset.tpl'}
    {/if}

    {block name='frontend_checkout_payment_required'}
        {* Required fields hint *}
        <div class="register--required-info">
            {s name='RegisterPersonalRequiredText' namespace='frontend/register/personal_fieldset'}{/s}
        </div>
    {/block}
</div>