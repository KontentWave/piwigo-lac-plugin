# features/lac_consent_expiration.feature

Feature: Consent Expiration for Legal Age Gate
  As a site administrator,
  I want to set a time limit on age consent,
  So that consent is periodically re-validated for compliance and security.

  Background:
    Given a Piwigo gallery is set up
    And the Legal Age Consent plugin is active

  Scenario: Administrator can set and save the consent duration
    Given I am logged in as an administrator
    When I navigate to the plugin's admin page
    And I fill in "Consent Duration" with "60"
    And I save the settings
    When I navigate to the plugin's admin page again
    Then the "Consent Duration" field should contain "60"

  Scenario: Guest is not asked again before consent expires
    Given the consent duration is set to "30" minutes
    And I am a guest user who has just given age consent
    When I wait for "10" minutes
    And I try to navigate to the main gallery page
    Then I should see the gallery's content directly
    And I should not be redirected to the age confirmation page

  Scenario: Guest must re-confirm their age after consent expires
    Given the consent duration is set to "15" minutes
    And I am a guest user who has just given age consent
    When I wait for "20" minutes
    And I try to navigate to the main gallery page
    Then I should be redirected to the age confirmation page

  Scenario: Guest consent lasts for the session when duration is zero
    Given the consent duration is set to "0" minutes
    And I am a guest user who has just given age consent
    # We wait a long time to show that the expiration is not time-based in this case
    When I wait for "90" minutes
    And I try to navigate to the main gallery page
    Then I should see the gallery's content directly
    And I should not be redirected to the age confirmation page