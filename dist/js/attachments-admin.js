if(document.getElementById("wp-media-grid")){function getGridsAttachments(){let t=setInterval(()=>{let e=document.querySelectorAll(".attachments-browser .attachments .attachment");e.length>0&&(clearInterval(t),e.forEach(t=>{t.addEventListener("click",()=>{document.querySelectorAll(".attachments-browser .attachments .attachment.selected").length>0?(createTermsButton(),bindSecondaryToolbarButtons()):removeTermsButton()})}))},200)}function createTermsButton(){if(!document.getElementById("setTermsButton")){let t=document.querySelector(".media-frame-toolbar .media-toolbar-secondary");termsButton=document.createElement("button"),termsButton.setAttribute("type","button"),termsButton.setAttribute("class","button media-button button-primary button-large"),termsButton.setAttribute("id","setTermsButton"),termsButton.innerHTML="Ajouter des tags aux photos selectionnées",t.prepend(termsButton),termsButton.addEventListener("click",()=>{displayTermsForm()})}}function bindSecondaryToolbarButtons(){let t=document.querySelectorAll(".media-frame-toolbar .media-toolbar-secondary .button");t.length>0&&t.forEach(t=>{"setTermsButton"!=t.getAttribute("id")&&t.addEventListener("click",()=>{removeTermsButton()})})}function removeTermsButton(){let t=document.getElementById("setTermsButton");t&&t.remove()}function displayTermsForm(){let t=document.getElementById("attachmentsAddTerms");t?t.classList.remove("hidden"):(termsForm=document.createElement("div"),termsForm.setAttribute("id","attachmentsAddTerms"),termsForm.innerHTML='<div class="choices"><ul class="themes"><p>Thématiques</p></ul><ul class="places"><p>Lieux</p></ul><ul class="seasons"><p>Saisons</p></ul></div><div class="actions"><button class="button button-primary apply">Valider</button><button class=" button close">Annuler</button></div>',getAttachmentsTerms(termsForm))}function getAttachmentsTerms(t){fetch("/wp-json/woody/attachments/terms/get").then(t=>t.json()).then(e=>{if(e){Object.entries(e).forEach(([e,n])=>n.forEach(n=>{let o=t.querySelector("."+e),r=document.createElement("li");r.innerHTML=n.name;let s=document.createElement("input");s.setAttribute("type","checkbox"),s.setAttribute("value",n.id),r.prepend(s),o.append(r)})),document.getElementById("wpbody-content").append(t);let n=t.querySelectorAll('input[type="checkbox"]');bindTermsFormActions(t,n)}}).catch(t=>{console.error("Attachments terms fetch: "+t)})}function bindTermsFormActions(t,e){t.querySelector(".button.close").addEventListener("click",()=>{t.classList.add("hidden"),e.forEach(t=>{t.checked=!1})}),t.querySelector(".button.apply").addEventListener("click",()=>{setPostTerms(t,e)})}function setPostTerms(t,e){let n=[],o=[],r=document.querySelectorAll(".attachments-browser .attachments .attachment.selected");r.length>0&&r.forEach(t=>{n.push(t.dataset.id)}),e.length>0&&e.forEach(t=>{t.checked&&o.push(t.value)}),n.join(","),o.join(","),fetch("/wp-json/woody/attachments/terms/set?attach_ids="+n+"&terms_ids="+o).then(t=>t.json()).then(t=>{1==t&&location.reload()})}function bindLoadMoreButton(){let t=setInterval(()=>{let e=document.querySelector(".load-more-wrapper");e&&(clearInterval(t),document.querySelector(".button.load-more").addEventListener("click",function(){let t=setInterval(()=>{e.querySelector(".load-more-jump").classList.contains("hidden")||(clearInterval(t),getGridsAttachments())},100)}))},200)}getGridsAttachments(),bindLoadMoreButton()}