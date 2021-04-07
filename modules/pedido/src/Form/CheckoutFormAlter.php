<?php


namespace Drupal\pedido\Form;


use Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\form_alter_service\FormAlterBase;
use Drupal\profile\Entity\Profile;
use Drupal\user\Entity\User;
use Stripe\Stripe;

class CheckoutFormAlter extends FormAlterBase {

  /**
   * @inheritDoc
   */
  public function alterForm(array &$form, FormStateInterface $form_state): void {
    // Información de contacto.
    if (isset($form['contact_information']['email'])) {
      $form['contact_information']['email']["#title_display"] = 'none';
      $form['contact_information']['email']["#attributes"]['placeholder'] = 'Correo electrónico';
    }

    // Información de envio.
    $pane = 'shipping_information';
    $profile = 'shipping_profile';
    $shipping_profile = NULL;
    if (isset($form['shipping_information']["recalculate_shipping"])) {
      $form['shipping_information']["recalculate_shipping"]["#attributes"]['class'][] = 'hidden';
      $form['shipping_information']["recalculate_shipping"]["#attributes"]['class'][] = 'button-recalculate-shipping';

      $shipping_profile = $form_state->get('shipping_profile');

      if ($shipping_profile instanceof  Profile) {
        $form_state->set('shipping_profile', $shipping_profile);
        if ($shipping_profile->address->administrative_area != '' && $shipping_profile->address->locality != '') {
          $form['shipping_information']["recalculate_shipping"]["#attributes"]['class'][] = 'button-recalculate-shipping-onload';
        }
      }
    }

    if (isset($form["shipping_information"]["add_shipment"])) {
      $form["shipping_information"]["add_shipment"]["#attributes"]['class'][] = 'hidden';
      $form["shipping_information"]["add_shipment"]["#attributes"]['class'][] = 'button-refresh-summary';
      if ($shipping_profile instanceof  Profile) {
        $form_state->set('shipping_profile', $shipping_profile);
        if ($shipping_profile->address->administrative_area != '' && $shipping_profile->address->locality != '') {
          $form["shipping_information"]["add_shipment"]["#attributes"]['class'][] = 'button-refresh-summary-onload';
        }
      }
    }

    if (isset($form[$pane][$profile]["address"])) {
      $form[$pane][$profile]["address"]['widget'][0]['address']['#after_build'][] = [get_called_class(), 'profile_address_field'];
      $form[$pane][$profile]["field_telefono"]['widget'][0]['value']["#attributes"]['placeholder'] = 'Teléfono';
      $form[$pane][$profile]["field_telefono"]['widget'][0]['value']["#title_display"] = 'none';
      $form[$pane][$profile]["field_telefono"]['widget'][0]['value']["#attributes"]['data-toggle'] = 'tooltip';
      $form[$pane][$profile]["field_telefono"]['widget'][0]['value']["#attributes"]['title'] = $form[$pane][$profile]["field_telefono"]['widget'][0]['value']["#attributes"]['placeholder'];

      $form[$pane][$profile]["field_dni_cif"]['widget'][0]['value']["#attributes"]['placeholder'] = 'DNI/CIF';
      $form[$pane][$profile]["field_dni_cif"]['widget'][0]['value']["#title_display"] = 'none';
      $form[$pane][$profile]["field_dni_cif"]['widget'][0]['value']["#attributes"]['data-toggle'] = 'tooltip';
      $form[$pane][$profile]["field_dni_cif"]['widget'][0]['value']["#attributes"]['title'] = $form[$pane][$profile]["field_dni_cif"]['widget'][0]['value']["#attributes"]['placeholder'];
    }

    if (isset($form[$pane][$profile]['copy_to_address_book'])) {
      $form[$pane][$profile]['copy_to_address_book']['#access'] = FALSE;
    }


    if (isset($form[$pane]["shipments"][0]["shipping_method"]["widget"][0]["#title"])) {
      $form[$pane]["shipments"][0]["shipping_method"]["widget"][0]["#title"] = 'Forma de envío';
    }

    // Datos de facturación
    $pane = 'order_fields:checkout';
    $profile = 'billing_profile';

    $billing_profile = NULL;
    if (isset($form[$pane][$profile]['widget'][0]['profile'])) {

      if (\Drupal::currentUser()->id())  {
        $storage = \Drupal::entityTypeManager()->getStorage('profile');
        /** @var User $user */
        $user = User::load(\Drupal::currentUser()->id());
        $profiles = $storage->loadMultipleByUser($user, 'datos_de_facturacion');
        if (!empty($profiles)) {
          $billing_profile = current($profiles);
        }
      }
      $form_state->set('billing_profile_default', $billing_profile);
      if (isset($form[$pane][$profile]['widget'][0]['profile']['rendered']['#profile']) && $billing_profile) {
        $form[$pane][$profile]['widget'][0]['profile']['rendered']['#profile'] = Profile::load($billing_profile->id());
      }

      $form[$pane][$profile]['widget'][0]['profile']["address"]['widget'][0]['address']['#after_build'][] = [get_called_class(), 'profile_address_field'];
      $form[$pane][$profile]['widget'][0]['profile']["field_telefono"]['widget'][0]['value']["#attributes"]['placeholder'] = 'Teléfono';
      $form[$pane][$profile]['widget'][0]['profile']["field_telefono"]['widget'][0]['value']["#title_display"] = 'none';
      $form[$pane][$profile]['widget'][0]['profile']["field_telefono"]['widget'][0]['value']["#attributes"]['data-toggle'] = 'tooltip';
      $form[$pane][$profile]['widget'][0]['profile']["field_telefono"]['widget'][0]['value']["#attributes"]['title'] = $form[$pane][$profile]['widget'][0]['profile']["field_telefono"]['widget'][0]['value']["#attributes"]['placeholder'];

      $form[$pane][$profile]['widget'][0]['profile']["field_dni_cif"]['widget'][0]['value']["#attributes"]['placeholder'] = 'DNI/CIF';
      $form[$pane][$profile]['widget'][0]['profile']["field_dni_cif"]['widget'][0]['value']["#title_display"] = 'none';
      $form[$pane][$profile]['widget'][0]['profile']["field_dni_cif"]['widget'][0]['value']["#attributes"]['data-toggle'] = 'tooltip';
      $form[$pane][$profile]['widget'][0]['profile']["field_dni_cif"]['widget'][0]['value']["#attributes"]['title'] = $form[$pane][$profile]['widget'][0]['profile']["field_dni_cif"]['widget'][0]['value']["#attributes"]['placeholder'];

      if (isset($form[$pane][$profile]['widget'][0]['profile']['copy_to_address_book'])) {
        $form[$pane][$profile]['widget'][0]['profile']['copy_to_address_book']["#access"] = FALSE;
      }
    }

    // Registo finalizar compra
    if (isset($form['completion_register']['name'])) {
      $profiles = NULL;
      $order = \Drupal::routeMatch()->getParameter('commerce_order');

      if ($order instanceof Order) {
        $profiles = $order->collectProfiles();
      }


      $form['completion_register']['name']["#title_display"] = 'none';
      $form['completion_register']['name']["#attributes"]['placeholder'] = 'Correo electrónico';
      $form['completion_register']['name']["#description"] = '';
      if ($order instanceof Order) {
        $form['completion_register']['name']["#default_value"] = $order->getEmail();
        $form['completion_register']['name']['#attributes']['class'][] = 'hidden';

      }

      $form['completion_register']['field_nombre']['widget'][0]['value']['#placeholder'] = 'Nombre';
      $form['completion_register']['field_nombre']['widget'][0]['value']['#title_display'] = 'invisible';
      $form['completion_register']['field_nombre']['widget'][0]['value']['#attributes']['data-toggle'] = 'tooltip';
      $form['completion_register']['field_nombre']['widget'][0]['value']['#attributes']['title'] = $form['completion_register']['field_nombre']['widget'][0]['value']['#placeholder'];

      $form['completion_register']['field_apellidos']['widget'][0]['value']['#placeholder'] = 'Apellidos';
      $form['completion_register']['field_apellidos']['widget'][0]['value']['#title_display'] = 'invisible';
      $form['completion_register']['field_apellidos']['widget'][0]['value']['#attributes']['data-toggle'] = 'tooltip';
      $form['completion_register']['field_apellidos']['widget'][0]['value']['#attributes']['title'] = $form['completion_register']['field_apellidos']['widget'][0]['value']['#placeholder'];

      if (isset($profiles['shipping'])) {
        $profile = $profiles['shipping'];
        if ($profile instanceof Profile) {
          $form['completion_register']['field_nombre']['widget'][0]['value']['#default_value'] = $profile->get('address')->given_name;
          $form['completion_register']['field_apellidos']['widget'][0]['value']['#default_value'] = $profile->get('address')->family_name;
          $form['completion_register']['field_nombre']['#attributes']['class'][] = 'hidden';
          $form['completion_register']['field_apellidos']['#attributes']['class'][] = 'hidden';
        }
      }

      $form['completion_register']['field_nombre']['#weight'] = 1;
      $form['completion_register']['field_apellidos']['#weight'] = 2;
      $form['completion_register']['name']['#weight'] = 3;
      $form['completion_register']['pass']['#weight'] = 4;
    }

    if (isset($form['#step_id'])) {
      switch ($form['#step_id']) {
        case 'order_information':
          $form['actions']['next']['#value'] = 'Pagar y completar compra';
          $form['actions']['next']['#submit'][] = [get_called_class(), 'submitForm'];

          break;
        case 'review':
          $form['sidebar']['coupon_redemption']['#access'] = FALSE;
          $form['#attributes']['class'][] = 'chechout-review';
          break;
        case 'complete':
          if (isset($form['completion_register'])) {
            $form['#submit'][] = [get_called_class(), 'registerSubmit'];
          }
          break;
      }
    }

    $form['#attached']['library'][] = 'pedido/checkout';
  }

