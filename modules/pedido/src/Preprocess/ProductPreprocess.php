<?php


namespace Drupal\pedido\Preprocess;


use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_product\Entity\ProductAttribute;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductPreprocess {


  /**
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected CurrentRouteMatch $route;

  /**
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected ?Request $request;

  /**
   * ProductPreprocess constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   */
  public function __construct(RequestStack $requestStack, CurrentRouteMatch $currentRouteMatch) {
    $this->request = $requestStack->getCurrentRequest();
    $this->route = $currentRouteMatch;
  }

  public function select(array &$variables) {
    $request = $this->request;
    if ($request) {
      $item_id = $request->query->get('item_id');
      $parameters = $request->query->all();
      if ($item_id && !isset($parameters['ajax_form'])      ) {
        $orderItem = OrderItem::load($item_id);
        if ($orderItem instanceof OrderItem) {
          $variation = $orderItem->getPurchasedEntity();
          if ($variation instanceof ProductVariation) {
            $variables["product"]["variation_field_imagen"] = $variation->field_imagen->view($variables["elements"]["#view_mode"]);
          }
        }
      }
    }
  }
}
