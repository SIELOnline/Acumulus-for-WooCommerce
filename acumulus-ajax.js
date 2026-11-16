"use strict";
(function ($) {
    const buttonSelector = "button:not(.notice-dismiss), input[type=button], input[type=submit]";

    function addAcumulusAjaxHandling(elt) {
        // Add some classes to our buttons in our area.
            $(buttonSelector, ".acumulus-area").addClass("button button-primary"); // jQuery

        // Links on which we want ajax behaviour: move the value of the "href"
        // attribute to a data attribute and empty it.
        $("a.acumulus-ajax", elt).each(function () { // jQuery
            const target = this.getAttribute("href");
            this.setAttribute("data-acumulus-ajax-href", target);
            this.setAttribute("href", "#");
        });

        // Define the click handling for button links.
        $("input.acumulus-ajax", elt).click(function () { // jQuery
            acumulusAjaxHandlingArea(this);
        });
        // Define the click handling for <a> links
        $("a.acumulus-ajax", elt).click(function () { // jQuery
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

        // Add form values by selecting on the form attribute
        const formElementSelector = "[form=" + data.area + "]";
        $(formElementSelector, area).each(function () { // jQuery
            data[this.name ?? 'undefined'] = this.value ?? 'undefined';
        });

        // ajaxurl is defined in the admin header and points to admin-ajax.php.
        $.post(ajaxurl, data, function (response) { // jQuery
            area.insertAdjacentHTML("beforebegin", response.content);
            const newArea = area.previousElementSibling;
            area.parentNode.removeChild(area);
            addAcumulusAjaxHandling(newArea);
            $(document.body).trigger("post-load"); // jQuery
            // Apparently, this help tip binding is not done on post-load events.
            $(".woocommerce-help-tip", newArea).tipTip({ // jQuery
                "attribute": "data-tip",
                "fadeIn": 50,
                "fadeOut": 50,
                "delay": 200,
                "keepAlive": true
            });
        });
    }

    $(document).ready(function () { // jQuery
        addAcumulusAjaxHandling(document);
        $(".acumulus-auto-click").click(); // jQuery
    });
}(jQuery));
