"use strict";
(self["webpackChunkassistant"] = self["webpackChunkassistant"] || []).push([["reference-picker-lazy"],{

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=script&lang=js":
/*!***********************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=script&lang=js ***!
  \***********************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var vue_material_design_icons_ContentCopy_vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vue-material-design-icons/ContentCopy.vue */ "./node_modules/vue-material-design-icons/ContentCopy.vue");
/* harmony import */ var vue_material_design_icons_ClipboardCheckOutline_vue__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! vue-material-design-icons/ClipboardCheckOutline.vue */ "./node_modules/vue-material-design-icons/ClipboardCheckOutline.vue");
/* harmony import */ var _nextcloud_vue_dist_Components_NcButton_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcButton.js */ "./node_modules/@nextcloud/vue/dist/Components/NcButton.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcLoadingIcon_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcLoadingIcon.js */ "./node_modules/@nextcloud/vue/dist/Components/NcLoadingIcon.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcRichContenteditable_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcRichContenteditable.js */ "./node_modules/@nextcloud/vue/dist/Components/NcRichContenteditable.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcCheckboxRadioSwitch_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js */ "./node_modules/@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.mjs");
/* harmony import */ var _nextcloud_axios__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @nextcloud/axios */ "./node_modules/@nextcloud/axios/dist/index.es.mjs");
/* harmony import */ var _nextcloud_router__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @nextcloud/router */ "./node_modules/@nextcloud/router/dist/index.js");
/* harmony import */ var _nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @nextcloud/dialogs */ "./node_modules/@nextcloud/dialogs/dist/index.mjs");
/* harmony import */ var humanize_duration__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! humanize-duration */ "./node_modules/humanize-duration/humanize-duration.js");
/* harmony import */ var humanize_duration__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(humanize_duration__WEBPACK_IMPORTED_MODULE_9__);
/* harmony import */ var vue_clipboard2__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! vue-clipboard2 */ "./node_modules/vue-clipboard2/vue-clipboard.js");
/* harmony import */ var vue_clipboard2__WEBPACK_IMPORTED_MODULE_10___default = /*#__PURE__*/__webpack_require__.n(vue_clipboard2__WEBPACK_IMPORTED_MODULE_10__);
/* harmony import */ var vue__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! vue */ "./node_modules/vue/dist/vue.runtime.esm.js");












vue__WEBPACK_IMPORTED_MODULE_11__["default"].use((vue_clipboard2__WEBPACK_IMPORTED_MODULE_10___default()));
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  name: 'FreePromptGenerationDisplay',
  components: {
    NcLoadingIcon: _nextcloud_vue_dist_Components_NcLoadingIcon_js__WEBPACK_IMPORTED_MODULE_3__["default"],
    NcRichContenteditable: _nextcloud_vue_dist_Components_NcRichContenteditable_js__WEBPACK_IMPORTED_MODULE_4__["default"],
    NcButton: _nextcloud_vue_dist_Components_NcButton_js__WEBPACK_IMPORTED_MODULE_2__["default"],
    NcCheckboxRadioSwitch: _nextcloud_vue_dist_Components_NcCheckboxRadioSwitch_js__WEBPACK_IMPORTED_MODULE_5__["default"],
    ContentCopyIcon: vue_material_design_icons_ContentCopy_vue__WEBPACK_IMPORTED_MODULE_0__["default"],
    ClipboardCheckOutlineIcon: vue_material_design_icons_ClipboardCheckOutline_vue__WEBPACK_IMPORTED_MODULE_1__["default"]
  },
  props: {
    genId: {
      type: String,
      required: true
    }
  },
  data() {
    return {
      originalResponse: null,
      originalResult: null,
      result: null,
      copied: false,
      loading: true,
      processing: false,
      bgProcessingScheduled: false,
      includePrompt: false,
      prompt: '',
      timeUntilCompletion: null,
      rawCompletionTimestamp: null,
      closed: false
    };
  },
  mounted() {
    this.getResults();
  },
  beforeDestroy() {
    this.closed = true;
  },
  methods: {
    getResults() {
      // Check if this element has already been closed/destroyed
      if (this.closed) {
        return;
      }
      const config = {
        params: {
          genId: this.genId
        }
      };
      const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_7__.generateUrl)('/apps/assistant/f/get_outputs');
      return _nextcloud_axios__WEBPACK_IMPORTED_MODULE_6__["default"].get(url, config).then(response => {
        const data = response.data;
        if (data.length && data.length > 0) {
          if (!data.length || data[0]?.status === undefined) {
            this.loading = false;
            (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_8__.showError)(t('assistant', 'Unexpected server response'));
            this.$emit('error');
            return;
          }
          if (this.rawCompletionTimestamp === null) {
            // Get the largest timestamp of all generations
            this.rawCompletionTimestamp = Math.max(...data.map(c => c.completion_time));
            this.updateTimeUntilCompletion(this.rawCompletionTimestamp);
          }

          // Check if processing of all completions is finished
          // 1 = scheduled, 2 = running
          const numGensProcessing = data.filter(c => c.status === 1 || c.status === 2).length;
          if (numGensProcessing === 0) {
            // 4 = failed, 0 = unknown
            const nFailures = data.filter(c => c.status === 4 || c.status === 0).length;
            if (nFailures > 0) {
              if (nFailures === data.length) {
                (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_8__.showError)(t('assistant', 'The processing of generations failed.'));
                this.loading = false;
                this.result = null;
                this.$emit('error');
                return;
              }
              (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_8__.showError)(t('assistant', 'The processing of some generations failed.'));
            }
            this.loading = false;
            this.$emit('loaded');
            this.processCompletion(data);
          } else {
            if (numGensProcessing === data.length) {
              this.bgProcessingScheduled = true;
            }
            this.processing = true;
            this.$emit('scheduled');
            this.processCompletion(data);
            setTimeout(() => {
              this.getResults();
            }, 1000);
          }
        } else {
          this.loading = false;
          this.$emit('error');
          this.error = response.data.error;
        }
      }).catch(error => {
        this.loading = false;
        this.$emit('error');
        console.error('Text  completions request error', error);
        (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_8__.showError)(t('assistant', 'Text generation error') + ': ' + (error.response?.data?.body?.error?.message || error.response?.data?.body?.error?.code || error.response?.data?.error || t('assistant', 'Unknown text generation API error')));
      });
    },
    onReset() {
      this.result = this.originalResult;
    },
    delayedReset() {
      // This is a hack to sure the text box is updated
      // when we reset the text since removing newlines or spaces
      // from the end of the text does not trigger an update.

      // Delete any trailing newlines
      this.result = this.result.replace(/\n+$/, '');
      this.result += '.';

      // Let the ui refresh before resetting the text
      setTimeout(() => {
        this.onReset();
      }, 0);
    },
    async onCopy() {
      try {
        const container = this.$refs.output.$el;
        await this.$copyText(this.result.trim(), container);
        this.copied = true;
        setTimeout(() => {
          this.copied = false;
        }, 5000);
      } catch (error) {
        console.error(error);
        (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_8__.showError)(t('assistant', 'Result could not be copied to clipboard'));
      }
    },
    processCompletion(response) {
      this.originalResponse = response;
      const totalGens = response.length;
      // Drop completions that are not yet finished
      this.prompt = response[0].prompt;
      response = response.filter(c => c.status === 3);
      const answers = response.filter(c => !!c.text).map(c => c.text.replace(/^\s+|\s+$/g, ''));
      if (answers.length > 0) {
        if (totalGens === 1) {
          this.originalResult = this.result = this.includePrompt ? t('assistant', 'Prompt') + '\n' + this.prompt + '\n\n' + t('assistant', 'Result') + '\n' + answers[0] : answers[0];
        } else {
          const multiAnswers = answers.map((a, i) => {
            return t('assistant', 'Result {index}', {
              index: i + 1
            }) + '\n' + a;
          });
          this.originalResult = this.result = this.includePrompt ? t('assistant', 'Prompt') + '\n' + this.prompt + '\n\n' + multiAnswers.join('\n\n') : multiAnswers.join('\n\n');
        }
      }
      this.$emit('update:result', this.result);
    },
    onIncludePromptToggle() {
      this.processCompletion(this.originalResponse);
    },
    onTextEdit() {
      this.$emit('update:result', this.result);
    },
    updateTimeUntilCompletion(completionTimeStamp) {
      const timeDifference = new Date(completionTimeStamp * 1000) - new Date();
      if (timeDifference < 60000) {
        this.timeUntilCompletion = null;
        return;
      }
      this.timeUntilCompletion = humanize_duration__WEBPACK_IMPORTED_MODULE_9___default()(timeDifference, {
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
    }
  }
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=script&lang=js":
/*!********************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=script&lang=js ***!
  \********************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var vue_material_design_icons_Eye_vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vue-material-design-icons/Eye.vue */ "./node_modules/vue-material-design-icons/Eye.vue");
