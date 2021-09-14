//* CHECKOUT *//
function getCheckout(event) {
    event.preventDefault();

    var options = {
        id: event.target.getAttribute('checkoutId'),
        type: "checkout",
        onResult: (data) => {
            var status = data.status.code;
            if (status > 1 && status < 400) {
                window.top.location.href = event.target.getAttribute('returnUrl') + '&status=' + status + '&transactionId=' + data.id;
            } else {
                window.top.location.reload();
            }
        },
        onClose: (cancelled) => {
            // Only if cancelled    
            if (cancelled === true) {
                window.top.location.reload();
            }
        },
    };

    initMobbexPayment(options);
}

function initMobbexPayment(options) {
    var mbbxButton = window.MobbexEmbed.init(options);
    mbbxButton.open();
}