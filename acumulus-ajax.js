"use strict";
(function($) {
  function addAcumulusAjaxHandling() {
    $(".acumulus-ajax").click(function() { // jQuery
      const clickedElt = this;
      //noinspection JSUnresolvedVariable
      clickedElt.value = acumulus_data.wait
      clickedElt.disabled = true;

      // Area is the element that is going to be replaced and serves as the
      // parent in which we will search for form elements.
      const area = $(this).parents(".acumulus-area").get(0); // jQuery

      // The data we are going to send consists of:
      // - action: WP ajax action, used to route the request on the server to
      //   our plugin.
      // - acumulus_nonce: WP ajax form nonce.
      // - clicked: the name of the element that was clicked, the name should
      //   make clear what action is requested on the server and, optionally, on
      //   what object.
      // - {values}: values of all form elements in area: input, select and
      //   textarea, except buttons (inputs with type="button"). If multiple
      //   buttons exist in the area, the naming will
      //noinspection JSUnresolvedVariable
      const data = {
        action: "acumulus_ajax_action",
        // Variable acumulus_data has been set via a wp_localize_script() call.
        acumulus_nonce: acumulus_data.ajax_nonce,
        clicked: clickedElt.name,
        area: area.id,
      };
      const form = document.createElement('form');
      form.appendChild(area.cloneNode(true));
      const formData = new FormData(form);
      for(let entry of formData.entries()) {
        data[entry[0]] = entry[1];
      }

      // ajaxurl is defined in the admin header and points to admin-ajax.php.
      $.post(ajaxurl, data, function(response) { // jQuery
        area.insertAdjacentHTML('beforebegin', response.content);
        area.parentNode.removeChild(area);
        addAcumulusAjaxHandling();
        $(document.body).trigger("post-load"); // jQuery
      });
    });
  }

  $(document).ready(function() { // jQuery
    addAcumulusAjaxHandling();
  });
}(jQuery));
