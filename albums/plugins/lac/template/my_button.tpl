{strip}
{combine_css id="lac" path=$LAC_PATH|cat:"template/style.css"}

{* <!-- nothing more than the button itself must be defined here --> *}
<a href="javascript:alert('Hello world!');" title="{'This is not the %s you are looking for'|translate:('button'|translate)}" class="pwg-state-default pwg-button" rel="nofollow">
  <span class="pwg-icon lac-button"> </span>
  <span class="pwg-button-text">{'Legal Age Consent'|translate}</span>
</a>
{/strip}