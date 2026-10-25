import React, { createContext, useContext, useState, useEffect, useCallback, ReactNode } from 'react';
import { AuthContextValue, AuthUser, AuthRoleType, LoginCredentials } from '../types/auth';
import { authService } from '../services/authService';
import { getAuthToken, getAuthRoleType } from '../utils/env';

export const AuthContext = createContext<AuthContextValue | undefined>(undefined);

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [roleType, setRoleType] = useState<AuthRoleType | null>(() => getAuthRoleType());
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  /**
   * Check active session and hydrate user profile from Sanctum.
   */
  const checkAuth = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    const token = getAuthToken();
    if (!token) {
      setUser(null);
      setIsAuthenticated(false);
      setIsLoading(false);
      return;
    }

    try {
      const activeRole = getAuthRoleType() || 'admin';
      const profile = await authService.fetchProfile(activeRole);

      if (profile) {
        setUser(profile);
        setRoleType(activeRole);
        setIsAuthenticated(true);
      } else {
        setUser(null);
        setIsAuthenticated(false);
      }
    } catch (err) {
      const e = err as { message?: string };
      setUser(null);
      setIsAuthenticated(false);
      setError(e.message || 'Session verification failed.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  /**
   * Login using credentials and role type.
   */
  const login = useCallback(
    async (credentials: LoginCredentials, selectedRole: AuthRoleType = 'admin'): Promise<boolean> => {
      setIsLoading(true);
      setError(null);

      try {
        const tokenData = await authService.login(credentials, selectedRole);
        setUser(tokenData.user);
        setRoleType(selectedRole);
        setIsAuthenticated(true);
        setError(null);
        return true;
      } catch (err) {
        const e = err as { message?: string };
        setUser(null);
        setIsAuthenticated(false);
        setError(e.message || 'Invalid credentials or authentication failure.');
        return false;
      } finally {
        setIsLoading(false);
      }
    },
    []
  );

  /**
   * Logout and revoke Sanctum access token.
   */
  const logout = useCallback(async () => {
    setIsLoading(true);
    try {
      await authService.logout(roleType || undefined);
    } finally {
      setUser(null);
      setRoleType(null);
      setIsAuthenticated(false);
      setError(null);
      setIsLoading(false);
    }
  }, [roleType]);

  const clearError = useCallback(() => {
    setError(null);
  }, []);

  const hasRole = useCallback(
    (role: string): boolean => {
      return authService.hasRole(user, role);
    },
    [user]
  );

  const hasPermission = useCallback(
    (permission: string): boolean => {
      return authService.hasPermission(user, permission);
    },
    [user]
  );

  const hasAnyRole = useCallback(
    (roles: string[]): boolean => {
      return authService.hasAnyRole(user, roles);
    },
    [user]
  );

  const hasAnyPermission = useCallback(
    (permissions: string[]): boolean => {
      return authService.hasAnyPermission(user, permissions);
    },
    [user]
  );

  // Hydrate authentication on initial mount
  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  // Listen for unauthorized events emitted by API client interceptor
  useEffect(() => {
    const handleUnauthorized = () => {
      setUser(null);
      setIsAuthenticated(false);
      setError('Your session has expired. Please authenticate again.');
    };

    if (typeof window !== 'undefined') {
      window.addEventListener('innova:auth-unauthorized', handleUnauthorized);
      return () => {
        window.removeEventListener('innova:auth-unauthorized', handleUnauthorized);
      };
    }
  }, []);

  const value: AuthContextValue = {
    user,
    roleType,
    isAuthenticated,
    isLoading,
    error,
    login,
    logout,
    checkAuth,
    clearError,
    hasRole,
    hasPermission,
    hasAnyRole,
    hasAnyPermission,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

/**
 * Custom hook to access the authentication context.
 */
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
