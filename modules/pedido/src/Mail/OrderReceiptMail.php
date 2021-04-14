<?php

namespace Drupal\pedido\Mail;

use Drupal\commerce\MailHandlerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderTotalSummaryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\email\Entity\Mail;
use Drupal\pedido\Controller\OrderController;
use Drupal\profile\Entity\Profile;
use Drupal\commerce_order\Mail\OrderReceiptMailInterface;

class OrderReceiptMail implements OrderReceiptMailInterface {

  use StringTranslationTrait;

  /**
   * The mail handler.
   *
   * @var \Drupal\commerce\MailHandlerInterface
   */
  protected $mailHandler;

  /**
   * The order total summary.
   *
   * @var \Drupal\commerce_order\OrderTotalSummaryInterface
   */
  protected $orderTotalSummary;

  /**
   * The profile view builder.
   *
   * @var \Drupal\profile\ProfileViewBuilder
   */
  protected $profileViewBuilder;

  /**
   * Constructs a new OrderReceiptMail object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce\MailHandlerInterface $mail_handler
   *   The mail handler.
   * @param \Drupal\commerce_order\OrderTotalSummaryInterface $order_total_summary
   *   The order total summary.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MailHandlerInterface $mail_handler, OrderTotalSummaryInterface $order_total_summary) {
    $this->mailHandler = $mail_handler;
    $this->orderTotalSummary = $order_total_summary;
    $this->profileViewBuilder = $entity_type_manager->getViewBuilder('profile');
  }

  /**
   * {@inheritdoc}
   */
  public function send(OrderInterface $order, $to = NULL, $bcc = NULL) {
    $to = isset($to) ? $to : $order->getEmail();
    if (!$to) {
      // The email should not be empty.
      return FALSE;
    }

    $profiles = $order->collectProfiles();

    $resumen = [
      '#theme' => 'correo_resumen_pedido',
      '#order_entity' => $order,
    ];

    $resumen = \Drupal::service('renderer')->render($resumen);

    $envio = '';
    if (isset($profiles['shipping']) && $profiles['shipping'] instanceof Profile) {
      $envio = [
        '#theme' => 'correo_informacion_envio',
        '#profile' => $profiles['shipping'],
      ];
      $envio = \Drupal::service('renderer')->render($envio);
    }



    // Correo del cliente
    $mail = Mail::load(Mail::TYPE_CONFIRM_ORDER);

    if ($mail instanceof Mail) {
      $subject = $mail->getSubject();
      $body = $mail->getBody();

      $token_service = \Drupal::token();
      $subject = $token_service->replace($subject, [
        'commerce_order' => $order
      ]);
      $body = $token_service->replace($body, [
        'commerce_order' => $order
      ]);

      $body = str_replace('[resumen]', $resumen, $body);
      $body = str_replace('[datos_envio]', $envio, $body);

      $params = [
        'from' => $order->getStore()->getEmail(),
        'subject' => $subject,
        'body' => ['#markup' => Markup::create($body)],
      ];

      \Drupal::logger('correo')->info('Pedido #' . $order->id() . ' completado enviado a ' . $to);
      return $this->mailHandler->sendMail($to, $subject, ['#markup' => Markup::create($body)], $params);
    }


    return FALSE;

  }

}
