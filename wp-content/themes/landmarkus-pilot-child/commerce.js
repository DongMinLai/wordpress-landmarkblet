document.addEventListener('DOMContentLoaded',()=>{
 const $=window.jQuery;
 const passwordButtons=()=>document.querySelectorAll('.lm-commerce-main .show-password-input').forEach(button=>{if(button.querySelector('svg'))return;button.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="lm-eye-strike" d="m4 3 16 18"/></svg>';});
 $(passwordButtons);passwordButtons();
 document.querySelectorAll('.woocommerce-product-gallery .flex-control-thumbs img').forEach((image,index)=>{image.tabIndex=index===0?0:-1;image.addEventListener('click',()=>{image.closest('ol,ul').querySelectorAll('img').forEach(i=>i.tabIndex=i===image?0:-1);});image.addEventListener('keyup',event=>{if(['Enter',' ','ArrowLeft','ArrowRight'].includes(event.key))event.stopPropagation();});image.setAttribute('role','button');image.setAttribute('aria-label','View product photo '+(index+1));image.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();image.click();}if(event.key==='ArrowLeft'||event.key==='ArrowRight'){event.preventDefault();const list=[...image.closest('ol,ul').querySelectorAll('img')];const next=list[(index+(event.key==='ArrowRight'?1:-1)+list.length)%list.length];next.focus();next.click();}});});
 document.querySelectorAll('.lm-bulk-form').forEach(form=>{
  const rows=[...form.querySelectorAll('tr[data-variant]')],qtys=rows.map(r=>r.querySelector('.lm-bulk-qty')),picks=rows.map(r=>r.querySelector('.lm-bulk-select')),tools=form.querySelector('.lm-bulk-tools'),status=form.querySelector('.lm-bulk-status'),submit=form.querySelector('.lm-bulk-submit'),undo=form.querySelector('.lm-bulk-undo'),confirmation=form.querySelector('.lm-bulk-confirm');
  let previous=null,pending=null;
  const feedback=form.querySelector('.lm-bulk-feedback'),successLink=form.querySelector('.lm-bulk-success-link'),idleLabel=submit.textContent;
  const showFeedback=(state,message,reveal=false)=>{feedback.hidden=false;feedback.dataset.state=state;status.textContent=message;successLink.hidden=!['success','error'].includes(state);if(reveal){feedback.focus({preventScroll:true});feedback.scrollIntoView({block:'nearest',behavior:'instant'});}};
  const resetSubmit=()=>{if(!form.hasAttribute('aria-busy'))submit.textContent=idleLabel;};
  tools.hidden=false;form.querySelectorAll('.lm-bulk-select,.lm-bulk-select-group').forEach(e=>e.hidden=false);
  const snapshot=()=>qtys.map(e=>e.value),save=()=>{previous=snapshot();undo.disabled=false;};
  const refresh=()=>{
   let total=0,count=0;qtys.forEach(e=>{if(!e.disabled&&e.validity.valid&&Number(e.value)>0){total+=Number(e.value);count++;}});form.querySelector('.lm-bulk-total').textContent=total+' belts across '+count+' variants';
   const available=picks.filter(e=>!e.disabled),selected=available.filter(e=>e.checked);form.querySelector('.lm-bulk-selection').textContent=selected.length+' variants selected for batch fill';const all=form.querySelector('.lm-bulk-select-all');all.checked=available.length>0&&selected.length===available.length;all.indeterminate=selected.length>0&&selected.length<available.length;
   form.querySelectorAll('tbody').forEach(group=>{const active=[...group.querySelectorAll('.lm-bulk-select:not(:disabled)')],checked=active.filter(e=>e.checked),master=group.querySelector('.lm-bulk-select-group');master.disabled=!active.length;master.checked=!!active.length&&checked.length===active.length;master.indeterminate=!!checked.length&&checked.length<active.length;});
  };
  form.addEventListener('input',event=>{if(event.target.matches('.lm-bulk-qty')){resetSubmit();refresh();}});form.addEventListener('change',refresh);
  form.querySelector('.lm-bulk-select-all').addEventListener('change',event=>{picks.forEach(e=>{if(!e.disabled)e.checked=event.target.checked;});refresh();});
  form.querySelectorAll('.lm-bulk-select-group').forEach(master=>master.addEventListener('change',()=>{master.closest('tbody').querySelectorAll('.lm-bulk-select:not(:disabled)').forEach(e=>e.checked=master.checked);refresh();}));
  const apply=(targets,value)=>{save();targets.forEach(e=>e.value=value);confirmation.hidden=true;pending=null;resetSubmit();refresh();};
  form.querySelector('.lm-bulk-apply').addEventListener('click',()=>{
   const input=form.querySelector('.lm-bulk-fill-value'),targets=rows.filter(r=>r.querySelector('.lm-bulk-select').checked&&!r.querySelector('.lm-bulk-qty').disabled).map(r=>r.querySelector('.lm-bulk-qty'));
   if(!targets.length){showFeedback('info','Select one or more available variants first.');return;}if(input.value===''||!input.checkValidity()){input.reportValidity();showFeedback('error','Enter a whole, non-negative quantity for each selected variant.');return;}
   if(targets.some(e=>e.value!==''&&Number(e.value)>0&&e.value!==input.value)){pending={targets,value:input.value};confirmation.hidden=false;confirmation.querySelector('button').focus();return;}apply(targets,input.value);showFeedback('info','Quantity applied to '+targets.length+' variants.');
  });
  confirmation.querySelectorAll('button').forEach(button=>button.addEventListener('click',()=>{if(button.dataset.confirm==='yes'&&pending)apply(pending.targets,pending.value);else{pending=null;confirmation.hidden=true;}form.querySelector('.lm-bulk-apply').focus();}));
  undo.addEventListener('click',()=>{if(previous){qtys.forEach((e,i)=>e.value=previous[i]);previous=null;undo.disabled=true;confirmation.hidden=true;pending=null;refresh();resetSubmit();showFeedback('info','Previous quantities restored.');}});
  form.querySelector('.lm-bulk-clear').addEventListener('click',()=>{const targets=qtys.filter(e=>!e.disabled);if(targets.some(e=>Number(e.value)>0)){pending={targets,value:''};confirmation.hidden=false;confirmation.querySelector('button').focus();}});
  form.addEventListener('submit',async event=>{
   event.preventDefault();if(submit.disabled)return;
   if(!form.reportValidity()){showFeedback('error','Review the highlighted quantities.');return;}
   if(!qtys.some(e=>!e.disabled&&Number(e.value)>0)){showFeedback('error','Enter a quantity for at least one available variant.',true);return;}
   submit.disabled=true;submit.textContent='Adding…';form.setAttribute('aria-busy','true');showFeedback('pending','Adding your selected quantities…');
   try{
    const response=await fetch(form.dataset.ajaxUrl,{method:'POST',credentials:'same-origin',body:new FormData(form),headers:{Accept:'application/json'}});const result=await response.json();
    if(!response.ok||!result.success){showFeedback('error',result.data?.message||'Your cart could not be updated. Your quantities are still here.',true);return;}
    form.querySelector('input[name="request_id"]').value=result.data.next_request_id;qtys.forEach(e=>e.value='');picks.forEach(e=>e.checked=false);previous=null;undo.disabled=true;refresh();
    submit.textContent='Added to cart';showFeedback('success',result.data.message,true);
   }catch{showFeedback('error','We could not confirm the update. Review your cart before retrying; your quantities are still here.',true);}
   finally{submit.disabled=false;form.removeAttribute('aria-busy');if(feedback.dataset.state!=='success')submit.textContent=idleLabel;}
  });refresh();
 });
});
