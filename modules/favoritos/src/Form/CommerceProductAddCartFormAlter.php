<?php


namespace Drupal\favoritos\Form;


use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_product\Entity\ProductAttribute;
use Drupal\Core\Form\FormStateInterface;
use Drupal\form_alter_service\Annotation\FormSubmit;
use Drupal\form_alter_service\FormAlterBase;

class CommerceProductAddCartFormAlter extends FormAlterBase {

  /**
   * @inheritDoc
   */
  public function alterForm(array &$form, FormStateInterface $form_state): void {
    $storage = $form_state->getStorage();

    $storage = $form_state->getStorage();
    if ($storage["view_mode"] == 'full') {
      if (!isset($form['actions']['submit'])) {
        $form['actions'] = ['#type' => 'actions'];
      }
      $form['actions']['link_favorito'] = ['#theme' => 'link_favorito', '#commerce_product' => $storage["product"], '#weight' => 10];
    }
  }


}
