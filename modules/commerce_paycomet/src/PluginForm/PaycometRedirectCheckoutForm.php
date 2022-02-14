<?php

namespace Drupal\commerce_paycomet\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PaycometRedirectCheckoutForm extends BasePaymentOffsiteForm implements ContainerInjectionInterface {

  private string $language;

  public function __construct(LanguageManager $languageManager) {
    $this->language = $languageManager->getCurrentLanguage()->getId();
  }


  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('language_manager')
    );
  }


  /**
   * {@inheritdoc}
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = $payment->getOrder();

    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

    $gateway_settings = $payment_gateway_plugin->getConfiguration();
    $amount = $payment->getAmount()->multiply(100)->getNumber();
    $ds_oder =  time() . '_' . $order->id();
    $language = strtoupper($this->language);


    $operation = '1';
    $signature = hash('sha512', $gateway_settings['client_code'] . $gateway_settings['terminal'] . $operation . $ds_oder . $amount . $gateway_settings['currency'] . md5($gateway_settings['key']));
    $data = [
      'MERCHANT_MERCHANTCODE' => $gateway_settings['client_code'],
      'MERCHANT_TERMINAL' => $gateway_settings['terminal'],
      'OPERATION' => $operation,
      'LANGUAGE' => $language,
      'MERCHANT_MERCHANTSIGNATURE' => $signature,
      'MERCHANT_ORDER' => $ds_oder,
      'MERCHANT_AMOUNT' => $amount,
      'MERCHANT_CURRENCY' => $gateway_settings['currency'],
      'MERCHANT_PRODUCTDESCRIPTION' => 'Pedido #' .  $order->id(),
      '3DSECURE' => '1'
    ];

    $amount = (float) $amount;
    if ($amount < 3000) {
      $data['MERCHANT_SCA_EXCEPTION'] = 'LWV';
    }


    $data['URLOK'] = $form['#return_url'];
    $data['URLKO'] = $form['#cancel_url'];

    return $this->buildRedirectForm($form, $form_state, $gateway_settings['url_iframe'], $data, BasePaymentOffsiteForm::REDIRECT_GET);
  }

}
