/**
 * INNOVA WEB — Environment & Configuration Resolver
 *
 * Resolves runtime configuration without hardcoding hostnames, ports, or credentials.
 * Automatically discovers API base URL, CSRF tokens, and application runtime modes.
 */

declare global {
  interface Window {
    __APP_CONFIG__?: {
      apiBaseUrl?: string;
      environment?: string;
      appName?: string;
      csrfToken?: string;
    };
  }
}

/**
 * Resolve the API version 1 base URL dynamically.
 * Priority:
 * 1. Root container dataset attribute (data-api-base-url)
 * 2. Window global configuration (__APP_CONFIG__.apiBaseUrl)
 * 3. Vite environment variable (import.meta.env.VITE_API_BASE_URL)
 * 4. Relative default path ('/api/v1')
 */
export function getApiBaseUrl(): string {
  if (typeof document !== 'undefined') {
    const mountEl = document.getElementById('react-root');
    const datasetUrl = mountEl?.getAttribute('data-api-base-url');
    if (datasetUrl) {
      return datasetUrl.replace(/\/+$/, '');
    }
  }

  if (typeof window !== 'undefined' && window.__APP_CONFIG__?.apiBaseUrl) {
    return window.__APP_CONFIG__.apiBaseUrl.replace(/\/+$/, '');
  }

  const envUrl = (import.meta as unknown as { env?: { VITE_API_BASE_URL?: string } }).env?.VITE_API_BASE_URL;
  if (envUrl) {
    return envUrl.replace(/\/+$/, '');
  }

  return '/api/v1';
}

/**
 * Retrieve the Laravel CSRF token from the DOM meta tag or window config.
 */
export function getCsrfToken(): string | null {
  if (typeof document !== 'undefined') {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
      return metaTag.getAttribute('content');
    }
  }

  if (typeof window !== 'undefined' && window.__APP_CONFIG__?.csrfToken) {
    return window.__APP_CONFIG__.csrfToken;
  }

  return null;
}

/**
 * Retrieve stored Sanctum authentication token from local storage or memory.
 */
export function getAuthToken(): string | null {
  if (typeof window === 'undefined') {
    return null;
  }
  return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
}

/**
 * Set Sanctum authentication token in storage.
 */
export function setAuthToken(token: string, remember: boolean = true): void {
  if (typeof window === 'undefined') {
    return;
  }
  if (remember) {
    localStorage.setItem('auth_token', token);
  } else {
    sessionStorage.setItem('auth_token', token);
  }
}

/**
 * Clear stored authentication token.
 */
export function clearAuthToken(): void {
  if (typeof window === 'undefined') {
    return;
  }
  localStorage.removeItem('auth_token');
  sessionStorage.removeItem('auth_token');
}
