# Simple AVS

Simple AVS is a lightweight, configurable **age verification system** for Drupal 10/11.  
It displays a themable modal gate to visitors and enforces minimum-age access rules before content is revealed.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/simpleavs).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/search/simplsavs).

## Table of contents

- Features
- Requirements
- Installation
- Configuration
- Maintainers

## Features

- **Verification methods**
  - Simple Yes/No confirmation
  - Date of Birth input with validation (MM/DD/YYYY or DD/MM/YYYY)

- **Frequency control**
  - Once per session  
  - Once per day  
  - Once per week  
  - Every page load  

- **Page targeting**
  - Apply globally, include only specific paths, or exclude specific paths  
  - Wildcard and `<front>` support  

- **Customizable modal**
  - Preset themes (Light, Dark, Contrast, Glass, Brand, Love)  
  - Fully editable colors, labels, and messages  

- **Redirect options**
  - Send users to a success page or a failure page, or simply stay in place  

- **Session-safe tokens**
  - AJAX endpoints secured with one-time session tokens  
  - Works with authenticated and anonymous traffic  

## Requirements

This module requires the following:

- Drupal 10.4+ or 11
- PHP 8.2+
- No external services required

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

## Configuration

Configure Simple AVS at (/admin/config/simpleavs).

## Maintainers

Current maintainers:

- [Michael Goldsmith (Platypus Media)](https://www.drupal.org/u/platypus-media)

Supporting organizations:

- [Michael Goldsmith (Platypus Media](https://www.drupal.org/u/platypus-media) Created this module for you!
