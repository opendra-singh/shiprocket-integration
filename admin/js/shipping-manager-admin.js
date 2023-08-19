(function ($) {
  "use strict";

  /**
   * All of the code for your admin-facing JavaScript source
   * should reside in this file.
   *
   * Note: It has been assumed you will write jQuery code here, so the
   * $ function reference has been prepared for usage within the scope
   * of this function.
   *
   * This enables you to define handlers, for when the DOM is ready:
   *
   * $(function() {
   *
   * });
   *
   * When the window is loaded:
   *
   * $( window ).load(function() {
   *
   * });
   *
   * ...and/or other possibilities.
   *
   * Ideally, it is not considered best practise to attach more than a
   * single DOM-ready or window-load handler for a particular page.
   * Although scripts in the WordPress core, Plugins and Themes may be
   * practising this, we should strive to set a better example in our own work.
   */

  document.addEventListener("DOMContentLoaded", function () {
    $(document).find("body").tooltip({ selector: "[data-toggle=tooltip]" });
    $(document).on("click", ".add-order-shiprocket", function (e) {
      e.preventDefault();
      const element = this;
      const order_id = element.nextElementSibling.value;
	  document.querySelector(".mereloader[order-id='"+order_id+"']").classList.remove('d-none');
      const bodyContent = {
        action: "shipping_manager_generate_shipment_ajax",
        post_id: order_id,
        key: "only_order",
      };
      $.post(ajax_object.ajax_url, bodyContent, function (response) {
		alert(response);
		document.querySelector(".mereloader[order-id='"+order_id+"']").classList.add('d-none');
      });
    });

	$(document).on("click", ".generate-shipment-btn", function(e){
		e.preventDefault()
		const btn = this;
		const loader =
		  btn.nextElementSibling.nextElementSibling.nextElementSibling;
		const warning_icon_url =
		  btn.nextElementSibling.nextElementSibling.nextElementSibling
			.nextElementSibling.value;
		loader.classList.remove("d-none");
		const post_id = btn.nextElementSibling.value;
		const url = btn.nextElementSibling.nextElementSibling.value;
		const data = {
		  action: "shipping_manager_generate_shipment_ajax",
		  post_id: post_id,
		  url: url,
		  key: "full",
		};

		$.post(ajax_object.ajax_url, data, function (response) {
		  loader.classList.add("d-none");
		  if (response.includes("Shipment Generated Successfully.")) {
			const data = response.split("|");
			alert(data[0]);
			const url = data[2] == "nimbus" ? "https://ship.nimbuspost.com/shipping/tracking/" : "https://shiprocket.co/tracking/" 
			const track_btn =
			  '<a class="track-shipment-btn btn btn-outline-danger" target="_blank" href="' + url +
			  data[1] +
			  '">Track Shipment</a>';
			const parser = new DOMParser();
			const htmlDoc = parser.parseFromString(track_btn, "text/html");
			btn.parentElement.prepend(htmlDoc.querySelector("a"));
			btn.remove();
		  } else {
        const data = response.split("|");
			alert(data[0]);
			const warning_icon =
			  '<div class="col-2"><span data-toggle="tooltip" data-placement="right" data-original-title="' +
			  data[0] +
			  '"><img width="20" src="' +
			  warning_icon_url +
			  '"></span></div>';
			const parser = new DOMParser();
			const htmlDoc = parser.parseFromString(warning_icon, "text/html");
			if (btn.parentElement.parentElement.childNodes.length == 2) {
			  btn.parentElement.parentElement.firstElementChild.remove();
			  btn.parentElement.parentElement.prepend(
				htmlDoc.querySelector("div")
			  );
			} else {
			  btn.parentElement.parentElement.prepend(
				htmlDoc.querySelector("div")
			  );
			}
		  }
		});
	})

	setTimeout(() => {
      const book_name_add_btn = document.querySelector(
        ".add-file-name-book-update-btn"
      );
      if (book_name_add_btn !== undefined && book_name_add_btn !== null) {
        book_name_add_btn.addEventListener("click", function () {
          let products = [];
          const order_id = document
            .querySelector(".fields")
            .firstElementChild.getAttribute("order-id");
          let status = "false";
          document.querySelectorAll(".fields .row").forEach((e) => {
            const name = e.previousSibling.textContent;
            const id = e.querySelector("input").getAttribute("data-pid");
            const english = e.firstElementChild.firstElementChild.value;
            const hindi = e.lastElementChild.lastElementChild.value;
            if (
              (name.split(" - ")[1].toLowerCase() == "english" &&
                english == "") ||
              (name.split(" - ")[1].toLowerCase() == "hindi" && hindi == "")
            ) {
              status = "true";
            }
            products.push({
              id: id,
              name: name,
              english: english,
              hindi: hindi,
            });
          });
          if (status == "false") {
            const data = {
              action: "update_product_file_name_shop_order",
              products: products,
              order_id: order_id,
            };
            const attr = {};
            products.forEach((e) => {
              attr[e.id] = { name: e.name, meta_value: [e.english, e.hindi] };
            });
            $.post(ajax_object.ajax_url, data, function (response) {
				alert(response);
              document
                .getElementById("post-" + order_id)
                .querySelector("td.add_order button.book_name_add")
                .setAttribute(
                  "data-product",
                  JSON.stringify(attr).replace('"', "'")
				  );
				document
          .querySelector(".mereloader[order-id='" + order_id + "']")
          .classList.add("d-none");
            });
			document.getElementsByClassName("close")[0].click();
			  document.querySelector(".mereloader[order-id='"+order_id+"']").classList.remove('d-none');

          } else {
            alert("Please Enter Selective Book File Name");
          }
        });
      }
    }, 5000);
  });
})(jQuery);
