import Vue from 'vue'
import TaskResultPage from './views/TaskResultPage.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(TaskResultPageImageGenerationPage)
new View().$mount('#content')
