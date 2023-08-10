import Vue from 'vue'
import PersonalSettings from './components/PersonalSettings.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(PersonalSettings)
new View().$mount('#assistant_prefs')
