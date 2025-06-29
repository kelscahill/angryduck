// src/utils/axiosClient.js
import axios from 'axios';

const axiosClient = axios.create({
  baseURL: (window as any).wwlc_lap.rest_url,
  headers: {
    'X-WP-Nonce': (window as any).wwlc_lap.nonce,
    'Content-Type': 'application/json'
  }
});

export default axiosClient;