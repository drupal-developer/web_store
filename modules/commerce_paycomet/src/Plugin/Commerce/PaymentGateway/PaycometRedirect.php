<?php


namespace Drupal\commerce_paycomet\Plugin\Commerce\PaymentGateway;


use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\commerce_paycomet\PaycometBankstore;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Manual payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "paycomet_redirect",
 *   label = @Translation("Paycoment Redirect"),
 *   display_label = @Translation("Credit Cart"),
 *   payment_method_types = {"credit_card"},
 *   modes = {
 *     "n/a" = @Translation("N/A"),
 *   },
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_paycomet\PluginForm\PaycometRedirectCheckoutForm",
 *   }
 * )
 */

class PaycometRedirect extends OffsitePaymentGatewayBase {


  private \Drupal\Core\Logger\LoggerChannelInterface $logger;

  /**
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  private LockBackendInterface $lock;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, MinorUnitsConverterInterface $minor_units_converter = NULL, LoggerChannelFactoryInterface $loggerChannelFactory, LockBackendInterface $lockBackend) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time, $minor_units_converter);
    $this->logger = $loggerChannelFactory->get('commerce_paycomet');
    $this->lock = $lockBackend;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('commerce_price.minor_units_converter'),
      $container->get('logger.factory'),
      $container->get('lock')
    );
  }


  public function defaultConfiguration(): array {
    return [
        'client_code' => '',
        'terminal' => '',
        'key' => '',
        'jet_id' => '',
        'currency' => 'EUR'
      ] + parent::defaultConfiguration();
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['client_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Code'),
      '#default_value' => $this->configuration['client_code'],
      '#required' => TRUE,
    ];

    $form['terminal'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Terminal'),
      '#default_value' => $this->configuration['terminal'],
      '#required' => TRUE,
    ];

    $form['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Password'),
      '#default_value' => $this->configuration['key'],
      '#required' => TRUE,
    ];

    $form['jet_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JET ID'),
      '#default_value' => $this->configuration['jet_id'],
      '#required' => TRUE,
    ];

    $form['currency'] = [
      '#type' => 'select',
      '#title' => $this->t('Currency'),
      '#options' => ['EUR' => 'EURO'],
      '#default_value' => $this->configuration['currency'],
      '#required' => TRUE,
    ];

    return $form;
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    $this->configuration['client_code'] = $values['client_code'];
    $this->configuration['terminal'] = $values['terminal'];
    $this->configuration['key'] = $values['key'];
    $this->configuration['jet_id'] = $values['jet_id'];
    $this->configuration['currency'] = $values['currency'];
  }

  /**
   * Url: /payment/notify/{commerce_payment_gateway}
   */
  public function onNotify(Request $request) {
    try {
      $this->processRequest($request);
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * Process notification.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\commerce_order\Entity\OrderInterface|null $order
   *   The order.
   *
   * @return bool
   *   TRUE if the payment is valid, otherwise FALSE.
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function processRequest(Request $request, OrderInterface $order = NULL): bool {

    $feedback = [
      'Order' => $request->get('Order'),
      'Response' => $request->get('Response'),
      'TransactionType' => $request->get('TransactionType'),
      'TokenUser' => $request->get('TokenUser'),
      'IdUser' => $request->get('IdUser'),
      'AmountEur' => $request->get('AmountEur'),
      'Currency' => $request->get('Currency'),
    ];



    if (empty($feedback['Order']) || empty($feedback['Response'])) {
      throw new PaymentGatewayException('Bad feedback response, missing feedback parameter.');
    }

    if ($order === NULL) {
      $array = explode('_', $feedback['Order']);
      if (!empty($array[1])) {
        $order_id = (int) $array[1];
        $order_storage = NULL;
        try {
          $order_storage = $this->entityTypeManager->getStorage('commerce_order');
        }
        catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
          $this->logger->error($e->getMessage());
        }

        if ($order_storage) {
          $order = $order_storage->load($order_id);
        }
      }
    }

    if (($order instanceof OrderInterface) && $this->lock->acquire($this->getLockName($order))) {


      $payment_storage = NULL;
      try {
        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
      }
      catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
        $this->logger->error($e->getMessage());
      }

      if ($payment_storage) {
        $payments = $payment_storage->getQuery()
          ->condition('payment_gateway', $this->parentEntity->id())
          ->condition('order_id', $order->id())
          ->condition('remote_id', $feedback['Order'])
          ->execute();

        if (empty($payments)) {
          $price = new Price($feedback['AmountEur'], $feedback['Currency']);
          $state = $feedback['Response'] === 'OK' ? 'completed' : 'authorization';
          /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
          $payment = $payment_storage->create([
            'state' => $state,
            'amount' => $price,
            'payment_gateway' => $this->parentEntity->id(),
            'order_id' => $order->id(),
            'remote_id' => $feedback['Order'],
            'remote_state' => $feedback['Response'],
            'authorized' => $this->time->getRequestTime(),
          ]);
          $payment->save();

          if ($feedback['Response'] === 'OK') {
            $order->setTotalPaid($price);
            $order->save();
          }

        }
      }

      if ($feedback['Response'] === 'OK' && !empty($feedback['TokenUser']) && !empty($feedback['IdUser'])) {
        $remote_id = $feedback['IdUser'] . '_' . $feedback['TokenUser'];
        $payment_method_storage = NULL;
        try {
          $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
        }
        catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
          $this->logger->error($e->getMessage());
        }

        if ($payment_method_storage) {
          $payment_method = $payment_method_storage->getQuery()
            ->condition('uid', $order->getCustomerId())
            ->condition('remote_id', $remote_id)
            ->execute();

          if (empty($payment_method)) {
            $config = $this->getConfiguration();
            $paycomet = new PaycometBankstore($config['client_code'], $config['terminal'], $config['key'], $config['jet_id']);
            $info_user = $paycomet->InfoUser($feedback['IdUser'], $feedback['TokenUser']);


            if (isset($info_user->DS_MERCHANT_PAN)) {
              $expire = $info_user->DS_EXPIRYDATE;
              $expire = explode('/', $expire);
              [$year, $month] = $expire;

              $expires = CreditCard::calculateExpirationTimestamp($month, $year);
              $payment_method = PaymentMethod::create([
                'type' => 'credit_card',
                'payment_gateway' => $this->parentEntity->id(),
                'payment_gateway_mode' => $config['mode'],
                'uid' => $order->getCustomerId(),
                'remote_id' => $remote_id,
                'expires' => $expires,
              ]);

              $payment_method->card_type = strtolower($info_user->DS_CARD_BRAND);
              $payment_method->card_number = $info_user->DS_MERCHANT_PAN;
              $payment_method->card_exp_month = $month;
              $payment_method->card_exp_year = $year;

              $payment_method->save();
            }
          }
        }
      }


      $this->lock->release($this->getLockName($order));
      return TRUE;
    }

    $this->lock->release($this->getLockName($order));

    throw new PaymentGatewayException('Failed attempt, the payment could not be made.');
  }

  /**
   * Returns the lock name.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return string
   *   The built lock name.
   */
  protected function getLockName(OrderInterface $order): string {
    return 'commerce_paycoment_process_request_' . $order->uuid();
  }


}
