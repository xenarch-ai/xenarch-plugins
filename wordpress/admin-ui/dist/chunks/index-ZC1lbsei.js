const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["./add-CYeX_Bwe.js","../xenarch-admin.js","./all-wallets-cP6g43AJ.js","./arrow-bottom-circle-BEKW5IJC.js","./app-store-CFQ0S7qt.js","./apple-xLNu58s-.js","./arrow-bottom-TsiG1j1V.js","./arrow-left-DAzkECuV.js","./arrow-right-wM62uqiV.js","./arrow-top-BXHYZxUg.js","./bank-CXAlapAS.js","./browser-BHeTrR54.js","./card-Bj2IQojb.js","./checkmark-Cn9s2ufC.js","./checkmark-bold-Dq7Aj1FL.js","./chevron-bottom-gxyjJR3X.js","./chevron-left-DE2teDvK.js","./chevron-right-Bm8W2ebg.js","./chevron-top-BoQjPdDF.js","./chrome-store-t4nvy2RC.js","./clock-CWUZx7yH.js","./close-BnfxCQWd.js","./compass-DI9J7IMW.js","./coinPlaceholder-86GZFjVP.js","./copy-CvXIiM7b.js","./cursor-CsV12Mv0.js","./cursor-transparent-C2RD61JL.js","./desktop-BEMfNpyD.js","./disconnect-BjYZkAr3.js","./discord-B8ebLK90.js","./etherscan-CtkDAwDC.js","./extension-RTVGK2ml.js","./external-link-CIMH9YZY.js","./facebook-eIY_UWOA.js","./farcaster-pPQXAjv7.js","./filters-xidPEIA3.js","./github-DeNBzaSP.js","./google-DcomWQuR.js","./help-circle-D2ueBBim.js","./image-B6bmCnoT.js","./id-DhIitSh9.js","./info-circle-Ctz5Nq-d.js","./lightbulb-CNZ0m-iw.js","./mail-B5vwQcdm.js","./mobile-pOKPi2lN.js","./more-BQ0SQXsI.js","./network-placeholder-CQfuRSAr.js","./nftPlaceholder-ovutrD8W.js","./off-huT1S_w6.js","./play-store-OSOqekKA.js","./plus-BQ8xF0of.js","./qr-code-BPmvfDCw.js","./recycle-horizontal-CaelLg_Q.js","./refresh-B2z8czcJ.js","./search-Ct9ms9wr.js","./send-CYfWpFRe.js","./swapHorizontal-DiWC8Fpk.js","./swapHorizontalMedium-Cu0mlTyr.js","./swapHorizontalBold-BmDNW8nI.js","./swapHorizontalRoundedBold-C1J61GfR.js","./swapVertical-BMV3r7CR.js","./telegram-CPvaGvEg.js","./three-dots-B8IwmmbU.js","./twitch-CEnWtZJN.js","./x-YiqZSFHh.js","./twitterIcon-DUGGPwcT.js","./verify-CYchRMy4.js","./verify-filled-CMKG9J-2.js","./wallet-BQyovED9.js","./walletconnect-HNUcIktA.js","./wallet-placeholder-BgonxZqe.js","./warning-circle-BxKFAfb2.js","./info-S6zE2Mya.js","./exclamation-triangle-DL_6rX7H.js","./reown-logo-BujddMHZ.js"])))=>i.map(i=>d[i]);
import{d2 as S,c$ as E,d0 as h,dj as z,e4 as e}from"../xenarch-admin.js";import{n as l,e as W,a as H}from"./class-map-DV5BW3Ot.js";import{r as b,c as B,e as F}from"./core-Dsdg1yJ7.js";import{f as G,n as M}from"./async-directive-CkvWqUyw.js";const w={getSpacingStyles(t,r){if(Array.isArray(t))return t[r]?`var(--wui-spacing-${t[r]})`:void 0;if(typeof t=="string")return`var(--wui-spacing-${t})`},getFormattedDate(t){return new Intl.DateTimeFormat("en-US",{month:"short",day:"numeric"}).format(t)},getHostName(t){try{return new URL(t).hostname}catch{return""}},getTruncateString({string:t,charsStart:r,charsEnd:i,truncate:a}){return t.length<=r+i?t:a==="end"?`${t.substring(0,r)}...`:a==="start"?`...${t.substring(t.length-i)}`:`${t.substring(0,Math.floor(r))}...${t.substring(t.length-Math.floor(i))}`},generateAvatarColors(t){const i=t.toLowerCase().replace(/^0x/iu,"").replace(/[^a-f0-9]/gu,"").substring(0,6).padEnd(6,"0"),a=this.hexToRgb(i),n=getComputedStyle(document.documentElement).getPropertyValue("--w3m-border-radius-master"),s=100-3*Number(n==null?void 0:n.replace("px","")),c=`${s}% ${s}% at 65% 40%`,u=[];for(let g=0;g<5;g+=1){const _=this.tintColor(a,.15*g);u.push(`rgb(${_[0]}, ${_[1]}, ${_[2]})`)}return`
    --local-color-1: ${u[0]};
    --local-color-2: ${u[1]};
    --local-color-3: ${u[2]};
    --local-color-4: ${u[3]};
    --local-color-5: ${u[4]};
    --local-radial-circle: ${c}
   `},hexToRgb(t){const r=parseInt(t,16),i=r>>16&255,a=r>>8&255,n=r&255;return[i,a,n]},tintColor(t,r){const[i,a,n]=t,o=Math.round(i+(255-i)*r),s=Math.round(a+(255-a)*r),c=Math.round(n+(255-n)*r);return[o,s,c]},isNumber(t){return{number:/^[0-9]+$/u}.number.test(t)},getColorTheme(t){var r;return t||(typeof window<"u"&&window.matchMedia?(r=window.matchMedia("(prefers-color-scheme: dark)"))!=null&&r.matches?"dark":"light":"dark")},splitBalance(t){const r=t.split(".");return r.length===2?[r[0],r[1]]:["0","00"]},roundNumber(t,r,i){return t.toString().length>=r?Number(t).toFixed(i):t},formatNumberToLocalString(t,r=2){return t===void 0?"0.00":typeof t=="number"?t.toLocaleString("en-US",{maximumFractionDigits:r,minimumFractionDigits:r}):parseFloat(t).toLocaleString("en-US",{maximumFractionDigits:r,minimumFractionDigits:r})}};function U(t,r){const{kind:i,elements:a}=r;return{kind:i,elements:a,finisher(n){customElements.get(t)||customElements.define(t,n)}}}function N(t,r){return customElements.get(t)||customElements.define(t,r),r}function x(t){return function(i){return typeof i=="function"?N(t,i):U(t,i)}}const Y=S`
  :host {
    display: flex;
    width: inherit;
    height: inherit;
  }
`;var d=function(t,r,i,a){var n=arguments.length,o=n<3?r:a===null?a=Object.getOwnPropertyDescriptor(r,i):a,s;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")o=Reflect.decorate(t,r,i,a);else for(var c=t.length-1;c>=0;c--)(s=t[c])&&(o=(n<3?s(o):n>3?s(r,i,o):s(r,i))||o);return n>3&&o&&Object.defineProperty(r,i,o),o};let p=class extends E{render(){return this.style.cssText=`
      flex-direction: ${this.flexDirection};
      flex-wrap: ${this.flexWrap};
      flex-basis: ${this.flexBasis};
      flex-grow: ${this.flexGrow};
      flex-shrink: ${this.flexShrink};
      align-items: ${this.alignItems};
      justify-content: ${this.justifyContent};
      column-gap: ${this.columnGap&&`var(--wui-spacing-${this.columnGap})`};
      row-gap: ${this.rowGap&&`var(--wui-spacing-${this.rowGap})`};
      gap: ${this.gap&&`var(--wui-spacing-${this.gap})`};
      padding-top: ${this.padding&&w.getSpacingStyles(this.padding,0)};
      padding-right: ${this.padding&&w.getSpacingStyles(this.padding,1)};
      padding-bottom: ${this.padding&&w.getSpacingStyles(this.padding,2)};
      padding-left: ${this.padding&&w.getSpacingStyles(this.padding,3)};
      margin-top: ${this.margin&&w.getSpacingStyles(this.margin,0)};
      margin-right: ${this.margin&&w.getSpacingStyles(this.margin,1)};
      margin-bottom: ${this.margin&&w.getSpacingStyles(this.margin,2)};
      margin-left: ${this.margin&&w.getSpacingStyles(this.margin,3)};
    `,h`<slot></slot>`}};p.styles=[b,Y];d([l()],p.prototype,"flexDirection",void 0);d([l()],p.prototype,"flexWrap",void 0);d([l()],p.prototype,"flexBasis",void 0);d([l()],p.prototype,"flexGrow",void 0);d([l()],p.prototype,"flexShrink",void 0);d([l()],p.prototype,"alignItems",void 0);d([l()],p.prototype,"justifyContent",void 0);d([l()],p.prototype,"columnGap",void 0);d([l()],p.prototype,"rowGap",void 0);d([l()],p.prototype,"gap",void 0);d([l()],p.prototype,"padding",void 0);d([l()],p.prototype,"margin",void 0);p=d([x("wui-flex")],p);/**
 * @license
 * Copyright 2021 Google LLC
 * SPDX-License-Identifier: BSD-3-Clause
 */class q{constructor(r){this.G=r}disconnect(){this.G=void 0}reconnect(r){this.G=r}deref(){return this.G}}class X{constructor(){this.Y=void 0,this.Z=void 0}get(){return this.Y}pause(){this.Y??(this.Y=new Promise(r=>this.Z=r))}resume(){var r;(r=this.Z)==null||r.call(this),this.Y=this.Z=void 0}}/**
 * @license
 * Copyright 2017 Google LLC
 * SPDX-License-Identifier: BSD-3-Clause
 */const A=t=>!M(t)&&typeof t.then=="function",k=1073741823;class K extends G{constructor(){super(...arguments),this._$Cwt=k,this._$Cbt=[],this._$CK=new q(this),this._$CX=new X}render(...r){return r.find(i=>!A(i))??z}update(r,i){const a=this._$Cbt;let n=a.length;this._$Cbt=i;const o=this._$CK,s=this._$CX;this.isConnected||this.disconnected();for(let c=0;c<i.length&&!(c>this._$Cwt);c++){const u=i[c];if(!A(u))return this._$Cwt=c,u;c<n&&u===a[c]||(this._$Cwt=k,n=0,Promise.resolve(u).then(async g=>{for(;s.get();)await s.get();const _=o.deref();if(_!==void 0){const I=_._$Cbt.indexOf(u);I>-1&&I<_._$Cwt&&(_._$Cwt=I,_.setValue(g))}}))}return z}disconnected(){this._$CK.disconnect(),this._$CX.pause()}reconnected(){this._$CK.reconnect(this),this._$CX.resume()}}const Z=W(K);class J{constructor(){this.cache=new Map}set(r,i){this.cache.set(r,i)}get(r){return this.cache.get(r)}has(r){return this.cache.has(r)}delete(r){this.cache.delete(r)}clear(){this.cache.clear()}}const D=new J,Q=S`
  :host {
    display: flex;
    aspect-ratio: var(--local-aspect-ratio);
    color: var(--local-color);
    width: var(--local-width);
  }

  svg {
    width: inherit;
    height: inherit;
    object-fit: contain;
    object-position: center;
  }

  .fallback {
    width: var(--local-width);
    height: var(--local-height);
  }
`;var T=function(t,r,i,a){var n=arguments.length,o=n<3?r:a===null?a=Object.getOwnPropertyDescriptor(r,i):a,s;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")o=Reflect.decorate(t,r,i,a);else for(var c=t.length-1;c>=0;c--)(s=t[c])&&(o=(n<3?s(o):n>3?s(r,i,o):s(r,i))||o);return n>3&&o&&Object.defineProperty(r,i,o),o};const j={add:async()=>(await e(async()=>{const{addSvg:t}=await import("./add-CYeX_Bwe.js");return{addSvg:t}},__vite__mapDeps([0,1]),import.meta.url)).addSvg,allWallets:async()=>(await e(async()=>{const{allWalletsSvg:t}=await import("./all-wallets-cP6g43AJ.js");return{allWalletsSvg:t}},__vite__mapDeps([2,1]),import.meta.url)).allWalletsSvg,arrowBottomCircle:async()=>(await e(async()=>{const{arrowBottomCircleSvg:t}=await import("./arrow-bottom-circle-BEKW5IJC.js");return{arrowBottomCircleSvg:t}},__vite__mapDeps([3,1]),import.meta.url)).arrowBottomCircleSvg,appStore:async()=>(await e(async()=>{const{appStoreSvg:t}=await import("./app-store-CFQ0S7qt.js");return{appStoreSvg:t}},__vite__mapDeps([4,1]),import.meta.url)).appStoreSvg,apple:async()=>(await e(async()=>{const{appleSvg:t}=await import("./apple-xLNu58s-.js");return{appleSvg:t}},__vite__mapDeps([5,1]),import.meta.url)).appleSvg,arrowBottom:async()=>(await e(async()=>{const{arrowBottomSvg:t}=await import("./arrow-bottom-TsiG1j1V.js");return{arrowBottomSvg:t}},__vite__mapDeps([6,1]),import.meta.url)).arrowBottomSvg,arrowLeft:async()=>(await e(async()=>{const{arrowLeftSvg:t}=await import("./arrow-left-DAzkECuV.js");return{arrowLeftSvg:t}},__vite__mapDeps([7,1]),import.meta.url)).arrowLeftSvg,arrowRight:async()=>(await e(async()=>{const{arrowRightSvg:t}=await import("./arrow-right-wM62uqiV.js");return{arrowRightSvg:t}},__vite__mapDeps([8,1]),import.meta.url)).arrowRightSvg,arrowTop:async()=>(await e(async()=>{const{arrowTopSvg:t}=await import("./arrow-top-BXHYZxUg.js");return{arrowTopSvg:t}},__vite__mapDeps([9,1]),import.meta.url)).arrowTopSvg,bank:async()=>(await e(async()=>{const{bankSvg:t}=await import("./bank-CXAlapAS.js");return{bankSvg:t}},__vite__mapDeps([10,1]),import.meta.url)).bankSvg,browser:async()=>(await e(async()=>{const{browserSvg:t}=await import("./browser-BHeTrR54.js");return{browserSvg:t}},__vite__mapDeps([11,1]),import.meta.url)).browserSvg,card:async()=>(await e(async()=>{const{cardSvg:t}=await import("./card-Bj2IQojb.js");return{cardSvg:t}},__vite__mapDeps([12,1]),import.meta.url)).cardSvg,checkmark:async()=>(await e(async()=>{const{checkmarkSvg:t}=await import("./checkmark-Cn9s2ufC.js");return{checkmarkSvg:t}},__vite__mapDeps([13,1]),import.meta.url)).checkmarkSvg,checkmarkBold:async()=>(await e(async()=>{const{checkmarkBoldSvg:t}=await import("./checkmark-bold-Dq7Aj1FL.js");return{checkmarkBoldSvg:t}},__vite__mapDeps([14,1]),import.meta.url)).checkmarkBoldSvg,chevronBottom:async()=>(await e(async()=>{const{chevronBottomSvg:t}=await import("./chevron-bottom-gxyjJR3X.js");return{chevronBottomSvg:t}},__vite__mapDeps([15,1]),import.meta.url)).chevronBottomSvg,chevronLeft:async()=>(await e(async()=>{const{chevronLeftSvg:t}=await import("./chevron-left-DE2teDvK.js");return{chevronLeftSvg:t}},__vite__mapDeps([16,1]),import.meta.url)).chevronLeftSvg,chevronRight:async()=>(await e(async()=>{const{chevronRightSvg:t}=await import("./chevron-right-Bm8W2ebg.js");return{chevronRightSvg:t}},__vite__mapDeps([17,1]),import.meta.url)).chevronRightSvg,chevronTop:async()=>(await e(async()=>{const{chevronTopSvg:t}=await import("./chevron-top-BoQjPdDF.js");return{chevronTopSvg:t}},__vite__mapDeps([18,1]),import.meta.url)).chevronTopSvg,chromeStore:async()=>(await e(async()=>{const{chromeStoreSvg:t}=await import("./chrome-store-t4nvy2RC.js");return{chromeStoreSvg:t}},__vite__mapDeps([19,1]),import.meta.url)).chromeStoreSvg,clock:async()=>(await e(async()=>{const{clockSvg:t}=await import("./clock-CWUZx7yH.js");return{clockSvg:t}},__vite__mapDeps([20,1]),import.meta.url)).clockSvg,close:async()=>(await e(async()=>{const{closeSvg:t}=await import("./close-BnfxCQWd.js");return{closeSvg:t}},__vite__mapDeps([21,1]),import.meta.url)).closeSvg,compass:async()=>(await e(async()=>{const{compassSvg:t}=await import("./compass-DI9J7IMW.js");return{compassSvg:t}},__vite__mapDeps([22,1]),import.meta.url)).compassSvg,coinPlaceholder:async()=>(await e(async()=>{const{coinPlaceholderSvg:t}=await import("./coinPlaceholder-86GZFjVP.js");return{coinPlaceholderSvg:t}},__vite__mapDeps([23,1]),import.meta.url)).coinPlaceholderSvg,copy:async()=>(await e(async()=>{const{copySvg:t}=await import("./copy-CvXIiM7b.js");return{copySvg:t}},__vite__mapDeps([24,1]),import.meta.url)).copySvg,cursor:async()=>(await e(async()=>{const{cursorSvg:t}=await import("./cursor-CsV12Mv0.js");return{cursorSvg:t}},__vite__mapDeps([25,1]),import.meta.url)).cursorSvg,cursorTransparent:async()=>(await e(async()=>{const{cursorTransparentSvg:t}=await import("./cursor-transparent-C2RD61JL.js");return{cursorTransparentSvg:t}},__vite__mapDeps([26,1]),import.meta.url)).cursorTransparentSvg,desktop:async()=>(await e(async()=>{const{desktopSvg:t}=await import("./desktop-BEMfNpyD.js");return{desktopSvg:t}},__vite__mapDeps([27,1]),import.meta.url)).desktopSvg,disconnect:async()=>(await e(async()=>{const{disconnectSvg:t}=await import("./disconnect-BjYZkAr3.js");return{disconnectSvg:t}},__vite__mapDeps([28,1]),import.meta.url)).disconnectSvg,discord:async()=>(await e(async()=>{const{discordSvg:t}=await import("./discord-B8ebLK90.js");return{discordSvg:t}},__vite__mapDeps([29,1]),import.meta.url)).discordSvg,etherscan:async()=>(await e(async()=>{const{etherscanSvg:t}=await import("./etherscan-CtkDAwDC.js");return{etherscanSvg:t}},__vite__mapDeps([30,1]),import.meta.url)).etherscanSvg,extension:async()=>(await e(async()=>{const{extensionSvg:t}=await import("./extension-RTVGK2ml.js");return{extensionSvg:t}},__vite__mapDeps([31,1]),import.meta.url)).extensionSvg,externalLink:async()=>(await e(async()=>{const{externalLinkSvg:t}=await import("./external-link-CIMH9YZY.js");return{externalLinkSvg:t}},__vite__mapDeps([32,1]),import.meta.url)).externalLinkSvg,facebook:async()=>(await e(async()=>{const{facebookSvg:t}=await import("./facebook-eIY_UWOA.js");return{facebookSvg:t}},__vite__mapDeps([33,1]),import.meta.url)).facebookSvg,farcaster:async()=>(await e(async()=>{const{farcasterSvg:t}=await import("./farcaster-pPQXAjv7.js");return{farcasterSvg:t}},__vite__mapDeps([34,1]),import.meta.url)).farcasterSvg,filters:async()=>(await e(async()=>{const{filtersSvg:t}=await import("./filters-xidPEIA3.js");return{filtersSvg:t}},__vite__mapDeps([35,1]),import.meta.url)).filtersSvg,github:async()=>(await e(async()=>{const{githubSvg:t}=await import("./github-DeNBzaSP.js");return{githubSvg:t}},__vite__mapDeps([36,1]),import.meta.url)).githubSvg,google:async()=>(await e(async()=>{const{googleSvg:t}=await import("./google-DcomWQuR.js");return{googleSvg:t}},__vite__mapDeps([37,1]),import.meta.url)).googleSvg,helpCircle:async()=>(await e(async()=>{const{helpCircleSvg:t}=await import("./help-circle-D2ueBBim.js");return{helpCircleSvg:t}},__vite__mapDeps([38,1]),import.meta.url)).helpCircleSvg,image:async()=>(await e(async()=>{const{imageSvg:t}=await import("./image-B6bmCnoT.js");return{imageSvg:t}},__vite__mapDeps([39,1]),import.meta.url)).imageSvg,id:async()=>(await e(async()=>{const{idSvg:t}=await import("./id-DhIitSh9.js");return{idSvg:t}},__vite__mapDeps([40,1]),import.meta.url)).idSvg,infoCircle:async()=>(await e(async()=>{const{infoCircleSvg:t}=await import("./info-circle-Ctz5Nq-d.js");return{infoCircleSvg:t}},__vite__mapDeps([41,1]),import.meta.url)).infoCircleSvg,lightbulb:async()=>(await e(async()=>{const{lightbulbSvg:t}=await import("./lightbulb-CNZ0m-iw.js");return{lightbulbSvg:t}},__vite__mapDeps([42,1]),import.meta.url)).lightbulbSvg,mail:async()=>(await e(async()=>{const{mailSvg:t}=await import("./mail-B5vwQcdm.js");return{mailSvg:t}},__vite__mapDeps([43,1]),import.meta.url)).mailSvg,mobile:async()=>(await e(async()=>{const{mobileSvg:t}=await import("./mobile-pOKPi2lN.js");return{mobileSvg:t}},__vite__mapDeps([44,1]),import.meta.url)).mobileSvg,more:async()=>(await e(async()=>{const{moreSvg:t}=await import("./more-BQ0SQXsI.js");return{moreSvg:t}},__vite__mapDeps([45,1]),import.meta.url)).moreSvg,networkPlaceholder:async()=>(await e(async()=>{const{networkPlaceholderSvg:t}=await import("./network-placeholder-CQfuRSAr.js");return{networkPlaceholderSvg:t}},__vite__mapDeps([46,1]),import.meta.url)).networkPlaceholderSvg,nftPlaceholder:async()=>(await e(async()=>{const{nftPlaceholderSvg:t}=await import("./nftPlaceholder-ovutrD8W.js");return{nftPlaceholderSvg:t}},__vite__mapDeps([47,1]),import.meta.url)).nftPlaceholderSvg,off:async()=>(await e(async()=>{const{offSvg:t}=await import("./off-huT1S_w6.js");return{offSvg:t}},__vite__mapDeps([48,1]),import.meta.url)).offSvg,playStore:async()=>(await e(async()=>{const{playStoreSvg:t}=await import("./play-store-OSOqekKA.js");return{playStoreSvg:t}},__vite__mapDeps([49,1]),import.meta.url)).playStoreSvg,plus:async()=>(await e(async()=>{const{plusSvg:t}=await import("./plus-BQ8xF0of.js");return{plusSvg:t}},__vite__mapDeps([50,1]),import.meta.url)).plusSvg,qrCode:async()=>(await e(async()=>{const{qrCodeIcon:t}=await import("./qr-code-BPmvfDCw.js");return{qrCodeIcon:t}},__vite__mapDeps([51,1]),import.meta.url)).qrCodeIcon,recycleHorizontal:async()=>(await e(async()=>{const{recycleHorizontalSvg:t}=await import("./recycle-horizontal-CaelLg_Q.js");return{recycleHorizontalSvg:t}},__vite__mapDeps([52,1]),import.meta.url)).recycleHorizontalSvg,refresh:async()=>(await e(async()=>{const{refreshSvg:t}=await import("./refresh-B2z8czcJ.js");return{refreshSvg:t}},__vite__mapDeps([53,1]),import.meta.url)).refreshSvg,search:async()=>(await e(async()=>{const{searchSvg:t}=await import("./search-Ct9ms9wr.js");return{searchSvg:t}},__vite__mapDeps([54,1]),import.meta.url)).searchSvg,send:async()=>(await e(async()=>{const{sendSvg:t}=await import("./send-CYfWpFRe.js");return{sendSvg:t}},__vite__mapDeps([55,1]),import.meta.url)).sendSvg,swapHorizontal:async()=>(await e(async()=>{const{swapHorizontalSvg:t}=await import("./swapHorizontal-DiWC8Fpk.js");return{swapHorizontalSvg:t}},__vite__mapDeps([56,1]),import.meta.url)).swapHorizontalSvg,swapHorizontalMedium:async()=>(await e(async()=>{const{swapHorizontalMediumSvg:t}=await import("./swapHorizontalMedium-Cu0mlTyr.js");return{swapHorizontalMediumSvg:t}},__vite__mapDeps([57,1]),import.meta.url)).swapHorizontalMediumSvg,swapHorizontalBold:async()=>(await e(async()=>{const{swapHorizontalBoldSvg:t}=await import("./swapHorizontalBold-BmDNW8nI.js");return{swapHorizontalBoldSvg:t}},__vite__mapDeps([58,1]),import.meta.url)).swapHorizontalBoldSvg,swapHorizontalRoundedBold:async()=>(await e(async()=>{const{swapHorizontalRoundedBoldSvg:t}=await import("./swapHorizontalRoundedBold-C1J61GfR.js");return{swapHorizontalRoundedBoldSvg:t}},__vite__mapDeps([59,1]),import.meta.url)).swapHorizontalRoundedBoldSvg,swapVertical:async()=>(await e(async()=>{const{swapVerticalSvg:t}=await import("./swapVertical-BMV3r7CR.js");return{swapVerticalSvg:t}},__vite__mapDeps([60,1]),import.meta.url)).swapVerticalSvg,telegram:async()=>(await e(async()=>{const{telegramSvg:t}=await import("./telegram-CPvaGvEg.js");return{telegramSvg:t}},__vite__mapDeps([61,1]),import.meta.url)).telegramSvg,threeDots:async()=>(await e(async()=>{const{threeDotsSvg:t}=await import("./three-dots-B8IwmmbU.js");return{threeDotsSvg:t}},__vite__mapDeps([62,1]),import.meta.url)).threeDotsSvg,twitch:async()=>(await e(async()=>{const{twitchSvg:t}=await import("./twitch-CEnWtZJN.js");return{twitchSvg:t}},__vite__mapDeps([63,1]),import.meta.url)).twitchSvg,twitter:async()=>(await e(async()=>{const{xSvg:t}=await import("./x-YiqZSFHh.js");return{xSvg:t}},__vite__mapDeps([64,1]),import.meta.url)).xSvg,twitterIcon:async()=>(await e(async()=>{const{twitterIconSvg:t}=await import("./twitterIcon-DUGGPwcT.js");return{twitterIconSvg:t}},__vite__mapDeps([65,1]),import.meta.url)).twitterIconSvg,verify:async()=>(await e(async()=>{const{verifySvg:t}=await import("./verify-CYchRMy4.js");return{verifySvg:t}},__vite__mapDeps([66,1]),import.meta.url)).verifySvg,verifyFilled:async()=>(await e(async()=>{const{verifyFilledSvg:t}=await import("./verify-filled-CMKG9J-2.js");return{verifyFilledSvg:t}},__vite__mapDeps([67,1]),import.meta.url)).verifyFilledSvg,wallet:async()=>(await e(async()=>{const{walletSvg:t}=await import("./wallet-BQyovED9.js");return{walletSvg:t}},__vite__mapDeps([68,1]),import.meta.url)).walletSvg,walletConnect:async()=>(await e(async()=>{const{walletConnectSvg:t}=await import("./walletconnect-HNUcIktA.js");return{walletConnectSvg:t}},__vite__mapDeps([69,1]),import.meta.url)).walletConnectSvg,walletConnectLightBrown:async()=>(await e(async()=>{const{walletConnectLightBrownSvg:t}=await import("./walletconnect-HNUcIktA.js");return{walletConnectLightBrownSvg:t}},__vite__mapDeps([69,1]),import.meta.url)).walletConnectLightBrownSvg,walletConnectBrown:async()=>(await e(async()=>{const{walletConnectBrownSvg:t}=await import("./walletconnect-HNUcIktA.js");return{walletConnectBrownSvg:t}},__vite__mapDeps([69,1]),import.meta.url)).walletConnectBrownSvg,walletPlaceholder:async()=>(await e(async()=>{const{walletPlaceholderSvg:t}=await import("./wallet-placeholder-BgonxZqe.js");return{walletPlaceholderSvg:t}},__vite__mapDeps([70,1]),import.meta.url)).walletPlaceholderSvg,warningCircle:async()=>(await e(async()=>{const{warningCircleSvg:t}=await import("./warning-circle-BxKFAfb2.js");return{warningCircleSvg:t}},__vite__mapDeps([71,1]),import.meta.url)).warningCircleSvg,x:async()=>(await e(async()=>{const{xSvg:t}=await import("./x-YiqZSFHh.js");return{xSvg:t}},__vite__mapDeps([64,1]),import.meta.url)).xSvg,info:async()=>(await e(async()=>{const{infoSvg:t}=await import("./info-S6zE2Mya.js");return{infoSvg:t}},__vite__mapDeps([72,1]),import.meta.url)).infoSvg,exclamationTriangle:async()=>(await e(async()=>{const{exclamationTriangleSvg:t}=await import("./exclamation-triangle-DL_6rX7H.js");return{exclamationTriangleSvg:t}},__vite__mapDeps([73,1]),import.meta.url)).exclamationTriangleSvg,reown:async()=>(await e(async()=>{const{reownSvg:t}=await import("./reown-logo-BujddMHZ.js");return{reownSvg:t}},__vite__mapDeps([74,1]),import.meta.url)).reownSvg};async function tt(t){if(D.has(t))return D.get(t);const i=(j[t]??j.copy)();return D.set(t,i),i}let f=class extends E{constructor(){super(...arguments),this.size="md",this.name="copy",this.color="fg-300",this.aspectRatio="1 / 1"}render(){return this.style.cssText=`
      --local-color: ${`var(--wui-color-${this.color});`}
      --local-width: ${`var(--wui-icon-size-${this.size});`}
      --local-aspect-ratio: ${this.aspectRatio}
    `,h`${Z(tt(this.name),h`<div class="fallback"></div>`)}`}};f.styles=[b,B,Q];T([l()],f.prototype,"size",void 0);T([l()],f.prototype,"name",void 0);T([l()],f.prototype,"color",void 0);T([l()],f.prototype,"aspectRatio",void 0);f=T([x("wui-icon")],f);const rt=S`
  :host {
    display: inline-flex !important;
  }

  slot {
    width: 100%;
    display: inline-block;
    font-style: normal;
    font-family: var(--wui-font-family);
    font-feature-settings:
      'tnum' on,
      'lnum' on,
      'case' on;
    line-height: 130%;
    font-weight: var(--wui-font-weight-regular);
    overflow: inherit;
    text-overflow: inherit;
    text-align: var(--local-align);
    color: var(--local-color);
  }

  .wui-line-clamp-1 {
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
  }

  .wui-line-clamp-2 {
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
  }

  .wui-font-medium-400 {
    font-size: var(--wui-font-size-medium);
    font-weight: var(--wui-font-weight-light);
    letter-spacing: var(--wui-letter-spacing-medium);
  }

  .wui-font-medium-600 {
    font-size: var(--wui-font-size-medium);
    letter-spacing: var(--wui-letter-spacing-medium);
  }

  .wui-font-title-600 {
    font-size: var(--wui-font-size-title);
    letter-spacing: var(--wui-letter-spacing-title);
  }

  .wui-font-title-6-600 {
    font-size: var(--wui-font-size-title-6);
    letter-spacing: var(--wui-letter-spacing-title-6);
  }

  .wui-font-mini-700 {
    font-size: var(--wui-font-size-mini);
    letter-spacing: var(--wui-letter-spacing-mini);
    text-transform: uppercase;
  }

  .wui-font-large-500,
  .wui-font-large-600,
  .wui-font-large-700 {
    font-size: var(--wui-font-size-large);
    letter-spacing: var(--wui-letter-spacing-large);
  }

  .wui-font-2xl-500,
  .wui-font-2xl-600,
  .wui-font-2xl-700 {
    font-size: var(--wui-font-size-2xl);
    letter-spacing: var(--wui-letter-spacing-2xl);
  }

  .wui-font-paragraph-400,
  .wui-font-paragraph-500,
  .wui-font-paragraph-600,
  .wui-font-paragraph-700 {
    font-size: var(--wui-font-size-paragraph);
    letter-spacing: var(--wui-letter-spacing-paragraph);
  }

  .wui-font-small-400,
  .wui-font-small-500,
  .wui-font-small-600 {
    font-size: var(--wui-font-size-small);
    letter-spacing: var(--wui-letter-spacing-small);
  }

  .wui-font-tiny-400,
  .wui-font-tiny-500,
  .wui-font-tiny-600 {
    font-size: var(--wui-font-size-tiny);
    letter-spacing: var(--wui-letter-spacing-tiny);
  }

  .wui-font-micro-700,
  .wui-font-micro-600 {
    font-size: var(--wui-font-size-micro);
    letter-spacing: var(--wui-letter-spacing-micro);
    text-transform: uppercase;
  }

  .wui-font-tiny-400,
  .wui-font-small-400,
  .wui-font-medium-400,
  .wui-font-paragraph-400 {
    font-weight: var(--wui-font-weight-light);
  }

  .wui-font-large-700,
  .wui-font-paragraph-700,
  .wui-font-micro-700,
  .wui-font-mini-700 {
    font-weight: var(--wui-font-weight-bold);
  }

  .wui-font-medium-600,
  .wui-font-medium-title-600,
  .wui-font-title-6-600,
  .wui-font-large-600,
  .wui-font-paragraph-600,
  .wui-font-small-600,
  .wui-font-tiny-600,
  .wui-font-micro-600 {
    font-weight: var(--wui-font-weight-medium);
  }

  :host([disabled]) {
    opacity: 0.4;
  }
`;var O=function(t,r,i,a){var n=arguments.length,o=n<3?r:a===null?a=Object.getOwnPropertyDescriptor(r,i):a,s;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")o=Reflect.decorate(t,r,i,a);else for(var c=t.length-1;c>=0;c--)(s=t[c])&&(o=(n<3?s(o):n>3?s(r,i,o):s(r,i))||o);return n>3&&o&&Object.defineProperty(r,i,o),o};let y=class extends E{constructor(){super(...arguments),this.variant="paragraph-500",this.color="fg-300",this.align="left",this.lineClamp=void 0}render(){const r={[`wui-font-${this.variant}`]:!0,[`wui-color-${this.color}`]:!0,[`wui-line-clamp-${this.lineClamp}`]:!!this.lineClamp};return this.style.cssText=`
      --local-align: ${this.align};
      --local-color: var(--wui-color-${this.color});
    `,h`<slot class=${H(r)}></slot>`}};y.styles=[b,rt];O([l()],y.prototype,"variant",void 0);O([l()],y.prototype,"color",void 0);O([l()],y.prototype,"align",void 0);O([l()],y.prototype,"lineClamp",void 0);y=O([x("wui-text")],y);const et=S`
  :host {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    position: relative;
    overflow: hidden;
    background-color: var(--wui-color-gray-glass-020);
    border-radius: var(--local-border-radius);
    border: var(--local-border);
    box-sizing: content-box;
    width: var(--local-size);
    height: var(--local-size);
    min-height: var(--local-size);
    min-width: var(--local-size);
  }

  @supports (background: color-mix(in srgb, white 50%, black)) {
    :host {
      background-color: color-mix(in srgb, var(--local-bg-value) var(--local-bg-mix), transparent);
    }
  }
`;var v=function(t,r,i,a){var n=arguments.length,o=n<3?r:a===null?a=Object.getOwnPropertyDescriptor(r,i):a,s;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")o=Reflect.decorate(t,r,i,a);else for(var c=t.length-1;c>=0;c--)(s=t[c])&&(o=(n<3?s(o):n>3?s(r,i,o):s(r,i))||o);return n>3&&o&&Object.defineProperty(r,i,o),o};let m=class extends E{constructor(){super(...arguments),this.size="md",this.backgroundColor="accent-100",this.iconColor="accent-100",this.background="transparent",this.border=!1,this.borderColor="wui-color-bg-125",this.icon="copy"}render(){const r=this.iconSize||this.size,i=this.size==="lg",a=this.size==="xl",n=i?"12%":"16%",o=i?"xxs":a?"s":"3xl",s=this.background==="gray",c=this.background==="opaque",u=this.backgroundColor==="accent-100"&&c||this.backgroundColor==="success-100"&&c||this.backgroundColor==="error-100"&&c||this.backgroundColor==="inverse-100"&&c;let g=`var(--wui-color-${this.backgroundColor})`;return u?g=`var(--wui-icon-box-bg-${this.backgroundColor})`:s&&(g=`var(--wui-color-gray-${this.backgroundColor})`),this.style.cssText=`
       --local-bg-value: ${g};
       --local-bg-mix: ${u||s?"100%":n};
       --local-border-radius: var(--wui-border-radius-${o});
       --local-size: var(--wui-icon-box-size-${this.size});
       --local-border: ${this.borderColor==="wui-color-bg-125"?"2px":"1px"} solid ${this.border?`var(--${this.borderColor})`:"transparent"}
   `,h` <wui-icon color=${this.iconColor} size=${r} name=${this.icon}></wui-icon> `}};m.styles=[b,F,et];v([l()],m.prototype,"size",void 0);v([l()],m.prototype,"backgroundColor",void 0);v([l()],m.prototype,"iconColor",void 0);v([l()],m.prototype,"iconSize",void 0);v([l()],m.prototype,"background",void 0);v([l({type:Boolean})],m.prototype,"border",void 0);v([l()],m.prototype,"borderColor",void 0);v([l()],m.prototype,"icon",void 0);m=v([x("wui-icon-box")],m);const it=S`
  :host {
    display: block;
    width: var(--local-width);
    height: var(--local-height);
  }

  img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center center;
    border-radius: inherit;
  }
