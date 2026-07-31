<?php

namespace Drupal\helperbox\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\helperbox\Helper\HelperboxSettings;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Blocks access to /user/login and /user/password paths.
 */
class LoginBlockSubscriber implements EventSubscriberInterface {

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new LoginBlockSubscriber.
   *
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(AliasManagerInterface $alias_manager, AccountProxyInterface $current_user) {
    $this->aliasManager = $alias_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run later in the request cycle when session is initialized.
    $events[KernelEvents::REQUEST][] = ['onRequest', 100];
    return $events;
  }

  /**
   * Checks if the request is for the login page and blocks it.
   */
  public function onRequest(RequestEvent $event) {
    // Only act on main requests, not subrequests.
    if ($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
      return;
    }

    // Check if the feature is enabled in configuration.
    if (!HelperboxSettings::get_instance()->get_config('enable_only_alies_login_url')) {
      return;
    }

    // Bail out early if no alias exists for /user/login.
    $login_alias = $this->aliasManager->getAliasByPath('/user/login');
    if ($login_alias === '/user/login') {
      return;
    }

    $request = $event->getRequest();

    // Get the clean path, stripping query string and leading slash.
    // getPathInfo() handles subdirectories automatically.
    // e.g. /sub-dir/user/login → /user/login
    $rawPath = $request->getPathInfo();
    $rawPath = trim($rawPath, '/');
    if (empty($rawPath)) {
      return;
    }

    // Block /user for anonymous users.
    if ($rawPath === 'user' && $this->currentUser->isAnonymous()) {
      throw new NotFoundHttpException();
    }

    // Block direct access to /user/login and /user/password when an alias exists.
    $protectedPaths = ['user/login', 'user/password'];
    if (in_array($rawPath, $protectedPaths, TRUE)) {
      $alias = $this->aliasManager->getAliasByPath('/' . $rawPath);
      // If an alias exists (different from the system path), block the system path.
      if ($alias !== '/' . $rawPath) {
        throw new NotFoundHttpException();
      }
    }
  }
}
