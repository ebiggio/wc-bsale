jQuery( function( $ ) {
    function initSelect2() {
        $( ':input.wc-product-search' ).filter( ':not(.enhanced)' ).each( function() {
            let select2_args = {
                allowClear: true,
                placeholder: $(this).data('placeholder'),
                minimumInputLength: $(this).data('minimum_input_length') ? $(this).data('minimum_input_length') : '3',
                escapeMarkup: function (m) {
                    return m;
                },
                ajax: {
                    url: wc_enhanced_select_params.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            term: params.term,
                            action: $(this).data('action') || 'woocommerce_json_search_products_and_variations',
                            security: wc_enhanced_select_params.search_products_nonce,
                        };
                    },
                    processResults: function (data) {
                        let terms = [];
                        if (data) {
                            $.each(data, function (id, text) {
                                terms.push({id: id, text: text});
                            });
                        }
                        return {
                            results: terms
                        };
                    },
                    cache: true
                }
            };

            $( this ).select2( select2_args ).addClass( 'enhanced' );
        });
    }

    initSelect2();
});
