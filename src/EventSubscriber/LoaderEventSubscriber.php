<?php

namespace Drupal\helperbox\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Event subscriber
 */
class LoaderEventSubscriber implements EventSubscriberInterface {

  /**
   * React to kernel request to preload.
   */
  public function onKernelRequest(RequestEvent $event) {
    // Load PHPMailer manually if it's not already loaded.
    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
      $base_path = \Drupal::root() . '/libraries/phpmailer/src/';
      if (file_exists($base_path . 'PHPMailer.php')) {
        require_once $base_path . 'Exception.php';
        require_once $base_path . 'PHPMailer.php';
        require_once $base_path . 'SMTP.php';
      }
    }
    // Load ReCaptcha via its autoloader if available and it's not already loaded.
    if (!class_exists('\ReCaptcha\ReCaptcha')) {
      $recaptcha_autoload = \Drupal::root() . '/libraries/google/recaptcha/src/autoload.php';
      if (file_exists($recaptcha_autoload)) {
        require_once $recaptcha_autoload;
      }
    }
    // 
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 50],
    ];
  }
}