/* harmony import */ var vue_material_design_icons_Refresh_vue__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! vue-material-design-icons/Refresh.vue */ "./node_modules/vue-material-design-icons/Refresh.vue");
/* harmony import */ var vue_material_design_icons_ArrowRight_vue__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! vue-material-design-icons/ArrowRight.vue */ "./node_modules/vue-material-design-icons/ArrowRight.vue");
/* harmony import */ var _nextcloud_vue_dist_Components_NcButton_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcButton.js */ "./node_modules/@nextcloud/vue/dist/Components/NcButton.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcLoadingIcon_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcLoadingIcon.js */ "./node_modules/@nextcloud/vue/dist/Components/NcLoadingIcon.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcTextField_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcTextField.js */ "./node_modules/@nextcloud/vue/dist/Components/NcTextField.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcUserBubble_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcUserBubble.js */ "./node_modules/@nextcloud/vue/dist/Components/NcUserBubble.mjs");
/* harmony import */ var _components_FreePrompt_FreePromptGenerationDisplay_vue__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../components/FreePrompt/FreePromptGenerationDisplay.vue */ "./src/components/FreePrompt/FreePromptGenerationDisplay.vue");
/* harmony import */ var _nextcloud_axios__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @nextcloud/axios */ "./node_modules/@nextcloud/axios/dist/index.es.mjs");
/* harmony import */ var _nextcloud_router__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! @nextcloud/router */ "./node_modules/@nextcloud/router/dist/index.js");
/* harmony import */ var _nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! @nextcloud/dialogs */ "./node_modules/@nextcloud/dialogs/dist/index.mjs");











/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  name: 'FreePromptCustomPickerElement',
  components: {
    NcButton: _nextcloud_vue_dist_Components_NcButton_js__WEBPACK_IMPORTED_MODULE_3__["default"],
    NcLoadingIcon: _nextcloud_vue_dist_Components_NcLoadingIcon_js__WEBPACK_IMPORTED_MODULE_4__["default"],
    NcTextField: _nextcloud_vue_dist_Components_NcTextField_js__WEBPACK_IMPORTED_MODULE_5__["default"],
    ArrowRightIcon: vue_material_design_icons_ArrowRight_vue__WEBPACK_IMPORTED_MODULE_2__["default"],
    NcUserBubble: _nextcloud_vue_dist_Components_NcUserBubble_js__WEBPACK_IMPORTED_MODULE_6__["default"],
    RefreshIcon: vue_material_design_icons_Refresh_vue__WEBPACK_IMPORTED_MODULE_1__["default"],
    EyeIcon: vue_material_design_icons_Eye_vue__WEBPACK_IMPORTED_MODULE_0__["default"],
    FreePromptGenerationDisplay: _components_FreePrompt_FreePromptGenerationDisplay_vue__WEBPACK_IMPORTED_MODULE_7__["default"]
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
      query: '',
      genId: null,
      loading: false,
      error: false,
      models: [],
      completionNumber: 1,
      prompts: null,
      notify: false,
      submitted: false,
      result: null,
      scheduled: false
    };
  },
  computed: {
    previewButtonLabel() {
      return this.result !== null ? t('assistant', 'Regenerate') : t('assistant', 'Preview');
    },
    emptyResult() {
      return this.result.trim() === '';
    }
  },
  watch: {},
  mounted() {
    this.focusOnInput();
    this.getPromptHistory();
  },
  beforeDestroy() {
    if (!this.notify && !this.submitted) {
      this.cancelGeneration();
    }
  },
  methods: {
    focusOnInput() {
      setTimeout(() => {
        this.$refs['assistant-search-input'].$el.getElementsByTagName('input')[0]?.focus();
      }, 300);
    },
    getPromptHistory() {
      const params = {
        params: {}
      };
      const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_9__.generateUrl)('/apps/assistant/f/prompt_history');
      return _nextcloud_axios__WEBPACK_IMPORTED_MODULE_8__["default"].get(url, params).then(response => {
        this.prompts = response.data;
      }).catch(error => {
        console.error(error);
      });
    },
    submit() {
      this.submitted = true;
      this.$emit('submit', this.result.trim());
    },
    onError() {
      this.error = true;
      this.loading = false;
      this.genId = null;
    },
    onLoaded() {
      this.loading = false;
    },
    insertPrompt(prompt) {
      if (this.prompts.find(p => p.value === prompt) === undefined) {
        this.prompts.unshift({
          id: 0,
          value: prompt
        });
      }
    },
    generate() {
      if (this.query === '') {
        return;
      }
      this.loading = true;
      this.scheduled = false;
      this.error = false;
      this.result = null;
      this.genId = null;
      const params = {
        prompt: this.query,
        nResults: this.completionNumber
      };
      const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_9__.generateUrl)('/apps/assistant/f/process_prompt');
      return _nextcloud_axios__WEBPACK_IMPORTED_MODULE_8__["default"].post(url, params).then(response => {
        const data = response.data;
        if (data.length && data.length > 0) {
          this.genId = data;
          this.insertPrompt(this.query);
        } else {
          this.error = response.data.error;
        }
      }).catch(error => {
        this.loading = false;
        console.error('Text  completions request error', error);
        (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_10__.showError)(t('assistant', 'Text generation error') + ': ' + (error.response?.data?.body?.error?.message || error.response?.data?.body?.error?.code || error.response?.data?.error || t('assistant', 'Unknown text generation API error')));
      });
    },
    cancelGeneration() {
      if (this.genId === null) {
        return;
      }
      const params = {
        genId: this.genId
      };
      const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_9__.generateUrl)('/apps/assistant/f/cancel_generation');
      return _nextcloud_axios__WEBPACK_IMPORTED_MODULE_8__["default"].post(url, params).then(response => {
        this.genId = null;
        this.result = null;
        this.loading = false;
      }).catch(error => {
        console.error('Text  completions request error', error);
        (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_10__.showError)(t('assistant', 'Text generation error') + ': ' + (error.response?.data?.body?.error?.message || error.response?.data?.body?.error?.code || error.response?.data?.error || t('assistant', 'Unknown text generation API error')));
      });
    },
    notifyWhenReady() {
      /* For now all tasks will have a notification, since the assistant
       * backend doesn't support omitting a notification, yet.
       */
      this.notify = true;
      this.$emit('submit', '');
      /* if (this.failed || this.genId === null) {
      	return
      }
      	const params = {
      	genId: this.genId,
      	notify: true,
      }
      const url = generateUrl('/apps/assistant/f/set_notify')
      axios.post(url, params)
      	.then(() => {
      		showMessage(t('assistant', 'You will be notified when the text generation is ready.'))
      		this.notify = true
      		this.$emit('submit', '')
      	})
      	.catch((error) => {
      		console.error('Notify when ready request error', error)
      		showError(
      			t('assistant', 'Notify when ready error') + ': '
      			+ (error.response?.data?.body?.error?.message
      				|| error.response?.data?.body?.error?.code
      				|| error.response?.data?.error
      				|| t('assistant', 'Unknown notify when ready error')
      			),
      		)
      	}) */
    }
  }
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=script&lang=js":
/*!********************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=script&lang=js ***!
  \********************************************************************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var vue_material_design_icons_Eye_vue__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! vue-material-design-icons/Eye.vue */ "./node_modules/vue-material-design-icons/Eye.vue");
