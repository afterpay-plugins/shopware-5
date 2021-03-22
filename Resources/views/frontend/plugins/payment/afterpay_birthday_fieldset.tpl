<div class='afterpay--birthday-container'>
    <div class="afterpay--birthday-label">
        <label for="afterpay_{$payment_mean.name|replace:'-':'_'}_birthdate"
               class="birthday--label">{s name='RegisterPlaceholderBirthday' namespace='frontend/register/personal_fieldset'}{/s}{s name="RequiredField" namespace="frontend/register/index"}{/s}</label>
    </div>

    <div class="afterpay--birthday field--select select-field">
        <select id="afterpay_{$payment_mean.name|replace:'-':'_'}_birthdate"
                name="colo_afterpay_payment[{$payment_mean.name}][sBirthday][day]"{if $payment_mean.id eq $sFormData.payment} required="required" aria-required="true"{/if}
                class="{if $payment_mean.id eq $sFormData.payment}is--required{/if}{if isset($error_flags.sBirthday.day)} has--error{/if}">
            <option{if $form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sBirthday.day} disabled="disabled"{/if}
                    value="">{s name='RegisterBirthdaySelectDay' namespace="frontend/register/personal_fieldset"}{/s}</option>

            {for $day = 1 to 31}
                <option value="{$day}" {if $day == $form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sBirthday.day}selected{/if}>{$day}</option>
            {/for}
        </select>
    </div>

    <div class="afterpay--birthmonth field--select select-field">
        <select name="colo_afterpay_payment[{$payment_mean.name}][sBirthday][month]"{if $payment_mean.id eq $sFormData.payment} required="required" aria-required="true"{/if}
                class="{if $payment_mean.id eq $sFormData.payment}is--required{/if}{if isset($error_flags.sBirthday.month)} has--error{/if}">
            <option{if $form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sBirthday.month} disabled="disabled"{/if}
                    value="">{s name='RegisterBirthdaySelectMonth' namespace="frontend/register/personal_fieldset"}{/s}</option>

            {for $month = 1 to 12}
                <option value="{$month}" {if $month == $form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sBirthday.month}selected{/if}>{$month}</option>
            {/for}
        </select>
    </div>

    <div class="afterpay--birthyear field--select select-field">
        <select name="colo_afterpay_payment[{$payment_mean.name}][sBirthday][year]"{if $payment_mean.id eq $sFormData.payment} required="required" aria-required="true"{/if}
                class="{if $payment_mean.id eq $sFormData.payment}is--required{/if}{if isset($error_flags.sBirthday.year)} has--error{/if}">
            <option{if $form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sBirthday.year} disabled="disabled"{/if}
                    value="">{s name='RegisterBirthdaySelectYear' namespace="frontend/register/personal_fieldset"}{/s}</option>

            {for $year = date("Y") to date("Y")-120 step=-1}
                <option value="{$year}" {if $year == $form_data.coloAfterpayPaymentDetails[{$payment_mean.name}].sBirthday.year}selected{/if}>{$year}</option>
            {/for}
        </select>
    </div>
</div>