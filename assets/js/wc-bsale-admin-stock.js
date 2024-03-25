function updateAutoCheckboxStatus() {
    document.getElementById('wc_bsale_admin_auto_update').disabled = !document.getElementById('wc_bsale_admin_edit').checked;
}

document.getElementById('wc_bsale_admin_edit').addEventListener('change', updateAutoCheckboxStatus);
document.addEventListener('DOMContentLoaded', updateAutoCheckboxStatus);

jQuery(document).ready(function ($) {
    $('#wc_bsale_transversal_office_id').select2({
        placeholder: bsale_offices.select2_placeholder,
        minimumInputLength: 2,
        allowClear: true,
        ajax: {
            url: bsale_offices.offices_ajax_url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    term: params.term,
                    action: 'search_bsale_offices'
                }
            },
            cache: true
        }
    })

    $('#wc_bsale_transversal_order_status').select2({});
});