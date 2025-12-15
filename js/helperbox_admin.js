(function ($, Drupal, drupalSettings, once) {

    Drupal.behaviors.helperboxadminjs = {
        attach: function (context) {

            /**
             * 
             */
            const checkFormId = [
                'config-pages-fimi-settings-form',
                'node-understanding-fimi-edit-form',
            ];
            checkFormId.forEach(elementFormId => {
                once('helperboxadminjs', '#' + elementFormId, context).forEach(() => {
                    if (location.hash) {
                        var target = $('#' + elementFormId + ' .horizontal-tabs-list a[href="' + location.hash + '"]');
                        if (target.length) {
                            target.click();
                            // Scroll to the element
                            $('html, body').animate({
                                scrollTop: target.offset().top - 100
                            }, 400);
                        }

                    }
                    $('#' + elementFormId + ' ul.horizontal-tabs-list li a').on('click', function (e) {
                        const href = $(this).attr('href');        // e.g. "#edit-group-contact-info"
                        const tablink = location.pathname + location.search + href;
                        history.replaceState(null, null, tablink);
                    });

                });

            });
            // 

            /**
             * 
             **/
            once('helperboxadminjs', '.edit-field-helperbox-renderblock', context).forEach((el) => {
                const $thiscontext = $(el);
                $thiscontext.find('.contextual.edit-adminlinks .trigger').on('click', function () {
                    $thiscontext.find('.edit-adminlinks').toggleClass('open');
                });
            });

        }
    };

})(jQuery, Drupal, drupalSettings, once);