/* harmony import */ var vue_material_design_icons_Refresh_vue__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! vue-material-design-icons/Refresh.vue */ "./node_modules/vue-material-design-icons/Refresh.vue");
/* harmony import */ var vue_material_design_icons_ArrowRight_vue__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! vue-material-design-icons/ArrowRight.vue */ "./node_modules/vue-material-design-icons/ArrowRight.vue");
/* harmony import */ var _nextcloud_vue_dist_Components_NcButton_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcButton.js */ "./node_modules/@nextcloud/vue/dist/Components/NcButton.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcLoadingIcon_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcLoadingIcon.js */ "./node_modules/@nextcloud/vue/dist/Components/NcLoadingIcon.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcTextField_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcTextField.js */ "./node_modules/@nextcloud/vue/dist/Components/NcTextField.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcCheckboxRadioSwitch_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js */ "./node_modules/@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.mjs");
/* harmony import */ var _nextcloud_vue_dist_Components_NcUserBubble_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @nextcloud/vue/dist/Components/NcUserBubble.js */ "./node_modules/@nextcloud/vue/dist/Components/NcUserBubble.mjs");
/* harmony import */ var vue_material_design_icons_ChevronRight_vue__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! vue-material-design-icons/ChevronRight.vue */ "./node_modules/vue-material-design-icons/ChevronRight.vue");
/* harmony import */ var vue_material_design_icons_ChevronDown_vue__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! vue-material-design-icons/ChevronDown.vue */ "./node_modules/vue-material-design-icons/ChevronDown.vue");
/* harmony import */ var _nextcloud_axios__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! @nextcloud/axios */ "./node_modules/@nextcloud/axios/dist/index.es.mjs");
/* harmony import */ var _nextcloud_router__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! @nextcloud/router */ "./node_modules/@nextcloud/router/dist/index.js");
/* harmony import */ var _nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! @nextcloud/dialogs */ "./node_modules/@nextcloud/dialogs/dist/index.mjs");
/* harmony import */ var _components_Text2Image_Text2ImageDisplay_vue__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ../../components/Text2Image/Text2ImageDisplay.vue */ "./src/components/Text2Image/Text2ImageDisplay.vue");














/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ({
  name: 'Text2ImageCustomPickerElement',
  components: {
    NcButton: _nextcloud_vue_dist_Components_NcButton_js__WEBPACK_IMPORTED_MODULE_3__["default"],
    NcLoadingIcon: _nextcloud_vue_dist_Components_NcLoadingIcon_js__WEBPACK_IMPORTED_MODULE_4__["default"],
    NcTextField: _nextcloud_vue_dist_Components_NcTextField_js__WEBPACK_IMPORTED_MODULE_5__["default"],
    NcCheckboxRadioSwitch: _nextcloud_vue_dist_Components_NcCheckboxRadioSwitch_js__WEBPACK_IMPORTED_MODULE_6__["default"],
    ArrowRightIcon: vue_material_design_icons_ArrowRight_vue__WEBPACK_IMPORTED_MODULE_2__["default"],
    NcUserBubble: _nextcloud_vue_dist_Components_NcUserBubble_js__WEBPACK_IMPORTED_MODULE_7__["default"],
    RefreshIcon: vue_material_design_icons_Refresh_vue__WEBPACK_IMPORTED_MODULE_1__["default"],
    EyeIcon: vue_material_design_icons_Eye_vue__WEBPACK_IMPORTED_MODULE_0__["default"],
    Text2ImageDisplay: _components_Text2Image_Text2ImageDisplay_vue__WEBPACK_IMPORTED_MODULE_13__["default"]
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
      query: '',
      result: null,
      loading: false,
      isGenerating: false,
      models: [],
      inputPlaceholder: t('assistant', 'A description of the image you want to generate'),
      displayPrompt: true,
      notify: false,
      prompts: null,
      submitted: false,
      nResults: 1,
      showAdvanced: false
    };
  },
  computed: {
    previewButtonLabel() {
      return this.result !== null ? t('assistant', 'Regenerate') : t('assistant', 'Preview');
    },
    emptyResult() {
      return this.result === null || this.result.length === 0;
    },
    showAdvancedIcon() {
      return this.showAdvanced ? vue_material_design_icons_ChevronDown_vue__WEBPACK_IMPORTED_MODULE_9__["default"] : vue_material_design_icons_ChevronRight_vue__WEBPACK_IMPORTED_MODULE_8__["default"];
    }
  },
  watch: {
    displayPrompt(newValue) {
      localStorage.setItem('text2image_display_prompt', JSON.stringify(newValue));
    }
  },
  mounted() {
    this.focusOnInput();
    this.getPromptHistory();
    const cachedValue = localStorage.getItem('text2image_display_prompt') === 'true';
    if (cachedValue !== null) {
      this.displayPrompt = cachedValue;
    }
  },
  beforeDestroy() {
    if (!this.submitted && !this.notify) {
      this.cancelGeneration();
    }
  },
  methods: {
    focusOnInput() {
      setTimeout(() => {
        this.$refs['text2image-search-input'].$el.getElementsByTagName('input')[0]?.focus();
      }, 300);
    },
    getPromptHistory() {
      const params = {
        params: {}
      };
      const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_11__.generateUrl)('/apps/assistant/i/prompt_history');
      return _nextcloud_axios__WEBPACK_IMPORTED_MODULE_10__["default"].get(url, params).then(response => {
        this.prompts = response.data;
      }).catch(error => {
        console.error(error);
      });
    },
    submit() {
      this.submitted = true;
      this.$emit('submit', this.result.reference_url);
    },
    insertPrompt(prompt) {
      if (this.prompts.find(p => p.value === prompt) === undefined) {
        this.prompts.unshift({
          id: 0,
          value: prompt
        });
      }
    },
    cancelGeneration() {
      if (this.result === null) {
        return;
      }
      const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_11__.generateUrl)('/apps/assistant/i/cancel_generation');
      _nextcloud_axios__WEBPACK_IMPORTED_MODULE_10__["default"].post(url, {
        imageGenId: this.result.image_gen_id
      }).catch(error => {
        console.error('Image generation cancel request error', error);
        (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_12__.showError)(t('assistant', 'Image generation cancel error') + ': ' + (error.response?.data?.body?.error?.message || error.response?.data?.body?.error?.code || error.response?.data?.error || t('assistant', 'Unknown image generation cancel error')));
      });
      this.result = null;
    },
    generate() {
      if (this.result !== null) {
        this.cancelGeneration();
      }
      if (this.query === '') {
        return;
      }
      this.loading = true;
      const params = {
        prompt: this.query,
        nResults: this.nResults,
        displayPrompt: this.displayPrompt
      };
      const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_11__.generateUrl)('/apps/assistant/i/process_prompt');
      return _nextcloud_axios__WEBPACK_IMPORTED_MODULE_10__["default"].post(url, params).then(response => {
        const data = response.data;
        if (data?.url !== undefined) {
          this.result = data;
          this.insertPrompt(this.query);
        } else {
          (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_12__.showError)(t('assistant', 'Unexpected response from server.'));
        }
      }).catch(error => {
        console.error('Image generation request error', error);
        (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_12__.showError)(t('assistant', 'Image generation error') + ': ' + (error.response?.data?.body?.error?.message || error.response?.data?.body?.error?.code || error.response?.data?.error || t('assistant', 'Unknown image generation error')));
      }).then(() => {
        this.loading = false;
      });
    },
    notifyWhenReady() {
      if (this.failed || this.result === null) {
        return;
      }
      const url = (0,_nextcloud_router__WEBPACK_IMPORTED_MODULE_11__.generateUrl)('/apps/assistant/i/notify/' + this.result.image_gen_id);
      _nextcloud_axios__WEBPACK_IMPORTED_MODULE_10__["default"].post(url).then(() => {
        (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_12__.showMessage)(t('assistant', 'You will be notified when the image generation is ready.'));
        this.notify = true;
        this.$emit('submit', '');
      }).catch(error => {
        console.error('Notify when ready request error', error);
        (0,_nextcloud_dialogs__WEBPACK_IMPORTED_MODULE_12__.showError)(t('assistant', 'Notify when ready error') + ': ' + (error.response?.data?.body?.error?.message || error.response?.data?.body?.error?.code || error.response?.data?.error || t('assistant', 'Unknown notify when ready error')));
      });
    },
    imageGenerationFailed() {
      this.isGenerating = false;
    },
    imageGenerationReady() {
      this.isGenerating = false;
    },
    imageGenerationIsProcessing() {
      this.isGenerating = true;
    }
  }
});

