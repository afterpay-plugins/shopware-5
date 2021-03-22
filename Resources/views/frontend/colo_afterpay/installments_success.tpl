{block name="colo_afterpay_installment_options"}
    <div class='colo-afterpay--installments'>
        {foreach from=$coloAfterpayInstallments item=coloAfterpayInstallment name=installments}
            {if (!$coloAfterpaySelectedInstallment && $smarty.foreach.installments.first) || ($coloAfterpaySelectedInstallment == $coloAfterpayInstallment['installmentProfileNumber'])}
                {$coloSelectedInstallment = $coloAfterpayInstallment}
            {/if}
            <div class='installment--plan{if $smarty.foreach.installments.first} first{/if}{if (!$coloAfterpaySelectedInstallment && $smarty.foreach.installments.first) || ($coloAfterpaySelectedInstallment == $coloAfterpayInstallment['installmentProfileNumber'])} active{/if}'
                 data-interest-rate='{$coloAfterpayInstallment['interestRate']}%'
                 data-effective-rate='{$coloAfterpayInstallment['effectiveInterestRate']}%'
                 data-installments-amount='{$coloAfterpayInstallment['numberOfInstallments']}'
                 data-total-amount='{$coloAfterpayInstallment['totalAmount']|currency}'>
                <input class="is--hidden" type="radio" id="installment-plan-{$coloAfterpayInstallment['installmentProfileNumber']}" name="colo_afterpay_payment[colo_afterpay_installment][plan]"
                       value="{$coloAfterpayInstallment['installmentProfileNumber']}"{if (!$coloAfterpaySelectedInstallment && $smarty.foreach.installments.first) || ($coloAfterpaySelectedInstallment == $coloAfterpayInstallment['installmentProfileNumber'])} checked='checked'{/if} />
                <label for="installment-plan-{$coloAfterpayInstallment['installmentProfileNumber']}">
                    <div class="installment--monthly-fee">
                        {$coloAfterpayInstallment['installmentAmount']|currency} / {s name="colo_afterpay_installment_month" namespace="frontend/colo_afterpay/index"}Month{/s}
                    </div>
                    <div class="installment--number">
                        {s name="colo_afterpay_installment_in" namespace="frontend/colo_afterpay/index"}in{/s} {$coloAfterpayInstallment['numberOfInstallments']} {s name="colo_afterpay_installment_installments" namespace="frontend/colo_afterpay/index"}installments{/s}
                    </div>
                    <div class="installment--selected">
                        <i class="icon--check"></i>
                    </div>
                </label>
            </div>
        {/foreach}

        {block name="colo_afterpay_installment_info"}
            <div class="installment--information">
                <ul>
                    <li>{s name="colo_afterpay_installment_uspline" namespace="frontend/colo_afterpay/index"}Same installments every month - no surprises{/s}</li>
                    <li>{s name="colo_afterpay_installment_interest_rate" namespace="frontend/colo_afterpay/index"}Fixed interest rate of
                            <span class="interest--rate">{$coloSelectedInstallment['interestRate']}%</span>
                            p.a.{/s}</li>
                    <li>{s name="colo_afterpay_installment_effective_interest_rate" namespace="frontend/colo_afterpay/index"}Effective interest rate of
                            <span class="effective--rate">{$coloSelectedInstallment['effectiveInterestRate']}%</span>
                            p.a.{/s}</li>
                    <li>{s name="colo_afterpay_installment_cartinfo" namespace="frontend/colo_afterpay/index"}The shopping cart of {$sBasket.sAmount|currency} results in a total loan amount of
                            <span class='total--amount'>{$coloSelectedInstallment['totalAmount']|currency}</span>
                            when selecting
                            <span class='installments--amount'>{$coloSelectedInstallment['numberOfInstallments']}</span>
                            installments.{/s}</li>
                </ul>
                {block name="colo_afterpay_installment_links"}
                    <div class="installment-information--links" data-modalbox="true" data-targetSelector="a" data-mode="iframe" data-height="500" data-width="750">
                        {if $sBasket.sAmount >= 200 && $coloSelectedInstallment['interestRate'] > 0}
                            {s name="colo_afterpay_installment_customerinfo200" namespace="frontend/colo_afterpay/index"}Click
                                <a href="https://documents.myafterpay.com/consumer-terms-conditions/{$coloAfterpayLanguageCode}/{if $coloAfterpayMerchantID}{$coloAfterpayMerchantID}{else}default{/if}/installment"><span
                                            style='text-decoration: underline;'>here</span></a>
                                for more Information, the standard informations for consumer loans and exemplary amortisation schedules.{/s}
                        {else}
                            {s name="colo_afterpay_installment_customerinfo" namespace="frontend/colo_afterpay/index"}Click
                                <a href="https://documents.myafterpay.com/consumer-terms-conditions/{$coloAfterpayLanguageCode}/{if $coloAfterpayMerchantID}{$coloAfterpayMerchantID}{else}default{/if}/installment"><span
                                            style='text-decoration: underline;'>here</span></a>
                                for more Information and exemplary amortisation schedules.{/s}
                        {/if}
                    </div>
                {/block}
            </div>
        {/block}
    </div>
{/block}