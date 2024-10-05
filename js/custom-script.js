jQuery(document).ready(function($) {
    
    // enable select2 for billing address fields found by id starts with billing_address_fields_
    // select2 as tag select
    $('select[id^="billing_address_fields"]').select2({
        tags: true,
        placeholder: 'Выберите поля',
        allowClear: true,
        width: '100%',
        minimumResultsForSearch: -1,
    });
    
    var url = new URL(window.location.href);
    var tab = url.searchParams.get('tab');

    // make tab active
    if (tab) {
        $('.nav-tab').removeClass('nav-tab-active');
        $('.nav-tab[data-tab="' + tab + '"]').addClass('nav-tab-active');
    }
    
    // Переключение вкладок при клике
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).data('tab');

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // redirect to current url with get parameter tab=tabId
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);
        $(window).attr('location', url.href);
    });
    $('#billing_address_fields').select2();
});
