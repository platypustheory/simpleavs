<?php

declare(strict_types=1);

namespace Drupal\simpleavs\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Manages SimpleAVS session flags for pass state and frequency window.
 *
 * Stores:
 * - simpleavs_passed: TRUE when the user has passed the gate this period.
 * - simpleavs_last_date: A string used to determine the next eligible prompt.
 *   For daily it is "Y-m-d", for weekly it is "o-W" (ISO week-numbering year
 *   and week). This lets the front-end decide whether to show the prompt.
 */
final class SessionManager {

  /**
   * Constructs a SessionManager service.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Marks the session as having passed and records the current frequency key.
   *
   * For "daily" the key is the current day (Y-m-d).
   * For "weekly" the key is the ISO year-week (o-W).
   * For "session" the flag alone is sufficient; the key is still stored but
   * not used by the front-end logic.
   */
  public function markPassed(): void {
    $req = $this->requestStack->getCurrentRequest();
    $session = $req->getSession();
    $session->set('simpleavs_passed', TRUE);

    $config = $this->configFactory->get('simpleavs.settings');
    $freq = (string) $config->get('frequency');
    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $session->set(
      'simpleavs_last_date',
      $freq === 'weekly' ? $now->format('o-W') : $now->format('Y-m-d')
    );
  }

  /**
   * Clears all SimpleAVS markers from the current session.
   */
  public function reset(): void {
    $req = $this->requestStack->getCurrentRequest();
    $session = $req->getSession();
    $session->remove('simpleavs_passed');
    $session->remove('simpleavs_last_date');
  }

}
