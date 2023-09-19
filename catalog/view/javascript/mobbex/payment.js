jQuery(function ($) {
  //Event payment button
  $("#mobbex-payment").on("click", () => {
    //Open Mobbex checkout
    createCheckout((response) => !!mobbexEmbed ? embedPayment(response) : redirectToCheckout(response));
  });

  /**
   * Create the mobbex checkout.
   *
   * @param {CallableFunction} callback
   */
  function createCheckout(callback) {
    $.ajax({
      dataType: "json",
      method: "GET",
      url: mobbexData.checkoutUrl,
      success: function (response) {
        callback(response);
      },
      error: function () {
        location.href = mobbexData.returnUrl + "&status=500";
      },
    });
  }

  /**
   * Open embed Mobbex Checkout.
   *
   * @param object response
   */
  function embedPayment(response) {
    var options = {
      id: response.id,
      type: "checkout",

      onResult: (data) => {
        location.href = mobbexData.returnUrl + "&status=" + data.status.code;
      },

      onClose: () => {
        location.reload();
      },

      onError: (error) => {
        location.href = mobbexData.returnUrl + "&status=500";
      },
    };

    // Init Mobbex Embed
    var mbbxButton = window.MobbexEmbed.init(options);
    mbbxButton.open();
  }

  /**
   * Redirect to Mobbex Checkout.
   *
   * @param object response
   */
  function redirectToCheckout(response) {
    $("#mbbx_redirect_form").attr("action", response.url);
    $("#mbbx_redirect_form").submit();
  }
});