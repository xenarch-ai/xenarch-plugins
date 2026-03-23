import{cR as f,cL as a,cM as d,cK as u}from"./config-CZ4K1Dc5.js";import"../xenarch-admin.js";const w=f`
  :host > wui-flex:first-child {
    height: 500px;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: none;
  }

  :host > wui-flex:first-child::-webkit-scrollbar {
    display: none;
  }
`;var p=function(n,t,i,o){var l=arguments.length,e=l<3?t:o===null?o=Object.getOwnPropertyDescriptor(t,i):o,r;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")e=Reflect.decorate(n,t,i,o);else for(var c=n.length-1;c>=0;c--)(r=n[c])&&(e=(l<3?r(e):l>3?r(t,i,e):r(t,i))||e);return l>3&&e&&Object.defineProperty(t,i,e),e};let s=class extends a{render(){return d`
      <wui-flex flexDirection="column" .padding=${["0","3","3","3"]} gap="3">
        <w3m-activity-list page="activity"></w3m-activity-list>
      </wui-flex>
    `}};s.styles=w;s=p([u("w3m-transactions-view")],s);export{s as W3mTransactionsView};
