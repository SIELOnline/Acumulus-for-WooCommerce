"use strict";
(function($) {
  function addAcumulusEvents() {
    $(".acumulus-ajax").click(function() {
      var elt = $(this);
      elt.val("Please wait").prop("disabled", true);
      let data = {
        action: "acumulus_ajax_action",
        // Variable acumulus_data has been set via a wp_localize_script() call.
        security: acumulus_data.ajax_nonce
      };
      const eltData = elt.data();
      let key = '';
      for (let eltKey in eltData) {
        if (eltData.hasOwnProperty(eltKey)) {
          if (eltKey.startsWith('acumulus')) {
            // jQuery changes keys with - to camelCase: 'acumulus-service' =>
            // 'acumulusService', we want to extract 'service' out of that.
            key = eltKey.substr('acumulus'.length, 1).toLowerCase() + eltKey.substr('acumulus'.length + 1);
            data[key] = eltData[eltKey];
          }
        }
      }

      // Additional data from input elements (this works also for checkboxes and
      // radio buttons).
      jQuery.each($(".acumulus-ajax-data").serializeArray(), function(i, field) {
        data[field.name] = field.value;
      });

      // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
      $.post(ajaxurl, data, function(response) {
        if (response.id === 'wrap') {
          $("#" + response.id + " h1:first-of-type").before(response.content);
        } else {
          $("#" + response.id).replaceWith(response.content);
          addAcumulusEvents();
          $(document.body).trigger("post-load");
        }
      });
    });
  }

  $(document).ready(function() {
    addAcumulusEvents();
  });
}(jQuery));
