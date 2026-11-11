/* Native GET forms remain the no-JavaScript fallback. No external search service. */
(()=>{'use strict';
 if(!window.lmSmartSearch)return;
 const config=window.lmSmartSearch;
 document.querySelectorAll('#lm-desktop-search,#lm-header-search,#lm-catalog-search').forEach((input,index)=>{
  const form=input.form;if(!form)return;
  input.maxLength=120;input.autocomplete='off';input.spellcheck=false;
  const panel=document.createElement('div');panel.className='lm-search-suggestions';panel.hidden=true;
  const caption=document.createElement('p'),list=document.createElement('ul'),footer=document.createElement('a');
  const status=document.createElement('span');status.className='screen-reader-text';status.setAttribute('role','status');status.setAttribute('aria-live','polite');form.append(status);
  list.id='lm-search-list-'+index;list.setAttribute('role','listbox');list.setAttribute('aria-label','Suggested products');
  panel.append(caption,list,footer);document.body.append(panel);
  input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-controls',list.id);input.setAttribute('aria-expanded','false');
  let timer,controller,sequence=0,active=-1,items=[],last='',suppressFocus=false;
  const close=()=>{clearTimeout(timer);controller?.abort();sequence++;panel.hidden=true;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');active=-1;};
  const position=()=>{if(panel.hidden)return;const r=input.getBoundingClientRect(),viewport=document.documentElement.clientWidth,width=Math.min(520,viewport-24),left=Math.max(12,Math.min(r.left,viewport-width-12));panel.style.width=width+'px';panel.style.left=left+'px';panel.style.top=Math.max(8,Math.min(r.bottom+8,innerHeight-120))+'px';panel.style.maxHeight=Math.max(100,innerHeight-r.bottom-20)+'px';};
  const activate=n=>{active=n;items.forEach((e,i)=>e.setAttribute('aria-selected',String(i===n)));if(n<0)input.removeAttribute('aria-activedescendant');else{input.setAttribute('aria-activedescendant',items[n].id);items[n].scrollIntoView({block:'nearest'});}};
  const request=async()=>{
   const q=input.value.trim();if(q.length<2){close();return;}controller?.abort();controller=new AbortController();const turn=++sequence;
   status.textContent='Searching products…';
   const data=new FormData(form),url=new URL(config.endpoint);url.searchParams.set('action','landmarkus_smart_search');url.searchParams.set('q',q);
   const catalog=input.id==='lm-catalog-search';url.searchParams.set('scope',catalog?config.scope:'global');if(catalog){url.searchParams.set('term',config.term);for(const key of ['filter_color','filter_size','lm_skip'])if(data.has(key))url.searchParams.set(key,data.get(key));}
   try{
    const response=await fetch(url,{signal:controller.signal,credentials:'same-origin',cache:'no-store'});if(!response.ok)throw new Error('Search unavailable');const payload=await response.json();if(turn!==sequence||input!==document.activeElement)return;if(!payload.success)throw new Error('Search unavailable');const result=payload.data;
    list.replaceChildren();items=[];active=-1;input.removeAttribute('aria-activedescendant');
    caption.textContent=[...result.notices,...result.conditions.map(c=>c.dimension.charAt(0).toUpperCase()+c.dimension.slice(1)+': '+c.label)].join(' · ')||(result.total?'Suggested products':'No matching products. Try another SKU or condition.');
    for(const product of result.products){const row=document.createElement('li');row.id=list.id+'-'+items.length;row.setAttribute('role','option');row.setAttribute('aria-selected','false');row.dataset.url=product.url;
     const img=document.createElement('img');img.src=product.image;img.alt='';img.width=56;img.height=56;
     const body=document.createElement('span'),sku=document.createElement('small'),title=document.createElement('span'),price=document.createElement('span');sku.textContent=product.sku;title.textContent=product.title;title.className='lm-search-product-title';price.className='lm-search-product-price';if(product.price_html)price.innerHTML=product.price_html;else price.textContent=product.price_label;body.append(sku,title,price);row.append(img,body);row.addEventListener('pointerdown',e=>e.preventDefault());row.addEventListener('click',()=>location.assign(product.url));list.append(row);items.push(row);
    }
    const full=new URL(form.action,location.href);for(const [key,value] of data)full.searchParams.set(key,value);full.searchParams.set(input.name,q);footer.href=full;footer.textContent=result.total?'View all '+result.total+' results':'Search the full results page';
    footer.onkeydown=e=>{if(e.key==='Escape'){e.preventDefault();close();suppressFocus=true;input.focus();}};
    panel.hidden=false;input.setAttribute('aria-expanded','true');position();status.textContent=result.total+' matching products. Use the arrow keys to explore suggestions.';last=q;
   }catch(error){if(error.name==='AbortError'||turn!==sequence)return;close();status.textContent='Suggestions are unavailable. Press Enter to search.';}
  };
  input.addEventListener('input',()=>{clearTimeout(timer);controller?.abort();sequence++;panel.hidden=true;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');timer=setTimeout(request,250);});
  input.addEventListener('focus',()=>{if(suppressFocus){suppressFocus=false;return;}if(input.value.trim().length>=2){if(last===input.value.trim()&&items.length){panel.hidden=false;input.setAttribute('aria-expanded','true');position();}else timer=setTimeout(request,150);}});
  input.addEventListener('keydown',e=>{if(panel.hidden)return;if(e.key==='Escape'){e.preventDefault();e.stopPropagation();close();}else if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();if(items.length)activate((active+(e.key==='ArrowDown'?1:-1)+items.length)%items.length);}else if(e.key==='Enter'&&active>=0){e.preventDefault();location.assign(items[active].dataset.url);}else if(e.key==='Tab'&&!e.shiftKey){e.preventDefault();footer.focus();}});
  input.addEventListener('blur',()=>setTimeout(()=>{if(!panel.contains(document.activeElement)&&document.activeElement!==input)close();},0));
  panel.addEventListener('focusout',()=>setTimeout(()=>{if(!panel.contains(document.activeElement)&&document.activeElement!==input)close();},0));
  document.addEventListener('pointerdown',e=>{if(e.target!==input&&!panel.contains(e.target))close();});
  input.closest('details')?.addEventListener('toggle',e=>{if(!e.currentTarget.open)close();});
  form.addEventListener('submit',close);window.addEventListener('resize',position);window.addEventListener('scroll',position,{passive:true});
 });
})();
