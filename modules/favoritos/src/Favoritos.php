<?php


namespace Drupal\favoritos;


use Drupal\commerce_product\Entity\Product;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\favoritos\Entity\Favorito;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class Favoritos {

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $account;

  /**
   * The current request.
   *
   * @var ?\Symfony\Component\HttpFoundation\Request
   */
  protected ?\Symfony\Component\HttpFoundation\Request $request;

  /**
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannel $logger;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $entityTypeManger;

  /**
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $token;


  /**
   * Favoritos constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   * @param \Drupal\Core\Logger\LoggerChannel $loggerChannel
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   */
  public function __construct(AccountProxyInterface $account, RequestStack $request_stack, LoggerChannel $loggerChannel, EntityTypeManager $entityTypeManager, CsrfTokenGenerator $csrfTokenGenerator ) {
    $this->account = $account;
    $this->request = $request_stack->getCurrentRequest();
    $this->logger = $loggerChannel;
    $this->entityTypeManger = $entityTypeManager;
    $this->token = $csrfTokenGenerator;
  }

  /**
   * Comprobar si el producto ya esta añadido a favoritos.
   *
   * @param \Drupal\commerce_product\Entity\Product $product
   *
   * @return array|null
   */
  public function checkExistFavorito(Product $product): ?array {
    $favorito = NULL;
    if ($this->account->id()) {
      try {
        $favorito = $this->entityTypeManger->getStorage('favorito')
          ->loadByProperties([
            'usuario' => $this->account->id(),
            'producto' => $product->id(),
          ]);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $this->logger->error($e->getMessage());
      }
    }
    elseif (isset($_COOKIE['wishlist'])) {
      try {
        $favorito = $this->entityTypeManger->getStorage('favorito')
          ->loadByProperties([
            'cookie' => $_COOKIE['wishlist'],
            'producto' => $product->id(),
          ]);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $this->logger->error($e->getMessage());
      }
    }
    return $favorito;
  }

  /**
   * Crear cookie para favoritos.
   *
   * @param $token
   */
  private function createCookie($token) {
    $domain = $this->request->getHost();
    $cookie = new Cookie('wishlist', $token, time() + (3600 * 24 * 30), '/', $domain);
    $response = new Response();
    $response->headers->setCookie($cookie);
    $response->sendHeaders();
  }

  /**
   * Eliminar cookie favoritos.
   */
  public function deleteCookie() {
    if (isset($_COOKIE['wishlist'])) {
      $domain = $this->request->getHost();
      $cookie = new Cookie('wishlist', $_COOKIE['wishlist'], time() - 3600, '/', $domain);
      $response = new Response();
      $response->headers->setCookie($cookie);
      $response->sendHeaders();
    }

  }

  /**
   * Añadir producto a la lista de favoritos.
   *
   * @param \Drupal\commerce_product\Entity\Product $product
   *
   * @return bool
   */
  public function addFavorito(Product $product): bool {
    $result = FALSE;
    if (!$this->checkExistFavorito($product)) {
      $datos = [];
      $datos['producto'] = $product->id();
      if ($this->account->id()) {
        $datos['usuario'] = $this->account->id();
      }
      else {
        if (isset($_COOKIE['wishlist'])) {
          $cookie = $_COOKIE['wishlist'];
        }
        else {
          $cookie = $this->token->get();
          $this->createCookie($cookie);
        }
        $datos['cookie'] = $cookie;
      }

      $favorito = Favorito::create($datos);
      try {
        $favorito->save();
        $result = TRUE;
      }
      catch (EntityStorageException $e) {
        $this->logger->error($e->getMessage());
      }
    }

    return $result;
  }

  /**
   * Quitar producto de la lista de favoritos.
   *
   * @param \Drupal\commerce_product\Entity\Product $product
   *
   * @return bool
   */
  public function deleteFavorito(Product $product): bool {
    $result = FALSE;
    if ($product = $this->checkExistFavorito($product)) {
      $favorito = reset($product);
      if ($favorito instanceof Favorito) {
        try {
          $favorito->delete();
          $result = TRUE;
        }
        catch (EntityStorageException $e) {
          $this->logger->error($e->getMessage());
        }
      }
    }
    return $result;
  }

  /**
   * Asignar favoritos guardados por cookie al usuario.
   *
   * @param \Drupal\user\UserInterface $account
   */
  public function addUsuarioFavorito(UserInterface $account) {
    if (isset($_COOKIE['wishlist'])) {
      $cookie = $_COOKIE['wishlist'];
      $favoritosUsuario = NULL;
      $productos_ids = [];
      try {
        $favoritosUsuario = $this->entityTypeManger->getStorage('favorito')
          ->loadByProperties([
            'usuario' => $account->id()
          ]);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $this->logger->error($e->getMessage());
      }

      if ($favoritosUsuario) {
        foreach ($favoritosUsuario as $favorito) {
          if ($favorito instanceof Favorito) {
            $product_id = $favorito->get('producto')->target_id;
            $productos_ids[$product_id] = $product_id;
          }
        }
      }

      $favoritosCookie = NULL;
      try {
        $favoritosCookie = $this->entityTypeManger->getStorage('favorito')
          ->loadByProperties([
            'cookie' => $cookie
          ]);
      }
      catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $this->logger->error($e->getMessage());
      }

      if ($favoritosCookie) {
        foreach ($favoritosCookie as $id => $favorito) {
          if ($favorito instanceof Favorito) {
            $product_id = $favorito->get('producto')->target_id;
            if (!isset($productos_ids[$product_id])) {
              $favorito->set('usuario', $this->account->id());
              try {
                $favorito->save();
              }
              catch (EntityStorageException $e) {
                $this->logger->error($e->getMessage());
              }
            }
          }
        }
      }
      $this->deleteCookie();
    }
  }

}
