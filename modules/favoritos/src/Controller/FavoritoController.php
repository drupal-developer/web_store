<?php


namespace Drupal\favoritos\Controller;


use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;

class FavoritoController extends ControllerBase {

  /**
   * Acción añadir/eliminar producto de la lista de favoritos.
   *
   * @param \Drupal\commerce_product\Entity\Product $commerce_product
   * @param $action
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function action(Product $commerce_product, $action): AjaxResponse {
    $query = \Drupal::request()->query->get('view');
    if ($action == 'add') {
      \Drupal::service('favoritos')->addFavorito($commerce_product);
    }
    elseif($action == 'delete'){
      \Drupal::service('favoritos')->deleteFavorito($commerce_product);
    }
    $response = new AjaxResponse();
    $selector = '#link-favorito-' . $commerce_product->id();
    $link = ['#theme' => 'link_favorito', '#commerce_product' => $commerce_product];
    $response->addCommand(new ReplaceCommand($selector, $link));

    if ($query) {
      $view = [
        '#type' => 'view',
        '#name' => 'favoritos',
        '#display_id' => $query,
      ];
      $response->addCommand(new ReplaceCommand('.view-favoritos', $view));
    }
    return $response;
  }

  /**
   * Mover producto de la cesta a la lista de favoritos.
   *
   * @param \Drupal\commerce_order\Entity\OrderItem $commerce_order_item
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function cart(OrderItem $commerce_order_item): \Symfony\Component\HttpFoundation\RedirectResponse {
    $order = $commerce_order_item->getOrder();
    $variation = $commerce_order_item->getPurchasedEntity();
    if ($variation instanceof ProductVariation) {
      $product = $variation->getProduct();
      if ($product instanceof Product) {
        \Drupal::service('favoritos')->addFavorito($product);
      }
      $order->removeItem($commerce_order_item);
      try {
        $order->save();
        $commerce_order_item->delete();
      }
      catch (EntityStorageException $e) {
        \Drupal::logger('favoritos')->error($e->getMessage());
      }
    }

    return $this->redirect('commerce_cart.page');
  }
}
