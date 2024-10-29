jQuery(function ($) {

    function apcopay_for_woocommerce_order_block() {
        $('#woocommerce-order-items').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });
    }

    function apcopay_for_woocommerce_order_unblock() {
        $('#woocommerce-order-items').unblock();
    }

    $('#woocommerce-order-items').on('click', 'button.apcopay-for-woocommerce-extra-charge', function () {

        let amount = window.prompt(apcopay_for_woocommerce_admin_order_data.messages.enter_extra_charge_amount);

        if (amount != null) {
            apcopay_for_woocommerce_order_block();
            $.post(apcopay_for_woocommerce_admin_order_data.ajax_url,
                {
                    _ajax_nonce: apcopay_for_woocommerce_admin_order_data.nonce_extra_charge,
                    action: "apcopay_for_woocommerce_extra_charge",
                    order_id: woocommerce_admin_meta_boxes.post_id,
                    amount: amount
                },
                function (data) {
                    if (data) {
                        if (data.isSuccess) {
                            alert(apcopay_for_woocommerce_admin_order_data.messages.extra_charge_success);
                            // Refresh page to show updated ui
                            window.location.reload();
                        } else {
                            apcopay_for_woocommerce_order_unblock();
                            if (data.errorMessage && data.errorMessage != null) {
                                alert(data.errorMessage);
                            } else {
                                alert(apcopay_for_woocommerce_admin_order_data.messages.error_processing_request);
                            }
                        }
                    } else {
                        apcopay_for_woocommerce_order_unblock();
                        alert(apcopay_for_woocommerce_admin_order_data.messages.error_processing_request);
                    }
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    apcopay_for_woocommerce_order_unblock();
                    alert(apcopay_for_woocommerce_admin_order_data.messages.error_processing_request);
                });
        }
    });

    $('#woocommerce-order-items').on('click', 'button.apcopay-for-woocommerce-capture', function () {

        let amount = window.prompt(apcopay_for_woocommerce_admin_order_data.messages.enter_capture_amount);

        if (amount != null) {
            apcopay_for_woocommerce_order_block();
            $.post(apcopay_for_woocommerce_admin_order_data.ajax_url,
                {
                    _ajax_nonce: apcopay_for_woocommerce_admin_order_data.nonce_capture,
                    action: "apcopay_for_woocommerce_capture",
                    order_id: woocommerce_admin_meta_boxes.post_id,
                    amount: amount
                },
                function (data) {
                    if (data) {
                        if (data.isSuccess) {
                            alert(apcopay_for_woocommerce_admin_order_data.messages.capture_success);
                            // Refresh page to show updated ui
                            window.location.reload();
                        } else {
                            apcopay_for_woocommerce_order_unblock();
                            if (data.errorMessage && data.errorMessage != null) {
                                alert(data.errorMessage);
                            } else {
                                alert(apcopay_for_woocommerce_admin_order_data.messages.error_processing_request);
                            }
                        }
                    } else {
                        apcopay_for_woocommerce_order_unblock();
                        alert(apcopay_for_woocommerce_admin_order_data.messages.error_processing_request);
                    }
                }).fail(function (jqXHR, textStatus, errorThrown) {
                    apcopay_for_woocommerce_order_unblock();
                    alert(apcopay_for_woocommerce_admin_order_data.messages.error_processing_request);
                });
        }
    });
});