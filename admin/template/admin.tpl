<div class="lac-admin">
  <h2>{'Legal Age Consent'|@translate} – {'Configuration'|@translate}</h2>
  {* Legacy demo placeholder for informational message; now populated from $page['infos'] if any *}
  {if !empty($LAC_MESSAGE)}
    <div class="infos">{$LAC_MESSAGE}</div>
  {/if}
  {if !empty($LAC_ERRORS)}
    <div class="errors">
      <ul>
      {foreach from=$LAC_ERRORS item=err}
        <li>{$err}</li>
      {/foreach}
      </ul>
    </div>
  {/if}
  <form method="post">
    <input type="hidden" name="pwg_token" value="{$LAC_TOKEN}" />
    <fieldset>
      <legend>{'Settings'|@translate}</legend>
      <label>
        <input type="checkbox" name="lac_enabled" {$LAC_ENABLED} /> {'Enable age gate'|@translate}
      </label>
      <br /><br />
      <label>
        {'Fallback URL'|@translate}:<br />
        <input type="text" name="lac_fallback_url" value="{$LAC_FALLBACK_URL}" size="60" placeholder="https://example.com/too-young" />
      </label>
      <br /><br />
      <label>
        {'Consent Duration'|@translate} (minutes):<br />
        <input type="number" name="lac_consent_duration" value="{$LAC_CONSENT_DURATION}" min="0" style="width:120px;" />
      </label>
      <div class="helper-text" style="font-size:0.9em;color:#666;max-width:520px;">
        {'Duration (minutes) for which consent remains valid. Set 0 for session-only.'|@translate}
      </div>
      <br /><br />
      <label>
        <input type="checkbox" name="lac_apply_to_logged_in" {$LAC_APPLY_LOGGED_IN} /> {'Apply to Logged-in Users'|@translate}
      </label>
      <div class="helper-text" style="font-size:0.9em;color:#666;max-width:520px;">
        {'If checked, non-admin (regular) users must also confirm age. Administrators and webmasters are always excluded.'|@translate}
      </div>
    </fieldset>
    <p>
      <button class="submit" name="lac_settings_submit" value="1">{'Save Settings'|@translate}</button>
    </p>
  </form>
</div>
