(function(){
"use strict";
var colors=[
{name:"Wine",value:"wine",hex:"#6c2034"},
{name:"Rose",value:"rose",hex:"#d28d91"},
{name:"Oat",value:"oat",hex:"#d8c9ae"},
{name:"Sage",value:"sage",hex:"#829985"},
{name:"Blue",value:"blue",hex:"#6e96ac"},
{name:"Navy",value:"navy",hex:"#193d60"}
];
var labels={hoodie:"Hoodie",blanket:"Weighted blanket"};
var discountRates=[0,.09,.18,.28];
var bundlePrices=[89,79,69,59];
var state={
mode:"buy",
quantity:{buy:1,mix:2,bundle:1},
buyType:"hoodie",
buyItems:[{id:1,type:"hoodie",color:"wine",size:"single"}],
mixItems:[{id:2,type:"hoodie",color:"wine",size:"single"},{id:3,type:"blanket",color:"navy",size:"single"}],
bundleItems:[{id:4,type:"hoodie",color:"wine",size:"single"},{id:5,type:"blanket",color:"navy",size:"single"}],
cart:0
};
var nextId=6;
var productOptions=document.getElementById("productOptions");
var offerGrid=document.getElementById("offerGrid");
var pay=document.getElementById("payTotal");
var save=document.getElementById("saveTotal");
var oldTotal=document.getElementById("oldTotal");
var toast=document.getElementById("toast");
var heroImage=document.getElementById("heroImage");
var mixAdd=document.getElementById("mixAdd");
var assetsUrl=window.HLPBDemo&&window.HLPBDemo.assetsUrl?window.HLPBDemo.assetsUrl:"assets/";
if(!productOptions||!offerGrid||!pay||!save||!oldTotal||!toast||!heroImage||!mixAdd||!document.getElementById("mobilePay")||!document.getElementById("addToCart")||!document.getElementById("mobileAdd")||!document.querySelector(".arrow-prev")||!document.querySelector(".arrow-next")||!document.getElementById("countdown"))return;
var reuseInitialMarkup=offerGrid.children.length===4&&productOptions.children.length===1;
function fmt(value){return "£"+Number(value).toFixed(2)}
function roundMoney(value){return Math.round((value+Number.EPSILON)*100)/100}
function makeItem(type,color){return {id:nextId++,type:type||"hoodie",color:color||"wine",size:"single"}}
function ensureCount(items,count,type){
while(items.length<count)items.push(makeItem(type||"hoodie",items.length%2?"navy":"wine"));
if(items.length>count)items.length=count;
return items
}
function currentItems(){
if(state.mode==="buy")return state.buyItems;
if(state.mode==="mix")return state.mixItems;
return state.bundleItems;
}
function currentQuantity(){return state.mode==="mix"?state.mixItems.length:state.quantity[state.mode]}
function typeName(type){return labels[type]||labels.hoodie}
function unitPrice(item){
if(item.type==="hoodie")return 49;
return {single:40,double:50,king:60}[item.size]||40
}
function discountRate(count){return discountRates[Math.min(Math.max(count,1),4)-1]||0}
function bundleSizeExtra(){return {single:0,double:10,king:20}[state.bundleItems[1].size]||0}
function renderSwatches(item){
return '<span class="color-label">Choose colour</span><div class="swatches" role="group" aria-label="Choose '+typeName(item.type).toLowerCase()+' colour">'+colors.map(function(color){return '<button type="button" class="swatch" style="--swatch:'+color.hex+'" data-swatch="'+color.value+'" data-id="'+item.id+'" aria-label="'+color.name+'" aria-pressed="'+(item.color===color.value)+'"></button>'}).join("")+'</div>'
}
function renderSizes(item){
if(item.type!=="blanket")return "";
return '<span class="size-label">Choose size</span><div class="size-options" role="group" aria-label="Choose blanket size">'+["single","double","king"].map(function(size){var title=size.charAt(0).toUpperCase()+size.slice(1);return '<button type="button" class="size-choice" data-size="'+size+'" data-id="'+item.id+'" aria-pressed="'+(item.size===size)+'">'+title+'</button>'}).join("")+'</div>'
}
function itemCard(item,index){
var mode=state.mode,isBundle=mode==="bundle",isMix=mode==="mix";
var title=isBundle?typeName(item.type):(isMix?"Item "+(index+1):"Product "+(index+1));
var canChooseType=isMix||(mode==="buy"&&index===0);
var typeControl=canChooseType?'<select class="item-type-select" data-type="'+item.id+'" aria-label="'+title+' type"><option value="hoodie" '+(item.type==="hoodie"?"selected":"")+'>Hoodie</option><option value="blanket" '+(item.type==="blanket"?"selected":"")+'>Weighted blanket</option></select>':'<span class="type-pill">'+(isBundle?"One per set":typeName(item.type))+"</span>";
var removeControl=isMix?'<button type="button" class="remove-item" data-remove="'+item.id+'" aria-label="Remove item '+(index+1)+'">Remove</button>':'';
var imageBase=item.type==="hoodie"?"card-hoodie-product":"card-blanket-product";var image=imageBase+"-120-20261022.webp?v=20261022-pagespeed-v2";var imageSrcset=assetsUrl+"images/"+imageBase+"-120-20261022.webp?v=20261022-pagespeed-v2 120w, "+assetsUrl+"images/"+imageBase+"-180-20261022.webp?v=20261022-pagespeed-v2 180w, "+assetsUrl+"images/"+imageBase+".webp?v=20261022-pagespeed-v2 200w";
var alt=item.type==="hoodie"?"Burgundy oversized hoodie blanket":"Folded weighted blanket";
var note=mode==="buy"&&index===0?'<span class="item-subtitle">Product type applies to all '+state.quantity.buy+' item'+(state.quantity.buy===1?"":"s")+'</span>':(isBundle?'<span class="item-subtitle">Colour and size apply to every set</span>':'');
return '<article class="product-option"><img class="product-photo" src="'+assetsUrl+'images/'+image+'" srcset="'+imageSrcset+'" sizes="(max-width:380px) 58px, (max-width:520px) 52px, (max-width:700px) 44px, 58px" width="120" height="194" alt="'+alt+'" loading="lazy"><div class="product-info"><div class="product-head"><b class="item-title">'+title+'</b>'+typeControl+removeControl+'</div>'+note+renderSwatches(item)+renderSizes(item)+'</div></article>'
}
function renderItems(){
if(state.mode==="buy"){
ensureCount(state.buyItems,state.quantity.buy,state.buyType);
state.buyItems.forEach(function(item){item.type=state.buyType})
}
if(state.mode==="mix")state.quantity.mix=state.mixItems.length;
if(state.mode==="bundle"){
state.bundleItems[0].type="hoodie";
state.bundleItems[1].type="blanket";
}
productOptions.dataset.mode=state.mode;productOptions.dataset.count=currentItems().length;
productOptions.innerHTML=currentItems().map(itemCard).join("");
mixAdd.hidden=state.mode!=="mix";
mixAdd.disabled=state.mixItems.length>=8;
mixAdd.textContent=state.mixItems.length>=8?"Maximum of 8 items reached":"＋ Add another item · "+state.mixItems.length+" / 8";
}
function offerCard(n){
var active=Math.min(currentQuantity(),4)===n;
var rate=discountRate(n),percent=Math.round(rate*100),badge=n===4?"BEST VALUE":(n===2?"POPULAR":"");
var strong,small,discountText="";
if(state.mode==="buy"){
var reference=state.buyItems[0]||makeItem(state.buyType);
strong=fmt(roundMoney(unitPrice(reference)*(1-rate)));
small=reference.type==="blanket"?"each · "+reference.size:"each";
discountText=n===1?"":"Save "+percent+"%";
}else if(state.mode==="bundle"){
var unit=bundlePrices[n-1]+bundleSizeExtra();
strong=fmt(unit);
small="per set";
discountText=n===1?"":"Save "+Math.round((1-unit/(89+bundleSizeExtra()))*100)+"%";
}else{
strong=n===1?"Regular price":"Save "+percent+"%";
small=n===1?"on your mix":"on your mix";
}
return '<button class="offer-card '+(n===4?"best":"")+'" type="button" data-qty="'+n+'" aria-pressed="'+active+'">'+(badge?'<span class="best-label">'+badge+'</span>':'')+'<b>'+(state.mode==="bundle"?"BUY "+n+" SET"+(n===1?"":"S"):"BUY "+n)+'</b><strong>'+strong+'</strong><small>'+small+'</small>'+(discountText?'<em>'+discountText+'</em>':'')+'</button>'
}
function renderOffers(){
offerGrid.innerHTML=[1,2,3,4].map(offerCard).join("");
}
function currentPricing(){
var quantity=currentQuantity(),items=currentItems(),base,total,count,rate,savings;
if(state.mode==="bundle"){
count=quantity*2;
var extra=bundleSizeExtra();
base=(89+extra)*quantity;
total=(bundlePrices[quantity-1]+extra)*quantity;
}else{
count=items.length;
base=items.reduce(function(sum,item){return sum+unitPrice(item)},0);
rate=discountRate(count);
total=base*(1-rate);
}
base=roundMoney(base);total=roundMoney(total);savings=roundMoney(Math.max(0,base-total));
return {quantity:quantity,count:count,items:items,subtotal:base,savings:savings,total:total}
}
function announce(pricing){
var detail={mode:state.mode,quantity:pricing.quantity,count:pricing.count,items:pricing.items.map(function(item){return {type:item.type,color:item.color,size:item.type==="blanket"?item.size:null,quantity:state.mode==="bundle"?pricing.quantity:1}}),subtotal:pricing.subtotal,savings:pricing.savings,total:pricing.total,currency:"GBP"};
window.dispatchEvent(new CustomEvent("cozyform:selection-change",{detail:detail}));
}
function render(){
if(reuseInitialMarkup)reuseInitialMarkup=false;else{renderItems();renderOffers()}
var headings={buy:["STEP 1 · CHOOSE QUANTITY","STEP 2 · PICK EACH ITEM","Choose one product type; set a colour for every item and size for blankets."],mix:["STEP 1 · CHOOSE HOW MANY ITEMS","STEP 2 · BUILD YOUR MIX","Mix hoodie blankets and weighted blankets. Set each item separately; add up to 8."],bundle:["STEP 1 · CHOOSE NUMBER OF SETS","STEP 2 · PICK YOUR HOODIE + BLANKET","Each set includes one hoodie and one blanket. These options apply to every set."]};
document.getElementById("quantityHeading").textContent=headings[state.mode][0];
document.getElementById("optionsHeading").textContent=headings[state.mode][1];
document.getElementById("modeHint").textContent=headings[state.mode][2];
document.getElementById("subtotalLabel").textContent=state.mode==="bundle"?"Bundle total:":(state.mode==="mix"?"Mix total:":"Items total:");
document.getElementById("countdownLabel").textContent=state.mode==="bundle"?"Cozy bundle deal ends in":(state.mode==="mix"?"Mix & Match deal ends in":"Buy more deal ends in");
document.querySelectorAll(".mode-tab").forEach(function(button){button.setAttribute("aria-pressed",String(button.dataset.mode===state.mode))});
var pricing=currentPricing();
oldTotal.textContent=fmt(pricing.subtotal);
save.textContent="- "+fmt(pricing.savings);
pay.textContent=fmt(pricing.total);
document.getElementById("mobilePay").textContent=fmt(pricing.total);
document.querySelectorAll(".bundle-card").forEach(function(button){button.classList.toggle("is-selected",state.mode==="bundle"&&Number(button.dataset.bundle)===state.quantity.bundle)});
announce(pricing);
}
function changeMode(mode){
state.mode=mode;
render();
}
document.querySelectorAll(".mode-tab").forEach(function(button){button.addEventListener("click",function(){changeMode(button.dataset.mode)})});
offerGrid.addEventListener("click",function(event){
var button=event.target.closest("[data-qty]");if(!button)return;
var quantity=Number(button.dataset.qty);
if(state.mode==="buy"){
state.quantity.buy=quantity;
ensureCount(state.buyItems,quantity,state.buyType);
state.buyItems.forEach(function(item){item.type=state.buyType});
}else if(state.mode==="mix"){
ensureCount(state.mixItems,quantity,"hoodie");
state.quantity.mix=quantity;
}else state.quantity.bundle=quantity;
render();
});
productOptions.addEventListener("change",function(event){
var control=event.target.closest("[data-type]");if(!control)return;
var type=control.value;
if(state.mode==="buy"){
state.buyType=type;
state.buyItems.forEach(function(item){item.type=type;if(type==="blanket"&&!item.size)item.size="single"});
}else if(state.mode==="mix"){
var item=state.mixItems.find(function(candidate){return candidate.id===Number(control.dataset.type)});
if(item){item.type=type;if(type==="blanket"&&!item.size)item.size="single"}
}
render();
});
productOptions.addEventListener("click",function(event){
var remove=event.target.closest("[data-remove]");
if(remove&&state.mode==="mix"){
if(state.mixItems.length>1){state.mixItems=state.mixItems.filter(function(item){return item.id!==Number(remove.dataset.remove)});state.quantity.mix=state.mixItems.length;render()}
return
}
var swatch=event.target.closest("[data-swatch]");
if(swatch){var item=currentItems().find(function(candidate){return candidate.id===Number(swatch.dataset.id)});if(item){item.color=swatch.dataset.swatch;render()}return}
var size=event.target.closest("[data-size]");
if(size){var sized=currentItems().find(function(candidate){return candidate.id===Number(size.dataset.id)});if(sized){sized.size=size.dataset.size;render()}}
});
mixAdd.addEventListener("click",function(){
if(state.mode==="mix"&&state.mixItems.length<8){state.mixItems.push(makeItem("hoodie",colors[state.mixItems.length%colors.length].value));state.quantity.mix=state.mixItems.length;render()}
});
document.querySelectorAll(".bundle-card").forEach(function(button){
button.addEventListener("click",function(){state.quantity.bundle=Number(button.dataset.bundle);changeMode("bundle");document.getElementById("shop").scrollIntoView({behavior:"smooth"})})
});
function addToCart(){
var pricing=currentPricing();state.cart+=pricing.count;
toast.textContent=state.cart+" cosy item"+(state.cart===1?"":"s")+" added to your demo bag";
toast.classList.add("show");window.clearTimeout(addToCart.timer);addToCart.timer=window.setTimeout(function(){toast.classList.remove("show")},2200)
}
document.getElementById("addToCart").addEventListener("click",addToCart);
document.getElementById("mobileAdd").addEventListener("click",addToCart);
var thumbs=Array.prototype.slice.call(document.querySelectorAll(".thumb"));
var slide=0;
function setSlide(index){
slide=(index+thumbs.length)%thumbs.length;
var button=thumbs[slide];
heroImage.srcset=button.dataset.srcset||"";
heroImage.sizes="(max-width:700px) calc(100vw - 28px), 50vw";
heroImage.src=button.dataset.image;
heroImage.alt=button.dataset.alt;
thumbs.forEach(function(item,i){item.classList.toggle("active",i===slide);item.setAttribute("aria-pressed",String(i===slide));item.setAttribute("aria-current",String(i===slide))})
}
thumbs.forEach(function(button,index){button.addEventListener("click",function(){setSlide(index)})});
document.querySelector(".arrow-prev").addEventListener("click",function(){setSlide(slide-1)});
document.querySelector(".arrow-next").addEventListener("click",function(){setSlide(slide+1)});
var remaining=8*60+42;
function tick(){var minutes=Math.floor(remaining/60),seconds=remaining%60;document.getElementById("countdown").textContent=String(minutes).padStart(2,"0")+":"+String(seconds).padStart(2,"0");if(remaining>0)remaining--}
tick();window.setInterval(tick,1000);
render();
})();
