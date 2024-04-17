import Vue from 'vue'
import AssistantPage from './views/AssistantPage.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(AssistantPage)
new View().$mount('#content')
