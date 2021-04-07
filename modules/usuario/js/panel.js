(function ($, Drupal) {
  Drupal.behaviors.panel = {
    attach: function (context, settings) {
      // Eliminar tarjeta
      $('.card-delete').click(function (){
        let card_number = $(this).attr('data-number');
        let id = $(this).attr('data-id');
        let modal = $('#eliminarTarjeta');
        $('#modal-card-number').text(card_number);
        $('#btn-eliminar').attr('href', '/tarjeta/' + id + '/delete');
        modal.modal('show');
      });
    }
  };
})(jQuery, Drupal);
