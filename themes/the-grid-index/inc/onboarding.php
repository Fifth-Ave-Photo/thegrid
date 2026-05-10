<?php
/**
 * The Grid Index — Onboarding
 *
 * Welcome page, activation redirect, and welcome admin notice
 * intentionally removed. The theme exposes a single admin entry:
 *   Appearance → Grid Index Options
 */
if (!defined('ABSPATH')) { exit; }

// No add_submenu_page() for Welcome.
// No wp_safe_redirect() on after_switch_theme.
// No admin_notices linking to Welcome.
