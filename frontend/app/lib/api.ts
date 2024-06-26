import axios from 'axios';

const API_URL = process.env.REACT_APP_API_URL || 'http://localhost:8000/api/';

const axiosInstance = axios.create({
  baseURL: API_URL, // Cambia esto por la URL de tu API Laravel
  withCredentials: true, // Habilita el envío de cookies en las solicitudes
});

export default axiosInstance;
