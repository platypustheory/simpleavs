<?php

declare(strict_types=1);

namespace Drupal\simpleavs\Session;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Session and one-time-token manager for SimpleAVS.
 */
class AvsSessionManager {

  /**
   * Session key for AVS state: 'passed' | 'denied'.
   *
   * @var string
   */
  public const KEY_STATE = 'simpleavs.state';

  /**
   * Session key that holds issued one-time tokens.
   *
   * The value is an associative array of token strings mapped to TRUE.
   *
   * @var string
   */
  public const KEY_TOKENS = 'simpleavs.tokens';

  /**
   * Request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Session service.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected SessionInterface $session;

  /**
   * Logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Constructs the session manager.
   */
  public function __construct(
    RequestStack $request_stack,
    SessionInterface $session,
    LoggerInterface $logger,
  ) {
    $this->requestStack = $request_stack;
    $this->session = $session;
    $this->logger = $logger;
  }

  /**
   * Issues a one-time token bound to this session.
   */
  public function issueToken(): string {
    $token = bin2hex(random_bytes(16));
    $tokens = $this->session->get(self::KEY_TOKENS, []);
    $tokens[$token] = TRUE;
    $this->session->set(self::KEY_TOKENS, $tokens);
    // Ensure persistence.
    $this->session->save();
    return $token;
  }

  /**
   * Consumes and invalidates a token.
   *
   * @return bool
   *   TRUE if the token existed and was removed, FALSE otherwise.
   */
  public function consumeToken(string $token): bool {
    $tokens = $this->session->get(self::KEY_TOKENS, []);
    if (!isset($tokens[$token])) {
      return FALSE;
    }
    unset($tokens[$token]);
    $this->session->set(self::KEY_TOKENS, $tokens);
    $this->session->save();
    return TRUE;
  }

  /**
   * Marks this session as AVS passed.
   */
  public function setPassed(): void {
    $this->session->set(self::KEY_STATE, 'passed');
    $this->session->save();
  }

  /**
   * Marks this session as AVS denied.
   */
  public function setDenied(): void {
    $this->session->set(self::KEY_STATE, 'denied');
    $this->session->save();
  }

  /**
   * Checks whether this session already passed AVS.
   */
  public function isPassed(): bool {
    return $this->session->get(self::KEY_STATE) === 'passed';
  }

  /**
   * Clears all AVS data from this session.
   */
  public function clear(): void {
    $this->session->remove(self::KEY_STATE);
    $this->session->remove(self::KEY_TOKENS);
    $this->session->save();
  }

}
