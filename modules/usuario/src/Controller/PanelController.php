<?php


namespace Drupal\usuario\Controller;


use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\user\Entity\User;
use JetBrains\PhpStorm\ArrayShape;
use Picqer\Carriers\SendCloud\Parcel;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PanelController extends ControllerBase {

  /**
   * Panel usuario.
   *
   * @param $page
   *
   * @return string[]
   */
  #[ArrayShape([
    '#theme' => "string",
    '#page' => "",
    '#content' => "mixed",
    '#cache' => "array",
    '#attached' => "array"
  ])]
  public function panel($page): array {

    $content = NULL;

    switch ($page) {
      case 'cuenta':
        $entity = User::load(\Drupal::currentUser()->id());
        $content = \Drupal::service('entity.form_builder')->getForm($entity, 'default');
        break;
      case 'datos':
        $content = \Drupal::formBuilder()->getForm('\Drupal\usuario\Form\UsuarioDatosForm');
        break;
      case 'pedidos':
        $content['view'] = [
          '#type' => 'view',
          '#name' => 'panel_pedidos',
          '#display_id' => 'block_1'
          ];
        break;
      case 'tarjetas':
        $content = [
          '#theme' => 'tarjetas',
          '#uid' => \Drupal::currentUser()->id()
        ];
        break;
    }

    return [
      '#theme' => 'panel',
      '#page' => $page,
      '#content' => $content,
      '#cache' => ['max-age' => 0],
      '#attached' => ['library' => ['usuario/panel']]
    ];
  }

  /**
   * Eliminar tarjeta guardada.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethod $commerce_payment_method
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function eliminarTarjeta(PaymentMethod $commerce_payment_method): \Symfony\Component\HttpFoundation\RedirectResponse {
    if ($commerce_payment_method->get('uid')->target_id == $this->currentUser()->id()) {
      try {
        $commerce_payment_method->delete();
        $this->messenger()->addStatus('Tarjeta ' . $commerce_payment_method->get('card_number')->value . ' eliminada');
      }
      catch (EntityStorageException $e) {
        $this->messenger()->addError('No se ha podido eliminar la tarjeta');
        \Drupal::logger('panel')->error($e->getMessage());
      }
    }
    else {
      $this->messenger()->addError('La tarjeta pertenece a otro usuario');
    }

    return $this->redirect('usuario.panel', ['page' => 'tarjetas']);
  }

  /**
   * Presentación del pedido para el cliente.
   *
   * @param \Drupal\commerce_order\Entity\Order $commerce_order
   *
   * @return array
   */
  #[ArrayShape([
    '#theme' => "string",
    '#order' => "\Drupal\commerce_order\Entity\Order"
  ])]
  public function pedido(Order $commerce_order): array {
    return [
      '#theme' => 'panel_pedido',
      '#order' => $commerce_order,
    ];
  }

  /**
   * Titulo presentación del pedido para el cliente.
   *
   * @param \Drupal\commerce_order\Entity\Order $commerce_order
   *
   * @return string
   */
  public function pedidoTitle(Order $commerce_order): string {
    return 'Pedido #' . $commerce_order->getOrderNumber();
  }

  /**
   * Redirección a localizar pedido.
   *
   * @param \Drupal\commerce_order\Entity\Order $commerce_order
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function tracking(Order $commerce_order): RedirectResponse {
    $tracking_url = NULL;
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('commerce_sendcloud')) {
      $parcel = \Drupal::service('commerce_sendcloud.shipment')->getParcelOrder($commerce_order);
      if ($parcel instanceof Parcel) {
        return $this->redirect('commerce_sendcloud.tracking', ['tracking_number' => $parcel->tracking_number]);
      }
    }
    return new RedirectResponse('/panel/pedido');
  }


}
