jQuery(function ($) {
  let currentMethod = '';

  //Get wallet cards inputs
  const cardsInputs = document.querySelectorAll('.mobbex-card');

  //If a card is checked show card form in checkout.
  if(cardsInputs) {
    cardsInputs.forEach(card => {
      if(card.checked)
        document.querySelector(`#mobbex_${card.getAttribute('data-mobbex')}`).classList.toggle('hidden');
    });
  }

  //Event payment button
  $("#mobbex-payment").on("click", () => {
    let methods = document.querySelectorAll('.mobbex-method');

    methods.forEach(method => {
      if(method.checked)
        return currentMethod = method.getAttribute('data-mobbex');
    });

    if ($('[name=payment_method]:checked').attr('data-mobbex').includes('wallet_card')) {
      //Execute Wallet
      createCheckout((response) => executeWallet(response));
    } else {
      //Open Mobbex checkout
      createCheckout((response) => !!mobbexEmbed ? embedPayment(response) : redirectToCheckout(response));
    }
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

 /**
 * Execute wallet payment from selected card.
 * 
 * @param {array} response Mobbex checkout response.
 */
  function executeWallet(response) {
    let cardId      = $('[name=payment_method]:checked').attr('data-mobbex') ?? null;
    let cardNumber  = $(`#mobbex_${cardId}_number`).val();
    let updatedCard = response.wallet.find(card => card.card.card_number == cardNumber);
  
    var options = {
      intentToken: updatedCard.it,
      installment: $(`#mobbex_${cardId}_installments`).val(),
      securityCode: $(`#mobbex_${cardId}_code`).val()
    };

    window.MobbexJS.operation.process(options).then(data => {
      if (data.result === true) {
        location.href = mobbexData.returnUrl + '&status=' + data.data.status.code;
      } else {
        location.href = mobbexData.returnUrl + "&status=500";
      }
    }).catch(error => alert(error));
  }
});