`;var L=function(t,r,i,a){var n=arguments.length,o=n<3?r:a===null?a=Object.getOwnPropertyDescriptor(r,i):a,s;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")o=Reflect.decorate(t,r,i,a);else for(var c=t.length-1;c>=0;c--)(s=t[c])&&(o=(n<3?s(o):n>3?s(r,i,o):s(r,i))||o);return n>3&&o&&Object.defineProperty(r,i,o),o};let R=class extends E{constructor(){super(...arguments),this.src="./path/to/image.jpg",this.alt="Image",this.size=void 0}render(){return this.style.cssText=`
      --local-width: ${this.size?`var(--wui-icon-size-${this.size});`:"100%"};
      --local-height: ${this.size?`var(--wui-icon-size-${this.size});`:"100%"};
      `,h`<img src=${this.src} alt=${this.alt} @error=${this.handleImageError} />`}handleImageError(){this.dispatchEvent(new CustomEvent("onLoadError",{bubbles:!0,composed:!0}))}};R.styles=[b,B,it];L([l()],R.prototype,"src",void 0);L([l()],R.prototype,"alt",void 0);L([l()],R.prototype,"size",void 0);R=L([x("wui-image")],R);const ot=S`
  :host {
    display: flex;
    justify-content: center;
    align-items: center;
    height: var(--wui-spacing-m);
    padding: 0 var(--wui-spacing-3xs) !important;
    border-radius: var(--wui-border-radius-5xs);
    transition:
      border-radius var(--wui-duration-lg) var(--wui-ease-out-power-1),
      background-color var(--wui-duration-lg) var(--wui-ease-out-power-1);
    will-change: border-radius, background-color;
  }

  :host > wui-text {
    transform: translateY(5%);
  }

  :host([data-variant='main']) {
    background-color: var(--wui-color-accent-glass-015);
    color: var(--wui-color-accent-100);
  }

  :host([data-variant='shade']) {
    background-color: var(--wui-color-gray-glass-010);
    color: var(--wui-color-fg-200);
  }

  :host([data-variant='success']) {
    background-color: var(--wui-icon-box-bg-success-100);
    color: var(--wui-color-success-100);
  }

  :host([data-variant='error']) {
    background-color: var(--wui-icon-box-bg-error-100);
    color: var(--wui-color-error-100);
  }

  :host([data-size='lg']) {
    padding: 11px 5px !important;
  }

  :host([data-size='lg']) > wui-text {
    transform: translateY(2%);
  }
