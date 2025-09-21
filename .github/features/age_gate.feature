# features/age_gate.feature

Feature: Legal Age Consent Gate
  As a site administrator,
  I want to block guest users from viewing the gallery until they confirm they are of legal age,
  So that I can comply with content regulations.

  Background:
    Given a Piwigo gallery is set up
    And the Legal Age Consent plugin is active

  Scenario: A new guest user must confirm their age to access the gallery
    Given I am a guest user who has not yet given age consent
    When I try to navigate to the main gallery page
    Then I should be redirected to the age confirmation page at "/index.php"
    When I confirm my age on that page
    Then I should be redirected back to the main gallery page
    And I should see the gallery's content

  Scenario: A logged-in user is never shown the age gate
    Given I am a logged-in user
    When I navigate to the main gallery page
    Then I should see the gallery's content directly
    And I should not be redirected to the age confirmation page

  Scenario: A guest user who has already given consent is not asked again in the same session
    Given I am a guest user who has already given age consent in this session
    When I navigate to any page within the gallery
    Then I should see that page's content directly
    And I should not be redirected to the age confirmation page

  Scenario: A guest user who declines consent is redirected away from the gallery
    Given I am a guest user who has not yet given age consent
    When I try to navigate to the main gallery page
    Then I should be redirected to the age confirmation page at "/index.php"
    When I decline consent on that page
    Then I should be redirected to the external fallback URL