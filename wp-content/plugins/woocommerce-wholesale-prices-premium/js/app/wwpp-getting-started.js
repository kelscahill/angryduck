jQuery(document).ready(function ($) {

    var $wwpp_getting_started = $(".wwpp-getting-started");

    $wwpp_getting_started.find('button.notice-dismiss').click(function (e) {

        $wwpp_getting_started.fadeOut("fast", function () {
            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "wwpp_getting_started_notice_hide"
                },
                dataType: "json"
            })
                .done(function (data, textStatus, jqXHR) {
                    // notice is now hidden
                })

        });

    });

});