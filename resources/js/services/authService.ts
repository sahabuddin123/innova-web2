/**
 * INNOVA WEB — React Authentication Service (Phase 3.1)
 *
 * Implements typed client-side authentication operations calling Laravel 8 / Sanctum endpoints:
 * - Multi-guard login: Admin (/api/v1/admin/auth/login) & Student (/api/v1/auth/login)
 * - Profile hydration: Admin (/api/v1/admin/auth/me) & Student (/api/v1/auth/me)
 * - Token revocation: Admin (/api/v1/admin/auth/logout) & Student (/api/v1/auth/logout)
 * - Safe credential & session token management (in-memory + sessionStorage)
 * - Role & Spatie permission verification helpers
 */

import { get, post, API_ENDPOINTS } from '../api';
import {
  AuthRoleType,
  AuthUser,
  AuthTokenData,
  LoginCredentials,
} from '../types/auth';
import {
  getAuthToken,
  setAuthToken,
  clearAuthToken,
  getAuthRoleType,
  setAuthRoleType,
} from '../utils/env';

export const authService = {
  /**
   * Authenticate user credentials against Laravel Sanctum.
   */
  async login(
    credentials: LoginCredentials,
    roleType: AuthRoleType = 'admin'
  ): Promise<AuthTokenData> {
    const endpoint =
      roleType === 'admin'
        ? API_ENDPOINTS.auth.adminLogin
        : API_ENDPOINTS.auth.userLogin;

    const payload = {
      email: credentials.email.trim(),
      password: credentials.password,
      device_name: credentials.device_name || `innova-spa-${roleType}`,
    };

    const response = await post<AuthTokenData>(endpoint, payload);

    if (response.data?.access_token) {
      setAuthToken(response.data.access_token);
      setAuthRoleType(roleType);
    }

    return response.data;
  },

  /**
   * Hydrate authenticated user profile using active Sanctum Bearer token.
   * Returns null if unauthenticated, session expired, or token revoked.
   */
  async fetchProfile(overrideRoleType?: AuthRoleType): Promise<AuthUser | null> {
    const token = getAuthToken();
    if (!token) {
      return null;
    }

    const roleType = overrideRoleType || getAuthRoleType() || 'admin';
    const endpoint =
      roleType === 'admin'
        ? API_ENDPOINTS.auth.adminMe
        : API_ENDPOINTS.auth.userMe;

    try {
      const response = await get<AuthUser>(endpoint);
      return response.data;
    } catch (error) {
      const err = error as { status?: number };
      if (err.status === 401 || err.status === 403) {
        clearAuthToken();
      }
      return null;
    }
  },

  /**
   * Revoke current token on server and wipe client session state.
   */
  async logout(overrideRoleType?: AuthRoleType): Promise<void> {
    const token = getAuthToken();
    const roleType = overrideRoleType || getAuthRoleType() || 'admin';
    const endpoint =
      roleType === 'admin'
        ? API_ENDPOINTS.auth.adminLogout
        : API_ENDPOINTS.auth.userLogout;

    if (token) {
      try {
        await post(endpoint);
      } catch {
        // Even if network or revocation fails, local session is cleared safely
      }
    }

    clearAuthToken();
  },

  /**
   * Verify if user possesses a specific Spatie role.
   */
  hasRole(user: AuthUser | null, role: string): boolean {
    if (!user || !user.roles) {
      return false;
    }
    return user.roles.includes(role);
  },

  /**
   * Verify if user possesses any of the specified roles.
   */
  hasAnyRole(user: AuthUser | null, roles: string[]): boolean {
    if (!user || !user.roles) {
      return false;
    }
    return roles.some((role) => user.roles!.includes(role));
  },

  /**
   * Verify if user has a specific Spatie permission (super-admin bypasses checks).
   */
  hasPermission(user: AuthUser | null, permission: string): boolean {
    if (!user) {
      return false;
    }
    if (user.roles?.includes('super-admin')) {
      return true;
    }
    if (!user.permissions) {
      return false;
    }
    return user.permissions.includes(permission);
  },

  /**
   * Verify if user possesses any of the specified permissions.
   */
  hasAnyPermission(user: AuthUser | null, permissions: string[]): boolean {
    if (!user) {
      return false;
    }
    if (user.roles?.includes('super-admin')) {
      return true;
    }
    if (!user.permissions) {
      return false;
    }
    return permissions.some((perm) => user.permissions!.includes(perm));
  },
};
