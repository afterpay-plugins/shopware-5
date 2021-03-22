;
(function ($, window, document) {
    var ColoAfterpay = {
        opts: {
            formSelector: '#shippingPaymentForm',
            radioSelector: 'input.auto_submit[type=radio]',
            submitSelector: 'input[type=submit]'
        },
        init: function () {
            var me = this;
            me.registerEvents();
            me.registerSubscribers();
            me.initInstallments();
        },
        registerEvents: function () {
            var me = this;
            $("form.payment").on("submit", function (ev) {
                me.onPaymentFormSubmit($(this));
            });
        },
        registerSubscribers: function () {
            var me = this;
            $.subscribe('plugin/swShippingPayment/onInputChanged', $.proxy(me.onShippingPaymentChanged, me));
        },
        initInstallments: function () {
            var me = this,
                $opts = me.opts,
                form = $($opts.formSelector);
            if (form.length === 0) {
                return false;
            }
            me.loadInstallmentPlans(form);
        },
        onPaymentFormSubmit: function (form) {
            var me = this, ibanDD, ibanInstallment,
                ibanFieldDD = form.find("#iban-dd"),
                ibanFieldInstallment = form.find("#iban-installment");
            if (ibanFieldDD.length > 0) {
                ibanDD = ibanFieldDD.val();
                if (ibanFieldDD.hasClass("masked") && ibanDD === "") {
                    ibanFieldDD.removeAttr("name");
                }
            }
            if (ibanFieldInstallment.length > 0) {
                ibanInstallment = ibanFieldInstallment.val();
                if (ibanFieldInstallment.hasClass("masked") && ibanInstallment === "") {
                    ibanFieldInstallment.removeAttr("name");
                }
            }
        },
        onShippingPaymentChanged: function (ev, plugin) {
            var me = this,
                $el = plugin.$el,
                form = $el.find(me.opts.formSelector);
            if (form.length === 0) {
                return false;
            }
            me.loadInstallmentPlans(form);
        },
        loadInstallmentPlans: function (form) {
            var me = this,
                $opts = me.opts;
            var checkedRadio = form.find(".payment--method-list " + $opts.radioSelector + ":checked");
            if (checkedRadio.length === 0) {
                console.log("Radio input not found.");
                return false;
            }
            var installmentsUrl = checkedRadio.attr("data-installments-url");
            if (typeof installmentsUrl === "undefined" || installmentsUrl === false) {
                return false;
            }
            $.ajax({
                url: installmentsUrl,
                method: 'GET',
                dataType: 'html',
                success: function (result) {
                    var paymentMethodContainer = checkedRadio.parents(".payment--method");
                    if (paymentMethodContainer.length === 0) {
                        console.log("html element with \"payment--method\" class not found.");
                        return false;
                    }
                    var installmentsContainer = paymentMethodContainer.find(".colo-afterpay--installments");
                    if (installmentsContainer.length > 0) {
                        installmentsContainer.replace(result);
                    } else {
                        paymentMethodContainer.find(".payment--form-group").append(result);
                    }

                    $(".installment--plan input[type='radio']").on("change", function (ev) {
                        var plan = $(this).parents(".installment--plan");
                        $(".installment--plan").removeClass("active");
                        plan.addClass('active');

                        $(".installment--information").find(".interest--rate").html(plan.attr("data-interest-rate"));
                        $(".installment--information").find(".effective--rate").html(plan.attr("data-effective-rate"));
                        $(".installment--information").find(".total--amount").html(plan.attr("data-total-amount"));
                        $(".installment--information").find(".installments--amount").html(plan.attr("data-installments-amount"));
                    });
                    window.StateManager.addPlugin('.installment-information--links[data-modalbox="true"]', 'swModalbox');
                }
            });
        }
    };

    $(function () {
        ColoAfterpay.init();
    });
})($, window, document);