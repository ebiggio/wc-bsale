jQuery(document).ready(function ($) {
    function initSelect2() {
        $( ':input.wc-bsale-ajax-select2' ).filter( ':not(.enhanced)' ).each( function() {
            let select2_args = {
                allowClear: !!$(this).data('allow_clear'),
                placeholder: $(this).data('placeholder'),
                minimumInputLength: $(this).data('minimum_input_length') ? $(this).data('minimum_input_length') : '3',
                escapeMarkup: function (m) {
                    return m;
                },
                ajax: {
                    url: invoice_parameters.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            term: params.term,
                            action: $(this).data('ajax-action'),
                            security: invoice_parameters.nonce
                        };
                    },
                    cache: true
                }
            };

            $( this ).select2( select2_args ).addClass( 'enhanced' );
        });
    }

    initSelect2();

    $('#wc_bsale_invoice_order_status').select2({});
});