/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=template&id=1474f52a&scoped=true":
/*!**********************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=template&id=1474f52a&scoped=true ***!
  \**********************************************************************************************************************************************************************************************************************************************************************************************/
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
  }, [_vm.result !== null && _vm.result !== '' ? _c('NcRichContenteditable', {
    ref: "output",
    staticClass: "editable-preview",
    attrs: {
      "id": "free-prompt-output",
      "value": _vm.result,
      "multiline": true,
      "disabled": _vm.loading,
      "placeholder": _vm.t('assistant', 'Text generation content'),
      "link-autocomplete": false
    },
    on: {
      "update:value": [function ($event) {
        _vm.result = $event;
      }, _vm.onTextEdit]
    }
  }) : _vm._e(), _vm._v(" "), _vm.loading ? _c('div', [_c('NcLoadingIcon', {
    attrs: {
      "size": 64
    }
  }), _vm._v(" "), _vm.processing ? _c('div', {
    staticClass: "loading-info"
  }, [_vm.bgProcessingScheduled ? _c('div', {
    staticClass: "task-scheduled-info"
  }, [_vm._v("\n\t\t\t\t" + _vm._s(_vm.t('assistant', 'The text generation task was scheduled to run in the background.')) + "\n\t\t\t\t"), _vm.timeUntilCompletion !== null ? _c('div', [_vm._v("\n\t\t\t\t\t" + _vm._s(_vm.t('assistant', 'Estimated completion time: ') + _vm.timeUntilCompletion) + "\n\t\t\t\t")]) : _c('div', [_vm._v("\n\t\t\t\t\t" + _vm._s(_vm.t('assistant', 'This can take a while…')) + "\n\t\t\t\t")])]) : _c('div', [_vm._v("\n\t\t\t\t" + _vm._s(_vm.t('assistant', 'Some generations are still being processed in the background! Showing finished generations.')) + "\n\t\t\t")])]) : _c('div', [_vm._v("\n\t\t\t" + _vm._s(_vm.t('assistant', 'Loading generations…')) + "\n\t\t")])], 1) : _vm._e(), _vm._v(" "), !_vm.loading ? _c('div', {
    staticClass: "button-wrapper"
  }, [_c('NcButton', {
    attrs: {
      "disabled": _vm.result === null || _vm.loading,
      "type": "secondary",
      "title": _vm.t('assistant', 'Copy output text to clipboard')
    },
    on: {
      "click": _vm.onCopy
    },
    scopedSlots: _vm._u([{
      key: "icon",
      fn: function () {
        return [_vm.copied ? _c('ClipboardCheckOutlineIcon') : _c('ContentCopyIcon')];
      },
      proxy: true
    }], null, false, 2003204616)
  }, [_vm._v("\n\t\t\t" + _vm._s(_vm.t('assistant', 'Copy output')) + "\n\t\t\t")]), _vm._v(" "), _c('NcButton', {
    attrs: {
      "disabled": _vm.result === _vm.originalResult || _vm.loading,
      "type": "secondary",
      "title": _vm.t('assistant', 'Reset the output value to the originally generated one')
    },
    on: {
      "click": _vm.delayedReset
    }
  }, [_vm._v("\n\t\t\t" + _vm._s(_vm.t('assistant', 'Reset')) + "\n\t\t")]), _vm._v(" "), _c('NcCheckboxRadioSwitch', {
    attrs: {
      "checked": _vm.includePrompt,
      "disabled": _vm.loading || _vm.result === ''
    },
    on: {
      "update:checked": [function ($event) {
        _vm.includePrompt = $event;
      }, _vm.onIncludePromptToggle]
    }
  }, [_vm._v("\n\t\t\t" + _vm._s(_vm.t('assistant', 'Include prompt in the final result')) + "\n\t\t")])], 1) : _vm._e()], 1);
};
var staticRenderFns = [];
render._withStripped = true;


