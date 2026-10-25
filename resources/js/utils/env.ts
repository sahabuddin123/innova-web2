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

import type { AuthRoleType } from '../types/auth';

// In-memory token storage (XSS hardened: credentials and tokens stay in-memory first)
let inMemoryAuthToken: string | null = null;
let inMemoryRoleType: AuthRoleType | null = null;

const STORAGE_KEY_TOKEN = 'innova_session_token';
const STORAGE_KEY_ROLE = 'innova_session_role';

/**
 * Retrieve active Sanctum authentication token.
 * Priority:
 * 1. In-memory state (fastest, most secure)
 * 2. Active browser tab session (sessionStorage)
 * Kept strictly out of permanent localStorage to prevent token harvesting.
 */
export function getAuthToken(): string | null {
  if (inMemoryAuthToken) {
    return inMemoryAuthToken;
  }

  if (typeof window !== 'undefined') {
    try {
      const sessionToken = sessionStorage.getItem(STORAGE_KEY_TOKEN);
      if (sessionToken) {
        inMemoryAuthToken = sessionToken;
        return sessionToken;
      }
    } catch {
      // Storage access may be restricted in sandboxed environments
    }
  }

  return null;
}

/**
 * Set active Sanctum authentication token.
 * Saves in-memory and in sessionStorage for current tab persistence.
 * Actively purges any legacy permanent localStorage entries.
 */
export function setAuthToken(token: string): void {
  inMemoryAuthToken = token;

  if (typeof window !== 'undefined') {
    try {
      sessionStorage.setItem(STORAGE_KEY_TOKEN, token);
      // Cleanse any old token from permanent localStorage
      localStorage.removeItem('auth_token');
      localStorage.removeItem(STORAGE_KEY_TOKEN);
    } catch {
      // Silently fall back to in-memory only
    }
  }
}

/**
 * Clear stored authentication token and memory.
 */
export function clearAuthToken(): void {
  inMemoryAuthToken = null;
  inMemoryRoleType = null;

  if (typeof window !== 'undefined') {
    try {
      sessionStorage.removeItem(STORAGE_KEY_TOKEN);
      sessionStorage.removeItem(STORAGE_KEY_ROLE);
      localStorage.removeItem('auth_token');
      localStorage.removeItem(STORAGE_KEY_TOKEN);
      localStorage.removeItem(STORAGE_KEY_ROLE);
    } catch {
      // Ignore storage access errors
    }
  }
}

/**
 * Get active auth role type ('admin' | 'user').
 */
export function getAuthRoleType(): AuthRoleType | null {
  if (inMemoryRoleType) {
    return inMemoryRoleType;
  }

  if (typeof window !== 'undefined') {
    try {
      const stored = sessionStorage.getItem(STORAGE_KEY_ROLE) as AuthRoleType | null;
      if (stored === 'admin' || stored === 'user') {
        inMemoryRoleType = stored;
        return stored;
      }
    } catch {
      // Ignore
    }
  }

  return null;
}

/**
 * Set active auth role type.
 */
export function setAuthRoleType(roleType: AuthRoleType): void {
  inMemoryRoleType = roleType;

  if (typeof window !== 'undefined') {
    try {
      sessionStorage.setItem(STORAGE_KEY_ROLE, roleType);
    } catch {
      // Ignore
    }
  }
}

