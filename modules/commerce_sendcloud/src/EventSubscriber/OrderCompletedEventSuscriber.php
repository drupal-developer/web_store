<?php


namespace Drupal\commerce_sendcloud\EventSubscriber;

use Drupal\commerce_sendcloud\Api\SendCloudApi;
use Drupal\commerce_sendcloud\CommerceSenCloud;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Picqer\Carriers\SendCloud\Connection;
use Picqer\Carriers\SendCloud\SendCloud;
use Picqer\Carriers\SendCloud\SendCloudApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderCompletedEventSuscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannel $logger;

  /**
   * @var \Drupal\commerce_sendcloud\CommerceSenCloud
   */
  protected CommerceSenCloud $commerceSenCloud;

  public function __construct(CommerceSenCloud $commerceSenCloud, LoggerChannel $loggerChannel) {
    $this->commerceSenCloud = $commerceSenCloud;
    $this->logger = $loggerChannel;
  }


  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents() {
    return ['commerce_order.place.post_transition' => ['sendOrder', 201]];
  }



  /**
   * Enviar pedido a SendCloud.
   *
   * @param WorkflowTransitionEvent $event
   */
  public function sendOrder(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $event->getEntity();
    $profiles = $order->collectProfiles();
    $sendcloudClient = NULL;
    try {
      if ($config = $this->commerceSenCloud->getConfig()) {
        $connection = new Connection($config['public_key'], $config['secret_key']);
        $sendcloudClient = new SendCloud($connection);
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException | SendCloudApiException $e) {
      $this->logger->error($e->getMessage());
    }

    if ($sendcloudClient  && $order->get('shipments')->target_id && isset($profiles['shipping'])) {
      $shipment = Shipment::load($order->get('shipments')->target_id);
      $profile = $profiles['shipping'];

      $datos = $profile->toArray();
      $address = $datos['address'][0];

      if ($shipment instanceof Shipment) {
        $parcel = $sendcloudClient->parcels();
        $parcel->shipment = (int) $shipment->getShippingService();
        $parcel->name = $address['given_name'] . ' ' . $address['family_name'];
        $parcel->company_name = $address['organization'];
        $parcel->address = $address['address_line1'] . ' ' . $address['address_line2'];
        $parcel->city = $address['locality'];
        $parcel->postal_code = $address['postal_code'];
        $parcel->country = $address['country_code'];
        $parcel->order_number = $order->getOrderNumber();
        if (isset($datos['field_telefono'][0]['value'])) {
          $parcel->telephone = $datos['field_telefono'][0]['value'];
        }
        $parcel->requestShipment = true;

        try {
          $parcel->save();
        }
        catch (SendCloudApiException $e) {
          $this->logger->error($e->getMessage());
        }

        if ($parcel->tracking_number) {
          $this->logger->info('Pedido #' . $order->getOrderNumber() . ' enviado Tracking N:' . $parcel->tracking_number);
          $shipment->set('tracking_code', $parcel->tracking_number);
          $shipment->setTitle($parcel->shipment['name']);
          $shipment->set('state', 'ready');

          try {
            $shipment->save();
          }
          catch (EntityStorageException $e) {
            $this->logger->error($e->getMessage());
          }
        }
      }
    }
  }
}
