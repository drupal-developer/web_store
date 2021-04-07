<?php


namespace Drupal\producto\Form;


use Drupal\Core\Form\FormStateInterface;
use Drupal\form_alter_service\FormAlterBase;
use Drupal\user\Entity\User;

class ProductAttributeFormAlter extends FormAlterBase {

  /**
   * @inheritDoc
   */
  public function alterForm(array &$form, FormStateInterface $form_state): void {
    if (isset($form['values'])) {
      foreach ($form['values'] as &$value) {
        if (isset($value['entity']['field_color'])) {
          $value['entity']['field_color']['widget'][0]['value']['#type'] = 'color';
        }
      }
    }

    /** @var User $user */
    $user = User::load(\Drupal::currentUser()->id());
    if (!$user->hasPermission('administer product attributes')) {
      $form['label']['#disabled'] = TRUE;
      $form['elementType']['#access'] = FALSE;
      $form['variation_types']['#access'] = FALSE;
      $form['actions']['delete']['#access'] = FALSE;
    }

  }

}
