jQuery(function ($) {

    function apcopay_for_woocommerce_hosted_form_generate_token(successCallback, errorCallback) {
        $.post(apcopay_for_woocommerce_hosted_form_data.ajax_url,
            {
                _ajax_nonce: apcopay_for_woocommerce_hosted_form_data.nonce,
                action: "apcopay_for_woocommerce_generate_token"
            },
            function (data) {
                if (data) {
                    if (data.isSuccess) {
                        if (successCallback) {
                            successCallback(data.token);
                        }
                    } else {
                        if (errorCallback) {
                            if (data.errorMessage && data.errorMessage != null) {
                                errorCallback(data.errorMessage);
                            } else {
                                errorCallback(apcopay_for_woocommerce_hosted_form_data.messages.error_processing_request);
                            }
                        }
                    }
                } else {
                    if (errorCallback) {
                        errorCallback(apcopay_for_woocommerce_hosted_form_data.messages.error_processing_request);
                    }
                }
            }).fail(function (jqXHR, textStatus, errorThrown) {
                if (errorCallback) {
                    errorCallback(apcopay_for_woocommerce_hosted_form_data.messages.error_processing_request);
                }
            });
    }

    // Abstracts the hosted form communication
    let apcopay_for_woocommerce_hosted_form = {
        token: null,
        // Callbacks
        initialisedCallback: null,
        submitSuccessCallback: null,
        errorCallback: null,
        // Functions
        init: function () {
            window.addEventListener('message', apcopay_for_woocommerce_hosted_form.receive, false);
        },
        send_message: function (message) {
            let paymentFrame = document.getElementById('apcopay-for-woocommerce-checkout-frame');
            if (paymentFrame && paymentFrame.contentWindow) {
                paymentFrame.contentWindow.postMessage(message, apcopay_for_woocommerce_hosted_form_data.apcopay_baseurl);
            }
        },
        submit: function () {
            apcopay_for_woocommerce_hosted_form.send_message('apcopay_submit');
        },
        setToken: function (token) {
            apcopay_for_woocommerce_hosted_form.token = token;
            apcopay_for_woocommerce_hosted_form.send_message('apcopay_set_token:' + token);
        },
        showSavedCards: function () {
            apcopay_for_woocommerce_hosted_form.send_message('apcopay_show_saved_cards');
        },
        setLanguage: function (language) {
            apcopay_for_woocommerce_hosted_form.send_message('apcopay_set_language:' + language);
        },
        showFields: function (fields) {
            apcopay_for_woocommerce_hosted_form.send_message('apcopay_show_fields:' + fields.join(','));
        },
        setTransactionType: function (transactionType) {
            apcopay_for_woocommerce_hosted_form.send_message('apcopay_set_transaction_type:' + transactionType);
        },
        receive: function (event) {
            if (event.origin !== apcopay_for_woocommerce_hosted_form_data.apcopay_baseurl) return;

            // Must be a string
            if (typeof event.data !== 'string' && !(event.data instanceof String)) return;

            if (event.data === 'apcopay_initialised') {
                if (apcopay_for_woocommerce_hosted_form.initialisedCallback) {
                    apcopay_for_woocommerce_hosted_form.initialisedCallback();
                }
            } else if (event.data === 'apcopay_success') {
                if (apcopay_for_woocommerce_hosted_form.submitSuccessCallback) {
                    apcopay_for_woocommerce_hosted_form.submitSuccessCallback();
                }
            } else if (event.data.startsWith('apcopay_error')) {
                let message = event.data.replace('apcopay_error:', '');
                if (apcopay_for_woocommerce_hosted_form.errorCallback) {
                    apcopay_for_woocommerce_hosted_form.errorCallback(message);
                }
            }
        }
    };

    // Page hooks
    apcopay_for_woocommerce_hosted_form.initialisedCallback = function () {
        // apcopay_for_woocommerce_hosted_form.showFields(['HolderName']);
        apcopay_for_woocommerce_hosted_form.setLanguage(apcopay_for_woocommerce_hosted_form_data.language);
        apcopay_for_woocommerce_hosted_form.setTransactionType(apcopay_for_woocommerce_hosted_form_data.transaction_type);
        if (apcopay_for_woocommerce_hosted_form_data.MinTimeElapsed) {
            // If token is loaded, set token of hosted form
            if (apcopay_for_woocommerce_hosted_form_data.TokenCache != null) {
                apcopay_for_woocommerce_hosted_form_init();
            }
        }
    };

    apcopay_for_woocommerce_hosted_form.submitSuccessCallback = function () {
        let form = document.querySelector('form.woocommerce-checkout');
        if (form == null) {
            // Repay pending order form
            form = document.getElementById('order_review');
        }
        let tokenField = document.createElement('input');
        tokenField.id = 'apcopay_for_woocommerce_hosted_form_token';
        tokenField.setAttribute('type', 'hidden');
        tokenField.setAttribute('name', 'apcopay_for_woocommerce_hosted_form_token');
        tokenField.setAttribute('value', apcopay_for_woocommerce_hosted_form.token);
        form.appendChild(tokenField);

        $('#place_order').prop('disabled', false);
        apcopay_for_woocommerce_hosted_form_data.NeedsSubmit = true;
        document.getElementById('place_order').click();
    };

    apcopay_for_woocommerce_hosted_form.errorCallback = function (message) {
        if (message === 'Invalid token') {
            // Stop from regenerating too much tokens
            if (apcopay_for_woocommerce_hosted_form_data.GenerateTokenCount == null) {
                apcopay_for_woocommerce_hosted_form_data.GenerateTokenCount = 0;
            } else {
                apcopay_for_woocommerce_hosted_form_data.GenerateTokenCount++;
                if (apcopay_for_woocommerce_hosted_form_data.GenerateTokenCount > 5) {
                    window.location.reload(); // Something could have gone wrong, refresh page
                    return;
                }
            }

            $('.apcopay-for-woocommerce-loading-panel').show();
            apcopay_for_woocommerce_hosted_form_generate_token(
                function (token) {
                    $('.apcopay-for-woocommerce-loading-panel').hide();
                    apcopay_for_woocommerce_hosted_form_data.TokenCache = token;
                    apcopay_for_woocommerce_hosted_form.setToken(apcopay_for_woocommerce_hosted_form_data.TokenCache);
                    apcopay_for_woocommerce_hosted_form.submit();
                }, function (error) {
                    $('.apcopay-for-woocommerce-loading-panel').hide();
                    // Unable to regenerate token, refresh page
                    window.location.reload();
                });
        } else {
            $('#place_order').prop('disabled', false);
            document.getElementById('apcopay-for-woocommerce-checkout-frame').style.height = apcopay_for_woocommerce_hosted_form_data.show_saved_cards ? '280px' : '230px';
        }
    };

    // Called when iframe is loaded, token is received and "min time" elapsed
    // The iframe may be loaded multiple times by the page, "min time" prevents multiple calls to the hosted form
    function apcopay_for_woocommerce_hosted_form_init() {
        apcopay_for_woocommerce_hosted_form.setToken(apcopay_for_woocommerce_hosted_form_data.TokenCache);
        if (apcopay_for_woocommerce_hosted_form_data.show_saved_cards) {
            apcopay_for_woocommerce_hosted_form.showSavedCards();
        }
        $('.apcopay-for-woocommerce-loading-panel').hide();
    }

    // Place order button click
    $(document.body).on('click', '#place_order', function (event) {
        // Check selected 
        if ($('input[name="payment_method"]:checked').val() === 'apcopay') {
            // Check if hosted iframe card data was submitted
            if (apcopay_for_woocommerce_hosted_form_data.NeedsSubmit) {
                apcopay_for_woocommerce_hosted_form_data.NeedsSubmit = false;

                // Prevent button click until current submit finishes
                // There can be a delay until page shows loading
                setTimeout(function () {
                    $('#place_order').prop('disabled', false);
                }, 2000);

                // Signal checkout form to submit
                return true;
            } else {
                // Check hosted frame is ready
                if (apcopay_for_woocommerce_hosted_form.token) {
                    $('#place_order').prop('disabled', true);
                    // Submit hosted form
                    apcopay_for_woocommerce_hosted_form.submit();
                }

                // Prevent checkout form submit
                event.preventDefault();
                event.stopImmediatePropagation();
                return false;
            }
        }
    });

    apcopay_for_woocommerce_hosted_form.init();
    apcopay_for_woocommerce_hosted_form_generate_token(
        function (token) {
            // Cache token for now
            // Can be used if when time elapsed or iframe loads after token is received
            apcopay_for_woocommerce_hosted_form_data.TokenCache = token;
            if (apcopay_for_woocommerce_hosted_form_data.MinTimeElapsed) {
                // Set hosted form token
                apcopay_for_woocommerce_hosted_form_init();
            }
        });

    // Avoids multiple calls to backend if iframe is loaded multiple times in the time specified
    apcopay_for_woocommerce_hosted_form_data.MinTimeElapsed = false;
    apcopay_for_woocommerce_hosted_form_data.TokenCache = null;
    setTimeout(function () {
        apcopay_for_woocommerce_hosted_form_data.MinTimeElapsed = true;
        // If token is loaded, set token of hosted form
        if (apcopay_for_woocommerce_hosted_form_data.TokenCache != null) {
            apcopay_for_woocommerce_hosted_form_init();
        }
    }, 2000);
});