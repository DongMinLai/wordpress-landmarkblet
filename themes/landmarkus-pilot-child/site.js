document.addEventListener('DOMContentLoaded',()=>{
 document.body.classList.add('lm-shell-js');
 const header=document.querySelector('.lm-original-header');if(!header)return;
 const mobile=header.querySelector('.lm-mobile-menu'),search=header.querySelector('.lm-site-search'),desktop=header.querySelector('.lm-nav');
 const set=(li,open)=>{li.classList.toggle('is-open',open);const b=li.querySelector(':scope > .lm-submenu-toggle');if(b)b.setAttribute('aria-expanded',String(open));if(!open)li.querySelectorAll('li.is-open').forEach(child=>set(child,false));};
 const closeAll=()=>header.querySelectorAll('.lm-site-menu > li.is-open').forEach(li=>set(li,false));
 header.querySelectorAll('.lm-submenu-toggle').forEach(button=>{
  button.addEventListener('click',()=>{const li=button.parentElement,open=!li.classList.contains('is-open');for(const sibling of li.parentElement.children)if(sibling!==li)set(sibling,false);set(li,open);});
  button.addEventListener('keydown',event=>{if(event.key==='ArrowDown'){event.preventDefault();set(button.parentElement,true);button.parentElement.querySelector('.lm-submenu a')?.focus();}});
 });
 desktop.querySelectorAll('.lm-site-menu > li').forEach(li=>{
  li.addEventListener('pointerenter',()=>{if(!matchMedia('(min-width:1101px)').matches)return;closeAll();set(li,true);});
  li.addEventListener('pointerleave',()=>{if(!li.contains(document.activeElement))set(li,false);});
  li.querySelector(':scope > a')?.addEventListener('focus',()=>{closeAll();set(li,true);});
  li.addEventListener('focusout',event=>{if(li.contains(event.relatedTarget))return;requestAnimationFrame(()=>{if(!li.contains(document.activeElement))set(li,false);});});
 });
 mobile.addEventListener('click',event=>{if(event.target.closest('a')){mobile.open=false;closeAll();}});
 mobile.addEventListener('toggle',()=>{if(!mobile.open)closeAll();else search.open=false;});
 search.addEventListener('toggle',()=>{if(search.open){mobile.open=false;closeAll();search.querySelector('input[type="search"]').focus();}});
 document.addEventListener('click',event=>{if(!header.contains(event.target)){mobile.open=false;search.open=false;closeAll();}});
 document.addEventListener('keydown',event=>{
  if(event.key!=='Escape')return;
  if(search.open){search.open=false;search.querySelector('summary').focus();event.preventDefault();return;}
  const active=document.activeElement?.closest('li.is-open');
  if(active){set(active,false);active.querySelector(':scope > .lm-submenu-toggle')?.focus();event.preventDefault();return;}
  if(mobile.open){mobile.open=false;closeAll();mobile.querySelector('summary').focus();event.preventDefault();}else closeAll();
 });
 matchMedia('(min-width:1101px)').addEventListener('change',()=>{mobile.open=false;search.open=false;closeAll();});
});
