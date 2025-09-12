<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Provide default for test mode constant to silence undefined constant warnings when referenced.
if (!defined('LAC_TEST_MODE')) {
	define('LAC_TEST_MODE', false);
}

/**
 * Determine if current user is a guest.
 */
function lac_is_guest(): bool
{
	global $user;
	return isset($user['is_guest']) && $user['is_guest'];
}

/**
 * Whether the current session signals age consent.
 */
function lac_has_consent(): bool
{
	return !empty($_SESSION['lac_consent_granted']);
}

/**
 * Core decision function used by tests; returns one of: 'allow' or 'redirect'.
 */
function lac_gate_decision(): string
{
	if (!lac_is_guest()) { return 'allow'; }
	return lac_has_consent() ? 'allow' : 'redirect';
}

