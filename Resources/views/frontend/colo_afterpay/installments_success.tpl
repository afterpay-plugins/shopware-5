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
                 data-total-amount='{$coloAfterpayInstallment['totalAmount']|currency}'
                 data-total-interest-amount='{$coloAfterpayInstallment['totalInterestAmount']|currency}'>

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
                <br>
                <div>
                    <small>Die Ware bleibt im Eigentum des Verkäufers bis der ausstehende Gesamtbetrag voll gezahlt wurde
                    (Eigentumsvorbehalt). Es sind keine Versicherungen zu stellen. Die erste Monatsrate ist einen Monat
                    nach Vereinbarung der Ratenzahlung fällig. Die darauf folgenden Raten sind in Abstanden von jeweils
                    einem Monat fällig. Die Raten werden im Lastschriftverfahren eingezogen. . Die Option der Zahlung
                    der in der Zusammenfassung im Checkout angezeigten Gesamtsumme (Warenkorbwert zzgl. Versandkosten)
                    in Form der hier ausgewählten Raten (und zzgl. der angezeigten Zinskosten) hängt vom Zustandekommen
                    des nachträglichen Teilzahlungsgeschäfts ab (hierzu naher die AfterPay AGB). Sollte das
                    nachträgliche Teilzahlungsgeschäft nicht erfolgen, so ist die Gesamtsumme auf einmal zu zahlen und
                    die angezeigten Zinskosten entfallen.
                    </small>
                </div>
                <br>
                <div>
                    <small>
                    Beispiel: <br>
                    Bei einen Warenkorb im Wert von EUR 200,00, einem Soil-Zinssatz in Höhe von I 4,95% und einer
                    Rückzahlung des Gesamtbetrags in I2
                    gleichbleibenden monatlichen Raten ergibt sich ein effektiver Jahreszins in Hohe von 16,02%. Die
                    monatlich zu zahlenden Raten betragen dann jeweils EUR I 8,05, sodass sich der Gesamtbetrag auf
                    EUR 216,60 belauft. Bei gleichem Warenkorbwert und Soil-Zinssatz, jedoch bei Rückzahlung in 6
                    gleichbleibenden monatlichen Raten, ergibt sich ein effektiver Jahreszins von 15,9 I %. Die
                    monatlich zu zahlenden Raten betragen dann jeweils EUR 34,80 und der Gesamtbetrag beläuft sich auf
                    EUR 208,80.
                    </small>

                </div>

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