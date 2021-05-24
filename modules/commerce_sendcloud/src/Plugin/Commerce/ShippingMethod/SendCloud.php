<?php


namespace Drupal\commerce_sendcloud\Plugin\Commerce\ShippingMethod;


use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\profile\Entity\Profile;
use Drupal\state_machine\WorkflowManagerInterface;
use Picqer\Carriers\SendCloud\Connection;

/**
 * Método de envío a través SendCloud.
 *
 * @CommerceShippingMethod(
 *   id = "commerce_sendcloud",
 *   label = @Translation("SendCloud"),
 * )
 */
class SendCloud extends ShippingMethodBase {

  const TEST_SHIPPING_METHOD = 8;

  /**
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  private LoggerChannel $logger;

  /**
   * Constructs a new SendCloud object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param $plugin_id
   *   The plugin_id for the plugin instance.
   * @param $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, WorkflowManagerInterface $workflow_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager);
    $this->logger = new LoggerChannel('send_cloud_api');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
        'public_key' => '',
        'secret_key' => '',
        'tracking_url' => '',
        'devolution_url' => '',
        'mode' => '',
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['public_key'] = [
      '#type' => 'textfield',
      '#title' => t('Public Key'),
      '#default_value' => $this->configuration['public_key'],
      '#required' => TRUE,
    ];
    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => t('Secret Key'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['secret_key'],
    ];

    $form['tracking_url'] = [
      '#type' => 'textfield',
      '#title' => t('Tracking Url'),
      '#default_value' => $this->configuration['tracking_url'],
    ];

    $form['devolution_url'] = [
      '#type' => 'textfield',
      '#title' => t('Devolution Url'),
      '#default_value' => $this->configuration['devolution_url'],
    ];

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => t('Mode'),
      '#options' => ['live' => t('Live'), 'test' => t('Test')],
      '#default_value' => $this->configuration['mode'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['public_key'] = $values['public_key'];
      $this->configuration['secret_key'] = $values['secret_key'];
      $this->configuration['tracking_url'] = $values['tracking_url'];
      $this->configuration['devolution_url'] = $values['devolution_url'];
      $this->configuration['mode'] = $values['mode'];
    }
  }

  private function getRates(Order $order, Profile $profile): array {
    $currency = \Drupal::service('commerce_currency_resolver.current_currency')->getCurrency();
    $config = $this->getConfiguration();
    $rates = [];
    $country = $profile->get('address')->country_code;
    $connection = new Connection($config['public_key'], $config['secret_key']);
    $sendcloudClient = new \Picqer\Carriers\SendCloud\SendCloud($connection);
    $shipping_methods = $sendcloudClient->shippingMethods()->all();

    // Calcular peso total pedido
    $peso = 0;
    foreach ($order->getItems() as $item) {
      $producto = $item->getPurchasedEntity();
      if ($producto instanceof ProductVariation) {
        $peso += $producto->get('weight')->number * $item->getQuantity();
      }
    }


    // Obtener métodos de envio disponibles
    foreach($shipping_methods as $item) {
      $method = $item->attributes();

      if ($method['id'] == self::TEST_SHIPPING_METHOD) continue;
      if ($method['min_weight'] <= $peso && $method['max_weight'] >= $peso) {
        foreach ($method['countries'] as $item) {
          if ($item['iso_2'] == $country) {
            $service_name = $method['carrier'];
            $label = $method['name'];
            $price = $item['price'] ? $item['price'] : $method['price'];
            $price = ['number' => $price, 'currency_code' => $currency];
            $this->services[$service_name] = new ShippingService($method['id'], $label);
            $rates[] = new ShippingRate([
              'shipping_method_id' => $this->parentEntity->id(),
              'service' => $this->services[$service_name],
              'amount' => Price::fromArray($price),
            ]);
          }
        }
      }
    }

    return $rates;
  }

  /**
   * @inheritDoc
   */
  public function calculateRates(ShipmentInterface $shipment): array {

    $order = NULL;
    if ($shipment->getOrderId()) {
      /** @var Order $order */
      $order = Order::load($shipment->getOrderId());
    }

    $rates = [];

    if ($shipment->getShippingProfile()->get('address')->isEmpty() || !$order) {
      return $rates;
    }

    /** @var Profile $profile */
    $profile = $shipment->getShippingProfile();

    $config = $this->getConfiguration();

    $currency = 'EUR';
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('commerce_currency_resolver')) {
      $currency = \Drupal::service('commerce_currency_resolver.current_currency')->getCurrency();
    }


    if ($config['mode'] == 'test') {
      $price = ['number' => 5, 'currency_code' => $currency];
      $this->services['sendcloud_test'] = new ShippingService(self::TEST_SHIPPING_METHOD, 'Servicio de prueba');
      $rates[] = new ShippingRate([
        'shipping_method_id' => $this->parentEntity->id(),
        'service' => $this->services['sendcloud_test'],
        'amount' => Price::fromArray($price),
      ]);
    }
    else {
      $rates = $this->getRates($order, $profile);
    }

    //$rates += $this->getRates($order, $profile);

    return $rates;
  }

}
