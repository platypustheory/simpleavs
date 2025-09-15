<?php

namespace Drupal\simpleavs\Session;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Session + token manager for SimpleAVS.
 */
class AvsSessionManager {

  // 'passed' | 'denied' | NULL
  public const KEY_STATE  = 'simpleavs.state';
  public const KEY_TOKENS = 'simpleavs.tokens';  /**
                                                  * Array<string,bool>.
                                                  */

  protected RequestStack $requestStack;
  protected SessionInterface $session;
  protected LoggerInterface $logger;

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
   * Issue a one-time token bound to this session.
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
   * Consume and invalidate a token. Returns TRUE if it existed.
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
   * Mark this session as AVS passed.
   */
  public function setPassed(): void {
    $this->session->set(self::KEY_STATE, 'passed');
    $this->session->save();
  }

  /**
   * Mark this session as AVS denied.
   */
  public function setDenied(): void {
    $this->session->set(self::KEY_STATE, 'denied');
    $this->session->save();
  }

  /**
   * True if this session has already passed.
   */
  public function isPassed(): bool {
    return $this->session->get(self::KEY_STATE) === 'passed';
  }

  /**
   * Clear all AVS data for this session.
   */
  public function clear(): void {
    $this->session->remove(self::KEY_STATE);
    $this->session->remove(self::KEY_TOKENS);
    $this->session->save();
  }

}
