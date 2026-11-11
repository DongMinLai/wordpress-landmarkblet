/* CF7 owns validation and submit transport. This adds visible progress and context. */
(()=>{'use strict';
 const form=document.querySelector('.lm-contact-main .wpcf7 form');if(!form)return;
 const button=form.querySelector('[type=submit]'),status=form.querySelector('.lm-inquiry-progress'),topic=form.querySelector('#lm-topic'),message=form.querySelector('#lm-message'),help=form.querySelector('#lm-message-help');let pendingTimer;
 const hints={'Wholesale order':'Tell us which styles, colors and sizes you are considering.','Custom project':'Describe your design, logo or packaging changes and any references you can share.','Samples':'Tell us which SKUs you would like to discuss as samples.','Existing order':'Include your order number and describe the question or issue.','General question':'Tell us how we can help.'};
 const updateHelp=()=>{if(help)help.textContent=hints[topic?.value]||'Tell us about the styles, options or question you have in mind.';};
 topic?.addEventListener('change',updateHelp);
 updateHelp();
 if(message&&help)message.setAttribute('aria-describedby',help.id);
 const focusOutput=()=>{const output=form.querySelector('.wpcf7-response-output');if(output&&output.checkVisibility()&&output.textContent.trim()){output.tabIndex=-1;output.focus();output.scrollIntoView({block:'nearest',behavior:'smooth'});}};
 document.addEventListener('wpcf7reset',event=>{if(form.contains(event.target)||event.target===form.closest('.wpcf7'))queueMicrotask(focusOutput);});
 form.addEventListener('reset',()=>{if(help)help.textContent='Tell us about the styles, options or question you have in mind.';});
 document.addEventListener('wpcf7beforesubmit',event=>{if(!form.contains(event.target)&&event.target!==form.closest('.wpcf7'))return;clearTimeout(pendingTimer);if(button)button.disabled=true;form.setAttribute('aria-busy','true');if(status)status.textContent='Submitting your inquiry…';pendingTimer=setTimeout(()=>{if(button)button.disabled=false;form.removeAttribute('aria-busy');if(status)status.textContent='No confirmation yet. Check your connection or email the team before trying again.';},10000);});
 document.addEventListener('wpcf7submit',event=>{if(!form.contains(event.target)&&event.target!==form.closest('.wpcf7'))return;clearTimeout(pendingTimer);if(button)button.disabled=false;form.removeAttribute('aria-busy');if(status)status.textContent='';queueMicrotask(focusOutput);});
})();
