<?php


namespace Drupal\commerce_sendcloud\Controller;


use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_sendcloud\CommerceSenCloud;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SendCloundController extends ControllerBase {

  /**
   * @var \Drupal\commerce_sendcloud\CommerceSenCloud
   */
  protected CommerceSenCloud $commerceSenCloud;

  protected \Drupal\Core\Logger\LoggerChannelInterface $logger;


  /**
   * SendCloundController constructor.
   *
   * @param \Drupal\commerce_sendcloud\CommerceSenCloud $commerceSenCloud
   */
  public function __construct(CommerceSenCloud $commerceSenCloud) {
    $this->commerceSenCloud = $commerceSenCloud;
  }

  /**
   * @inheritDoc
   */
  public static function create(ContainerInterface $container): SendCloundController {
    return new static(
      $container->get('commerce_sendcloud.shipment')
    );
  }

  /**
   * Url para localizar el pedido.
   *
   * @param $tracking_number
   *
   * @return array
   */
  public function tracking($tracking_number) {
    $url = NULL;
    $shipment = NULL;
    try {
      $shipment = $this->entityTypeManager()
        ->getStorage('commerce_shipment')
        ->loadByProperties(['tracking_code' => $tracking_number]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->commerceSenCloud->getLogger()->error($e->getMessage());
    }

    if ($shipment) {
      $shipment = reset($shipment);
      if ($shipment instanceof Shipment) {
        $order = $shipment->getOrder();
        if ($order instanceof Order) {
          $url = $this->commerceSenCloud->getTrackingUrl($order);
        }
      }
    }

    return [
      '#theme' => 'sendcloud_tracking',
      '#url' => $url,
    ];
  }

  public function devolution() {
    return [
      '#theme' => 'sendcloud_devolution',
      '#url' => 'https://track-gear.shipping-portal.com/rp/reasons',
    ];
  }

}
