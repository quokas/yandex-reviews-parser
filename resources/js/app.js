import '../css/app.css';
import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';
import axios from 'axios';

axios.defaults.withCredentials = true;
axios.defaults.baseURL = window.location.origin;

const app = createApp(App);
app.mount('#app');
