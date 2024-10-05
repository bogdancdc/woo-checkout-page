jQuery(document).ready(function($) {
    // Обработка события изменения выбранного способа доставки
    function handleShippingMethodChange(first_time) {
        // Получаем выбранный метод доставки
        var selectedShippingMethod = $('input[name^="shipping_method"]:checked').val();
        if (selectedShippingMethod != undefined) {
            var a = selectedShippingMethod.split(':');

            selectedShippingMethod = a[0] + ':' + a[1];
            
            var fieldsToToggle = billing_address_fields[selectedShippingMethod] || [];
            
            $('input[id^="billing_"]').parents('.form-row').addClass('required-unset').removeClass('validate-required').hide();
            $('select[id^="billing_"]').parents('.form-row').addClass('required-unset').removeClass('validate-required').hide();
            // $('#' + fieldId).parents('.form-row').removeClass('validate-required');
            // $('#' + fieldId).parents('.form-row').addClass('required-unset');


            // $.each(fieldsToToggle, function(index, fieldId) {
            // });
            
            $('input[id^="billing_"]').parents('.form-row').removeClass('tfr-changed');
            $('select[id^="billing_"]').parents('.form-row').removeClass('tfr-changed');

            if ($('#billing_country').val() == ''){
                $('#billing_country').val('RU');
            }
            if ($('#billing_city').val() == ''){
                $('#billing_city').val('Москва');
            }
            // $('#billing_city').trigger('change');


            let check = function(element, index) { return ['billing_first_name', 'billing_last_name', 'billing_phone', 'billing_email'].indexOf(element) > -1; };
            
            if (fieldsToToggle.length <= 4 && fieldsToToggle.every(check)) {
                $('.ch-heading-addres').hide();
            } else {
                $('.ch-heading-addres').show();
                if (!first_time) {
                    // scroll to the ch-heading-addres
                    $('html, body').animate({
                        scrollTop: $(".ch-heading-addres").offset().top
                    }, 700);
                }
            }

            // Показываем только выбранные поля ввода
            $.each(fieldsToToggle, function(index, fieldId) {
                $('#' + fieldId).parents('.form-row').show();
                $('#' + fieldId).parents('.form-row').addClass('tfr-changed');
                if ($('#' + fieldId).parents('.form-row').hasClass('required-unset')) {
                    $('#' + fieldId).parents('.form-row').addClass('validate-required').removeClass('required-unset');
                    // $('#' + fieldId).parents('.form-row');
                }
            });
        } else {
            if ($('#billing_country').val() == ''){
                $('#billing_country').val('RU');
            }
            if ($('#billing_city').val() == ''){
                $('#billing_city').val('Москва');
            }
        }
    }
    
    handleShippingMethodChange(true);
    
    $('body').on('updated_checkout', function() {
        handleShippingMethodChange(false);
    });
});
