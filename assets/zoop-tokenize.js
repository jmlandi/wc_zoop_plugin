/**
 * Client-side card tokenization for the Zoop credit card gateway.
 *
 * Zoop requires card data to be tokenized in the browser — never sent to a
 * merchant backend — for KYC-approved accounts (see docs.zoop.co/docs/
 * coletando-dados-de-cartao and docs.zoop.co/docs/autorizacao-direta-de-
 * transacoes). This mirrors the tokenizeCard() logic from the Letztech
 * gateway's public/tokenize.js so both integrations stay consistent.
 *
 * Flow: on checkout submit for the Zoop credit card gateway, block the
 * normal submit, tokenize the card fields against Zoop directly from the
 * browser using the publishable key, strip the raw card fields so they
 * never post to our own server, inject the resulting token, then resubmit.
 */
jQuery(function ($) {
    if (typeof zoopTokenizeData === 'undefined') {
        return;
    }

    var GATEWAY_ID = 'zoop_credit_card';
    var MARKETPLACE_ID = zoopTokenizeData.marketplaceId;
    var PUBLISHABLE_KEY = zoopTokenizeData.publishableKey;
    var tokenized = false;

    async function tokenizeCard(card) {
        if (!MARKETPLACE_ID || !PUBLISHABLE_KEY) {
            throw new Error(zoopTokenizeData.i18n.missingConfig);
        }

        var res = await fetch(
            'https://api.zoop.ws/v1/marketplaces/' + MARKETPLACE_ID + '/cards/tokens',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    // Basic Auth with the PUBLISHABLE key only. Never put a
                    // private API key here — this code runs in the browser.
                    Authorization: 'Basic ' + btoa(PUBLISHABLE_KEY + ':'),
                },
                body: JSON.stringify(card),
            }
        );

        if (!res.ok) {
            var errorBody = await res.text();
            throw new Error(zoopTokenizeData.i18n.tokenizeFailed + ' (' + res.status + '): ' + errorBody);
        }

        var data = await res.json();
        // Token expires in 1h (Zoop docs) — used immediately below, not stored.
        return data.id;
    }

    $(document.body).on('checkout_place_order_' + GATEWAY_ID, function () {
        if (tokenized) {
            return true;
        }

        var $form = $('form.checkout');
        var card = {
            holder_name: $('#card_holder_name').val(),
            card_number: ($('#card_number').val() || '').replace(/\s+/g, ''),
            expiration_month: ('0' + ($('#card_expiry_month').val() || '')).slice(-2),
            expiration_year: $('#card_expiry_year').val(),
            security_code: $('#card_security_code').val(),
        };

        $form.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });

        tokenizeCard(card)
            .then(function (tokenId) {
                $form.find('input[name="card_token"]').val(tokenId);

                // Never let raw card data leave the browser toward our own
                // server — drop the name attributes so they're excluded
                // from the form submission entirely.
                $('#card_number, #card_security_code, #card_expiry_month, #card_expiry_year').removeAttr('name');

                tokenized = true;
                $form.unblock();
                $form.submit();
            })
            .catch(function (err) {
                $form.unblock();
                window.alert(zoopTokenizeData.i18n.errorPrefix + ' ' + err.message);
                console.error('WC Letztech-payment: Falha na tokenização do cartão', err);
            });

        return false;
    });
});
