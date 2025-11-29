(function ($, Drupal, drupalSettings, once) {

    Drupal.behaviors.yibackjs = {
        attach: function (context) {

            /**
             * 
             */
            const checkFormId = ['config-pages-fimi-settings-form'];
            checkFormId.forEach(elementFormId => {
                once('yibackjs', '#' + elementFormId, context).forEach(() => {
                    console.log(checkFormId);

                    if (location.hash) {
                        $('#' + elementFormId + ' .horizontal-tabs-list a[href="' + location.hash + '"]').click();
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
            once('yibackjs', '.edit-field-helperbox-renderblock', context).forEach((el) => {
                // const jsdata = drupalSettings.helperbox_renderblock;
                const $thiscontext = $(el);
                $thiscontext.find('.contextual.edit-adminlinks .trigger').on('click', function () {
                    $thiscontext.find('.edit-adminlinks').toggleClass('open');
                });
            });

        }
    };

})(jQuery, Drupal, drupalSettings, once);

