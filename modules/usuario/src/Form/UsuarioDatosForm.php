<?php

namespace Drupal\usuario\Form;

use Drupal;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use CommerceGuys\Addressing\AddressFormat\AddressField;
use Drupal\profile\Entity\Profile;
use Drupal\user\Entity\User;

/**
 * Class misDatosForm.
 */
class UsuarioDatosForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'usuario_datos_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $tipo = NULL): array {
    $account = \Drupal::currentUser();
    $list = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByProperties([
        'uid' => $account->id(),
        'is_default' => 1,
      ]);
    $billing_profile = NULL;
    $shipping_profile = NULL;
    if (!empty($list)) {
      /** @var Profile $profile */
      foreach ($list as $profile) {
        if ($profile->bundle() == 'customer') {
          $shipping_profile = $profile;
        }
        elseif ($profile->bundle() == 'datos_de_facturacion') {
          $billing_profile = $profile;
        }
      }
    }


    /** @var User $usuario */
    $usuario = User::load($account->id());

    $form['field_nombre'] = [
      '#type' => 'textfield',
      '#title' => 'Nombre',
      '#title_display' => 'none',
      '#default_value' => isset($usuario->get('field_nombre')->value) ? $usuario->get('field_nombre')->value : '',
      '#attributes' => [
        'placeholder' => ['Nombre'],
        'data-toggle' => 'tooltip',
        'title' => 'Nombre'
      ],
    ];
    $form['field_apellidos'] = [
      '#type' => 'textfield',
      '#title' => 'Apellidos',
      '#title_display' => 'none',
      '#default_value' => isset($usuario->get('field_apellidos')->value) ? $usuario->get('field_apellidos')->value : '',
      '#attributes' => [
        'placeholder' => ['Apellidos'],
        'data-toggle' => 'tooltip',
        'title' => 'Apellidos'
      ],
    ];

    $form['field_telefono'] = [
      '#type' => 'textfield',
      '#title' => 'Teléfono',
      '#title_display' => 'none',
      '#default_value' => isset($usuario->get('field_telefono')->value) ? $usuario->get('field_telefono')->value : '',
      '#attributes' => [
        'placeholder' => ['Número de teléfono'],
        'data-toggle' => 'tooltip',
        'title' => 'Teléfono'
      ],
    ];

    $form['field_dni_cif'] = [
      '#type' => 'textfield',
      '#title' => 'DNI',
      '#title_display' => 'none',
      '#default_value' => $billing_profile ? $billing_profile->get('field_dni_cif')->value : '',
      '#attributes' => [
        'placeholder' => ['CIF/NIF'],
        'data-toggle' => 'tooltip',
        'title' => 'CIF/NIF'
      ],
    ];

    $form['datos_de_facturacion'] = [
      '#type' => 'address',
      '#title' => 'Datos fiscales',
      '#profile_id' => $billing_profile ? $billing_profile->id() : 0,
      '#default_value' => $billing_profile ?  $billing_profile->toArray()['address'][0] : ['country_code' => 'ES'],
      '#used_fields' => [
        AddressField::ORGANIZATION,
        AddressField::ADDRESS_LINE1,
        AddressField::ADDRESS_LINE2,
        AddressField::LOCALITY,
        AddressField::ADMINISTRATIVE_AREA,
        AddressField::POSTAL_CODE,
      ],
      '#available_countries' => ['ES'],
      '#after_build' => [
        '::address_modify',
      ],
    ];


    $form['customer'] = [
      '#type' => 'address',
      '#title' => 'Datos de envio',
      '#default_value' => $shipping_profile ?  $shipping_profile->toArray()['address'][0] : ['country_code' => 'ES'],
      '#profile_id' => $shipping_profile ? $shipping_profile->id() : 0,
      '#used_fields' => [
        AddressField::ADDRESS_LINE1,
        AddressField::ADDRESS_LINE2,
        AddressField::LOCALITY,
        AddressField::ADMINISTRATIVE_AREA,
        AddressField::POSTAL_CODE,
      ],
      '#available_countries' => ['ES'],
      '#after_build' => [
        '::address_modify',
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Guardar cambios',
    ];

    $form['#theme'] = 'usuario_datos_form';


    return $form;
  }



  public function submitForm(array &$form, FormStateInterface $form_state) {
    $account = \Drupal::currentUser();
    $values = $form_state->getValues();
    /** @var User $usuario */
    $usuario = User::load($account->id());

    $usuario->set('field_nombre', $values['field_nombre']);
    $usuario->set('field_apellidos', $values['field_apellidos']);
    $usuario->set('field_telefono', $values['field_telefono']);

    $address_billing = $values['datos_de_facturacion'];
    $address_billing['given_name'] = $values['field_nombre'];
    $address_billing['family_name'] = $values['field_apellidos'];

    $address_shipping = $values['customer'];
    $address_shipping['given_name'] = $values['field_nombre'];
    $address_shipping['family_name'] = $values['field_apellidos'];

    if ($form['datos_de_facturacion']['#profile_id']) {
      /** @var Profile $billing_profile */
      $billing_profile = Profile::load($form['datos_de_facturacion']['#profile_id']);

      $billing_profile->set('address', $address_billing);
      $billing_profile->set('field_telefono', $values['field_telefono']);
      $billing_profile->set('field_dni_cif', $values['field_dni_cif']);
    }
    else {
      $billing_profile = Profile::create([
        'type' => 'datos_de_facturacion',
        'uid' => $usuario->id(),
        'address' => $address_billing,
        'field_dni' => $values['field_dni_cif'],
        'field_telefono' =>  $values['field_telefono']
      ]);
    }


    if ($form['customer']['#profile_id']) {
      /** @var Profile $shipping_profile */
      $shipping_profile = Profile::load($form['customer']['#profile_id']);

      $shipping_profile->set('address', $address_shipping);
      $shipping_profile->set('field_telefono',  $values['field_telefono']);
      $shipping_profile->set('field_dni_cif',  $values['field_dni_cif']);
    }
    else {
      $shipping_profile = Profile::create([
        'type' => 'customer',
        'uid' => $usuario->id(),
        'address' => $address_shipping,
        'field_dni' =>  $values['field_dni_cif'],
        'field_telefono' =>  $values['field_telefono']
      ]);
    }

    try {
      $usuario->save();
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('panel_usuario')->error($e->getMessage());
    }
    try {
      $billing_profile->save();
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('panel_usuario')->error($e->getMessage());
    }
    try {
      $shipping_profile->save();
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('panel_usuario')->error($e->getMessage());
    }

    $this->messenger()->addStatus('Datos guardados');

  }

  public static function address_modify($element, $form_state){
    $element['organization']["#title_display"] = 'none';
    $element['organization']["#attributes"]['placeholder'] = 'Razón social';
    $element['organization']["#attributes"]['data-toggle'] = 'tooltip';
    $element['organization']["#attributes"]['title'] = $element['organization']["#attributes"]['placeholder'];
    $element['given_name']["#title_display"] = 'none';
    $element['given_name']["#attributes"]['placeholder'] = 'Nombre';
    $element['given_name']["#attributes"]['data-toggle'] = 'tooltip';
    $element['given_name']["#attributes"]['title'] = $element['given_name']["#attributes"]['placeholder'];
    $element['family_name']["#title_display"] = 'none';
    $element['family_name']["#attributes"]['placeholder'] = 'Apellidos';
    $element['family_name']["#attributes"]['data-toggle'] = 'tooltip';
    $element['family_name']["#attributes"]['title'] = $element['family_name']["#attributes"]['placeholder'];
    $element['address_line1']["#title_display"] = 'none';
    $element['address_line1']["#attributes"]['placeholder'] = 'Calle y número';
    $element['address_line1']["#attributes"]['data-toggle'] = 'tooltip';
    $element['address_line1']["#attributes"]['title'] = 'Calle y número';
    $element['address_line2']["#title_display"] = 'none';
    $element['address_line2']["#attributes"]['placeholder'] = 'Escalera y piso';
    $element['address_line2']["#attributes"]['data-toggle'] = 'tooltip';
    $element['address_line2']["#attributes"]['title'] = 'Escalera y piso';
    $element['postal_code']["#title_display"] = 'none';
    $element['postal_code']["#attributes"]['placeholder'] = 'Código postal';
    $element['postal_code']["#attributes"]['data-toggle'] = 'tooltip';
    $element['postal_code']["#attributes"]['title'] = $element['postal_code']["#attributes"]['placeholder'];
    $element['locality']["#title_display"] = 'none';
    $element['locality']["#attributes"]['placeholder'] = 'Ciudad';
    $element['locality']["#attributes"]['data-toggle'] = 'tooltip';
    $element['locality']["#attributes"]['title'] = $element['locality']["#attributes"]['placeholder'];
    $element['administrative_area']["#title_display"] = 'none';
    $element['administrative_area']["#options"][''] = '- Provincia -';
    $element['administrative_area']["#attributes"]['data-toggle'] = 'tooltip';
    $element['administrative_area']["#attributes"]['title'] ='Provincia';

    $element['country_code']['#attributes']['class'][] = 'hidden';

    return $element;
  }


}
