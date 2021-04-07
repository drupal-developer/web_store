<?php


namespace Drupal\stock;


use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Drupal\email\Entity\Mail;
use Drupal\stock\Entity\Alerta;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;

class AlertaManager {

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $account;

  /**
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannel $logger;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManger;

  /**
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  protected ?\Symfony\Component\HttpFoundation\Request $request;

  /**
   * AlertaManager constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   * @param \Drupal\Core\Logger\LoggerChannel $loggerChannel
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   * @param \Drupal\Core\Utility\Token $token
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   */
  public function __construct(AccountProxyInterface $account, LoggerChannel $loggerChannel, EntityTypeManager $entityTypeManager, MailManagerInterface $mail_manager, Token $token, LanguageManagerInterface $language_manager) {
    $this->account = $account;
    $this->logger = $loggerChannel;
    $this->entityTypeManger = $entityTypeManager;
    $this->mailManager = $mail_manager;
    $this->token = $token;
    $this->languageManager = $language_manager;
  }

  /**
   * Enviar correo de aviso de disponibilidad.
   *
   * @param \Drupal\stock\Entity\Alerta $alerta
   * @param $tipo
   *  Tipo de email
   */
  public function sendMail(Alerta $alerta, $tipo) {
    $langcode = $this->languageManager->getDefaultLanguage()->getId();
    $mail = Mail::load($tipo);
    if ($mail instanceof Mail) {
      $subject = $mail->getSubject();
      $body = $mail->getBody();
      $token_service = $this->token;
      $subject = $token_service->replace($subject, [
        'alerta' => $alerta
      ]);

      $base_url = \Drupal::state()->get('base_url', NULL);
      if ($base_url) {
        $body = str_replace('[alerta:producto:entity:product_id:entity:url]', $base_url . '[alerta:producto:entity:product_id:entity:url]', $body);
      }

      $body = $token_service->replace($body, [
        'alerta' => $alerta
      ]);

      $body = str_replace('[alerta:usuario:entity:field_nombre]', '', $body);
      $body = str_replace('[alerta:email]', '', $body);



      $subject = str_replace('[alerta:usuario:entity:field_nombre]', '', $subject);
      $subject = str_replace('[alerta:email]', '', $subject);


      $email = $alerta->get('email')->value;
      if ($alerta->get('usuario')->target_id) {
        $usuario = User::load($alerta->get('usuario')->target_id);
        if ($usuario instanceof User) {
          $email = $usuario->getEmail();
        }
      }

      if ($email) {
        $params = [
          'from' => $email,
          'subject' => $subject,
          'body' => ['#markup' => Markup::create($body)],
        ];

        $this->mailManager->mail('commerce', 'stock_alert', $email, $langcode, $params);
        $estado = $tipo == Mail::TYPE_STOCK_ALERT ? Alerta::ESTADO_ENVIADA : Alerta::ESTADO_COMPLETADA;
        $alerta->set('estado', $estado);
        try {
          $alerta->save();
          $this->logger->info('Aviso de disponibilidad enviado a ' . $email);
        }
        catch (EntityStorageException $e) {
          $this->logger->error($e->getMessage());
        }
      }
    }
  }

}