  /**
   * Formatear formulario de perfil.
   *
   * @param $element
   * @param $form_state
   *
   * @return mixed
   */
  public static function profile_address_field($element, $form_state): mixed {

    if (\Drupal::currentUser()->id()) {
      /** @var User $user */
      $user = User::load(\Drupal::currentUser()->id());
      $nombre = $user->get('field_nombre')->value;
      $apellidos = $user->get('field_apellidos')->value;
      if (!isset($element['given_name']['#value']) || $element['given_name']['#value'] == '') {
        $element['given_name']['#value'] = $nombre;
      }

      if (!isset($element['family_name']['#value']) || $element['family_name']['#value'] == '') {
        $element['family_name']['#value'] = $apellidos;
      }
    }



    $element['organization']["#title_display"] = 'none';
    $element['organization']["#attributes"]['placeholder'] = 'Empresa';
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

    if (isset($element['#name']) && $element['#name'] == 'order_fields:checkout[billing_profile][0][profile][address][0][address]') {
      $billing_profile = $form_state->get('billing_profile_default');
      if ($billing_profile instanceof Profile) {
        $element['organization']['#value'] = $billing_profile->get('address')->organization;
        $element['given_name']['#value'] = $billing_profile->get('address')->given_name;
        $element['family_name']['#value'] = $billing_profile->get('address')->family_name;
        $element['address_line1']['#value'] = $billing_profile->get('address')->address_line1;
        $element['address_line1']['#value'] = $billing_profile->get('address')->address_line1;
        $element['address_line2']['#value'] = $billing_profile->get('address')->address_line2;
        $element['postal_code']['#value'] = $billing_profile->get('address')->postal_code;
        $element['locality']['#value'] = $billing_profile->get('address')->locality;
        $element['administrative_area']['#value'] = $billing_profile->get('address')->administrative_area;
      }
    }

    return $element;
  }

