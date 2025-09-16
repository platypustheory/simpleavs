<?php

declare(strict_types=1);

namespace Drupal\simpleavs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form/FormStateInterface;

/**
 * Settings form for SimpleAVS.
 */
final class AgeGateSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simpleavs_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['simpleavs.settings'];
  }

  /**
   * Returns preset definitions used to populate the appearance fields.
   *
   * @return array
   *   An associative array keyed by preset machine name. Each item contains:
   *   - label: The translatable label.
   *   - values: An array of appearance values applied by that preset.
   */
  private function presets(): array {
    return [
      'none' => [
        // Fixed: no leading/trailing whitespace around the translatable string.
        'label' => $this->t('No preset (keep current values)'),
        'values' => [],
      ],
      'light' => [
        'label' => $this->t('Classic Light'),
        'values' => [
          'overlay_color' => '#000000',
          'overlay_opacity' => 0.5,
          'modal_bg' => '#ffffff',
          'text_color' => '#111111',
          'button_bg' => '#1e3a8a',
          'button_text' => '#ffffff',
        ],
      ],
      'dark' => [
        'label' => $this->t('Classic Dark'),
        'values' => [
          'overlay_color' => '#000000',
          'overlay_opacity' => 0.85,
          'modal_bg' => '#111827',
          'text_color' => '#e5e7eb',
          'button_bg' => '#2563eb',
          'button_text' => '#ffffff',
        ],
      ],
      'love' => [
        'label' => $this->t('Love'),
        'values' => [
          'overlay_color' => '#000000',
          'overlay_opacity' => 0.85,
          'modal_bg' => '#F2E6D0',
          'text_color' => '#1F1B16',
          'button_bg' => '#B11E2F',
          'button_text' => '#FFF7E6',
        ],
      ],
      'glass' => [
        'label' => $this->t('Glass / Frosted'),
        'values' => [
          'overlay_color' => '#0f172a',
          'overlay_opacity' => 0.55,
          'modal_bg' => '#ffffff',
          'text_color' => '#0f172a',
          'button_bg' => '#0ea5e9',
          'button_text' => '#ffffff',
        ],
      ],
      'contrast' => [
        'label' => $this->t('High Contrast'),
        'values' => [
          'overlay_color' => '#000000',
          'overlay_opacity' => 0.9,
          'modal_bg' => '#ffffff',
          'text_color' => '#000000',
          'button_bg' => '#000000',
          'button_text' => '#ffffff',
        ],
      ],
      'brand' => [
        'label' => $this->t('Brand (Purple)'),
        'values' => [
          'overlay_color' => '#0b0b0b',
          'overlay_opacity' => 0.8,
          'modal_bg' => '#1f0937',
          'text_color' => '#f5f3ff',
          'button_bg' => '#7c3aed',
          'button_text' => '#ffffff',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Only used to know the currently saved preset for first render.
    $appearanceSaved = $this->config('simpleavs.settings')->get('appearance') ?? [];

    // Preset selection + preview (AJAX).
    $presets = $this->presets();

    // Prefer the current user input (AJAX selection) so colors update live.
    $ui = $form_state->getUserInput();
    if (isset($ui['appearance']['preset'])) {
      $selectedPreset = (string) $ui['appearance']['preset'];
    }
    else {
      $selectedPreset = $form_state->get('simpleavs_preset')
        ?? (string) ($appearanceSaved['preset'] ?? 'none');
    }

    // --- Core controls ---
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable SimpleAVS'),
      '#description' => $this->t('Leave unchecked until you finish configuring.'),
      '#config_target' => 'simpleavs.settings:enabled',
    ];

    $form['method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Verification method'),
      '#options' => [
        'question' => $this->t('Simple question (Yes/No)'),
        'dob' => $this->t('Date of birth'),
      ],
      '#config_target' => 'simpleavs.settings:method',
    ];

    $form['min_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum age'),
      '#min' => 0,
      '#required' => TRUE,
      '#config_target' => 'simpleavs.settings:min_age',
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
      '#config_target' => 'simpleavs.settings:date_format',
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
        'session' => $this->t('Once per session'),
        'daily' => $this->t('Once per day'),
        'weekly' => $this->t('Once per week'),
        'always' => $this->t('On every page load'),
      ],
      '#config_target' => 'simpleavs.settings:frequency',
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
      '#config_target' => 'simpleavs.settings:path_mode',
    ];
    $form['paths']['path_patterns'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pages'),
      '#description' => $this->t(
        'One path per line. Use %front for front page. Wildcards like blog/* allowed.',
        ['%front' => '<front>']
      ),
      '#config_target' => 'simpleavs.settings:path_patterns',
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
      '#description' => $this->t('Internal path ie. /node/1, full URL or leave blank.'),
      '#config_target' => 'simpleavs.settings:redirect_success',
    ];
    $form['redirects']['redirect_failure'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect on failure (optional)'),
      '#config_target' => 'simpleavs.settings:redirect_failure',
    ];

    // --- Strings ---
    $form['strings'] = [
      '#type' => 'details',
      '#title' => $this->t('Text & labels'),
      '#open' => FALSE,
    ];
    foreach ([
      'modal_title',
      'message_confirm',
      'confirm_button',
      'deny_button',
      'denied_message',
      'question_text',
      'yes_button',
      'no_button',
      'dob_instruction',
      'dob_verify_button',
      'dob_invalid_message',
    ] as $k) {
      $title = match ($k) {
        'modal_title' => $this->t('Modal title'),
        'message_confirm' => $this->t('Confirmation message'),
        'confirm_button' => $this->t('Confirm button'),
        'deny_button' => $this->t('Deny button'),
        'denied_message' => $this->t('Denied message'),
        'question_text' => $this->t('Question text'),
        'yes_button' => $this->t('Yes button'),
        'no_button' => $this->t('No button'),
        'dob_instruction' => $this->t('DOB instruction'),
        'dob_verify_button' => $this->t('DOB verify button'),
        'dob_invalid_message' => $this->t('DOB invalid message'),
        default => $this->t(ucwords(str_replace('_', ' ', $k))),
      };

      $element = [
        '#type' => 'textfield',
        '#title' => $title,
        '#config_target' => "simpleavs.settings:strings.$k",
      ];

      // Visibility for question/dob-specific strings:
      if (in_array($k, ['question_text', 'yes_button', 'no_button'], TRUE)) {
        $element['#states'] = ['visible' => [':input[name="method"]' => ['value' => 'question']]];
      }
      elseif (in_array($k, ['dob_instruction', 'dob_verify_button', 'dob_invalid_message'], TRUE)) {
        $element['#states'] = ['visible' => [':input[name="method"]' => ['value' => 'dob']]];
      }

      $form['strings'][$k] = $element;
    }

    // --- Appearance + Preset (AJAX preview) ---
    $form['appearance'] = [
      '#type' => 'details',
      '#title' => $this->t('Appearance'),
      '#open' => TRUE,
      // Ensure nested values are preserved.
      '#tree' => TRUE,
    ];

    $presetOptions = [];
    foreach ($presets as $k => $p) {
      $presetOptions[$k] = (string) $p['label'];
    }

    $form['appearance']['preset'] = [
      '#type' => 'select',
      '#title' => $this->t('Preset'),
      '#options' => $presetOptions,
      '#description' => $this->t('Choosing a preset will populate the fields below. You can still tweak any value.'),
      '#config_target' => 'simpleavs.settings:appearance.preset',
      '#ajax' => [
        'callback' => '::presetAjax',
        'wrapper' => 'simpleavs-appearance-wrapper',
        'event' => 'change',
      ],
    ];

    $form['appearance']['wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'simpleavs-appearance-wrapper'],
      '#tree' => TRUE,
    ];

    // Map appearance controls directly to config keys.
    $form['appearance']['wrapper']['overlay_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Overlay color'),
      '#config_target' => 'simpleavs.settings:appearance.overlay_color',
    ];
    $form['appearance']['wrapper']['overlay_opacity'] = [
      '#type' => 'number',
      '#title' => $this->t('Overlay opacity'),
      '#step' => 0.01,
      '#min' => 0,
      '#max' => 1,
      '#config_target' => 'simpleavs.settings:appearance.overlay_opacity',
    ];
    $form['appearance']['wrapper']['modal_bg'] = [
      '#type' => 'color',
      '#title' => $this->t('Modal background'),
      '#config_target' => 'simpleavs.settings:appearance.modal_bg',
    ];
    $form['appearance']['wrapper']['text_color'] = [
      '#type' => 'color',
      '#title' => $this->t('Text color'),
      '#config_target' => 'simpleavs.settings:appearance.text_color',
    ];
    $form['appearance']['wrapper']['button_bg'] = [
      '#type' => 'color',
      '#title' => $this->t('Button background'),
      '#config_target' => 'simpleavs.settings:appearance.button_bg',
    ];
    $form['appearance']['wrapper']['button_text'] = [
      '#type' => 'color',
      '#title' => $this->t('Button text'),
      '#config_target' => 'simpleavs.settings:appearance.button_text',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to re-render appearance fields when the preset changes.
   *
   * @return array
   *   The render array for the appearance wrapper container.
   */
  public function presetAjax(array &$form, FormStateInterface $form_state): array {
    $selected = (string) ($form_state->getValue(['appearance', 'preset']) ?? 'none');
    $presets = $this->presets();
    $values = $presets[$selected]['values'] ?? [];

    // Push chosen preset values into the live form for immediate preview.
    foreach ($values as $k => $v) {
      if (isset($form['appearance']['wrapper'][$k])) {
        $form['appearance']['wrapper'][$k]['#value'] = $v;
      }
    }

    $form_state->set('simpleavs_preset', $selected);
    $form_state->setRebuild();

    return $form['appearance']['wrapper'];
  }

  /**
   * {@inheritdoc}
   */
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

  /**
   * {@inheritdoc}
   *
   * Only applies preset values into the submitted form state.
   * The parent will persist everything via #config_target.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected = (string) ($form_state->getValue(['appearance', 'preset']) ?? 'none');

    if ($selected !== 'none') {
      $values = $this->presets()[$selected]['values'] ?? [];
      foreach ($values as $k => $v) {
        $form_state->setValue(['appearance', 'wrapper', $k], $v);
      }
    }

    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('SimpleAVS settings saved.'));
  }

}
