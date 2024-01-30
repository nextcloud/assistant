"use strict";
(self["webpackChunkassistant"] = self["webpackChunkassistant"] || []).push([["speech-to-text-picker-lazy"],{

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/icons/AssistantIcon.vue?vue&type=script&lang=js":
/*!****************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/icons/AssistantIcon.vue?vue&type=script&lang=js ***!
  \****************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  name: 'AssistantIcon',
  props: {
    title: {
      type: String,
      default: ''
    },
    fillColor: {
      type: String,
      default: 'currentColor'
    },
    size: {
      type: Number,
      default: 24
    }
  }
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=script&lang=js":
/*!************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=script&lang=js ***!
  \************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var vue_material_design_icons_ArrowRight_vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vue-material-design-icons/ArrowRight.vue */ "./node_modules/vue-material-design-icons/ArrowRight.vue");
/* harmony import */ var _components_icons_AssistantIcon_vue__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../components/icons/AssistantIcon.vue */ "./src/components/icons/AssistantIcon.vue");
/* harmony import */ var _nextcloud_vue_dist_Components_NcButton_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcButton.js */ "./node_modules/@nextcloud/vue/dist/Components/NcButton.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcLoadingIcon_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcLoadingIcon.js */ "./node_modules/@nextcloud/vue/dist/Components/NcLoadingIcon.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcCheckboxRadioSwitch_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js */ "./node_modules/@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.mjs");
/* harmony import */ var vue2_audio_recorder__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! vue2-audio-recorder */ "./node_modules/vue2-audio-recorder/dist/vue-audio-recorder.min.js");
/* harmony import */ var vue2_audio_recorder__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(vue2_audio_recorder__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _nextcloud_axios__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @nextcloud/axios */ "./node_modules/@nextcloud/axios/dist/index.es.mjs");
/* harmony import */ var _nextcloud_router__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @nextcloud/router */ "./node_modules/@nextcloud/router/dist/index.js");
/* harmony import */ var _nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @nextcloud/dialogs */ "./node_modules/@nextcloud/dialogs/dist/index.mjs");
/* harmony import */ var vue__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! vue */ "./node_modules/vue/dist/vue.runtime.esm.js");










vue__WEBPACK_IMPORTED_MODULE_9__["default"].use((vue2_audio_recorder__WEBPACK_IMPORTED_MODULE_5___default()));
const VALID_MIME_TYPES = ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/webm', 'audio/opus', 'audio/flac', 'audio/vorbis', 'audio/m4b'];
const picker = (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_8__.getFilePickerBuilder)(t('assistant', 'Choose Audio File')).setMimeTypeFilter(VALID_MIME_TYPES).setMultiSelect(false).allowDirectories(false).setType(1).build();
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  name: 'SpeechToTextCustomPickerElement',
  components: {
    ArrowRightIcon: vue_material_design_icons_ArrowRight_vue__WEBPACK_IMPORTED_MODULE_0__["default"],
    NcButton: _nextcloud_vue_dist_Components_NcButton_js__WEBPACK_IMPORTED_MODULE_2__["default"],
    NcCheckboxRadioSwitch: _nextcloud_vue_dist_Components_NcCheckboxRadioSwitch_js__WEBPACK_IMPORTED_MODULE_4__["default"],
    NcLoadingIcon: _nextcloud_vue_dist_Components_NcLoadingIcon_js__WEBPACK_IMPORTED_MODULE_3__["default"],
    AssistantIcon: _components_icons_AssistantIcon_vue__WEBPACK_IMPORTED_MODULE_1__["default"]
  },
  props: {
    providerId: {
      type: String,
      required: true
    },
    accessible: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      loading: false,
      mode: 'record',
      audioData: null,
      audioFilePath: null
    };
  },
  methods: {
    resetAudioState() {
      this.audioData = null;
      this.audioFilePath = null;
    },
    async onChooseButtonClick() {
      this.audioFilePath = await picker.pick();
    },
    async onRecordEnd(e) {
      try {
        this.audioData = e.blob;
      } catch (error) {
        console.error('Recording error:', error);
        this.audioData = null;
      }
    },
    async onInputEnter() {
      if (this.mode === 'record') {
        const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_7__.generateUrl)('/apps/assistant/stt/transcribeAudio');
        const formData = new FormData();
        formData.append('audioData', this.audioData);
        await this.apiRequest(url, formData);
      } else {
        const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_7__.generateUrl)('/apps/assistant/stt/transcribeFile');
        const params = {
          path: this.audioFilePath
        };
        await this.apiRequest(url, params);
      }
      this.resetAudioState();
    },
    async apiRequest(url, data) {
      this.loading = true;
      try {
        await _nextcloud_axios__WEBPACK_IMPORTED_MODULE_6__["default"].post(url, data);
        (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_8__.showSuccess)(t('assistant', 'Successfully scheduled transcription'));
        this.$emit('submit', '');
      } catch (error) {
        console.error('API error:', error);
        this.resetAudioState();
        (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_8__.showError)(t('assistant', 'Failed to schedule transcription') + (': ' + error.response?.data || 0 || 0));
      } finally {
        this.loading = false;
      }
    }
  }
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/icons/AssistantIcon.vue?vue&type=template&id=14653342":
/*!***************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/icons/AssistantIcon.vue?vue&type=template&id=14653342 ***!
  \***************************************************************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   render: () => (/* binding */ render),
