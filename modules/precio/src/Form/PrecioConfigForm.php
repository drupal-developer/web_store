<?php


namespace Drupal\precio\Form;


use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class PrecioConfigForm extends ConfigFormBase {

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return ['precio.config'];
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'precio_config';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('precio.config');

    $form = [];

    $form['exchange_rate'] = [
      '#type' => 'details',
      '#title' => 'Conversión de precios',
      '#tree' => TRUE,
    ];

    $form['exchange_rate']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => 'Activar conversión de precios',
      '#default_value' => $config->get('exchange_rate_enabled'),
    ];

    $form['exchange_rate']['api_key'] = [
      '#type' => 'textfield',
      '#title' => 'Exchange Rate Api Key',
      '#default_value' => $config->get('exchange_rate_api_key'),
      '#description' => '<a target="_blank" href="https://www.exchangerate-api.com/">Obtener clave api</a>'
    ];

    $form['exchange_rate']['currency'] = [
      '#type' => 'select',
      '#title' => 'Moneda base',
      '#options' => ['EUR' => 'Euro', 'USD' => 'Dolar', 'GPB' => 'Libra'],
      '#default_value' => $config->get('exchange_rate_currency'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => 'Guardar'
      ],
    ];

    return $form;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('precio.config');
    $values = $form_state->getValues();
    $config->set('exchange_rate_enabled', $values['exchange_rate']['enabled']);
    $config->set('exchange_rate_api_key', $values['exchange_rate']['api_key']);
    $config->set('exchange_rate_currency', $values['exchange_rate']['currency']);
    $config->save();
  }


}