/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=template&id=591b4c70&scoped=true":
/*!*******************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=template&id=591b4c70&scoped=true ***!
  \*******************************************************************************************************************************************************************************************************************************************************************************************/
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
    staticClass: "assistant-picker-content-wrapper"
  }, [_c('div', {
    staticClass: "assistant-picker-content"
  }, [_c('h2', [_vm._v("\n\t\t\t" + _vm._s(_vm.t('assistant', 'AI text generation')) + "\n\t\t")]), _vm._v(" "), _c('div', {
    staticClass: "input-wrapper"
  }, [_c('NcTextField', {
    ref: "assistant-search-input",
    attrs: {
      "value": _vm.query,
      "label": _vm.t('assistant', 'Enter your question or task here:'),
      "placeholder": "Generate a TODO list for a classic work day.",
      "disabled": _vm.loading,
      "show-trailing-button": !!_vm.query
    },
    on: {
      "update:value": function ($event) {
        _vm.query = $event;
      },
      "keydown": function ($event) {
        if (!$event.type.indexOf('key') && _vm._k($event.keyCode, "enter", 13, $event.key, "Enter")) return null;
        return _vm.generate.apply(null, arguments);
      },
      "trailing-button-click": function ($event) {
        _vm.query = '';
      }
    }
  })], 1), _vm._v(" "), _vm.result === null || _vm.query === '' ? _c('div', {
    staticClass: "prompts"
  }, _vm._l(_vm.prompts, function (p) {
    return _c('NcUserBubble', {
      key: p.id + p.value,
      staticClass: "prompt-bubble",
      attrs: {
        "title": p.value,
        "size": 30,
        "avatar-image": "icon-history",
        "display-name": p.value
      },
      on: {
        "click": function ($event) {
          _vm.query = p.value;
        }
      }
    });
  }), 1) : _vm._e(), _vm._v(" "), _c('div', {
    staticClass: "preview"
  }, [_vm.genId !== null ? _c('FreePromptGenerationDisplay', {
    attrs: {
      "gen-id": _vm.genId,
      "result": _vm.result
    },
    on: {
      "update:result": function ($event) {
        _vm.result = $event;
      },
      "error": _vm.onError,
      "loaded": _vm.onLoaded,
      "scheduled": function ($event) {
        _vm.scheduled = true;
      }
    }
  }) : _vm._e()], 1), _vm._v(" "), _c('div', {
    staticClass: "footer"
  }, [_c('NcButton', {
    attrs: {
      "type": "secondary",
      "aria-label": _vm.t('assistant', 'Preview text generation by AI'),
      "disabled": _vm.loading || !_vm.query
    },
    on: {
      "click": _vm.generate
    },
    scopedSlots: _vm._u([{
      key: "icon",
      fn: function () {
        return [_vm.loading ? _c('NcLoadingIcon') : _vm.result !== null ? _c('RefreshIcon') : _c('EyeIcon')];
      },
      proxy: true
    }])
  }, [_vm._v("\n\t\t\t\t" + _vm._s(_vm.previewButtonLabel) + "\n\t\t\t\t")]), _vm._v(" "), _vm.genId !== null && _vm.loading && _vm.scheduled ? _c('NcButton', {
    attrs: {
      "type": "secondary"
    },
    on: {
      "click": _vm.notifyWhenReady
    },
    scopedSlots: _vm._u([{
      key: "icon",
      fn: function () {
        return [_c('ArrowRightIcon')];
      },
      proxy: true
    }], null, false, 1168934321)
  }, [_vm._v("\n\t\t\t\t" + _vm._s(_vm.t('assistant', 'Notify when ready')) + "\n\t\t\t\t")]) : _vm._e(), _vm._v(" "), _vm.result !== null ? _c('NcButton', {
    attrs: {
      "type": "primary",
      "aria-label": _vm.t('assistant', 'Submit text generated by AI'),
      "disabled": _vm.loading || _vm.emptyResult
    },
    on: {
      "click": _vm.submit
    },
    scopedSlots: _vm._u([{
      key: "icon",
      fn: function () {
        return [_c('ArrowRightIcon')];
      },
      proxy: true
    }], null, false, 1168934321)
  }, [_vm._v("\n\t\t\t\t" + _vm._s(_vm.t('assistant', 'Submit')) + "\n\t\t\t\t")]) : _vm._e()], 1)])]);
};
var staticRenderFns = [];
render._withStripped = true;


/***/ }),

/***/ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=template&id=b9b6acf0&scoped=true":
/*!*******************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=template&id=b9b6acf0&scoped=true ***!
  \*******************************************************************************************************************************************************************************************************************************************************************************************/
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
    staticClass: "text2image-picker-content-wrapper"
  }, [_c('div', {
    staticClass: "text2image-picker-content"
  }, [_c('h2', [_vm._v("\n\t\t\t" + _vm._s(_vm.t('assistant', 'AI Image Generation')) + "\n\t\t")]), _vm._v(" "), _c('div', {
    staticClass: "input-wrapper"
  }, [_c('NcTextField', {
    ref: "text2image-search-input",
    attrs: {
      "value": _vm.query,
      "label": _vm.inputPlaceholder,
      "placeholder": "",
      "disabled": _vm.loading,
      "show-trailing-button": !!_vm.query
    },
    on: {
      "update:value": function ($event) {
        _vm.query = $event;
      },
      "keydown": function ($event) {
        if (!$event.type.indexOf('key') && _vm._k($event.keyCode, "enter", 13, $event.key, "Enter")) return null;
        return _vm.generate.apply(null, arguments);
      },
      "trailing-button-click": function ($event) {
        _vm.query = '';
      }
    }
  })], 1), _vm._v(" "), _vm.result === null || _vm.query === '' ? _c('div', {
    staticClass: "prompts"
  }, _vm._l(_vm.prompts, function (p) {
    return _c('NcUserBubble', {
      key: p.id + p.value,
      staticClass: "prompt-bubble",
      attrs: {
        "title": p.value,
        "size": 30,
        "avatar-image": "icon-history",
        "display-name": p.value
      },
      on: {
        "click": function ($event) {
          _vm.query = p.value;
        }
      }
    });
  }), 1) : _vm._e(), _vm._v(" "), _vm.result !== null ? _c('div', {
    staticClass: "preview-container"
  }, [_c('h3', [_vm._v(_vm._s(_vm.t('assistant', 'Preview')))]), _vm._v(" "), _c('div', {
    staticClass: "image-preview"
  }, [_c('Text2ImageDisplay', {
    key: _vm.result.image_gen_id,
    attrs: {
      "src": _vm.result.url,
      "prompt": _vm.result.prompt
    },
    on: {
      "failed": _vm.imageGenerationFailed,
      "ready": _vm.imageGenerationReady,
      "processing": _vm.imageGenerationIsProcessing
    }
  })], 1)]) : _vm._e(), _vm._v(" "), _c('div', {
    staticClass: "footer"
  }, [_c('div', [_c('NcButton', {
    staticClass: "advanced-button",
    attrs: {
      "type": "tertiary",
      "aria-label": _vm.t('assistant', 'Show/hide advanced options')
    },
    on: {
      "click": function ($event) {
        _vm.showAdvanced = !_vm.showAdvanced;
      }
    },
    scopedSlots: _vm._u([{
      key: "icon",
      fn: function () {
        return [_c(_vm.showAdvancedIcon, {
          tag: "component"
        })];
      },
      proxy: true
    }])
  }, [_vm._v("\n\t\t\t\t\t" + _vm._s(_vm.t('assistant', 'Advanced options')) + "\n\t\t\t\t")])], 1), _vm._v(" "), _c('div', {
    staticClass: "buttons"
  }, [_c('NcButton', {
    attrs: {
      "type": "secondary",
      "aria-label": _vm.t('assistant', 'Preview image generation by AI'),
      "disabled": _vm.loading || !_vm.query
    },
    on: {
      "click": _vm.generate
    },
    scopedSlots: _vm._u([{
      key: "icon",
      fn: function () {
        return [_vm.loading ? _c('NcLoadingIcon') : _vm.result !== null ? _c('RefreshIcon') : _c('EyeIcon')];
      },
      proxy: true
    }])
  }, [_vm._v("\n\t\t\t\t\t" + _vm._s(_vm.previewButtonLabel) + "\n\t\t\t\t\t")]), _vm._v(" "), _vm.result !== null ? _c('NcButton', {
    attrs: {
      "type": "secondary",
      "disabled": !_vm.isGenerating
    },
    on: {
      "click": _vm.notifyWhenReady
    },
    scopedSlots: _vm._u([{
      key: "icon",
      fn: function () {
        return [_c('ArrowRightIcon')];
      },
      proxy: true
    }], null, false, 1168934321)
  }, [_vm._v("\n\t\t\t\t\t" + _vm._s(_vm.t('assistant', 'Notify when ready')) + "\n\t\t\t\t\t")]) : _vm._e(), _vm._v(" "), _vm.result !== null ? _c('NcButton', {
    attrs: {
      "type": "primary",
      "aria-label": _vm.t('assistant', 'Submit image(s) generated by AI'),
      "disabled": _vm.loading || _vm.emptyResult
    },
    on: {
      "click": _vm.submit
    },
    scopedSlots: _vm._u([{
      key: "icon",
      fn: function () {
        return [_c('ArrowRightIcon')];
      },
      proxy: true
    }], null, false, 1168934321)
  }, [_vm._v("\n\t\t\t\t\t" + _vm._s(_vm.t('assistant', 'Send')) + "\n\t\t\t\t\t")]) : _vm._e()], 1)]), _vm._v(" "), _c('div', {
    directives: [{
      name: "show",
      rawName: "v-show",
      value: _vm.showAdvanced,
      expression: "showAdvanced"
    }],
    staticClass: "advanced"
  }, [_c('div', {
    staticClass: "line"
  }, [_c('NcCheckboxRadioSwitch', {
    staticClass: "include-query",
    attrs: {
      "checked": _vm.displayPrompt
    },
    on: {
      "update:checked": function ($event) {
        _vm.displayPrompt = $event;
      }
    }
  }, [_vm._v("\n\t\t\t\t\t" + _vm._s(_vm.t('assistant', 'Include the prompt in the result')) + "\n\t\t\t\t")])], 1), _vm._v(" "), _c('div', {
    staticClass: "spacer"
  }), _vm._v(" "), _c('div', {
    staticClass: "line"
  }, [_c('label', {
    attrs: {
      "for": "nResults"
    }
  }, [_vm._v(_vm._s(_vm.t('assistant', 'Number of results')))]), _vm._v(" "), _c('input', {
    directives: [{
      name: "model",
      rawName: "v-model",
      value: _vm.nResults,
      expression: "nResults"
    }],
    attrs: {
      "id": "nResults",
      "type": "number",
      "min": "1",
      "max": "10"
    },
    domProps: {
      "value": _vm.nResults
    },
    on: {
      "input": function ($event) {
        if ($event.target.composing) return;
        _vm.nResults = $event.target.value;
      }
    }
  })])])])]);
};
var staticRenderFns = [];
render._withStripped = true;


