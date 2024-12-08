(()=>{"use strict";var e={n:t=>{var n=t&&t.__esModule?()=>t.default:()=>t;return e.d(n,{a:n}),n},d:(t,n)=>{for(var o in n)e.o(n,o)&&!e.o(t,o)&&Object.defineProperty(t,o,{enumerable:!0,get:n[o]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.wp.blocks,n=window.wp.element,o=window.wp.components,l=window.wp.blockEditor,s=window.wp.serverSideRender;var a=e.n(s);const r=window.wp.data,i=window.ReactJSXRuntime;function c({block_ui_config:e,attributes:t,setAttributes:s,blockName:c}){const{select:p}=wp.data,{editPost:b}=(0,r.useDispatch)("core/editor"),u=p("core"),d=p("core/editor"),_=d.getEditedPostAttribute("template"),m=u.getEntityRecord("postType",d.getCurrentPostType(),d.getCurrentPostId())?.template,f=_===e.settings.kb_block_page_template,[g,k]=(0,n.useState)({}),x=Object.fromEntries(Object.values(e).filter((e=>e.groups)).flatMap((e=>Object.values(e.groups))).filter((e=>e.fields)).flatMap((e=>Object.entries(e.fields))).map((([e,t])=>[e,t.setting_type]))),h=(0,l.useBlockProps)({onClickCapture:e=>{const t=e.target.closest("a");t&&t.closest(".eckb-block-editor-preview")&&!t.closest(".eckb-kb-no-content")&&!t.closest("#eckb-kb-faqs-not-assigned")&&e.preventDefault()}});function j(e){return"number"==typeof e?e:parseInt(e)}function y(e){let n=!0;return e.hide_on_dependencies&&Object.entries(e.hide_on_dependencies).forEach((([e,o])=>{e in x&&x[e].length&&t[e]===o&&(n=!1)})),e.hide_on_selection_amount_dependencies&&Object.entries(e.hide_on_selection_amount_dependencies).forEach((([e,o])=>{e in x&&x[e].length&&t[e].length===o&&(n=!1)})),n}return document.getElementById("echo-knowledge-base-block-editor-inline-css")&&document.getElementById("echo-knowledge-base-block-editor-inline-css").remove(),(0,n.useEffect)((()=>{Object.values(e).filter((e=>e.groups)).flatMap((e=>Object.values(e.groups))).filter((e=>e.fields)).flatMap((e=>Object.entries(e.fields))).filter((([,e])=>"template_toggle"===e.setting_type)).map((([e])=>e)).forEach((e=>{f&&"on"!==t[e]?s({[e]:"on"}):f||"on"!==t[e]||s({[e]:"off"})}))}),[f,t,e,s]),(0,i.jsxs)("div",{...h,children:[(0,i.jsx)(l.InspectorControls,{children:(0,i.jsx)(o.Panel,{className:"epkb-block-editor-controls",children:(0,i.jsx)(o.TabPanel,{className:"epkb-block-editor-tabpanel",tabs:Object.entries(e).map((([e,t])=>({name:e,title:t.title,icon:t.icon,groups:t.groups}))),children:n=>(0,i.jsx)(React.Fragment,{children:Object.entries(n.groups).map((([n,{title:a,fields:r}])=>{const c=Object.entries(r).filter((([e,t])=>y(t)));return c.length?(0,i.jsx)(o.PanelBody,{title:a,className:"epkb-block-ui-section",children:c.map((([n,a])=>{if(!y(a))return null;const r=function(e){let n=!1;return e.disable_on_dependencies&&Object.entries(e.disable_on_dependencies).forEach((([e,o])=>{t[e]===o&&(n=!0)})),n}(a);switch(a.setting_type){case"text":return(0,i.jsx)(o.TextControl,{disabled:r,label:a.label,value:t[n]||a.default,onChange:e=>s({[n]:e}),help:a.description||"",className:"epkb-block-ui-text-control"},n);case"number":return(0,i.jsx)(o.__experimentalNumberControl,{disabled:r,isShiftStepEnabled:!0,shiftStep:1,min:a.min,max:a.max,label:a.label,value:""===t[n]||Number.isNaN(parseInt(t[n]))?1:parseInt(t[n]),onChange:e=>s({[n]:""===e||Number.isNaN(parseInt(e))?1:parseInt(e)}),className:"epkb-block-ui-number-control"},n);case"color":return(0,i.jsx)(l.PanelColorSettings,{colorSettings:[{value:t[n],onChange:e=>s({[n]:e}),enableAlpha:!0,label:a.label}],className:"epkb-block-ui-color-control"},n);case"select_buttons_string":return(0,i.jsx)(o.__experimentalToggleGroupControl,{__nextHasNoMarginBottom:!0,isBlock:!0,label:a.label,onChange:e=>s({[n]:e}),value:void 0!==t[n]?t[n]:a.default,className:"epkb-block-ui-select-buttons-control",children:Object.entries(a.options).map((([e,t])=>(0,i.jsx)(o.__experimentalToggleGroupControlOption,{label:t,value:e},e)))},n);case"select_buttons_icon":return(0,i.jsx)(o.__experimentalToggleGroupControl,{__nextHasNoMarginBottom:!0,isBlock:!0,label:a.label,onChange:e=>s({[n]:e}),value:void 0!==t[n]?t[n]:a.default,className:"epkb-block-ui-select-buttons-control",children:Object.entries(a.options).map((([e,t])=>(0,i.jsx)(o.__experimentalToggleGroupControlOptionIcon,{icon:(0,i.jsx)("span",{className:"epkbfa "+t.icon_class}),label:t.label,value:e},e)))},n);case"select_buttons":return(0,i.jsx)(o.__experimentalToggleGroupControl,{__nextHasNoMarginBottom:!0,isBlock:!0,label:a.label,onChange:e=>s({[n]:j(e)}),value:void 0!==t[n]?j(t[n]):j(a.default),className:"epkb-block-ui-select-buttons-control",children:Object.entries(a.options).map((([e,t])=>(0,i.jsx)(o.__experimentalToggleGroupControlOption,{label:t,value:"number"==typeof e?e:parseInt(e)},e)))},n);case"toggle":return(0,i.jsx)(o.ToggleControl,{disabled:r,__nextHasNoMarginBottom:!0,label:a.label,checked:"on"===t[n],onChange:e=>s({[n]:e?"on":"off"}),className:"epkb-block-ui-toggle-control"},n);case"custom_toggle":return(0,i.jsx)(o.ToggleControl,{disabled:r,__nextHasNoMarginBottom:!0,label:a.label,checked:t[n]===a.options.on,onChange:e=>s({[n]:e?a.options.on:a.options.off}),className:"epkb-block-ui-toggle-control"},n);case"template_toggle":return(0,i.jsx)(o.ToggleControl,{disabled:r,__nextHasNoMarginBottom:!0,label:a.label,checked:f,onChange:t=>{s({[n]:t?"on":"off"}),b(t?{template:e.settings.kb_block_page_template}:{template:m===e.settings.kb_block_page_template?"":m})},className:"epkb-block-ui-toggle-control"},n);case"range":return(0,i.jsx)(o.RangeControl,{disabled:r,isShiftStepEnabled:!0,shiftStep:1,min:a.min,max:a.max,label:a.label,value:""===t[n]||Number.isNaN(parseInt(t[n]))?parseInt(a.default):parseInt(t[n]),onChange:e=>s({[n]:""===e||Number.isNaN(parseInt(e))?1:parseInt(e)}),className:"epkb-block-ui-range-control",help:(0,i.jsxs)(i.Fragment,{children:[a.description&&(0,i.jsx)("span",{className:"epkb-help-description",children:a.description}),a.help_text&&(0,i.jsxs)("span",{className:"epkb-help-text",children:[a.help_text," ",(0,i.jsx)("a",{href:a.help_link_url,target:"_blank",rel:"noopener noreferrer",children:a.help_link_text})]})]})},n);case"custom_dropdown":const c=t[n]||a.default,p=Object.values(a.options).find((e=>e.key===c));return(0,i.jsx)(o.CustomSelectControl,{disabled:r,__next40pxDefaultSize:!0,label:a.label,value:p,onChange:e=>s({[n]:e.selectedItem.key}),options:Object.entries(a.options).map((([e,t])=>({key:t.key,name:t.name,style:t.style}))),className:"epkb-block-ui-custom-dropdown-control"},n);case"dropdown":return(0,i.jsx)(o.CustomSelectControl,{disabled:r,__next40pxDefaultSize:!0,label:a.label,value:{key:t[n]||a.default,name:a.options[t[n]||a.default],style:{}},onChange:e=>s({[n]:e.selectedItem.key}),options:Object.entries(a.options).map((([e,t])=>({key:e,name:t,style:{}}))),className:"epkb-block-ui-dropdown-control"},n);case"presets_dropdown":const u=g[n]?g[n]:a.default;return(0,i.jsx)(o.CustomSelectControl,{disabled:r,__next40pxDefaultSize:!0,label:a.label,value:{key:u,name:a.presets[u].label,style:{}},onChange:e=>{"current"!==e.selectedItem.key&&(k((t=>({...t,[n]:e.selectedItem.key}))),Object.entries(a.presets[e.selectedItem.key].settings).forEach((([e,t])=>{s({[e]:t})})),s({theme_presets:e.selectedItem.key,theme_name:e.selectedItem.key}))},options:Object.entries(a.presets).map((([e,t])=>({key:e,name:t.label,style:{}}))),className:"epkb-block-ui-presets-dropdown-control"},n);case"checkbox_multi_select":return(0,i.jsx)(o.BaseControl,{label:a.label,className:"epkb-block-ui-checkbox-multi-select-control",children:Object.entries(a.options).map((([e,l])=>(0,i.jsx)(o.CheckboxControl,{disabled:r,__nextHasNoMarginBottom:!0,checked:-1!==t[n].indexOf(parseInt(e)),label:l,onChange:o=>{const l=parseInt(e),a=o?[...t[n],l]:t[n].filter((e=>e!==l));s({[n]:a})}},n+"_"+e)))},n);case"box_control":const d={[a.side]:""===t[n]||Number.isNaN(parseInt(t[n]))?a.default:parseInt(t[n])};return(0,i.jsx)(o.__experimentalBoxControl,{__next40pxDefaultSize:!0,label:a.label,sides:a.side,inputProps:{min:j(a.min),max:j(a.max)},values:d,onChange:e=>s({[n]:""===e[a.side]||Number.isNaN(parseInt(e[a.side]))?a.default:parseInt(e[a.side])}),className:"epkb-block-ui-box-control"},n);case"box_control_combined":const _=Object.values(a.combined_settings).map((e=>e.side)),x=Object.fromEntries(Object.entries(a.combined_settings).map((([e,n])=>{const o=""===t[e]||Number.isNaN(parseInt(t[e]))?n.default:parseInt(t[e]);return[n.side,o]}))),h=Object.fromEntries(Object.entries(a.combined_settings).map((([e,t])=>[t.side,t.default])));return(0,i.jsx)(o.__experimentalBoxControl,{__next40pxDefaultSize:!0,label:a.label,sides:_,resetValues:h,inputProps:{min:j(a.min),max:j(a.max)},values:x,onChange:e=>{Object.entries(e).forEach((([e,t])=>{Object.entries(a.combined_settings).forEach((([n,o])=>{o.side===e&&s({[n]:""===t||Number.isNaN(parseInt(t))?a.combined_settings[n].default:parseInt(t)})}))}))},className:"epkb-block-ui-box-combined-control"},n);case"typography_controls":return(0,i.jsxs)(o.__experimentalToolsPanel,{label:a.label,resetAll:()=>{s({[n]:{...t[n],font_family:a.controls.font_family.default,font_size:a.controls.font_size.default,font_appearance:a.controls.font_appearance.default}})},className:"epkb-typography-controls",children:[(0,i.jsx)(o.__experimentalToolsPanelItem,{hasValue:()=>t[n].font_family!==a.controls.font_family.default,label:a.controls.font_family.label,onDeselect:()=>{s({[n]:{...t[n],font_family:a.controls.font_family.default}})},children:(0,i.jsx)(l.__experimentalFontFamilyControl,{__next40pxDefaultSize:!0,fontFamilies:Object.entries(epkb_block_editor_vars.font_families).map((([e,t])=>({fontFamily:t,name:t,slug:t}))),onChange:e=>{const o={...t[n],font_family:e};s({[n]:o})},value:t[n].font_family||a.controls.font_family.default},n+"_font_family")}),(0,i.jsx)(o.__experimentalToolsPanelItem,{hasValue:()=>!0,label:a.controls.font_size.label,onDeselect:()=>{s({[n]:{...t[n],font_size:a.controls.font_size.default}})},isShownByDefault:!0,children:(0,i.jsx)(o.FontSizePicker,{fontSizes:Object.entries(a.controls.font_size.options).map((([e,t])=>({name:t.name,size:t.size,slug:e}))),value:t[n].font_size||a.controls.font_size.default,onChange:e=>{const o={...t[n],font_size:e};s({[n]:o})},disableCustomFontSizes:!1,withReset:!1,withSlider:!0,fallbackFontSize:a.controls.font_size.default},n+"_font_size")}),(0,i.jsx)(o.__experimentalToolsPanelItem,{hasValue:()=>t[n].font_appearance!==a.controls.font_appearance.default,label:a.controls.font_appearance.label,onDeselect:()=>{s({[n]:{...t[n],font_appearance:a.controls.font_appearance.default}})},children:(0,i.jsx)(o.CustomSelectControl,{__next40pxDefaultSize:!0,label:a.controls.font_appearance.label,value:(()=>{const e=t[n].font_appearance||a.controls.font_appearance.default;return{key:e,...a.controls.font_appearance.options[e]}})(),onChange:e=>{const o={...t[n],font_appearance:e.selectedItem.key};s({[n]:o})},options:Object.entries(a.controls.font_appearance.options).map((([e,t])=>({key:e,name:t.name,style:t.style})))},n+"_font_appearance")})]},n);case"section_description":return(0,i.jsxs)("div",{className:"epkb-block-ui-section-description",children:[(0,i.jsx)("span",{children:a.description}),a.link_text.length>0?(0,i.jsx)("a",{href:a.link_url,target:"_blank",rel:"noopener noreferrer",children:a.link_text}):null]},n);default:return null}}))},n):null}))})})})}),(0,i.jsx)(a(),{block:c,attributes:t,epkbBlockStorage:g,urlQueryArgs:{is_editor_preview:1},httpMethod:"POST"})]})}(0,t.registerBlockType)("echo-knowledge-base/featured-articles",{edit:function({attributes:e,setAttributes:t,name:n}){return epkb_featured_articles_block_ui_config?(0,i.jsx)(c,{block_ui_config:epkb_featured_articles_block_ui_config,attributes:e,setAttributes:t,blockName:n}):(0,i.jsx)(i.Fragment,{children:(0,i.jsx)("div",{children:"Unable to load all assets."})})},save:function({attributes:e}){return l.useBlockProps.save(),null}}),function(e){const{unregisterBlockType:t,getBlockType:n}=e.blocks,{select:o,subscribe:l}=e.data;e.domReady((()=>{let e=!1;const s=l((()=>{if(e)return;let l=null;if(o("core/editor")&&"function"==typeof o("core/editor").getCurrentPostType&&(l=o("core/editor").getCurrentPostType()),l){if("page"===l)return e=!0,void s();if(n("echo-knowledge-base/featured-articles"))try{t("echo-knowledge-base/featured-articles")}catch(e){}e=!0,s()}}))}))}(window.wp)})();