/* harmony export */   staticRenderFns: () => (/* binding */ staticRenderFns)
/* harmony export */ });
var render = function render() {
  var _vm = this,
    _c = _vm._self._c;
  return _c('span', _vm._b({
    staticClass: "material-design-icon assistant-icon",
    attrs: {
      "aria-hidden": !_vm.title,
      "aria-label": _vm.title,
      "role": "img"
    },
    on: {
      "click": function ($event) {
        return _vm.$emit('click', $event);
      }
    }
  }, 'span', _vm.$attrs, false), [_c('svg', {
    attrs: {
      "fill": _vm.fillColor,
      "width": _vm.size,
      "height": _vm.size,
      "enable-background": "new 0 0 24 24",
      "version": "1.1",
      "viewBox": "0 0 24 24",
      "xml:space": "preserve",
      "xmlns": "http://www.w3.org/2000/svg"
    }
  }, [_c('path', {
    attrs: {
      "d": "M18,4C16.29,4 15.25,4.33 14.65,4.61C13.88,4.23 13,4 12,4C11,4 10.12,4.23 9.35,4.61C8.75,4.33 7.71,4 6,4C3,4 1,12 1,14C1,14.83 2.32,15.59 4.14,15.9C4.78,18.14 7.8,19.85 11.5,20V15.72C10.91,15.35 10,14.68 10,14C10,13 12,13 12,13C12,13 14,13 14,14C14,14.68 13.09,15.35 12.5,15.72V20C16.2,19.85 19.22,18.14 19.86,15.9C21.68,15.59 23,14.83 23,14C23,12 21,4 18,4M4.15,13.87C3.65,13.75 3.26,13.61 3,13.5C3.25,10.73 5.2,6.4 6.05,6C6.59,6 7,6.06 7.37,6.11C5.27,8.42 4.44,12.04 4.15,13.87M9,12A1,1 0 0,1 8,11C8,10.46 8.45,10 9,10A1,1 0 0,1 10,11C10,11.56 9.55,12 9,12M15,12A1,1 0 0,1 14,11C14,10.46 14.45,10 15,10A1,1 0 0,1 16,11C16,11.56 15.55,12 15,12M19.85,13.87C19.56,12.04 18.73,8.42 16.63,6.11C17,6.06 17.41,6 17.95,6C18.8,6.4 20.75,10.73 21,13.5C20.75,13.61 20.36,13.75 19.85,13.87Z"
    }
  })])]);
};
var staticRenderFns = [];
render._withStripped = true;


/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=template&id=59332248&scoped=true":
/*!***********************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=template&id=59332248&scoped=true ***!
  \***********************************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   render: () => (/* binding */ render),
