<?php


namespace Drupal\stock\Commands;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\email\Entity\Mail;
use Drupal\stock\AlertaManager;
use Drupal\stock\Entity\Alerta;
use Drush\Commands\DrushCommands;

class AlertCommand extends DrushCommands {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManger;

  /**
   * @var \Drupal\stock\AlertaManager
   */
  protected AlertaManager $alertaManager;

  /**
   * AlertCommand constructor.
   *
   * @param \Drupal\Core\Logger\LoggerChannel $loggerChannel
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   * @param \Drupal\stock\AlertaManager $alertaManager
   */
  public function __construct(LoggerChannel $loggerChannel, EntityTypeManager $entityTypeManager, AlertaManager $alertaManager) {
    parent::__construct();
    $this->logger = $loggerChannel;
    $this->entityTypeManger = $entityTypeManager;
    $this->alertaManager = $alertaManager;

  }

  /**
   * Enviar alertas de stock.
   *
   * @command stock:alert
   * @aliases st-al
   */
  public function sendAlerts() {

    // Primer aviso
    $alertas = NULL;
    try {
      $alertas = $this->entityTypeManger->getStorage('alerta')
        ->loadByProperties(['estado' => Alerta::ESTADO_PENDIENTE]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error($e->getMessage());
    }

    if ($alertas) {
      foreach ($alertas as $alerta) {
        if ($alerta instanceof Alerta) {
          $producto = $alerta->getProducto();
          if ($producto instanceof ProductVariation) {
            if ($producto->get('field_stock')->value) {
              $this->alertaManager->sendMail($alerta, Mail::TYPE_STOCK_ALERT);
            }
          }
        }
      }
    }

    //Segundo aviso
    $alertas = NULL;
    try {

      $alertas = $this->entityTypeManger->getStorage('alerta')
        ->loadByProperties(['estado' => Alerta::ESTADO_ENVIADA]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error($e->getMessage());
    }

    if ($alertas) {
      foreach ($alertas as $alerta) {
        if ($alerta instanceof Alerta) {
          $fecha = strtotime('-15 days');
          if ($alerta->get('changed')->value < $fecha) {
            $producto = $alerta->getProducto();
            if ($producto instanceof ProductVariation) {
              if ($producto->get('field_stock')->value) {
                $this->alertaManager->sendMail($alerta, Mail::TYPE_STOCK_ALERT_COMPLETE);
              }
            }
          }
        }
      }
    }
  }

}
