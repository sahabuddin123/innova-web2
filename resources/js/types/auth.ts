/**
 * INNOVA WEB — React Authentication Foundation Types (Phase 3.1)
 *
 * Strongly-typed contracts matching Laravel Sanctum and multi-guard authentication:
 * - Admin identity with Spatie roles & permissions
 * - User (Student) identity
 * - Standardized API token envelopes
 */

export type AuthRoleType = 'admin' | 'user';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  is_admin: boolean;
  roles?: string[];
  permissions?: string[];
}

export interface LoginCredentials {
  email: string;
  password: string;
  device_name?: string;
}

export interface AuthTokenData {
  token_type: string;
  access_token: string;
  user: AuthUser;
}

export interface AuthState {
  user: AuthUser | null;
  roleType: AuthRoleType | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
}

export interface AuthContextValue extends AuthState {
  login: (credentials: LoginCredentials, roleType?: AuthRoleType) => Promise<boolean>;
  logout: () => Promise<void>;
  checkAuth: () => Promise<void>;
  clearError: () => void;
  hasRole: (role: string) => boolean;
  hasPermission: (permission: string) => boolean;
  hasAnyRole: (roles: string[]) => boolean;
  hasAnyPermission: (permissions: string[]) => boolean;
}
