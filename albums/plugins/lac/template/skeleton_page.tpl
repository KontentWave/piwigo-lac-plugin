{* <!-- load CSS files --> *}
{combine_css id="lac" path=$LAC_PATH|cat:"template/style.css"}

{* <!-- load JS files --> *}
{* {combine_script id="lac" require="jquery" path=$LAC_PATH|cat:"template/script.js"} *}

{* <!-- add inline JS --> *}
{footer_script require="jquery"}
  jQuery('#lac').on('click', function(){
    alert('{'Hello world!'|translate}');
  });
{/footer_script}

{* <!-- add inline CSS --> *}
{html_style}
  #lac {
    display:block;
  }
{/html_style}


{* <!-- add page content here --> *}
<h1>{'What Legal Age Consent can do for me?'|translate}</h1>

<blockquote>
  {$INTRO_CONTENT}
</blockquote>

<div id="lac">{'Click for fun'|translate}</div>