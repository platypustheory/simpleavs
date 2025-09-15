<?php

declare(strict_types=1);

namespace Drupal\simpleavs\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 *
 */
final class SessionManager {

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   *
   */
  public function markPassed(): void {
    $req = $this->requestStack->getCurrentRequest();
    $session = $req->getSession();
    $session->set('simpleavs_passed', TRUE);

    $config = $this->configFactory->get('simpleavs.settings');
    $freq = (string) $config->get('frequency');
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $session->set('simpleavs_last_date',
      $freq === 'weekly' ? $now->format('o-W') : $now->format('Y-m-d'));
  }

  /**
   *
   */
  public function reset(): void {
    $req = $this->requestStack->getCurrentRequest();
    $session = $req->getSession();
    $session->remove('simpleavs_passed');
    $session->remove('simpleavs_last_date');
  }

}
