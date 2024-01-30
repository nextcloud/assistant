"use strict";
(self["webpackChunkassistant"] = self["webpackChunkassistant"] || []).push([["src_components_Text2Image_Text2ImageDisplay_vue"],{

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=script&lang=js":
/*!*************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=script&lang=js ***!
  \*************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _nextcloud_vue_dist_Components_NcLoadingIcon_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcLoadingIcon.js */ "./node_modules/@nextcloud/vue/dist/Components/NcLoadingIcon.mjs");
/* harmony import */ var vue_material_design_icons_Cog_vue__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! vue-material-design-icons/Cog.vue */ "./node_modules/vue-material-design-icons/Cog.vue");
/* harmony import */ var vue_material_design_icons_InformationOutline_vue__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! vue-material-design-icons/InformationOutline.vue */ "./node_modules/vue-material-design-icons/InformationOutline.vue");
/* harmony import */ var _nextcloud_axios__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @nextcloud/axios */ "./node_modules/@nextcloud/axios/dist/index.es.mjs");
/* harmony import */ var _icons_AssistantIcon_vue__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../icons/AssistantIcon.vue */ "./src/components/icons/AssistantIcon.vue");
/* harmony import */ var _nextcloud_router__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @nextcloud/router */ "./node_modules/@nextcloud/router/dist/index.js");
/* harmony import */ var humanize_duration__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! humanize-duration */ "./node_modules/humanize-duration/humanize-duration.js");
/* harmony import */ var humanize_duration__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(humanize_duration__WEBPACK_IMPORTED_MODULE_6__);