/***/ }),

/***/ "./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss":
/*!*****************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss ***!
  \*****************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
___CSS_LOADER_EXPORT___.push([module.id, `.display-container[data-v-1474f52a] {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  width: 100%;
  margin-top: 24px;
}
.display-container .loading-info[data-v-1474f52a] {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-top: 12px;
  margin-bottom: 24px;
}
.display-container .loading-info .task-scheduled-info[data-v-1474f52a] {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.display-container .button-wrapper[data-v-1474f52a] {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  margin-top: 12px;
  margin-bottom: 12px;
}
.display-container .button-wrapper > *[data-v-1474f52a] {
  margin-right: 12px;
  margin-left: 12px;
}
.display-container .editable-preview[data-v-1474f52a] {
  display: flex;
  flex-direction: column;
  width: 100%;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 12px;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;
  margin-bottom: 24px;
}`, ""]);
// Exports
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (___CSS_LOADER_EXPORT___);


/***/ }),

/***/ "./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss":
/*!**************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss ***!
  \**************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
___CSS_LOADER_EXPORT___.push([module.id, `.assistant-picker-content-wrapper[data-v-591b4c70] {
  width: 100%;
}
.assistant-picker-content[data-v-591b4c70] {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 12px 16px 16px 16px;
}
.assistant-picker-content h2[data-v-591b4c70] {
  display: flex;
  align-items: center;
}
.assistant-picker-content .prompts[data-v-591b4c70] {
  margin-top: 8px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
}
.assistant-picker-content .prompts > *[data-v-591b4c70] {
  margin-right: 8px;
}
.assistant-picker-content .prompt-bubble[data-v-591b4c70] {
  max-width: 250px;
}
.assistant-picker-content .preview[data-v-591b4c70] {
  width: 100%;
}
.assistant-picker-content .spacer[data-v-591b4c70] {
  flex-grow: 1;
}
.assistant-picker-content .attribution[data-v-591b4c70] {
  color: var(--color-text-maxcontrast);
  padding-bottom: 8px;
}
.assistant-picker-content .input-wrapper[data-v-591b4c70] {
  display: flex;
  align-items: center;
  width: 100%;
}
.assistant-picker-content .prompt-select[data-v-591b4c70] {
  width: 100%;
  margin-top: 4px;
}
.assistant-picker-content .footer[data-v-591b4c70] {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: end;
  margin-top: 12px;
}
.assistant-picker-content .footer > *[data-v-591b4c70] {
  margin-left: 4px;
}`, ""]);
// Exports
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (___CSS_LOADER_EXPORT___);


/***/ }),

/***/ "./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss":
/*!**************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss ***!
  \**************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
___CSS_LOADER_EXPORT___.push([module.id, `.text2image-picker-content-wrapper[data-v-b9b6acf0] {
  width: 100%;
}
.text2image-picker-content[data-v-b9b6acf0] {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 12px 16px 16px 16px;
}
.text2image-picker-content h2[data-v-b9b6acf0] {
  display: flex;
  align-items: center;
}
.text2image-picker-content .prompts[data-v-b9b6acf0] {
  margin-top: 8px;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
}
.text2image-picker-content .prompts > *[data-v-b9b6acf0] {
  margin-right: 8px;
}
.text2image-picker-content .prompt-bubble[data-v-b9b6acf0] {
  max-width: 250px;
}
.text2image-picker-content .preview-container[data-v-b9b6acf0] {
  width: 90%;
}
.text2image-picker-content .preview-container .image-preview[data-v-b9b6acf0] {
  display: flex;
  flex-direction: column;
  margin-top: 8px;
  border: 3px solid var(--color-border);
  border-radius: var(--border-radius-large);
  padding: 12px;
}
.text2image-picker-content .spacer[data-v-b9b6acf0] {
  flex-grow: 1;
}
.text2image-picker-content .input-wrapper[data-v-b9b6acf0] {
  display: flex;
  align-items: center;
  width: 100%;
}
.text2image-picker-content .prompt-select[data-v-b9b6acf0] {
  width: 100%;
  margin-top: 4px;
}
.text2image-picker-content .footer[data-v-b9b6acf0] {
  width: 100%;
  display: flex;
  flex-direction: row;
  margin-top: 12px;
  justify-content: space-between;
}
.text2image-picker-content .footer .buttons[data-v-b9b6acf0] {
  display: flex;
  align-items: end;
}
.text2image-picker-content .footer .buttons > *[data-v-b9b6acf0] {
  margin-left: 4px;
}
.text2image-picker-content .advanced[data-v-b9b6acf0] {
  width: 100%;
  padding: 12px 0;
}
.text2image-picker-content .advanced .line[data-v-b9b6acf0] {
  display: flex;
  align-items: center;
  margin-top: 8px;
}
.text2image-picker-content .advanced .line input[data-v-b9b6acf0],
.text2image-picker-content .advanced .line select[data-v-b9b6acf0] {
  margin-left: 24px;
  width: 200px;
}
.text2image-picker-content .advanced input[type=number][data-v-b9b6acf0] {
  width: 80px;
  appearance: initial !important;
  -moz-appearance: initial !important;
  -webkit-appearance: initial !important;
}`, ""]);
// Exports
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (___CSS_LOADER_EXPORT___);


/***/ }),

