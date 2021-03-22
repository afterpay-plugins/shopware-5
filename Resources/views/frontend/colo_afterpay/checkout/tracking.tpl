{if $ColoAfterpayConfigs['colo_afterpay_fingerprint'] && $ColoAfterpayConfigs['colo_afterpay_tracking_id'] && ($ColoUserLoggedIn || $ColoIsLoginRegisterPage)}
{literal}
    <script type="text/javascript">
        var _itt = {
            c: {/literal}'{$ColoAfterpayConfigs["colo_afterpay_tracking_id"]}'{literal},
            s: {/literal}'{$ColoAfterpayUserSessionId}'{literal},
            t: 'CO'
        };
        (function () {
            var a = document.createElement('script');
            a.type = 'text/javascript';
            a.async = true;
            a.src = '//uc8.tv/{/literal}{$ColoAfterpayConfigs["colo_afterpay_tracking_id"]}{literal}.js';
            var b = document.getElementsByTagName('script')[0];
            b.parentNode.insertBefore(a, b);
        })();
    </script>
    <noscript>
        <img src='//uc8.tv/img/{/literal}{$ColoAfterpayConfigs["colo_afterpay_tracking_id"]}{literal}/{/literal}{$ColoAfterpayUserSessionId}{literal}'
             border=0 height=0 width=0/>
    </noscript>
{/literal}
{/if}