/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  name: 'Text2ImageDisplay',
  components: {
    NcLoadingIcon: _nextcloud_vue_dist_Components_NcLoadingIcon_js__WEBPACK_IMPORTED_MODULE_0__["default"],
    InformationOutlineIcon: vue_material_design_icons_InformationOutline_vue__WEBPACK_IMPORTED_MODULE_2__["default"],
    AssistantIcon: _icons_AssistantIcon_vue__WEBPACK_IMPORTED_MODULE_4__["default"],
    Cog: vue_material_design_icons_Cog_vue__WEBPACK_IMPORTED_MODULE_1__["default"]
  },
  props: {
    src: {
      type: String,
      required: true
    },
    forceEditMode: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      prompt: '',
      loadingImages: true,
      imgLoadedList: [],
      timeUntilCompletion: null,
      failed: false,
      imageUrls: [],
      isOwner: false,
      errorMsg: t('assistant', 'Image generation failed'),
      closed: false,
      fileVisStatusArray: [],
      hoveredIndex: -1,
      hovered: false,
      editModeEnabled: false,
      waitingInBg: false
    };
  },
  computed: {
    loading() {
      // Will turn to false once all images have loaded or if something fails
      return this.loadingImages && !this.failed && this.imageUrls.length > 0;
    },
    hasVisibleImages() {
      if (this.isOwner) {
        return this.fileVisStatusArray.some(status => status.visible);
      } else {
        return this.imageUrls.length > 0;
      }
    }
  },
  mounted() {
    this.getImageGenInfo();
    this.editModeEnabled = this.forceEditMode;
  },
  unmounted() {
    this.closed = true;
  },
  methods: {
    onImageLoad(index) {
      this.imgLoadedList[index] = true;
      if (this.imgLoadedList.every(loaded => loaded)) {
        this.loadingImages = false;
      }
    },
    getImages(imageGenId, fileIds) {
      this.loadingImages = true;
      this.imageUrls = [];
      this.imgLoadedList = [];
      this.fileVisStatusArray = fileIds;

      // Loop through all the fileIds and get the images:
      fileIds.forEach(fileId => {
        this.imageUrls.push((0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_5__.generateUrl)('/apps/assistant/i/' + imageGenId + '/' + fileId.id));
        this.imgLoadedList.push = false;
      });
    },
    getImageGenInfo() {
      let success = false;
      _nextcloud_axios__WEBPACK_IMPORTED_MODULE_3__["default"].get(this.src).then(response => {
        if (response.status === 200) {
          if (response.data?.files !== undefined) {
            this.waitingInBg = false;
            if (response.data.files.length === 0) {
              this.errorMsg = t('assistant', 'This generation has no visible images');
              this.failed = true;
              this.imgLoadedList = [];
            } else {
              this.prompt = response.data.prompt;
              this.isOwner = response.data.is_owner;
              success = true;
              this.getImages(response.data.image_gen_id, response.data.files);
              this.onGenerationReady();
            }
          } else if (response.data?.processing !== undefined) {
            this.waitingInBg = true;
            this.$emit('processing');
            this.updateTimeUntilCompletion(response.data.processing);
          } else {
            this.errorMsg = t('assistant', 'Unexpected server response');
            this.failed = true;
            this.imgLoadedList = [];
          }
        } else {
          console.error('Unexpected response status: ' + response.status);
          this.errorMsg = t('assistant', 'Unexpected server response');
          this.failed = true;
          this.imgLoadedList = [];
        }
        // If we didn't succeed in loading the image gen info yet, try again
        if (!success && !this.failed && !this.closed) {
          setTimeout(this.getImageGenInfo, 3000);
        }
      }).catch(error => {
        this.onError(error);
      });
    },
    updateTimeUntilCompletion(completionTimeStamp) {
      // AFAIK there's no trivial way to do this with a computed property unless timers/intervals
      // are used, so we might as well do this with a method:
      const timeDifference = new Date(completionTimeStamp * 1000) - new Date();
      // If the time difference is less than 5 minutes, don't show the time left
      // (as we don't know when the scheduled job will start exactly)
      if (timeDifference < 5 * 60000) {
        this.timeUntilCompletion = null;
        return;
      }
      this.timeUntilCompletion = humanize_duration__WEBPACK_IMPORTED_MODULE_6___default()(timeDifference, {
        units: ['h', 'm'],
        language: OC.getLanguage(),
        fallbacks: ['en'],
        round: true
      });

      // Schedule next update:
      if (!this.closed) {
        setTimeout(() => {
          this.updateTimeUntilCompletion(completionTimeStamp);
        }, 30000);
      }
    },
    onError(error) {
      // If error response status is 429 let the user know that they are being rate limited
      if (error.response?.status === 429) {
        this.errorMsg = t('assistant', 'Rate limit reached. Please try again later.');
        this.failed = true;
        this.imgLoadedList = [];
      } else if (error.response?.data !== undefined) {
        this.errorMsg = error.response.data.error;
        this.failed = true;
        this.imgLoadedList = [];
      } else {
        console.error('Could not handle response error: ' + error);
        this.errorMsg = t('assistant', 'Unknown server query error');
        this.failed = true;
        this.imgLoadedList = [];
      }
      this.$emit('failed');
    },
    onGenerationReady() {
      this.$emit('ready');
    },
    onCheckboxChange() {
      const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_5__.generateUrl)('/apps/assistant/i/visibility/' + this.src.split('/').pop());
      _nextcloud_axios__WEBPACK_IMPORTED_MODULE_3__["default"].post(url, {
        fileVisStatusArray: this.fileVisStatusArray
      }).then(response => {
        if (response.status === 200) {
          // console.log('Successfully updated visible images')
        } else {
          console.error('Unexpected response status: ' + response.status);
        }
      }).catch(error => {
        console.error('Could not update visible images: ' + error);
      });
    },
    toggleCheckbox(index) {
      this.fileVisStatusArray[index].visible = !this.fileVisStatusArray[index].visible;
      this.onCheckboxChange();
    },
    toggleEditMode() {
      this.editModeEnabled = !this.editModeEnabled;
    }
  }
});

