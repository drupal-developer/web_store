(function ($, Drupal) {
  Drupal.behaviors.checkout = {
    attach: function (context, settings) {
      if ($('.checkout-pane-shipping-information').length) {
        $(window).on('load', function () {
          $('.button-recalculate-shipping-onload').mousedown();
          $('.button-refresh-summary-onload').mousedown();
        });

        $('.path-checkout').on('change', '.checkout-pane-shipping-information .administrative-area', function(){
          $('.button-recalculate-shipping').mousedown();
          setTimeout(function (){
            $('.button-refresh-summary').mousedown();
          }, 1500)
        });
      }
      $('.field--name-shipping-method input').change(function (){
        $('.button-refresh-summary').mousedown();
      })

      $('.field--name-shipping-method label').click(function (){
        $('.button-refresh-summary').mousedown();
      })

      let buttonReview = $('.chechout-review button.form-submit');

      $(window).on('load', function () {
        if (buttonReview.length) {
          buttonReview.click();
        }
      });

    }
  };
})(jQuery, Drupal);
