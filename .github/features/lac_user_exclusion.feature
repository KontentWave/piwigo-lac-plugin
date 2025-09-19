# features/lac_user_exclusion.feature

Feature: User Exclusion Rule for Legal Age Gate
  As a site administrator,
  I want to control whether the age gate applies to logged-in users,
  So I can enforce age confirmation for all non-admin users if required for compliance.

  Background:
    Given a Piwigo gallery is set up
    And the Legal Age Consent plugin is active

  Scenario: Administrator enables the age gate for logged-in users
    Given I am logged in as an administrator
    And the age gate is not applied to logged-in users
    When I navigate to the plugin's admin page
    And I check the "Apply to Logged-in Users" checkbox
    And I save the settings
    Then the age gate should now be applied to logged-in users

  Scenario: A logged-in user is ignored when the setting is disabled (default behavior)
    Given the age gate is not applied to logged-in users
    And I am a logged-in user who has not given age consent
    When I navigate to the main gallery page
    Then I should see the gallery's content directly
    And I should not be redirected

  Scenario: A logged-in user is checked for consent when the setting is enabled
    Given the age gate is applied to logged-in users
    And I am a logged-in user who has not given age consent
    When I navigate to the main gallery page
    Then I should be redirected to the age confirmation page

  Scenario: An administrator is always ignored, even when the setting is enabled
    Given the age gate is applied to logged-in users
    And I am logged in as an administrator
    When I navigate to the main gallery page
    Then I should see the gallery's content directly
    And I should not be redirected

  Scenario: A guest user is always checked for consent, regardless of the setting
    Given the age gate is not applied to logged-in users
    And I am a guest user who has not given age consent
    When I try to navigate to the main gallery page
    Then I should be redirected to the age confirmation page