/***/ }),

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

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=template&id=58ab6ca3&scoped=true":
/*!************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=template&id=58ab6ca3&scoped=true ***!
  \************************************************************************************************************************************************************************************************************************************************************************************/
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
    staticClass: "display-container"
  }, [_c('div', {
    staticClass: "title"
  }, [_c('div', {
    staticClass: "icon-and-text"
  }, [_vm.loading ? _c('div', [_c('NcLoadingIcon', {
    staticClass: "icon",
    attrs: {
      "size": 20
    }
  })], 1) : _c('div', [_c('AssistantIcon', {
    staticClass: "icon",
    attrs: {
      "size": 20
    }
  })], 1), _vm._v(" "), _c('strong', {
    staticClass: "app-name"
  }, [_vm._v("\n\t\t\t\t" + _vm._s(_vm.t('assistant', 'Image generation') + ':') + "\n\t\t\t")]), _vm._v("\n\t\t\t" + _vm._s(_vm.prompt) + "\n\t\t")]), _vm._v(" "), _vm.isOwner ? _c('Cog', {
    staticClass: "edit-icon",
    class: {
      'active': _vm.editModeEnabled
    },
    attrs: {
      "size": 30,
      "title": _vm.t('assistant', 'Edit visible images')
    },
    on: {
      "click": _vm.toggleEditMode
    }
  }) : _vm._e()], 1), _vm._v(" "), _vm.editModeEnabled && _vm.isOwner ? _c('div', [_vm.imageUrls.length > 0 && !_vm.failed ? _c('div', {
    staticClass: "image-list"
  }, _vm._l(_vm.imageUrls, function (imageUrl, index) {
    return _c('div', {
      key: index,
      staticClass: "image-container",
      on: {
        "mouseover": function ($event) {
          _vm.hoveredIndex = index;
        },
        "mouseout": function ($event) {
          _vm.hoveredIndex = -1;
        }
      }
    }, [_c('div', {
      staticClass: "checkbox-container",
      class: {
        'hovering': _vm.hoveredIndex === index
      }
    }, [_c('input', {
      directives: [{
        name: "model",
        rawName: "v-model",
        value: _vm.fileVisStatusArray[index].visible,
        expression: "fileVisStatusArray[index].visible"
      }],
      attrs: {
        "v-show": !_vm.imgLoadedList[index],
        "type": "checkbox",
        "title": _vm.t('assistant', 'Click to toggle generation visibility')
      },
      domProps: {
        "checked": Array.isArray(_vm.fileVisStatusArray[index].visible) ? _vm._i(_vm.fileVisStatusArray[index].visible, null) > -1 : _vm.fileVisStatusArray[index].visible
      },
      on: {
        "change": [function ($event) {
          var $$a = _vm.fileVisStatusArray[index].visible,
            $$el = $event.target,
            $$c = $$el.checked ? true : false;
          if (Array.isArray($$a)) {
            var $$v = null,
              $$i = _vm._i($$a, $$v);
            if ($$el.checked) {
              $$i < 0 && _vm.$set(_vm.fileVisStatusArray[index], "visible", $$a.concat([$$v]));
            } else {
              $$i > -1 && _vm.$set(_vm.fileVisStatusArray[index], "visible", $$a.slice(0, $$i).concat($$a.slice($$i + 1)));
            }
          } else {
            _vm.$set(_vm.fileVisStatusArray[index], "visible", $$c);
          }
        }, function ($event) {
          return _vm.onCheckboxChange();
        }]
      }
    })]), _vm._v(" "), _c('div', {
      staticClass: "image-wrapper",
      class: {
        'deselected': !_vm.fileVisStatusArray[index].visible
      }
    }, [_c('img', {
      staticClass: "image-editable",
      attrs: {
        "src": imageUrl,
        "title": _vm.t('assistant', 'Click to toggle generation visibility')
      },
      on: {
        "load": function ($event) {
          return _vm.onImageLoad(index);
        },
        "click": function ($event) {
          return _vm.toggleCheckbox(index);
        },
        "error": _vm.onError
      }
    })])]);
  }), 0) : _vm._e()]) : _c('div', [_vm.imageUrls.length > 0 && !_vm.failed ? _c('div', {
    staticClass: "image-list"
  }, [_vm._l(_vm.imageUrls, function (imageUrl, index) {
    return _c('div', {
      key: index,
      staticClass: "image-container"
    }, [_c('div', {
      directives: [{
        name: "show",
        rawName: "v-show",
        value: !_vm.isOwner || _vm.fileVisStatusArray[index].visible,
        expression: "!isOwner || fileVisStatusArray[index].visible"
      }],
      staticClass: "image-wrapper"
    }, [_c('img', {
      staticClass: "image-non-editable",
      attrs: {
        "src": imageUrl,
        "title": _vm.t('assistant', 'Generated image')
      },
      on: {
        "load": function ($event) {
          return _vm.onImageLoad(index);
        },
        "error": _vm.onError
      }
    })])]);
  }), _vm._v(" "), !_vm.hasVisibleImages ? _c('div', {
    staticClass: "error_msg"
  }, [_vm._v("\n\t\t\t\t" + _vm._s(_vm.t('assistant', 'This generation has no visible images')) + "\n\t\t\t")]) : _vm._e()], 2) : _vm._e()]), _vm._v(" "), !_vm.failed && _vm.waitingInBg ? _c('div', {
    staticClass: "processing-notification-container"
  }, [_vm.timeUntilCompletion !== null ? _c('div', {
    staticClass: "processing-notification"
  }, [_c('InformationOutlineIcon', {
    staticClass: "icon",
    attrs: {
      "size": 20
    }
  }), _vm._v("\n\t\t\t" + _vm._s(_vm.t('assistant', 'Estimated generation time left: ') + _vm.timeUntilCompletion + '. ') + "\n\t\t\t" + _vm._s(_vm.t('assistant', 'The generated image is shown once ready.')) + "\n\t\t")], 1) : _c('div', {
    staticClass: "processing-notification"
  }, [_c('InformationOutlineIcon', {
    staticClass: "icon",
    attrs: {
      "size": 20
    }
  }), _vm._v("\n\t\t\t" + _vm._s(_vm.t('assistant', 'This image generation was scheduled to run in the background.')) + "\n\t\t\t" + _vm._s(_vm.t('assistant', 'The generated image is shown once ready.')) + "\n\t\t")], 1)]) : _vm._e(), _vm._v(" "), _vm.failed ? _c('span', {
    staticClass: "error_msg"
  }, [_vm._v("\n\t\t" + _vm._s(_vm.t('assistant', _vm.errorMsg)) + "\n\t")]) : _vm._e()]);
};
var staticRenderFns = [];
render._withStripped = true;


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

