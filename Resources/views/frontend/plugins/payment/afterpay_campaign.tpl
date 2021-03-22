{namespace name='frontend/colo_afterpay/index'}

<div class="payment--form-group">
    {if $ColoAfterpayConfigs['colo_afterpay_birthday_check']}
        {include file='frontend/plugins/payment/afterpay_birthday_fieldset.tpl'}
        {block name='frontend_checkout_payment_required'}
            {* Required fields hint *}
            <div class="register--required-info">
                {s name='RegisterPersonalRequiredText' namespace='frontend/register/personal_fieldset'}{/s}
            </div>
        {/block}
    {/if}
</div>