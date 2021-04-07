<?php


namespace Drupal\pedido\Plugin\views\field;


use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Acciones para los pedidos en el panel de usuario.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("commerce_order_user_actions_field")
 */
class OrderUserActionsField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * @inheritDoc
   */
  public function render(ResultRow $values) {

    $actions = [
      '#theme' => 'order_user_actions',
      '#order_id' => $values->order_id
    ];
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('commerce_sendcloud')) {
      $actions['#tracking'] = TRUE;
    }

    return $actions;
  }

}
