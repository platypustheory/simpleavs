<?php

declare(strict_types=1);

namespace Drupal\simpleavs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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
          'modal_bg'_