/***/ "./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss":
/*!*******************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss ***!
  \*******************************************************************************************************************************************************************************************************************************************************************************************************************/
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
___CSS_LOADER_EXPORT___.push([module.id, `.display-container[data-v-58ab6ca3] {
  display: flex;
  flex-direction: column;
  width: 100%;
  align-items: center;
  justify-content: center;
  /*.checkbox {
  	cursor: pointer;
  }*/
}
.display-container .edit-icon[data-v-58ab6ca3] {
  position: static;
  opacity: 0.2;
  transition: opacity 0.2s ease-in-out;
  cursor: pointer;
}
.display-container .edit-icon.active[data-v-58ab6ca3] {
  opacity: 1;
  cursor: pointer;
}
.display-container .image-list[data-v-58ab6ca3] {
  display: flex;
  flex-direction: column;
  flex-wrap: wrap;
  justify-content: center;
}
.display-container .image-container[data-v-58ab6ca3] {
  display: flex;
  flex-direction: column;
  position: relative;
  justify-content: center;
  max-width: 90%;
}
.display-container .checkbox-container[data-v-58ab6ca3] {
  position: absolute;
  top: 5%;
  left: 95%;
  z-index: 1;
  opacity: 0.2;
  transition: opacity 0.2s ease-in-out;
}
.display-container .checkbox-container > input[data-v-58ab6ca3] {
  cursor: pointer;
}
.display-container .checkbox-container.hovering[data-v-58ab6ca3] {
  opacity: 1;
}
.display-container .image-wrapper[data-v-58ab6ca3] {
  display: flex;
  flex-direction: column;
  position: relative;
  max-width: 100%;
  height: 100%;
  margin-top: 12px;
  filter: none;
  transition: filter 0.2s ease-in-out;
}
.display-container .image-wrapper.deselected[data-v-58ab6ca3] {
  filter: grayscale(100%) brightness(50%);
}
.display-container .image-editable[data-v-58ab6ca3] {
  display: flex;
  width: 100%;
  height: 100%;
  min-width: 400px;
  object-fit: contain;
  cursor: pointer;
  border-radius: var(--border-radius);
}
.display-container .image-non-editable[data-v-58ab6ca3] {
  display: flex;
  width: 100%;
  height: 100%;
  min-width: 400px;
  object-fit: contain;
}
.display-container .title[data-v-58ab6ca3] {
  max-width: 600px;
  width: 100%;
  display: flex;
  flex-direction: row;
  margin-top: 0;
}
.display-container .title .icon-and-text[data-v-58ab6ca3] {
  width: 100%;
  display: flex;
  flex-direction: row;
  align-items: top;
  justify-content: start;
  margin-right: 8px;
}
.display-container .title .icon-and-text .app-name[data-v-58ab6ca3] {
  margin-right: 8px;
  white-space: nowrap;
}
.display-container .title .icon[data-v-58ab6ca3] {
  display: inline;
  position: relative;
  margin-right: 8px;
}
.display-container .processing-notification-container[data-v-58ab6ca3] {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-top: 24px;
}
.display-container .processing-notification-container .processing-notification[data-v-58ab6ca3] {
  display: flex;
  flex-direction: row;
  margin-top: 24px;
  width: 90%;
  align-items: center;
  justify-content: center;
  border: 3px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: 12px;
  font-size: 0.8rem;
  column-gap: 24px;
}
.display-container .error_msg[data-v-58ab6ca3] {
  color: var(--color-error);
  font-weight: bold;
  margin-bottom: 24px;
  align-self: center;
}`, ""]);
// Exports
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (___CSS_LOADER_EXPORT___);


