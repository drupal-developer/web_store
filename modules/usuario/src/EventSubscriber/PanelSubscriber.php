<?php


namespace Drupal\usuario\EventSubscriber;


use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;

class PanelSubscriber implements EventSubscriberInterface{

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


  public function __construct(AccountProxyInterface $account,  RequestStack $request_stack) {
    $this->account = $account;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * Redirigir al panel de usuario.
   */
  public function onRequestCheckUserPage() {

    $url_object = \Drupal::service('path.validator')->getUrlIfValid($this->request->getRequestUri());
    if ($url_object instanceof Url) {
      $route_name = $url_object->getRouteName();
      $parameters  = $url_object->getRouteParameters();
      if ($route_name == 'entity.user.canonical' && $parameters['user'] == \Drupal::currentUser()->id()) {
        $response = new RedirectResponse('/panel');
        $response->send();
        exit;
      }
    }
  }

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequestCheckUserPage', 1];
    return $events;
  }

}
