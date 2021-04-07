<?php

namespace  Drupal\commerce_sendcloud;

use Drupal\commerce_order\Entity\Order;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannel;
use Picqer\Carriers\SendCloud\Connection;
use Picqer\Carriers\SendCloud\Parcel;
use Picqer\Carriers\SendCloud\SendCloud;
use Picqer\Carriers\SendCloud\SendCloudApiException;

class CommerceSenCloud {

  /**
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannel $logger;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManager;

  public function __construct(EntityTypeManager $entityTypeManager, LoggerChannel $loggerChannel) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerChannel;
  }

  public function getLogger(): LoggerChannel {
    return $this->logger;
  }

  /**
   * Obtener configuración.
   *
   * @return array|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getConfig(): ?array {
    $config = NULL;
    $shipping_method_storage = $this->entityTypeManager->getStorage('commerce_shipping_method');
    /** @var \Drupal\commerce_shipping\Entity\ShippingMethodInterface[] $shipping_methods */
    $shipping_methods = $shipping_method_storage->loadMultiple();
    foreach ($shipping_methods as $method) {
      $plugin = $method->getPlugin();
      if ($plugin->getPluginId() == 'commerce_sendcloud') {
        $config = $plugin->getConfiguration();
      }
    }
    return $config;
  }

  /**
   * Obtener parcela del pedido.
   *
   * @param \Drupal\commerce_order\Entity\Order $order
   *
   * @return \Picqer\Carriers\SendCloud\Parcel|null
   */
  public function getParcelOrder(Order $order): ?Parcel {
    $sendcloudClient = NULL;
    try {
      if ($config = $this->getConfig()) {
        $connection = new Connection($config['public_key'], $config['secret_key']);
        $sendcloudClient = new SendCloud($connection);
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | SendCloudApiException $e) {
      $this->logger->error($e->getMessage());
    }
    $parcel = NULL;
    if ($sendcloudClient) {
      $parcels = $sendcloudClient->parcels();
      $parcels = $parcels->all(['order_number' => $order->getOrderNumber()]);
      if (!empty($parcels)) {
        /** @var \Picqer\Carriers\SendCloud\Parcel $parcel */
        $parcel = current($parcels);
      }
    }
    return $parcel;
  }

  /**
   * Obtener tracking url.
   *
   * @param \Drupal\commerce_order\Entity\Order $order
   *
   * @return string|null
   */
  public function getTrackingUrl(Order $order): ?string {
    $tracking_url = NULL;
    $parcel = $this->getParcelOrder($order);
    $config = NULL;
    try {
      $config = $this->getConfig();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error($e->getMessage());
    }
    if ($parcel instanceof Parcel) {
      if (isset($config['tracking_url']) && $config['tracking_url'] != '') {
        $tracking_url = $config['tracking_url'] . '?country=' . strtolower($parcel->country['iso_2']) . '&tracking_number=' . $parcel->tracking_number . '&postal_code=' . $parcel->postal_code;
      }
      else {
        $tracking_url = $parcel->getTrackingUrl();
      }
    }

    return $tracking_url;
  }
}
