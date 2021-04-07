<?php


namespace Drupal\pedido\Form;


use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_product\Entity\ProductAttribute;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\form_alter_service\FormAlterBase;

class CommerceProductAddCartFormAlter extends FormAlterBase {

  /**
   * @inheritDoc
   */
  public function alterForm(array &$form, FormStateInterface $form_state): void {

    $storage = $form_state->getStorage();
    if ($storage["view_mode"] == 'full') {
      $item_id = \Drupal::request()->query->get('item_id');
      $trigger_element = $form_state->getTriggeringElement();
      if ($item_id) {
        $form['actions']['submit']['#submit'][] = [get_called_class(), 'editCartProduct'];

        $orderItem = OrderItem::load($item_id);
        if ($orderItem instanceof OrderItem && !$trigger_element) {
          $variation = $orderItem->getPurchasedEntity();
          if ($variation instanceof ProductVariation) {
            $attributes = $variation->getAttributeValues();
            if (isset($attributes['attribute_talla'])) {
              $form["purchased_entity"]["widget"][0]["attributes"]["attribute_talla"]['#default_value'] = $attributes['attribute_talla']->id();
            }

            if (isset($attributes['attribute_color'])) {
              $form["purchased_entity"]["widget"][0]["attributes"]["attribute_color"]['#default_value'] = $attributes['attribute_color']->id();
            }

            if (isset($attributes['attribute_sexo'])) {
              $form["purchased_entity"]["widget"][0]["attributes"]["attribute_sexo"]['#default_value'] = $attributes['attribute_sexo']->id();
            }
          }
        }
      }
    }
  }

  /**
   * Editar producto de la cesta.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function editCartProduct(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    $values = $form_state->getValues();
    if (isset($storage['cart_id'])){
      $order = Order::load($storage['cart_id']);
      if ($order instanceof Order) {
        $item_id = \Drupal::request()->query->get('item_id');
        if ($item_id) {
          $orderItem = OrderItem::load($item_id);
          if ($orderItem instanceof OrderItem) {
            $talla_id = $values["purchased_entity"][0]["attributes"]["attribute_talla"];
            $color_id = $values["purchased_entity"][0]["attributes"]["attribute_color"];
            $sexo_id = $values["purchased_entity"][0]["attributes"]["attribute_sexo"];
            $variation = $orderItem->getPurchasedEntity();
            $deleteItem = FALSE;
            if ($variation instanceof ProductVariation) {
              $attributes = $variation->getAttributeValues();
              if (isset($attributes['attribute_talla']) && $attributes['attribute_talla']->id() != $talla_id) {
                $deleteItem = TRUE;
              }

              if (isset($attributes['attribute_color']) && $attributes['attribute_color']->id() != $color_id) {
                $deleteItem = TRUE;
              }

              if (isset($attributes['attribute_sexo']) && $attributes['attribute_sexo']->id() != $sexo_id) {
                $deleteItem = TRUE;
              }
            }

            if ($deleteItem) {
              $order->removeItem($orderItem);
              try {
                $orderItem->delete();
                $order->save();
              }
              catch (EntityStorageException $e) {
                \Drupal::logger('track')->error($e->getMessage());
              }
            }
          }
        }
      }
    }
  }
}
