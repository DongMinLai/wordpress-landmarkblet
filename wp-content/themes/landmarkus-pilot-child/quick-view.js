document.addEventListener('DOMContentLoaded', () => {
 const dialog=document.getElementById('lm-quick-view-dialog');
 if(!dialog || typeof dialog.showModal!=='function')return;
 const content=dialog.querySelector('.lm-qv-content'),close=dialog.querySelector('.lm-qv-close');
 let opener=null,scrollY=0;
 const thumbnails=()=>[...content.querySelectorAll('.lm-qv-thumbnail')];
 const selectPhoto=(button,focus=false)=>{
  if(!button)return;
  const image=content.querySelector('.lm-qv-main-image');
  image.srcset=button.dataset.srcset||'';image.src=button.dataset.src;image.alt=button.dataset.alt;
  for(const thumb of thumbnails()){const active=thumb===button;thumb.setAttribute('aria-pressed',String(active));thumb.tabIndex=active?0:-1;}
  content.querySelector('.lm-qv-photo-status').textContent=`Photo ${Number(button.dataset.index)+1} of ${thumbnails().length}`;
  if(focus){button.focus({preventScroll:true});button.scrollIntoView({block:'nearest',inline:'nearest',behavior:'instant'});}
 };
 for(const button of document.querySelectorAll('.lm-quick-view')){
  const template=document.getElementById('lm-qv-product-'+button.dataset.lmProduct);
  if(!template)continue;
  button.hidden=false;
  button.addEventListener('click',()=>{
   content.replaceChildren(template.content.cloneNode(true));opener=button;scrollY=window.scrollY;
   document.documentElement.classList.add('lm-qv-open');button.setAttribute('aria-expanded','true');dialog.showModal();dialog.scrollTop=0;close.focus({preventScroll:true});
  });
 }
 close.addEventListener('click',()=>dialog.close());
 // 只有起止均在弹窗外才关闭，避免拖动图片/选文字后误关。
 let backdropDown=false;
 dialog.addEventListener('pointerdown',event=>{const r=dialog.getBoundingClientRect();backdropDown=event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom;});
 dialog.addEventListener('click',event=>{
  const r=dialog.getBoundingClientRect(),outside=event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom;
  if(backdropDown&&outside){dialog.close();return;}backdropDown=false;
  const thumb=event.target.closest('.lm-qv-thumbnail');if(thumb)selectPhoto(thumb);
 });
 dialog.addEventListener('keydown',event=>{
  if(event.key==='Tab'){
   const focusable=[...dialog.querySelectorAll('button,a[href],[tabindex]')].filter(e=>!e.disabled&&e.tabIndex>=0&&e.getClientRects().length);
   const first=focusable[0],last=focusable[focusable.length-1];
   if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}
   else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}
   return;
  }
  const thumb=event.target.closest('.lm-qv-thumbnail');if(!thumb)return;
  const all=thumbnails(),index=all.indexOf(thumb);let next;
  if(event.key==='ArrowRight')next=(index+1)%all.length;
  if(event.key==='ArrowLeft')next=(index-1+all.length)%all.length;
  if(event.key==='Home')next=0;if(event.key==='End')next=all.length-1;
  if(next!==undefined){event.preventDefault();selectPhoto(all[next],true);}
 });
 dialog.addEventListener('close',()=>{
  document.documentElement.classList.remove('lm-qv-open');content.replaceChildren();
  if(opener){opener.setAttribute('aria-expanded','false');window.scrollTo({top:scrollY,behavior:'instant'});opener.focus({preventScroll:true});opener=null;}
 });
});
