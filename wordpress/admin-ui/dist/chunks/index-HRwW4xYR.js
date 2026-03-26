import{cY as p,cZ as m,c$ as f,d0 as u}from"../xenarch-admin.js";import{n as d}from"./class-map-DV5BW3Ot.js";import{c as h}from"./index-DXZn5nRs.js";const g=p`
  :host {
    position: relative;
    display: flex;
    width: 100%;
    height: 1px;
    background-color: ${({tokens:t})=>t.theme.borderPrimary};
    justify-content: center;
    align-items: center;
  }

  :host > wui-text {
    position: absolute;
    padding: 0px 8px;
    transition: background-color ${({durations:t})=>t.lg}
      ${({easings:t})=>t["ease-out-power-2"]};
    will-change: background-color;
  }

  :host([data-bg-color='primary']) > wui-text {
    background-color: ${({tokens:t})=>t.theme.backgroundPrimary};
  }

  :host([data-bg-color='secondary']) > wui-text {
    background-color: ${({tokens:t})=>t.theme.foregroundPrimary};
  }
`;var l=function(t,r,o,n){var a=arguments.length,e=a<3?r:n===null?n=Object.getOwnPropertyDescriptor(r,o):n,s;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")e=Reflect.decorate(t,r,o,n);else for(var c=t.length-1;c>=0;c--)(s=t[c])&&(e=(a<3?s(e):a>3?s(r,o,e):s(r,o))||e);return a>3&&e&&Object.defineProperty(r,o,e),e};let i=class extends f{constructor(){super(...arguments),this.text="",this.bgColor="primary"}render(){return this.dataset.bgColor=this.bgColor,u`${this.template()}`}template(){return this.text?u`<wui-text variant="md-regular" color="secondary">${this.text}</wui-text>`:null}};i.styles=[m,g];l([d()],i.prototype,"text",void 0);l([d()],i.prototype,"bgColor",void 0);i=l([h("wui-separator")],i);
