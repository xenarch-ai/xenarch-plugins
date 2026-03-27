import{cY as b,cZ as p,c_ as m,c$ as f,d0 as l}from"../xenarch-admin.js";import{n as d}from"./class-map-DV5BW3Ot.js";import{c as v}from"./index-DXZn5nRs.js";const h=b`
  button {
    border: none;
    background: transparent;
    height: 20px;
    padding: ${({spacing:t})=>t[2]};
    column-gap: ${({spacing:t})=>t[1]};
    border-radius: ${({borderRadius:t})=>t[1]};
    padding: 0 ${({spacing:t})=>t[1]};
    border-radius: ${({spacing:t})=>t[1]};
  }

  /* -- Variants --------------------------------------------------------- */
  button[data-variant='accent'] {
    color: ${({tokens:t})=>t.core.textAccentPrimary};
  }

  button[data-variant='secondary'] {
    color: ${({tokens:t})=>t.theme.textSecondary};
  }

  /* -- Focus states --------------------------------------------------- */
  button:focus-visible:enabled {
    box-shadow: 0px 0px 0px 4px rgba(9, 136, 240, 0.2);
  }

  button[data-variant='accent']:focus-visible:enabled {
    background-color: ${({tokens:t})=>t.core.foregroundAccent010};
  }

  button[data-variant='secondary']:focus-visible:enabled {
    background-color: ${({tokens:t})=>t.theme.foregroundSecondary};
  }

  /* -- Hover & Active states ----------------------------------------------------------- */
  button[data-variant='accent']:hover:enabled {
    background-color: ${({tokens:t})=>t.core.foregroundAccent010};
  }

  button[data-variant='secondary']:hover:enabled {
    background-color: ${({tokens:t})=>t.theme.foregroundSecondary};
  }

  button[data-variant='accent']:focus-visible {
    background-color: ${({tokens:t})=>t.core.foregroundAccent010};
  }

  button[data-variant='secondary']:focus-visible {
    background-color: ${({tokens:t})=>t.theme.foregroundSecondary};
    box-shadow: 0px 0px 0px 4px rgba(9, 136, 240, 0.2);
  }

  button[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
  }
`;var r=function(t,n,a,c){var i=arguments.length,e=i<3?n:c===null?c=Object.getOwnPropertyDescriptor(n,a):c,s;if(typeof Reflect=="object"&&typeof Reflect.decorate=="function")e=Reflect.decorate(t,n,a,c);else for(var u=t.length-1;u>=0;u--)(s=t[u])&&(e=(i<3?s(e):i>3?s(n,a,e):s(n,a))||e);return i>3&&e&&Object.defineProperty(n,a,e),e};const y={sm:"sm-medium",md:"md-medium"},$={accent:"accent-primary",secondary:"secondary"};let o=class extends f{constructor(){super(...arguments),this.size="md",this.disabled=!1,this.variant="accent",this.icon=void 0}render(){return l`
      <button ?disabled=${this.disabled} data-variant=${this.variant}>
        <slot name="iconLeft"></slot>
        <wui-text
          color=${$[this.variant]}
          variant=${y[this.size]}
        >
          <slot></slot>
        </wui-text>
        ${this.iconTemplate()}
      </button>
    `}iconTemplate(){return this.icon?l`<wui-icon name=${this.icon} size="sm"></wui-icon>`:null}};o.styles=[p,m,h];r([d()],o.prototype,"size",void 0);r([d({type:Boolean})],o.prototype,"disabled",void 0);r([d()],o.prototype,"variant",void 0);r([d()],o.prototype,"icon",void 0);o=r([v("wui-link")],o);
