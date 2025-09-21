# features/lac_root_page_logic.feature

Feature: Root Consent Page Logic and Fallback
  As a system administrator,
  I want the root consent page to be consistent with the plugin's rules and robust against deactivation,
  So that exempt users are not incorrectly gated and the site does not crash if the plugin is missing.

  Note:
    In fallback mode (plugin not available), gating is session-only: the page considers only the current PHP session
    (`$_SESSION['lac_consent']` or legacy `lac_consent_granted`). Configured duration and the LAC cookie are ignored
    in this mode; there is no cookie-based reconstruction.

  Background:
    Given a Piwigo gallery is set up

  Scenario: An exempt administrator is redirected away when the plugin is active
    Given the Legal Age Consent plugin is active
    And I am logged in as an administrator
    When I navigate directly to the age confirmation page
    Then I should be redirected to the main gallery page
    And I should not see the age confirmation form

  Scenario: An exempt logged-in user is redirected away when the plugin is active
    Given the Legal Age Consent plugin is active
    And the age gate is not applied to logged-in users
    And I am a logged-in user
    When I navigate directly to the age confirmation page
    Then I should be redirected to the main gallery page
    And I should not see the age confirmation form

  Scenario: A guest user is shown the form when the plugin is active
    Given the Legal Age Consent plugin is active
    And I am a guest user
    When I navigate directly to the age confirmation page
    Then I should see the age confirmation form

  Scenario: Fallback - An administrator is shown the form when the plugin is inactive
    Given the Legal Age Consent plugin is not active
    And I am logged in as an administrator
    When I navigate directly to the age confirmation page
    Then the site should not produce a fatal error
    And I should see the age confirmation form

  Scenario: Fallback - A guest is shown the form when the plugin is inactive
    Given the Legal Age Consent plugin is not active
    And I am a guest user
    When I navigate directly to the age confirmation page
    Then the site should not produce a fatal error
    And I should see the age confirmation form

  Scenario: Fallback - Session-only gating ignores cookie/duration
    Given the Legal Age Consent plugin is not active
    And a non-zero consent duration is configured in the database
    And I have a valid LAC cookie but no active PHP session consent
    When I navigate directly to the age confirmation page
    Then I should see the age confirmation form