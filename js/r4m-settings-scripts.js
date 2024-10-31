//Auto send orders by status
;(function($) {
    $("#r4me_auto_send").change(function(){
        var autoSend = $(this).val();
        if(autoSend === "yes"){
            $("#r4m_send_by_order_status_section").fadeIn();
        }else{
            $("#r4m_send_by_order_status_section").fadeOut();
        }
    });
}(jQuery));