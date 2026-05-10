<?php
/**
 * Blog index — same editorial layout as front-page when used.
 * Delegates to front-page.php so the experience stays consistent.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

include get_template_directory() . '/front-page.php';
