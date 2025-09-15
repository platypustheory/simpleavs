<?php

declare(strict_types=1);

namespace Drupal\simpleavs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * AJAX endpoints for SimpleAVS.
 *
 * Routes:
 *  - /simpleavs/token   (GET)   -> token()
 *  - /simpleavs/verify  (POST)  -> verify()
 *
 * Relies on AvsSessionManager methods:
 *  - issueToken(): string
 *  - consumeToken(string $token): bool   (one-time)
 *  - setPassed(): void
 *  - setDenied(): void
 */
final class AgeGateController extends ControllerBase {

  /**
   * @var \Psr\Log\LoggerInterface */
  private $logger;

  /**
   * @var object */
  private $sessionManager;

  /**
   *
   */
  public static function create(ContainerInterface $container): self {
    $inst = new self();
    $inst->logger = $container->get('logger.channel.simpleavs');
    $inst->sessionManager = $container->get('simpleavs.session_manager');
    return $inst;
  }

  /**
   * GET /simpleavs/token
   * Returns a one-time token as JSON.
   */
  public function token(Request $request): JsonResponse {
    try {
      $token = $this->sessionManager->issueToken();
      $this->logger->info('Issued token {t}', ['t' => $token]);
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
   * POST /simpleavs/verify
   * Accepts: action=yes|no|dob, token=..., dob given as mdy/dmy (digits-only or with separators).
   */
  public function verify(Request $request): JsonResponse {
    try {
      // Accept either x-www-form-urlencoded or JSON payload.
      $action = NULL;
      $token  = NULL;
      $dobRaw = NULL;

      if (0 === strpos((string) $request->headers->get('Content-Type'), 'application/json')) {
        $data = json_decode((string) $request->getContent(), TRUE) ?: [];
        $action = (string) ($data['action'] ?? '');
        $token  = (string) ($data['token'] ?? '');
        $dobRaw = isset($data['dob']) ? (string) $data['dob'] : NULL;
      }
      else {
        $action = (string) $request->request->get('action', '');
        $token  = (string) $request->request->get('token', '');
        $dobRaw = $request->request->has('dob') ? (string) $request->request->get('dob') : NULL;
      }

      if ($token === '') {
        return $this->jsonError('Missing token.', 400);
      }

      // One-time token check.
      if (!$this->sessionManager->consumeToken($token)) {
        return $this->jsonError('Invalid token.', 400);
      }

      $cfg = $this->config('simpleavs.settings');
      $method = (string) ($cfg->get('method') ?? 'question');
      $minAge = (int) ($cfg->get('min_age') ?? 18);

      // Only support 'mdy' or 'dmy'. Anything else falls back to 'mdy'.
      $dateFormat = (string) ($cfg->get('date_format') ?? 'mdy');
      if ($dateFormat !== 'dmy' && $dateFormat !== 'mdy') {
        $dateFormat = 'mdy';
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
          $isoDob = $this->normalizeDob($dobRaw, $dateFormat);
          if ($isoDob === NULL) {
            return $this->jsonError('Invalid DOB format.', 400);
          }
          $age = $this->calculateAgeFromIso($isoDob);
          $payload['age'] = $age;
          if ($age >= $minAge) {
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
   * Normalize DOB to 'Y-m-d' for 'mdy' or 'dmy'.
   * Accepts digits-only (e.g., 09151990 / 15091990) or separated (/, -, .).
   */
  private function normalizeDob(string $input, string $expected = 'mdy'): ?string {
    $s = trim($input);
    if ($s === '') {
      return NULL;
    }

    // Digits-only path: MMDDYYYY (mdy) or DDMMYYYY (dmy).
    $digits = preg_replace('/\D+/', '', $s);
    if (is_string($digits) && strlen($digits) === 8) {
      if ($expected === 'dmy') {
        $d = (int) substr($digits, 0, 2);
        $m = (int) substr($digits, 2, 2);
        $y = (int) substr($digits, 4, 4);
      }
      // Mdy default.
      else {
        $m = (int) substr($digits, 0, 2);
        $d = (int) substr($digits, 2, 2);
        $y = (int) substr($digits, 4, 4);
      }
      if ($m >= 1 && $m <= 12 && checkdate($m, $d, $y)) {
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
      }
      return NULL;
    }

    // Separated path: strict formats for the expected ordering.
    $formats = ($expected === 'dmy')
      ? ['d/m/Y', 'd-m-Y', 'd.m.Y']
      : ['m/d/Y', 'm-d-Y', 'm.d.Y'];

    foreach ($formats as $fmt) {
      $dt = \DateTime::createFromFormat('!' . $fmt, $s);
      $err = \DateTime::getLastErrors();
      if ($dt && empty($err['warning_count']) && empty($err['error_count'])) {
        return $dt->format('Y-m-d');
      }
    }

    return NULL;
  }

  /**
   * Age (years) for an ISO date.
   */
  private function calculateAgeFromIso(string $isoDate): int {
    $dob = \DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate) ?: new \DateTimeImmutable('@0');
    $now = new \DateTimeImmutable('today');
    $diff = $dob->diff($now);
    return max(0, (int) $diff->y);
  }

  /**
   * JSON helper with no-cache headers.
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
   *
   */
  private function jsonError(string $message, int $status = 400): JsonResponse {
    return $this->json(['ok' => FALSE, 'error' => $message], $status);
  }

}
