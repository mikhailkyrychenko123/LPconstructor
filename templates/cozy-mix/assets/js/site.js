(function(){
"use strict";
var mobileMenus=document.querySelectorAll(".site-header .mobile-menu");
mobileMenus.forEach(function(menu){menu.addEventListener("toggle",function(){if(!menu.open)return;mobileMenus.forEach(function(other){if(other!==menu)other.open=false})})});
document.addEventListener("click",function(event){if(!event.target.closest(".site-header .mobile-menu")){mobileMenus.forEach(function(menu){menu.open=false})}});
document.addEventListener("keydown",function(event){if(event.key==="Escape")mobileMenus.forEach(function(menu){menu.open=false})});
mobileMenus.forEach(function(menu){menu.addEventListener("click",function(event){if(event.target.closest(".mobile-menu-panel a"))menu.open=false})});
if(window.HLPBCartData)return;
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

(function(){
'use strict';
/* HLPB_COZY_CALCULATOR_RUNTIME */
const calculatorRoot=document.querySelector('.cozy-calculator-section');if(!calculatorRoot)return;const $=selector=>calculatorRoot.querySelector(selector),$$=selector=>[...calculatorRoot.querySelectorAll(selector)];
const paths={
case:'<rect x="3" y="7" width="18" height="14" rx="1"/><path d="M8 7V4h8v3M7 11v6m10-6v6"/>',inbox:'<path d="M3 5h18v14H3zM3 13h5l2 3h4l2-3h5"/>',meeting:'<circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20v-2a6 6 0 0 1 12 0v2m1-5a5 5 0 0 1 7 4v1"/>',plant:'<path d="M12 21v-9M12 14C5 14 4 9 4 6c5 0 8 2 8 8ZM12 12c0-6 4-9 8-9 0 5-2 9-8 9ZM7 22h10"/>',sock:'<path d="M8 3h8v10l5 3-3 5H7v-5l3-3V3M8 8h8"/>',snack:'<path d="M5 7h14l-1 14H6L5 7Zm3 0 1-4h6l1 4M9 12h6M9 16h4"/>',chat:'<path d="M3 4h18v13H9l-6 4V4Z"/><path d="M7 9h10M7 13h7"/>',tools:'<path d="m4 5 6 6m-7-7 5 1 3 5-4 4-5-4-1-5Zm9 9 8 8m-4-11 5-5 3 3-5 5"/>',mug:'<path d="M5 9h12v12H5zM17 12h2a3 3 0 0 1 0 6h-2M8 5c-1-1 1-2 0-3m4 3c-1-1 1-2 0-3"/>',paw:'<ellipse cx="12" cy="16" rx="6" ry="5"/><circle cx="5" cy="9" r="2"/><circle cx="10" cy="6" r="2"/><circle cx="16" cy="6" r="2"/><circle cx="20" cy="10" r="2"/>',calendar:'<rect x="3" y="5" width="18" height="16" rx="1"/><path d="M7 3v5m10-5v5M3 10h18M7 14h3m3 0h3m-9 3h3"/>',coins:'<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v5c0 2 3 3 6 3m6-5c0-2 3-3 6-3s6 1 6 3-3 3-6 3"/><path d="M3 12v5c0 2 3 3 6 3m6-4v2c0 2 3 3 6 3s6-1 6-3v-5"/>',hanger:'<path d="M12 5a2 2 0 1 0-2 2m2 0 9 7H3l9-7Zm-7 7 2 8h10l2-8"/>',clock:'<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',dumbbell:'<path d="M3 9v6m4-9v12m0-9h10m0-3v12m4-9v6"/>',book:'<path d="M4 5h9a3 3 0 0 1 3 3v13a3 3 0 0 0-3-3H4zM20 5h-4v16a3 3 0 0 1 3-3h1z"/>',bolt:'<path d="m13 2-9 12h7l-1 8 10-13h-7z"/>',plate:'<circle cx="12" cy="13" r="8"/><circle cx="12" cy="13" r="4"/><path d="M3 3v7m-2-7v4m4-4v4M22 3v18"/>',leaf:'<path d="M22 3C10 3 4 8 4 15a6 6 0 0 0 6 6c7 0 12-6 12-18ZM4 21c4-6 8-9 14-13"/>',film:'<rect x="4" y="5" width="16" height="14" rx="2"/><path d="m10 9 5 3-5 3zM8 22h8"/>',cloud:'<path d="M6 18a4 4 0 1 1 1-8 6 6 0 0 1 12 1 3.5 3.5 0 1 1 0 7H6Z"/><path d="m9 21-1 2m6-2-1 2m6-2-1 2"/>'};
const icon=n=>'<svg viewBox="0 0 24 24" aria-hidden="true">'+(paths[n]||paths.case)+'</svg>';
const scenarios=[
{id:"carry",group:"Useful tools",name:"The carry-on calculator",title:"What makes the cut?",desc:"Plan a small travel wardrobe around trip length, weather shifts, and laundry chances.",icon:"case",sl:[["Days away","calendar",1,14,3,"One day","Two weeks","days"],["Weather changes","cloud",1,4,3,"Steady forecast","Four seasons","shifts"],["Laundry chances","waves",0,4,2,"No laundry","Every few days","chances"]]},
{id:"inbox",group:"Funny science",name:"Inbox triage, with optimism",title:"How big is the email mountain?",desc:"Estimate the time to clear a backlog and protect a little focus time. The inbox insists it is not a mountain.",icon:"inbox",sl:[["Unread messages","chat",10,300,80,"A few","A small novel","emails"],["Minutes per reply","clock",1,12,3,"Quick note","Long answer","min"],["Focus sessions","clock",1,8,3,"One sprint","A whole day","sessions"]]},
{id:"meeting",group:"Funny science",name:"Meeting escape velocity",title:"Can this meeting be an email?",desc:"A totally unofficial assessment of agenda length, attendees, and coffee proximity.",icon:"meeting",sl:[["Agenda slides","book",1,40,12,"One slide","Deck trilogy","slides"],["People invited","meeting",2,30,9,"Small huddle","Town hall","people"],["Coffee distance","mug",0,10,4,"At your elbow","Another building","min"]]},
{id:"plant",group:"Funny science",name:"Plant parent panic",title:"Will the fern forgive you?",desc:"Calculate your houseplant's optimism against your actual watering habits.",icon:"plant",sl:[["Days since watering","clock",0,21,6,"Just watered","A dry season","days"],["Plants in the room","plant",1,30,7,"One leafy friend","Indoor jungle","plants"],["Holiday length","calendar",0,21,5,"Back tomorrow","Long weekend plus","days"]]},
{id:"sock",group:"Funny science",name:"The laundry sock mystery",title:"Where do the socks go?",desc:"Feed the laundry gremlin some numbers. It refuses to provide references.",icon:"sock",sl:[["Loads this week","waves",1,12,4,"One load","Laundry mountain","loads"],["People at home","meeting",1,8,3,"Just you","Full house","people"],["Mismatched pairs","sock",0,10,2,"All accounted","Sock witness","pairs"]]},
{id:"snack",group:"Funny science",name:"Snack logistics office",title:"How many snacks is enough?",desc:"The answer depends on travel time, companions, and how dramatic the afternoon gets.",icon:"snack",sl:[["Hours out","clock",1,12,5,"Quick errand","Odyssey","hours"],["Snack companions","meeting",0,8,2,"Solo mission","Snack committee","people"],["Hunger plot twists","bolt",0,5,3,"Very composed","Cinema-level","twists"]]},
{id:"chat",group:"Funny science",name:"Group chat vacation",title:"Will the group decide?",desc:"Estimate the number of messages between “anywhere is fine” and an actual booking.",icon:"chat",sl:[["People in chat","meeting",2,18,7,"Tiny group","Entire friend group","people"],["Destination choices","leaf",1,12,5,"One obvious place","Spreadsheet of places","ideas"],["“I'm flexible” replies","chat",0,20,8,"None yet","Everyone, somehow","replies"]]},
{id:"tools",group:"Funny science",name:"DIY confidence meter",title:"How many screws remain?",desc:"A playful forecast of project confidence. The mystery screw is in the model.",icon:"tools",sl:[["Instructions pages","book",1,80,14,"Quick diagram","A small novel","pages"],["Tools on the floor","tools",1,15,6,"One screwdriver","Hardware shop","tools"],["Mystery parts","tools",0,12,3,"All identified","Bag of surprises","parts"]]},
{id:"mug",group:"Funny science",name:"Coffee runway",title:"How long until the next cup?",desc:"Estimate your caffeine runway using cups, meetings, and distance to the kettle.",icon:"mug",sl:[["Cups already enjoyed","mug",0,8,2,"Just woke up","Barista knows you","cups"],["Meetings ahead","meeting",0,8,3,"Clear calendar","Wall-to-wall","meetings"],["Kettle distance","clock",0,15,4,"Right there","A small expedition","min"]]},
{id:"paw",group:"Funny science",name:"Pet patience index",title:"Is this the pet's chair now?",desc:"A household seating analysis, reviewed by a panel of one very confident pet.",icon:"paw",sl:[["Pets in residence","paw",1,5,1,"One supervisor","Tiny committee","pets"],["Free seats","chair",0,8,3,"Plenty","Floor is popular","seats"],["Treat negotiations","snack",0,10,4,"No bargaining","Treat diplomacy","treats"]]},
{id:"calendar",group:"Useful tools",name:"Week, with breathing room",title:"How much margin is left?",desc:"Balance fixed commitments, flexible tasks, and the hours you want to keep for yourself.",icon:"calendar",sl:[["Fixed hours","clock",5,50,24,"Light week","Full calendar","hours"],["Flexible tasks","calendar",1,20,8,"A few","Many moving parts","tasks"],["Hours to protect","clock",2,25,8,"A little space","A proper boundary","hours"]]},
{id:"coins",group:"Useful tools",name:"Split-the-bill helper",title:"What does everyone owe?",desc:"A clean estimate for a shared bill, tip, and optional shared extra.",icon:"coins",sl:[["Bill amount","coins",20,600,145,"Small lunch","Big celebration","€"],["People sharing","meeting",2,16,5,"Two people","A full table","people"],["Tip percent","star",0,25,10,"No tip","Generous tip","%"]]},
{id:"hanger",group:"Useful tools",name:"Cost-per-wear reality check",title:"Will this earn its hanger?",desc:"Turn purchase price and expected wears into an easy cost-per-wear estimate.",icon:"hanger",sl:[["Price","coins",10,250,49,"High-street find","Investment piece","€"],["Wears per month","hanger",1,20,8,"Occasional","Wardrobe regular","wears"],["Months in rotation","calendar",1,48,18,"One season","For years","months"]]},
{id:"clock",group:"Useful tools",name:"Leave-on-time buffer",title:"When should you leave?",desc:"Add a realistic preparation window and travel buffer to your arrival time.",icon:"clock",sl:[["Get-ready time","clock",5,120,35,"Almost instant","Full getting-ready arc","min"],["Travel time","clock",5,150,40,"Around the corner","Cross-town","min"],["Buffer","clock",0,45,15,"Door-to-door","Unexpected plot","min"]]},
{id:"dumbbell",group:"Useful tools",name:"Gentle movement planner",title:"A week that can actually happen.",desc:"Shape a realistic movement plan around available days and session length.",icon:"dumbbell",sl:[["Days available","calendar",1,7,3,"One day","Every day","days"],["Minutes each time","clock",10,90,30,"A short walk","Long session","min"],["Rest days wanted","moon",0,6,2,"Keep moving","Take your time","days"]]},
{id:"book",group:"Useful tools",name:"Reading finish-line",title:"How many chapters tonight?",desc:"Estimate reading sessions from the book length, your pace, and spare time.",icon:"book",sl:[["Pages left","book",10,800,180,"A chapter","Doorstopper","pages"],["Pages per hour","clock",5,80,35,"Slow and cosy","Fast page-turner","pages/hr"],["Reading time nightly","clock",5,180,30,"Before bed","Lost in the book","min"]]},
{id:"bolt",group:"Useful tools",name:"Home energy nudge",title:"Where can a little energy go?",desc:"A simple illustrative estimate of hours and devices to help plan a lower-energy day.",icon:"bolt",sl:[["Devices switched off","bolt",0,20,5,"One standby light","Power-down round","devices"],["Hours away","clock",1,16,8,"Out for lunch","All afternoon","hours"],["Laundry temperature","cloud",20,60,30,"Cool wash","Hot cycle","°C"]]},
{id:"plate",group:"Useful tools",name:"Dinner portions, minus guesswork",title:"How much dinner should you make?",desc:"Estimate portions and a small buffer from the number of guests and appetite.",icon:"plate",sl:[["People eating","meeting",1,24,6,"Just the household","Friends are coming","people"],["Extra portions","plate",0,12,2,"No leftovers","Future lunch","portions"],["Appetite level","snack",1,3,2,"Light","Very hungry","level"]]},
{id:"leaf",group:"Useful tools",name:"Garden watering rhythm",title:"How thirsty is the garden?",desc:"Plan a rough weekly watering rhythm from heat, containers, and time away.",icon:"leaf",sl:[["Warm days ahead","cloud",0,7,4,"Cool week","Summer spell","days"],["Pots to check","plant",0,30,8,"A few pots","Balcony jungle","pots"],["Days away","calendar",0,14,2,"Home all week","Holiday mode","days"]]},
{id:"film",group:"Funny science",name:"What-to-watch summit",title:"Will you pick a film?",desc:"Estimate decision time given the group size and the number of “maybe” titles.",icon:"film",sl:[["People voting","meeting",1,10,4,"Solo curator","Cinema committee","people"],["Saved titles","film",1,100,27,"One clear choice","Watchlist archive","films"],["Snack refills","snack",0,8,3,"One bowl","Intermission service","refills"]]}
];
const calculatorSection=calculatorRoot,calculatorSelection=calculatorSection&&calculatorSection.dataset.hlpbCalculators;
if(calculatorSelection){try{const enabled=new Set(JSON.parse(calculatorSelection));const visible=scenarios.filter(item=>enabled.has(item.id));if(visible.length)scenarios.splice(0,scenarios.length,...visible)}catch(error){}}
const sym={calendar:"calendar",clock:"clock",cloud:"cloud",waves:"leaf",chat:"chat",book:"book",mug:"mug",plant:"plant",sock:"sock",bolt:"bolt",meeting:"meeting",snack:"snack",tools:"tools",paw:"paw",chair:"chair",moon:"moon",coins:"coins",hanger:"hanger",star:"star",film:"film"};
paths.chair='<path d="M6 13h12v7H6zM8 13V8a4 4 0 0 1 8 0v5M8 20v3m8-3v3"/>';paths.moon='<path d="M20 15a8 8 0 0 1-11-11 9 9 0 1 0 11 11Z"/>';
function initMenu(){const m=$("#menu"),groups=[["Funny science","Funny science · with affectionate side-eye","play"],["Useful tools","Useful tools · actual small calculations","useful"]];let html="";groups.forEach(g=>{const entries=scenarios.filter(x=>x.group===g[0]);if(!entries.length)return;html+="<div class=\"group-label\">"+g[1]+"</div>";entries.forEach(x=>html+="<button class=\"option\" role=\"option\" data-id=\""+x.id+"\">"+icon(x.icon)+"<span>"+x.name+"</span><small>"+g[2]+"</small></button>")});m.innerHTML=html;m.querySelectorAll(".option").forEach(b=>b.onclick=()=>{choose(b.dataset.id);m.classList.remove("open");$("#pick").setAttribute("aria-expanded","false")})}
let active=scenarios[0];function choose(id){active=scenarios.find(x=>x.id===id)||scenarios[0];$("#calcIcon").innerHTML=icon(active.icon);$("#calcGroup").textContent=active.group;$("#calcName").textContent=active.title;$("#calcDesc").textContent=active.desc;$("#pick").firstChild.textContent=active.name;$$(".option").forEach(b=>b.setAttribute("aria-selected",b.dataset.id===active.id?"true":"false"));const box=$("#sliders");box.innerHTML=active.sl.map((s,i)=>'<div class="slider"><div class="slider-top"><label class="slider-label">'+icon(s[1])+s[0]+'</label><span class="slider-value" id="val'+i+'"></span></div><div class="range"><span class="bubble" id="bubble'+i+'"></span><input type="range" min="'+s[2]+'" max="'+s[3]+'" value="'+s[4]+'" data-i="'+i+'"><div class="ends"><span>'+s[5]+'</span><span>'+s[6]+'</span></div></div></div>').join("");box.querySelectorAll("input").forEach(x=>x.oninput=update);update()}
function update(){const v=[...$("#sliders").querySelectorAll("input")].map(x=>+x.value),r=$("#sliders").querySelectorAll("input");v.forEach((n,i)=>{let s=active.sl[i],pct=(n-s[2])/(s[3]-s[2])*100;r[i].style.setProperty("--pct",pct+"%");$("#bubble"+i).style.setProperty("--pct",pct+"%");$("#bubble"+i).textContent=n;$("#val"+i).textContent=n+" "+s[7]});
let rows=[],verdict="",joke="",score=0;
switch(active.id){
case"carry":rows=[["Outfit ideas",Math.max(1,Math.ceil(v[0]/(v[2]+1)))+" looks"],["Core pieces",Math.max(3,Math.ceil(v[0]/2)+v[1])+" pieces"],["Weather layers",v[1]+" layers + 1 spare"]];score=v[0]+v[1]*2-v[2];verdict=score<8?"Carry-on confidence":score<15?"One careful tote":"The suitcase has entered the chat";joke=score<8?"A tidy edit. Leave room for the souvenir you definitely planned.":"Pack one spare, then close the case before your 'just in case' pile gets a vote.";break;
case"inbox":{let hours=(v[0]*v[1]/60/v[2]).toFixed(1);rows=[["Reply time",hours+" focus hours"],["At "+v[2]+" min per session",Math.ceil(v[0]*v[1]/60/v[2])+" sessions"],["Best next move",v[0]>100?"Sort, then reply":"Start with the quick wins"]];score=+hours;verdict=score<2?"Inbox in sight":"A small email expedition";joke=score<2?"Close the loop, then close the tab.":"The inbox has formed a committee. Give it a timer.";break}
case"meeting":rows=[["Agenda-to-attendee ratio",(v[0]/v[1]).toFixed(1)],["Coffee round trip",v[2]*2+" minutes"],["Meeting forecast",v[0]>20?"Bring a bookmark":"Bring one good question"]];score=v[0]+v[1]+v[2];verdict=score<20?"Could be a crisp 15":"Could this be a note?";joke="If the agenda has a sequel, the email option remains available.";break;
case"plant":rows=[["Watering status",v[0]<3?"Fern is thriving":v[0]<9?"Fern is hopeful":"Fern has thoughts"],["Leafy household",v[1]+" plants"],["Return-day check",v[2]+" days away"]];score=v[0]+v[2];verdict=score<7?"The fern believes in you":"The fern has a backup plan";joke=score<7?"Look at you, remembering the living room has flora.":"Perhaps ask a neighbour. The fern has drafted a note.";break;
case"sock":rows=[["Pair-matching forecast",Math.max(0,v[0]*2-v[2])+" socks in circulation"],["Laundry batches",v[0]+" loads"],["Case file",v[2]?"Open, obviously":"Suspiciously tidy"]];score=v[2]+v[0];verdict=v[2]<2?"Pairs accounted for":"The sock drawer has lore";joke="The washing machine maintains its right to remain silent.";break;
case"snack":rows=[["Snack allocation",Math.ceil(v[0]*1.2+v[1]+v[2])+" snacks"],["Emergency reserve",Math.max(1,Math.ceil(v[2]/2))+" extras"],["Crumb forecast",v[1]>3?"Seat-level":"Contained"]];score=v[0]+v[1]*2+v[2]*2;verdict=score<15?"Prepared, not dramatic":"Snack operations";joke="One snack for the journey, one for the story about the journey.";break;
case"chat":rows=[["Likely messages",v[0]*v[1]+v[2]+" replies"],["Decision ratio",Math.max(1,Math.ceil(v[1]/Math.max(1,v[0]-1)))+" rounds"],["Booking outlook",v[2]>12?"Poll required":"A brave volunteer"]];score=v[0]+v[1]+v[2];verdict=score<20?"Plan may emerge":"Create a poll, captain";joke="“Anywhere is fine” has been logged as a non-binding statement.";break;
case"tools":rows=[["Parts to identify",v[2]+" mystery bits"],["Confidence reserve",Math.max(0,v[1]-v[2])+" tools on hand"],["Instruction-to-patience",Math.round(v[0]/Math.max(1,v[1]))+" pages per tool"]];score=v[0]+v[2]*3;verdict=v[2]<3?"DIY optimism":"Keep the receipt";joke="Set the mystery screw aside. It may be the final boss.";break;
case"mug":rows=[["Caffeine runway",Math.max(0,Math.round((v[0]+1)*45-v[1]*15-v[2]))+" minutes"],["Kettle expedition",v[2]+" minutes each way"],["Meeting-friendly cups",Math.max(0,4-v[1])+" cups"]];score=v[1]*2+v[2]-v[0];verdict=score<5?"Cup within reach":"Kettle break advised";joke="Hydration has requested a speaking slot too.";break;
case"paw":rows=[["Available seating",Math.max(0,v[1]-v[0])+" seats after claims"],["Treat diplomacy",v[2]+" negotiations"],["Likely owner",v[0]>v[1]?"The pet":"Still disputed"]];score=v[0]*2-v[1]+v[2];verdict=score<3?"Humans may sit":"Furniture is a pet benefit";joke="The chair was yours until the warm patch appeared.";break;
case"calendar":{let free=168-v[0]-v[1]*1.5-v[2];rows=[["Unallocated week",Math.max(0,Math.round(free))+" hours"],["Protected hours",v[2]+" hours"],["Task load",v[1]+" flexible tasks"]];score=v[0]+v[1]*2-v[2];verdict=free>45?"Room to breathe":"A full-looking week";joke=free>45?"Keep one blank square blank.":"Your calendar would like a tiny boundary.";break}
case"coins":{let due=v[0]*(1+v[2]/100)/v[1];rows=[["Each person's share","€"+due.toFixed(2)],["Tip included","€"+(v[0]*v[2]/100).toFixed(2)],["Split across",v[1]+" people"]];score=due;verdict="A neat split: €"+due.toFixed(2);joke="Everyone can now stop doing restaurant maths on a napkin.";break}
case"hanger":{let cpw=v[0]/Math.max(1,v[1]*v[2]);rows=[["Estimated wears",v[1]*v[2]+" times"],["Cost per wear","€"+cpw.toFixed(2)],["Rotation outlook",v[1]>6?"Wardrobe regular":"Occasional guest"]];score=cpw;verdict=cpw<1?"Hanger well earned":"Let it audition first";joke="The most sustainable item is still the one you actually wear.";break}
case"clock":{let leave=v[0]+v[1]+v[2];rows=[["Door-to-door plan",leave+" minutes"],["Suggested leave",leave+" min before"],["Buffer included",v[2]+" minutes"]];score=leave;verdict=v[2]>10?"A little breathing room":"Shoes on, keys found";joke="This estimate assumes the keys are where you left them.";break}
case"dumbbell":{let activeDays=Math.max(0,v[0]-v[2]);let minutes=activeDays*v[1];rows=[["Movement days",activeDays+" days"],["Weekly minutes",minutes+" min"],["Rest stays in plan",v[2]+" days"]];score=minutes;verdict=minutes>120?"A steady rhythm":"Gentle is still a plan";joke="The plan works best if it survives meeting Tuesday.";break}
case"book":{let hours=v[0]/Math.max(1,v[1]),sessions=Math.ceil(hours/(v[2]/60));rows=[["Reading time",hours.toFixed(1)+" hours"],["Bedtime sessions",sessions],["Pages per evening",Math.ceil(v[0]/sessions)+" pages"]];score=sessions;verdict=sessions<5?"A very possible finish":"A few cosy chapters";joke="A bookmark is a finish line with excellent manners.";break}
case"bolt":{let saved=v[0]*.008*v[1];rows=[["Illustrative standby saving",saved.toFixed(2)+" kWh"],["Devices unplugged",v[0]],["Cool-wash setting",v[2]+"°C"]];score=saved;verdict="Small switches, steady habits";joke="Tiny estimate only; your meter has the official numbers.";break}
case"plate":{let portions=v[0]+v[1],food=portions*v[2];rows=[["People at the table",v[0]],["Total portions",portions],["Appetite-adjusted serves",food+" portions"]];score=food;verdict=portions>v[0]?"Lunch is handled":"Clean plates forecast";joke="Leftovers are tomorrow-you's meal prep assistant.";break}
case"leaf":{let water=Math.ceil(v[1]*(1+v[0]/7)*.35),check=Math.max(1,7-v[2]);rows=[["Illustrative watering",water+" litres / week"],["Pots to check",v[1]],["Ask a neighbour in",check+" days"]];score=v[0]+v[2];verdict=v[2]>5?"Find a plant sitter":"Leaves are in the loop";joke="A rough reminder, not horticultural advice from the ficus.";break}
default:{let time=v[1]*1.3+v[0]*3;rows=[["Decision time",Math.round(time)+" minutes"],["Snack refills",v[2]],["Votes required",v[0]]];score=time;verdict=v[1]>30?"Pick the shortest title":"Film night is possible";joke="The opening credits may arrive before consensus.";break}
}
const tier=score<10?0:score<25?1:score<55?2:3;$("#result").innerHTML=rows.map(x=>'<div class="result-line"><span>'+x[0]+'</span><b>'+x[1]+'</b></div>').join("")+'<div class="verdict"><div class="verdict-head"><small>YOUR LITTLE VERDICT</small><span class="verdict-badge">'+icon(active.icon)+'</span></div><h4>'+verdict+'</h4><p>'+joke+'</p><div class="cast"><span><i class="person"></i>You</span><span><i class="person"></i>Reality</span><span><i class="person"></i>The spare plan</span></div></div>'}
initMenu();choose("carry");$("#pick").onclick=()=>{let o=$("#menu").classList.toggle("open");$("#pick").setAttribute("aria-expanded",o?"true":"false")};document.addEventListener("click",e=>{if(!e.target.closest(".pick-wrap")){$("#menu").classList.remove("open");$("#pick").setAttribute("aria-expanded","false")}});
})();