/* harmony export */   staticRenderFns: () => (/* binding */ staticRenderFns)
/* harmony export */ });
var render = function render() {
  var _vm = this,
    _c = _vm._self._c;
  return _c('div', {
    staticClass: "picker-content-wrapper"
  }, [_c('div', {
    staticClass: "picker-content"
  }, [_c('h2', [_c('AssistantIcon', {
    staticClass: "icon",
    attrs: {
      "size": 24
    }
  }), _vm._v("\n\t\t\t" + _vm._s(_vm.t('assistant', 'Speech to Text')) + "\n\t\t")], 1), _vm._v(" "), _c('div', {
    staticClass: "form-wrapper"
  }, [_c('div', {
    staticClass: "line justified"
  }, [_c('div', {
    staticClass: "radios"
  }, [_c('NcCheckboxRadioSwitch', {
    attrs: {
      "button-variant": true,
      "checked": _vm.mode,
      "type": "radio",
      "value": "record",
      "button-variant-grouped": "horizontal",
      "name": "mode"
    },
    on: {
      "update:checked": [function ($event) {
        _vm.mode = $event;
      }, _vm.resetAudioState]
    }
  }, [_vm._v("\n\t\t\t\t\t\t" + _vm._s(_vm.t('assistant', 'Record Audio')) + "\n\t\t\t\t\t")]), _vm._v(" "), _c('NcCheckboxRadioSwitch', {
    attrs: {
      "button-variant": true,
      "checked": _vm.mode,
      "type": "radio",
      "value": "choose",
      "button-variant-grouped": "horizontal",
      "name": "mode"
    },
    on: {
      "update:checked": [function ($event) {
        _vm.mode = $event;
      }, _vm.resetAudioState]
    }
  }, [_vm._v("\n\t\t\t\t\t\t" + _vm._s(_vm.t('assistant', 'Choose Audio File')) + "\n\t\t\t\t\t")])], 1)])]), _vm._v(" "), _vm.mode === 'record' ? _c('audio-recorder', {
    staticClass: "recorder",
    attrs: {
      "attempts": 1,
      "time": 300,
      "show-download-button": false,
      "show-upload-button": false,
      "after-recording": _vm.onRecordEnd
    }
  }) : _c('div', [_c('div', {
    staticClass: "line"
  }, [_vm._v("\n\t\t\t\t" + _vm._s(_vm.audioFilePath == null ? _vm.t('assistant', 'No audio file selected') : _vm.t('assistant', 'Selected Audio File:') + " " + _vm.audioFilePath.split('/').pop()) + "\n\t\t\t")]), _vm._v(" "), _c('div', {
    staticClass: "line justified"
  }, [_c('NcButton', {
    attrs: {
      "disabled": _vm.loading
    },
    on: {
      "click": _vm.onChooseButtonClick
    }
  }, [_vm._v("\n\t\t\t\t\t" + _vm._s(_vm.t('assistant', 'Choose Audio File')) + "\n\t\t\t\t")])], 1)]), _vm._v(" "), _c('div', {
    staticClass: "footer"
  }, [_c('NcButton', {
    attrs: {
      "type": "primary",
      "disabled": _vm.loading || _vm.audioData == null && _vm.audioFilePath == null
    },
    on: {
      "click": _vm.onInputEnter
    },
    scopedSlots: _vm._u([{
      key: "icon",
      fn: function () {
        return [_vm.loading ? _c('NcLoadingIcon', {
          attrs: {
            "size": 20
          }
        }) : _c('ArrowRightIcon')];
      },
      proxy: true
    }])
  }, [_vm._v("\n\t\t\t\t" + _vm._s(_vm.t('assistant', 'Schedule Transcription')) + "\n\t\t\t")])], 1)], 1)]);
};
var staticRenderFns = [];
render._withStripped = true;


/***/ }),

/***/ "./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss":
/*!******************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss ***!
  \******************************************************************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_css_loader_dist_runtime_noSourceMaps_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../node_modules/css-loader/dist/runtime/noSourceMaps.js */ "./node_modules/css-loader/dist/runtime/noSourceMaps.js");
/* harmony import */ var _node_modules_css_loader_dist_runtime_noSourceMaps_js__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_css_loader_dist_runtime_noSourceMaps_js__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../../node_modules/css-loader/dist/runtime/api.js */ "./node_modules/css-loader/dist/runtime/api.js");
/* harmony import */ var _node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_1__);
// Imports