  /**
   * Submit form.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function submitForm(array &$form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    $billing_profile = NULL;
    if (isset($form['order_fields:checkout']['billing_profile']['widget'][0]['profile']["#inline_form"])) {
      /** @var EntityInlineFormInterface $inline_form */
      $inline_form = $form['order_fields:checkout']['billing_profile']['widget'][0]['profile']["#inline_form"];
      $billing_profile = $inline_form->getEntity();
    }

    if(isset($values['order_fields:checkout']['billing_profile'][0]['profile']['copy_fields']['enable']) && $values['order_fields:checkout']['billing_profile'][0]['profile']['copy_fields']['enable'] == 0) {
      if (!isset($values['order_fields:checkout']['billing_profile'][0]['profile']['address'])) {
        $profiles = [];
        try {
          $storage = \Drupal::entityTypeManager()->getStorage('profile');
          /** @var User $user */
          $user = User::load(\Drupal::currentUser()->id());
          $profiles = $storage->loadMultipleByUser($user, 'datos_de_facturacion');
        }
        catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
          \Drupal::logger('pedido')->error($e->getMessage());
        }

        if (!empty($profiles)) {
          $billing_profile = current($profiles);
        }
      }
    }

    $object = $form_state->getformObject();
    if (method_exists($object, 'getOrder')) {
      /** @var Order $order */
      $order = $object->getOrder();
      if ($billing_profile instanceof Profile) {
        $order->setBillingProfile($billing_profile);
        $order->save();
      }
    }
  }

  /**
   * Añadir perfil de facturación al usuario nuevo.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function registerSubmit(array &$form, FormStateInterface $form_state) {
    $object = $form_state->getformObject();
    if (method_exists($object, 'getOrder')) {
      /** @var Order $order */
      $order = $object->getOrder();
      if ($order instanceof Order) {
        \Drupal::service('commerce_order.order_receipt_subscriber')->setBillingProfile($order);
        \Drupal::service('commerce_order.order_receipt_subscriber')->setUserInformation($order);
      }
    }
  }

}
