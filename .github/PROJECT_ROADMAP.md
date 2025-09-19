---

# `PROJECT_ROADMAP.md` 🗺️

## Project: Piwigo Legal Age Consent (LAC) Plugin

### 🎯 Overall Project Vision

To create a robust, flexible, and user-friendly legal age consent gate for Piwigo galleries. The plugin will restrict access to galleries for non-registered users until they confirm they are of legal age, with configurable rules to ensure both compliance and a smooth user experience.

---

### 📋 Backlog of Features & Ideas

This is a list of desired functionalities for the plugin. The development will be broken down into phases.

* **Core Consent Gate:** A central page (`index.php`) that serves as the entry point, displaying the age confirmation prompt.
* **Session-Based Verification:** Consent is required for every new browser session to protect against unintended access (e.g., a child using a parent's device).
* **Admin Control Panel (ACP) Integration:** A dedicated section in the Piwigo admin panel to manage all plugin settings.
* **Configurable Consent Expiration:** Admins can set a specific duration (e.g., 60 minutes) after which a user must re-confirm their age.
* **Configurable User Exclusion:** Admins can choose whether the age gate applies to registered/logged-in users.
* **Smart Redirection:**
    * After successful confirmation, users are redirected back to the specific page they were trying to access.
    * If a user declines consent, they are redirected away from the gallery to their previous page or a fallback URL (like google.com).
* **Customizable Content (Future):** Allow admins to change the text, title, and logo on the consent page via the ACP.
* **Multilingual Support (Future):** Provide translations for the consent page interface.

---

### 🏗️ Rough Development Phases

This outlines the planned order of implementation, starting with the most critical features first.

#### **Phase 1: Minimum Viable Product (MVP) - The Core Gate**

*The goal is to get the basic, non-configurable blocking mechanism working.*
1.  **Create the Redirector (`index.php`):** Build the static HTML page in the web root that asks for age confirmation. It will handle the "Yes" and "No" logic.
2.  **Develop the Core Plugin Handler:** Create the Piwigo plugin structure and use a Piwigo event handler to check for a session variable on every page load for non-registered users.
3.  **Implement Basic Redirection:** If the session variable is not set, the handler will redirect the user to the root `index.php`. Upon confirmation, `index.php` sets the session variable and redirects to the Piwigo gallery's main page.

#### **Phase 2: Administrative Controls**

*The goal is to make the MVP's behavior configurable by the gallery administrator.*
1.  **Build the Admin Control Panel (ACP):** Create the user interface within the Piwigo admin backend.
2.  **Implement Consent Expiration:** Add the setting in the ACP to define how long the consent is valid (in minutes). The plugin handler will now check the timestamp of the consent.
3.  **Implement User Exclusion Rule:** Add the switch in the ACP to enable or disable the age gate for logged-in users.

#### **Phase 3: Enhanced User Experience (UX)**

*The goal is to make the process seamless and intelligent for the user.*
1.  **Implement "Return to Last Page" Logic:**
    * The event handler will save the user's intended destination URL before redirecting them to the age gate.
    * The `index.php` redirector will use this saved URL to send the user back to the exact page they wanted to see.
2.  **Implement Root Consent Page Logic and Fallback:** Refactor the root `index.php` to be fully aware of the plugin's advanced rules (like admin and user exemptions), ensuring a consistent experience. Crucially, engineered it also with a graceful fallback to a simple, session-only gate, ensuring the site never crashes if the plugin is deactivated or missing.