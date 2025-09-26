<?php

declare(strict_types=1);

namespace Drupal\simpleavs\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\simpleavs\Session\AvsSessionManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * AJAX endpoints for SimpleAVS.
 *
 * Routes exposed:
 * - /simpleavs/token    (GET)   -> token()
 * - /simpleavs/verify   (POST)  -> verify()
 */
final class AgeGateController implements ContainerInjectionInterface {

  /**
   * Channel logger.
   */
  private LoggerInterface $logger;

  /**
   * Manages AVS session state and one-time tokens.
   */
  private AvsSessionManager $sessionManager;

  /**
   * Config factory, to read simpleavs.settings.
   */
  private ConfigFactoryInterface $configFactory;

  public function __construct(
    LoggerInterface $logger,
    AvsSessionManager $sessionManager,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->logger = $logger;
    $this->sessionManager = $sessionManager;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.simpleavs');
    /** @var \Drupal\simpleavs\Session\AvsSessionManager $manager */
    $manager = $container->get('simpleavs.session_manager');
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = $container->get('config.factory');

    return new self($logger, $manager, $config_factory);
  }

  /**
   * Returns a one-time token as JSON.
   */
  public function token(Request $request): JsonResponse {
    try {
      $token = $this->sessionManager->issueToken();
      // Intentionally do not log token values.
      return $this->json(['token' => $token], 200);
    }
    catch (\Throwable $e) {
      $this->logger->error('token() failed: {m} {f}:{l}', [
        'm' => $e->getMessage(),
        'f' => $e->getFile(),
        'l' => $e->getLine(),
      ]);
      return $this->jsonError('server error', 500);
    }
  }

  /**
   * Verifies an action: "yes", "no", or "dob".
   *
   * Accepts either `application/x-www-form-urlencoded` or `application/json`
   * with fields: `action=yes|no|dob`, `token=...`, and optional `dob`.
   */
  public function verify(Request $request): JsonResponse {
    try {
      // Accept either x-www-form-urlencoded or JSON payload.
      $action = NULL;
      $token = NULL;
      $dobRaw = NULL;

      $content_type = (string) $request->headers->get('Content-Type');
      if (strpos($content_type, 'application/json') === 0) {
        $data = json_decode((string) $request->getContent(), TRUE) ?: [];
        $action = (string) ($data['action'] ?? '');
        $token = (string) ($data['token'] ?? '');
        $dobRaw = isset($data['dob']) ? (string) $data['dob'] : NULL;
      }
      else {
        $action = (string) $request->request->get('action', '');
        $token = (string) $request->request->get('token', '');
        $dobRaw = $request->request->has('dob')
          ? (string) $request->request->get('dob')
          : NULL;
      }

      if ($token === '') {
        return $this->jsonError('Missing token.', 400);
      }

      // One-time token check.
      if (!$this->sessionManager->consumeToken($token)) {
        return $this->jsonError('Invalid token.', 400);
      }

      $cfg = $this->configFactory->get('simpleavs.settings');
      $method = (string) ($cfg->get('method') ?? 'question');
      $min_age = (int) ($cfg->get('min_age') ?? 18);

      // Only support 'mdy' or 'dmy'. Anything else falls back to 'mdy'.
      $date_format = (string) ($cfg->get('date_format') ?? 'mdy');
      if ($date_format !== 'dmy' && $date_format !== 'mdy') {
        $date_format = 'mdy';
      }

      $payload = ['ok' => TRUE];

      switch ($action) {
        case 'yes':
          $this->sessionManager->setPassed();
          $payload['result'] = 'passed';
          break;

        case 'no':
          $this->sessionManager->setDenied();
          $payload['result'] = 'denied';
          break;

        case 'dob':
          if ($method !== 'dob') {
            return $this->jsonError('Unsupported action.', 400);
          }
          if ($dobRaw === NULL || $dobRaw === '') {
            return $this->jsonError('DOB required.', 400);
          }
          $iso = $this->normalizeDob($dobRaw, $date_format);
          if ($iso === NULL) {
            return $this->jsonError('Invalid DOB format.', 400);
          }
          $age = $this->calculateAgeFromIso($iso);
          $payload['age'] = $age;
          if ($age >= $min_age) {
            $this->sessionManager->setPassed();
            $payload['result'] = 'passed';
          }
          else {
            $this->sessionManager->setDenied();
            $payload['result'] = 'denied';
          }
          break;

        default:
          return $this->jsonError('Unsupported action.', 400);
      }

      return $this->json($payload, 200);
    }
    catch (\Throwable $e) {
      $this->logger->error('verify() failed: {m} {f}:{l}', [
        'm' => $e->getMessage(),
        'f' => $e->getFile(),
        'l' => $e->getLine(),
      ]);
      return $this->jsonError('server error', 500);
    }
  }

  /**
   * Normalize a DOB string from the modal into YYYY-MM-DD.
   *
   * Accepts:
   *   - 09/23/1980, 09-23-1980, 09231980 (mdy)
   *   - 23/09/1980, 23-09-1980, 23091980 (dmy)
   *   - 1980-09-23 (ISO; always allowed)
   */
  private function normalizeDob(string $raw, string $fmt): ?string {
    $raw = trim($raw);

    // Accept ISO-8601 directly.
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
      [$y, $m, $d] = explode('-', $raw);
      if (checkdate((int) $m, (int) $d, (int) $y)) {
        return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
      }
      return NULL;
    }

    $digits = preg_replace('/\D+/', '', $raw);
    if (strlen($digits) !== 8) {
      return NULL;
    }

    // Extract parts per configured format.
    if ($fmt === 'dmy') {
      $d = substr($digits, 0, 2);
      $m = substr($digits, 2, 2);
      $y = substr($digits, 4, 4);
    }
    // Default 'mdy'.
    else {
      $m = substr($digits, 0, 2);
      $d = substr($digits, 2, 2);
      $y = substr($digits, 4, 4);
    }

    $mm = (int) $m;
    $dd = (int) $d;
    $yy = (int) $y;
    if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31 || $yy < 1900 || $yy > 2100) {
      return NULL;
    }
    if (!checkdate($mm, $dd, $yy)) {
      return NULL;
    }

    return sprintf('%04d-%02d-%02d', $yy, $mm, $dd);
  }

  /**
   * Computes age in years for an ISO date.
   */
  private function calculateAgeFromIso(string $isoDate): int {
    $dob = \DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate)
      ?: new \DateTimeImmutable('@0');
    $now = new \DateTimeImmutable('today');
    $diff = $dob->diff($now);
    return max(0, (int) $diff->y);
  }

  /**
   * Builds a JSON response with no-cache headers.
   */
  private function json(array $payload, int $status = 200): JsonResponse {
    $res = new JsonResponse($payload, $status);
    $res->headers->set('Content-Type', 'application/json');
    $res->headers->set('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    $res->headers->set('Pragma', 'no-cache');
    $res->headers->set('Expires', '0');
    return $res;
  }

  /**
   * Convenience wrapper for JSON error responses.
   */
  private function jsonError(string $message, int $status = 400): JsonResponse {
    return $this->json(['ok' => FALSE, 'error' => $message], $status);
  }

}
