<?php

declare(strict_types=1);

namespace Drupal\simpleavs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for SimpleAVS.
 */
final class AgeGateSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'simpleavs_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['simpleavs.settings'];
  }

  /**
   * Preset definitions.
   */
  private function presets(): array {
    return [
      'none' => [
        'label' => $this->t('— No preset (keep current values) —'),
        'values' => [],
      ],
      'light' => [
        'label' => $this->t('Classic Light'),
        'values' => [
          'overlay_color'   => '#000000',
          'overlay_opacity' => 0.5,
          'modal_bg'        => '#ffffff',
          'text_color'      => '#111111',
          'button_bg'       => '#1e3a8a',
          'button_text'     => '#ffffff',
        ],
      ],
      'dark' => [
        'label' => $this->t('Classic Dark'),
        'values' => [
          'overlay_color'   => '#000000',
          'overlay_opacity' => 0.85,
          'modal_bg'        => '#111827',
          'text_color'      => '#e5e7eb',
          'button_bg'       => '#2563eb',
          'button_text'     => '#ffffff',
        ],
      ],
      'love' => [
        'label' => $this->t('Love'),
        'values' => [
          'overlay_color'   => '#000000',
          'overlay_opacity' => 0.85,
          'modal_bg'        => '#F2E6D0',
          'text_color'      => '#1F1B16',
          'button_bg'       => '#B11E2F',
          'button_text'     => '#FFF7E6',
        ],
      ],
      'glass' => [
        'label' => $this->t('Glass / Frosted'),
        'values' => [
          'overlay_color'   => '#0f172a',
          'overlay_opacity' => 0.55,
          'modal_bg'        => '#ffffff',
          'text_color'      => '#0f172a',
          'button_bg'       => '#0ea5e9',
          'button_text'     => '#ffffff',
        ],
      ],
      'contrast' => [
        'label' => $this->t('High Contrast'),
        'values' => [
          'overlay_color'   => '#000000',
          'overlay_opacity' => 0.9,
          'modal_bg'        => '#ffffff',
          'text_color'      => '#000000',
          'button_bg'       => '#000000',
          'button_text'     => '#ffffff',
        ],
      ],
      'brand' => [
        'label' => $this->t('Brand (Purple)'),
        'values' => [
          'overlay_color'   => '#0b0b0b',
          'overlay_opacity' => 0.8,
          'modal_bg'        => '#1f0937',
          'text_color'      => '#f5f3ff',
          'button_bg'       => '#7c3aed',
          'button_text'     => '#ffffff',
        ],
      ],
    ];
  }

  /**
   * Apply preset values onto an existing appearance array.
   */
  private function applyPreset(string $key, array $current): array {
    $presets = $this->presets();
    if (!isset($presets[$key]) || empty($presets[$key]['values'])) {
      return $current;
    }
    foreach ($presets[$key]['values'] as $k => $v) {
      $current[$k] = $v;
    }
    return $current;
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $cfg = $this->config('simpleavs.settings');

    // Safe reads with sane defaults.
    $enabled   = (bool) ($cfg->get('enabled') ?? FALSE);
    $method    = (string) ($cfg->get('method') ?? 'question');
    $min_age   = (int) ($cfg->get('min_age') ?? 18);
    $frequency = (string) ($cfg->get('frequency') ?? 'never');

    $path_mode     = (string) ($cfg->get('path_mode') ?? 'exclude');
    $path_patterns = (string) ($cfg->get('path_patterns') ?? '');

    $redirect_success = (string) ($cfg->get('redirect_success') ?? '');
    $redirect_failure = (string) ($cfg->get('redirect_failure') ?? '');

    $strings         = $cfg->get('strings') ?? [];
    $appearanceSaved = $cfg->get('appearance') ?? [];

    // Date format (only mdy|dmy).
    $date_format = (string) ($cfg->get('date_format') ?? 'mdy');
    if ($date_format !== 'dmy' && $date_format !== 'mdy') {
      $date_format = 'mdy';
    }

    // Preset selection + preview (AJAX).
    $presets = $this->presets();

    // ? Prefer the current user input (AJAX selection) so colors update live.
    $ui = $form_state->getUserInput();
    if (isset($ui['appearance']['preset'])) {
      $selectedPreset = (string) $ui['appearance']['preset'];
    }
    else {
      $selectedPreset = $form_state->get('simpleavs_preset')
        ?? (string) ($appearanceSaved['preset'] ?? 'none');
    }

    // Apply PRESET OVER the saved/current appearance for preview.
    $appearance = $this->applyPreset($selectedPreset, $appearanceSaved);

    // --- Core controls ---
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable SimpleAVS'),
      '#default_value' => $enabled,
      '#description' => $this->t('Leave unchecked until you finish configuring.'),
    ];

    $form['method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Verification method'),
      '#options' => [
        'question' => $this->t('Simple question (Yes/No)'),
        'dob'      => $this->t('Date of birth'),
      ],
      '#default_value' => $method,
    ];

    $form['min_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum age'),
      '#min' => 0,
      '#default_value' => $min_age,
      '#required' => TRUE,
    ];

    // Show only when method = dob.
    $form['date_format'] = [
      '#type' => 'radios',
      '#title' => $this->t('DOB input format'),
      '#description' => $this->t('Controls how the modal expects dates and how the input is auto-formatted.'),
      '#options' => [
        'mdy' => $this->t('MM/DD/YYYY'),
        'dmy' => $this->t('DD/MM/YYYY'),
      ],
      '#default_value' => $date_format,
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => 'dob'],
        ],
      ],
    ];

    $form['frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('Prompt frequency'),
      '#options' => [
        'never'   => $this->t('Never (off)'),
        'session' => $this->t('Once per session'),
        'daily'   => $this->t('Once per day'),
        'weekly'  => $this->t('Once per week'),
        'always'  => $this->t('On every page load'),
      ],
      '#default_value' => $frequency,
    ];

    // --- Path targeting ---
    $form['paths'] = [
      '#type' => 'details',
      '#title' => $this->t('Page targeting'),
      '#open' => TRUE,
    ];
    $form['paths']['path_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Apply to'),
      '#options' => [
        'include' => $this->t('Only the following pages'),
        'exclude' => $this->t('All pages except the following'),
      ],
      '#default_value' => $path_mode,
    ];
    $form['paths']['path_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pages'),
      '#default_value' => $path_patterns,
      '#description' => $this->t('One path per line. Use %front for the front page. Wildcards like "blog/*" are allowed.', ['%front' => '<front>']),
    ];

    // --- Redirects ---
    $form['redirects'] = [
      '#type' => 'details',
      '#title' => $this->t('Redirects'),
      '#open' => FALSE,
    ];
    $form['redirects']['redirect_success'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect on success (optional)'),
      '#default_value' => $redirect_success,
      '#description' => $this->t('Internal path (e.g., "/node/1") or absolute URL (https://example.com). Leave blank to stay on the same page.'),
    ];
    $form['redirects']['redirect_failure'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect on failure (optional)'),
      '#default_value' => $redirect_failure,
    ];

    // --- Strings ---
    $form['strings'] = [
      '#type' => 'details',
      '#title' => $this->t('Text & labels'),
      '#open' => FALSE,
    ];
    $def = function(string $k, string $fallback) use ($strings) {
      return (string) ($strings[$k] ?? $fallback);
    };

    // Always visible
    $form['strings']['modal_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal title'),
      '#default_value' => $def('modal_title', 'Age Verification Required'),
    ];
    $form['strings']['message_confirm'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation message'),
      '#default_value' => $def('message_confirm', 'This site requires you to be at least [age] years old.'),
    ];
    $form['strings']['confirm_button'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirm button'),
      '#default_value' => $def('confirm_button', 'Enter'),
    ];
    $form['strings']['deny_button'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deny button'),
      '#default_value' => $def('deny_button', 'Leave'),
    ];
    $form['strings']['denied_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Denied message'),
      '#default_value' => $def('denied_message', 'You must be [age]+ to enter.'),
    ];

    // Question-only strings
    $form['strings']['question_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Question text'),
      '#default_value' => $def('question_text', 'Are you over the age of [age]?'),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => 'question'],
        ],
      ],
    ];
    $form['strings']['yes_button'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Yes button'),
      '#default_value' => $def('yes_button', 'Yes'),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => 'question'],
        ],
      ],
    ];
    $form['strings']['no_button'] = [
      '#type' => 'textfield',
      '#title' => $this->t('No button'),
      '#default_value' => $def('no_button', 'No'),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => 'question'],
        ],
      ],
    ];

    // DOB-only strings
    $form['strings']['dob_instruction'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DOB instruction'),
      '#default_value' => $def('dob_instruction', 'Please enter your date of birth to verify your age:'),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => 'dob'],
        ],
      ],
    ];
    $form['strings']['dob_verify_button'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DOB verify button'),
      '#default_value' => $def('dob_verify_button', 'Verify'),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => 'dob'],
        ],
      ],
    ];
    $form['strings']['dob_invalid_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('DOB invalid message'),
      '#default_value' => $def('dob_invalid_message', 'Please enter a valid date of birth.'),
      '#states' => [
        'visible' => [
          ':input[name="method"]' => ['value' => 'dob'],
        ],
      ],
    ];

    // --- Appearance + Preset (AJAX preview) ---
    $form['appearance'] = [
      '#type' => 'details',
      '#title' => $this->t('Appearance'),
      '#open' => TRUE,
      '#tree' => TRUE, // ensure nested values are preserved
    ];

    $presetOptions = [];
    foreach ($presets as $k => $p) {
      $presetOptions[$k] = (string) $p['label'];
    }

    $form['appearance']['preset'] = [
      '#type' => 'select',
      '#title' => $this->t('Preset'),
      '#options' => $presetOptions,
      '#default_value' => $selectedPreset,
      '#description' => $this->t('Choosing a preset will populate the fields below. You can still tweak any value.'),
      '#ajax' => [
        'callback' => '::presetAjax',
        'wrapper'  => 'simpleavs-appearance-wrapper',
        'event'    => 'change',
      ],
    ];

    $form['appearance']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'simpleavs-appearance-wrapper'],
      '#tree' => TRUE,
    ];

    $app = fn(string $k, $fallback) => $appearance[$k] ?? $fallback;

    $form['appearance']['wrapper']['overlay_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Overlay color'),
      '#default_value' => (string) $app('overlay_color', '#000000'),
    ];
    $form['appearance']['wrapper']['overlay_opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Overlay opacity'),
      '#step' => 0.01,
      '#min' => 0,
      '#max' => 1,
      '#default_value' => (float) $app('overlay_opacity', 0.85),
    ];
    $form['appearance']['wrapper']['modal_bg'] = [
      '#type' => 'color',
      '#title' => $this->t('Modal background'),
      '#default_value' => (string) $app('modal_bg', '#ffffff'),
    ];
    $form['appearance']['wrapper']['text_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Text color'),
      '#default_value' => (string) $app('text_color', '#111111'),
    ];
    $form['appearance']['wrapper']['button_bg'] = [
      '#type' => 'color',
      '#title' => $this->t('Button background'),
      '#default_value' => (string) $app('button_bg', '#1e3a8a'),
    ];
    $form['appearance']['wrapper']['button_text'] = [
      '#type' => 'color',
      '#title' => $this->t('Button text'),
      '#default_value' => (string) $app('button_text', '#ffffff'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to re-render appearance fields when preset changes.
   */
  public function presetAjax(array &$form, FormStateInterface $form_state): array {
    // Read from triggering element to avoid stale values during AJAX.
    $trigger = $form_state->getTriggeringElement();
    $selected = is_array($trigger) && isset($trigger['#value'])
      ? (string) $trigger['#value']
      : (string) $form_state->getValue(['appearance','preset']);

    $form_state->set('simpleavs_preset', $selected);
    $form_state->setRebuild();

    return $form['appearance']['wrapper'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['redirect_success', 'redirect_failure'] as $key) {
      $val = trim((string) $form_state->getValue($key));
      if ($val !== '' && !(str_starts_with($val, '/') || preg_match('@^https?://@i', $val))) {
        $form_state->setErrorByName($key, $this->t('Enter an internal path starting with "/" or an absolute URL.'));
      }
    }
    $op = (float) $form_state->getValue(['appearance', 'wrapper', 'overlay_opacity']);
    if ($op < 0 || $op > 1) {
      $form_state->setErrorByName('overlay_opacity', $this->t('Overlay opacity must be between 0 and 1.'));
    }
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->cleanValues()->getValues();

    // Current saved appearance to compare prior preset.
    $prevAppearance = $this->config('simpleavs.settings')->get('appearance') ?? [];
    $prevPreset     = (string) ($prevAppearance['preset'] ?? 'none');

    $save = [
      'enabled'          => (bool)   $values['enabled'],
      'method'           => (string) $values['method'],
      'min_age'          => (int)    $values['min_age'],
      'date_format'      => (string) $values['date_format'], // 'mdy' | 'dmy'
      'frequency'        => (string) $values['frequency'],
      'path_mode'        => (string) $values['path_mode'],
      'path_patterns'    => (string) $values['path_patterns'],
      'redirect_success' => (string) $values['redirect_success'],
      'redirect_failure' => (string) $values['redirect_failure'],
      'strings'          => [],
      'appearance'       => [],
    ];

    // Strings.
    foreach ([
      'modal_title','question_text','yes_button','no_button',
      'dob_instruction','dob_verify_button','dob_invalid_message',
      'message_confirm','confirm_button','deny_button','denied_message',
    ] as $k) {
      $save['strings'][$k] = (string) ($values[$k] ?? '');
    }

    // Figure out appearance from either the preset or submitted fields.
    $presetSel = (string) $values['appearance']['preset'];
    $submitted = [
      'overlay_color'   => (string) ($values['appearance']['wrapper']['overlay_color'] ?? '#000000'),
      'overlay_opacity' => (float)  ($values['appearance']['wrapper']['overlay_opacity'] ?? 0.85),
      'modal_bg'        => (string) ($values['appearance']['wrapper']['modal_bg'] ?? '#ffffff'),
      'text_color'      => (string) ($values['appearance']['wrapper']['text_color'] ?? '#111111'),
      'button_bg'       => (string) ($values['appearance']['wrapper']['button_bg'] ?? '#1e3a8a'),
      'button_text'     => (string) ($values['appearance']['wrapper']['button_text'] ?? '#ffffff'),
    ];

    // If preset changed, trust the preset values (so user doesn't need to save twice).
    if ($presetSel !== 'none' && $presetSel !== $prevPreset) {
      $presetVals = $this->presets()[$presetSel]['values'] ?? [];
      $appearance = $presetVals; // apply preset definitively on this submit
    }
    else {
      // No change of preset: keep whatever the user submitted in the fields.
      $appearance = $submitted;
    }

    // Persist.
    $save['appearance'] = $appearance + ['preset' => $presetSel];

    $this->configFactory()->getEditable('simpleavs.settings')->setData($save)->save();
    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('SimpleAVS settings saved.'));
  }
}
