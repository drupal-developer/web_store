<?php

namespace Drupal\precio\Resolver;

use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Resolver\PriceResolverInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannel;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Serializer;

/**
 * Returns a price and currency depending of language or country.
 */
class CommerceCurrencyResolver implements PriceResolverInterface {

  /**
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannel $logger;

  /**
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * @var array|mixed|null
   */
  protected mixed $api_key;

  protected ?\Symfony\Component\HttpFoundation\Request $request;

  /**
   * @var \Drupal\Component\Serialization\Json
   */
  protected Json $json;

  /**
   * @var \Drupal\Component\Serialization\PhpSerialize
   */
  protected PhpSerialize $serializer;

  /**
   * @var array|mixed|null
   */
  protected mixed $enabled;

  /**
   * @var array|mixed|null
   */
  protected mixed $currency;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManager;


  /**
   * Constructs a new CommerceCurrencyResolver object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   * @param \Drupal\Core\Logger\LoggerChannel $loggerChannel
   * @param \GuzzleHttp\Client $client
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param \Drupal\Component\Serialization\PhpSerialize $serializer
   * @param \Drupal\Component\Serialization\Json $json
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   */
  public function __construct(ConfigFactory $configFactory, LoggerChannel $loggerChannel, Client $client, RequestStack $requestStack, PhpSerialize  $serializer, Json $json, EntityTypeManager $entityTypeManager) {

    $config = $configFactory->get('precio.config');
    $this->api_key = $config->get('exchange_rate_api_key');
    $this->enabled = $config->get('exchange_rate_enabled');
    $this->currency = $config->get('exchange_rate_currency');
    $this->logger = $loggerChannel;
    $this->client = $client;
    $this->request = $requestStack->getCurrentRequest();
    $this->serializer = $serializer;
    $this->json = $json;
    $this->entityTypeManager = $entityTypeManager;
  }


  /**
   * Obtener datos de la IP.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function geoIp() {

    $ip = $this->request->getClientIp();
    $url = 'http://www.geoplugin.net/php.gp?ip=' . $ip;

    $session = $this->request->getSession();
    $sessionIP = $session->get('geoip');

    if (!$session->get('geoip') || (isset($sessionIP["geoplugin_request"]) && $sessionIP["geoplugin_request"] != $ip)) {
      $request = $this->client->request('GET', $url);
      if ($request) {
        $result = $request->getBody()->getContents();
        if ($result) {
          $sessionIP = $this->serializer->decode($result);
          $session->set('geoip', $sessionIP);
        }
      }
    }

    return $sessionIP;
  }

  /**
   * Obtener datos para la conversión.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getRate(): ?array {
    $datos = NULL;
    $geoip = $this->geoIp();
    $currencies = NULL;
    try {
      $currencies = $this->entityTypeManager->getStorage('commerce_currency')
        ->loadMultiple();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->logger->error($e->getMessage());
    }
    if (isset($geoip['geoplugin_currencyCode']) && $geoip['geoplugin_currencyCode'] != $this->currency) {
      if (isset($currencies[$geoip['geoplugin_currencyCode']])) {
        $currency_code_ip = $geoip['geoplugin_currencyCode'];
        $url = 'https://v6.exchangerate-api.com/v6/' . $this->api_key . '/latest/' . $this->currency;
        $request = $this->client->request('GET', $url);
        if ($request) {
          $result = $request->getBody()->getContents();
          if ($result) {
            $result = $this->json::decode($result);
            $rate = $result['conversion_rates'][$currency_code_ip] ?? 1;
            $datos = ['currency' => $currency_code_ip, 'rate' => $rate];
          }
        }
      }

    }

    return $datos;
  }

  /**
   * {@inheritdoc}
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {
    if ($this->enabled) {
      $price = $entity->getListPrice() ? $entity->getListPrice() : $entity->getPrice();
      $rate = $this->getRate();
      if ($rate) {
        $amount = $price->getNumber();
        $amount = $amount * $rate['rate'];
        $amount = round($amount,2);
        $resolve = new Price($amount, $rate['currency']);
        if ($resolve instanceof Price) {
          return $resolve;
        }
      }
    }
  }
}
