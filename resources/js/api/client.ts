import axios, { AxiosInstance, AxiosRequestConfig, AxiosResponse, AxiosError } from 'axios';
import { getApiBaseUrl, getCsrfToken, getAuthToken } from '../utils/env';
import { ApiResponse, ApiErrorResponse } from '../types/api';

/**
 * INNOVA WEB — Centralized Axios Client Instance
 *
 * Configured with dynamic baseURL resolution, CSRF headers,
 * Sanctum authorization token attachment, and error envelope formatting.
 */
export const apiClient: AxiosInstance = axios.create({
  baseURL: getApiBaseUrl(),
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 15000,
});

// Request Interceptor: Attach CSRF and Bearer tokens
apiClient.interceptors.request.use(
  (config) => {
    // Dynamic base URL re-check in case mount element changed
    if (!config.baseURL || config.baseURL === '/') {
      config.baseURL = getApiBaseUrl();
    }

    const csrfToken = getCsrfToken();
    if (csrfToken && config.headers) {
      config.headers['X-CSRF-TOKEN'] = csrfToken;
    }

    const authToken = getAuthToken();
    if (authToken && config.headers && !config.headers.Authorization) {
      config.headers.Authorization = `Bearer ${authToken}`;
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response Interceptor: Standardize error envelopes
apiClient.interceptors.response.use(
  (response: AxiosResponse) => {
    return response;
  },
  (error: AxiosError<ApiErrorResponse>) => {
    const errorResponse: ApiErrorResponse = {
      success: false,
      message: error.response?.data?.message || error.message || 'An unexpected error occurred.',
      errors: error.response?.data?.errors ?? null,
    };

    return Promise.reject(errorResponse);
  }
);

/**
 * Typed HTTP GET Helper
 */
export async function get<T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
  const res = await apiClient.get<ApiResponse<T>>(url, config);
  return res.data;
}

/**
 * Typed HTTP POST Helper
 */
export async function post<T, B = unknown>(url: string, body?: B, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
  const res = await apiClient.post<ApiResponse<T>>(url, body, config);
  return res.data;
}

/**
 * Typed HTTP PUT Helper
 */
export async function put<T, B = unknown>(url: string, body?: B, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
  const res = await apiClient.put<ApiResponse<T>>(url, body, config);
  return res.data;
}

/**
 * Typed HTTP DELETE Helper
 */
export async function del<T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
  const res = await apiClient.delete<ApiResponse<T>>(url, config);
  return res.data;
}
