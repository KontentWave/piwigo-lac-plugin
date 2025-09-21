# features/lac_admin.feature

Feature: Administrative Controls for Legal Age Consent
  As a site administrator,
  I want a settings page for the Legal Age Consent plugin,
  So that I can enable or disable the age gate and set a fallback URL.

  Background:
    Given a Piwigo gallery is set up
    And I am logged in as an administrator
    And the Legal Age Consent plugin is active

  Scenario: Enabling the gate and setting the fallback URL
    Given the age gate is currently disabled
    When I navigate to the plugin's admin page
    And I check the "Enable age gate" checkbox
    And I fill in the "Fallback URL" with "https://example.com/too-young"
    And I save the settings
    Then the age gate should now be enabled
    And the fallback URL should be saved as "https://example.com/too-young"

  Scenario: Verifying that saved settings are displayed correctly
    Given the age gate has been enabled
    And the fallback URL has been set to "https://example.com/saved-url"
    When I navigate to the plugin's admin page
    Then the "Enable age gate" checkbox should be checked
    And the "Fallback URL" field should contain "https://example.com/saved-url"

  Scenario: A guest user is not redirected when the gate is disabled
    Given the age gate is disabled in the admin settings
    And I am a guest user who has not given age consent
    When I try to navigate to the main gallery page
    Then I should see the gallery's content directly
    And I should not be redirected