var ___CSS_LOADER_EXPORT___ = _node_modules_css_loader_dist_runtime_api_js__WEBPACK_IMPORTED_MODULE_1___default()((_node_modules_css_loader_dist_runtime_noSourceMaps_js__WEBPACK_IMPORTED_MODULE_0___default()));
// Module
___CSS_LOADER_EXPORT___.push([module.id, `.picker-content-wrapper[data-v-59332248] {
  width: 100%;
}
.picker-content[data-v-59332248] {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 12px 16px 16px 16px;
}
.picker-content h2[data-v-59332248] {
  display: flex;
  align-items: center;
  gap: 8px;
}
.picker-content h2 .icon[data-v-59332248] {
  color: var(--color-primary);
}
.picker-content .form-wrapper[data-v-59332248] {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  margin: 8px 0;
}
.picker-content .form-wrapper .radios[data-v-59332248] {
  display: flex;
}
.picker-content .line[data-v-59332248] {
  display: flex;
  align-items: center;
  margin-top: 8px;
  width: 100%;
}
.picker-content .line.justified[data-v-59332248] {
  justify-content: center;
}
.picker-content .footer[data-v-59332248] {
  display: flex;
  align-items: center;
  justify-content: end;
  gap: 8px;
  margin-top: 8px;
  width: 100%;
}
.picker-content[data-v-59332248] .recorder {
  background-color: var(--color-main-background) !important;
  box-shadow: unset !important;
}
.picker-content[data-v-59332248] .recorder .ar-content * {
  color: var(--color-main-text) !important;
}
.picker-content[data-v-59332248] .recorder .ar-icon {
  background-color: var(--color-main-background) !important;
  fill: var(--color-main-text) !important;
  border: 1px solid var(--color-border) !important;
}
.picker-content[data-v-59332248] .recorder .ar-recorder__time-limit {
  position: unset !important;
}
.picker-content[data-v-59332248] .recorder .ar-player-bar {
  border: 1px solid var(--color-border) !important;
}
.picker-content[data-v-59332248] .recorder .ar-player .ar-line-control {
  background-color: var(--color-background-dark) !important;
}
.picker-content[data-v-59332248] .recorder .ar-player .ar-line-control__head {
  background-color: var(--color-main-text) !important;
}
.picker-content[data-v-59332248] .recorder .ar-player__time {
  font-size: 14px;
}
.picker-content[data-v-59332248] .recorder .ar-player .ar-volume__icon {
  background-color: var(--color-main-background) !important;
  fill: var(--color-main-text) !important;
}
.picker-content[data-v-59332248] .recorder .ar-records {
  height: unset !important;
}
.picker-content[data-v-59332248] .recorder .ar-records__record {
  border-bottom: 1px solid var(--color-border) !important;
}
.picker-content[data-v-59332248] .recorder .ar-records__record--selected {
  background-color: var(--color-background-dark) !important;
  border: 1px solid var(--color-border) !important;
}
.picker-content[data-v-59332248] .recorder .ar-records__record--selected .ar-icon {
  background-color: var(--color-background-dark) !important;
}`, ""]);
// Exports
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (___CSS_LOADER_EXPORT___);


/***/ }),

/***/ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss":
/*!**********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss ***!
  \**********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/injectStylesIntoStyleTag.js */ "./node_modules/style-loader/dist/runtime/injectStylesIntoStyleTag.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/styleDomAPI.js */ "./node_modules/style-loader/dist/runtime/styleDomAPI.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/insertBySelector.js */ "./node_modules/style-loader/dist/runtime/insertBySelector.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/setAttributesWithoutAttributes.js */ "./node_modules/style-loader/dist/runtime/setAttributesWithoutAttributes.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/insertStyleElement.js */ "./node_modules/style-loader/dist/runtime/insertStyleElement.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! !../../../node_modules/style-loader/dist/runtime/styleTagTransform.js */ "./node_modules/style-loader/dist/runtime/styleTagTransform.js");
/* harmony import */ var _node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_style_index_0_id_59332248_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! !!../../../node_modules/css-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/sass-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss */ "./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss");

      
      
      
      
      
      
      
      
      

var options = {};

options.styleTagTransform = (_node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5___default());
options.setAttributes = (_node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3___default());

      options.insert = _node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2___default().bind(null, "head");
    
options.domAPI = (_node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1___default());
options.insertStyleElement = (_node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4___default());

var update = _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0___default()(_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_style_index_0_id_59332248_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"], options);




       /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_style_index_0_id_59332248_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"] && _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_style_index_0_id_59332248_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals ? _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_style_index_0_id_59332248_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals : undefined);


/***/ }),

/***/ "./src/components/icons/AssistantIcon.vue":
/*!************************************************!*\
  !*** ./src/components/icons/AssistantIcon.vue ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _AssistantIcon_vue_vue_type_template_id_14653342__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./AssistantIcon.vue?vue&type=template&id=14653342 */ "./src/components/icons/AssistantIcon.vue?vue&type=template&id=14653342");
/* harmony import */ var _AssistantIcon_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./AssistantIcon.vue?vue&type=script&lang=js */ "./src/components/icons/AssistantIcon.vue?vue&type=script&lang=js");
/* harmony import */ var _node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! !../../../node_modules/vue-loader/lib/runtime/componentNormalizer.js */ "./node_modules/vue-loader/lib/runtime/componentNormalizer.js");





