jQuery(document).ready(function ($) {

    var $wwlc_getting_started = $(".wwlc-getting-started");

    $wwlc_getting_started.find('button.notice-dismiss').click(function (e) {

        $wwlc_getting_started.fadeOut("fast", function () {
            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "wwlc_getting_started_notice_hide"
                },
                dataType: "json"
            })
                .done(function (data, textStatus, jqXHR) {
                    // notice is now hidden
                })

        });

    });

});