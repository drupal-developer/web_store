<?php


namespace Drupal\stock\Form;


use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_product\Entity\ProductAttribute;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\form_alter_service\Annotation\FormSubmit;
use Drupal\form_alter_service\FormAlterBase;
use Drupal\stock\Entity\Alerta;

class CommerceProductAddCartFormAlter extends FormAlterBase {

  /**
   * @inheritDoc
   */
  public function alterForm(array &$form, FormStateInterface $form_state): void {
    $storage = $form_state->getStorage();

    if (empty($storage['product'])) {
      return;
    }


    $storage = $form_state->getStorage();
    if ($storage["view_mode"] == 'full') {

      if (isset($form['outofstock'])) {
        $selectedVariationId = $form_state->get('selected_variation');
        if ($selectedVariationId != NULL) {
          $variation = ProductVariation::load($selectedVariationId);
        }
        else {
          $product = $storage['product'];
          $variation = $product->getDefaultVariation();
        }

        $form['outofstock'] = [
          '#type' => 'container',
          '#weight' => 0,
          'selected_variation' => [
            '#type' => 'value',
            '#value' => $variation->id(),
          ],
          'title' => [
            '#markup' => '<p class="tittle-no-stock">Fuera de stock</p>'
          ]
        ];

        if(!\Drupal::currentUser()->id()) {
          $form['outofstock']['email'] = [
            '#type' => 'email',
            '#title' => 'Email',
            '#title_display' => 'Email',
            '#placeholder' => 'Indícanos tu email...'
          ];
        }

        $form['outofstock']['alert'] = [
          '#type' => 'submit',
          '#value' => 'Avísame',
          '#submit' => ['\Drupal\stock\Form\CommerceProductAddCartFormAlter::alertSubmit']
        ];
      }
    }
  }

  /**
   * Crear alerta.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function alertSubmit(array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (isset($values['selected_variation'])) {
      $variation = ProductVariation::load($values['selected_variation']);
      if ($variation instanceof ProductVariation) {
        $datos = [
          'producto' => $variation->id(),
          'usuario' => \Drupal::currentUser()->id() ? \Drupal::currentUser()->id() : NULL,
          'email' => isset($values['email']) ? $values['email'] : NULL
        ];

        $alerta = Alerta::create($datos);

        try {
          $alerta->save();
          \Drupal::messenger()->addStatus('Alerta creada. Te avisaremos cuando dispongamos de stock.');
        }
        catch (EntityStorageException $e) {
          \Drupal::logger('stock')->error($e->getMessage());
        }

      }
    }
  }
}
