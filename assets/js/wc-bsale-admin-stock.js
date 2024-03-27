function updateAutoCheckboxStatus() {
    document.getElementById('wc_bsale_admin_auto_update').disabled = !document.getElementById('wc_bsale_admin_edit').checked;
}

document.getElementById('wc_bsale_admin_edit').addEventListener('change', updateAutoCheckboxStatus);
document.addEventListener('DOMContentLoaded', updateAutoCheckboxStatus);

jQuery(document).ready(function ($) {
    $('#wc_bsale_transversal_office_id').select2({
        placeholder: stock_parameters.placeholder,
        minimumInputLength: 3,
        allowClear: true,
        ajax: {
            url: stock_parameters.ajax_url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    term: params.term,
                    action: 'search_bsale_offices',
                    security: stock_parameters.nonce
                }
            },
            cache: true
        }
    })

    $('#wc_bsale_transversal_order_status').select2({});
});