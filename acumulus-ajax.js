"use strict";
(function($) {
  function addAcumulusAjaxHandling(elt) {
    // @todo: Herstel dit als we de rate plugin message in een div in de notice
    //  plaatsen en we dus niet meer het kruisje rechtboven meenemen.
    //const buttonSelector = "button, input[type=button], input[type=submit]";
    const buttonSelector = "input[type=button], input[type=submit]";
    $(buttonSelector, ".acumulus-area").addClass("button button-primary"); // jQuery
    $(".acumulus-ajax", elt).click(function() { // jQuery
      // Area is the element that is going to be replaced and serves as the
      // parent in which we will search for form elements.
      const clickedElt = this;
      const area = $(clickedElt).parents(".acumulus-area").get(0); // jQuery
      $(buttonSelector, area).prop("disabled", true); // jQuery
      clickedElt.value = area.getAttribute('data-acumulus-wait');

      // The data we are going to send consists of:
      // - action: WP ajax action, used to route the request on the server to
      //   our plugin.
      // - acumulus_nonce: WP ajax form nonce.
      // - clicked: the name of the element that was clicked, the name should
      //   make clear what action is requested on the server and, optionally, on
      //   what object.
      // - area: the id of the area from which this request originates, the
      //   "acumulus form part" (though not necessarily a form node). This is
      //   used for further routing the request to the correct Acumulus form as
      //   'ajaxurl' is just 1 common url for all ajax requests and 'action' is
      //   just one hook for all Acumulus ajax requests.
      // - {values}: values of all form elements in area: input, select and
      //   textarea, except buttons (inputs with type="button").
      //noinspection JSUnresolvedVariable
      const data = {
        action: "acumulus_ajax_action",
        acumulus_nonce: area.getAttribute('data-acumulus-nonce'),
        clicked: clickedElt.name,
        area: area.id,
      };

      // area is not necessarily a form node, in which case FormData will not
      // work. So we clone area into a temporary form node.
      const form = document.createElement('form');
      form.appendChild(area.cloneNode(true));
      const formData = new FormData(form);
      for(let entry of formData.entries()) {
        data[entry[0]] = entry[1];
      }

      // ajaxurl is defined in the admin header and points to admin-ajax.php.
      $.post(ajaxurl, data, function(response) { // jQuery
        area.insertAdjacentHTML('beforebegin', response.content);
        const newArea = area.previousElementSibling;
        area.parentNode.removeChild(area);
        addAcumulusAjaxHandling(newArea);
        $(document.body).trigger("post-load"); // jQuery
      });
    });
  }

  $(document).ready(function() { // jQuery
    addAcumulusAjaxHandling(document);
    $(".acumulus-auto-click").click(); // jQuery
  });
}(jQuery));
