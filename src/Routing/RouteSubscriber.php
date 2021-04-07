<?php


namespace Drupal\store\Routing;


use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Routing\RouteSubscriberBase;

class RouteSubscriber extends RouteSubscriberBase {

  /**
   * @inheritDoc
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('commerce.configuration')) {
      $route->setRequirement('_permission', 'access store configuration');
    }

    if ($route = $collection->get('entity.commerce_product_attribute.add_form')) {
      $route->setRequirement('_permission', 'administer product attributes');
    }

    if ($route = $collection->get('entity.commerce_product_attribute.delete_form')) {
      $route->setRequirements(['_permission', 'administer product attributes']);
    }
  }
}