/***/ }),

/***/ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss":
/*!***********************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss ***!
  \***********************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
/* harmony import */ var _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_style_index_0_id_58ab6ca3_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! !!../../../node_modules/css-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/sass-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss */ "./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss");

      
      
      
      
      
      
      
      
      

var options = {};

options.styleTagTransform = (_node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5___default());
options.setAttributes = (_node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3___default());

      options.insert = _node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2___default().bind(null, "head");
    
options.domAPI = (_node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1___default());
options.insertStyleElement = (_node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4___default());

var update = _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0___default()(_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_style_index_0_id_58ab6ca3_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"], options);




       /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_style_index_0_id_58ab6ca3_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"] && _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_style_index_0_id_58ab6ca3_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals ? _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_style_index_0_id_58ab6ca3_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals : undefined);


/***/ }),

/***/ "./src/components/Text2Image/Text2ImageDisplay.vue":
/*!*********************************************************!*\
  !*** ./src/components/Text2Image/Text2ImageDisplay.vue ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _Text2ImageDisplay_vue_vue_type_template_id_58ab6ca3_scoped_true__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Text2ImageDisplay.vue?vue&type=template&id=58ab6ca3&scoped=true */ "./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=template&id=58ab6ca3&scoped=true");
/* harmony import */ var _Text2ImageDisplay_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Text2ImageDisplay.vue?vue&type=script&lang=js */ "./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=script&lang=js");
/* harmony import */ var _Text2ImageDisplay_vue_vue_type_style_index_0_id_58ab6ca3_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss */ "./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss");
/* harmony import */ var _node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! !../../../node_modules/vue-loader/lib/runtime/componentNormalizer.js */ "./node_modules/vue-loader/lib/runtime/componentNormalizer.js");



;


/* normalize component */

var component = (0,_node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _Text2ImageDisplay_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"],
  _Text2ImageDisplay_vue_vue_type_template_id_58ab6ca3_scoped_true__WEBPACK_IMPORTED_MODULE_0__.render,
  _Text2ImageDisplay_vue_vue_type_template_id_58ab6ca3_scoped_true__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns,
  false,
  null,
  "58ab6ca3",
  null
  
)

/* hot reload */
if (false) { var api; }
component.options.__file = "src/components/Text2Image/Text2ImageDisplay.vue"
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (component.exports);

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

/***/ "./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=script&lang=js":
/*!*********************************************************************************!*\
  !*** ./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=script&lang=js ***!
  \*********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./Text2ImageDisplay.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=script&lang=js");
 /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"]); 

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

/***/ "./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=template&id=58ab6ca3&scoped=true":
/*!***************************************************************************************************!*\
  !*** ./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=template&id=58ab6ca3&scoped=true ***!
  \***************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   render: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_template_id_58ab6ca3_scoped_true__WEBPACK_IMPORTED_MODULE_0__.render),
/* harmony export */   staticRenderFns: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_template_id_58ab6ca3_scoped_true__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_template_id_58ab6ca3_scoped_true__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./Text2ImageDisplay.vue?vue&type=template&id=58ab6ca3&scoped=true */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=template&id=58ab6ca3&scoped=true");


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

/***/ "./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss":
/*!******************************************************************************************************************!*\
  !*** ./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss ***!
  \******************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_style_loader_dist_cjs_js_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageDisplay_vue_vue_type_style_index_0_id_58ab6ca3_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/style-loader/dist/cjs.js!../../../node_modules/css-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/sass-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss */ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/Text2Image/Text2ImageDisplay.vue?vue&type=style&index=0&id=58ab6ca3&scoped=true&lang=scss");


/***/ })

}]);
//# sourceMappingURL=assistant-src_components_Text2Image_Text2ImageDisplay_vue.js.map?v=bd1dd00f9ea29b8a8e98