/* normalize component */
;
var component = (0,_node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_2__["default"])(
  _AssistantIcon_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"],
  _AssistantIcon_vue_vue_type_template_id_14653342__WEBPACK_IMPORTED_MODULE_0__.render,
  _AssistantIcon_vue_vue_type_template_id_14653342__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns,
  false,
  null,
  null,
  null
  
)

/* hot reload */
if (false) { var api; }
component.options.__file = "src/components/icons/AssistantIcon.vue"
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (component.exports);

/***/ }),

/***/ "./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue":
/*!********************************************************************!*\
  !*** ./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue ***!
  \********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _SpeechToTextCustomPickerElement_vue_vue_type_template_id_59332248_scoped_true__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./SpeechToTextCustomPickerElement.vue?vue&type=template&id=59332248&scoped=true */ "./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=template&id=59332248&scoped=true");
/* harmony import */ var _SpeechToTextCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./SpeechToTextCustomPickerElement.vue?vue&type=script&lang=js */ "./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=script&lang=js");
/* harmony import */ var _SpeechToTextCustomPickerElement_vue_vue_type_style_index_0_id_59332248_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss */ "./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss");
/* harmony import */ var _node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! !../../../node_modules/vue-loader/lib/runtime/componentNormalizer.js */ "./node_modules/vue-loader/lib/runtime/componentNormalizer.js");



;


/* normalize component */

var component = (0,_node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _SpeechToTextCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"],
  _SpeechToTextCustomPickerElement_vue_vue_type_template_id_59332248_scoped_true__WEBPACK_IMPORTED_MODULE_0__.render,
  _SpeechToTextCustomPickerElement_vue_vue_type_template_id_59332248_scoped_true__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns,
  false,
  null,
  "59332248",
  null
  
)

/* hot reload */
if (false) { var api; }
component.options.__file = "src/views/SpeechToText/SpeechToTextCustomPickerElement.vue"
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (component.exports);

/***/ }),

/***/ "./src/components/icons/AssistantIcon.vue?vue&type=script&lang=js":
/*!************************************************************************!*\
  !*** ./src/components/icons/AssistantIcon.vue?vue&type=script&lang=js ***!
  \************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_AssistantIcon_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./AssistantIcon.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/icons/AssistantIcon.vue?vue&type=script&lang=js");
 /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_AssistantIcon_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"]); 

/***/ }),

/***/ "./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=script&lang=js":
/*!********************************************************************************************!*\
  !*** ./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=script&lang=js ***!
  \********************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./SpeechToTextCustomPickerElement.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=script&lang=js");
 /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"]); 

/***/ }),

/***/ "./src/components/icons/AssistantIcon.vue?vue&type=template&id=14653342":
/*!******************************************************************************!*\
  !*** ./src/components/icons/AssistantIcon.vue?vue&type=template&id=14653342 ***!
  \******************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   render: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_AssistantIcon_vue_vue_type_template_id_14653342__WEBPACK_IMPORTED_MODULE_0__.render),
/* harmony export */   staticRenderFns: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_AssistantIcon_vue_vue_type_template_id_14653342__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_AssistantIcon_vue_vue_type_template_id_14653342__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./AssistantIcon.vue?vue&type=template&id=14653342 */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/icons/AssistantIcon.vue?vue&type=template&id=14653342");


/***/ }),

/***/ "./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=template&id=59332248&scoped=true":
/*!**************************************************************************************************************!*\
  !*** ./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=template&id=59332248&scoped=true ***!
  \**************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   render: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_template_id_59332248_scoped_true__WEBPACK_IMPORTED_MODULE_0__.render),
/* harmony export */   staticRenderFns: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_template_id_59332248_scoped_true__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_template_id_59332248_scoped_true__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./SpeechToTextCustomPickerElement.vue?vue&type=template&id=59332248&scoped=true */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=template&id=59332248&scoped=true");


/***/ }),

/***/ "./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss":
/*!*****************************************************************************************************************************!*\
  !*** ./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss ***!
  \*****************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_style_loader_dist_cjs_js_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_SpeechToTextCustomPickerElement_vue_vue_type_style_index_0_id_59332248_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/style-loader/dist/cjs.js!../../../node_modules/css-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/sass-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss */ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/SpeechToText/SpeechToTextCustomPickerElement.vue?vue&type=style&index=0&id=59332248&scoped=true&lang=scss");


/***/ })

}]);
//# sourceMappingURL=assistant-speech-to-text-picker-lazy.js.map?v=a6c6110876f808f1b248