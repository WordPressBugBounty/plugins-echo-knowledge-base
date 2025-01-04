!function(){"use strict";var e={n:function(t){var o=t&&t.__esModule?function(){return t.default}:function(){return t};return e.d(o,{a:o}),o},d:function(t,o){for(var n in o)e.o(o,n)&&!e.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:o[n]})},o:function(e,t){return Object.prototype.hasOwnProperty.call(e,t)}},t=window.wp.blocks,o=window.wp.element,n=window.wp.components,l=window.wp.blockEditor,s=window.wp.serverSideRender,a=e.n(s),r=window.wp.data,i=window.ReactJSXRuntime;function c({block_ui_config:e,attributes:t,setAttributes:s,blockName:c}){const{select:p}=wp.data,{editPost:b}=(0,r.useDispatch)("core/editor"),u=p("core"),d=p("core/editor"),m=d.getEditedPostAttribute("template"),_=u.getEntityRecord("postType",d.getCurrentPostType(),d.getCurrentPostId())?.template,f=m===e.settings.kb_block_page_template,[k,g]=(0,o.useState)({}),h=Object.fromEntries(Object.values(e).filter((e=>e.groups)).flatMap((e=>Object.values(e.groups))).filter((e=>e.fields)).flatMap((e=>Object.entries(e.fields))).map((([e,t])=>[e,t.setting_type]))),x=(0,l.useBlockProps)({onClickCapture:e=>{const t=e.target.closest("a");t&&t.closest(".eckb-block-editor-preview")&&!t.closest(".eckb-kb-no-content")&&!t.closest("#eckb-kb-faqs-not-assigned")&&e.preventDefault()}});function j(e){return"number"==typeof e?e:parseInt(e)}function y(e){let o=!0;return e.hide_on_dependencies&&Object.entries(e.hide_on_dependencies).forEach((([e,n])=>{e in h&&h[e].length&&t[e]===n&&(o=!1)})),e.hide_on_selection_amount_dependencies&&Object.entries(e.hide_on_selection_amount_dependencies).forEach((([e,n])=>{e in h&&h[e].length&&t[e].length===n&&(o=!1)})),o}return document.getElementById("echo-knowledge-base-block-editor-inline-css")&&document.getElementById("echo-knowledge-base-block-editor-inline-css").remove(),(0,o.useEffect)((()=>{Object.values(e).filter((e=>e.groups)).flatMap((e=>Object.values(e.groups))).filter((e=>e.fields)).flatMap((e=>Object.entries(e.fields))).filter((([,e])=>"template_toggle"===e.setting_type)).map((([e])=>e)).forEach((e=>{f&&"on"!==t[e]?s({[e]:"on"}):f||"on"!==t[e]||s({[e]:"off"})}))}),[f,t,e,s]),(0,i.jsxs)("div",{...x,children:[(0,i.jsx)(l.InspectorControls,{children:(0,i.jsx)(n.Panel,{className:"epkb-block-editor-controls",children:(0,i.jsx)(n.TabPanel,{className:"epkb-block-editor-tabpanel",tabs:Object.entries(e).map((([e,t])=>({name:e,title:t.title,icon:t.icon,groups:t.groups}))),children:o=>(0,i.jsx)(React.Fragment,{children:Object.entries(o.groups).map((([o,{title:a,fields:r}])=>{const c=Object.entries(r).filter((([e,t])=>y(t)));return c.length?(0,i.jsx)(n.PanelBody,{title:a,className:"epkb-block-ui-section",children:c.map((([o,a])=>{if(!y(a))return null;const r=function(e){let o=!1;return e.disable_on_dependencies&&Object.entries(e.disable_on_dependencies).forEach((([e,n])=>{t[e]===n&&(o=!0)})),o}(a);switch(a.setting_type){case"text":return(0,i.jsx)(n.TextControl,{disabled:r,label:a.label,value:t[o]||a.default,onChange:e=>s({[o]:e}),help:a.description||"",className:"epkb-block-ui-text-control"},o);case"number":return(0,i.jsx)(n.__experimentalNumberControl,{disabled:r,isShiftStepEnabled:!0,shiftStep:1,min:a.min,max:a.max,label:a.label,value:""===t[o]||Number.isNaN(parseInt(t[o]))?1:parseInt(t[o]),onChange:e=>s({[o]:""===e||Number.isNaN(parseInt(e))?1:parseInt(e)}),className:"epkb-block-ui-number-control"},o);case"color":return(0,i.jsx)(l.PanelColorSettings,{colorSettings:[{value:t[o],onChange:e=>s({[o]:e}),enableAlpha:!0,label:a.label}],className:"epkb-block-ui-color-control"},o);case"select_buttons_string":return(0,i.jsx)(n.__experimentalToggleGroupControl,{__nextHasNoMarginBottom:!0,isBlock:!0,label:a.label,onChange:e=>s({[o]:e}),value:void 0!==t[o]?t[o]:a.default,className:"epkb-block-ui-select-buttons-control",children:Object.entries(a.options).map((([e,t])=>(0,i.jsx)(n.__experimentalToggleGroupControlOption,{label:t,value:e},e)))},o);case"select_buttons_icon":return(0,i.jsx)(n.__experimentalToggleGroupControl,{__nextHasNoMarginBottom:!0,isBlock:!0,label:a.label,onChange:e=>s({[o]:e}),value:void 0!==t[o]?t[o]:a.default,className:"epkb-block-ui-select-buttons-control",children:Object.entries(a.options).map((([e,t])=>(0,i.jsx)(n.__experimentalToggleGroupControlOptionIcon,{icon:(0,i.jsx)("span",{className:"epkbfa "+t.icon_class}),label:t.label,value:e},e)))},o);case"select_buttons":return(0,i.jsx)(n.__experimentalToggleGroupControl,{__nextHasNoMarginBottom:!0,isBlock:!0,label:a.label,onChange:e=>s({[o]:j(e)}),value:void 0!==t[o]?j(t[o]):j(a.default),className:"epkb-block-ui-select-buttons-control",children:Object.entries(a.options).map((([e,t])=>(0,i.jsx)(n.__experimentalToggleGroupControlOption,{label:t,value:"number"==typeof e?e:parseInt(e)},e)))},o);case"toggle":return(0,i.jsx)(n.ToggleControl,{disabled:r,__nextHasNoMarginBottom:!0,label:a.label,checked:"on"===t[o],onChange:e=>s({[o]:e?"on":"off"}),className:"epkb-block-ui-toggle-control"},o);case"custom_toggle":return(0,i.jsx)(n.ToggleControl,{disabled:r,__nextHasNoMarginBottom:!0,label:a.label,checked:t[o]===a.options.on,onChange:e=>s({[o]:e?a.options.on:a.options.off}),className:"epkb-block-ui-toggle-control"},o);case"template_toggle":return(0,i.jsx)(n.ToggleControl,{disabled:r,__nextHasNoMarginBottom:!0,label:a.label,checked:f,onChange:t=>{s({[o]:t?"on":"off"}),b(t?{template:e.settings.kb_block_page_template}:{template:_===e.settings.kb_block_page_template?"":_})},className:"epkb-block-ui-toggle-control"},o);case"range":return(0,i.jsx)(n.RangeControl,{disabled:r,isShiftStepEnabled:!0,shiftStep:1,min:a.min,max:a.max,label:a.label,value:""===t[o]||Number.isNaN(parseInt(t[o]))?parseInt(a.default):parseInt(t[o]),onChange:e=>s({[o]:""===e||Number.isNaN(parseInt(e))?1:parseInt(e)}),className:"epkb-block-ui-range-control",help:(0,i.jsxs)(i.Fragment,{children:[a.description&&(0,i.jsx)("span",{className:"epkb-help-description",children:a.description}),a.help_text&&(0,i.jsxs)("span",{className:"epkb-help-text",children:[a.help_text," ",(0,i.jsx)("a",{href:a.help_link_url,target:"_blank",rel:"noopener noreferrer",children:a.help_link_text})]})]})},o);case"custom_dropdown":const c=t[o]||a.default,p=Object.values(a.options).find((e=>e.key===c));return(0,i.jsx)(n.CustomSelectControl,{disabled:r,__next40pxDefaultSize:!0,label:a.label,value:p,onChange:e=>s({[o]:e.selectedItem.key}),options:Object.entries(a.options).map((([e,t])=>({key:t.key,name:t.name,style:t.style}))),className:"epkb-block-ui-custom-dropdown-control"},o);case"dropdown":return(0,i.jsx)(n.CustomSelectControl,{disabled:r,__next40pxDefaultSize:!0,label:a.label,value:{key:t[o]||a.default,name:a.options[t[o]||a.default],style:{}},onChange:e=>s({[o]:e.selectedItem.key}),options:Object.entries(a.options).map((([e,t])=>({key:e,name:t,style:{}}))),className:"epkb-block-ui-dropdown-control"},o);case"presets_dropdown":const u=k[o]?k[o]:a.default;return(0,i.jsx)(n.CustomSelectControl,{disabled:r,__next40pxDefaultSize:!0,label:a.label,value:{key:u,name:a.presets[u].label,style:{}},onChange:e=>{"current"!==e.selectedItem.key&&(g((t=>({...t,[o]:e.selectedItem.key}))),Object.entries(a.presets[e.selectedItem.key].settings).forEach((([e,t])=>{s({[e]:t})})),s({theme_presets:e.selectedItem.key,theme_name:e.selectedItem.key}))},options:Object.entries(a.presets).map((([e,t])=>({key:e,name:t.label,style:{}}))),className:"epkb-block-ui-presets-dropdown-control"},o);case"checkbox_multi_select":return(0,i.jsx)(n.BaseControl,{label:a.label,className:"epkb-block-ui-checkbox-multi-select-control",children:Object.entries(a.options).map((([e,l])=>(0,i.jsx)(n.CheckboxControl,{disabled:r,__nextHasNoMarginBottom:!0,checked:-1!==t[o].indexOf(parseInt(e)),label:l,onChange:n=>{const l=parseInt(e),a=n?[...t[o],l]:t[o].filter((e=>e!==l));s({[o]:a})}},o+"_"+e)))},o);case"box_control":const d={[a.side]:""===t[o]||Number.isNaN(parseInt(t[o]))?a.default:parseInt(t[o])};return(0,i.jsx)(n.__experimentalBoxControl,{__next40pxDefaultSize:!0,label:a.label,sides:a.side,inputProps:{min:j(a.min),max:j(a.max)},values:d,onChange:e=>s({[o]:""===e[a.side]||Number.isNaN(parseInt(e[a.side]))?a.default:parseInt(e[a.side])}),className:"epkb-block-ui-box-control"},o);case"box_control_combined":const m=Object.values(a.combined_settings).map((e=>e.side)),h=Object.fromEntries(Object.entries(a.combined_settings).map((([e,o])=>{const n=""===t[e]||Number.isNaN(parseInt(t[e]))?o.default:parseInt(t[e]);return[o.side,n]}))),x=Object.fromEntries(Object.entries(a.combined_settings).map((([e,t])=>[t.side,t.default])));return(0,i.jsx)(n.__experimentalBoxControl,{__next40pxDefaultSize:!0,label:a.label,sides:m,resetValues:x,inputProps:{min:j(a.min),max:j(a.max)},values:h,onChange:e=>{Object.entries(e).forEach((([e,t])=>{Object.entries(a.combined_settings).forEach((([o,n])=>{n.side===e&&s({[o]:""===t||Number.isNaN(parseInt(t))?a.combined_settings[o].default:parseInt(t)})}))}))},className:"epkb-block-ui-box-combined-control"},o);case"typography_controls":return(0,i.jsxs)(n.__experimentalToolsPanel,{label:a.label,resetAll:()=>{s({[o]:{...t[o],font_family:a.controls.font_family.default,font_size:a.controls.font_size.default,font_appearance:a.controls.font_appearance.default}})},className:"epkb-typography-controls",children:[(0,i.jsx)(n.__experimentalToolsPanelItem,{hasValue:()=>t[o].font_family!==a.controls.font_family.default,label:a.controls.font_family.label,onDeselect:()=>{s({[o]:{...t[o],font_family:a.controls.font_family.default}})},children:(0,i.jsx)(l.__experimentalFontFamilyControl,{__next40pxDefaultSize:!0,fontFamilies:Object.entries(epkb_block_editor_vars.font_families).map((([e,t])=>({fontFamily:t,name:t,slug:t}))),onChange:e=>{const n={...t[o],font_family:e};s({[o]:n})},value:t[o].font_family||a.controls.font_family.default},o+"_font_family")}),(0,i.jsx)(n.__experimentalToolsPanelItem,{hasValue:()=>!0,label:a.controls.font_size.label,onDeselect:()=>{s({[o]:{...t[o],font_size:a.controls.font_size.default}})},isShownByDefault:!0,children:(0,i.jsx)(n.FontSizePicker,{fontSizes:Object.entries(a.controls.font_size.options).map((([e,t])=>({name:t.name,size:t.size,slug:e}))),value:t[o].font_size||a.controls.font_size.default,onChange:e=>{const n={...t[o],font_size:e};s({[o]:n})},disableCustomFontSizes:!1,withReset:!1,withSlider:!0,fallbackFontSize:a.controls.font_size.default},o+"_font_size")}),(0,i.jsx)(n.__experimentalToolsPanelItem,{hasValue:()=>t[o].font_appearance!==a.controls.font_appearance.default,label:a.controls.font_appearance.label,onDeselect:()=>{s({[o]:{...t[o],font_appearance:a.controls.font_appearance.default}})},children:(0,i.jsx)(n.CustomSelectControl,{__next40pxDefaultSize:!0,label:a.controls.font_appearance.label,value:(()=>{const e=t[o].font_appearance||a.controls.font_appearance.default;return{key:e,...a.controls.font_appearance.options[e]}})(),onChange:e=>{const n={...t[o],font_appearance:e.selectedItem.key};s({[o]:n})},options:Object.entries(a.controls.font_appearance.options).map((([e,t])=>({key:e,name:t.name,style:t.style})))},o+"_font_appearance")})]},o);case"section_description":return(0,i.jsxs)("div",{className:"epkb-block-ui-section-description",children:[(0,i.jsx)("span",{children:a.description}),a.link_text.length>0?(0,i.jsx)("a",{href:a.link_url,target:"_blank",rel:"noopener noreferrer",children:a.link_text}):null]},o);default:return null}}))},o):null}))})})})}),(0,i.jsx)(a(),{block:c,attributes:t,epkbBlockStorage:k,urlQueryArgs:{is_editor_preview:1},httpMethod:"POST"})]})}(0,t.registerBlockType)("echo-knowledge-base/tabs-layout",{icon:{src:(0,i.jsxs)("svg",{id:"Tabs_Layout","data-name":"Tabs Layout",xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 119.27 79.15",fill:"none",children:[(0,i.jsx)("path",{d:"M65 0.85h53.66v16.78H65Z",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M0.71 0.85h55.61v16.78H0.71Z",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M23.54 12.85h9.95v9.56h-9.95Z",transform:"rotate(45 28.52 18.63)",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M1.91 34.32h35.8v11.9H1.91Z",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M2.83 58.24h33.95v4.2H2.83Z",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M2.66 74.31h33.95v4.2H2.66Z",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M42.39 58.39h33.95v4.2H42.39Z",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M42.22 74.46h33.95v4.2H42.22Z",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M82.34 58.39h33.95v4.2H82.34Z",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M82.12 74.46h33.95v4.2H82.12Z",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M41.69 34.46h35.8v11.9H41.69Z",stroke:"#000",strokeMiterlimit:"10"}),(0,i.jsx)("path",{d:"M81.47 34.46h35.8v11.9H81.47Z",stroke:"#000",strokeMiterlimit:"10"})]})},edit:function({attributes:e,setAttributes:t,name:o}){return epkb_tabs_layout_block_ui_config?(0,i.jsx)(c,{block_ui_config:epkb_tabs_layout_block_ui_config,attributes:e,setAttributes:t,blockName:o}):(0,i.jsx)(i.Fragment,{children:(0,i.jsx)("div",{children:"Unable to load all assets."})})},save:function({attributes:e}){return l.useBlockProps.save(),null}}),function(e,t){const{unregisterBlockType:o,getBlockType:n}=e.blocks,{select:l,subscribe:s}=e.data;e.domReady((()=>{let e=!1;const a=s((()=>{if(e)return;let s=null;if(l("core/editor")&&"function"==typeof l("core/editor").getCurrentPostType&&(s=l("core/editor").getCurrentPostType()),s){if("page"===s)return e=!0,void a();if(n("echo-knowledge-base/"+t))try{o("echo-knowledge-base/"+t)}catch(e){}e=!0,a()}}))}))}(window.wp,"tabs-layout")}();