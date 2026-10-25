import React, { ReactNode } from 'react';
import { useAuth } from '../../hooks/useAuth';
import { Alert, LoadingSpinner } from '../common';

interface RequireAuthProps {
  children: ReactNode;
  fallback?: ReactNode;
}

/**
 * Ensures user is authenticated before rendering children.
 */
export const RequireAuth: React.FC<RequireAuthProps> = ({ children, fallback }) => {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', padding: '2rem' }}>
        <LoadingSpinner size="md" label="Verifying session..." />
      </div>
    );
  }

  if (!isAuthenticated) {
    if (fallback) {
      return <>{fallback}</>;
    }
    return (
      <Alert type="warning" title="Authentication Required">
        You must be logged in to access this section. Please authenticate using the form below.
      </Alert>
    );
  }

  return <>{children}</>;
};

interface RequireRoleProps {
  children: ReactNode;
  role?: string;
  roles?: string[];
  fallback?: ReactNode;
}

/**
 * Ensures authenticated user possesses specified role(s).
 */
export const RequireRole: React.FC<RequireRoleProps> = ({
  children,
  role,
  roles,
  fallback,
}) => {
  const { hasRole, hasAnyRole, isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return null;
  }

  if (!isAuthenticated) {
    return null;
  }

  let authorized = false;
  if (role) {
    authorized = hasRole(role);
  } else if (roles && roles.length > 0) {
    authorized = hasAnyRole(roles);
  }

  if (!authorized) {
    if (fallback) {
      return <>{fallback}</>;
    }
    return (
      <Alert type="error" title="Access Denied">
        You do not possess the required administrative role to view this information.
      </Alert>
    );
  }

  return <>{children}</>;
};

interface RequirePermissionProps {
  children: ReactNode;
  permission?: string;
  permissions?: string[];
  fallback?: ReactNode;
}

/**
 * Ensures authenticated user possesses specified Spatie permission(s).
 */
export const RequirePermission: React.FC<RequirePermissionProps> = ({
  children,
  permission,
  permissions,
  fallback,
}) => {
  const { hasPermission, hasAnyPermission, isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return null;
  }

  if (!isAuthenticated) {
    return null;
  }

  let authorized = false;
  if (permission) {
    authorized = hasPermission(permission);
  } else if (permissions && permissions.length > 0) {
    authorized = hasAnyPermission(permissions);
  }

  if (!authorized) {
    if (fallback) {
      return <>{fallback}</>;
    }
    return (
      <Alert type="error" title="Insufficient Privileges">
        You do not possess the required permission to perform or view this operation.
      </Alert>
    );
  }

  return <>{children}</>;
};