/***/ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss":
/*!*********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss ***!
  \*********************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
/* harmony import */ var _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_style_index_0_id_1474f52a_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! !!../../../node_modules/css-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/sass-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss */ "./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss");

      
      
      
      
      
      
      
      
      

var options = {};

options.styleTagTransform = (_node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5___default());
options.setAttributes = (_node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3___default());

      options.insert = _node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2___default().bind(null, "head");
    
options.domAPI = (_node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1___default());
options.insertStyleElement = (_node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4___default());

var update = _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0___default()(_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_style_index_0_id_1474f52a_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"], options);




       /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_style_index_0_id_1474f52a_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"] && _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_style_index_0_id_1474f52a_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals ? _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_style_index_0_id_1474f52a_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals : undefined);


/***/ }),

/***/ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss":
/*!******************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss ***!
  \******************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
/* harmony import */ var _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_style_index_0_id_591b4c70_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! !!../../../node_modules/css-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/sass-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss */ "./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss");

      
      
      
      
      
      
      
      
      

var options = {};

options.styleTagTransform = (_node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5___default());
options.setAttributes = (_node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3___default());

      options.insert = _node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2___default().bind(null, "head");
    
options.domAPI = (_node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1___default());
options.insertStyleElement = (_node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4___default());

var update = _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0___default()(_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_style_index_0_id_591b4c70_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"], options);




       /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_style_index_0_id_591b4c70_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"] && _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_style_index_0_id_591b4c70_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals ? _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_style_index_0_id_591b4c70_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals : undefined);


/***/ }),

/***/ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss":
/*!******************************************************************************************************************************************************************************************************************************************************************************************************************************************************************!*\
  !*** ./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss ***!
  \******************************************************************************************************************************************************************************************************************************************************************************************************************************************************************/
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
/* harmony import */ var _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_style_index_0_id_b9b6acf0_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! !!../../../node_modules/css-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/sass-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss */ "./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss");

      
      
      
      
      
      
      
      
      

var options = {};

options.styleTagTransform = (_node_modules_style_loader_dist_runtime_styleTagTransform_js__WEBPACK_IMPORTED_MODULE_5___default());
options.setAttributes = (_node_modules_style_loader_dist_runtime_setAttributesWithoutAttributes_js__WEBPACK_IMPORTED_MODULE_3___default());

      options.insert = _node_modules_style_loader_dist_runtime_insertBySelector_js__WEBPACK_IMPORTED_MODULE_2___default().bind(null, "head");
    
options.domAPI = (_node_modules_style_loader_dist_runtime_styleDomAPI_js__WEBPACK_IMPORTED_MODULE_1___default());
options.insertStyleElement = (_node_modules_style_loader_dist_runtime_insertStyleElement_js__WEBPACK_IMPORTED_MODULE_4___default());

var update = _node_modules_style_loader_dist_runtime_injectStylesIntoStyleTag_js__WEBPACK_IMPORTED_MODULE_0___default()(_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_style_index_0_id_b9b6acf0_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"], options);




       /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_style_index_0_id_b9b6acf0_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"] && _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_style_index_0_id_b9b6acf0_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals ? _node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_style_index_0_id_b9b6acf0_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_6__["default"].locals : undefined);


/***/ }),

/***/ "./src/components/FreePrompt/FreePromptGenerationDisplay.vue":
/*!*******************************************************************!*\
  !*** ./src/components/FreePrompt/FreePromptGenerationDisplay.vue ***!
  \*******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _FreePromptGenerationDisplay_vue_vue_type_template_id_1474f52a_scoped_true__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./FreePromptGenerationDisplay.vue?vue&type=template&id=1474f52a&scoped=true */ "./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=template&id=1474f52a&scoped=true");
/* harmony import */ var _FreePromptGenerationDisplay_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./FreePromptGenerationDisplay.vue?vue&type=script&lang=js */ "./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=script&lang=js");
/* harmony import */ var _FreePromptGenerationDisplay_vue_vue_type_style_index_0_id_1474f52a_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss */ "./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss");
/* harmony import */ var _node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! !../../../node_modules/vue-loader/lib/runtime/componentNormalizer.js */ "./node_modules/vue-loader/lib/runtime/componentNormalizer.js");



;


/* normalize component */

var component = (0,_node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _FreePromptGenerationDisplay_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"],
  _FreePromptGenerationDisplay_vue_vue_type_template_id_1474f52a_scoped_true__WEBPACK_IMPORTED_MODULE_0__.render,
  _FreePromptGenerationDisplay_vue_vue_type_template_id_1474f52a_scoped_true__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns,
  false,
  null,
  "1474f52a",
  null
  
)

/* hot reload */
if (false) { var api; }
component.options.__file = "src/components/FreePrompt/FreePromptGenerationDisplay.vue"
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (component.exports);

/***/ }),

/***/ "./src/views/FreePrompt/FreePromptCustomPickerElement.vue":
/*!****************************************************************!*\
  !*** ./src/views/FreePrompt/FreePromptCustomPickerElement.vue ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _FreePromptCustomPickerElement_vue_vue_type_template_id_591b4c70_scoped_true__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./FreePromptCustomPickerElement.vue?vue&type=template&id=591b4c70&scoped=true */ "./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=template&id=591b4c70&scoped=true");
/* harmony import */ var _FreePromptCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./FreePromptCustomPickerElement.vue?vue&type=script&lang=js */ "./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=script&lang=js");
/* harmony import */ var _FreePromptCustomPickerElement_vue_vue_type_style_index_0_id_591b4c70_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss */ "./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss");
/* harmony import */ var _node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! !../../../node_modules/vue-loader/lib/runtime/componentNormalizer.js */ "./node_modules/vue-loader/lib/runtime/componentNormalizer.js");



;


/* normalize component */

var component = (0,_node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _FreePromptCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"],
  _FreePromptCustomPickerElement_vue_vue_type_template_id_591b4c70_scoped_true__WEBPACK_IMPORTED_MODULE_0__.render,
  _FreePromptCustomPickerElement_vue_vue_type_template_id_591b4c70_scoped_true__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns,
  false,
  null,
  "591b4c70",
  null
  
)

/* hot reload */
if (false) { var api; }
component.options.__file = "src/views/FreePrompt/FreePromptCustomPickerElement.vue"
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (component.exports);

