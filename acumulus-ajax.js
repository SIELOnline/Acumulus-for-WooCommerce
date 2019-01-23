"use strict";
(function($) {
  function addAcumulusEvents() {
    $(".acumulus-ajax").click(function() {
      var elt = $(this);
      elt.val("Please wait").prop("disabled", true);
      // noinspection JSUnresolvedVariable
      var data = {
        action: "acumulus_ajax_action",
        service: elt.data("acumulus-service"),
        parent_type: elt.data("acumulus-parent_type"),
        parent_source: elt.data("acumulus-parent_source"),
        type: elt.data("acumulus-type"),
        source: elt.data("acumulus-source"),
        value:  elt.data("acumulus-value"),
        // Variable acumulus_data has been set via a wp_localize_script() call.
        security: acumulus_data.ajax_nonce
      };

      // Additional data from input elements (this works also for checkboxes and
      // radio buttons).
      jQuery.each($(".acumulus-ajax-data").serializeArray(), function(i, field) {
        data[field.name] = field.value;
      });

      // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
      $.post(ajaxurl, data, function(response) {
        $("#" + response.id).replaceWith(response.content);
        addAcumulusEvents();
        $(document.body).trigger("post-load");
      });
    });
  }

  $(document).ready(function() {
    addAcumulusEvents();
  });
}(jQuery));
