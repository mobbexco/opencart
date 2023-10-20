jQuery(function ($) {
  let currentMethod = '';

  //Event payment button
  $("#mobbex-payment").on("click", () => {
    let methods = document.querySelectorAll('.mobbex-method');

    methods.forEach(method => {
      if(method.checked)
        return currentMethod = method.getAttribute('data-mobbex');
    });

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
      paymentMethod: currentMethod ?? null,

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
    //Get checkout redirect form
    let mobbexForm = $("#mbbx_redirect_form");

    //add checkout url to action
    $("#mbbx_redirect_form").attr("action", response.url);
    $("#mbbx_redirect_form").attr("method", 'get');

    //Add current method
    if(currentMethod !== ''){
      let method = document.createElement('input');
      method.setAttribute('type', 'hidden');
      method.setAttribute('name', 'paymentMethod');
      method.setAttribute('value', currentMethod);
      $("#mbbx_redirect_form").append(method);
    }

    //Submit form to open checkout
    mobbexForm.submit();
  }
});