import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Add token to requests
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Auth
export const register = (data) => api.post('/register', data);
export const login = (data) => api.post('/login', data);
export const logout = () => api.post('/logout');
export const getMe = () => api.get('/me');

// Events
export const getEvents = () => api.get('/events');
export const getEventBySlug = (slug) => api.get(`/events/${slug}`);

// Orders
export const createOrder = (data) => api.post('/orders', data);
export const getOrders = () => api.get('/orders');
export const getOrderDetail = (orderNumber) => api.get(`/orders/${orderNumber}`);

// Payment - ADD THESE TWO LINES
export const createPayment = (orderNumber) => api.post(`/payment/${orderNumber}`);
export const checkPaymentStatus = (orderNumber) => api.get(`/payment/${orderNumber}/status`);

export default api;