/***/ }),

/***/ "./src/views/Text2Image/Text2ImageCustomPickerElement.vue":
/*!****************************************************************!*\
  !*** ./src/views/Text2Image/Text2ImageCustomPickerElement.vue ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _Text2ImageCustomPickerElement_vue_vue_type_template_id_b9b6acf0_scoped_true__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./Text2ImageCustomPickerElement.vue?vue&type=template&id=b9b6acf0&scoped=true */ "./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=template&id=b9b6acf0&scoped=true");
/* harmony import */ var _Text2ImageCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Text2ImageCustomPickerElement.vue?vue&type=script&lang=js */ "./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=script&lang=js");
/* harmony import */ var _Text2ImageCustomPickerElement_vue_vue_type_style_index_0_id_b9b6acf0_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss */ "./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss");
/* harmony import */ var _node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! !../../../node_modules/vue-loader/lib/runtime/componentNormalizer.js */ "./node_modules/vue-loader/lib/runtime/componentNormalizer.js");



;


/* normalize component */

var component = (0,_node_modules_vue_loader_lib_runtime_componentNormalizer_js__WEBPACK_IMPORTED_MODULE_3__["default"])(
  _Text2ImageCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_1__["default"],
  _Text2ImageCustomPickerElement_vue_vue_type_template_id_b9b6acf0_scoped_true__WEBPACK_IMPORTED_MODULE_0__.render,
  _Text2ImageCustomPickerElement_vue_vue_type_template_id_b9b6acf0_scoped_true__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns,
  false,
  null,
  "b9b6acf0",
  null
  
)

/* hot reload */
if (false) { var api; }
component.options.__file = "src/views/Text2Image/Text2ImageCustomPickerElement.vue"
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (component.exports);

/***/ }),

/***/ "./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=script&lang=js":
/*!*******************************************************************************************!*\
  !*** ./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=script&lang=js ***!
  \*******************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./FreePromptGenerationDisplay.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=script&lang=js");
 /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"]); 

/***/ }),

/***/ "./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=script&lang=js":
/*!****************************************************************************************!*\
  !*** ./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=script&lang=js ***!
  \****************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./FreePromptCustomPickerElement.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=script&lang=js");
 /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"]); 

/***/ }),

/***/ "./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=script&lang=js":
/*!****************************************************************************************!*\
  !*** ./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=script&lang=js ***!
  \****************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./Text2ImageCustomPickerElement.vue?vue&type=script&lang=js */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=script&lang=js");
 /* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_script_lang_js__WEBPACK_IMPORTED_MODULE_0__["default"]); 

/***/ }),

/***/ "./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=template&id=1474f52a&scoped=true":
/*!*************************************************************************************************************!*\
  !*** ./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=template&id=1474f52a&scoped=true ***!
  \*************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   render: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_template_id_1474f52a_scoped_true__WEBPACK_IMPORTED_MODULE_0__.render),
/* harmony export */   staticRenderFns: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_template_id_1474f52a_scoped_true__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_template_id_1474f52a_scoped_true__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./FreePromptGenerationDisplay.vue?vue&type=template&id=1474f52a&scoped=true */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=template&id=1474f52a&scoped=true");


/***/ }),

/***/ "./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=template&id=591b4c70&scoped=true":
/*!**********************************************************************************************************!*\
  !*** ./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=template&id=591b4c70&scoped=true ***!
  \**********************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   render: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_template_id_591b4c70_scoped_true__WEBPACK_IMPORTED_MODULE_0__.render),
/* harmony export */   staticRenderFns: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_template_id_591b4c70_scoped_true__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_template_id_591b4c70_scoped_true__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./FreePromptCustomPickerElement.vue?vue&type=template&id=591b4c70&scoped=true */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=template&id=591b4c70&scoped=true");


/***/ }),

/***/ "./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=template&id=b9b6acf0&scoped=true":
/*!**********************************************************************************************************!*\
  !*** ./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=template&id=b9b6acf0&scoped=true ***!
  \**********************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   render: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_template_id_b9b6acf0_scoped_true__WEBPACK_IMPORTED_MODULE_0__.render),
/* harmony export */   staticRenderFns: () => (/* reexport safe */ _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_template_id_b9b6acf0_scoped_true__WEBPACK_IMPORTED_MODULE_0__.staticRenderFns)
/* harmony export */ });
/* harmony import */ var _node_modules_babel_loader_lib_index_js_node_modules_vue_loader_lib_loaders_templateLoader_js_ruleSet_1_rules_3_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_template_id_b9b6acf0_scoped_true__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/babel-loader/lib/index.js!../../../node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./Text2ImageCustomPickerElement.vue?vue&type=template&id=b9b6acf0&scoped=true */ "./node_modules/babel-loader/lib/index.js!./node_modules/vue-loader/lib/loaders/templateLoader.js??ruleSet[1].rules[3]!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=template&id=b9b6acf0&scoped=true");


/***/ }),

/***/ "./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss":
/*!****************************************************************************************************************************!*\
  !*** ./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss ***!
  \****************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_style_loader_dist_cjs_js_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptGenerationDisplay_vue_vue_type_style_index_0_id_1474f52a_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/style-loader/dist/cjs.js!../../../node_modules/css-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/sass-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss */ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/components/FreePrompt/FreePromptGenerationDisplay.vue?vue&type=style&index=0&id=1474f52a&scoped=true&lang=scss");


/***/ }),

/***/ "./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss":
/*!*************************************************************************************************************************!*\
  !*** ./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss ***!
  \*************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_style_loader_dist_cjs_js_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_FreePromptCustomPickerElement_vue_vue_type_style_index_0_id_591b4c70_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/style-loader/dist/cjs.js!../../../node_modules/css-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/sass-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss */ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/FreePrompt/FreePromptCustomPickerElement.vue?vue&type=style&index=0&id=591b4c70&scoped=true&lang=scss");


/***/ }),

/***/ "./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss":
/*!*************************************************************************************************************************!*\
  !*** ./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss ***!
  \*************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _node_modules_style_loader_dist_cjs_js_node_modules_css_loader_dist_cjs_js_node_modules_vue_loader_lib_loaders_stylePostLoader_js_node_modules_sass_loader_dist_cjs_js_node_modules_vue_loader_lib_index_js_vue_loader_options_Text2ImageCustomPickerElement_vue_vue_type_style_index_0_id_b9b6acf0_scoped_true_lang_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! -!../../../node_modules/style-loader/dist/cjs.js!../../../node_modules/css-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/loaders/stylePostLoader.js!../../../node_modules/sass-loader/dist/cjs.js!../../../node_modules/vue-loader/lib/index.js??vue-loader-options!./Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss */ "./node_modules/style-loader/dist/cjs.js!./node_modules/css-loader/dist/cjs.js!./node_modules/vue-loader/lib/loaders/stylePostLoader.js!./node_modules/sass-loader/dist/cjs.js!./node_modules/vue-loader/lib/index.js??vue-loader-options!./src/views/Text2Image/Text2ImageCustomPickerElement.vue?vue&type=style&index=0&id=b9b6acf0&scoped=true&lang=scss");


/***/ })

}]);
//# sourceMappingURL=assistant-reference-picker-lazy.js.map?v=f39943e5f7503cfe5ae1