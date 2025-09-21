{combine_css path=$LAC_PATH|@cat:"admin/template/style.css"}

{footer_script}
jQuery('input[name="option2"]').change(function() {
  $('.option1').toggle();
});

jQuery(".showInfo").tipTip({
  delay: 0,
  fadeIn: 200,
  fadeOut: 200,
  maxWidth: '300px',
  defaultPosition: 'bottom'
});
{/footer_script}


<div class="titrePage">
	<h2>Legal Age Consent</h2>
</div>

<form method="post" action="" class="properties">
  <input type="hidden" name="pwg_token" value="{$LAC_TOKEN}">
  <fieldset>
    <legend>{'Age Gate Settings'|translate}</legend>
    <ul>
      <li>
        <label>
          <input type="checkbox" name="lac_enabled" value="1" {$LAC_ENABLED}>
          <b>{'Enable Age Gate'|translate}</b>
        </label>
        <div class="hint">{'Uncheck to disable all gating logic without uninstalling the plugin.'|translate}</div>
      </li>
      <li>
        <label>
          <b>{'Fallback URL'|translate}</b>
          <input type="text" name="lac_fallback_url" value="{$LAC_FALLBACK_URL}" size="60" placeholder="https://example.org/">
        </label>
        <div class="hint">{'External URL used when a visitor declines. Must be http(s) and not this host.'|translate}</div>
      </li>
      <li>
        <label>
          <b>{'Consent Duration (minutes)'|translate}</b>
          <input type="number" min="0" name="lac_consent_duration" value="{$LAC_CONSENT_DURATION}" size="8">
        </label>
        <div class="hint">{'0 = session only. Otherwise re-confirmation required after the elapsed time.'|translate}</div>
      </li>
      <li>
        <label>
          <input type="checkbox" name="lac_apply_to_logged_in" value="1" {$LAC_APPLY_LOGGED_IN}>
          <b>{'Apply to Logged-in Users'|translate}</b>
        </label>
        <div class="hint">{'If checked, non-admin (regular) users must also confirm age. Administrators and webmasters are always excluded.'|translate}</div>
      </li>
    </ul>
  </fieldset>

  {if !empty($LAC_ERRORS)}
    <div class="errors">
      <ul>
      {foreach from=$LAC_ERRORS item=err}
        <li>{$err}</li>
      {/foreach}
      </ul>
    </div>
  {/if}
  {if $LAC_MESSAGE}
    <div class="infos">{$LAC_MESSAGE}</div>
  {/if}

  <p class="formButtons"><input type="submit" name="lac_settings_submit" value="{'Save Settings'|translate}"></p>
</form>