{extends file="parent:frontend/checkout/confirm.tpl"}

{block name="frontend_index_header_javascript_tracking"}
    {$smarty.block.parent}
    {include file='frontend/colo_afterpay/checkout/tracking.tpl'}
{/block}

{block name="frontend_checkout_confirm_left_payment_method"}
    {$smarty.block.parent}
    {if ($sUserData.additional.payment.name === "colo_afterpay_dd" || $sUserData.additional.payment.name === "colo_afterpay_installment") && ($sUserData.additional.payment.data.sSepaIban)}
        <div class="payment--additional-info">
            {if $sUserData.additional.payment.data.sSepaIban}
                {if strlen($sUserData.additional.payment.data.sSepaIban) > 10}
                    {$ibanMaskStartIndex = strlen($sUserData.additional.payment.data.sSepaIban) - 10}
                    {$maskedIban = substr_replace($sUserData.additional.payment.data.sSepaIban, "********", $ibanMaskStartIndex, 8)}
                {else}
                    {$maskedIban = $sUserData.additional.payment.data.sSepaIban}
                {/if}
                <strong>{s name='PaymentSepaLabelIban' namespace='frontend/plugins/payment/sepa'}{/s}:</strong>
                {$maskedIban}
                <br/>
            {/if}
        </div>
        <br/>
    {/if}
{/block}

{block name='frontend_checkout_confirm_agb'}
    {$smarty.block.parent}
    {if $coloAfterpayMerchantID}
        <li class="block-group row--tos">
            {block name='frontend_checkout_confirm_afterpay_merchant_checkbox'}
                <span class="block column--checkbox">
                    <input type="checkbox" required="required" aria-required="true" id="coloAfterpayMerchant"
                           name="coloAfterpayMerchantCheck"{if $coloAfterpayMerchantChecked} checked="checked"{/if} />
                </span>
            {/block}

            {* AGB label *}
            {block name='frontend_checkout_confirm_afterpay_merchant_label'}
                <span class="block column--label">
                    <label for="coloAfterpayMerchant"{if $coloAfterpayMerchantError} class="has--error"{/if}>
                        {s name="ConfirmMerchantCheck" namespace="frontend/colo_afterpay/index"}I have read the
                            <span data-modalbox="true" data-targetSelector="a" data-mode="iframe" data-height="500" data-width="750"><a
                                        href="https://documents.myafterpay.com/consumer-terms-conditions/{$coloAfterpayLanguageCode}/{$coloAfterpayMerchantID}/{$coloAfterpayMerchantPaymentMethod}"><span
                                            style="text-decoration:underline;">Terms & Conditions</span></a></span>
                             and the
                            <span data-modalbox="true" data-targetSelector="a" data-mode="iframe" data-height="500" data-width="750"><a
                                        href="https://documents.myafterpay.com/privacy-statement/{$coloAfterpayLanguageCode}/{if $coloAfterpayMerchantID}{$coloAfterpayMerchantID}{else}default{/if}"><span
                                            style="text-decoration:underline;">Privacy Policy</span></a></span>
                            of AfterPay and do accept them.{/s}
                    </label>
                </span>
            {/block}
        </li>
    {/if}
{/block}