`;var V=function(t,r,i,a){var n=arguments.length,o=n<3?r:a===null?a=Object.getOwnPropertyDescriptor(r,i):a,s;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")o=Reflect.decorate(t,r,i,a);else for(var c=t.length-1;c>=0;c--)(s=t[c])&&(o=(n<3?s(o):n>3?s(r,i,o):s(r,i))||o);return n>3&&o&&Object.defineProperty(r,i,o),o};let $=class extends E{constructor(){super(...arguments),this.variant="main",this.size="lg"}render(){this.dataset.variant=this.variant,this.dataset.size=this.size;const r=this.size==="md"?"mini-700":"micro-700";return h`
      <wui-text data-variant=${this.variant} variant=${r} color="inherit">
        <slot></slot>
      </wui-text>
    `}};$.styles=[b,ot];V([l()],$.prototype,"variant",void 0);V([l()],$.prototype,"size",void 0);$=V([x("wui-tag")],$);const at=S`
  :host {
    display: flex;
  }

  :host([data-size='sm']) > svg {
    width: 12px;
    height: 12px;
  }

  :host([data-size='md']) > svg {
    width: 16px;
    height: 16px;
  }

  :host([data-size='lg']) > svg {
    width: 24px;
    height: 24px;
  }

  :host([data-size='xl']) > svg {
    width: 32px;
    height: 32px;
  }

  svg {
    animation: rotate 2s linear infinite;
  }

  circle {
    fill: none;
    stroke: var(--local-color);
    stroke-width: 4px;
    stroke-dasharray: 1, 124;
    stroke-dashoffset: 0;
    stroke-linecap: round;
    animation: dash 1.5s ease-in-out infinite;
  }

  :host([data-size='md']) > svg > circle {
    stroke-width: 6px;
  }

  :host([data-size='sm']) > svg > circle {
    stroke-width: 8px;
  }

  @keyframes rotate {
    100% {
      transform: rotate(360deg);
    }
  }

  @keyframes dash {
    0% {
      stroke-dasharray: 1, 124;
      stroke-dashoffset: 0;
    }

    50% {
      stroke-dasharray: 90, 124;
      stroke-dashoffset: -35;
    }

    100% {
      stroke-dashoffset: -125;
    }
  }
`;var C=function(t,r,i,a){var n=arguments.length,o=n<3?r:a===null?a=Object.getOwnPropertyDescriptor(r,i):a,s;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")o=Reflect.decorate(t,r,i,a);else for(var c=t.length-1;c>=0;c--)(s=t[c])&&(o=(n<3?s(o):n>3?s(r,i,o):s(r,i))||o);return n>3&&o&&Object.defineProperty(r,i,o),o};let P=class extends E{constructor(){super(...arguments),this.color="accent-100",this.size="lg"}render(){return this.style.cssText=`--local-color: ${this.color==="inherit"?"inherit":`var(--wui-color-${this.color})`}`,this.dataset.size=this.size,h`<svg viewBox="25 25 50 50">
      <circle r="20" cy="50" cx="50"></circle>
    </svg>`}};P.styles=[b,at];C([l()],P.prototype,"color",void 0);C([l()],P.prototype,"size",void 0);P=C([x("wui-loading-spinner")],P);export{w as U,x as c};
