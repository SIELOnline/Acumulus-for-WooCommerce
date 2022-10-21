"use strict";
(function($) {
  // @todo: Herstel dit als we de rate plugin message in een div in de notice
  //   plaatsen en we dus niet meer het kruisje rechtsboven meenemen.
  //const buttonSelector = "button, input[type=button], input[type=submit]";
  const buttonSelector = "input[type=button], input[type=submit]";

  function addAcumulusAjaxHandling(elt) {
    // Add some classes to our buttons in our area.
    $(buttonSelector, ".acumulus-area").addClass("button button-primary"); // jQuery

    // Ajax <a> links: move the value of href to a data attribute and empty it.
    $("a.acumulus-ajax", elt).each(function() { // jQuery
      const target =  this.getAttribute("href");
      this.setAttribute("data-acumulus-ajax-href", target);
      this.setAttribute("href", "#");
    });

    // Define the click handling for button links.
    $("input.acumulus-ajax", elt).click(function() { // jQuery
      acumulusAjaxHandlingArea(this);
    });
    // Define the click handling for <a> links
    $("a.acumulus-ajax", elt).click(function() { // jQuery
      acumulusAjaxHandlingLink(this);
    });
  }

  function acumulusAjaxHandlingArea(clickedElt) {
    // Area is the Element that is going to be replaced and serves as the
    // parent in which we will search for form elements.
    const area = $(clickedElt).closest(".acumulus-area").get(0); // jQuery
    // Disable the buttons in the area.
    $(buttonSelector, area).prop("disabled", true); // jQuery
    // Show a text on the clicked button that tells the user that the request is
    // being executed.
    clickedElt.value = area.getAttribute("data-acumulus-wait");

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
      acumulus_nonce: area.getAttribute("data-acumulus-nonce"),
      clicked: clickedElt.name,
      area: area.id,
    };

    // area is not necessarily a form node, in which case FormData will not
    // work. So we clone area into a temporary form node.
    const form = document.createElement("form");
    form.appendChild(area.cloneNode(true));
    const formData = new FormData(form);
    for(let entry of formData.entries()) {
      data[entry[0]] = entry[1];
    }

    // ajaxurl is defined in the admin header and points to admin-ajax.php.
    $.post(ajaxurl, data, function(response) { // jQuery
      area.insertAdjacentHTML("beforebegin", response.content);
      const newArea = area.previousElementSibling;
      area.parentNode.removeChild(area);
      addAcumulusAjaxHandling(newArea);
      $(document.body).trigger("post-load"); // jQuery
      // Apparently, this help tip binding is not done on post-load.
      $(".woocommerce-help-tip", newArea).tipTip({ // jQuery
        "attribute": "data-tip",
        "fadeIn": 50,
        "fadeOut": 50,
        "delay": 200,
        "keepAlive": true
      });
    });
  }

  // @todo: Not longer functional, remove when no"longer needed as possible
  //   example for other plugins.
  function acumulusAjaxHandlingLink(that) {
    const clickedElt = $(that); // jQuery

    // Check if already "executing" this link.
    if (clickedElt.prop("disabled")) { // jQuery
      return;
    }
    // "Disable" this link and other buttons (if in our area)
    clickedElt.prop("disabled", true); // jQuery
    const area = clickedElt.parents(".acumulus-area").get(0); // jQuery
    if (area) {
      $(buttonSelector, area).prop("disabled", true); // jQuery
    }

    // All the data we need to send is already in the full link. So just
    // execute it.
    const ajaxLink =  that.getAttribute("data-acumulus-ajax-href");
    $.get(ajaxLink, function(/*response*/) { // jQuery
      clickedElt.prop("disabled", false); // jQuery
      if (area) {
        $(buttonSelector, area).prop("disabled", false); // jQuery
      }
    });
  }

  $(document).ready(function() { // jQuery
    addAcumulusAjaxHandling(document);
    $(".acumulus-auto-click").click(); // jQuery
  });
}(jQuery));
